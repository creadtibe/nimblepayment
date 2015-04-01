<?php
/**
* 2007-2014 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once dirname(__FILE__).'/../../library/sdk/lib/Nimble/base/NimbleAPI.php';
use Nimble\Base\NimbleAPI;

class NimblePaymentPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;
	public $nimblepayment_client_secret = '';
	public $nimblepayment_client_id = '';
	public $nimblepayment_url_ok = '';
	public $nimblepayment_url_ko = '';
	public $nimblepayment_urltpv = '';
	public $type_error = 0; /** Ninguno */
	public $nimbleapi;
	/**
	* @see FrontController::initContent()
	*/
	public function initContent()
	{
		parent::initContent();
		$cart = $this->context->cart;
		if (!$this->module->checkCurrencyNimble($cart))
			Tools::redirect('index.php?controller=order');
		if ($this->validatePaymentData() == true)
		{
			$total = str_replace('.', '', $cart->getOrderTotal(true, Cart::BOTH));
			$numpedido = str_pad($cart->id, 8, '0', STR_PAD_LEFT);
			$paramurl = $numpedido.md5($numpedido.$this->nimblepayment_client_secret.$total);
			if ($this->authentified() == true)
			$this->sendPayment($total, $paramurl);
		}
		$this->context->smarty->assign(array(
			'this_path' => $this->module->getPathUri(),
			'error' => $this->type_error,
			));
	}

	public function validatePaymentData()
	{
		$this->nimblepayment_client_secret = Tools::getValue('NIMBLEPAYMENT_CLIENT_SECRET', Configuration::get('NIMBLEPAYMENT_CLIENT_SECRET'));
		$this->nimblepayment_client_id = Tools::getValue('NIMBLEPAYMENT_CLIENT_ID', Configuration::get('NIMBLEPAYMENT_CLIENT_ID'));
		$this->nimblepayment_urltpv = Tools::getValue('NIMBLEPAYMENT_URLTPV', Configuration::get('NIMBLEPAYMENT_URLTPV'));
		$this->nimblepayment_url_ok = Tools::getValue('NIMBLEPAYMENT_URL_OK', Configuration::get('NIMBLEPAYMENT_URL_OK'));
		$this->nimblepayment_url_ko = Tools::getValue('NIMBLEPAYMENT_URL_KO', Configuration::get('NIMBLEPAYMENT_URL_KO'));

		if ($this->nimblepayment_url_ok != 'http://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'module/nimblepayment/paymentok'
		|| $this->nimblepayment_url_ko != 'http://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'module/nimblepayment/paymentko')
		{
			Configuration::updateValue('NIMBLEPAYMENT_URL_OK', 'http://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'module/nimblepayment/paymentok');
			Configuration::updateValue('NIMBLEPAYMENT_URL_KO', 'http://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'module/nimblepayment/paymentko');
			$this->nimblepayment_url_ok = Tools::getValue('NIMBLEPAYMENT_URL_OK', Configuration::get('NIMBLEPAYMENT_URL_OK'));
			$this->nimblepayment_url_ko = Tools::getValue('NIMBLEPAYMENT_URL_KO', Configuration::get('NIMBLEPAYMENT_URL_KO'));
		}
		if ($this->nimblepayment_client_secret == '' || $this->nimblepayment_client_id == '')
		{
			$this->setTemplate('payment_failed.tpl');
			$this->type_error = 1;
			return false;
		}
		return true;
	}

	public function authentified()
	{
		$params = array(
			'clientId' => $this->nimblepayment_client_id,
			'clientSecret' => $this->nimblepayment_client_secret,
			'mode' => $this->nimblepayment_urltpv
		);

		try
		{
			$this->nimbleapi = new NimbleAPI($params);
		}
		catch (Exception $e)
		{
			$this->type_error = $e->getMessage(); /** Autentificación */
			$this->setTemplate('payment_failed.tpl');
			return false;
		}
		return true;
	}

	public function sendPayment($total, $paramurl)
	{
		$payment = array(
			'amount' => (int)$total,
			'currency' => 'EUR',
			'paymentSuccessUrl' => $this->nimblepayment_url_ok.'?paymentcode='.$paramurl,
			'paymentErrorUrl' => $this->nimblepayment_url_ko.'?paymentcode='.$paramurl
		);

		try
		{
			$response = Nimble\Api\Payment::SendPaymentClient($this->nimbleapi, $payment);
		}
		catch (Exception $e)
		{
			$this->type_error = 3; /** Problema en el envío del pago. */
			$this->setTemplate('payment_failed.tpl');
			return false;
		}
		if (!isset($response['error']))
		{
			if ($response['result']['code'] == 200)
				Tools::redirect($response['data'][0]['paymentUrl']);
			else
			{
				$this->setTemplate('payment_failed.tpl');
				$this->type_error = 3; /** Fallo en el envio a la pasarela */
			}
		}
		else
		{
			$this->type_error = 4; /**Se detecto una anomalia en los datos enviados. */
			$this->setTemplate('payment_failed.tpl');
			return false;
		}
	}
}