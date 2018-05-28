
<?php

require_once __DIR__ . '/../vendor/autoload.php';

//ini_set("allow_url_fopen", false);
use \GuzzleHttp\Promise\Promise;

use GuzzleHttp\Promise\FulfilledPromise;

$promise = new FulfilledPromise('value');

// Fulfilled callbacks are immediately invoked.
$promise->then(function ($value) {
    echo $value;
});

echo "xxxxxxx";sleep(3);die;

$promise = new \GuzzleHttp\Promise\Promise();

$promise = new Promise(function () use (&$promise) {
//    $promise->resolve('foo');
    throw new \Exception('foo');
});

// Calling wait will return the value of the promise.
echo $promise->wait(); // outputs "foo"
die;

$promise
    ->then(null, function ($reason) {
        return "It's ok";
    })
    ->then(function ($value) {
        echo $value . "\n";
        assert($value === "It's ok");
    });

$promise->reject('Error!');

die;

echo 'step1';
$promise = new \GuzzleHttp\Promise\Promise();
$promise->resolve("step 3");
$promise->then(function($param){echo $param;});

echo "step2\n";die;

$host = '118.24.111.175';

$config = [

    'handler' => new \GuzzleHttp\Handler\StreamHandler(),
    'handler' => new \psrebniak\GuzzleSocketHandler\SocketHandlerFactory($host,AF_INET, SOCK_STREAM, SOL_TCP),

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