<?php
date_default_timezone_set('Asia/Calcutta');
ini_set("max_execution_time", 0);
include_once './includes/config_reader.php';
$configobj = config_reader::getInstance();

$configDetails = $configobj->getKey('db_config');
define('SERVER',$configDetails['SERVER']);
define('USER',$configDetails['USER']);
define('PASSWORD',$configDetails['PASSWORD']);
define('DATABASE',$configDetails['DATABASE']);

$serverApiDetails = $configobj->getKey('server_api_details');
define('MANTHAN_API_URL',$serverApiDetails['MANTHAN_API_URL']);

$thresholdValues = $configobj->getKey('threshold_values');

