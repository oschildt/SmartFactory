<?php
namespace MyApplication;

use function SmartFactory\session;
use function SmartFactory\application_settings;
use function SmartFactory\checkempty;
use function SmartFactory\echo_html;
use function SmartFactory\text;
use function SmartFactory\input_text;
use function SmartFactory\checkbox;
use function SmartFactory\messenger;

//-----------------------------------------------------------------
require_once "../includes/_general_inc.php";

require_once "message_output.php";
//-----------------------------------------------------------------
session()->startSession();

application_settings()->setContext("data_exchange_settings");
?><!DOCTYPE html>
<html lang="en">
<head>
<title>Booking Settings</title>

<link rel="stylesheet" href="examples.css" type="text/css"/>
</head>
<body>
<h2>Booking Settings: Data Exchange Settings</h2>

<?php
function process_form()
{
  if(empty($_REQUEST["act"])) return true;
  
  application_settings()->setParameter("booking_url", checkempty($_REQUEST["booking_settings"]["booking_url"]));
  application_settings()->setParameter("hotel_id", checkempty($_REQUEST["booking_settings"]["hotel_id"]));
  application_settings()->setParameter("default_rate", checkempty($_REQUEST["booking_settings"]["default_rate"]));
  
  if($_REQUEST["act"] == "Back") 
  {
    header("location: 14.application_settings.php");
    return true;
  }
  
  if(!application_settings()->validateSettings("database_settings")) return false;
  
  if(!application_settings()->saveSettings()) return false;
  
  messenger()->setInfo(text("MsgSettingsSaved"));
  
  header("location: 14.application_settings_final.php");
  exit();  
} // process_form

process_form();
?>

<?php
report_messages();
?>

<p>Dirty (global): <?php echo(application_settings()->isDirty(true)); ?></p>
<p>Dirty (this mask - <?php echo(application_settings()->getContext()); ?>): <?php echo(application_settings()->isDirty()); ?></p>

<form action="14.application_settings_next.php" method="post">

<table>
<tr>
  <td>Booking Service URL*:</td>
  <td>
    <?php input_text(["name" => "booking_settings[booking_url]", 
                      "autocomplete" => "off",
                      "value" => application_settings()->getParameter("booking_url", true)
                     ]); ?>
  </td>
</tr>
<tr>
  <td>Hotel-ID*:</td>
  <td>
    <?php input_text(["name" => "booking_settings[hotel_id]", 
                      "autocomplete" => "off",
                      "value" => application_settings()->getParameter("hotel_id", true)
                     ]); ?>
  </td>
</tr>
<tr>
  <td>Default Rate*:</td>
  <td>
    <?php input_text(["name" => "booking_settings[default_rate]", 
                      "autocomplete" => "off",
                      "value" => application_settings()->getParameter("default_rate", true)
                     ]); ?>
  </td>
</tr>
</table>

<br>
<br>

<input type="submit" name="act" value="Next">

<input type="submit" name="act" value="Back">

</form>

</body>
</html>


