<?php

class JFormFieldMenuTest extends JFormField {
  var $type = 'menuTest';
  protected function getInput() {
      $html = include('menucontent.php');
      $html .="";
    return $html;
	}

}
