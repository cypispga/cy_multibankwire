<?php

class cy_multibankwirePaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order');

		$total = sprintf(
			$this->module->l('%1$s (tax incl.)'),
			Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
		);

		$this->context->smarty->assign(array(
      'back_url' => $this->context->link->getPageLink('order', true, NULL, "step=3"),
			'confirm_url' => $this->context->link->getModuleLink('cy_multibankwire', 'validation', [], true),
			'image_url' => $this->module->getPathUri() . 'cy_multibankwire.jpg',
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->module->getCurrency((int)$cart->id_currency),
			'total' => $total,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('payment_execution.tpl');
	}
}
