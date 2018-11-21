<?php
include_once(dirname( dirname(__FILE__) ) . '/library/ReportPdfLog.php');
if (!isset($_POST['item']) or empty($_POST['item'])) {
  exit;
}
ob_start();
$document = $_POST["document"];
$objeto = $_POST['item'];
$obj = json_decode($objeto);
$getpdf = new ReportPdfLog($document);
$temp = json_decode($objeto);
if ($document == "report"){
    unset($temp->php_info);
} else {
    $temp = array('php_info' => $temp->php_info);
}
$objeto = json_encode($temp);
ob_end_clean();
$getpdf->getReport($objeto);
?>
