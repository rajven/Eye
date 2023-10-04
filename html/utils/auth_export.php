<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/xlsxwriter.class.php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["ExportAuth"])) {
    //export selected only
    $filename = 'all-ips.xlsx';
    header('Content-disposition: attachment; filename="'.XLSXWriter::sanitize_filename($filename).'"');
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    $header = array(
        'login'=>'string',
        'ip'=>'string',
        'mac'=>'string',
        'comment'=>'string',
        'dns'=>'string',
        'last found'=>'date',
      );

    $writer = new XLSXWriter();
    $writer->setAuthor('Eye'); 
    $writer->writeSheetHeader('Sheet1', $header );
    if ($_POST["a_selected"] * 1) {
        $auth_id = $_POST["fid"];
        foreach ($auth_id as $key => $val) {
            if ($val) {
                $sSQL = "SELECT User_list.login, User_auth.ip, User_auth.mac, User_auth.comment, User_auth.dns_name, User_auth.last_found FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.id = ".$val;
                $record = get_record_sql($db_link,$sSQL);
                $writer->writeSheetRow('Sheet1', $record);
                }
            }
        } else {
        //export all
        $sSQL = "SELECT User_list.login, User_auth.ip, User_auth.mac, User_auth.comment, User_auth.dns_name, User_auth.last_found FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.deleted = 0 ORDER BY User_auth.ip_int";
        $auth_table = mysqli_query($db_link, $sSQL);
        while ($record = mysqli_fetch_array($auth_table)) {
            $writer->writeSheetRow('Sheet1', $record);
            }
        }
    $writer->writeToStdOut();
}
