<?php
//-----------------------------------------------------------------
require_once "../includes/SmartFactory/application_root_inc.php";
//-----------------------------------------------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
<title>Class tester</title>
</head>
<body>

<h2>Class tester</h2>

<?php
function deep_include($dir)
{
  $files = scandir($dir);
  foreach($files as $file)
  {
    if($file == "." || $file == ".." || $file == ".htaccess") continue;
    
    if(is_dir($dir . $file))
    {
      deep_include($dir . $file . "/");
    }
  }  
  
  $files = scandir($dir);
  foreach($files as $file)
  {
    if($file == "." || $file == ".." || $file == ".htaccess") continue;
    
    if(!is_dir($dir . $file))
    {
      include_once $dir . $file;
    }
  }  
}

deep_include(APPLICATION_ROOT . "classes/SmartFactory/Interfaces/");

deep_include(APPLICATION_ROOT . "classes/SmartFactory/");

echo "<p>Passed!</p>";
?>

<h2>Function tester</h2>

<?php
require_once APPLICATION_ROOT . "includes/SmartFactory/utility_functions_inc.php";

require_once APPLICATION_ROOT . "includes/SmartFactory/short_functions_inc.php";

require_once APPLICATION_ROOT . "includes/SmartFactory/html_utils_inc.php";

echo "<p>Passed!</p>";

echo "<p>All is fine!</p>";
?>

</body>
</html>