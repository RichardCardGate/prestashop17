<?php

if ( !defined('_PS_VERSION_') )
    exit;

require_once "cardgatepayment.php";

class Cardgate extends PaymentModule {
    
    var $shop_version = _PS_VERSION_;

    public function __construct() {
        $this->name = 'cardgate';
        $this->paymentcode = 'cardgate';
        $this->paymentname = 'CardGate';
        $this->tab = 'payments_gateways';
        $this->version = '1.7.0';
        $this->author = 'CardGate';
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'radio';

        $this->imageurl = 'https://gateway.cardgateplus.com/images/logo' . $this->paymentcode . '.gif';

        parent::__construct();

        $this->page = basename( __FILE__, '.php');
        $this->displayName = $this->l('CardGate Bank common');
        $this->description = $this->l('CardGate Bank base module.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the CardGate module?');
    }
    
    public function install() {
        $this->createOrderState();
        return parent::install();
    }

    public function uninstall() {
        return parent::uninstall();
    }

    public function createOrderState() {

        if ( !Configuration::get('CARDGATE_PENDING') ) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach ( Language::getLanguages() as $language ) {
                if ( Tools::strtolower( $language['iso_code'] ) == 'nl') {
                    $order_state->name[$language['id_lang']] = 'Wachten op CardGate betaling';
                } elseif ( Tools::strtolower( $language['iso_code'] ) == 'de') {
                    $order_state->name[$language['id_lang']] = 'Warten auf Zahlungseingang von CardGate';
                } elseif ( Tools::strtolower( $language['iso_code'] ) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'En attente du paiement par CardGate';
                } elseif ( Tools::strtolower( $language['iso_code'] ) == 'es') {
                    $order_state->name[$language['id_lang']] = 'En espera de pago por CardGate';
                } elseif ( Tools::strtolower( $language['iso_code'] ) == 'it') {
                    $order_state->name[$language['id_lang']] = 'In attesa di pagamento con CardGate';
                } else {
                    $order_state->name[$language['id_lang']] = 'Awaiting CardGate payment';
                }
            }

            $order_state->send_email = true;
            $order_state->template = 'payment';
            $order_state->color = 'RoyalBlue';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            $orderState->paid = false;
            $order_state->unremovable = true;


            if ( $order_state->add() ) {
                $source = _PS_MODULE_DIR_ . 'cardgate/logo.gif';
                $destination = dirname( __FILE__ ) . '/../../img/os/' . ( int ) $order_state->id . '.gif';
                copy( $source, $destination );
            }
            Configuration::updateGlobalValue('CARDGATE_PENDING', ( int ) $order_state->id );
        }
    }

    public function displayConf() {

        $this->_html = $this->displayConfirmation( $this->l('Settings updated') );
    }
    
    public function alterName($name) {
       $name =  ($name == 'mc' ? 'mistercash' : $name);
       return $name;
    }

    public function getContent() {
        $output = null;

        if ( Tools::isSubmit('submit' . $this->name ) ) {
            // get settings from post because post can give errors and you want to keep values
            $mode = ( string ) Tools::getValue('CARDGATE_TEST_MODE');
            $siteid = ( string ) Tools::getValue('CARDGATE_SITE_ID');
            $hashkey = ( string ) Tools::getValue('CARDGATE_HASH_KEY');
            $merchantid = ( string ) Tools::getValue('CARDGATE_MERCHANT_ID');
            $merchantapikey = ( string ) Tools::getValue('CARDGATE_MERCHANT_API_KEY') ;
            $paymentdisplay = ( string ) Tools::getValue('CARDGATE_PAYMENT_DISPLAY') ;
            $my_module_field_names = $this->myModelFieldNames();
            foreach ( $my_module_field_names as $key => $my_module_field_name ) {
                Configuration::updateValue($my_module_field_name,  ( string ) Tools::getValue( $my_module_field_name) );
            }

            // no errors so update the values
            Configuration::updateValue('CARDGATE_TEST_MODE', $mode );
            Configuration::updateValue('CARDGATE_SITE_ID', $siteid );
            Configuration::updateValue('CARDGATE_HASH_KEY', $hashkey );
            Configuration::updateValue('CARDGATE_MERCHANT_ID', $merchantid );
            Configuration::updateValue('CARDGATE_MERCHANT_API_KEY', $merchantapikey );
            Configuration::updateValue('CARDGATE_PAYMENT_DISPLAY', $paymentdisplay );

            $output .= $this->displayConfirmation( $this->l('Settings updated') );
        }

        return $output . $this->displayForm();
    }

    public function displayForm() {
        $my_modules = array();
        $my_module_field_names = array();
        $modules = Module::getModulesOnDisk();
        foreach ( $modules AS $module ) {
            if ( strstr( $module->name, 'cardgate') !== false ) {
                $name = str_replace('cardgate', '', $module->name );
                if ( $name != '') {
                    $name = $this->alterName($name );
                    $my_modules[] = $name;
                    $my_module_field_names[] = 'CARDGATE_' . strtoupper( $name ) . '_EXTRACOST';
                    $name = '';
                }
            }
        }
        $extra_costs = array();
        foreach ( $my_modules as $key => $module ) {

            $extra_costs[] = array(
                'type' => 'text',
                'label' => $this->l('Extra cost').' '. $module,
                'name' => $my_module_field_names[$key],
                'size' => '1',
                'required' => false,
                'hint' => $this->l('Add an extra charge for your payment method, for example, 1.95 or 5%'),
            );
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('General Settings'),
                'image' => '../img/admin/edit.gif'
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Mode'),
                    'name' => 'CARDGATE_TEST_MODE',
                    'required' => false,
                    'default_value' => 1,
                    'options' => array(
                        'query' => array( array('id' => 1, 'name' => 'test'), array('id' => 0, 'name' => 'live') ),
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Site Id'),
                    'name' => 'CARDGATE_SITE_ID',
                    'size' => 64,
                    'required' => true,
                    'hint' => $this->l('The CardGate Site Id, which you can find in your CardGate back-office')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Hash Key'),
                    'name' => 'CARDGATE_HASH_KEY',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('The CardGate Hash Key, which you can find in your CardGate back-office')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Merchant ID'),
                    'name' => 'CARDGATE_MERCHANT_ID',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('The Merchant ID, which you can find in your CardGate back-office')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Merchant API key'),
                    'name' => 'CARDGATE_MERCHANT_API_KEY',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('The Merchant API Key, which you can obtain from your Accountmanager.')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Payment display'),
                    'name' => 'CARDGATE_PAYMENT_DISPLAY',
                    'required' => false,
                    'default_value' => 'textonly',
                    'options' => array(
                        'query' => array( array('id' => 'textonly', 'name' => 'Text only'), array('id' => 'logoonly', 'name' => 'Logo Only'), array('id' => 'textandlogo', 'name' => 'Text and Logo') ),
                        'id' => 'id',
                        'name' => 'name'
                    ),
                    'hint' => $this->l('Choose which display will be used at the checkout')
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $fields_form[0]['form']['input'] = array_merge( $fields_form[0]['form']['input'], $extra_costs );

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
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $extra_costs = array();

        if ( Tools::isSubmit('submit' . $this->name ) ) {
            // get settings from post because post can give errors and you want to keep values
            $mode = ( string ) Tools::getValue('CARDGATE_TEST_MODE');
            $siteid = ( string ) Tools::getValue('CARDGATE_SITE_ID');
            $hashkey = ( string ) Tools::getValue('CARDGATE_HASH_KEY');
            $merchantid = ( string ) Tools::getValue('CARDGATE_MERCHANT_ID');
            $merchantapikey = ( string ) TOOLS:: getValue('CARDGATE_MERCHANT_API_KEY');
            $paymentdisplay = ( string ) TOOLS:: getValue('CARDGATE_PAYMENT_DISPLAY');
            
            foreach ( $my_module_field_names as $key => $my_module_field_name ) {
                $extra_costs[$my_module_field_name] = ( string ) Tools::getValue( $my_module_field_name );
            }
        } else {
            $mode = Configuration::get('CARDGATE_TEST_MODE');
            $siteid = Configuration::get('CARDGATE_SITE_ID');
            $hashkey = Configuration::get('CARDGATE_HASH_KEY');
            $merchantid = Configuration::get('CARDGATE_MERCHANT_ID');
            $merchantapikey = Configuration::get('CARDGATE_MERCHANT_API_KEY');
            $paymentdisplay = Configuration::get('CARDGATE_PAYMENT_DISPLAY');
            foreach ( $my_module_field_names as $key => $my_module_field_name ) {
                $extra_costs[$my_module_field_name] = Configuration::get( $my_module_field_name );
            }
        }

        // Load current value
        $helper->fields_value['CARDGATE_TEST_MODE'] = $mode;
        $helper->fields_value['CARDGATE_SITE_ID'] = $siteid;
        $helper->fields_value['CARDGATE_HASH_KEY'] = $hashkey;
        $helper->fields_value['CARDGATE_MERCHANT_ID'] = $merchantid;
        $helper->fields_value['CARDGATE_MERCHANT_API_KEY'] = $merchantapikey;
        $helper->fields_value['CARDGATE_PAYMENT_DISPLAY'] = $paymentdisplay;

        foreach ( $my_module_field_names as $key => $my_module_field_name ) {
            $helper->fields_value[$my_module_field_name] = $extra_costs[$my_module_field_name];
        }

        return $helper->generateForm( $fields_form );
    }
    public function myModelFieldNames(){
        $my_module_field_names = array();
        $modules = Module::getModulesOnDisk();
        foreach ( $modules AS $module ) {
            if ( strstr( $module->name, 'cardgate') !== false ) {
                $name = str_replace('cardgate', '', $module->name );
                if ( $name != '') {
                    $name = $this->alterName($name );
                    $my_module_field_names[] = 'CARDGATE_' . strtoupper( $name ) . '_EXTRACOST';
                    $name = '';
                }
            }
        }
        return $my_module_field_names;
    }
     public function _paymentData($option){

        $moduleName = 'cardgate'.$option;
        
        $extrafee = Configuration::get('CARDGATE_' . strtoupper( $option ) . '_EXTRACOST');
        $extrafee = (is_numeric($extrafee) ? $extrafee : 0);
        $cart = $this->context->cart;

        $cg_total = number_format( (($cart->getOrderTotal( true, Cart::BOTH ) + $extrafee) * 100 ), 0, '.', '');
       
        $ref = date( "YmdHis" ).'|'.$cart->id.'|'.$extrafee;
        $address = new Address( $cart->id_address_invoice );
        $countryObj = new Country( $address->id_country );
        $delivery_address= new Address( $cart->id_address_delivery );
        $deliveryCountryObj = new Country( $delivery_address->id_country );
        $customer = $this->context->customer;
        $currency = new Currency( ( int ) $cart->id_currency );

        $cartitems = array();
        $products = $cart->getproducts( true );

        foreach ( $products as $product ) {
            $vat_amount = $product['price_wt'] - $product['price'];
            $vat = round( $vat_amount / $product['price'] * 100, 2 );
            $item = array();
            $item['quantity'] = $product['cart_quantity'];
            $item['sku'] = $product['id_product'];
            $item['name'] = $product['name'];
            $item['price'] = round( $product['price_wt'] * 100, 0 );
            $item['vat'] = $vat;
            $item['vat_amount'] = round( $vat_amount * 100, 0 );
            $item['vat_inc'] = 1;
            $item['type'] = 1;
            $cartitems[] = $item;
        }

        $shippingcost = 0;
        if ( isset( $cart->id_carrier ) ) {
            $shippingcost = $cart->getOrderShippingCost( $cart->id_carrier );
        }
        if ( $shippingcost > 0 ) {
            $carrier = new Carrier( $cart->id_carrier );
            $item = array();
            $item['quantity'] = 1;
            $item['sku'] = 'SHIPPING_' . $carrier->id_reference;
            $item['name'] = $carrier->name;
            $item['price'] = round( $shippingcost * 100, 0 );
            $item['vat'] = 0;
            $item['vat_amount'] = 0;
            $item['vat_inc'] = 1;
            $item['type'] = 2;
            $cartitems[] = $item;
        }

        if ( $extrafee > 0 ) {
            $carrier = new Carrier( $cart->id_carrier );
            $item = array();
            $item['quantity'] = 1;
            $item['sku'] = 'TRANSACTIONFEE';
            $item['name'] = 'Transactie kosten';
            $item['price'] = round( $extrafee * 100, 0 );
            $item['vat'] = 0;
            $item['vat_amount'] = 0;
            $item['vat_inc'] = 1;
            $item['type'] = 3;
            $cartitems[] = $item;
        }
        
        
        $data = array();
        $data['option'] = $option;
        $data['language'] = $this->context->language->iso_code;
        $data['return_url'] = Tools::getHttpHost( true, true ) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . ( int ) $cart->id . '&key=' . $customer->secure_key;
        $data['return_url_failed'] = Tools::getHttpHost( true, true ) . __PS_BASE_URI__ . 'index.php?controller=order&step=3';
        $data['amount'] = $cg_total;
        $data['currency'] = $currency->iso_code;
        $data['description'] = 'Payment of the account #' . $ref;
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
        $data['phone_number'] = !empty( $address->phone_mobile ) ? $address->phone_mobile : $address->phone;
        $data['plugin_name'] = $this->name;
        $data['plugin_version'] = $this->version;
        $data['shop_name'] = 'PrestaShop';
        $data['shop_version'] = $this->shop_version;
        $data['cartitems'] = $cartitems;
        
        return $data;
    }
    public function extraCosts( $extra_cost ) {
        $cart = $this->context->cart;
        $total = number_format( ($cart->getOrderTotal( true, Cart::BOTH ) ), 2, '.', '');
        if ( $extra_cost == 0 || $extra_cost == '') {
            return 0;
        }
        if ( strstr( $extra_cost, '%') ) {
            $percentage = str_replace('%', '', $extra_cost );
            return round( ($total * $percentage / 100 ), 2 );
        }
        if ( is_numeric( $extra_cost ) ) {
            return round( $extra_cost, 2 );
        }
    }

}