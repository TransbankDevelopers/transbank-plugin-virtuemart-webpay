<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
	require_once(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

if (!class_exists('ShopFunctions')) {
    require_once(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');
}

defined ('DIR_SYSTEM') or define ('DIR_SYSTEM', VMPATH_PLUGINS . '/vmpayment/transbank_webpay/transbank_webpay/');

if (!class_exists('TransbankSdkWebpay')) {
    require_once(DIR_SYSTEM.'library/TransbankSdkWebpay.php');
}

if (!class_exists('LogHandler')) {
    require_once(DIR_SYSTEM.'library/LogHandler.php');
}

if (!class_exists('HealthCheck')) {
    require_once(DIR_SYSTEM.'library/HealthCheck.php');
}

if (!class_exists('ReportPdfLog')) {
    require_once(DIR_SYSTEM.'library/ReportPdfLog.php');
}

if (!class_exists('ConfigProvider')) {
    require_once(DIR_SYSTEM.'library/ConfigProvider.php');
}

/**
 * Transbank Webpay Payment plugin implementation
 * @autor vutreras (victor.utreras@continuum.cl)
 */
class plgVmPaymentTransbank_Webpay extends vmPSPlugin {

    const PLUGIN_CODE = 'transbank_webpay'; //code of plugin for virtuemart

    private $paymentTypeCodearray = array(
        "VD" => "Venta Debito",
        "VN" => "Venta Normal",
        "VC" => "Venta en cuotas",
        "SI" => "3 cuotas sin interés",
        "S2" => "2 cuotas sin interés",
        "NC" => "N cuotas sin interés",
    );

    function __construct(&$subject, $config) {

        parent::__construct ($subject, $config);
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
        $this->_tableId = 'id';

        if ($config['name'] == self::PLUGIN_CODE) {

            $varsToPush = $this->getVarsToPush();
            $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
            $this->setCryptedFields(array('key'));

            if (isset($_GET['createPdf'])) {
                $this->createPdf();
            } else if (isset($_GET['updateConfig'])) {
                $this->updateConfig();
            } else if (isset($_GET['checkTransaction'])) {
                $this->checkTransaction();
            }
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

        $session = JFactory::getSession();

        $paymentMethodId = $order['details']['BT']->virtuemart_paymentmethod_id;

        if (!($method = $this->getVmPluginMethod($paymentMethodId))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $amount = $order['details']['BT']->order_total;
        $sessionId = (string)intval(microtime(true));
        $orderId = $order['details']['BT']->virtuemart_order_id;
        $orderNumber = $order['details']['BT']->order_number;

        $baseUrl = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse' .
                                '&task=pluginresponsereceived' .
                                '&cid=' . $paymentMethodId;

        $returnUrl = $baseUrl;
        $finalUrl = $baseUrl;

        $config = $this->getAllConfig();

        $transbankSdkWebpay = new TransbankSdkWebpay($config);
        $result = $transbankSdkWebpay->initTransaction($amount, $sessionId, $orderNumber, $returnUrl, $finalUrl);

        $session->set('webpay_order_id', $orderId);

        if (isset($result["token_ws"])) {

            $url = $result["url"];
            $tokenWs = $result["token_ws"];

            $session->set('webpay_payment_ok', 'WAITING');
            $session->set('webpay_token_ws', $tokenWs);

            $this->toRedirect($url, array('token_ws' => $tokenWs));

        } else {

            $session->set('webpay_payment_ok', 'FAIL');
            $app = JFactory::getApplication();
            $app->redirect($finalUrl);
        }

        die();
    }

    /**
     *  Process final response, show message on result.  Empty cart if payment went ok
     *  @Override
     */
    function plgVmOnPaymentResponseReceived(&$html) {

        if (!($method = $this->getMethodPayment())) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $session = JFactory::getSession();
        $paymentOk = $session->get('webpay_payment_ok');
        $orderId = $session->get('webpay_order_id');
        $tokenWs = $session->get('webpay_token_ws');

        if($paymentOk == 'WAITING'){

            $transbankSdkWebpay = new TransbankSdkWebpay($config);
            $result = $transbankSdkWebpay->commitTransaction($tokenWs);

            $session->set('result', json_encode($result));

            $order = array();
            $order['virtuemart_order_id'] = $orderId;
            $order['customer_notified'] = 1;
            $order['transbank_webpay_metadata'] = json_encode($result);

            if (isset($result->buyOrder) && isset($result->detailOutput) && $result->detailOutput->responseCode == 0) {

                $session->set('webpay_payment_ok', 'SUCCESS');

                $comment = array(
                    'buyOrder' => $result->buyOrder,
                    'sessionId' => $result->sessionId,
                    'responseCode' => $result->detailOutput->responseCode,
                    'authorizationCode' => $result->detailOutput->authorizationCode,
                    'paymentTypeCode' => $result->detailOutput->paymentTypeCode,
                    'vci' => $result->VCI
                );

                $order['order_status'] = $this->getConfig('status_success');
                $order['comments'] = 'Pago exitoso: ' . json_encode($comment);

                $modelOrder = VmModel::getModel('orders');
                $modelOrder->updateStatusForOneOrder($orderId, $order, true);

                $this->toRedirect($result->urlRedirection, array('token_ws' => $tokenWs));
                die();

            } else {

                $session->set('webpay_payment_ok', 'FAIL');

                $comment = $result;

                //check if was return from webpay, then use only subset data
                if (isset($result->buyOrder)) {
                    $comment = array(
                        'buyOrder' => $result->buyOrder,
                        'sessionId' => $result->sessionId,
                        'responseCode' => $result->detailOutput->responseCode,
                        'responseDescription' => $result->detailOutput->responseDescription
                    );
                }

                $order['order_status'] = $this->getConfig('status_canceled');
                $order['comments'] = 'Pago fallido: ' . json_encode($comment);

                $modelOrder = VmModel::getModel('orders');
                $modelOrder->updateStatusForOneOrder($orderId, $order, true);

                $html = $this->getRejectMessage($result);
            }

        } else {

            $result = $session->get('result');

            if($paymentOk == 'SUCCESS') {

                $html = $this->getSuccessMessage($result);
                $this->emptyCart(null);

            } else if ($paymentOk == 'FAIL') {

                $order = array();
                $order['order_status'] = $this->getConfig('status_canceled');
                $order['virtuemart_order_id'] = $orderId;
                $order['customer_notified'] = 1;
                $order['comments'] = $result->error . ', ' . $result->detail;

                $modelOrder = VmModel::getModel('orders');
                $modelOrder->updateStatusForOneOrder($orderId, $order, true);

                $html = $this->getRejectMessage($result);
            }
        }
        return null;
    }

    private function getSuccessMessage($result) {

        if (is_string($result)) {
            $result = json_decode($result);
        } else {
            $result = json_encode($result);
            $result = json_decode($result);
        }

        $app = JFactory::getApplication();
        $app->enqueueMessage('Pago exitoso', 'message');

        if($result->detailOutput->paymentTypeCode == "SI" || $result->detailOutput->paymentTypeCode == "S2" ||
            $result->detailOutput->paymentTypeCode == "NC" || $result->detailOutput->paymentTypeCode == "VC" ) {
            $tipoCuotas = $this->paymentTypeCodearray[$result->detailOutput->paymentTypeCode];
        } else {
            $tipoCuotas = "Sin cuotas";
        }

		$message = "<h2>Detalles del pago con Webpay</h2>
        <p>
            <br>
            <b>Respuesta de la Transacci&oacute;n: </b>{$result->detailOutput->responseCode}<br>
            <b>Monto:</b> $ {$result->detailOutput->amount}<br>
            <b>Order de Compra: </b> {$result->detailOutput->buyOrder}<br>
            <b>Fecha de la Transacci&oacute;n: </b>".date('d-m-Y', strtotime($result->transactionDate))."<br>
            <b>Hora de la Transacci&oacute;n: </b>".date('H:i:s', strtotime($result->transactionDate))."<br>
            <b>Tarjeta: </b>************{$result->cardDetail->cardNumber}<br>
            <b>C&oacute;digo de autorizacion: </b>{$result->detailOutput->authorizationCode}<br>
            <b>N&uacute;mero de cuotas: </b>{$tipoCuotas}
        </p>";
        return $message;
    }

    private function getRejectMessage($result) {

        if (is_string($result)) {
            $result = json_decode($result);
        } else {
            $result = json_encode($result);
            $result = json_decode($result);
        }

        $app = JFactory::getApplication();
        $app->enqueueMessage('Pago rechazado', 'error');

        if  (isset($result->detailOutput)) {
            $message = "<h2>Transacci&oacute;n rechazada con Webpay</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$result->detailOutput->responseCode}<br>
                <b>Monto:</b> $ {$result->detailOutput->amount}<br>
                <b>Order de Compra: </b> {$result->detailOutput->buyOrder}<br>
                <b>Fecha de la Transacci&oacute;n: </b>".date('d-m-Y', strtotime($result->transactionDate))."<br>
                <b>Hora de la Transacci&oacute;n: </b>".date('H:i:s', strtotime($result->transactionDate))."<br>
                <b>Tarjeta: </b>************{$result->cardDetail->cardNumber}<br>
                <b>Mensaje de Rechazo: </b>{$result->detailOutput->responseDescription}
            </p>";
            return $message;
        } else if (isset($result->error)) {
            $error = $result->error;
            $detail = isset($result->detail) ? $result->detail : 'Sin detalles';
            $message = "<h2>Transacci&oacute;n fallida con Webpay</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$error}<br>
                <b>Mensaje: </b>{$detail}
            </p>";
            return $message;
        } else {
            $message = "<h2>Transacci&oacute;n Fallida</h2>";
            return $message;
        }
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

    /**
     * empty cart
     * @Override
     */
    public function emptyCart($session_id = null, $order_number = null) {
        if ($session_id != null) {
            $session = JFactory::getSession();
            $session->close();
            session_id($session_id);
            session_start();
        }
        $cart = $this->getCurrentCart();
        $cart->emptyCart();
        return true;
    }

    //Helpers

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


    // Actions

    private function getAllConfig() {
        $config = array(
            'MODO' => $this->getConfig("ambiente"),
            'COMMERCE_CODE' => $this->getConfig("id_comercio"),
            'PUBLIC_CERT' => $this->getConfig("cert_public"),
            'PRIVATE_KEY' => $this->getConfig("key_secret"),
            'WEBPAY_CERT' => $this->getConfig("cert_transbank"),
            'ECOMMERCE' => 'virtuemart'
        );
        return $config;
    }

    private function createPdf() {

        $config = $this->getAllConfig();

        $healthcheck = new HealthCheck($config);
        $json = $healthcheck->printFullResume();

        $document = $_GET["document"];
        $temp = json_decode($json);
        if ($document == "report"){
            unset($temp->php_info);
        } else {
            $temp = array('php_info' => $temp->php_info);
        }

        $rl = new ReportPdfLog($document);
        $rl->getReport(json_encode($temp));
        die;
    }

    private function updateConfig() {
        $logHandler = new LogHandler();
        $logHandler->setLockStatus($_GET['status'] == 'true' ? true : false);
        $logHandler->setnewconfig((integer)$_GET['max_days'], (integer)$_GET['max_weight']);
        die;
    }

    private function checkTransaction() {
        $config = $this->getAllConfig();
        $healthcheck = new HealthCheck($config);
        $response = $healthcheck->setInitTransaction();
        echo json_encode($response);
        die;
    }
}
