<?php
ini_set("display_errors", "1");
echo '<pre>';
include_once './includes/constants.php';
include_once './includes/database_config.php';
include_once './classes/process_request.php';

$obj_process_request = new process_request();
$res = $obj_process_request->is_first_request();

if( isset( $res ) && (int) ( count($res) ) > 1 ){
    $obj_process_request->process_all_request($thresholdValues);    
}elseif( isset( $res ) && (int) ( count($res) ) == 1 ){    
    $obj_process_request->process_first_request( $res[0]['id'] , $thresholdValues);    
}