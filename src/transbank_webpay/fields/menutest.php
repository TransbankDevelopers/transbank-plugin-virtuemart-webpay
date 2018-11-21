<?php
class JFormFieldMenuTest extends JFormField {
    var $type = 'MenuTest';
    protected function getInput() {
        return include('menucontent.php');
    }
}
