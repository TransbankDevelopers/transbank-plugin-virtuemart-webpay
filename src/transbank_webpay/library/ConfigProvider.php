<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
	require_once(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

if (!class_exists('LogHandler')) {
    require_once('LogHandler.php');
}

/**
 * Transbank Webpay config provider for outside plugin instance
 * @autor vutreras (victor.utreras@continuum.cl)
 */
class ConfigProvider {

    function __construct() {
        $this->log = new LogHandler();
        $this->_config = 0;
    }

    /**
     * return configuration for the plugin
     */
    public function getConfig($key = NULL) {
        if ($this->_config === 0) {
            try {
                $this->_config = array();
                $db = JFactory::getDbo();
                $query = $db->getQuery(true);
                $query->select($db->quoteName(array('payment_params')));
                $query->from($db->quoteName('#__virtuemart_paymentmethods'));
                $query->where($db->quoteName('payment_element') . ' = '. $db->quote('transbank_webpay'));
                //$query = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` where `payment_element` = ' . $db->quote('transbank_webpay');
                $db->setQuery($query);
                $values = $db->loadObjectList();
                $arr = explode('|', $values[0]->payment_params);
                foreach ($arr as $val) {
                    $kv = explode('=', $val);
                    $k = str_replace('"', '', $kv[0]);
                    $v = str_replace('"', '', $kv[1]);
                    $this->_config[$k] = $v;
                }
            } catch (Exception $e) {
                $this->log->logError($e);
            }
        }
        return $key != NULL ? $this->_config[$key] : $this->_config;
    }
}
