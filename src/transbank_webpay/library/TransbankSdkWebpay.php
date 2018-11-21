<?php

require_once(DIR_SYSTEM.'library/transbank-sdk-php/init.php');
require_once(DIR_SYSTEM.'library/DiagnosticPDF.php');

use \Transbank\Onepay\OnepayBase;
use \Transbank\Onepay\ShoppingCart;
use \Transbank\Onepay\Item;
use \Transbank\Onepay\Transaction;
use \Transbank\Onepay\Options;
use \Transbank\Onepay\Refund;
use \Transbank\Onepay\Exceptions\TransbankException;
use \Transbank\Onepay\Exceptions\TransactionCreateException;
use \Transbank\Onepay\Exceptions\TransactionCommitException;
use \Transbank\Onepay\Exceptions\RefundCreateException;

/**
 * Helper for load onepay sdk and expose to opencart
 * @autor vutreras (victor.utreras@continuum.cl)
 */
class TransbankSdkWebpay {

    const PLUGIN_VERSION = '2.4.0'; //version of plugin payment
    const PLUGIN_CODE = 'transbank_webpay'; //code of plugin for virtuemart

    //constants for log handler
    const LOG_FILENAME = 'onepay-log'; //name of the log file
    const LOG_DEBUG_ENABLED = false; //enable or disable debug logs
    const LOG_INFO_ENABLED = true; //enable or disable info logs
    const LOG_ERROR_ENABLED = true; //enable or disable error logs
}
?>
