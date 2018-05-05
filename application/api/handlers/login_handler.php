<?php
namespace MyApplication;

use SmartFactory\JsonApiRequestHandler;

use function SmartFactory\singleton;

singleton(JsonApiRequestHandler::class)->registerApiRequestHandler("login", function($handler, $api_request) {
  
  /*
  if(!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST')
  {
    $response_data["result"] = "error";
    
    $response_data["errors"] = [
      ["error_code" => "method_not_supported", "error_text" => "POST request expected!"]
    ];
    
    $handler->reportErrors($response_data, ['HTTP/1.1 401 Unauthorized']);
    
    return false;
  }
  */
  
  $response_data = array();

  if(empty($_REQUEST["user"]) || empty($_REQUEST["password"]))
  {
    $response_data["result"] = "error";
    
    $response_data["errors"] = [
      ["error_code" => "login_failed", "error_text" => "Wrong login or password!"]
    ];
    
    $handler->reportErrors($response_data, ['HTTP/1.1 401 Unauthorized']);
    
    return false;
  }
  
  $response_data["result"] = "success";
  
  $response_data["user"] = [
    "first_name"  => "John",
    "Last_name"  => "Smith"
  ];
  
  $handler->sendJsonResponse($response_data);
  
  return true;
});
?>