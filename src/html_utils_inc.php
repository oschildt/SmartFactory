<?php
/**
 * This file contains the implementation of the auxiliary functions
 * for placement of the form fields values of which can be automatically bound to
 * the request variables with the corresponding name.
 *
 * Furthermore, it provides the function for creating a table from an array.
 *
 * @package HTML Utils
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Function for getting value of a request variable.
 *
 * This function checks wether the request variable exists and
 * returns its value. If the request variable does not exist,
 * an empty value is returned.
 *
 * @param string $name
 * Name of the request variable how it is written in the form fields.
 *
 * @return mixed
 * The value of the request variable or an empty string.
 *
 * @author Oleg Schildt
 */
function reqvar_value($name)
{
    if (empty($name)) {
        return "";
    }
    
    $name = preg_replace("/([^\[\]]+)(.*)/i", "\$_REQUEST[\\1]\\2", $name);
    $name = preg_replace("/\[([^\[\]]+)\]/i", "[\"\\1\"]", $name);
    $name = str_replace("[]", "", $name);
    
    $val = "";
    // protection against the php injection
    $name = preg_replace("/[;,\\(\\)\\?]+/", "", $name);
    
    eval("\$val = isset($name) ? $name : '';");
    
    return $val;
} // reqvar_value

/**
 * Function for rendering the text input field.
 *
 * This function renders a text input field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * - $parameters["value"] - value that should be set for the field. If it is not specified,
 * the value of the corresponding request variable is taken.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * input_text(["id" => "data_input_text",
 *             "name" => "data[input_text]",
 *             "class" => "my_class",
 *             "style" => "width: 300px",
 *             "placeholder" => "enter the data",
 *             "title" => "enter the data",
 *             "data-prop" => "some-prop",
 *             "onblur" => "alert('Hello & Buy!')"
 *            ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_password()
 * @see \SmartFactory\input_hidden()
 * @see \SmartFactory\textarea()
 * @see \SmartFactory\select()
 * @see \SmartFactory\checkbox()
 * @see \SmartFactory\radiobutton()
 *
 * @author Oleg Schildt
 */
function input_text($parameters, $echo = true)
{
    $val = "";
    
    $html = "<input type=\"text\"";
    
    $sys_fields = ["id", "name", "value", "formatter"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    // Value setting logic:
    //
    // If explicitely set in value, than take it, otherwise take it from the request variable with that name.
    
    if (!empty($parameters["value"])) {
        $val = $parameters["value"];
    }
    
    if (!empty($parameters["formatter"]) && is_callable($parameters["formatter"])) {
        $val = call_user_func($parameters["formatter"], $val);
    }
    
    $html .= " value=\"" . escape_html($val) . "\"";
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    $html .= ">";
    
    if (!$echo) {
        return $html;
    }
    
    //echo("<pre>" . escape_html($html) . "</pre>");
    echo($html);
    
    return null;
} // input_text

/**
 * Function for rendering the password input field.
 *
 * This function renders a password input field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * input_password(["id" => "data_input_password",
 *                 "name" => "data[input_password]",
 *                 "class" => "my_class",
 *                 "style" => "width: 300px",
 *                 "placeholder" => "enter the password",
 *                 "title" => "enter the password",
 *                 "data-prop" => "some-prop"
 *                ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_text()
 * @see \SmartFactory\input_hidden()
 * @see \SmartFactory\textarea()
 * @see \SmartFactory\select()
 * @see \SmartFactory\checkbox()
 * @see \SmartFactory\radiobutton()
 *
 * @author Oleg Schildt
 */
function input_password($parameters, $echo = true)
{
    $val = "";
    
    $html = "<input type=\"password\"";
    
    $sys_fields = ["id", "name", "value", "formatter"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    // Value setting logic:
    //
    // If explicitely set in value, than take it, otherwise take it from the request variable with that name.
    
    if (!empty($parameters["value"])) {
        $val = $parameters["value"];
    }
    
    if (!empty($parameters["formatter"]) && is_callable($parameters["formatter"])) {
        $val = call_user_func($parameters["formatter"], $val);
    }
    
    $html .= " value=\"" . escape_html($val) . "\"";
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    $html .= ">";
    
    if (!$echo) {
        return $html;
    }
    
    //echo("<pre>" . escape_html($html) . "</pre>");
    echo($html);
    
    return null;
} // input_password

/**
 * Function for rendering the hidden input field.
 *
 * This function renders a text hidden field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * - $parameters["value"] - value that should be set for the field. If it is not specified,
 * the value of the corresponding request variable is taken.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * input_hidden(["id" => "data_input_hidden",
 *               "name" => "data[input_hidden]",
 *               "data-prop" => "some-prop"
 *              ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_text()
 * @see \SmartFactory\input_password()
 * @see \SmartFactory\textarea()
 * @see \SmartFactory\select()
 * @see \SmartFactory\checkbox()
 * @see \SmartFactory\radiobutton()
 *
 * @author Oleg Schildt
 */
function input_hidden($parameters, $echo = true)
{
    $val = "";
    
    $html = "<input type=\"hidden\"";
    
    $sys_fields = ["id", "name", "value", "formatter"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    // Value setting logic:
    //
    // If explicitely set in value, than take it, otherwise take it from the request variable with that name.
    
    if (!empty($parameters["value"])) {
        $val = $parameters["value"];
    }
    
    if (!empty($parameters["formatter"]) && is_callable($parameters["formatter"])) {
        $val = call_user_func($parameters["formatter"], $val);
    }
    
    $html .= " value=\"" . escape_html($val) . "\"";
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    $html .= ">";
    
    if (!$echo) {
        return $html;
    }
    
    echo($html);
    
    return null;
} // input_hidden

/**
 * Function for rendering the text area field.
 *
 * This function renders a text area field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * - $parameters["value"] - value that should be set for the field. If it is not specified,
 * the value of the corresponding request variable is taken.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * textarea(["id" => "data_textarea",
 *           "name" => "data[textarea]",
 *           "class" => "my_class",
 *           "style" => "width: 300px; height: 150px;",
 *           "placeholder" => "enter the text",
 *           "title" => "enter the text",
 *           "data-prop" => "some-prop",
 *           "onblur" => "alert('Hello & Buy!')"
 *          ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_text()
 * @see \SmartFactory\input_password()
 * @see \SmartFactory\input_hidden()
 * @see \SmartFactory\select()
 * @see \SmartFactory\checkbox()
 * @see \SmartFactory\radiobutton()
 *
 * @author Oleg Schildt
 */
function textarea($parameters, $echo = true)
{
    $val = "";
    
    $html = "<textarea";
    
    $sys_fields = ["id", "name", "value", "formatter"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    // Value setting logic:
    //
    // If explicitely set in value, than take it, otherwise take it from the request variable with that name.
    
    if (!empty($parameters["value"])) {
        $val = $parameters["value"];
    }
    
    if (!empty($parameters["formatter"]) && is_callable($parameters["formatter"])) {
        $val = call_user_func($parameters["formatter"], $val);
    }
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    $html .= ">" . escape_html($val);
    $html .= "</textarea>";
    
    if (!$echo) {
        return $html;
    }
    
    echo($html);
    
    return null;
} // textarea

/**
 * Function for rendering the select field.
 *
 * This function renders a select field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * - $parameters["value"] - value that should be set for the field. If it is not specified,
 * the value of the corresponding request variable is taken.
 *
 * - $parameters["options"] - an associative array of list options in the form "option value" => "option text".
 *
 * - $parameters["multiple"] - set this key "multiple" => "multiple" if the list should support multichoice.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * $options = [
 *   "yellow" => "Yellow",
 *   "blue" => "Blue",
 *   "red" => "Red",
 *   "brown" => "Brown",
 *   "black" => "Black",
 *   "white" => "White",
 *   "green" => "Green"
 * ];
 *
 * select(["id" => "data_multiselect",
 *         "name" => "data[multiselect][]",
 *         "multiple" => "multiple",
 *         "class" => "my_class",
 *         "style" => "width: 300px; height: 180px",
 *         "title" => "select multi value",
 *         "data-prop" => "some-prop",
 *         "options" => $options
 *        ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_text()
 * @see \SmartFactory\input_password()
 * @see \SmartFactory\input_hidden()
 * @see \SmartFactory\textarea()
 * @see \SmartFactory\checkbox()
 * @see \SmartFactory\radiobutton()
 *
 * @author Oleg Schildt
 */
function select($parameters, $echo = true)
{
    $val = "";
    
    $html = "<select";
    
    $sys_fields = ["id", "name", "value", "options", "multiple"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    // Value setting logic:
    //
    // If explicitely set in value, than take it, otherwise take it from the request variable with that name.
    
    if (!empty($parameters["value"])) {
        $val = $parameters["value"];
    }
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    if (isset($parameters["multiple"])) {
        $html .= " multiple";
    }
    
    $html .= ">\n";
    
    if (!empty($parameters["options"])) {
        if (!is_array($parameters["options"])) {
            trigger_error("The parameter options must be an array val => text!", E_USER_ERROR);
        } else {
            foreach ($parameters["options"] as $optval => $text) {
                $selected = "";
                
                if (!is_array($val) && $optval == $val) {
                    $selected = "selected";
                }
                if (is_array($val) && in_array($optval, $val)) {
                    $selected = "selected";
                }
                
                $html .= "<option value=\"" . escape_html($optval) . "\" $selected>" . escape_html($text) . "</option>\n";
            }
        }
    }
    
    $html .= "</select>";
    
    if (!$echo) {
        return $html;
    }
    
    //echo("<pre>" . escape_html($html) . "</pre>");
    echo($html);
    
    return null;
} // select

/**
 * Function for rendering the checkbox field.
 *
 * This function renders a checkbox field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * - $parameters["value"] - the value of the request variable that should be submitted
 * if the checkbox is checked.
 *
 * - $parameters["checked"] - checked state that should be set for the field. If it is not specified,
 * the value of the corresponding request variable is used to set the checked state.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * checkbox(["id" => "data_checkbox",
 *           "name" => "data[checkbox]",
 *           "class" => "my_class",
 *           "value" => "1",
 *           "title" => "select checkbox",
 *           "data-prop" => "some-prop"
 *          ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_text()
 * @see \SmartFactory\input_password()
 * @see \SmartFactory\input_hidden()
 * @see \SmartFactory\textarea()
 * @see \SmartFactory\select()
 * @see \SmartFactory\radiobutton()
 *
 * @author Oleg Schildt
 */
function checkbox($parameters, $echo = true)
{
    $val = "";
    
    $html = "<input type=\"checkbox\"";
    
    $sys_fields = ["id", "name", "value", "checked"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    if (!empty($parameters["value"])) {
        $html .= " value=\"" . escape_html($parameters["value"]) . "\"";
    }
    
    // Checked setting logic:
    //
    // If explicitely set in checked, than take it, otherwise take it from the request variable with that name.
    
    $checked = "";
    
    if (!is_array($val) && !empty($val)) {
        $checked = " checked";
    }
    if (is_array($val) && in_array(checkempty($parameters["value"]), $val)) {
        $checked = " checked";
    }
    
    if (!empty($parameters["checked"])) {
        $checked = " checked";
    }
    
    $html .= $checked;
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    $html .= ">";
    
    if (!$echo) {
        return $html;
    }
    
    //echo("<pre>" . escape_html($html) . "</pre>");
    echo($html);
    
    return null;
} // checkbox

/**
 * Function for rendering the radiobutton field.
 *
 * This function renders a radiobutton field that is bound to
 * the request variable with the corresponding name.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["id"] - id of the field.
 *
 * - $parameters["name"] - name of the field.
 *
 * - $parameters["value"] - the value of the request variable that should be submitted
 * if the radiobutton is checked.
 *
 * - $parameters["checked"] - checked state that should be set for the field. If it is not specified,
 * the value of the corresponding request variable is used to set the checked state.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * radiobutton(["id" => "data_radiocolor_red",
 *              "name" => "data[radiocolor]",
 *              "value" => "red"
 *             ]);
 *
 * radiobutton(["id" => "data_radiocolor_green",
 *              "name" => "data[radiocolor]",
 *              "value" => "green"
 *             ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the field is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the field html
 * code is returned, otherwise null.
 *
 * @see \SmartFactory\input_text()
 * @see \SmartFactory\input_password()
 * @see \SmartFactory\input_hidden()
 * @see \SmartFactory\textarea()
 * @see \SmartFactory\select()
 * @see \SmartFactory\checkbox()
 *
 * @author Oleg Schildt
 */
function radiobutton($parameters, $echo = true)
{
    $val = "";
    
    $html = "<input type=\"radio\"";
    
    $sys_fields = ["id", "name", "value", "checked"];
    
    if (!empty($parameters["id"])) {
        $html .= " id=\"" . escape_html($parameters["id"]) . "\"";
    }
    if (!empty($parameters["name"])) {
        $html .= " name=\"" . escape_html($parameters["name"]) . "\"";
        $val = reqvar_value($parameters["name"]);
    }
    
    if (!empty($parameters["value"])) {
        $html .= " value=\"" . escape_html($parameters["value"]) . "\"";
    }
    
    // Checked setting logic:
    //
    // If explicitely set in checked, than take it, otherwise take it from the request variable with that name.
    
    $checked = "";
    
    if (!empty($val) && checkempty($parameters["value"]) == $val) {
        $checked = " checked";
    }
    
    if (!empty($parameters["checked"])) {
        $checked = " checked";
    }
    
    $html .= $checked;
    
    foreach ($parameters as $name => $data) {
        if (in_array($name, $sys_fields)) {
            continue;
        }
        
        $html .= " " . escape_html($name) . "=\"" . escape_html($data) . "\"";
    }
    
    $html .= ">";
    
    if (!$echo) {
        return $html;
    }
    
    //echo("<pre>" . escape_html($html) . "</pre>");
    echo($html);
    
    return null;
} // radiobutton

/**
 * Function for rendering tables.
 *
 * This function renders a tables based on the values of an array.
 *
 * @param array &$array
 * The array of the rows.
 *
 * @param array $parameters
 * The array of parameters as an associative array in the form key => value:
 *
 * - $parameters["captions"] - the associative array of column captions. If
 * it is not specified, the table is rendered without captions.
 *
 * - $parameters["col_class_from_keys"] - if it is specified and set to true,
 * the keys of the captions are used as the class names.
 *
 * - $parameters["no_escape_html"] - if it is specified and set to true,
 * the values of the data array are not escaped and passed as is. It is useful
 * when html codes of other elements are passed. Per default, the table data
 * is escaped for html.
 *
 * - $parameters["formatter"] - this function is used for the value formatting
 * if specified. It is called for evelry cell of the table. The signature of
 * this function is:
 *
 * ```php
 * function ($rownum, $colnum, $colname, $val) : string;
 * ```
 *
 * - $rownum - the row number.
 *
 * - $colnum - the colnum number.
 *
 * - $colname - the column name if the $parameters["captions"] are provided.
 * The keys of the cpation array are used. If the $parameters["captions"]
 * are not provided, $colname is equal to $colnum.
 *
 * - $val - original value of the table cell.
 *
 * If necessary, format the original value and return it. Otherwise,
 * return the original value without changes.
 *
 * Any other attributes may be specified.
 *
 * Example:
 * ```php
 * $rows = [
 *    ["name" => "DB design", "employee" => "Alex", ... ],
 *    ["name" => "Mask implementation", "employee" => "Boris", ... ],
 *    ["name" => "Validation", "employee" => "Alex", ... ],
 *    ["name" => "Settings", "employee" => "Boris", ... ],
 *    ["name" => "About", "employee" => "Robert", ... ],
 *    ["name" => "Initialization", "employee" => "Alon", ... ]
 * ];
 *
 * $captions = [
 *   "name" => "Task name",
 *   "employee" => "Employee",
 *   "time_estimation" => "Time estimation",
 *   "deadline" => "Deadline",
 *   "comments" => "Conmments"
 * ];
 *
 * $formatter = function ($rownum, $colnum, $colname, $val) {
 *   if($colname == "time_estimation") return format_number($val, 2);
 *   if($colname == "deadline") return date("Y-m-d H:i", $val);
 *   return $val;
 * };
 *
 * table($rows,
 *       ["captions" => $captions,
 *        "class" => "my_table",
 *        "style" => "background-color: #dddddd",
 *        "col_class_from_keys" => true,
 *        "no_escape_html" => false,
 *        "formatter" => $formatter
 *       ]);
 * ```
 *
 * @param boolean $echo
 * If the value of $echo is true, the html code of the table is directly echoed.
 * Otherwise it is returned as string. It might be useful to pass this code to
 * other rendering functions instead of echoing.
 *
 * @return string|null
 * If the paramter $echo is true, the string of the table html
 * code is returned, otherwise null.
 *
 * @author Oleg Schildt
 */
function table(&$array, $parameters = [], $echo = true)
{
    if (!is_array($array)) {
        trigger_error("An array expected!", E_USER_ERROR);
    }
    
    $html = "<table";
    
    if (!empty($parameters["class"])) {
        $html .= " class=\"" . escape_html($parameters["class"]) . "\"";
    }
    if (!empty($parameters["style"])) {
        $html .= " style=\"" . escape_html($parameters["style"]) . "\"";
    }
    
    $html .= ">\n";
    
    if (!empty($parameters["captions"])) {
        $html .= "<tr>\n";
        
        foreach ($parameters["captions"] as $key => $value) {
            $html .= "<th";
            
            if (!empty($parameters["col_class_from_keys"])) {
                $html .= " class=\"" . escape_html($key) . "\"";
            }
            
            $html .= ">\n";
            
            if (empty($parameters["no_escape_html"])) {
                $value = escape_html($value);
            }
            
            $html .= $value;
            
            $html .= "\n</th>\n";
        }
        
        $html .= "</tr>\n";
    }
    
    $rownum = 0;
    foreach ($array as &$row) {
        $rownum++;
        
        $html .= "<tr>\n";
        
        $colnum = 0;
        foreach ($row as $key => $value) {
            $colnum++;
            
            $html .= "<td";
            
            if (!empty($parameters["col_class_from_keys"])) {
                $html .= " class=\"" . escape_html($key) . "\"";
            }
            
            $html .= ">\n";
            
            if (!empty($parameters["formatter"]) && is_callable($parameters["formatter"])) {
                $value = call_user_func($parameters["formatter"], $rownum, $colnum, $key, $value);
            }
            
            if (empty($parameters["no_escape_html"])) {
                $value = escape_html($value);
            }
            
            $html .= $value;
            
            $html .= "\n</td>\n";
        }
        
        $html .= "</tr>\n";
    }
    
    $html .= "</table>";
    
    if (!$echo) {
        return $html;
    }
    
    echo($html);
    
    return null;
} // table
