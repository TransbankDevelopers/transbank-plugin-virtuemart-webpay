<?php

defined('_JEXEC') or die('Restricted access');

/**
 *
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2015 Flow - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin')) {
    require (JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

include_once 'libwebpay/webpay-config.php';
include_once 'libwebpay/webpay-normal.php';


class plgVmPaymentWebpay extends vmPSPlugin {

    public static $_this = false;

    function __construct(&$subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->setCryptedFields(array('key'));
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush) {
        return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
    }

    public function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Webpay Table');
    }

    function getTableSQLFields() {
        $SQLfields = array('id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => ' char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
        );
        return $SQLfields;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {

        // TODO verificar si es necesario calcular costos adicionales. Otros plugins de Flow no lo hacen
        return $cart_prices['salesPrice'];
    }

    /**
     * Reimplementation of vmPaymentPlugin::checkPaymentConditions()
     * @return bool true if conditions verified
     */
    function checkConditions($cart, $method, $cart_prices) {
        $this->convert($method);
        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount && $amount <= $method->max_amount || ($amount >= $method->min_amount && empty($method->max_amount)));

        return $amount_cond;
    }

    function convert($method) {
        $method->min_amount = (float)$method->min_amount;
        $method->max_amount = (float)$method->max_amount;
    }

    /**
     * Prepare data and redirect to Webpay
     */
    function plgVmConfirmedOrder($cart, $order) {

        //Se inicializa el flag de anulacion
        $session = & JFactory::getSession();
        $session->set('webpay_flag_anulacion', 'SI');

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $session = JFactory::getSession();
        $return_context = $session->getId();

        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- order number: ' . $order['details']['BT']->order_number, 'message');

        $vendorModel = VmModel::getModel('vendor');
        $vendorName = $vendorModel->getVendorName($method->virtuemart_vendor_id);

        $ordenCompra = $order['details']['BT']->order_number;
        $monto = $order['details']['BT']->order_total;

        // se guarda la orden de compra y el amount en la sesion
        $session->set('webpay_order_number', $ordenCompra);
        $session->set('webpay_monto_compra', $monto);

        $ambiente = $method->ambiente;
        $secret_key = $method->key_secret;
        $cert_publico = $method->cert_public;
        $cert_transbank = $method->cert_transbank;
        $id_comercio = $method->id_comercio;

        $session->set('webpay_status_success', $method->status_success);
        $session->set('webpay_status_canceled', $method->status_canceled);

        $url_base = JROUTE::_(JURI::root() .
                        'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status_code={status}&on=' .
                        $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id .
                        '&transaction_id=' . $order['details']['BT']->virtuemart_order_id);

        $url_exito = str_replace('{status}', 'ok', $url_base);
        $url_fracaso = str_replace('{status}', 'cancel', $url_base);
        $url_confirmacion = JURI::root() .
                'index.php?option=com_virtuemart&format=raw&view=pluginresponse&task=pluginnotification' .
                '&tmpl=component&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id;

        $session->set('webpay_url_fracaso', $url_fracaso);

        // Set the language code
        $lang = JFactory::getLanguage();
        $lang->load('plg_vmpayment_' . $this->_name, JPATH_ADMINISTRATOR);

        $tag = substr($lang->get('tag'), 0, 2);
        //$language = in_array($tag, $api->getSupportedLanguages()) ? $tag : ($method->language ? $method->language : 'en');
        // Prepare data that should be stored in the database
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues[$this->_name . '_custom'] = $return_context;
        $this->storePSPluginInternalData($dbValues);

        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- payment data saved to table ' . $this->_tablename, 'message');
        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- user redirected to ' . $this->_name, 'message');


        //config lo llenan con los datos almacenados en el e-commerce.
        $config = array(
            "MODO" => $ambiente,
            "PRIVATE_KEY" => $secret_key,
            "PUBLIC_CERT" => $cert_publico,
            "WEBPAY_CERT" => $cert_transbank,
            "COMMERCE_CODE" => $id_comercio,
            "URL_FINAL" => $url_exito,
            "URL_RETURN" => $url_confirmacion,
            "ECOMMERCE" => 'virtuemart'
        );

        try {
            $conf = new WebPayConfig($config);
            $webpay = new WebPayNormal($conf);
            $result = $webpay->initTransaction($monto, $sessionId = "123abc", $ordenCompra, $config['URL_FINAL']);
        } catch (Exception $e) {
            $result["error"] = "Error conectando a Webpay";
            $result["detail"] = $e->getMessage();
        }
        $url_token = '0';
        $token_webpay = '0';


        if (isset($result["token_ws"])) {

            $url_token = $result["url"];
            $token_webpay = $result["token_ws"];

            $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- payment data saved to table ' . $this->_tablename, 'message');
            $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- user redirected to ' . $this->_name, 'message');
            $session = & JFactory::getSession();
            $session->set('webpay_url_token', $url_token);
            $session->set('webpay_token', $token_webpay);
            $session->set('webpay_config', $config);

            // echo the redirect form
            echo $this->getConfirmFormHtml($url_token, $token_webpay);
        } else {

           //echo "<br/>Ocurrio un error al intentar conectar con WebPay. Por favor intenta mas tarde.<br/>";

            JFactory::getApplication()->enqueueMessage('Ocurrio un error al intentar conectar con WebPay.', 'error');

            $allDone =& JFactory::getApplication();
            $allDone->redirect($url_fracaso);

            //echo $this->data["error_detail"] = $result["detail"];
            //var_dump($result);
        }


        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        die(); // not save order, not send mail, do redirect

    }

    function getConfirmFormHtml($url, $token) {

        $form .= '<html>';

        $form .= '<head>';
        $form .= '  <title>Redirection</title>';
        $form .=  '</head>';
        $form .=  '<body>';
        $form .=  'Redireccionando a webpay.cl...';

        $form .= '<form action="' . $url . '" method="POST">' . "\n";
        $form .= '<input type="hidden" name="token_ws" value="' . $token . '" />' . "\n";
        $form .= '<script type="text/javascript">document.forms[0].submit();</script>';
        $form .= '</form>' . "\n";
        $form .= '</body></html>' . "\n";


        return $form;
    }

    function genericRedirect($url) {

        $form .= '<html>';

        $form .= '<head>';
        $form .= '  <title>Redirection</title>';
        $form .=  '</head>';
        $form .=  '<body>';
        $form .= '<form action="' . $url . '" method="POST">' . "\n";
        $form .= '<script type="text/javascript">document.forms[0].submit();</script>';
        $form .= '</form>' . "\n";
        $form .= '</body></html>' . "\n";

        return $form;
    }

    /**
     *  Process final response, show message on result.  Empty cart if payment went ok
     */
    function plgVmOnPaymentResponseReceived(&$html) {

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        $session = & JFactory::getSession();

        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->_debug = true;
        $this->logInfo('plgVmOnPaymentResponseReceived -- user returned back from ' . $this->_name, 'message');

        $resp = JRequest::get('request');

        // Retrieve order info from database
        if (!class_exists('VirtueMartModelOrders')) {
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $order_number = $session->get('webpay_order_number', "");
        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);

        // Order not found
        if (!$virtuemart_order_id) {
            vmdebug('plgVmOnPaymentResponseReceived ' . $this->_name, $resp, $resp['transaction_id']);
            $this->logInfo('plgVmOnPaymentResponseReceived -- payment check attempted on non existing order : ' . $resp['transaction_id'], 'error');
            return null;
        }


        $this->order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
        $order_status_code = $order->items->order_status;

        if($session->get('webpay_flag_anulacion', "NO")){
            if ($resp['status_code'] == 'ok') {
                $html = $this->_getHtmlPaymentResponse('La orden se ha creado exitosamente', true, $resp['transaction_id']);
                $html .= $this->_getHtmlPaymentDataResponse($resp);

                $this->emptyCart(null);

            } else {
                $html = $this->_getHtmlPaymentResponse('La compra ha sido rechazada.', false);
                $html .= $this->_getHtmlPaymentDataRejectResponse($resp);
                $new_status = $method->status_canceled;
            }
        }else{ //compra anulada por usuario
                $session->set('webpay_flag_anulacion', 'SI'); //se resetea flag

                $modelOrder = VmModel::getModel('orders');
                $order['order_status'] = $session->get('webpay_status_canceled', "");
                $order['virtuemart_order_id'] = $virtuemart_order_id;
                $order['customer_notified'] = 1;
                $order['comments'] = "Anulado por el usuario";
                vmdebug($this->_name . ' - PaymentNotification', $order);

                $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                $html = $this->_getHtmlPaymentResponse('La compra ha sido anulada por el usuario.', true, $resp['transaction_id']);
        }
        return null;
    }

    /**
     * Process a payment cancellation
     */
    function plgVmOnUserPaymentCancel() {

        if (!class_exists('VirtueMartModelOrders')) {
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $session = & JFactory::getSession();
        $order_number = $session->get('webpay_order_number', "");


        if (!$order_number) {
            return false;
        }
        if (!$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number)) {
            return null;
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }

        $session = JFactory::getSession();
        $return_context = $session->getId();
        $field = $this->_name . '_custom';

        $this->handlePaymentUserCancel($virtuemart_order_id);

        return true;
    }

    function handlePaymentUserCancel ($virtuemart_order_id) {

        $session = & JFactory::getSession();

        if ($virtuemart_order_id) {
            // set the order to cancel , to handle the stock correctly
            if (!class_exists ('VirtueMartModelOrders')) {
                    require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
            }

            $modelOrder = VmModel::getModel ('orders');

            $order['order_status'] = $session->get('webpay_status_canceled', "");
            $order['virtuemart_order_id'] = $virtuemart_order_id;
            $order['customer_notified'] = 0;
            $order['comments'] = vmText::_ ('Rechazado desde Webpay');
            vmdebug($this->_name . ' - PaymentNotification', $order);


            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            $session = & JFactory::getSession();
            $url_fracaso = $session->get('webpay_url_fracaso', "");

            echo $this->genericRedirect($url_fracaso);
        }
    }

    /**
     * Webpay payment callback
     */
    function plgVmOnPaymentNotification() {


        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        $privatekey = $method->secret;
        $comercio = $method->receiver_id;

        $errorResponse = array('status' => 'RECHAZADO', 'c' => $comercio);
        $acceptResponse = array('status' => 'ACEPTADO', 'c' => $comercio);

        $this->logInfo('plgVmOnPaymentNotification -- notification from merchant', 'message');

        $response = JRequest::get('response');
        $data = $response['response'];
        $voucher = false;
        $error_transbank = "NO";

        $session = & JFactory::getSession();
        $token_ws = $session->get('webpay_token', "EMPTY");
        $config = $session->get('webpay_config', "EMPTY");

        try {
          $conf = new WebPayConfig($config);
          $webpay = new WebPayNormal($conf);
            $result = $webpay->getTransactionResult($token_ws);
        } catch (Exception $e) {
            $result["error"] = "Error conectando a Webpay";
            $result["detail"] = $e->getMessage();
            $error_transbank = "SI";
        }


        $order_id = $result->buyOrder;
        $this->savePaymentDataVoucherTransbank($result, $error_transbank);

        if ($order_id && $error_transbank == "NO") {

            if (($result->VCI == "TSY" || $result->VCI == "A" || $result->VCI == "")) {
                // Transaccion autorizada
                $voucher = true;

            } else {
                $responseDescription = htmlentities($result->detailOutput->responseDescription);
            }
        }

        $order = null;
        $order_number = $session->get('webpay_order_number', "");

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        if ($virtuemart_order_id) {
            $order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
        }

        if (!$order) {
            vmdebug('plgVmOnPaymentNotification ' . $this->_name, $response, $virtuemart_order_id);
            $this->logInfo('plgVmOnPaymentNotification -- payment merchant confirmation attempted on non existing order : ' . $virtuemart_order_id, 'error');
            return;
        }

        $order_total = floor($order['details']['BT']->order_total);
        $amount = $session->get('webpay_monto_compra', "");
        $amount_floor = floor($amount);


        if ($order_total != $amount_floor) {
            vmdebug('plgVmOnPaymentNotification ' . $this->_name, $response, $amount);
            $this->logInfo('plgVmOnPaymentNotification -- payment merchant confirmation attempted inconsistent amount : ' . $amount . ' expected ' . $order_total, 'error');
        }


        if ($voucher == true) {
            // save order data
            $session->set('webpay_flag_anulacion', 'NO');


            $modelOrder = VmModel::getModel('orders');
            $order['order_status'] = $session->get('webpay_status_success', "");
            $order['virtuemart_order_id'] = $virtuemart_order_id;
            $order['customer_notified'] = 1;
            $order['comments'] = "Confirmado desde Webpay";
            vmdebug($this->_name . ' - PaymentNotification', $order);

            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            //se va al voucher final de transbank.
            $this->redirect($result->urlRedirection, array("token_ws" => $token_ws));
        }else{
            $session->set('webpay_flag_anulacion', 'NO');
            $this->plgVmOnUserPaymentCancel();
        }

        die();
    }

    public static function redirect($url, $data){
      	echo  "<form action='" . $url . "' method='POST' name='webpayForm'>";
        	foreach ($data as $name => $value) {
  			echo "<input type='hidden' name='".htmlentities($name)."' value='".htmlentities($value)."'>";
  		}
  		echo  "</form>"
  			 ."<script language='JavaScript'>"
               ."document.webpayForm.submit();"
               ."</script>";
     }

    public function savePaymentDataVoucherTransbank($result, $error_transbank) {

        $paymentTypeCodearray = array(
            "VD" => "Venta D&eacute;bito",
            "VN" => "Venta Normal",
            "VC" => "Venta en cuotas",
            "SI" => "3 cuotas sin inter&eacute;s",
            "S2" => "2 cuotas sin inter&eacute;s",
            "NC" => "N cuotas sin inter&eacute;s",
        );

        $session = & JFactory::getSession();

        if ($result->detailOutput->responseCode == 0) {
            $transactionResponse = "Aceptado";
        } else {
            $transactionResponse = $result->detailOutput->responseDescription; //." (".$result->detailOutput->responseCode.")";
        }

        if ($error_transbank == "NO") {

            $session->set('webpay_result_code', $result->detailOutput->responseCode);
            $session->set('webpay_result_desc', $transactionResponse);
        }
        $date_tmp = strtotime($result->transactionDate);
        $date_tx_hora = date('H:i:s', $date_tmp);
        $date_tx_fecha = date('d-m-Y', $date_tmp);


        //tipo de cuotas
        if ($result->detailOutput->paymentTypeCode == "SI" || $result->detailOutput->paymentTypeCode == "S2" ||
                $result->detailOutput->paymentTypeCode == "NC" || $result->detailOutput->paymentTypeCode == "VC") {
            $tipo_cuotas = $paymentTypeCodearray[$result->detailOutput->paymentTypeCode];
        } else {
            $tipo_cuotas = "Sin cuotas";
        }
        $session->set('webpay_tx_anulada', "NO");

        $session->set('webpay_voucher_token', $url_token);
        $session->set('webpay_voucher_txresptexto', $transactionResponse);
        $session->set('webpay_voucher_totalpago', $result->detailOutput->amount);
        $session->set('webpay_voucher_accdate', $result->accountingDate);
        $session->set('webpay_voucher_ordencompra', $result->buyOrder);
        $session->set('webpay_voucher_txdate_hora', $date_tx_hora);
        $session->set('webpay_voucher_txdate_fecha', $date_tx_fecha);
        $session->set('webpay_voucher_nrotarjeta', $result->cardDetail->cardNumber);
        $session->set('webpay_voucher_autcode', $result->detailOutput->authorizationCode);
        $session->set('webpay_voucher_tipopago', $paymentTypeCodearray[$result->detailOutput->paymentTypeCode]);
        $session->set('webpay_voucher_tipocuotas', $tipo_cuotas);
        $session->set('webpay_voucher_respcode', $result->detailOutput->responseCode);
        $session->set('webpay_voucher_nrocuotas', $result->detailOutput->sharesNumber);
    }

    function _getHtmlPaymentResponse($msg, $is_success = true, $order_id = null, $amount = null) {

        if (!$is_success) {
            return '<p style="text-align: center;">' . JText::_($msg) . '</p>';
        } else {
            $html = '<table>' . "\n";
            $html .= '<thead><tr><td colspan="2" style="text-align: center;">' . JText::_($msg) . '</td></tr></thead>';
            $html .= '</table>' . "\n";
            return $html;
        }
    }

    function _getHtmlPaymentDataResponse($resp) {

        $html = '<div class="product vm-col">';
        //$html = '<div class="product vm-col">';
        $html .= '<table>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> DETALLES DEL PAGO :  </td></tr></thead><br>';
        $html .= '</table>' . "\n";
        $html .= '<table>' . "\n";

        $session = & JFactory::getSession();

        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Respuesta de la Transacci&oacute;n : ' . $session->get('webpay_voucher_txresptexto', "") . '</td></tr></thead><br>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Tarjeta de cr&eacute;dito: ' . $session->get('webpay_voucher_nrotarjeta', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Fecha de Transacci&oacute;n : ' . $session->get('webpay_voucher_txdate_fecha', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Hora de Transacci&oacute;n : ' . $session->get('webpay_voucher_txdate_hora', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Monto Compra : ' . $session->get('webpay_voucher_totalpago', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Orden de Compra : ' . $session->get('webpay_voucher_ordencompra', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Codigo de Autorizaci&oacute;n : ' . $session->get('webpay_voucher_autcode', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Tipo de Pago : ' . $session->get('webpay_voucher_tipopago', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Tipo de Cuotas : ' . $session->get('webpay_voucher_tipocuotas', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Numero de cuotas : ' . $session->get('webpay_voucher_nrocuotas', "") . '</td></tr></thead>';
        $html .= '</table>' . "\n";
        $html .= '</div>' . "\n";
        return $html;
    }

    function _getHtmlPaymentDataRejectResponse($resp) {

        $html = '<div class="product vm-col">';
        //$html = '<div class="product vm-col">';
        $html .= '<table>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> DETALLES DE RECHAZO:  </td></tr></thead><br>';
        $html .= '</table>' . "\n";
        $html .= '<table>' . "\n";

        $session = & JFactory::getSession();

        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Respuesta de la Transacci&oacute;n : ' . $session->get('webpay_voucher_txresptexto', "") . '</td></tr></thead><br>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Orden de Compra : ' . $session->get('webpay_voucher_ordencompra', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Fecha de Transacci&oacute;n : ' . $session->get('webpay_voucher_txdate_fecha', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Hora de Transacci&oacute;n : ' . $session->get('webpay_voucher_txdate_hora', "") . '</td></tr></thead>';
        $html .= '</table>' . "\n";
        $html .= '</div>' . "\n";
        return $html;
    }

    function savePaymentData($virtuemart_order_id, $resp) {

        vmdebug($this->_name . 'response_raw', json_encode($resp));
        $response[$this->_tablepkey] = $this->_getTablepkeyValue($virtuemart_order_id);
        $response['virtuemart_order_id'] = $virtuemart_order_id;
        $response[$this->_name . '_response_payment_date'] = gmdate('Y-m-d H:i:s', time());
        $response[$this->_name . '_response_payment_status'] = $resp['status_code'];
        $response[$this->_name . '_response_trans_id'] = $resp['transaction_id'];
        ;
        $this->storePSPluginInternalData($response, $this->_tablepkey, true);
    }

    function _getTablepkeyValue($virtuemart_order_id) {
        $db = JFactory::getDBO();
        $q = 'SELECT ' . $this->_tablepkey . ' FROM `' . $this->_tablename . '` ' . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);

        if (!($pkey = $db->loadResult())) {
            JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        return $pkey;
    }

    function emptyCart($session_id = null, $order_number = null) {
        if ($session_id != null) {
            $session = JFactory::getSession();
            $session->close();

            // Recover session in wich the payment is done
            session_id($session_id);
            session_start();
        }

        if (!class_exists('VirtueMartCart')) {
            require (JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }

        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Val������rie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been
     * selected. It can be used to store additional payment info in
     * the cart.
     *
     * @author Max Milbers
     * @author Val������rie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array & $cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise

     * public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
     * return null;
     * }
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

}

if (!function_exists('http_response_code')) {

    function http_response_code($code = NULL) {

        if ($code !== NULL) {

            switch ($code) {
                case 100: $text = 'Continue';
                    break;
                case 101: $text = 'Switching Protocols';
                    break;
                case 200: $text = 'OK';
                    break;
                case 201: $text = 'Created';
                    break;
                case 202: $text = 'Accepted';
                    break;
                case 203: $text = 'Non-Authoritative Information';
                    break;
                case 204: $text = 'No Content';
                    break;
                case 205: $text = 'Reset Content';
                    break;
                case 206: $text = 'Partial Content';
                    break;
                case 300: $text = 'Multiple Choices';
                    break;
                case 301: $text = 'Moved Permanently';
                    break;
                case 302: $text = 'Moved Temporarily';
                    break;
                case 303: $text = 'See Other';
                    break;
                case 304: $text = 'Not Modified';
                    break;
                case 305: $text = 'Use Proxy';
                    break;
                case 400: $text = 'Bad Request';
                    break;
                case 401: $text = 'Unauthorized';
                    break;
                case 402: $text = 'Payment Required';
                    break;
                case 403: $text = 'Forbidden';
                    break;
                case 404: $text = 'Not Found';
                    break;
                case 405: $text = 'Method Not Allowed';
                    break;
                case 406: $text = 'Not Acceptable';
                    break;
                case 407: $text = 'Proxy Authentication Required';
                    break;
                case 408: $text = 'Request Time-out';
                    break;
                case 409: $text = 'Conflict';
                    break;
                case 410: $text = 'Gone';
                    break;
                case 411: $text = 'Length Required';
                    break;
                case 412: $text = 'Precondition Failed';
                    break;
                case 413: $text = 'Request Entity Too Large';
                    break;
                case 414: $text = 'Request-URI Too Large';
                    break;
                case 415: $text = 'Unsupported Media Type';
                    break;
                case 500: $text = 'Internal Server Error';
                    break;
                case 501: $text = 'Not Implemented';
                    break;
                case 502: $text = 'Bad Gateway';
                    break;
                case 503: $text = 'Service Unavailable';
                    break;
                case 504: $text = 'Gateway Time-out';
                    break;
                case 505: $text = 'HTTP Version not supported';
                    break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;
        } else {

            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }

        return $code;
    }

}

// No closing tag
