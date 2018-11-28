<?php
require_once('TransbankSdkWebpay.php');

class HealthCheck {

    var $publicCert;
    var $privateKey;
    var $webpayCert;
    var $commerceCode;
    var $environment;
    var $extensions;
    var $versioninfo;
    var $resume;
    var $fullResume;
    var $certficados;
    var $ecommerce;
    var $config;

    public function __construct($config) {
        $this->config = $config;
        $this->environment = $config['MODO'];
        $this->commerceCode = $config['COMMERCE_CODE'];
        $this->publicCert = $config['PUBLIC_CERT'];
        $this->privateKey = $config['PRIVATE_KEY'];
        $this->webpayCert = $config['WEBPAY_CERT'];
        $this->ecommerce = $config['ECOMMERCE'];
        // extensiones necesarias
        $this->extensions = array(
            'openssl',
            'SimpleXML',
            'soap',
            'dom'
        );
    }

    // validacion certificado publico versus la llave
    private function getValidateCertificates() {
        if ($var = openssl_x509_parse($this->publicCert)) {
            $today = date('Y-m-d H:i:s');
            $from = date('Y-m-d H:i:s', $var['validFrom_time_t']);
            $to = date('Y-m-d H:i:s', $var['validTo_time_t']);
            if ($today >= $from and $today <= $to) {
                $val = "OK";
            } else {
                $val = "Error!: Certificado Inválido por Fecha";
            }
            $this->certinfo = array(
                'subject_commerce_code' => $var['subject']['CN'],
                'version' => $var['version'],
                'is_valid' => $val,
                'valid_from' => date('Y-m-d H:i:s', $var['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $var['validTo_time_t']),
            );
        } else {
            $this->certinfo = array(
                'subject_commerce_code' => $this->commerceCode,
                'version' => 'Error',
                'is_valid' => 'Error',
                'valid_from' => 'Error',
                'valid_to' => 'Error',
            );
        }
        if (openssl_x509_check_private_key($this->publicCert, $this->privateKey)) {
            if ($this->commerceCode == $this->certinfo['subject_commerce_code']) {
                $this->certificates = array(
                    'cert_vs_private_key' => 'OK',
                    'commerce_code_validate' => 'OK'
                );
            }
        } else {
            $this->certificates = array(
                'cert_vs_private_key' => 'Error!: Certificados inconsistentes',
                'commerce_code_validate' => 'Error'
            );
        }
        return array('consistency' => $this->certificates, 'cert_info' => $this->certinfo);
    }

    // valida version de php
    private function getValidatephp(){
        if (version_compare(phpversion(), '7.2.1', '<=') and version_compare(phpversion(), '5.5.0', '>=')) {
            $this->versioninfo = array(
                'status' => 'OK',
                'version' => phpversion()
            );
        } else {
            $this->versioninfo = array(
                'status' => 'Error!: Versión no soportada',
                'version' => phpversion()
            );
        }
        return $this->versioninfo;
    }

    // verifica si existe la extension y cual es la version de esta
    private function getCheckExtension($extension){
        if (extension_loaded($extension)) {
            if ($extension == 'openssl') {
                $version = OPENSSL_VERSION_TEXT;
            } else {
                $version = phpversion($extension);
                if (empty($version) or $version == null or $version === false or $version == " " or $version == "") {
                    $version = "PHP Extension Compiled. ver:".phpversion();
                }
            }
            $status = 'OK';
            $result = array(
                'status' => $status,
                'version' => $version
            );
        } else {
            $result = array(
                'status' => 'Error!',
                'version' => 'No Disponible'
            );
        }
        return $result;
    }

    // obtiene ultima version exclusivamente para virtuemart
    // NOTE: lastrelasevirtuemart
    private function getLastVirtuemartVersion(){
        $request_url ='http://virtuemart.net/releases/vm3/virtuemart_update.xml';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $request_url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 130);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($curl);
        curl_close($curl);

        $xml = simplexml_load_string($response);
        $json = json_encode($xml);
        $arr = json_decode($json,true);
        $version = $arr['update']['version'];
        return $version;
    }

    // funcion para obtener info de cada ecommerce, si el ecommerce es incorrecto o no esta seteado se escapa como respuesta "NO APLICA"
    private function getEcommerceInfo($ecommerce){
        include_once(JPATH_ROOT.'/administrator/components/com_virtuemart/version.php');
        $actualversion = vmVersion::$RELEASE; // NOTE: confirmar si es como obtiene la version de ecommerce
        $lastversion = $this->getLastVirtuemartVersion();
        if (! file_exists(JPATH_PLUGINS."/vmpayment/transbank_webpay/transbank_webpay.xml")) {
            exit;
        } else {
            $xml = simplexml_load_file(JPATH_PLUGINS."/vmpayment/transbank_webpay/transbank_webpay.xml",null, LIBXML_NOCDATA);
            $json = json_encode($xml);
            $arr = json_decode($json,true);
            $currentplugin = $arr['version'];
        }
        $result = array(
            'current_ecommerce_version' => $actualversion,
            'last_ecommerce_version' => $lastversion,
            'current_plugin_version' => $currentplugin
        );
        return $result;
    }

    // creacion de retornos
    // arma array que entrega informacion del ecommerce: nombre, version instalada, ultima version disponible
    private function getPluginInfo($ecommerce) {
        $data = $this->getEcommerceInfo($ecommerce);
        $result = array(
            'ecommerce' => $ecommerce,
            'ecommerce_version' => $data['current_ecommerce_version'],
            'current_plugin_version' => $data['current_plugin_version'],
            'last_plugin_version' => $this->getPluginLastVersion($ecommerce, $data['current_ecommerce_version']) // ultimo declarado
        );
        return $result;
    }

    // arma array con informacion del ultimo plugin compatible con el ecommerce
    /*
    vers_product:
    1 => WebPay Soap
    2 => WebPay REST
    3 => PatPass
    4 => OnePay
    */
    private function getPluginLastVersion($ecommerce, $currentversion){
        return 'Indefinido';
    }

    // lista y valida extensiones/ modulos de php en servidor ademas mostrar version
    private function getExtensionsValidate(){
        foreach ($this->extensions as $value) {
            $this->resExtensions[$value] = $this->getCheckExtension($value);
        }
        return $this->resExtensions;
    }

    // crea resumen de informacion del servidor. NO incluye a PHP info
    private function getServerResume() {
        // arma array de despliegue
        $this->resume = array(
            'php_version' => $this->getValidatephp(),
            'server_version' => array('server_software' => $_SERVER['SERVER_SOFTWARE']),
            'plugin_info' => $this->getPluginInfo($this->ecommerce)
        );
        return $this->resume;
    }

    // crea array con la informacion de comercio para posteriormente exportarla via json
    private function getCommerceInfo() {
        $result = array(
            'environment' => $this->environment,
            'commerce_code' => $this->commerceCode,
            'public_cert' => $this->publicCert,
            'private_key' => $this->privateKey,
            'webpay_cert' => $this->webpayCert
        );
        return array('data' => $result);
    }

    // guarda en array informacion de funcion phpinfo
    private function getPhpInfo(){
        ob_start();
        phpinfo();
        $info = ob_get_contents();
        ob_end_clean();
        $newinfo = strstr($info, '<table>');
        $newinfo = strstr($newinfo, '<h1>PHP Credits</h1>',true);
        $return = array('string' => array('content' => str_replace('</div></body></html>','', $newinfo)));
        return $return;
    }

    public function setInitTransaction(){
        $transbankSdkWebpay = new TransbankSdkWebpay($this->config);
        $amount = 990;
        $buyOrder = "_Healthcheck_";
        $sessionId = uniqid();
        $returnUrl = "https://webpay3gint.transbank.cl/filtroUnificado/initTransaction";
        $finalUrl = "https://webpay3gint.transbank.cl/filtroUnificado/initTransaction";
        $result = $transbankSdkWebpay->initTransaction($amount, $sessionId, $buyOrder, $returnUrl, $finalUrl);
        if ($result) {
            if (!empty($result["error"]) && isset($result["error"])) {
                $status = 'Error';
            } else {
                $status = 'OK';
            }
        } else {
            if (array_key_exists('error', $result)) {
                $status =  "Error";
            }
        }
        $response = array(
            'status' => array('string' => $status),
            'response' => preg_replace('/<!--(.*)-->/Uis', '', $result)
        );
        return $response;
    }

    //compila en solo un metodo toda la informacion obtenida, lista para imprimir
    private function getFullResume() {
        $this->fullResume = array(
            'validate_certificates' => $this->getValidateCertificates(),
            'server_resume' => $this->getServerResume(),
            'php_extensions_status'  => $this->getExtensionsValidate(),
            'commerce_info' => $this->getCommerceInfo(),
            'php_info' => $this->getPhpInfo()
        );
        return $this->fullResume;
    }

    private function setpostinstall() {
        return false;
    }

    // imprime informacion de comercio y llaves
    public function printCommerceInfo() {
        return json_encode($this->getCommerceInfo());
    }

    public function printPhpInfo() {
        return json_encode($this->getPhpInfo());
    }

    // imprime resultado la consistencia de certificados y llabves
    public function printCertificatesStatus() {
        return json_encode($this->getValidateCertificates());
    }

    // imprime en formato json la validacion de extensiones / modulos de php
    public function printExtensionStatus() {
        return json_encode($this->getExtensionsValidate());
    }

    // imprime en formato json informacion del servidor
    public function printServerResume() {
        return json_encode($this->getServerResume());
    }

    // imprime en formato json el resumen completo
    public function printFullResume() {
        return json_encode($this->getFullResume());
    }

    public function getInitTransaction() {
        return json_encode($this->setInitTransaction());
    }

    public function getpostinstallinfo() {
        return json_encode($this->setpostinstall());
    }
}
