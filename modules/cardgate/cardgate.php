<?php

if (file_exists(dirname(__FILE__) . '/../cardgate/cardgate.php')) {
    require_once dirname(__FILE__) . '/../cardgate/cardgate.php';
} else {
    $GLOBALS['CARDGATENOTFOUND'] = 1;
    if (!class_exists('CardgatePayment')) {
        class CardgatePayment extends PaymentModule
        {
            public function get_url()
            {
            }
        }
    }
}

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'cardgatepayment.php';

class Cardgate extends CardgatePayment
{
    public $shop_version = _PS_VERSION_;

    public function __construct()
    {
        Configuration::updateValue('CARDGATE_MODULE_VERSION', '1.7.19');
        $this->name = 'cardgate';
        $this->paymentcode = 'cardgate';
        $this->paymentname = 'CardGate';
        $this->tab = 'payments_gateways';
        $this->version = Configuration::get('CARDGATE_MODULE_VERSION');
        $this->author = 'CardGate';
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'radio';

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('CardGate Bank common');
        $this->description = $this->l('CardGate Bank base module.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the CardGate module?');
        Configuration::updateValue('CARDGATE_VERSION', $this->version);
    }

    public function install()
    {
        $this->createOrderState();

        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function createOrderState()
    {
        if (!Configuration::get('CARDGATE_PENDING')) {
            $order_state = new OrderState();
            $order_state->name = [];

            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'nl') {
                    $order_state->name[$language['id_lang']] = 'Wachten op CardGate betaling';
                } elseif (Tools::strtolower($language['iso_code']) == 'de') {
                    $order_state->name[$language['id_lang']] = 'Warten auf Zahlungseingang von CardGate';
                } elseif (Tools::strtolower($language['iso_code']) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'En attente du paiement par CardGate';
                } elseif (Tools::strtolower($language['iso_code']) == 'es') {
                    $order_state->name[$language['id_lang']] = 'En espera de pago por CardGate';
                } elseif (Tools::strtolower($language['iso_code']) == 'it') {
                    $order_state->name[$language['id_lang']] = 'In attesa di pagamento con CardGate';
                } else {
                    $order_state->name[$language['id_lang']] = 'Awaiting CardGate payment';
                }
            }

            $order_state->send_email = true;
            $order_state->template = 'payment';
            $order_state->color = 'RoyalBlue';

            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;

            $order_state->invoice = false;
            $order_state->paid = false;
            $order_state->unremovable = true;

            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_ . 'cardgate/logo.gif';
                $destination = dirname(__FILE__) . '/../../img/os/' . (int) $order_state->id . '.gif';
                copy($source, $destination);
            }
            Configuration::updateGlobalValue('CARDGATE_PENDING', (int) $order_state->id);
        }
    }

    public function displayConf()
    {
        $this->_html = $this->displayConfirmation($this->l('Settings updated'));
    }

    public function alterName($name)
    {
        $name = ($name == 'mc' ? 'mistercash' : $name);

        return $name;
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $mode = (string) Tools::getValue('CARDGATE_TEST_MODE');
            $siteid = (string) Tools::getValue('CARDGATE_SITE_ID');
            $hashkey = (string) Tools::getValue('CARDGATE_HASH_KEY');
            $merchantid = (string) Tools::getValue('CARDGATE_MERCHANT_ID');
            $merchantapikey = (string) Tools::getValue('CARDGATE_MERCHANT_API_KEY');
            $paymentdisplay = (string) Tools::getValue('CARDGATE_PAYMENT_DISPLAY');
            $my_module_field_names = $this->myModelFieldNames();
            foreach ($my_module_field_names as $key => $my_module_field_name) {
                Configuration::updateValue($my_module_field_name, (string) Tools::getValue($my_module_field_name));
            }

            // no errors so update the values
            Configuration::updateValue('CARDGATE_TEST_MODE', $mode);
            Configuration::updateValue('CARDGATE_SITE_ID', $siteid);
            Configuration::updateValue('CARDGATE_HASH_KEY', $hashkey);
            Configuration::updateValue('CARDGATE_MERCHANT_ID', $merchantid);
            Configuration::updateValue('CARDGATE_MERCHANT_API_KEY', $merchantapikey);
            Configuration::updateValue('CARDGATE_PAYMENT_DISPLAY', $paymentdisplay);

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $my_modules = [];
        $my_module_field_names = [];
        $modules = Module::getModulesOnDisk();
        foreach ($modules as $module) {
            if (strstr($module->name, 'cardgate') !== false) {
                $name = str_replace('cardgate', '', $module->name);
                if ($name != '') {
                    $name = $this->alterName($name);
                    $my_modules[] = $name;
                    $name = '';
                }
            }
        }

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('General Settings'),
                'image' => '../img/admin/edit.gif',
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->l('Mode'),
                    'name' => 'CARDGATE_TEST_MODE',
                    'required' => false,
                    'default_value' => 1,
                    'options' => [
                        'query' => [
                            [
                                'id' => 1,
                                'name' => 'test',
                            ],
                            [
                                'id' => 0,
                                'name' => 'live',
                            ],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Site ID'),
                    'name' => 'CARDGATE_SITE_ID',
                    'size' => 64,
                    'required' => true,
                    'hint' => $this->l('The CardGate Site ID, which you can find in your CardGate back-office'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Hash key'),
                    'name' => 'CARDGATE_HASH_KEY',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('The CardGate hash key, which you can find in your CardGate back-office'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Merchant ID'),
                    'name' => 'CARDGATE_MERCHANT_ID',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('The Merchant ID, which you can find in your CardGate back-office'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('API key'),
                    'name' => 'CARDGATE_MERCHANT_API_KEY',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('The API Key, which you can obtain from your Accountmanager.'),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Payment display'),
                    'name' => 'CARDGATE_PAYMENT_DISPLAY',
                    'required' => false,
                    'default_value' => 'textonly',
                    'options' => [
                        'query' => [
                            [
                                'id' => 'textonly',
                                'name' => 'Text only',
                            ],
                            [
                                'id' => 'logoonly',
                                'name' => 'Logo Only',
                            ],
                            [
                                'id' => 'textandlogo',
                                'name' => 'Text and Logo',
                            ],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                    'hint' => $this->l('Choose which display will be used at the checkout'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        if (Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $mode = (string) Tools::getValue('CARDGATE_TEST_MODE');
            $siteid = (string) Tools::getValue('CARDGATE_SITE_ID');
            $hashkey = (string) Tools::getValue('CARDGATE_HASH_KEY');
            $merchantid = (string) Tools::getValue('CARDGATE_MERCHANT_ID');
            $merchantapikey = (string) TOOLS::getValue('CARDGATE_MERCHANT_API_KEY');
            $paymentdisplay = (string) TOOLS::getValue('CARDGATE_PAYMENT_DISPLAY');
            $showissuers = (string) TOOLS::getValue('CARDGATE_SHOW_ISSUERS');
        } else {
            $mode = Configuration::get('CARDGATE_TEST_MODE');
            $siteid = Configuration::get('CARDGATE_SITE_ID');
            $hashkey = Configuration::get('CARDGATE_HASH_KEY');
            $merchantid = Configuration::get('CARDGATE_MERCHANT_ID');
            $merchantapikey = Configuration::get('CARDGATE_MERCHANT_API_KEY');
            $paymentdisplay = Configuration::get('CARDGATE_PAYMENT_DISPLAY');
            $showissuers = Configuration::get('CARDGATE_SHOW_ISSUERS');
        }

        // Load current value
        $helper->fields_value['CARDGATE_TEST_MODE'] = $mode;
        $helper->fields_value['CARDGATE_SITE_ID'] = $siteid;
        $helper->fields_value['CARDGATE_HASH_KEY'] = $hashkey;
        $helper->fields_value['CARDGATE_MERCHANT_ID'] = $merchantid;
        $helper->fields_value['CARDGATE_MERCHANT_API_KEY'] = $merchantapikey;
        $helper->fields_value['CARDGATE_PAYMENT_DISPLAY'] = $paymentdisplay;
        $helper->fields_value['CARDGATE_SHOW_ISSUERS'] = $showissuers;

        return $helper->generateForm($fields_form);
    }

    public function myModelFieldNames()
    {
        $my_module_field_names = [];
        $modules = Module::getModulesOnDisk();
        foreach ($modules as $module) {
            if (strstr($module->name, 'cardgate') !== false) {
                $name = str_replace('cardgate', '', $module->name);
                if ($name != '') {
                    $name = $this->alterName($name);
                }
            }
        }

        return $my_module_field_names;
    }

    public function _paymentData($option)
    {
        $moduleName = 'cardgate' . $option;
        $language = new Language($this->context->cart->id_lang);
        $cart = $this->context->cart;
        $cg_total = number_format($cart->getOrderTotal(true, Cart::BOTH) * 100, 0, '.', '');

        $ref = 'Order ' . $cart->id;

        $address = new Address($cart->id_address_invoice);
        $countryObj = new Country($address->id_country);
        $delivery_address = new Address($cart->id_address_delivery);
        $deliveryCountryObj = new Country($delivery_address->id_country);
        $customer = $this->context->customer;
        $currency = new Currency((int) $cart->id_currency);

        $cartitems = [];
        $products = $cart->getproducts(true);

        $iCartItemTotal = 0;
        $iCartItemTaxTotal = 0;

        foreach ($products as $product) {
            $vat_amount = $product['price_wt'] - $product['price'];
            $vat = round($vat_amount / $product['price'] * 100, 2);
            $item = [];
            $item['quantity'] = $product['cart_quantity'];
            $item['sku'] = $product['id_product'];
            $item['name'] = $product['name'];
            $item['price'] = round($product['price'] * 100, 0);
            $item['vat'] = $vat;
            $item['vat_amount'] = round($vat_amount * 100, 0);
            $item['vat_inc'] = 0;
            $item['type'] = 1;
            $cartitems[] = $item;
            $iCartItemTotal += round($item['price'] * $item['quantity']);
            $iCartItemTaxTotal += round($item['vat_amount'] * $item['quantity']);
        }

        $shippingcost = 0;
        $iShippingTotal = 0;
        $iShippingTaxTotal = 0;

        $free_shipping = false;
        foreach ($this->context->cart->getCartRules() as $rule) {
            if ($rule['free_shipping']) {
                $free_shipping = true;
                break;
            }
        }

        if ($free_shipping === false) {
            $shipping_cost_with_tax = $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
            $shipping_cost_without_tax = $this->context->cart->getOrderTotal(false, Cart::ONLY_SHIPPING);

            if ($shipping_cost_without_tax > 0) {
                $carrier = new Carrier($this->context->cart->id_carrier);

                $carrieraddress = new Address($this->context->cart->id_address_delivery);
                if (!Configuration::get('PS_ATCP_SHIPWRAP')) {
                    $carriertaxrate = round(($shipping_cost_with_tax / $shipping_cost_without_tax) - 1, 2) * 100;
                } else {
                    $carriertaxrate = $carrier->getTaxesRate($carrieraddress);
                }

                if (($shipping_cost_with_tax != $shipping_cost_without_tax) && $carriertaxrate == 0) {
                    // Prestashop error due to EU module?
                    $carriertaxrate = round(($shipping_cost_with_tax / $shipping_cost_without_tax) - 1, 2) * 100;
                }

                // $shippingReference = $this->module->shippingreferences[$language->iso_code];
                $item = [];
                $item['quantity'] = 1;
                $item['sku'] = 'SHIPPING_' . $carrier->id_reference;
                $item['name'] = $carrier->name;
                $item['price'] = round($shipping_cost_without_tax * 100, 0);
                $item['vat'] = $carriertaxrate;
                $item['vat_amount'] = round(($shipping_cost_with_tax - $shipping_cost_without_tax) * 100, 0);
                $item['vat_inc'] = 0;
                $item['type'] = 2;
                $cartitems[] = $item;

                $iShippingTotal += round($item['price'] * $item['quantity'], 0);
                $iShippingTaxTotal += round($item['vat_amount'] * $item['quantity'], 0);
            }
        }

        $iProductCorrection = round($cart->getOrderTotal(false, Cart::BOTH) * 100, 0) - $iCartItemTotal - $iShippingTotal;

        if ($iProductCorrection != 0) {
            $item = [];
            $item['quantity'] = 1;
            $item['sku'] = 'PRODUCTCORRECTION';
            $item['name'] = 'product_corection';
            $item['price'] = $iProductCorrection;
            $item['vat'] = 0;
            $item['vat_amount'] = 0;
            $item['vat_inc'] = 1;
            $item['type'] = 6;
            $cartitems[] = $item;
        }
        $iTaxCorrection = $cg_total - $iCartItemTotal - $iShippingTotal - $iProductCorrection - $iCartItemTaxTotal - $iShippingTaxTotal;
        if ($iTaxCorrection != 0) {
            $item = [];
            $item['quantity'] = 1;
            $item['sku'] = 'VATCORRECTION';
            $item['name'] = 'vat_corection';
            $item['price'] = $iTaxCorrection;
            $item['vat'] = 0;
            $item['vat_amount'] = 0;
            $item['vat_inc'] = 1;
            $item['type'] = 7;
            $cartitems[] = $item;
        }
        if ($address->phone_mobile != '') {
            $phone = $address->phone;
        } elseif ($address->phone != '') {
            $phone = $address->phone;
        } else {
            $phone = '';
        }

        $data = [];
        $data['option'] = $option;
        $data['language'] = $this->context->language->iso_code;
        $data['callback'] = Tools::getHttpHost(true, true) . __PS_BASE_URI__ . 'module/cardgate/validation';
        $data['return_url'] = Tools::getHttpHost(true, true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . $this->id . '&key=' . $customer->secure_key;
        $data['return_url_failed'] = Tools::getHttpHost(true, true) . __PS_BASE_URI__ . 'index.php?controller=order&step=3';
        $data['amount'] = $cg_total;
        $data['currency'] = $currency->iso_code;
        $data['description'] = $ref;
        $data['ref'] = $ref;
        $data['first_name'] = $address->firstname;
        $data['last_name'] = $address->lastname;
        $data['address'] = $address->address1 . ' ' . $address->address2;
        $data['zip_code'] = $address->postcode;
        $data['city'] = $address->city;
        $data['country'] = $countryObj->iso_code;
        $data['delivery_first_name'] = $delivery_address->firstname;
        $data['delivery_last_name'] = $delivery_address->lastname;
        $data['delivery_address'] = $delivery_address->address1 . ' ' . $delivery_address->address2;
        $data['delivery_zip_code'] = $delivery_address->postcode;
        $data['delivery_city'] = $delivery_address->city;
        $data['delivery_country'] = $deliveryCountryObj->iso_code;
        $data['email'] = $customer->email;
        $data['phone'] = $phone;
        $data['plugin_name'] = $this->name;
        $data['plugin_version'] = configuration::get('CARDGATE_VERSION');
        $data['shop_name'] = 'PrestaShop';
        $data['shop_version'] = $this->shop_version;
        $data['cartitems'] = $cartitems;

        return $data;
    }
}
