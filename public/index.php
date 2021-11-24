<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("../vendor/autoload.php");

$file = "./sites.json";
if(!file_exists($file)){
  http_response_code(500);
  echo "Nenhum site configurado";
  exit();
}
$sites = json_decode(file_get_contents($file));
if($sites == null || sizeof($sites) <= 0){
  http_response_code(500);
  echo "Nenhum site válido configurado";
  exit();
}

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Laminas\Diactoros\ServerRequestFactory;

$cachekey    = "";
$cachefile   = "";
$cacheroot   = dirname(dirname(__FILE__))."/cache/";
$cachefolder = "";
$currentSite = null;

foreach($sites AS $site){
  if($_SERVER["SERVER_NAME"] == $site->domain){
    $currentSite = $site;
    $cachekey = $site->forwardTo.$_SERVER["REQUEST_URI"];
    $cachefolder = $cacheroot.$site->folderName."/";
    
    $uri       = preg_replace("/[^a-zA-Z0-9\-\_]/","_",$_SERVER["REQUEST_URI"]);
    $cachefile = $cachefolder.$uri;
    if(file_exists($cachefile)){
      readfile($cachefile);
      exit();
    }
    break;
  }
} 

if($cachekey == ""){
  http_response_code(400);
  echo "Acesso inválido";
  exit();
}

// Create a PSR7 request based on the current browser request.
$request = ServerRequestFactory::fromGlobals();

// Create a guzzle client
$guzzle = new GuzzleHttp\Client();

// Create the proxy instance
$proxy = new Proxy(new GuzzleAdapter($guzzle));

// Add a response filter that removes the encoding headers.
$proxy->filter(new RemoveEncodingFilter());

try {
    // Forward the request and get the response.
    // http://origin.exemplo.com.br:3011
    $response = $proxy->forward($request)->to($currentSite->forwardTo);

    // gravando cache (somente para GET)
    if($_SERVER["REQUEST_METHOD"] == "GET"){
      @mkdir($cachefolder);
      file_put_contents($cachefile,$response->getBody());
    }

    // Output response to the browser.
    (new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);
} catch(\GuzzleHttp\Exception\BadResponseException $e) {
    // Correct way to handle bad responses
    (new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($e->getResponse());
}
?>