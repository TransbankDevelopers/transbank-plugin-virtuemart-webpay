<?php
defined('JPATH_BASE') or die();

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

include_once(dirname( dirname(__FILE__) ) . '/library/HealthCheck.php');
include_once(dirname( dirname(__FILE__) ) . '/library/LogHandler.php');

    $db = JFactory::getDbo();
    $query = $db->getQuery(true);
    $query->select($db->quoteName(array('payment_params')));
    $query->from($db->quoteName('#__virtuemart_paymentmethods'));
    $query->where($db->quoteName('payment_element') . ' = '. $db->quote('webpay'));
    $query->order('ordering ASC');
    $db->setQuery($query);
    $results = $db->loadObjectList();
    $array = json_decode(json_encode($results[0]), True);
    $array = $array['payment_params'];
    $arr = explode("|", $array);
    array_pop($arr);

    $configBd = array();
    foreach ($arr as $value) {
        $array = explode('=', $value);
        $array[1] = trim($array[1], '"');
        $configBd[$array[0]] = $array[1];
    }

    $config = null;

    if (!isset($configBd['MODO']) or $configBd['MODO'] == "" or $configBd['MODO'] == null or $configBd['MODO'] == 'INTEGRACION') {
        include_once(dirname( dirname(__FILE__) ) . '/library/Certificates.php');
        $config = array(
            'MODO' => $certificate['environment'],
            'COMMERCE_CODE' => $certificate['commerce_code'],
            'PUBLIC_CERT' => $certificate['public_cert'],
            'PRIVATE_KEY' => $certificate['private_key'],
            'WEBPAY_CERT' => $certificate['webpay_cert'],
            'ECOMMERCE' => 'virtuemart'
        );
    } else {
        $config = array(
            'MODO' => "{$configBd['MODO']}",
            'COMMERCE_CODE' => "{$configBd['id_comercio']}",
            'PUBLIC_CERT' => "{$configBd['cert_public']}",
            'PRIVATE_KEY' => "{$configBd['key_secret']}",
            'WEBPAY_CERT' => "{$configBd['cert_transbank']}",
            'ECOMMERCE' => 'virtuemart'
        );
    }

    $loghandler = new LogHandler();
    $logs = json_decode($loghandler->getResume());

    $healthCheck = new HealthCheck($config);
    $res = json_decode($healthCheck->printFullResume());

    if ($res->validate_init_transaction->status->string == 'OK') {
        $respuesta_init = "<tr><td><div title='URL entregada por Transbank para realizar la transacción' class='label label-info'>?</div> <b>URL: </b></td><td class='tbk_table_trans'>{$res->validate_init_transaction->response->url}</td></tr><tr><td><div title='Token entregada por Transbank para realizar la transacción' class='label label-info'>?</div> <b>Token: </b></td><td class='tbk_table_trans'><code>{$res->validate_init_transaction->response->token_ws}</code></td></tr>";
    }else{
        $respuesta = "Error!";
    }

    function classresp($var){
        if ($var == "OK") {
            return "<span class='label label-success'>OK</span>";
        }else{
            return "<span class='label label-danger'>{$var}</span>";
        }
    }

    if (isset($logs->last_log->log_content)) {
        $res_logcontent = $logs->last_log->log_content;
        $log_file = $logs->last_log->log_file;
        $log_file_weight = $logs->last_log->log_weight;
        $log_file_regs = $logs->last_log->log_regs_lines;
    } else {
        $res_logcontent = $logs->last_log;
        $log_file = json_encode($res_logcontent);
        $log_file_weight = $log_file;
        $log_file_regs = $log_file;
    }

    if ($logs->config->status === false ) {
        $estado = "<span class='label label-warning'>Desactivado sistema de Registros</span>";
    }else{
        $estado = "<span class='label label-success'>Activado sistema de Registros</span>";
    }

    $logs_list = "<ul>";
    if (is_array($logs->logs_list) || is_object($logs->logs_list)) {
        foreach ($logs->logs_list as $value) {
            $logs_list .= "<li>{$value}</li>";
        }
    }
    $logs_list .= "</ul>";

    $logs_main_info =
            "<table>
                <tr>
                    <td><div title='Informa si actualmente se guarda la información de cada compra mediante Webpay' class='label label-info'>?</div> <b>Estado de Registros: </b></td>
                    <td class='tbk_table_td'>{$estado}</td>
                </tr>
                <tr>
                    <td><div title='Carpeta en el servidor en donde se guardan los archivos con la informacón de cada compra mediante Webpay' class='label label-info'>?</div> <b>Directorio de Registros: </b></td>
                    <td class='tbk_table_td'>".stripslashes(json_encode($logs->log_dir))."</td>
                </tr>
                <tr>
                    <td><div title='Cantidad de archivos que guardan la información de cada compra mediante Webpay' class='label label-info'>?</div> <b>Cantidad de Registros en Directorio: </b></td>
                    <td class='tbk_table_td'>".json_encode($logs->logs_count->log_count)."</td>
                </tr>
                <tr>
                    <td><div title='Lista los archivos archivos que guardan la información de cada compra mediante Webpay' class='label label-info'>?</div> <b>Listado de Registros Disponibles: </b></td>
                    <td class='tbk_table_td'>{$logs_list}</td>
                </tr>
            </table>";

    $plugininfo =
                "<tr>
                    <td><b>E-commerce</b></td>
                    <td>{$res->server_resume->plugin_info->ecommerce}</td>
                </tr>
                <tr>
                    <td><b>Version E-commerce</b></td>
                    <td>{$res->server_resume->plugin_info->ecommerce_version}</td>
                </tr>
                <tr>
                    <td><b>Version Plugin Webpay Instalada</b></td>
                    <td>{$res->server_resume->plugin_info->current_plugin_version}</td>
                </tr>
                <tr>
                    <td><b>Ultima Version disponible para este E-commerce</b></td>
                    <td>{$res->server_resume->plugin_info->last_plugin_version}</td>
                </tr>";

    $tb_max_logs_days = $logs->config->max_logs_days;
    $tb_max_logs_weight = $logs->config->max_log_weight;
    if ($logs->config->status === true) {
        $tb_check_regs = "<input type='checkbox' name='tb_reg_checkbox' id='tb_reg_checkbox' checked>";
        $tb_btn_update = '<td><button type="button" name="tb_update" id="tb_update" class="btn btn-info">Actualizar Parametros</button></td>';
    } else {
        $tb_check_regs = "<input type='checkbox' name='tb_reg_checkbox' id='tb_reg_checkbox' >";
        $tb_btn_update = '<td><button type="button" name="tb_update" id="tb_update" class="btn btn-info disabled">Actualizar Parametros</button></td>';
    }
?>

<style media="screen">
    .no-border{
    }
    H3.menu-head{
        background-color: #d6012f;
        color: #ffffff;
    }
    .invisible{
        visibility:hidden;
    }
    .tbk_table_info{
        width:100% !important;
        line-height: 18pt;
    }
    .tbk_table_td{
        width:40%;
    }
    .tbk_table_trans{
        width:60%;
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.4/js/bootstrap-switch.min.js" charset="utf-8"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.4/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet" >

<div class="modal fade" id="tb_commerce_mod_info" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tb_main_info" data-toggle="tab">Información</a></li>
                        <li><a href="#tb_php_info" data-toggle="tab">PHP info</a></li>
                        <li><a href="#tb_logs" data-toggle="tab">Registros</a></li>
                    </ul>
                </h4>
            </div>
            <!--Inicio modal-body-->
            <div class="modal-body">
                <!--Inicio main info-->
                <div id="tb_main_info" class="tab-pane fade active in">
                    <!-- inicio container-fluid -->
                    <div class="container-fluid">
                        <div class="no-border">
                            <h3 class="menu-head">Informacion de Plugin / Ambiente</h3>
                            <table class="tbk_table_info">
                                <tr>
                                    <td>
                                        <div title="Nombre del E-commerce instalado en el servidor" class="label label-info">?</div>
                                        <b>Software E-commerce</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->server_resume->plugin_info->ecommerce; ?>
                                    </td>
                                    <input type="hidden" name="tb_ecommerce" id="tb_ecommerce"
                                            value=<?php echo '"'.$res->server_resume->plugin_info->ecommerce.'"'; ?>>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Versión de <?php echo $res->server_resume->plugin_info->ecommerce; ?> instalada en el servidor"
                                            class="label label-info">?</div> <b>Version E-commerce</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->server_resume->plugin_info->ecommerce_version; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Versión del plugin Webpay para <?php echo $res->server_resume->plugin_info->ecommerce; ?> instalada actualmente"
                                            class="label label-info">?</div> <b>Version Plugin Webpay Instalada</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->server_resume->plugin_info->current_plugin_version; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Última versión del plugin Webpay para <?php echo $res->server_resume->plugin_info->ecommerce; ?> disponible"
                                            class="label label-info">?</div> <b>Ultima Version de Plugin Disponible</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->server_resume->plugin_info->last_plugin_version; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="no-border">
                            <h3 class="menu-head">Validacion de Certificados</h3>
                            <h4>Consistencias</h4>
                            <table class="tbk_table_info">
                                <tr>
                                    <td>
                                        <div title="Informa si las llaves ingresadas por el usuario corresponden al certificado entregado por Transbank"
                                            class="label label-info">?</div> <b>Consistencias con llaves: </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo classresp($res->validate_certificates->consistency->cert_vs_private_key); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Informa si el código de comercio ingresado por el usuario corresponde al certificado entregado por Transbank"
                                            class="label label-info">?</div> <b>Validacion Codigo de commercio: </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo classresp($res->validate_certificates->consistency->commerce_code_validate); ?>
                                    </td>
                                </tr>
                            </table>
                            <hr>
                            <h4>Información Certificado</h4>
                            <table class="tbk_table_info">
                                <tr>
                                    <td>
                                        <div title="CN (common name) dentro del certificado, en este caso corresponde al código de comercio emitido por Transbank"
                                            class="label label-info">?</div> <b>Código de Comercio Valido </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->validate_certificates->cert_info->subject_commerce_code; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Versión del certificado emitido por Transbank" class="label label-info">?</div>
                                        <b>Versión certificado </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->validate_certificates->cert_info->version; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Informa si el certificado está vigente actualmente" class="label label-info">?</div>
                                        <b>Vigencia </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo classresp($res->validate_certificates->cert_info->is_valid); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Fecha desde la cual el certificado es válido" class="label label-info">?</div>
                                        <b>Válido desde </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->validate_certificates->cert_info->valid_from; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Fecha hasta la cual el certificado es válido" class="label label-info">?</div>
                                        <b>Válido hasta </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->validate_certificates->cert_info->valid_to; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="no-border">
                            <h3 class="menu-head">Información de Servidor</h3>
                            <h4>Informacion Principal</h4>
                            <table class="tbk_table_info">
                                <tr>
                                    <td>
                                        <div title="Descripción del Servidor Web instalado" class="label label-info">?</div>
                                        <b>Software Servidor</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->server_resume->server_version->server_software; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <h4>PHP</h4>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Informa si la versión de PHP instalada en el servidor es compatible con el plugin de Webpay"
                                            class="label label-info">?</div> <b>Estado</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo classresp($res->server_resume->php_version->status); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Versión de PHP instalada en el servidor" class="label label-info">?</div>
                                        <b>Version</b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $res->server_resume->php_version->version; ?>
                                    </td>
                                </tr>
                            </table>

                            <hr>
                            <h4>Extensiones PHP requeridas</h4>
                            <table class="table table-responsive table-striped">
                                <thead>
                                    <th>Extension</th>
                                    <th>Estado</th>
                                    <th>Version</th>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><b>openssl</b></td>
                                        <td>
                                            <?php echo classresp($res->php_extensions_status->openssl->status); ?>
                                        </td>
                                        <td>
                                            <?php echo $res->php_extensions_status->openssl->version; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><b>SimpleXML</b></td>
                                        <td>
                                            <?php echo classresp($res->php_extensions_status->SimpleXML->status); ?>
                                        </td>
                                        <td>
                                            <?php echo $res->php_extensions_status->SimpleXML->version; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><b>soap</b></td>
                                        <td>
                                            <?php echo classresp($res->php_extensions_status->soap->status); ?>
                                        </td>
                                        <td>
                                            <?php echo $res->php_extensions_status->soap->version; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><b>mcrypt</b></td>
                                        <td>
                                            <?php echo classresp($res->php_extensions_status->mcrypt->status); ?>
                                        </td>
                                        <td>
                                            <?php echo $res->php_extensions_status->mcrypt->version; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><b>dom</b></td>
                                        <td>
                                            <?php echo classresp($res->php_extensions_status->dom->status); ?>
                                        </td>
                                        <td>
                                            <?php echo $res->php_extensions_status->dom->version; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="no-border">
                            <h3 class="menu-head">Validación Transacción</h3>
                            <h4>General</h4>
                            <table class="tbk_table_info">
                                <tr>
                                    <td>
                                        <div title="Informa el estado de la comunicación con Transbank mediante método init_transaction"
                                            class="label label-info">?</div> <b>Estado: </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo classresp($res->validate_init_transaction->status->string); ?>
                                    </td>
                                </tr>
                            </table>
                            <h4>Respuesta</h4>
                            <table>
                                <?php echo $respuesta_init; ?>
                            </table>
                        </div>

                        <!--fin container-fluid -->
                    </div>
                    <!-- fin main info -->
                </div>
                <div class="tab-pan fade" id="tb_php_info">
                    <div class="container-fluid">
                        <?php echo $res->php_info->string->content; ?>
                    </div>
                </div>
                <div class="tab-pane fade" id="tb_logs">
                    <div class="container-fluid">
                        <div class="form_validate">
                            <h3 class="menu-head">Configuracion</h3>
                            <table class="tbk_table_info">
                                <tr>
                                    <td>
                                        <div title="Al activar esta opción se habilita que se guarden los datos de cada compra mediante Webpay"
                                            class="label label-info">?</div> <b>Activar Registro: </b>
                                    </td>
                                    <td class="tbk_table_td">
                                        <?php echo $tb_check_regs; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Cantidad de días que se conservan los datos de cada compra mediante Webpay"
                                            class="label label-info">?</div> <b>Cantidad de Dias a Registrar</b>
                                    </td>
                                    <td class="tbk_table_td"><input type="number" name="tb_regs_days" id="tb_regs_days"
                                            value=<?php echo '"' .(integer)$tb_max_logs_days.'"'; ?> placeholder="1"
                                        maxlength="2" size="2" min="1" max="30"> <span>Dias</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div title="Peso máximo (en Megabytes) de cada archivo que guarda los datos de las compras mediante Webpay"
                                            class="label label-info">?</div> <b>Peso maximo de Registros: </b>
                                    </td>
                                    <td class="tbk_table_td"> <input type="number" name="tb_regs_weight" id="tb_regs_weight"
                                            value=<?php echo '"' .(integer)$tb_max_logs_weight.'"'; ?> placeholder="2"
                                        maxlength="2" size="2" min="2" max="10"> <span>Mb</span></td>
                                </tr>
                                <tr>
                                    <?php echo $tb_btn_update; ?>
                                </tr>
                            </table>
                        </div>
                        <div id="maininfo">
                            <h3 class="menu-head">Información de Registros</h3>
                            <?php echo $logs_main_info; ?>
                        </div>
                        <h3 class="menu-head">Ultimos Registros</h3>
                        <table class="tbk_table_info">
                            <tr>
                                <td>
                                    <div title="Nombre del útimo archivo de registro creado" class="label label-info">?</div>
                                    <b>Último Documento: </b>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $log_file; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div title="Peso del último archivo de registro creado" class="label label-info">?</div>
                                    <b>Peso de Documento: </b>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $log_file_weight; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div title="Cantidad de líneas que posee el último archivo de registro creado"
                                        class="label label-info">?</div> <b>Cantidad de Líneas: </b>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $log_file_regs; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><b></b></td>
                                <td></td>
                            </tr>
                        </table>
                        <br>
                        <b>Contenido último Log: </b>
                        <div class="log_content">
                            <pre><code><?php echo stripslashes((string)$res_logcontent); ?></code></pre>
                        </div>
                    </div>
                </div>
                <!--FIN modalbody-->
            </div>
            <div class="modal-footer">
                <button type=button class="btn btn-danger btn-lg" id="boton_pdf">Crear PDF</button>
                <button type=button class="btn btn-danger btn-lg" id="boton_php_info">Crear PHP info</button>
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

    jQuery().ready(function($){

        var options = {
            onText: "Si",
            size: "small",
            onColor: 'success',
            offColor: 'warning',
            offText: "No",
            animate: true
        };
        $('#tb_reg_checkbox').bootstrapSwitch(options);

        $('#tb_commerce_mod_info').hide();

        $('#tb_commerce_mod_info').on('show.bs.modal', function () {
            $('.modal .modal-body').css('overflow-y', 'auto');
            $('.modal .modal-body').css('max-height', $(window).height() * 0.6);
            $('.modal .modal-body').css('min-height', $(window).height() * 0.6);
        });

        //$('#tb_full_obj').hide();
        $('#tb_php_info').hide();
        $('#tb_logs').hide();
        $('#boton_php_info').hide();

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href");
            if (target == '#tb_main_info') {
                $('#boton_pdf').show();
                $('#boton_php_info').hide();
                $('#tb_main_info').show();
                $('#tb_php_info').hide();
                $('#tb_logs').hide();
                console.log('se habilita boton de imprimir resultados');
            } else {
                $('#boton_pdf').hide();
                if (target == '#tb_php_info') {
                    $('#boton_php_info').show();
                    $('#tb_main_info').hide();
                    $('#tb_logs').hide();
                    $('#tb_php_info').show();
                } else {
                    $('#tb_main_info').hide();
                    $('#tb_logs').show();
                    $('#tb_php_info').hide();
                }
            }
        });

        function creaPdf(documentValue) {

            var iframe = document.createElement("iframe");
            iframe.name = "myTarget";
            iframe.style.display = "none";
            document.body.appendChild(iframe);

            var ob = <?php echo json_encode($res); ?>;
            var data = {"item":JSON.stringify(ob), 'document': documentValue};

            var form = document.createElement("form"):
            form.action = "../plugins/vmpayment/transbank_webpay/transbank_webpay/fields/creapdf.php";
            form.method = 'POST';
            form.target = iframe.name;
            form.style.display = "none";

            for(var name in data) {
                var node = document.createElement("input");
                node.name  = name;
                node.value = data[name].toString();
                form.appendChild(node.cloneNode());
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        $('#boton_pdf').click(function(evt) {
            creaPdf('report');
            evt.preventDefault();
        });

        $('#boton_php_info').click(function(evt) {
            creaPdf('php_info');
            evt.preventDefault();
        });

        function cargaConfig(data) {
            $.ajax({
                url: '../plugins/vmpayment/webpay/webpay/fields/cargaconfig.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(data, status){
                    $('#maininfo').load(document.URL + '#maininfo');
                },
                error: function(e){
                    $('#maininfo').load(document.URL + '#maininfo');
                }
            });
        }

        $('#tb_update').click(function(evt) {
            var max_days = $("#tb_regs_days").val();
            var max_weight = $("#tb_regs_weight").val();
            var data = {
                status: true,
                max_days: max_days,
                max_weight: max_weight,
                update: true
            };
            cargaConfig(data);
            evt.preventDefault();
        });

        $('#tb_reg_checkbox').on('switchChange.bootstrapSwitch', function(evt) {
            var max_days = $("#tb_regs_days").val();
            var max_weight = $("#tb_regs_weight").val();
            var status = false;
            if ($('#tb_reg_checkbox').is(':checked')) {
                status = true;
                $('#tb_update').removeClass('disabled');
            } else {
                $('#tb_update').addClass('disabled');
            }
            var data = {
                status: status,
                max_days: max_days,
                max_weight: max_weight,
                update: true
            };
            cargaConfig(data);
            evt.preventDefault();
        });

        $('#maininfo').load(document.URL +  ' #maininfo');
})

</script>
