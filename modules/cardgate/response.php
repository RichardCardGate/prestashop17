<?php

/**
 * CardGate - Prestashop
 *
 * 2010-11-09 (LL) Version 1.00
 *   Initial release
 *
 * 2011-04-18 (BZ) Version 1.01
 *   Fixed minor errors and spelling
 */
// CONSTANTES
//

class callbackResponse
{
    public $status = '';
    public $get = '';
    public $option = '';

    public function __construct($get = '')
    {
        $this->get = $get;
    }

    private function isSafe()
    {
        $amount = $this->get['amount'];
        $hashKey = Configuration::get('CARDGATE_HASH_KEY');
        $hashString = ($this->get['testmode'] == 1 ? 'TEST' : '') .
                $this->get['transaction'] .
                $this->get['currency'] .
                $amount .
                $this->get['reference'] .
                $this->get['code'] .
                $hashKey;

        return md5($hashString) == $this->get['hash'];
    }

    public function isCardgateCallback()
    {
        if (!isset($this->get['hash'])) {
            return false;
        } else {
            return $this->isSafe();
        }
    }

    public function getCartId()
    {
        return (int) substr($this->get['reference'], 6);
    }

    public function getTotal()
    {
        return (float) $this->get['amount'] / 100;
    }
    // Return Number of Cart

    public function getDataSend()
    {
        return $this->get['ref'];
    }

    // Get payment name
    public function getPaymentName()
    {
        $option = ($this->get['pt'] == 'idealpro' ? 'ideal' : $this->get['pt']);
        require dirname(__FILE__) . $option . '/cardgate' . $option . '.php';
        $method = 'cardgate' . $option;
        $payment = new $method();
        $name = $payment->paymentname;
        unset($payment);

        return $name;
    }

    public function getTransaction()
    {
        return $this->get['transaction'];
    }

    public function isCanceled()
    {
        $status = (int) $this->get['code'];

        return $status == 309;
    }

    public function getStatus()
    {
        $code = (int) $this->get['code'];
        $pending = Configuration::get('CARDGATE_PENDING');

        if ($code < 200) {
            return $pending;
        } elseif ($code < 300) {
            return _PS_OS_PAYMENT_;
        } elseif ($code < 400) {
            return _PS_OS_CANCELED_;
        } elseif ($code >= 700 && $code < 800) {
            return $pending;
        }
    }
}
