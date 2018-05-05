<?php
namespace MyApplication;

use SmartFactory\Interfaces\ILanguageManager;

use function SmartFactory\singleton;
use function SmartFactory\session;
use function SmartFactory\user_settings;
use function SmartFactory\checkempty;
use function SmartFactory\echo_html;
use function SmartFactory\text;
use function SmartFactory\input_text;
use function SmartFactory\checkbox;
use function SmartFactory\select;

//-----------------------------------------------------------------
require_once "../includes/_general_inc.php";

require_once "message_output.php";
//-----------------------------------------------------------------
session()->startSession();

user_settings()->setContext("general_settings");
?><!DOCTYPE html>
<html lang="en">
<head>
<title>User Settings</title>

<link rel="stylesheet" href="examples.css" type="text/css"/>
</head>
<body>
<h2>User Settings: General Settings</h2>

<?php
$language_list = [];
$language_list[""] = "-";
singleton(ILanguageManager::class)->getLanguageList($language_list);

function process_form()
{
  if(empty($_REQUEST["act"])) return true;
  
  user_settings()->setParameter("LANGUAGE", checkempty($_REQUEST["user_settings"]["LANGUAGE"]));
  user_settings()->setParameter("TIME_ZONE", checkempty($_REQUEST["user_settings"]["TIME_ZONE"]));
  
  if(!user_settings()->validateSettings()) return false;
  
  header("location: 15.user_settings_next.php");
  exit();  
} // process_form

process_form();
?>

<?php
report_messages();
?>

<p>Dirty (global): <?php echo(user_settings()->isDirty(true)); ?></p>
<p>Dirty (this mask - <?php echo(user_settings()->getContext()); ?>): <?php echo(user_settings()->isDirty()); ?></p>

<form action="15.user_settings.php" method="post">

<table>
<tr>
  <td>Language*:</td>
  <td>
    <?php select(["name" => "user_settings[LANGUAGE]", 
                  "options" => $language_list,
                  "value" => user_settings()->getParameter("LANGUAGE", true)
                  ]); ?>
  </td>
</tr>
<tr>
  <td>Time zone*:</td>
  <td>
    <?php input_text(["name" => "user_settings[TIME_ZONE]", 
                      "autocomplete" => "off",
                      "value" => user_settings()->getParameter("TIME_ZONE", true)
                     ]); ?>
  </td>
</tr>
</table>

<br>
<br>

<input type="submit" name="act" value="Next">

</form>

</body>
</html>



