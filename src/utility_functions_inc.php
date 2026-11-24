<?php
/**
 * This file contains utility functions.
 *
 * @package Utility Functions
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Checks whether an array has keys other than consecutive integers starting at zero.
 *
 * @param array<array-key, mixed> $array
 * The array to check.
 *
 * @return bool
 * Returns false for lists, including an empty array, and true for all other arrays.
 *
 * @author Oleg Schildt
 */
function is_associative(array $array): bool
{
    return !array_is_list($array);
} // is_associative

/**
 * Checks whether PHP is running through a web SAPI rather than CLI or phpdbg.
 *
 * @return bool
 * Returns false for CLI and phpdbg, otherwise true, including the built-in web server.
 *
 * @author Oleg Schildt
 */
function is_web(): bool
{
    return PHP_SAPI !== "cli" && PHP_SAPI !== "phpdbg";
} // is_web

/**
 * Finds the common prefix of two strings without splitting valid UTF-8 characters.
 *
 * Valid UTF-8 inputs are compared by Unicode code points. If either input is not
 * valid UTF-8, both inputs are compared as byte strings instead.
 *
 * @param string $s1
 * The first UTF-8 or binary string to compare.
 *
 * @param string $s2
 * The second UTF-8 or binary string to compare.
 *
 * @param int $max
 * The maximum number of code points, or bytes for binary inputs. Nonpositive values return an empty string.
 *
 * @return string
 * Returns the common prefix, limited to the requested number of characters or bytes.
 *
 * @author Oleg Schildt
 */
function common_prefix(string $s1, string $s2, int $max = 1000): string
{
    if ($max <= 0) {
        return "";
    }

    if (mb_check_encoding($s1, "UTF-8") && mb_check_encoding($s2, "UTF-8")) {
        $first = mb_str_split(mb_substr($s1, 0, $max, "UTF-8"), 1, "UTF-8");
        $second = mb_str_split(mb_substr($s2, 0, $max, "UTF-8"), 1, "UTF-8");
    } else {
        $first = str_split(substr($s1, 0, $max));
        $second = str_split(substr($s2, 0, $max));
    }

    $limit = min(count($first), count($second));
    $length = 0;

    while ($length < $limit && $first[$length] === $second[$length]) {
        $length++;
    }

    return implode("", array_slice($first, 0, $length));
} // common_prefix

/**
 * Decodes a JSON object or list and merges it into the target array.
 *
 * JSON objects update matching keys without renumbering integer keys. JSON lists
 * append their items using array_merge() semantics. Merging is shallow. Integers
 * outside PHP's integer range are decoded as strings to preserve their precision.
 *
 * @param string $json
 * The UTF-8 JSON object or list to decode.
 *
 * @param array<array-key, mixed> &$array
 * The target array, which remains unchanged if decoding or root validation fails.
 *
 * @return void
 * Merges the decoded values into the target array.
 *
 * @throws \JsonException
 * If the JSON is malformed, is not valid UTF-8, or exceeds the decoding depth limit.
 *
 * @throws \UnexpectedValueException
 * If the JSON root is a scalar value or null instead of an object or list.
 *
 * @author Oleg Schildt
 */
function json_to_array(string $json, array &$array): void
{
    $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

    if (!is_array($result)) {
        throw new \UnexpectedValueException("JSON must contain an object or array at the root.");
    }

    // The JSON syntax distinguishes an object with numeric keys from a list.
    $array = str_starts_with(ltrim($json), "[")
        ? array_merge($array, $result)
        : array_replace($array, $result);
} // json_to_array

/**
 * Encodes an array as readable UTF-8 JSON while preserving numeric string values.
 *
 * @param array<array-key, mixed> $array
 * The array of JSON-serializable values to encode.
 *
 * @return string
 * Returns the formatted JSON without coercing numeric strings to numbers.
 *
 * @throws \JsonException
 * If a value contains invalid UTF-8, recursion, nonfinite numbers, unsupported types,
 * or nesting deeper than the encoding limit.
 *
 * @throws \Throwable
 * If a contained JsonSerializable object throws while producing its JSON value.
 *
 * @author Oleg Schildt
 */
function array_to_json(array $array): string
{
    return json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} // array_to_json

/**
 * Appends a nested array to an XML element using named item elements.
 *
 * Nested arrays are marked with array="1". Scalar and Stringable values become
 * text, and null becomes an empty string. Scalar types are not preserved by XML.
 * This format supports loading and saving settings through dom_to_array().
 *
 * @param \DOMElement $node
 * The parent element, which must belong to a DOMDocument.
 *
 * @param array<array-key, mixed> &$array
 * The acyclic nested array of scalar, null or Stringable values. It is not modified.
 *
 * @return void
 * Appends the generated child elements to the parent element.
 *
 * @throws \LogicException
 * If the parent element does not belong to a DOMDocument.
 *
 * @throws \UnexpectedValueException
 * If an entry is a resource or an object that does not implement Stringable.
 *
 * @throws \DOMException
 * If creating or attaching an XML node or attribute fails.
 *
 * @throws \Throwable
 * If converting a Stringable object to text throws an exception or error.
 *
 * @see \SmartFactory\dom_to_array()
 *
 * @author Oleg Schildt
 */
function array_to_dom(\DOMElement $node, array &$array): void
{
    /**
     * A directly constructed DOMElement may not have an owner document.
     *
     * @var \DOMDocument|null $xmldoc
     *
     * @author Oleg Schildt
     */
    $xmldoc = $node->ownerDocument;

    if ($xmldoc === null) {
        throw new \LogicException("The parent element must belong to a DOMDocument.");
    }

    $node->setAttribute("array", "1");

    foreach ($array as $key => $val) {
        $child = $xmldoc->createElement("item");

        $child->setAttribute("name", (string) $key);

        if (is_array($val)) {
            array_to_dom($child, $val);
        } else {
            if ($val !== null && !is_scalar($val) && !$val instanceof \Stringable) {
                throw new \UnexpectedValueException("XML array entries must be arrays, scalars, null or Stringable objects.");
            }

            $txtnode = $xmldoc->createTextNode((string) $val);

            $child->appendChild($txtnode);
        }

        $node->appendChild($child);
    }
} // array_to_dom

/**
 * Converts XML child elements to a nested array.
 *
 * Elements named "item" use their "name" attribute as the array key; other elements
 * use their node name. Text and CDATA become string values when no child elements
 * exist. An array="1" marker takes precedence over formatting whitespace and
 * preserves empty arrays. Comments and processing instructions are ignored.
 * Collections may contain formatting whitespace but not mixed text content.
 *
 * @param \DOMElement $node
 * The parent element whose child elements are converted.
 *
 * @param array<array-key, mixed> &$array
 * The target array. Matching scalar entries are replaced and nested arrays are extended.
 *
 * @return void
 * Updates the target array with the converted child elements.
 *
 * @throws \TypeError
 * If an XML collection matches an existing target entry that is neither an array nor null.
 *
 * @throws \UnexpectedValueException
 * If an XML collection also contains non-whitespace text or CDATA that cannot be represented.
 *
 * @see \SmartFactory\array_to_dom()
 *
 * @author Oleg Schildt
 */
function dom_to_array(\DOMElement $node, array &$array): void
{
    foreach ($node->childNodes as $child) {
        // Only elements expose attributes and satisfy dom_to_array()'s parameter type.
        if (!($child instanceof \DOMElement)) {
            continue;
        }

        $name = $child->nodeName;

        if ($child->nodeName === "item") {
            $name = $child->getAttribute("name");
        }

        $is_collection = $child->getAttribute("array") === "1";

        foreach ($child->childNodes as $grandchild) {
            if ($grandchild instanceof \DOMElement) {
                $is_collection = true;

                break;
            }
        }

        if (!$is_collection) {
            $array[$name] = $child->textContent;

            continue;
        }

        // Refuse mixed content instead of silently discarding scalar data.
        foreach ($child->childNodes as $grandchild) {
            if ($grandchild instanceof \DOMText && trim($grandchild->textContent) !== "") {
                throw new \UnexpectedValueException("XML collections cannot contain non-whitespace text or CDATA.");
            }
        }

        if (!isset($array[$name])) {
            $array[$name] = [];
        }

        dom_to_array($child, $array[$name]);
    }
} // dom_to_array

/**
 * Checks whether text is null or an empty string, preserving "0" as a nonempty value.
 *
 * @param ?string $text
 * The text to be checked.
 *
 * @return bool
 * Returns true for null or an empty string, otherwise false.
 *
 * @author Oleg Schildt
 */
function string_empty(?string $text): bool
{
    return $text === null || $text === "";
} // string_empty

/**
 * Escapes HTML special characters in UTF-8 text while preserving null.
 *
 * @param ?string $text
 * The UTF-8 text to escape, or null for an absent value.
 *
 * @return ?string
 * Returns escaped text, replacing invalid UTF-8 sequences with U+FFFD, or null.
 *
 * @see \SmartFactory\escape_html_array()
 * @see \SmartFactory\escape_js()
 *
 * @author Oleg Schildt
 */
function escape_html(?string $text): ?string
{
    if ($text === null) {
        return null;
    }

    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
} // escape_html

/**
 * Recursively escapes UTF-8 array values for HTML while preserving null entries.
 *
 * @param array<array-key, mixed> &$array
 * The acyclic nested array of strings, scalars, null or Stringable values.
 * Non-null scalar and Stringable values are converted to escaped strings.
 *
 * @return void
 * Updates values in place without changing array keys.
 *
 * @throws \InvalidArgumentException
 * If an entry is a resource or an object that does not implement Stringable.
 *
 * @throws \Throwable
 * If converting a Stringable object to text throws an exception or error.
 *
 * @see \SmartFactory\escape_html()
 * @see \SmartFactory\escape_js()
 *
 * @author Oleg Schildt
 */
function escape_html_array(array &$array): void
{
    foreach ($array as $key => $val) {
        if (is_array($val)) {
            escape_html_array($array[$key]);
        } else {
            if ($val !== null && !is_scalar($val) && !$val instanceof \Stringable) {
                throw new \InvalidArgumentException("HTML array entries must be arrays, scalars, null or Stringable objects.");
            }

            $array[$key] = escape_html($val === null ? null : (string) $val);
        }
    }
} // escape_html_array

/**
 * Escapes text inside a single- or double-quoted JavaScript string literal.
 *
 * The enclosing quotes are not added. HTML-sensitive characters are escaped for
 * inline script content. Template literals and HTML attributes require their own
 * context-specific encoding; this function does not provide that encoding.
 *
 * @param string $text
 * The text to be escaped.
 *
 * @return string
 * Returns text with JavaScript delimiters, control characters and HTML delimiters escaped.
 *
 * @see \SmartFactory\escape_html()
 *
 * @author Oleg Schildt
 */
function escape_js(string $text): string
{
    return strtr($text, [
        "\\" => "\\\\",
        "\n" => "\\n",
        "\r" => "\\r",
        "\t" => "\\t",
        "\x00" => "\\u0000",
        "\x08" => "\\b",
        "\x0C" => "\\f",
        "\xE2\x80\xA8" => "\\u2028",
        "\xE2\x80\xA9" => "\\u2029",
        "/" => "\\/",
        "'" => "\\'",
        "\"" => "\\\"",
        "<" => "\\x3C",
        ">" => "\\x3E",
        "&" => "\\x26"
    ]);
} // escape_js

/**
 * Gets a string cookie value without treating the string "0" as missing.
 *
 * @param string $name
 * Name of the cookie.
 *
 * @return string
 * Returns the string value, or an empty string if the cookie is absent or is not a string.
 *
 * @author Oleg Schildt
 */
function get_cookie(string $name): string
{
    $value = $_COOKIE[$name] ?? null;

    return is_string($value) ? $value : "";
} // get_cookie

/**
 * Gets a request header by its case-insensitive name in web and CLI environments.
 *
 * Native request headers take precedence over CGI-style server variables. Values
 * are read on each call so a persistent worker cannot reuse a previous request's headers.
 *
 * @param string $name
 * Name of the header.
 *
 * @return string
 * Returns the string value, including "0", or an empty string if no scalar header is available.
 *
 * @author Oleg Schildt
 */
function get_header(string $name): string
{
    $headers = function_exists("getallheaders") ? \getallheaders() : [];

    if (is_array($headers)) {
        foreach ($headers as $header => $value) {
            if (strcasecmp($header, $name) === 0 && is_string($value)) {
                return $value;
            }
        }
    }

    $server_name = strtoupper(str_replace("-", "_", $name));
    $server_key = in_array($server_name, ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5"], true)
        ? $server_name
        : "HTTP_" . $server_name;
    $value = $_SERVER[$server_key] ?? $_SERVER["HTTP_" . $server_name] ?? null;

    if ($value === null && $server_name === "AUTHORIZATION") {
        $value = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? null;
    }

    return is_string($value) ? $value : "";
} // get_header

/**
 * Queues a cookie response header using the native cookie options.
 *
 * @param string $name
 * Name of the cookie.
 *
 * @param string $value
 * Value of the cookie.
 *
 * @param int $expires
 * The expiration Unix timestamp; zero creates a session cookie. Overrides params["expires"].
 *
 * @param array{expires?: int, path?: string, domain?: string, secure?: bool, httponly?: bool, samesite?: string} $params
 * Additional native cookie options, such as path, domain, secure, httponly and samesite.
 *
 * @return bool
 * Returns true if the response header was queued, or false if headers were already sent.
 * This does not confirm that the client accepted the cookie.
 *
 * @throws \ValueError
 * If the cookie name, path or domain contains forbidden characters, an option is
 * unknown, or the expiration timestamp exceeds the native supported range.
 *
 * @throws \TypeError
 * If a cookie option has a type that the native setcookie() function rejects.
 *
 * @author Oleg Schildt
 */
function set_cookie(string $name, string $value = "", int $expires = 0, array $params = []): bool
{
    $params["expires"] = $expires;

    return setcookie($name, $value, $params);
} // set_cookie

/**
 * Escapes literal text for use in a regular expression pattern.
 *
 * @param string $pattern
 * The literal text to escape, including regex metacharacters.
 *
 * @param string $delimiter
 * The regular expression delimiter to escape in addition to regex metacharacters.
 *
 * @return string
 * Returns text with regular expression metacharacters and the delimiter escaped.
 *
 * @see \SmartFactory\preg_r_escape()
 *
 * @author Oleg Schildt
 */
function preg_p_escape(string $pattern, string $delimiter = "/"): string
{
    return preg_quote($pattern, $delimiter);
} // preg_p_escape

/**
 * Escapes dollar signs and backslashes for a literal preg_replace() replacement.
 *
 * @param string $pattern
 * The literal replacement text, which may contain backreference syntax.
 *
 * @return string
 * Returns text whose dollar signs and backslashes are interpreted literally in a replacement.
 *
 * @see \SmartFactory\preg_p_escape()
 *
 * @author Oleg Schildt
 */
function preg_r_escape(string $pattern): string
{
    return strtr($pattern, ["\\" => "\\\\", "$" => "\\$"]);
} // preg_r_escape

/**
 * Parses a complete date/time string using PHP's DateTime format syntax.
 *
 * Missing date/time fields are initialized to the Unix epoch's calendar fields
 * in the default timezone, rather than inherited from the current clock. Format
 * reset markers and timezone fields retain their native DateTime semantics.
 *
 * @param string $time_string
 * The date/time string, or an empty string for an absent value.
 *
 * @param string $format
 * The DateTime::createFromFormat() format, for example "Y.m.d H:i:s".
 *
 * @return int|string|null
 * Returns the timestamp on success, null for an empty input, or "error" if parsing
 * fails, emits a warning, or encounters a null byte. Trailing data is rejected.
 *
 * @author Oleg Schildt
 */
function timestamp(string $time_string, string $format): int|string|null
{
    if ($time_string === "") {
        return null;
    }

    if ($format === "") {
        return "error";
    }

    try {
        $date = \DateTimeImmutable::createFromFormat("!" . $format, $time_string);
        $errors = \DateTimeImmutable::getLastErrors();
    } catch (\ValueError) {
        return "error";
    }

    if ($date === false || ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0))) {
        return "error";
    }

    return $date->getTimestamp();
} // timestamp

/**
 * Formats a numeric string using the specified separators and decimal precision.
 *
 * Null and empty strings are preserved to distinguish an absent value from zero.
 *
 * @param string|null $number
 * The numeric string to format, or null or an empty string for an absent value.
 *
 * @param int $decimals
 * The number of digits after the dot.
 *
 * @param string $dec_point
 * The decimal separator.
 *
 * @param string $thousand_sep
 * The thousands separator.
 *
 * @return string|null
 * Returns the formatted number, or the original null or empty string.
 *
 * @throws \TypeError
 * If a nonempty number string is not numeric and number_format() rejects it.
 *
 * @author Oleg Schildt
 */
function format_number(?string $number, int $decimals = 0, string $dec_point = ".", string $thousand_sep = ","): ?string
{
    if ($number === null || $number === "") {
        return $number;
    }

    return number_format($number, $decimals, $dec_point, $thousand_sep);
} // format_number

/**
 * Converts a numeric string to a float using the specified separators.
 *
 * Removes thousands separators, normalizes the decimal separator, and delegates
 * parsing to floatval(). Numeric prefixes are accepted and nonnumeric strings
 * become 0.0. Null and empty strings are preserved.
 *
 * @param string|null $str
 * The string to convert, or null or an empty string for an absent value.
 *
 * @param string $dec_point
 * The decimal separator.
 *
 * @param string $thousand_sep
 * The thousands separator to remove before parsing.
 *
 * @return float|string|null
 * Returns the parsed float, or the original null or empty string.
 *
 * @author Oleg Schildt
 */
function string_to_number(?string $str, string $dec_point = ".", string $thousand_sep = ","): float|string|null
{
    if ($str === "" || $str === null) {
        return $str;
    }

    $str = str_replace($thousand_sep, "", $str);
    $str = str_replace($dec_point, ".", $str);
    $number = floatval($str);

    return $number;
} // string_to_number

/**
 * Encrypts binary or UTF-8 data using a versioned, authenticated AES-256-GCM format.
 *
 * Version 1 derives a 32-byte key with PBKDF2-HMAC-SHA256 and 600,000 iterations.
 * Each message has a random 16-byte salt and 12-byte nonce. The output is the
 * prefix "SF-AES256-GCM:1:" followed by base64(salt || nonce || tag || ciphertext),
 * with a 16-byte authentication tag. The prefix is authenticated as additional data.
 *
 * @param string $data
 * The plaintext bytes to encrypt, including an empty string.
 *
 * @param string $password_key
 * The password bytes from which the encryption key is derived.
 *
 * @return string
 * Returns the versioned ASCII envelope, using fresh salt and nonce values on every call.
 *
 * @throws \RuntimeException
 * If OpenSSL is unavailable or AES-256-GCM encryption fails.
 *
 * @throws \Random\RandomException
 * If the operating system cannot supply cryptographically secure random bytes.
 *
 * @see \SmartFactory\aes_256_decrypt()
 *
 * @author Oleg Schildt
 */
function aes_256_encrypt(string $data, string $password_key): string
{
    if (!extension_loaded("openssl")) {
        throw new \RuntimeException("The OpenSSL extension is required for encryption.");
    }

    $prefix = "SF-AES256-GCM:1:";
    $salt = random_bytes(16);
    $nonce = random_bytes(12);
    $key = hash_pbkdf2("sha256", $password_key, $salt, 600000, 32, true);
    $tag = "";
    $encrypted_data = openssl_encrypt($data, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $nonce, $tag, $prefix, 16);

    if ($encrypted_data === false || strlen($tag) !== 16) {
        throw new \RuntimeException("AES-256-GCM encryption failed.");
    }

    return $prefix . base64_encode($salt . $nonce . $tag . $encrypted_data);
} // aes_256_encrypt

/**
 * Decrypts a versioned AES-256-GCM envelope, optionally accepting legacy AES-CBC data.
 *
 * New envelopes are authenticated before plaintext is returned. Legacy unprefixed
 * base64(salt || ciphertext) remains readable for migration, but has no integrity
 * protection and cannot reliably detect an incorrect password or tampering.
 * Set allow_legacy to false after old stored data has been re-encrypted.
 *
 * @param string $edata
 * The ASCII envelope returned by aes_256_encrypt(), or a legacy base64 payload.
 *
 * @param string $password_key
 * The password bytes used when the data was encrypted.
 *
 * @param bool $allow_legacy
 * Whether to accept the unauthenticated legacy format. Defaults to true for existing stored data.
 *
 * @return string
 * Returns the plaintext bytes, including an empty string for a successfully decrypted empty message.
 *
 * @throws \UnexpectedValueException
 * If the envelope has an unknown version, invalid base64, invalid lengths or failed
 * authentication/decryption, or if legacy data is supplied when it is disabled.
 *
 * @throws \RuntimeException
 * If the OpenSSL extension is unavailable.
 *
 * @see \SmartFactory\aes_256_encrypt()
 *
 * @author Oleg Schildt
 */
function aes_256_decrypt(string $edata, string $password_key, bool $allow_legacy = true): string
{
    if (!extension_loaded("openssl")) {
        throw new \RuntimeException("The OpenSSL extension is required for decryption.");
    }

    $prefix = "SF-AES256-GCM:1:";

    if (str_starts_with($edata, $prefix)) {
        $data = base64_decode(substr($edata, strlen($prefix)), true);

        // GCM requires the complete tag; truncated tags must never reach OpenSSL.
        if ($data === false || strlen($data) < 44) {
            throw new \UnexpectedValueException("Invalid AES-256-GCM envelope.");
        }

        $salt = substr($data, 0, 16);
        $nonce = substr($data, 16, 12);
        $tag = substr($data, 28, 16);
        $ciphertext = substr($data, 44);
        $key = hash_pbkdf2("sha256", $password_key, $salt, 600000, 32, true);
        $plaintext = openssl_decrypt($ciphertext, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $nonce, $tag, $prefix);

        if ($plaintext === false) {
            throw new \UnexpectedValueException("Decryption failed: the password or encrypted data is invalid.");
        }

        return $plaintext;
    }

    if (str_starts_with($edata, "SF-") || !$allow_legacy) {
        throw new \UnexpectedValueException("Unsupported encrypted data format.");
    }

    $data = base64_decode($edata, true);

    if ($data === false || strlen($data) < 32 || (strlen($data) - 16) % 16 !== 0) {
        throw new \UnexpectedValueException("Invalid legacy AES-256-CBC payload.");
    }

    $salt = substr($data, 0, 16);
    $first = hash("sha256", $password_key . $salt, true);
    $second = hash("sha256", $first . $password_key . $salt, true);
    $plaintext = openssl_decrypt(substr($data, 16), "aes-256-cbc", $first, OPENSSL_RAW_DATA, substr($second, 0, 16));

    if ($plaintext === false) {
        throw new \UnexpectedValueException("Legacy decryption failed: the password or encrypted data is invalid.");
    }

    return $plaintext;
} // aes_256_decrypt
