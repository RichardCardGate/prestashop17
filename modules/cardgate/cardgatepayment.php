<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class CardgatePayment extends PaymentModule
{
    public $version = '1.7.21';
    public $tab = 'payments_gateways';
    public $author = 'CardGate';
    public $shop_version = _PS_VERSION_;
    public $currencies = true;
    public $currencies_mode = 'radio';
    public $_html = '';
    protected $_paymentHookTpl = '';

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        return true;

        $payment = strtoupper($this->paymentcode);

        if (!parent::install() or !$this->registerHook('payment')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        $paymentcode = $this->paymentcode;

        if ($paymentcode == '') {
            return false;
        }

        $paymentcode = strtoupper($paymentcode);

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    public function checkPaymentCurrency($currency, $payment_method)
    {
        $strictly_euro = in_array($payment_method, ['cardgateideal',
            'cardgateidealqr',
            'cardgatebancontact',
            'cardgatebanktransfer',
            'cardgatebillink',
            'cardgatesofortbanking',
            'cardgatedirectdebit',
            'cardgateonlineueberweisen',
            'cardgatespraypay']);
        if ($strictly_euro && $currency != 'EUR') {
            return false;
        }

        $strictly_pln = in_array($payment_method, ['cardgateprzelewy24']);
        if ($strictly_pln && $currency != 'PLN') {
            return false;
        }

        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }
        if (!$this->checkCurrency($params['cart'])) {
            return [];
        }

        $currency_order = new Currency($params['cart']->id_currency);
        $currency = $currency_order->iso_code;
        if (!$this->checkPaymentCurrency($currency, $this->name)) {
            return [];
        }

        $paymentOption = new PaymentOption();
        $costText = '';
        $additionalInformation = null;

        $display = Configuration::get('CARDGATE_PAYMENT_DISPLAY');

        if ($display == 'textandlogo' || $display == 'textonly') {
            $actionText = $this->l('Pay with') . ' ' . $this->paymentname . $costText;
        } else {
            $actionText = null;
        }

        if ($display == 'textandlogo' || $display == 'logoonly') {
            $logo = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.gif');
        } else {
            $logo = null;
        }

        $paymentOption->setCallToActionText($actionText)
                      ->setAction($this->context->link->getModuleLink('cardgate', 'validation', [], true))
                      ->setInputs($this->paymentData())
                      ->setAdditionalInformation($additionalInformation)
                      ->setLogo($logo);

        $payment_options = [
            $paymentOption,
        ];

        return $payment_options;
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

    public function displayConf()
    {
        $this->_html = $this->displayConfirmation($this->l('Settings updated'));
    }

    public function paymentData()
    {
        $data = [
            'option' => [
                'name' => 'option',
                'type' => 'hidden',
                'value' => $this->paymentcode,
            ],
            'paymentname' => [
                'name' => 'paymentname',
                'type' => 'hidden',
                'value' => $this->paymentname,
            ],
        ];

        return $data;
    }

    protected function generateForm()
    {
        return false;
    }
}
