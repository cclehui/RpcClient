<?php

require_once __DIR__ . '/../vendor/autoload.php';


$rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();

//设置log 对象
//$rpc_client::setLogInstance(new \CClehui\RpcClient\RpcLogEcho());

//环境变量用来构造 rpc_trace_id
$rpc_client::setEnvValue("argv", $argv);

$url = 'http://www.baidu.com';
$url = 'http://open.chenlehui.babytree-dev.com/meitun/test_handle';
$params = [];
$promises = [];

for ($i = 1; $i <= 2; $i++) {
    $promises[$i] = $rpc_client->callRemote($url, $params, 'GET', [], true);
}

$result_list = \GuzzleHttp\Promise\settle($promises);//->wait();

echo get_class($result_list);die;

foreach($result_list as $key => $item) {
    $response = $item['value'];

    $response = (string)$response->getBody();
    echo $response . "\n";
}