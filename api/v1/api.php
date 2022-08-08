<?php
// Requests from the same server don't have a HTTP_ORIGIN header
if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
}

require_once 'MyAPI.class.php';

$API=null;
try {
	$API = new MyAPI($_REQUEST['request'], $_SERVER['HTTP_ORIGIN']);
} catch (Exception $e) {
    echo json_encode(Array('error' => $e->getMessage()));
}
try {
    $res=$API->processAPI();
    if (isset($API->binaryArray)) {
	header('Content-Type: application/octet-stream');
	foreach($API->binaryArray as $v) echo (chr($v));
    } else if (isset($API->fileToDownload)) {     	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($API->fileToDownload).'"');
	header('Expires: 0');
    	header('Cache-Control: must-revalidate');
	header('Pragma: public');
    	header('Content-Length: ' . filesize($API->fileToDownload));
	readfile($API->fileToDownload);
    }
    else echo $res;
} catch (Exception $e) {
    echo $API->changeStatus(Array('error' => $e->getMessage()),$e->getCode());
}
?>