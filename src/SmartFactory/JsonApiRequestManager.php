<?php
/**
 * This file contains the implementation of the interface IApiRequestHandler
 * in the class JsonApiRequestHandler for handling JSON requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IJsonApiRequestHandler;

/**
 * Class for handling JSON requests.
 *
 * @see IJsonApiRequestHandler
 *
 * @author Oleg Schildt
 */
class JsonApiRequestManager
{
    /**
     * Internal array for storing the handler mappings.
     *
     * @var array
     *
     * @see registerApiRequestHandler()
     *
     * @author Oleg Schildt
     */
    protected $handler_table = [];
    
    /**
     * Internal flag for storing the state whether to do the handling should be stopped.
     *
     * @var boolean
     *
     * @see stopHandling()
     *
     * @author Oleg Schildt
     */
    protected $stop_handling = false;
    
    
    /**
     * This is an auxiliary function for stopping the handling by subsequent handlers.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function stopHandling()
    {
        $this->stop_handling = true;
    }
    
    /**
     * This is an auxiliary function for sending the response in JSON
     * format.
     *
     * @param array $response_data
     * The array with response data.
     *
     * @param array $headers
     * The array of additional headers if necessary. The header
     * 'Content-type: application/json' is sent automatically
     * and need not be listed explicitly.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function sendJsonResponse(&$response_data, $headers = [])
    {
        header('Content-type: application/json');
        
        if (!empty($headers)) {
            if (is_array($headers)) {
                foreach ($headers as $header) {
                    header($header);
                }
            }
        }
        
        echo array_to_json($response_data);
    } // sendJsonResponse
    
    /**
     * This is an auxiliary function for sending the response in JSON
     * format by an exception.
     *
     * @param \Exception $ex
     * The thrown exception.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function exitWithException($ex)
    {
        $response_data = [];
        
        $response_data["result"] = "error";
        
        $response_data["errors"] = [
            ["error_code" => "system_error", "error_type" => "programming_error", "error_text" => $ex->getMessage()]
        ];
        
        $this->sendJsonResponse($response_data);
        
        exit;
    } // exitWithException
    
    /**
     * Defines the current API request name.
     *
     * @return string
     * Returns the current API request.
     *
     * @author Oleg Schildt
     */
    public function getApiRequest()
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return "";
        }
        
        $api_request = $_SERVER['REQUEST_URI'];
        
        if (!empty($_SERVER['QUERY_STRING'])) {
            $api_request = str_replace($_SERVER['QUERY_STRING'], "", $api_request);
        }
        
        $api_request = rtrim($api_request, "/?");
        
        $api_base = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
        
        $api_base = rtrim($api_base, "/");
        
        //echo $api_request . "<br>";
        //echo $api_base . "<br>";
        
        return trim(str_replace($api_base, "", $api_request), "/");
    } // getApiRequest
    
    /**
     * Registers a handler action for an API request call.
     *
     * @param string $api_request
     * The target API request.
     *
     * @param string $handler_class_name
     * The name of the class for handling this API request.
     *
     * Important! It should be a name of the class, neither the class instance
     * nor the class object. It is done to prevent situation that a wrong registration
     * of a handler breaks the handling of all requests.
     *
     * The class instantiating and class loading occurs only if this API request
     * comes.
     *
     * @return boolean
     * Returns true if the registration was successfull, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the request name is not specified.
     * - if the request already has a handler.
     *
     * @see registerPreProcessHandler()
     * @see registerPostProcessHandler()
     * @see registerDefaultHandler()
     *
     * @author Oleg Schildt
     */
    public function registerApiRequestHandler($api_request, $handler_class_name)
    {
        if (empty($api_request)) {
            throw new \Exception("The API request is undefined (empty)!");
        }
        
        if (!empty($this->handler_table[$api_request])) {
            throw new \Exception("The API request '$api_request' has already the handler '" . $this->handler_table[$api_request] . "'!");
        }
        
        $this->handler_table[$api_request] = $handler_class_name;
        
        return true;
    } // registerApiRequestHandler
    
    /**
     * Registers a handler action that will be executed before any other handlers.
     *
     * @param string $handler_class_name
     * The name of the class for handling this API request.
     *
     * Important! It should be a name of the class, neither the class instance
     * nor the class object. It is done to prevent situation that a wrong registration
     * of a handler breaks the handling of all requests.
     *
     * The class instantiating and class loading occurs only if this API request
     * comes.
     *
     * This is usefult for turning on the maintenance mode. The handler may check a
     * a setting value and return the maintenance json for all requests.
     *
     * @return boolean
     * Returns true if the registration was successfull, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the request already has a handler.
     *
     * @see registerApiRequestHandler()
     * @see registerPostProcessHandler()
     * @see registerDefaultHandler()
     *
     * @author Oleg Schildt
     */
    public function registerPreProcessHandler($handler_class_name)
    {
        return $this->registerApiRequestHandler("#pre_process#", $handler_class_name);
    } // registerPreProcessHandler
    
    /**
     * Registers a handler action that will be executed after the standard handling.
     *
     * @param string $handler_class_name
     * The name of the class for handling this API request.
     *
     * Important! It should be a name of the class, neither the class instance
     * nor the class object. It is done to prevent situation that a wrong registration
     * of a handler breaks the handling of all requests.
     *
     * The class instantiating and class loading occurs only if this API request
     * comes.
     *
     * @return boolean
     * Returns true if the registration was successfull, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the request already has a handler.
     *
     * @see registerApiRequestHandler()
     * @see registerPreProcessHandler()
     * @see registerDefaultHandler()
     *
     * @author Oleg Schildt
     */
    public function registerPostProcessHandler($handler_class_name)
    {
        return $this->registerApiRequestHandler("#post_process#", $handler_class_name);
    } // registerPostProcessHandler
    
    /**
     * Registers a handler action that will be executed if no handler is specified for this API request.
     *
     * @param string $handler_class_name
     * The name of the class for handling this API request.
     *
     * Important! It should be a name of the class, neither the class instance
     * nor the class object. It is done to prevent situation that a wrong registration
     * of a handler breaks the handling of all requests.
     *
     * The class instantiating and class loading occurs only if this API request
     * comes.
     *
     * @return boolean
     * Returns true if the registration was successfull, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the request already has a handler.
     *
     * @see registerApiRequestHandler()
     * @see registerPreProcessHandler()
     * @see registerPostProcessHandler()
     *
     * @author Oleg Schildt
     */
    public function registerDefaultHandler($handler_class_name)
    {
        return $this->registerApiRequestHandler("#default_handler#", $handler_class_name);
    } // registerDefaultHandler
    
    /**
     * Handles an API request call trying to call the handler registered
     * for this API request.
     *
     * @return void.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the creation of the handler fails.
     *
     * @uses IJsonApiRequestHandler
     *
     * @author Oleg Schildt
     */
    public function handleApiRequest()
    {
        $api_request = $this->getApiRequest();
        
        $response_data = [];
        $additional_headers = [];
        
        if (empty($api_request)) {
            $response_data["result"] = "error";
            
            $response_data["errors"] = [
                ["error_code" => "system_error", "error_text" => "The API request is undefined (empty)!"]
            ];
            
            $this->sendJsonResponse($response_data, $additional_headers);
            
            return;
        }
        
        $handlers_to_call = [];
        
        if (!empty($this->handler_table["#pre_process#"])) {
            $handlers_to_call["#pre_process#"] = $this->handler_table["#pre_process#"];
        }
        
        if (empty($this->handler_table[$api_request])) {
            if (empty($this->handler_table["#default_handler#"])) {
                $response_data["result"] = "error";
                
                $response_data["errors"] = [
                    [
                        "error_code" => "system_error",
                        "error_text" => sprintf("No handler is defined for the API request '%s'!", $api_request)
                    ]
                ];
                
                $this->sendJsonResponse($response_data, $additional_headers);
                
                return;
            } else {
                $handlers_to_call["#default_handler#"] = $this->handler_table["#default_handler#"];
            }
        } else {
            $handlers_to_call[$api_request] = $this->handler_table[$api_request];
        }
        
        if (!empty($this->handler_table["#post_process#"])) {
            $handlers_to_call["#post_process#"] = $this->handler_table["#post_process#"];
        }
        
        foreach ($handlers_to_call as $handler_key => $handler_class_name) {
            if (!class_exists($handler_class_name)) {
                $response_data["result"] = "error";
                
                $response_data["errors"] = [
                    [
                        "error_code" => "system_error",
                        "error_text" => sprintf("The handler class '%s', defined for the request '%s', does not exist!", $handler_class_name, $handler_key)
                    ]
                ];
                
                $this->sendJsonResponse($response_data, $additional_headers);
                
                return;
            }
            
            $handler_class = new \ReflectionClass($handler_class_name);
            
            if (!$handler_class->isSubclassOf("SmartFactory\Interfaces\IJsonApiRequestHandler")) {
                $response_data["result"] = "error";
                
                $response_data["errors"] = [
                    [
                        "error_code" => "system_error",
                        "error_text" => sprintf("The handler class '%s', defined for the request '%s', does not implement the interface '%s'!", $handler_class_name, $handler_key, "IJsonApiRequestHandler")
                    ]
                ];
                
                $this->sendJsonResponse($response_data, $additional_headers);
                
                return;
            }
            
            $handler = $handler_class->newInstance();
            
            $handler->handle($this, $api_request, $response_data, $additional_headers);
            
            if ($this->stop_handling) {
                break;
            }
        } // foreach
        
        $this->sendJsonResponse($response_data, $additional_headers);
    } // handleApiRequest
} // JsonApiRequestManager
