<?php
namespace MyApplication;

use function SmartFactory\singleton;

singleton(HotelXmlApiRequestHandler::class)->registerApiRequestHandler("GetRooms", function($handler, $api_request, $xmldoc) {
  $xsdpath = new \DOMXPath($xmldoc);
  
  $nodes = $xsdpath->evaluate("/Request/City");
  if($nodes->length == 0)
  {
    $response_data["errors"] = [
      ["error_code" => "no_city", "error_text" => "City is undefined!"]
    ];

    $handler->reportErrors($response_data);
    return false;
  }
  $city = $nodes->item(0)->nodeValue;

  $outxmldoc = new \DOMDocument("1.0", "UTF-8");
  $outxmldoc->formatOutput = true;
  
  $response = $outxmldoc->createElement("Response");
  $outxmldoc->appendChild($response);
  
  $node = $outxmldoc->createElement("City");
  $response->appendChild($node);

  $text = $outxmldoc->createTextNode($city);
  $node->appendChild($text);
  
  $rooms = $outxmldoc->createElement("Rooms");
  $response->appendChild($rooms);

  $node = $outxmldoc->createElement("Room");
  $node->setAttribute("Price", 100);
  $rooms->appendChild($node);
  $text = $outxmldoc->createTextNode("Single");
  $node->appendChild($text);
  
  $node = $outxmldoc->createElement("Room");
  $node->setAttribute("Price", 200);
  $rooms->appendChild($node);
  $text = $outxmldoc->createTextNode("Double");
  $node->appendChild($text);

  $handler->sendXMLResponse($outxmldoc);
  
  return true;
});
?>