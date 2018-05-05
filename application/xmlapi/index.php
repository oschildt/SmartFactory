<?php
namespace MyApplication;

use function SmartFactory\singleton;

//-----------------------------------------------------------------
require_once "../includes/_general_inc.php";
//-----------------------------------------------------------------

$apireqhandler = singleton(HotelXmlApiRequestHandler::class);

$dir = __DIR__ . "/handlers/";
$files = scandir($dir);
foreach($files as $file)
{
  if($file == "." || $file == ".." || $file == ".htaccess") continue;
  
  if(is_dir($dir . $file))
  {
    $subdir = $dir . $file . "/";
    
    $subfiles = scandir($subdir);
    foreach($subfiles as $subfile)
    {
      if($subfile == "." || $subfile == ".." || $subfile == ".htaccess" || is_dir($subdir . $subfile)) continue;
      
      if(file_exists($subdir . $subfile)) @include_once($subdir . $subfile);
    }
  }

  if(file_exists($dir . $file)) @include_once($dir . $file);
}

$apireqhandler->handleApiRequest();

?>