<?php
require_once('tcpdf/TCPDF/tcpdf.php');

include_once('LogHandler.php');

class ReportPdf {

    var $buffer;

    public function __construct(){

        $css = "
        <style type='text/css'>
        body{
            font-family: helvetica;
        }
        tr {
            display:block; width:500px;
        }
        td {
            display: block;
            height: 11px;
        }
        ul {
            margin: 0px;
        }
        .pdf1 {
            background-color: #d6012f;
            font-size: 18px;
            color: white;
            height:32px;
            display: block;
        }
        .pdf2 {
            background-color: #008ac3;
            color: white;
            font-size: 16px ;
            font-weight: bold;
        }
        .pdf3 {
            font-size: 12px;
            font-weight: bold;
            width: 30%;
        }
        .final {
            font-size: 9px;
            width: 70%;
            font-family: monospace;
        }
        .log{
            font-size: 9px;
            font-family: monospace;
        }
        pre {
            margin: 0;
        }
        a {
            color: black;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .center {
            text-align: center;
        }
        .center table {
            margin: 1em auto;
            text-align: left;
        }
        .center th {
            text-align: center !important;
        }
        td, th {
            border: 1px solid #666;
            vertical-align: baseline;
            padding: 4px 5px;
        }
        h1 {
            font-size: 150%;
        }
        h2 {
            font-size: 125%;text-align:
            center; color: black;
        }
        .p {
            text-align: left;
        }
        .e {
            background-color: #ccf;
            font-weight: bold;
            text-align: left;
        }
        .h {
            background-color: #686767;
            font-weight: bold;
        }
        .v {
            background-color: #ddd;
            overflow-x: auto;text-align: left;
        }
        .v i {
            color: #999;
        }
        img {
            float: right; border: 0;
        }
        hr {
            background-color: #ccc;
            border: 0;
            height: 1px;
        }
        </style>
        ";
//
        $this->buffer='<html>
        <head>
        <link href="'.__DIR__.'/css/ReportPdf.css" rel="stylesheet" type="text/css" media="all" />
            ' . $css . '
        </head>
        <body>';

        $this->log = new LogHandler();
    }

    private function chain($element, $level){
        if ($level==0) {
            $this->buffer.= '<table>';
        }

        if (is_array($element)){
            $child_lvl=$level+1;
            $child=array_keys($element);
            for ($count_child=0; $count_child<count($child); $count_child++) {

                if ($child[$count_child] == 'php_info' ) {
                    $this->buffer.= '<tr><td colspan="2" class="pdf1">'.$child[$count_child].'</td></tr>';
                    $this->buffer.= '<tr><td colspan="2" >'.$element['php_info']['string']['content'].'</td></tr>';
                } else if ($child[$count_child] == 'log' ){
                    $this->buffer.='<tr><td colspan="2" class="log">'.$element['log'].'</td></tr>';
                } else if ($child[$count_child] == 'public_cert' || $child[$count_child] == 'private_key' || $child[$count_child] == 'webpay_cert'){

                } else{
                    if ($child_lvl != 3) {
                        $this->buffer.= '<tr><td colspan="2" class="pdf'.$child_lvl.'">'.$child[$count_child].'</td></tr>';
                    } else {
                        $this->buffer.= '<tr><td class="pdf'.$child_lvl.'">'.$child[$count_child].'</td>';
                    }

                    $this->chain($element[$child[$count_child]], $child_lvl);
                }
            }
        } else {
            $this->buffer.= '<td class="final">'.$element.'</td></tr>';
        }
        if ($level==0) {
            $this->buffer.= '</table></body></html>';
        }
    }

    public function getReport($myJSON){
        //$this->log->logInfo($myJSON);
        $obj = json_decode($myJSON,true);
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(10,5,10, false);
        $pdf->AddPage();
        $pdf->setFontSubsetting(false);
        $this->chain($obj,0);
        $pdf->writeHTML($this->buffer, 0, 1, 0, true, '');
        $pdf->Output('report_webpay_'.date_timestamp_get(date_create()).'.pdf', 'D');
    }
}
