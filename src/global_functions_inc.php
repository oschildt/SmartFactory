<?php
/**
 * This file contains the implementation of the auxiliary global fuctions.
 *
 * @package Utility Functions
 *
 * @author Oleg Schildt
 */

/**
 * Function for escaping the special symbols for putting into the regexp pattern 
 * as a simple text in the pattern.
 *
 * This function escapes all special symbols so that they are treated as a simple text.
 *
 * @param string $str
 * The string to be escaped.
 *
 * @param string $delimiter
 * The string used in the pattern.
 *
 * @return string
 * The escaped string.
 *
 * @see \preg_replacement_quote()
 *
 * @author Oleg Schildt
 */
function preg_pattern_quote(string $str, string $delimiter = "/"): string
{
    return preg_quote($str, $delimiter);
}

/**
 * Function for escaping the special symbols for putting into the regexp pattern 
 * as a simple text.
 *
 * This function escapes all special symbols so that they are treated as a 
 * simple text in the replacement.
 *
 * @param string $str
 * The string to be escaped.
 *
 * @return string
 * The escaped string.
 *
 * @see \preg_pattern_quote()
 *
 * @author Oleg Schildt
 */
function preg_replacement_quote(string $str): string
{
    return strtr($str, ["\\" => "\\\\", "$" => "\\$"]);
}
?>