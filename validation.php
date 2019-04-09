<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/../../init.php');

$context = Context::getContext();
$cart = $context->cart;
$bankwire = Module::getInstanceByName('cy_multibankwire');

if ($cart->id_customer == 0 OR $cart->id_address_delivery == 0 OR $cart->id_address_invoice == 0 OR !$bankwire->active)
	Tools::redirect('index.php?controller=order&step=1');

// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
$authorized = false;
foreach (Module::getPaymentModules() as $module)
	if ($module['name'] == 'cy_multibankwire')
	{
		$authorized = true;
		break;
	}
if (!$authorized)
	die($bankwire->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Wirepayment.Shop'));

$customer = new Customer((int)$cart->id_customer);

if (!Validate::isLoadedObject($customer))
	Tools::redirect('index.php?controller=order&step=1');

$currency = $context->currency;
$total = (float)($cart->getOrderTotal(true, Cart::BOTH));

$bankwire->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $bankwire->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);

$order = new Order($bankwire->currentOrder);
Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$bankwire->id.'&id_order='.$bankwire->currentOrder.'&key='.$customer->secure_key);
