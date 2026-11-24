<?php

/**
 * This file contains the implementation of the class PreparedQuery.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

/**
 * Compiles public SQL placeholders and validates prepared-query arguments.
 *
 * Recognizes SQL strings, quoted identifiers, comments and dialect-specific quoting.
 * Only positional ? and named :name markers are accepted. Named occurrences are tracked
 * separately from the unique argument names, allowing one value to serve several positions.
 * No database connection is required for compilation or argument validation.
 *
 * @author Oleg Schildt
 */
final class PreparedQuery
{
    /**
     * Creates the immutable result of SQL placeholder compilation.
     *
     * @param string $original
     * The original application SQL.
     *
     * @param string $sql
     * The SQL containing native driver markers.
     *
     * @param list<string|null> $names
     * The name for each marker occurrence, or NULL for each positional marker.
     *
     * @param bool $named
     * True if the application SQL uses named markers.
     *
     * @author Oleg Schildt
     */
    private function __construct(
        /**
         * Stores the original application SQL before native marker translation.
         *
         * @var string
         *
         * @author Oleg Schildt
         */
        public readonly string $original,

        /**
         * Stores SQL with native driver bind markers.
         *
         * @var string
         *
         * @author Oleg Schildt
         */
        public readonly string $sql,

        /**
         * Stores the parameter name, or NULL for a positional marker, for each SQL occurrence.
         *
         * @var list<string|null>
         *
         * @author Oleg Schildt
         */
        public readonly array $names,

        /**
         * Indicates whether the compiled query uses named application parameters.
         *
         * @var bool
         *
         * @author Oleg Schildt
         */
        public readonly bool $named
    ) {
        // Promoted properties retain the immutable compilation result; no database access occurs here.
    } // __construct

    /**
     * Compiles application bind markers into native SQL without changing quoted SQL content.
     *
     * Recognizes quoted strings/identifiers, comments, PostgreSQL dollar quotes and JSON operators,
     * and Oracle q/nq quoting. PostgreSQL reuses the same native index for repeated names so all uses
     * participate in native type inference. Parameter values are never inserted into SQL text.
     *
     * @param string $sql
     * The application SQL containing either ? or :name markers.
     *
     * @param string $dialect
     * The SQL dialect: mysql, mssql, postgres or oracle.
     *
     * @param bool $backslash_escapes
     * Whether MySQL backslash escapes are active. Other dialects apply their own quoting rules.
     *
     * @return self
     * Returns the original SQL, translated SQL and marker occurrence mapping.
     *
     * @throws DBWorkerException
     * If marker styles are mixed, native numbered markers are used, a quoted construct or comment is
     * unterminated, or placeholders occur inside an executable MySQL comment.
     *
     * @author Oleg Schildt
     */
    public static function compile(string $sql, string $dialect, bool $backslash_escapes = true): self
    {
        // Scan SQL constructs before recognizing markers so quoted text and comments remain untouched.
        $output = '';
        $names = [];
        $named = false;
        $positional = false;
        $named_indexes = [];
        $operand = false;
        $length = strlen($sql);

        // Track expression boundaries so PostgreSQL JSON operators are not mistaken for bind markers.
        $keywords = array_fill_keys(
            explode(
                ' ',
                'SELECT WHERE AND OR NOT WHEN THEN ELSE ON BY AS HAVING LIMIT OFFSET VALUES SET RETURNING DISTINCT INTO UNION ALL CASE EXISTS BETWEEN LIKE ILIKE IS IN UPDATE INSERT DELETE FROM JOIN CROSS LEFT RIGHT INNER OUTER FULL USING TOP FETCH NEXT ROWS ONLY ASC DESC'
            ),
            true
        );

        for ($i = 0; $i < $length;) {
            $start = $i;
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if (
                (
                    $char === '-'
                    && $next === '-'
                    && ($dialect !== 'mysql' || $i + 2 === $length || ctype_space($sql[$i + 2]))
                )
                || ($dialect === 'mysql' && $char === '#')
            ) {
                // Skip a line comment as opaque SQL, including any marker-like text in it.
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
            } elseif ($char === '/' && $next === '*') {
                $i += 2;

                // Only dialects with nested block comments increase the nesting depth.
                $depth = 1;

                while ($i < $length && $depth > 0) {
                    if (substr($sql, $i, 2) === '/*' && in_array($dialect, ['postgres', 'mssql'], true)) {
                        $depth++;
                        $i += 2;
                    } elseif (substr($sql, $i, 2) === '*/') {
                        $depth--;
                        $i += 2;
                    } else {
                        $i++;
                    }
                }

                if ($depth !== 0) {
                    self::invalid('Unterminated SQL comment.');
                }

                if (
                    $dialect === 'mysql'
                    && substr($sql, $start, 3) === '/*!'
                    && preg_match('/\?|:[a-zA-Z_]/', substr($sql, $start, $i - $start))
                ) {
                    self::invalid('Placeholders inside executable MySQL comments are not supported.');
                }
            } elseif (
                $dialect === 'oracle'
                && preg_match("/\\A(?:nq|q)'(.)/is", substr($sql, $i), $match)
                && ($i === 0 || !preg_match('/[a-zA-Z0-9_]/', $sql[$i - 1]))
            ) {
                // Oracle alternative quotes use either a paired delimiter or a custom closing character.
                $open = $match[1];
                $close = ['[' => ']', '(' => ')', '{' => '}', '<' => '>'][$open] ?? $open;
                $end = strpos($sql, $close . "'", $i + strlen($match[0]));

                if ($end === false) {
                    self::invalid('Unterminated Oracle quoted string.');
                }

                $i = $end + 2;
                $operand = true;
            } elseif (
                $char === "'"
                || $char === '"'
                || ($dialect === 'mysql' && $char === '`')
                || ($dialect === 'mssql' && $char === '[')
            ) {
                // Handle doubled delimiters and only the backslash escapes enabled for this dialect.
                $close = $char === '[' ? ']' : $char;
                $escape = ($dialect === 'mysql' && $backslash_escapes)
                    || (
                        $dialect === 'postgres'
                        && $char === "'"
                        && $i > 0
                        && strtolower($sql[$i - 1]) === 'e'
                        && ($i < 2 || !preg_match('/[a-zA-Z0-9_]/', $sql[$i - 2]))
                    );
                $i++;
                $closed = false;

                while ($i < $length) {
                    if ($escape && $sql[$i] === '\\') {
                        $i += 2;
                    } elseif ($sql[$i] === $close) {
                        if (($sql[$i + 1] ?? '') === $close) {
                            $i += 2;
                        } else {
                            $i++;
                            $closed = true;

                            break;
                        }
                    } else {
                        $i++;
                    }
                }

                if (!$closed) {
                    self::invalid('Unterminated SQL string or identifier.');
                }

                $operand = true;
            } elseif (
                $dialect === 'postgres'
                && $char === '$'
                && preg_match('/\A\$(?:[a-zA-Z_][a-zA-Z0-9_]*)?\$/', substr($sql, $i), $match)
            ) {
                // Keep the entire PostgreSQL dollar-quoted string unchanged.
                $end = strpos($sql, $match[0], $i + strlen($match[0]));

                if ($end === false) {
                    self::invalid('Unterminated PostgreSQL dollar-quoted string.');
                }

                $i = $end + strlen($match[0]);
                $operand = true;
            } elseif ($char === '$' && ctype_digit($next)) {
                self::invalid('Use ? for positional placeholders; native $1 placeholders are not accepted.');
            } elseif ($char === ':' && ($next === ':' || $next === '=')) {
                $i += 2; // PostgreSQL casts and PL/SQL assignments.
            } elseif ($char === ':' && ctype_digit($next)) {
                self::invalid('Use ? or :name for placeholders.');
            } elseif ($char === ':' && preg_match('/\A:([a-zA-Z_][a-zA-Z0-9_]*)/', substr($sql, $i), $match)) {
                // Record each named occurrence, but reuse the native PostgreSQL index for repeated names.
                $named = true;
                $names[] = $match[1];
                $named_indexes[$match[1]] ??= count($named_indexes) + 1;
                $output .= self::marker($dialect, $dialect === 'postgres' ? $named_indexes[$match[1]] : count($names));
                $i += strlen($match[0]);
                $operand = true;

                continue;
            } elseif ($char === '?') {
                // PostgreSQL JSON operators are SQL, not bind markers. An operand on the
                // left distinguishes the bare ? operator from a positional placeholder.
                if (
                    $dialect === 'postgres'
                    && ($operand || in_array($next, ['|', '&'], true) || ($sql[$i - 1] ?? '') === '@')
                ) {
                    $i += in_array($next, ['|', '&'], true) ? 2 : 1;
                    $operand = false;
                } else {
                    // Only a real positional marker contributes another bound value.
                    $positional = true;
                    $names[] = null;
                    $output .= self::marker($dialect, count($names));
                    $i++;
                    $operand = true;

                    continue;
                }
            } elseif (preg_match('/[a-zA-Z_]/', $char)) {
                preg_match('/\A[a-zA-Z_][a-zA-Z0-9_$#]*/', substr($sql, $i), $match);

                $i += strlen($match[0]);

                // A keyword requiring an expression cannot be the left operand of a JSON operator.
                $operand = !isset($keywords[strtoupper($match[0])]);
            } else {
                if (!ctype_space($char)) {
                    $operand = ctype_digit($char) || $char === ')' || $char === ']';
                }

                $i++;
            }

            // Preserve the exact bytes of SQL regions that do not need marker translation.
            $output .= substr($sql, $start, $i - $start);
        }

        // Reject a mixed public syntax after scanning all quoted/commented regions.
        if ($named && $positional) {
            self::invalid('Named and positional placeholders cannot be mixed.');
        }

        return new self($sql, $output, $names, $named);
    } // compile

    /**
     * Formats a native bind marker for the selected database dialect.
     *
     * @param string $dialect
     * The native SQL dialect.
     *
     * @param int $index
     * The one-based native parameter index.
     *
     * @return string
     * Returns ?, $n or :pn according to the driver.
     *
     * @author Oleg Schildt
     */
    private static function marker(string $dialect, int $index): string
    {
        // Translate only the placeholder syntax; argument values never become part of SQL text.
        return match ($dialect) {
            'postgres' => '$' . $index,
            'oracle' => ':p' . $index,
            default => '?'
        };
    } // marker

    /**
     * Validates arguments and expands repeated named occurrences into ordered values.
     *
     * A supplied NULL is recognized as a value. Named array keys may start with one colon.
     * Duplicate normalized names are rejected, and floating-point values must be finite.
     *
     * @param array<array-key, mixed> $args
     * The variadic argument array received by execute_prepared_query(), optionally containing a single argument array.
     *
     * @return list<scalar|null|BlobValue|ClobValue>
     * Returns values in SQL occurrence order; a repeated name contributes the same value for each occurrence.
     *
     * @throws DBWorkerException
     * If argument names or counts do not match the placeholders, argument styles are mixed, positional
     * keys are not contiguous, names are invalid or duplicated, or values have unsupported types or non-finite floats.
     *
     * @author Oleg Schildt
     */
    public function arguments(array $args): array
    {
        // Normalize the supported call forms before checking the exact set of required parameters.
        if (array_is_list($args) && count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        if (!$this->named) {
            if (!array_is_list($args) || count($args) !== count($this->names)) {
                self::invalid('Positional parameters must be a contiguous list matching the number of placeholders.');
            }

            $values = $args;
        } else {
            $provided = [];

            foreach ($args as $name => $value) {
                if (!is_string($name)) {
                    self::invalid('Named placeholders require named arguments or an associative array.');
                }

                $name = str_starts_with($name, ':') ? substr($name, 1) : $name;

                if (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $name) || array_key_exists($name, $provided)) {
                    self::invalid('Invalid or duplicate parameter name.');
                }

                $provided[$name] = $value;
            }

            // Validate unique names before expanding repeated SQL occurrences.
            $required = array_fill_keys($this->names, true);

            if (array_diff_key($required, $provided) || array_diff_key($provided, $required)) {
                self::invalid('Supplied parameter names must exactly match the query placeholders.');
            }

            $values = array_map(static fn (string $name): mixed => $provided[$name], $this->names);
        }

        // Reject unsupported objects and non-finite floats before any native execution.
        foreach ($values as $value) {
            if ($value !== null && !is_scalar($value) && !$value instanceof ClobValue && !$value instanceof BlobValue) {
                self::invalid('Unsupported parameter value; wrap streams in ClobValue or BlobValue.');
            }

            if (is_float($value) && !is_finite($value)) {
                self::invalid('Numeric parameters must be finite.');
            }
        }

        return $values;
    } // arguments

    /**
     * Throws the standard DBWorker data-format exception for an invalid query or argument list.
     *
     * @param string $message
     * The validation failure description.
     *
     * @return never
     * This method does not return.
     *
     * @throws DBWorkerException
     * Always thrown with the ERR_WRONG_DATA_FORMAT error code and the supplied validation message.
     *
     * @author Oleg Schildt
     */
    private static function invalid(string $message): never
    {
        // Use the same domain exception for all placeholder and argument validation failures.
        throw new DBWorkerException($message, DBWorker::ERR_WRONG_DATA_FORMAT);
    } // invalid
} // PreparedQuery
