<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class cy_multibankwire extends PaymentModule
{
    const FLAG_DISPLAY_PAYMENT_INVITE = 'CY_MILTI_BANK_WIRE_PAYMENT_INVITE';

    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'cy_multibankwire';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.4';
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        $this->author = 'Cypis.net';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('CY_MILTI_BANK_WIRE_DETAILS', 'CY_MILTI_BANK_WIRE_OWNER', 'CY_MILTI_BANK_WIRE_ADDRESS', 'CY_MILTI_BANK_WIRE_RESERVATION_DAYS'));
       
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->getTranslator()->trans('Cy Multi Wire payment', array(), 'Modules.cy_multibankwire.Admin');
        $this->description = $this->getTranslator()->trans('Accept payments by bank transfer.', array(), 'Modules.cy_multibankwire.Admin');
        $this->confirmUninstall = $this->getTranslator()->trans('Are you sure about removing these details?', array(), 'Modules.cy_multibankwire.Admin');
        if (!isset($this->owner) || !isset($this->details) || !isset($this->address)) {
            $this->warning = $this->getTranslator()->trans('Account owner and account details must be configured before using this module.', array(), 'Modules.cy_multibankwire.Admin');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->getTranslator()->trans('No currency has been set for this module.', array(), 'Modules.cy_multibankwire.Admin');
        }

        $this->extra_mail_vars = array(
                                        '{bankwire_owner}' => '[CY_BANKWIRE_OWNER]',
                                        '{bankwire_details}' => '[CY_BANKWIRE_DETAILS]',
                                        '{bankwire_address}' => '[CY_BANKWIRE_ADDRESS]',
                                        );
    }

    public function install()
    {
        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
        if (!parent::install() 
        	|| !$this->registerHook('paymentReturn') 
        	|| !$this->registerHook('ActionEmailAddAfterContent') 
        	|| !$this->registerHook('paymentOptions')) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        $languages = Language::getLanguages(false);
        $curencies = Currency::getCurrencies();
        foreach ($languages as $lang) {
        	foreach ($curencies as $c) 
				{
					Configuration::deleteByName($c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS', $lang['id_lang']);
            		Configuration::deleteByName($c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER', $lang['id_lang']);
               		Configuration::deleteByName($c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS', $lang['id_lang']);
				}
        
            if (!Configuration::deleteByName('CY_MILTI_BANK_WIRE_CUSTOM_TEXT', $lang['id_lang'])) {
                return false;
            }
        }
		
		if (!Configuration::deleteByName('CY_MILTI_BANK_WIRE_RESERVATION_DAYS')
                || !Configuration::deleteByName(self::FLAG_DISPLAY_PAYMENT_INVITE)
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE));
			
			$languages = Language::getLanguages(false);
            $curencies = Currency::getCurrencies();
            foreach ($languages as $lang) {
            	foreach ($curencies as $c) {
			            if (!Tools::getValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS_'.$lang['id_lang'])) {
			                $this->_postErrors[] = $this->getTranslator()->trans('Account details are required.', array(), 'Modules.cy_multibankwire.Admin')." [".$c['iso_code']."][".$lang['iso_code']."]";
			            } elseif (!Tools::getValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER_'.$lang['id_lang'])) {
			                $this->_postErrors[] = $this->getTranslator()->trans('Account owner is required.', array(), "Modules.cy_multibankwire.Admin")." [".$c['iso_code']."][".$lang['iso_code']."]";
			            }
					}
			}
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {

            $custom_text = array();
            $languages = Language::getLanguages(false);
            $curencies = Currency::getCurrencies();
            foreach ($languages as $lang) {
            	foreach ($curencies as $c) {
	               
	                if (Tools::getIsset($c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS_'.$lang['id_lang'])) {
	                    $cy_milti_bank_wire_address[$lang['id_lang']] = Tools::getValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS_'.$lang['id_lang']);
	                }
	                if (Tools::getIsset($c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS_'.$lang['id_lang'])) {
	                    $cy_milti_bank_wire_details[$lang['id_lang']] = Tools::getValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS_'.$lang['id_lang']);
	                }
	               
	                if (Tools::getIsset($c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER_'.$lang['id_lang'])) {
	                    $cy_milti_bank_wire_owner[$lang['id_lang']] = Tools::getValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER_'.$lang['id_lang']);
	                }
				}
				
                if (Tools::getIsset('CY_MILTI_BANK_WIRE_CUSTOM_TEXT_'.$lang['id_lang'])) {
	                    $custom_text[$lang['id_lang']] = Tools::getValue('CY_MILTI_BANK_WIRE_CUSTOM_TEXT_'.$lang['id_lang']);
	           	}
            }
            Configuration::updateValue('CY_MILTI_BANK_WIRE_RESERVATION_DAYS', Tools::getValue('CY_MILTI_BANK_WIRE_RESERVATION_DAYS'));
            Configuration::updateValue('CY_MILTI_BANK_WIRE_CUSTOM_TEXT', $custom_text);
            
            foreach ($curencies as $c) {
            	Configuration::updateValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS', $cy_milti_bank_wire_details );
            	Configuration::updateValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER', $cy_milti_bank_wire_owner );
            	Configuration::updateValue($c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS', $cy_milti_bank_wire_address );
			}
            
        }
        $this->_html .= $this->displayConfirmation($this->getTranslator()->trans('Settings updated', array(), 'Admin.Global'));
    }

    protected function _displayBankWire()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayBankWire();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->getTranslator()->trans('CY Pay by bank wire', array(), 'Modules.cy_multibankwire.Shop'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:cy_multibankwire/views/templates/hook/cy_multibankwire_intro.tpl'));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active || !Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) {
            return;
        }
		
		 $currency_order = new Currency($params['order']->id_currency);
		 
		 $bankwireOwner = Configuration::get($currency_order->iso_code.'_CY_MILTI_BANK_WIRE_OWNER', $params['order']->id_lang );
		 $bankwireDetails = Tools::nl2br( Configuration::get($currency_order->iso_code.'_CY_MILTI_BANK_WIRE_DETAILS', $params['order']->id_lang ) );
		 $bankwireAddress = Tools::nl2br( Configuration::get($currency_order->iso_code.'_CY_MILTI_BANK_WIRE_ADDRESS', $params['order']->id_lang ) );
		 
		 
		
        $state = $params['order']->getCurrentState();
        if (
            in_array(
                $state,
                array(
                    Configuration::get('PS_OS_BANKWIRE'),
                    Configuration::get('PS_OS_OUTOFSTOCK'),
                    Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
                )
        )) {
            if (!$bankwireOwner) {
                $bankwireOwner = '___________';
            }

            if (!$bankwireDetails) {
                $bankwireDetails = '___________';
            }

            if (!$bankwireAddress) {
                $bankwireAddress = '___________';
            }

            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'bankwireDetails' => $bankwireDetails,
                'bankwireAddress' => $bankwireAddress,
                'bankwireOwner' => $bankwireOwner,
                'status' => 'ok',
                'reference' => $params['order']->reference,
                'contact_url' => $this->context->link->getPageLink('contact', true)
            ));
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed',
                    'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:cy_multibankwire/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
    	
    	$curencies = Currency::getCurrencies();
    	$tabs= array();
    	foreach($curencies as $c){
    			$tabs[$c['iso_code']]= $c['name'];
				$formfields[] =[
			            'type' => 'text',
			                'label' => $this->getTranslator()->trans('Account owner', array(), 'Modules.cy_multibankwire.Admin')." [".$c['iso_code']."]",
			                'name' => $c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER',
			                'required' => true,
			                'lang' => true,
			                'tab' => $c['iso_code'],
			            ];
			            
				$formfields[] =[
			                'type' => 'textarea',
			                'label' => $this->getTranslator()->trans('Account details', array(), 'Modules.cy_multibankwire.Admin')." [".$c['iso_code']."]",
			                'name' => $c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS',
			                'desc' => $this->getTranslator()->trans('Such as bank branch, IBAN number, BIC, etc.', array(), 'Modules.cy_multibankwire.Admin'),
			                'required' => true,
			                'lang' => true,
			                'tab' => $c['iso_code'],
			           ];
				$formfields[] =[
			                'type' => 'textarea',
			                'label' => $this->getTranslator()->trans('Bank address', array(), 'Modules.cy_multibankwire.Admin')." [".$c['iso_code']."]",
			                'name' => $c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS',
			                'required' => true,
			                'lang' => true,
			                'tab' => $c['iso_code'],
			           ];
		};

                   
                
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->getTranslator()->trans('Account details', array(), 'Modules.cy_multibankwire.Admin'),
                    'icon' => 'icon-envelope'
                ),
                'input' => $formfields,
                'submit' => array(
                    'title' => $this->getTranslator()->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $fields_form_customization = array(
            'form' => array(
	           	'tabs'	=> $tabs,
                'legend' => array(
                    'title' => $this->getTranslator()->trans('Customization', array(), 'Modules.cy_multibankwire.Admin'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->getTranslator()->trans('Reservation period', array(), 'Modules.cy_multibankwire.Admin'),
                        'desc' => $this->getTranslator()->trans('Number of days the items remain reserved', array(), 'Modules.cy_multibankwire.Admin'),
                        'name' => 'CY_MILTI_BANK_WIRE_RESERVATION_DAYS',
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->getTranslator()->trans('Information to the customer', array(), 'Modules.cy_multibankwire.Admin'),
                        'name' => 'CY_MILTI_BANK_WIRE_CUSTOM_TEXT',
                        'desc' => $this->getTranslator()->trans('Information on the bank transfer (processing time, starting of the shipping...)', array(), 'Modules.cy_multibankwire.Admin'),
                        'lang' => true
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->getTranslator()->trans('Display the invitation to pay in the order confirmation page', array(), 'Modules.cy_multibankwire.Admin'),
                        'name' => self::FLAG_DISPLAY_PAYMENT_INVITE,
                        'is_bool' => true,
                        'hint' => $this->getTranslator()->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.cy_multibankwire.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->getTranslator()->trans('Enabled', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->getTranslator()->trans('Disabled', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->getTranslator()->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_customization));
    }

    public function getConfigFieldsValues()
    {
        $custom_text = array();
        $cy_milti_bank_wire_details = array();
        $cy_milti_bank_wire_owner_ = array();
        $cy_milti_bank_wire_address = array();
        
        $curencies = Currency::getCurrencies();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
         	
         	foreach($curencies as $c){
	         	$cy_milti_bank_wire_details[$c['iso_code']][$lang['id_lang']] = Tools::getValue(
	                $c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS_'.$lang['id_lang'],
	                Configuration::get($c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS', $lang['id_lang'])
	            );	
	        	$cy_milti_bank_wire_owner[$c['iso_code']][$lang['id_lang']] = Tools::getValue(
	                $c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER_'.$lang['id_lang'],
	                Configuration::get($c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER', $lang['id_lang'])
	            );	
	        	$cy_milti_bank_wire_address[$c['iso_code']][$lang['id_lang']] = Tools::getValue(
	                $c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS_'.$lang['id_lang'],
	                Configuration::get($c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS', $lang['id_lang'])
	            );	
			}
            $custom_text[$lang['id_lang']] = Tools::getValue(
                'CY_MILTI_BANK_WIRE_CUSTOM_TEXT_'.$lang['id_lang'],
                Configuration::get('CY_MILTI_BANK_WIRE_CUSTOM_TEXT', $lang['id_lang'])
            );
        }
		
		$out = array();
		
		foreach($curencies as $c){
			$out[$c['iso_code'].'_CY_MILTI_BANK_WIRE_DETAILS'] = $cy_milti_bank_wire_details[$c['iso_code']];
        	$out[$c['iso_code'].'_CY_MILTI_BANK_WIRE_OWNER'] = $cy_milti_bank_wire_owner[$c['iso_code']];
        	$out[$c['iso_code'].'_CY_MILTI_BANK_WIRE_ADDRESS'] = $cy_milti_bank_wire_address[$c['iso_code']];
		}
            
        $out['CY_MILTI_BANK_WIRE_DETAILS'] = $cy_milti_bank_wire_details;
        $out['CY_MILTI_BANK_WIRE_OWNER'] = $cy_milti_bank_wire_owner;
        $out['CY_MILTI_BANK_WIRE_ADDRESS'] = $cy_milti_bank_wire_address;
        $out['CY_MILTI_BANK_WIRE_RESERVATION_DAYS'] = Tools::getValue('CY_MILTI_BANK_WIRE_RESERVATION_DAYS', Configuration::get('CY_MILTI_BANK_WIRE_RESERVATION_DAYS'));
        $out['CY_MILTI_BANK_WIRE_CUSTOM_TEXT'] = $custom_text;
        $out[self::FLAG_DISPLAY_PAYMENT_INVITE] = Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE));
    
        return $out;
    }

    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;
        $total = sprintf(
            $this->getTranslator()->trans('%1$s (tax incl.)', array(), 'Modules.cy_multibankwire.Shop'),
            Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
        );

 		 $currency_order = new Currency($cart->id_currency);
		 $lang_order = new Language($cart->id_lang);
		 $bankwireOwner = Configuration::get($currency_order->iso_code.'_CY_MILTI_BANK_WIRE_OWNER', $cart->id_lang );
		 $bankwireDetails = Tools::nl2br( Configuration::get($currency_order->iso_code.'_CY_MILTI_BANK_WIRE_DETAILS', $cart->id_lang ) );
		 $bankwireAddress = Tools::nl2br( Configuration::get($currency_order->iso_code.'_CY_MILTI_BANK_WIRE_ADDRESS', $cart->id_lang ) );
		 
		 
		 
        if (!$bankwireOwner) {
            $bankwireOwner = '___________';
        }

        if (!$bankwireDetails) {
            $bankwireDetails = '___________';
        }

        if (!$bankwireAddress) {
            $bankwireAddress = '___________';
        }

        $bankwireReservationDays = Configuration::get('CY_MILTI_BANK_WIRE_RESERVATION_DAYS');
        if (false === $bankwireReservationDays) {
            $bankwireReservationDays = 7;
        }

        $bankwireCustomText = Tools::nl2br(Configuration::get('CY_MILTI_BANK_WIRE_CUSTOM_TEXT', $this->context->language->id));
        if (false === $bankwireCustomText) {
            $bankwireCustomText = '';
        }

        return array(
            'total' => $total,
            'bankwireDetails' => $bankwireDetails,
            'bankwireAddress' => $bankwireAddress,
            'bankwireOwner' => $bankwireOwner,
            'bankwireReservationDays' => (int)$bankwireReservationDays,
            'bankwireCustomText' => $bankwireCustomText,
        );
    }
    
    public function hookActionEmailAddAfterContent($params) {
        $content = '';               
		if ($params['template'] == 'bankwire') { // Let's edit content of Order's Confirmation email
			    $from=array('[CY_BANKWIRE_OWNER]','[CY_BANKWIRE_DETAILS]','[CY_BANKWIRE_ADDRESS]');
        		$to=array('konto 1', 'konto 2', 'konto 3');
        		
		}
		
		$params['template_html'] = str_replace($from,$to, $params['template_html']); // and add text to end of {products} variable
	}

}
