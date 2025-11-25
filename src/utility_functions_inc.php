<?php
/**
 * This file contains utility functions.
 *
 * @author Oleg Schildt
 *
 * @package Utility Functions
 */

namespace SmartFactory;

/**
 * Checks whether the array $array is associative.
 *
 * @param array &$array
 * Array to be checked.
 *
 * @return bool
 * Returns true if the array is associative, otherwise false.
 *
 * @author Oleg Schildt
 */
function is_associative(array &$array): bool
{
    if (!is_array($array) || empty($array)) {
        return false;
    }
    
    $keys = array_keys($array);
    
    return array_keys($keys) !== $keys;
} // is_associative

/**
 * Checks whether the session is cmd client or web.
 *
 * @return bool
 * Returns true if the session is web, otherwise false.
 *
 * @author Oleg Schildt
 */
function is_web(): bool
{
    return http_response_code() !== false;
} // is_web

/**
 * Defines the common prefix of two strings.
 *
 * @param string $s1
 * First string to be checked.
 *
 * @param string $s2
 * Second string to be checked.
 *
 * @param int $max
 * The maximal number of charactes to check.
 *
 * @return string
 * Returns the common prefix of the passed strings.
 *
 * @author Oleg Schildt
 */
function common_prefix(string $s1, string $s2, $max = 1000): string
{
    $prefix = "";
    
    $l1 = strlen($s1);
    $l2 = strlen($s2);
    
    for ($i = 0; $i < $max; $i++) {
        if ($i >= $l1 || $i >= $l2) {
            break;
        }
        
        if ($s1[$i] != $s2[$i]) {
            break;
        }
        
        $prefix .= $s1[$i];
    }
    
    return $prefix;
} // common_prefix

/**
 * Converts the JSON string to an array.
 *
 * It is a wrapper over the system function json_decode. It
 * is introduced to give the ability to overwrite the system
 * function if necessary.
 *
 * @param string &$json
 * Input JSON string.
 *
 * @param array &$array
 * target array.
 *
 * @throws \Exception
 * It might throw the exception if the JSON cannot be parsed.
 *
 * @return void
 *
 * @author Oleg Schildt
 */
function json_to_array(string &$json, array &$array): void
{
    $result = json_decode($json, true);
    if ($result === null) {
        throw new \Exception(json_last_error_msg());
    }
    
    if (empty($array)) {
        $array = [];
    }
    
    $array = array_merge($array, $result);
} // json_to_array

/**
 * Converts the array to JSON string.
 *
 * It is a wrapper over the system function json_encode. It
 * is introduced to give the ability to overwrite the system
 * function if necessary.
 *
 * @param array &$array
 * Array to be converted.
 *
 * @return string
 * Returns the JSON string of the array.
 *
 * @author Oleg Schildt
 */
function array_to_json(array &$array): string
{
    return json_encode($array, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
} // array_to_json

/**
 * Converts an array to XML DOM structure.
 *
 * This function can convert an array of any dimensions
 * to a DOM structure. It might be used for loading and saving
 * settings to a config file.
 *
 * @param \DOMNode &$node
 * The parent node of the DOM structure.
 *
 * @param array &$array
 * The array to be converted to the DOM structure.
 *
 * @return void
 *
 * @see \SmartFactory\dom_to_array()
 *
 * @author Oleg Schildt
 */
function array_to_dom(&$node, &$array): void
{
    $xmldoc = $node->ownerDocument;
    
    $node->setAttribute("array", 1);
    
    foreach ($array as $key => &$val) {
        $child = $xmldoc->createElement("item");
        $child->setAttribute("name", $key);
        
        if (is_array($val)) {
            array_to_dom($child, $val);
        } else {
            $txtnode = $xmldoc->createTextNode($val);
            $child->appendChild($txtnode);
        }
        
        $node->appendChild($child);
    }
} // array_to_dom

/**
 * Converts a XML DOM structure to an array.
 *
 * This function can convert any DOM structure to an array.
 * It might be used for loading and saving settings to a
 * config file.
 *
 * @param \DOMNode &$node
 * The parent node of the DOM structure to be converted to the array.
 *
 * @param array &$array
 * The tagrget array to be filled from the DOM structure.
 *
 * @return void
 *
 * @see \SmartFactory\array_to_dom()
 *
 * @author Oleg Schildt
 */
function dom_to_array(\DOMNode &$node, array &$array): void
{
    if (!$node->hasChildNodes()) {
        return;
    }
    
    foreach ($node->childNodes as $child) {
        if ($child->nodeType == XML_TEXT_NODE) {
            continue;
        }
        
        $name = $child->nodeName;
        if ($child->nodeName == "item") {
            $name = $child->getAttribute("name");
        }
        
        // has a single text node
        
        if ($child->hasChildNodes() && $child->childNodes->length == 1 && $child->childNodes->item(0)->nodeType == XML_TEXT_NODE) {
            $array[$name] = $child->childNodes->item(0)->nodeValue;
            continue;
        }
        
        // has a collection
        
        if ($child->hasChildNodes() || $child->getAttribute("array") == "1") {
            if (!isset($array[$name])) {
                $array[$name] = array();
            }
            dom_to_array($child, $array[$name]);
            continue;
        }
        
        $array[$name] = $child->nodeValue;
    }
} // dom_to_array

/**
 * Checks whether the text is an empty string.
 *
 * @param ?string $text
 * The text to be checked.
 *
 * @return bool
 * Returns true if the string is an empty string.
 *
 * @author Oleg Schildt
 */
function string_empty(?string $text): bool
{
    if ($text === null) return true;

    if ($text === "") return true;
    
    return false;
} // string_empty

/**
 * Escapes the HTML special characters in the text.
 *
 * @param ?string $text
 * The text to be escaped.
 *
 * @return ?string
 * Returns the text with escaped HTML special characters.
 *
 * @see \SmartFactory\escape_html_array()
 * @see \SmartFactory\escape_js()
 *
 * @author Oleg Schildt
 */
function escape_html(?string $text): ?string
{
    if (empty($text)) {
        return $text;
    }

    return htmlspecialchars($text, ENT_QUOTES);
} // escape_html

/**
 * Escapes recursively the HTML special characters in the values
 * of the array.
 *
 * @param array &$array
 * The array to be escaped.
 *
 * @return void
 *
 * @see \SmartFactory\escape_html()
 * @see \SmartFactory\escape_js()
 *
 * @author Oleg Schildt
 */

function escape_html_array(array &$array): void
{
    foreach ($array as &$val) {
        if (is_array($val)) {
            escape_html_array($val);
        } else {
            $val = htmlspecialchars($val, ENT_QUOTES);
        }
    }
} // escape_html_array

/**
 * Escapes the JavaScript special characters in the text.
 *
 * @param string $text
 * The text to be escaped.
 *
 * @return string
 * Returns the text with escaped JavaScript special characters.
 *
 * @see \SmartFactory\escape_html()
 *
 * @author Oleg Schildt
 */
function escape_js(string $text): string
{
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace("\n", "\\n", $text);
    $text = str_replace("\r", "\\r", $text);
    
    $text = str_replace("/", "\\/", $text);
    $text = str_replace("'", "\\'", $text);
    $text = str_replace("\"", "\\\"", $text);
    
    return $text;
} // escape_js

/**
 * Gets the cookie value by name.
 *
 * @param string $name
 * Name of the cookie.
 *
 * @return string
 * Returns the cookie value of empty string if the cookie is not set.
 *
 * @author Oleg Schildt
 */
function get_cookie(string $name): string
{
    return empty($_COOKIE[$name]) ? "" : $_COOKIE[$name];
} // get_cookie

/**
 * Gets the header by its name.
 *
 * @param string $name
 * Name of the header.
 *
 * @return string
 * Returns the header value of empty string if the header is not set.
 *
 * @author Oleg Schildt
 */
function get_header(string $name): string {
    static $headers;

    if(empty($headers)) {
        $tmp = getallheaders();
        foreach($tmp as $header => $value) {
            $headers[strtolower($header)] = $value;
        }
    }

    if(empty($headers[strtolower($name)])) return "";

    return $headers[strtolower($name)];
}

/**
 * Sets the cookie name=value.
 *
 * @param string $name
 * Name of the cookie.
 *
 * @param string $value
 * Value of the cookie.
 *
 * @param int $expires
 * The time the cookie expires.
 *
 * @param array $params
 * Any additional parameters like expires, path, domain, secure, httponly or samesite.
 *
 * @return bool
 * Returns true if the cookie has been set successfully, otherwise false.
 *
 * @author Oleg Schildt
 */
function set_cookie(string $name, string $value = "", int $expires = 0, array $params = []): bool
{
    if (version_compare(phpversion(), "7.3") >= 0) {
        $params["expires"] = $expires;
        return setcookie($name, $value, $params);
    }
    
    $path = "";
    if (!empty($params["path"])) {
        $path = $params["path"];
    }
    if (!empty($params["samesite"])) {
        $path .= "/; samesite=" . $params["samesite"];
    }
    return setcookie($name, $value, $expires, $path);
} // set_cookie

/**
 * Escapes the special characters in the text used for the regular
 * expression pattern.
 *
 * @param string $pattern
 * The pattern to be escaped.
 *
 * @return string
 * Returns the text with escaped special characters.
 *
 * @see \SmartFactory\preg_r_escape()
 *
 * @author Oleg Schildt
 */
function preg_p_escape(string $pattern): string
{
    return preg_replace("/[\\\\\\[\\]\\+\\?\\-\\^\\$\\(\\)\\/\\.\\|\\{\\}\\|]/", "\\\\$0", $pattern);
} // preg_p_escape

/**
 * Escapes the special characters in the text used for the regular
 * expression replacement.
 *
 * @param string $pattern
 * The pattern to be escaped.
 *
 * @return string
 * Returns the text with escaped special characters.
 *
 * @see \SmartFactory\preg_p_escape()
 *
 * @author Oleg Schildt
 */
function preg_r_escape(string $pattern): string
{
    return preg_replace("/[\\\\\\$]/", "\\\\$0", $pattern);
} // preg_r_escape

/**
 * Converts the string representing the date/time in the
 * specified format to the timestap.
 *
 * @param string $time_string
 * The date/time string.
 *
 * @param string $format
 * The date format of the date/time string in the PHP systax
 * for the date formats, e.g. "Y.m.d H:i:s".
 *
 * @return int|string|null
 * If the date/time string could be converted, the timestamp is
 * returned, otherwise the string "error" is returned.
 *
 * @author Oleg Schildt
 */
function timestamp(string $time_string, string $format): int|string|null
{
    if (empty($time_string)) {
        return null;
    }
    
    $err_status = "error";
    
    $pattern = preg_replace(array("/Y/", "/m/", "/d/", "/H/", "/i/", "/s/"), array("([0-9]{4})", "([0-9]{1,2})", "([0-9]{1,2})", "([0-9]{1,2})", "([0-9]{1,2})", "([0-9]{1,2})"), preg_quote($format));
    
    $units = array();
    
    if (!preg_match("/" . $pattern . "/", $time_string, $units)) {
        return $err_status;
    }
    
    array_shift($units);
    
    //return implode("|", $units);
    
    $order = preg_replace("/[^YmdHis]/", "", $format);
    
    $date_part = "";
    $pos_Y = strpos($order, "Y");
    $pos_m = strpos($order, "m");
    $pos_d = strpos($order, "d");
    if (!($pos_Y === false || $pos_m === false || $pos_d === false)) {
        if (!checkdate($units[$pos_m], $units[$pos_d], $units[$pos_Y])) {
            return $err_status;
        }
        
        $date_part = $units[$pos_Y] . "-" . $units[$pos_m] . "-" . $units[$pos_d];
    }
    
    $time_part = "";
    $pos_H = strpos($order, "H");
    $pos_i = strpos($order, "i");
    $pos_s = strpos($order, "s");
    if (!($pos_H === false || $pos_i === false)) {
        if (!is_numeric($units[$pos_H]) || $units[$pos_H] < 0 || $units[$pos_H] > 23) {
            return $err_status;
        }
        if (!is_numeric($units[$pos_i]) || $units[$pos_i] < 0 || $units[$pos_i] > 59) {
            return $err_status;
        }
        
        $time_part = $units[$pos_H] . ":" . $units[$pos_i];
        
        if (!($pos_s === false)) {
            if (!is_numeric($units[$pos_s]) || $units[$pos_s] < 0 || $units[$pos_s] > 59) {
                return $err_status;
            }
            $time_part .= ":" . $units[$pos_s];
        }
    }
    
    return strtotime(trim($date_part . " " . $time_part));
} // timestamp

/**
 * Formats the number due to the specified settings.
 * It is a wrapper over the system function number_format.
 *
 * @param string $number
 * The number to be formatted.
 *
 * @param int $decimals
 * The number of digits after the dot.
 *
 * @param string $dec_point
 * The decimal separator.
 *
 * @param string $thousand_sep
 * The thousand separator.
 *
 * @return ?string
 * If the number is a vialid number, its formatted value is returned. Otherwise,
 * the empty value is returned. It is usefil if we need to distiguish the empty value and 0.
 *
 * @author Oleg Schildt
 */
function format_number(string $number, int $decimals = 0, string $dec_point = ".", string $thousand_sep = ","): ?string
{
    if ($number === null || $number === "") {
        return $number;
    }
    
    return number_format($number, $decimals, $dec_point, $thousand_sep);
} // format_number

/**
 * Converts the string to number due to the specified settings.
 *
 * @param string $str
 * The string to be converted.
 *
 * @param string $dec_point
 * The decimal separator.
 *
 * @param string $thousand_sep
 * The thousand separator.
 *
 * @throws \Exception
 * It might throw the exception if the string is not a number
 *
 * @return float|string|null
 * If the string is a vialid number, its numeric value is returned.
 *
 * @author Oleg Schildt
 */
function string_to_number(string $str, string $dec_point = ".", string $thousand_sep = ","): float|string|null
{
    if ($str === "" || $str === null) {
        return $str;
    }
    
    $str = str_replace($thousand_sep, "", $str);
    $str = str_replace($dec_point, ".", $str);
    $number = floatval($str);
    
    return $number;
}

/**
 * Encrypts the text with the AES 256 using a password key.
 *
 * @param string $data
 * The data to be encrypted.
 *
 * @param string $password_key
 * The password key used for the encryption.
 *
 * @return string
 * Returns the encrypted text.
 *
 * @see \SmartFactory\aes_256_decrypt()
 *
 * @author Oleg Schildt
 */
function aes_256_encrypt(string $data, string $password_key): string
{
    // Set a random salt
    $salt = openssl_random_pseudo_bytes(16);
    
    $salted = '';
    $dx = '';
    // Salt the key(32) and iv(16) = 48
    while (strlen($salted) < 48) {
        $dx = hash('sha256', $dx . $password_key . $salt, true);
        $salted .= $dx;
    }
    
    $key = substr($salted, 0, 32);
    $iv = substr($salted, 32, 16);
    
    $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $key, true, $iv);
    return base64_encode($salt . $encrypted_data);
} // aes_256_encrypt

/**
 * Decrypts the text previously encrypted with the AES 256 using a password key.
 *
 * @param string $edata
 * The data to be decrypted.
 *
 * @param string $password_key
 * The password key used for the encryption.
 *
 * @return string
 * Returns the decrypted text.
 *
 * @see \SmartFactory\aes_256_decrypt()
 *
 * @author Oleg Schildt
 */
function aes_256_decrypt(string $edata, string $password_key): string
{
    $data = base64_decode($edata);
    $salt = substr($data, 0, 16);
    $ct = substr($data, 16);
    
    $rounds = 3; // depends on key length
    $data00 = $password_key . $salt;
    $hash = array();
    $hash[0] = hash('sha256', $data00, true);
    $result = $hash[0];
    
    for ($i = 1; $i < $rounds; $i++) {
        $hash[$i] = hash('sha256', $hash[$i - 1] . $data00, true);
        $result .= $hash[$i];
    }
    
    $key = substr($result, 0, 32);
    $iv = substr($result, 32, 16);
    
    return openssl_decrypt($ct, 'AES-256-CBC', $key, true, $iv);
} // aes_256_decrypt
