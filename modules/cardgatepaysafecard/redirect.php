<?php

include dirname(__FILE__) . '/../../config/config.inc.php';

define('LOGER_TRANSACTIONS', false); // true ou false, write log of response or not
define('FICHIER_LOG', 'dialxs.log'); // File for testing response answer
define('EMAIL_DE_NOTIFICATION', ''); // Email
// add Link Object to Context if it does not exist yet
// fixes bug when member function getPageLink() is called

if (!is_object(Context::getContext()->link)) {
    Context::getContext()->link = new Link();
}

if (isset($_GET['extra']) && isset($_GET['status'])) {
    header('Location: ' . Tools::getHttpHost(true, true) . __PS_BASE_URI__ . 'order-confirmation.php');
}
