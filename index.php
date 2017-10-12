<?php

$start_time = microtime(true);

$IS_DEVELOPMENT = FALSE;
require 'token.php';

//show_error(503, "503 Service Unavailable", "We are currently under maintenance.");

function show_error($response_code, $status_code, $message) 
{
    http_response_code($response_code);
    header('Content-Type: application/json');
    echo json_encode(array('status_code' => $status_code, 'message' => $message));
//    include("error.php");
    die;
}

function validate_post() 
{
    global $headers;
    
    if ($_SERVER["REQUEST_METHOD"] != 'POST') {
        show_error(405, "405 Method Not Allowed", "Invalid Method");
    }
    if (!isset($headers['Content-Type']) || strpos($headers['Content-Type'], 'application/json')  !== 0) {
        show_error(400, "400 Bad Request", "Invalid Content Type");
    }
}

function validate_get()
{
    if ($_SERVER["REQUEST_METHOD"] != 'GET') {
        show_error(405, "405 Method Not Allowed", "Invalid Method");
    }
}

if (function_exists("getallheaders")) {
    $headers = getallheaders();
} else {
    $headers['x-api-key'] = isset($_SERVER["HTTP_X_API_KEY"]) ? $_SERVER["HTTP_X_API_KEY"] : "";
    $headers['Content-Type'] = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : "";
}
//var_dump($headers);
if (!isset($headers['x-api-key']) || $headers['x-api-key'] != X_API_KEY_TOKEN) {
    show_error(401, "401 Unauthorized", "Invalid Token");
}

//print_r($headers);
//echo "_SERVER[\"REQUEST_METHOD\"]" . $_SERVER["REQUEST_METHOD"] . "\r\n";
//echo "_SERVER[\"QUERY_STRING\"]" . $_SERVER["QUERY_STRING"] . "\r\n";

$query_string = isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "";
$params = explode("/", $query_string);

$service = isset($params[0]) ? $params[0] : "";
$current_world = isset($params[1]) && is_numeric($params[1]) ? $params[1]: "1";
$current_world = "1"; // leaderboard

// if service suffix is -dev then mark as DEVELOPMENT
if (strpos($service, "-dev")) {
    $service = str_replace("-dev", "", $service);
    $IS_DEVELOPMENT = true;
}

//var_dump($params);

// validate service ...
switch ($service) {
//    case 'leaderboard' :
//        validate_get();
//        break;
    case 'list' :
    case 'save' :
        validate_post();
        $input = file_get_contents("php://input");
        break;
    default :
        show_error(503, "503 Service Unavailable", "Invalid Service");
}

// valid service goes here ... then try 'execute service' ...
try {
    $service_result = include($service.'.php');
} catch (Exception $ex) {
    show_error(500, "500 Internal Server Error", $ex->getMessage());
}
        
$end_time = microtime(true);

//$service_result['headers'] = $headers;
$service_result['execution_time'] = number_format($end_time - $start_time, 5);
$service_result['memory_usage'] = memory_get_usage(true);

//http_response_code(200);
header('Content-Type: application/json');

echo json_encode($service_result);
