<?php

$direccion = dirname(dirname(dirname(__FILE__)));
$fileh = $direccion."/libwebpay/LogHandler.php";

include_once ($fileh);

if (!isset($_POST['req']) or empty($_POST['req'])) {
  exit;
}

$objeto = $_POST['req'];
$obj = json_decode($objeto);
$logHandler = new LogHandler();

if (isset($_POST['update']) and $_POST['update'] == 'yes') {
  $logHandler->setnewconfig((integer)$obj->max_days, (integer)$obj->max_weight);
} else {
  if ($obj->status === true) {
    $logHandler->setLockStatus($obj->status);
    $logHandler->setnewconfig((integer)$obj->max_days, (integer)$obj->max_weight);
  } else {
    $logHandler->setLockStatus(false);
  }
}
?>
