<?php
/**
 *
 * Realex payment plugin
 *
 * @author Valerie Isaksen
 * @version $Id$
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004-2015 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');
class JFormFieldWebpayImage extends JFormField {

	var $type = 'webpayImage';

	protected function getInput() {

		JHtml::_('behavior.colorpicker');

		vmJsApi::addJScript( '/plugins/vmpayment/webpay/webpay/assets/js/admin.js');
		//vmJsApi::addJScript('https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js');
		$url = "https://www.transbank.cl/";
		$logo = '<img src="https://www.transbank.cl/public/img/LogoWebpay.png" width="100" height="91"/>';
		$html = '<p><a target="_blank" href="' . $url . '"  >' . $logo . '</a> <button class="btn btn-lg btn-danger" data-toggle="modal" data-target="#tb_commerce_mod_info">Informacion</button></p>';

		return $html;
	}

}
