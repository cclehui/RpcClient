
<?php

require_once __DIR__ . '/../vendor/autoload.php';

//ini_set("allow_url_fopen", false);
//use \GuzzleHttp\Promise\Promise;

$host = '118.24.111.175';

$config = [

//    'handler' => new \GuzzleHttp\Handler\StreamHandler(),
    'handler' => new \CClehui\RpcClient\GuzzleHandler\StreamSocketHandler(),
//    'handler' => new \psrebniak\GuzzleSocketHandler\SocketHandlerFactory($host,AF_INET, SOCK_STREAM, SOL_TCP),

];

$client = new GuzzleHttp\Client($config);

$url = "http://118.24.111.175/test/test.php";

$params = [
    "aaaaaaaaaaa" => "1111111"
];

$options = [
    'form_params' => $params,
//    'body' => $params,
];

$response = $client->post($url, $options);

echo ((string)$response->getBody()) . "\n";


class MyHandler {

}