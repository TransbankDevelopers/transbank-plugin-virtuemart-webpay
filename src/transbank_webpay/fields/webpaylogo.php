<?php
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

class JFormFieldWebpayLogo extends JFormField {

    /**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'WebpayLogo';

	protected function getInput() {
		vmJsApi::addJScript( '/plugins/vmpayment/webpay/webpay/assets/js/admin.js');
		$url = "https://www.transbank.cl/";
		$logo = '<img src="https://www.transbank.cl/public/img/LogoWebpay.png" width="100" height="91"/>';
        $html = '<p>
                    <a target="_blank" href="' . $url . '"  >' . $logo . '</a>
                    <button class="btn btn-lg btn-danger" data-toggle="modal" data-target="#tb_commerce_mod_info">Información</button>
                </p>';
		return $html;
	}
}
