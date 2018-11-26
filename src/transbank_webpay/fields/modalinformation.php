<?php
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

class JFormFieldModalInformation extends JFormField {

    /**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
    var $type = 'ModalInformation';

    protected function getInput() {
        return include_once('modalcontent.php');
    }
}
