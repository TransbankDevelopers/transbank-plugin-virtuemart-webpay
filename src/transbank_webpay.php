<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
	require_once(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

if (!class_exists('ShopFunctions')) {
    require_once(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');
}

if (!class_exists('VirtueMartModelOrders')) {
    require_once(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
}

defined ('DIR_SYSTEM') or define ('DIR_SYSTEM', VMPATH_PLUGINS . '/vmpayment/transbank_webpay/transbank_webpay/');

if (!class_exists('TransbankSdkWebpay')) {
    require_once(DIR_SYSTEM.'library/TransbankSdkWebpay.php');
}

if (!class_exists('LogHandler')) {
    require_once(DIR_SYSTEM.'library/LogHandler.php');
}

/**
 * Transbank Webpay Payment plugin implementation
 * @autor vutreras (victor.utreras@continuum.cl)
 */
class plgVmPaymentTransbank_Webpay extends vmPSPlugin {

    function __construct(&$subject, $config) {

        parent::__construct ($subject, $config);
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
        $this->_tableId = 'id';

        if ($config['name'] == TransbankSdkWebpay::PLUGIN_CODE) {
            $this->log = new LogHandler();
            $varsToPush = $this->getVarsToPush();
            $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
            $this->setCryptedFields(array('key'));
        }
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @return bool
     * @Override
     */
    function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Transbank_Webpay Table');
    }

    /**
	 * Fields to create the payment table
     *
	 * @return string SQL fields
     * @Override
	 */
    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'order_pass' => 'char(64)',
            'order_status' => 'varchar(10)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(20)',
            'payment_currency' => 'smallint(1)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
            'tax_id' => 'smallint(1)',
            'transbank_webpay_metadata' => 'varchar(2000)'
        );
        return $SQLfields;
    }

    /**
     * Prepare data and redirect to Webpay
     */
    function plgVmConfirmedOrder($cart, $order) {

        //Se inicializa el flag de anulacion
        $session = JFactory::getSession();
        $session->set('webpay_flag_anulacion', 'SI');

        $paymentMethodId = $order['details']['BT']->virtuemart_paymentmethod_id;

        if (!($method = $this->getVmPluginMethod($paymentMethodId))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $orderId = $order['details']['BT']->virtuemart_order_id;
        $orderNumber = $order['details']['BT']->order_number;
        $amount = $order['details']['BT']->order_total;
        $sessionId = (string)intval(microtime(true));

        // se guarda la orden de compra y el amount en la sesion
        $session->set('webpay_order_id', $orderId);
        $session->set('webpay_order_number', $orderNumber);
        $session->set('webpay_order_amount', $amount);

        $ambiente = $method->ambiente;
        $secret_key = $method->key_secret;
        $cert_publico = $method->cert_public;
        $cert_transbank = $method->cert_transbank;
        $id_comercio = $method->id_comercio;

        $pluginResponseUrl = 'index.php?option=com_virtuemart&view=pluginresponse';

        $baseUrl = JURI::root() . $pluginResponseUrl . '&task=pluginresponsereceived' .
                                '&status_code={status}&on=' . $orderNumber.
                                '&pm=' . $paymentMethodId .
                                '&transaction_id=' . $orderId;

        $finalUrl = str_replace('{status}', 'ok', $baseUrl);
        $errorurl = str_replace('{status}', 'cancel', $baseUrl);
        $returnUrl = JURI::root() . $pluginResponseUrl . '&format=raw&task=pluginnotification' .
                                    '&tmpl=component&pm=' . $paymentMethodId;

        $session->set('webpay_error_url', $errorurl);

        /*
        $order = array();
        $order['order_number'] = $orderNumber;
        $order['order_status'] = $this->getConfig('status_wait_payment');
        $order['virtuemart_order_id'] = $orderId;
        $order['virtuemart_paymentmethod_id'] = $paymentMethodId;
        $order['customer_notified'] = 1;
        $order['comments'] = "Esperando el pago";
        $order['payment_name'] = TransbankSdkWebpay::PLUGIN_CODE;
        //$this->storePSPluginInternalData($order);
        */

        $config = array(
            "MODO" => $ambiente,
            "PRIVATE_KEY" => $secret_key,
            "PUBLIC_CERT" => $cert_publico,
            "WEBPAY_CERT" => $cert_transbank,
            "COMMERCE_CODE" => $id_comercio,
            "URL_FINAL" => $finalUrl,
            "URL_RETURN" => $returnUrl,
            "ECOMMERCE" => 'virtuemart'
        );

        $transbankSdkWebpay = new TransbankSdkWebpay($config);
        $result = $transbankSdkWebpay->initTransaction($amount, $sessionId, $orderNumber, 'xx'.$returnUrl, 'xx'.$finalUrl);

        $this->log->logInfo('result: ' . json_encode($result));

        if (isset($result["token_ws"])) {

            $url = $result["url"];
            $tokenWs = $result["token_ws"];

            $session->set('webpay_payment_ok', 'WAITING');
            $session->set('webpay_url', $url);
            $session->set('webpay_token_ws', $tokenWs);
            $session->set('webpay_config', $config);

            $this->toRedirect($url, array('token_ws' => $tokenWs));

        } else {

            $session->set('webpay_payment_ok', 'FAIL');
            $session->set('webpay_voucher_txresptexto', $result['error'] . ', ' . $result['detail']);
            $session->set('webpay_voucher_ordencompra', $orderNumber);
            $session->set('webpay_voucher_txdate_fecha', date('d-m-Y'));
            $session->set('webpay_voucher_txdate_hora', date('H:i:s'));

            $app = JFactory::getApplication();
            $app->enqueueMessage('Ocurrio un error al intentar conectar con WebPay.', 'error');
            $app->redirect($errorurl);
        }

        /*$cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        */
        die();
    }

    private function toRedirect($url, $data) {
        echo "<form action='$url' method='POST' name='webpayForm'>";
        foreach ($data as $name => $value) {
            echo "<input type='hidden' name='".htmlentities($name)."' value='".htmlentities($value)."'>";
        }
        echo "</form>";
        echo "<script language='JavaScript'>"
                ."document.webpayForm.submit();"
                ."</script>";
        return true;
    }

    /**
     *  Process final response, show message on result.  Empty cart if payment went ok
     *  @Override
     */
    function plgVmOnPaymentResponseReceived(&$html) {

        $paymentmethodId = JRequest::getInt('pm', 0);
        $session = JFactory::getSession();

        if (!($method = $this->getVmPluginMethod($paymentmethodId))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $request = JRequest::get('request');
        $orderId = $session->get('webpay_order_id', "");

        if($session->get('webpay_flag_anulacion', "NO")){
            if ($request['status_code'] == 'ok') {
                $html = $this->getHtmlPaymentResponse('La orden se ha creado exitosamente', true);
                $html .= $this->_getHtmlPaymentDataResponse($request);
                $this->emptyCart(null);
            } else {
                $html = $this->getHtmlPaymentResponse('La compra ha sido rechazada.', false);
                $html .= $this->_getHtmlPaymentDataRejectResponse($request);
            }
        } else { //compra anulada por usuario
            $session->set('webpay_flag_anulacion', 'SI'); //se resetea flag

            $order = array();
            $order['order_status'] = $this->getConfig('status_canceled');
            $order['virtuemart_order_id'] = $orderId;
            $order['customer_notified'] = 1;
            $order['comments'] = "Anulado por el usuario";

            $modelOrder = VmModel::getModel('orders');
            $modelOrder->updateStatusForOneOrder($orderId, $order, true);

            $html = $this->getHtmlPaymentResponse('La compra ha sido anulada por el usuario.', true);
        }
        return null;
    }

    /**
     * Process a payment cancellation
     * @Override
     */
    function plgVmOnUserPaymentCancel() {

        $session = JFactory::getSession();
        $orderId = $session->get('webpay_order_id', "");

        if (!($paymentTable = $this->getDataByOrderId($orderId))) {
            return null;
        }

        $order = array();
        $order['order_status'] = $this->getConfig('status_canceled');
        $order['virtuemart_order_id'] = $orderId;
        $order['customer_notified'] = 0;
        $order['comments'] = vmText::_('Rechazado desde Webpay');

        $modelOrder = VmModel::getModel ('orders');
        $modelOrder->updateStatusForOneOrder($orderId, $order, true);

        $errorurl = $session->get('webpay_error_url', "");
        $app = JFactory::getApplication();
        $app->redirect($errorurl);

        return true;
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
        $token_ws = $session->get('webpay_token_ws', "EMPTY");
        $config = $session->get('webpay_config', "EMPTY");

        try {
          $webpay = new WebPayNormal($config);
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
        $amount = $session->get('webpay_order_amount', "");
        $amount_floor = floor($amount);


        if ($order_total != $amount_floor) {
            vmdebug('plgVmOnPaymentNotification ' . $this->_name, $response, $amount);
            $this->logInfo('plgVmOnPaymentNotification -- payment merchant confirmation attempted inconsistent amount : ' . $amount . ' expected ' . $order_total, 'error');
        }


        if ($voucher == true) {
            // save order data
            $session->set('webpay_flag_anulacion', 'NO');

            $modelOrder = VmModel::getModel('orders');
            $order['order_status'] = $this->getConfig('status_success');
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

    private function getHtmlPaymentResponse($msg, $success = true) {
        if (!$success) {
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
        $session = JFactory::getSession();
        $html = '<div class="product vm-col">';
        $html .= '<table>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> DETALLES DE RECHAZO:</td></tr></thead><br>';
        $html .= '</table>';
        $html .= '<table>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Respuesta de la Transacci&oacute;n : ' . $session->get('webpay_voucher_txresptexto', "") . '</td></tr></thead><br>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Orden de Compra : ' . $session->get('webpay_voucher_ordencompra', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Fecha de Transacci&oacute;n : ' . $session->get('webpay_voucher_txdate_fecha', "") . '</td></tr></thead>';
        $html .= '<thead><tr><td colspan="2" style="text-align: left;"> Hora de Transacci&oacute;n : ' . $session->get('webpay_voucher_txdate_hora', "") . '</td></tr></thead>';
        $html .= '</table>';
        $html .= '</div>';
        return $html;
    }

    function savePaymentData($virtuemart_order_id, $resp) {
        $response[$this->_tablepkey] = $this->_getTablepkeyValue($virtuemart_order_id);
        $response['virtuemart_order_id'] = $virtuemart_order_id;
        $response[$this->_name . '_response_payment_date'] = gmdate('Y-m-d H:i:s', time());
        $response[$this->_name . '_response_payment_status'] = $resp['status_code'];
        $response[$this->_name . '_response_trans_id'] = $resp['transaction_id'];
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

    /**
     * return true for show the Transbank Onepay payment method in cart screen
     *
     * @param $cart
     * @param $method
     * @param $cart_prices
     * @Override
     */
    protected function checkConditions($cart, $method, $cart_prices) {
        //enable transbank webpay only for Chile and salesPrice > 0
        $salesPrice = round($cart_prices['salesPrice']);
        if ($salesPrice > 0 && $cart->pricesCurrency == $method->currency_id) {
            $currency = ShopFunctions::getCurrencyByID($cart->pricesCurrency, 'currency_code_3');
            if ($currency == 'CLP') {
                return true;
            }
        }
        return false;
    }

    /**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
     *
     * @param $jplugin_id
	 * @Override
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

    /**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
     *
	 * @param VirtueMartCart $cart: the actual cart
     * @param $msg
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
	 * @Override
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart,  &$msg) {
		return $this->OnSelectCheck($cart);
	}

    /**
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 * @Override
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

    /*
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param                $cart_prices_name
     *
     * @return
     * @Override
     */
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

    /**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
     * @Override
	 */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}

		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
    }

    /**
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 * @Override
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
	 * @Override
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
	 * @Override
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
	 * @param $data
	 * @return bool
     * @Override
	 */
    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        $ret = $this->declarePluginParams('payment', $data);
        if ($ret == 1) {
            $this->logInfo('Configuracion guardada correctamente');
        }
        return $ret;
    }

    /**
	 * @param $name
	 * @param $id
	 * @param $table
	 * @return bool
     * @Override
	 */
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
    }

    //Helpers

    /**
     * return the current cart
     */
    private function getCurrentCart() {
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        return VirtueMartCart::getCart();
    }

    /**
     * return the model orders
     */
    private function getModelOrder() {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        return new VirtueMartModelOrders();
    }

    /**
     * empty cart
     */
    public function emptyCart($session_id = null, $order_number = null) {
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
     * return method payment from virtuemart system by id
     */
    private function getMethodPayment() {
        $cid = vRequest::getvar('cid', NULL, 'array');
        if (is_Array($cid)) {
            $virtuemart_paymentmethod_id = $cid[0];
        } else {
            $virtuemart_paymentmethod_id = $cid;
        }
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        return $method;
    }

    //get configurations

    /**
     * return configuration for the plugin
     */
    public function getConfig($key) {
        $method = $this->getMethodPayment();
        return $method != NULL ? $method->$key : NULL;
    }
}
