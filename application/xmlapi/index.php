<?php
namespace MyApplication;

use SmartFactory\Interfaces\ISessionManager;

use function SmartFactory\singleton;

//-----------------------------------------------------------------
require_once "../includes/_general_inc.php";
//-----------------------------------------------------------------

$rmanager = singleton(HotelXmlApiRequestManager::class);

//-----------------------------------------------------------------
$rmanager->registerApiRequestHandler("GetRooms", "MyApplication\\Hotel\\RoomHandler");
//-----------------------------------------------------------------

$rmanager->handleApiRequest();
?>