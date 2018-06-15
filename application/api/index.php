<?php
namespace MyApplication;

use SmartFactory\Interfaces\ISessionManager;
use SmartFactory\JsonApiRequestManager;

use function SmartFactory\singleton;

//-----------------------------------------------------------------
require_once "../includes/_general_inc.php";
//-----------------------------------------------------------------

singleton(ISessionManager::class)->startSession();

$rmanager = singleton(JsonApiRequestManager::class);

//-----------------------------------------------------------------
$rmanager->registerApiRequestHandler("login", "MyApplication\\Handlers\\LoginHandler");
//-----------------------------------------------------------------

$rmanager->handleApiRequest();
