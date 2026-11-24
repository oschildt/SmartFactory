<?php
/**
 * This file contains the implementation of the auxiliary global functions.
 *
 * @package Utility Functions
 *
 * @author Oleg Schildt
 */

/**
 * Escapes regular expression metacharacters so a string is matched as literal text.
 *
 * @param string $str
 * The string to be escaped.
 *
 * @param string $delimiter
 * The regular expression delimiter to escape in addition to regex metacharacters.
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
} // preg_pattern_quote

/**
 * Escapes dollar signs and backslashes for a literal preg_replace() replacement.
 *
 * Backreferences such as $1, ${1} and \\1 are preserved as literal text rather than
 * expanded. This function escapes replacement strings, not regular expression patterns.
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
} // preg_replacement_quote
