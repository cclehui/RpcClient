
<?php

require_once __DIR__ . '/../vendor/autoload.php';

//ini_set("allow_url_fopen", false);

//use \GuzzleHttp\Promise\Promise;

$host = '118.24.111.175';

$config = [

//    'handler' => new \GuzzleHttp\Handler\StreamHandler(),
//    'handler' => new \CClehui\RpcClient\GuzzleHandler\StreamSocketHandler(),
    'handler' => new \CClehui\RpcClient\GuzzleHandler\SocketHandler(),

];

//print_r(stream_get_wrappers());die;

$client = new GuzzleHttp\Client($config);

$url = "http://118.24.111.175/test.php";
//$url = "http://open.chenlehui.babytree-dev.com/user/test";

$params = [
    "aaaaaaaaaaa" => "1111111"
];

$options = [
    'form_params' => $params,
//    'body' => $params,
];

$response = $client->post($url, $options);

//echo get_class($response->getBody()) . "\n";

echo ((string)$response->getBody()) . "\n";

$response = $client->post($url, $options);

//echo get_class($response->getBody()) . "\n";

echo ((string)$response->getBody()) . "\n";

class MyHandler {

}