<?php
class sekurme_helper {
   //generating and returns the XML data
   static function xml_generator($data,$root){
      $xml = new DOMDocument("1.0","utf-8");
      $rootElement = $xml->createElement($root);
      foreach($data as $id => $val){
         $rootElement->appendChild($xml->createElement($id,$val));
      }
      $xml->appendChild($rootElement);
    
   
      return $xml->saveXml();
   }
    
   static function _process_response($response){
        
     $xml = new DOMDocument();
     $pattern = '/<\?xml version=[^>]+>/';
     $output = preg_replace($pattern, '', $response);
    	
     $result = $xml->loadXML($output);
	
     $data =array();
     foreach($xml->childNodes as $nodes){          
       foreach($nodes->childNodes as $node){
	    $data[$node->nodeName] = $node->nodeValue;
         }                 
      }
   
     return $data ;        
   }
   //Start transaction and making cURL request to the server. 
   static function start_trac($data,$host,$req_root){
        
      $ch = curl_init();
      $xml_data = self::xml_generator($data,$req_root);
      $header  = array(
		"Content-type: application/xml",		
		"Content-transfer-encoding: text",
		"Connection: close"
	    );          
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
      curl_setopt($ch, CURLOPT_POST,true);
      curl_setopt($ch, CURLOPT_URL,$host."/MT/SekurServer_StartTransaction");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_TIMEOUT, 4);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
      $response = curl_exec($ch);
      return self::_process_response($response);  
    }
  
}

?>
