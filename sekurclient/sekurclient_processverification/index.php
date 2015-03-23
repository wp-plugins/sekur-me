<?php
/*
Process verifiaction response page from the SASS server.
Collecting the response and printing the response.
*/

if($_SERVER['REQUEST_METHOD']!='POST'){
    status_header('400');
    return;
}
header( 'Content-type: application/xml' );


CONST DELETE_INTERVAL = 2;

$response = file_get_contents('php://input');



//Append server reponse and sent to wordpress via curl
$response = "server_response=".$response;

  
$ch = curl_init();     
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
curl_setopt($ch, CURLOPT_POST,true);
curl_setopt($ch, CURLOPT_URL,"http://".$_SERVER['HTTP_HOST']."/wordpress/?action=sekur");

curl_setopt($ch, CURLOPT_POST, count($response));
curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
$response = curl_exec($ch);
curl_close($ch);
exit;
?>


