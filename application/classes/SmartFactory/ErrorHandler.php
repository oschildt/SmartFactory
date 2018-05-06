<?php
/**
 * This file contains the implementation of the interface IErrorHandler 
 * in the class ErrorHandler for error handling.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory;

use SmartFactory\Interfaces\IErrorHandler;

/**
 * Class for error handling.
 *
 * @author Oleg Schildt 
 */
class ErrorHandler implements IErrorHandler
{
  /**
   * @var string
   * Internal variable for storing the last error.
   *
   * @see getLastError
   * @see setLastError
   *
   * @author Oleg Schildt 
   */
  protected static $last_error;

  /**
   * @var boolean
   * Internal variable for storing the state of tracing - active or not.
   *
   * @see traceActive
   * @see enableTrace
   * @see disableTrace
   *
   * @author Oleg Schildt 
   */
  protected static $trace_disabled = false;

  /**
   * Formats the standard PHP backtrace (debug_backtrace).
   *
   * @param array $btrace
   * The backtrace.
   *
   * @return string
   * Returns the formatted backtrace.
   *
   * @author Oleg Schildt 
   */
  protected function format_backtrace($btrace)
  {
    if(empty($btrace) || count($btrace) == 0) return "backtrace empty";

    $trace = "";
    
    foreach($btrace as $nr => &$btrace_entry)
    {
      if($nr == 0)
      {
        $trace .= $btrace_entry["args"][2] . "\r\n" .
                  "line: " . $btrace_entry["args"][3] . "\r\n" .
                  $btrace_entry["args"][1];

        $cstack = $this->extract_call_stack($btrace);          
        if(!empty($cstack)) $trace .= "\r\n\r\nCall stack:\r\n\r\n" . $cstack;

        continue;
      }
    }

    return $trace;
  } // format_backtrace

  /**
   * Extracts the call stack from the standard PHP backtrace (debug_backtrace).
   *
   * @param array $btrace
   * The backtrace.
   *
   * @return string
   * Returns the extracted call stack.
   *
   * @author Oleg Schildt 
   */
  protected function extract_call_stack($btrace)
  {
    if(empty($btrace) || !is_array($btrace)) return "";

    $trace = "";

    $indent = "";
    foreach($btrace as $btrace_entry)
    {
      
      
      if(!empty($btrace_entry["function"]) && 
         ($btrace_entry["function"] == "handle_error" || 
          strpos($btrace_entry["function"], "{closure}") !== false || 
          $btrace_entry["function"] == "handleError" ||
          $btrace_entry["function"] == "trigger_error" 
         )
        ) continue;

      if(empty($btrace_entry["file"])) continue;
      
      if(!empty($btrace_entry["function"])) $trace .= $indent . str_replace("SmartFactory\\", "", $btrace_entry["function"]) . "() ";
      
      $trace .= "[";
      
      $trace .= str_replace(APPLICATION_ROOT, "", $aroot = str_replace("\\", "/", $btrace_entry["file"]));

      $trace .= ", ";

      if(empty($btrace_entry["line"])) $trace .= "line number undefined";
      else                             $trace .= $btrace_entry["line"];
      
      $trace .= "]";
      
      $args = (isset($btrace_entry["args"])) ? $btrace_entry["args"] :[];
      $args_str = $this->make_arg_list($args);
      
      if(!empty($btrace_entry["function"]) && !empty($args_str)) 
        $trace .= "  " . str_replace("SmartFactory\\", "", $btrace_entry["function"]) . "(" . $args_str . ")";

      $trace .= "\r\n";
      
      $indent .= "  ";
    }

    return trim($trace);
  } // extract_call_stack

  /**
   * This is an auxiliary function for generation of the detailed string from the 
   * function arguments from the standard PHP backtrace (debug_backtrace).
   *
   * @param array $arr
   * The array of the function arguments.
   *
   * @return string
   * Returns the detailed string from the function arguments.
   *
   * @used_by make_arg_list()
   *
   * @author Oleg Schildt 
   */
  protected function deep_implode(&$arr)
  {
    $list = "";

    foreach($arr as $nm => &$val)
    {
      if(is_array($val))      $list .= $this->deep_implode($val) . ", ";
      elseif(is_object($val)) $list .= str_replace("SmartFactory\\", "", get_class($val)) . ", ";
      else                    $list .= $nm . "=" . $val . ", ";
    }

    return "[" . trim($list, ", ") . "]";
  } // deep_implode

  /**
   * Generates the detailed string from the function arguments from the 
   * standard PHP backtrace (debug_backtrace).
   *
   * @param array $args
   * The array of the function arguments.
   *
   * @return string
   * Returns the detailed string from the function arguments.
   *
   * @author Oleg Schildt 
   */
  protected function make_arg_list(&$args)
  {
    $list = "";

    foreach($args as $arg)
    {
      if(is_array($arg)) 
        $list .= $this->deep_implode($arg) . ", ";
      elseif(is_object($arg))
        $list .= str_replace("SmartFactory\\", "", get_class($arg)) . ", ";
      else               
        $list .= $arg . ", ";
    }

    return trim($list, ", ");
  } // make_arg_list

  /**
   * Traces an error to the standard trace file (logs/trace.log).
   *
   * @param string $message
   * The message to be traced.
   *
   * @return boolean
   * Returns true if the message has been successfully trace, otherwise false.
   *
   * @author Oleg Schildt 
   */
  protected function trace_message($message)
  {
    if(!$this->traceActive()) return true;
 
    $path = APPLICATION_ROOT . "logs/";
    $file = $path . "trace.log";

    if((!file_exists($file) && is_writable($path)) || is_writable($file))
    {
      return error_log(
      date("Y-m-d H:i:s") . "\r\n" .
      "----------------------------------------------------------\r\n" .
      $message . "\r\n" .
      "----------------------------------------------------------\r\n" .
      "\r\n\r\n", 3, $file);
    }
    
    return true;
  } // trace_message
  
  /**
   * This is the function for handling of the PHP errors. It is set a the 
   * error handler.
   *
   * @param int $errno
   * Error code.
   *
   * @param string $errstr
   * Error text.
   *
   * @param string $errfile
   * Source file where the error occured.
   *
   * @param int $errline
   * Line number where the error occured.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function handleError($errno, $errstr, $errfile, $errline)
  {
    $this->setLastError($errstr);

    $errortype = [
                   E_ERROR           => "Error",
                   E_WARNING         => "Warning",
                   E_PARSE           => "Parsing Error",
                   E_NOTICE          => "Notice",
                   E_CORE_ERROR      => "Core Error",
                   E_CORE_WARNING    => "Core Warning",
                   E_COMPILE_ERROR   => "Compile Error",
                   E_COMPILE_WARNING => "Compile Warning",
                   E_USER_ERROR      => "User Error",
                   E_USER_WARNING    => "User Warning",
                   E_USER_NOTICE     => "User Notice",
                   E_STRICT          => "Runtime Notice",   
                   E_DEPRECATED      => "Deprecated Notice" 
                 ];

    if(empty($errortype[$errno])) $etype = $errno;
    else                          $etype = $errortype[$errno];

    $this->trace_message($this->format_backtrace(debug_backtrace()));
    
    event()->fireEvent("php_error", ["etype" => $etype, "errstr" => $errstr, "errfile" => $errfile, "errline" => $errline]);
  } // handleError

  /**
   * Returns the last error.
   *
   * @return string
   * Returns the last error or an empty string if no error occured so far.
   *
   * @author Oleg Schildt 
   */
  public function getLastError()
  {
    if(empty(self::$last_error)) return "";
    
    return self::$last_error;
  } // getLastError

  /**
   * Stores the last error.
   *
   * @param string $error
   * The error text to be stored.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function setLastError($error)
  {
    self::$last_error = $error;    
  } // setLastError

  /**
   * Returns the state whether the trace is active or not.
   *
   * If the trace is active, any eror, warning or notice is traced to
   * the standard file.
   *
   * The trace is generally managed over the setting tracing_enabled.
   * But you can also temporarily disable tracing, e.g. to keep the
   * trace log clear when you make a check that can produce a trace
   * entry, but it is a controlled noticed and should not clutter the trace.
   *
   * @return boolean
   * Returns the state whether the trace is active or not.
   *
   * @see enableTrace
   * @see disableTrace
   *
   * @author Oleg Schildt 
   */
  public function traceActive()
  {
    return empty(self::$trace_disabled) &&
           config_settings()->getParameter("tracing_enabled", false, 1) != 0;
  } // traceActive

  /**
   * Enables the trace.
   *
   * If the trace is active, any eror, warning or notice is traced to
   * the standard file.
   *
   * @return void
   *
   * @see traceActive
   * @see disableTrace
   *
   * @author Oleg Schildt 
   */
  public function enableTrace()
  {
    self::$trace_disabled = false;
  } // enableTrace

  /**
   * Disables the trace.
   *
   * If the trace is active, any eror, warning or notice is traced to
   * the standard file.
   *
   * @return void
   *
   * @see traceActive
   * @see enableTrace
   *
   * @author Oleg Schildt 
   */
  public function disableTrace()
  {
    self::$trace_disabled = true;
  } // disableTrace
} // ErrorHandler
