<?php
namespace MyApplication;

use function SmartFactory\session;
use function SmartFactory\messenger;

//-----------------------------------------------------------------
require_once "../includes/_general_inc.php";

require_once "message_output.php";
//-----------------------------------------------------------------
session()->startSession();
?><!DOCTYPE html>
<html lang="en">
<head>
<title>Messages</title>

<link rel="stylesheet" href="examples.css" type="text/css"/>
</head>
<body>
<h2>Messages - next step</h2>

<p>Display error and warning messages collected over the execution.</p>

<?php
report_messages();
?>

</body>
</html>


