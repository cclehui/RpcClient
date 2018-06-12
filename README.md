## 适用场景
在微服务和服务化的场景下，项目之间存在很多的远程调用场景，在一次请求中存在多次的远程rpc调用，
这种远程调用有的是基于tcp的，有的是基于http的， 为了提升调用性能，我们必须支持异步并发调用，
同时，我们还必须跟踪每次调用的调用链。RpcClient正好解决用来解决这两个问题。

## 安装
直接通过composer安装

## 功能和使用说明
### 基于http协议的rpc调用
HttpRpcClientUtil 在guzzle promise的基础上提供了同步和异步的http 远程调用类封装，在log中
收集了调用的信息，可以用于调用链的性能分析

```php

//配置不同的handler来处理
$config = [
//        'handler' => new \GuzzleHttp\Handler\StreamHandler(),
//    'handler' => new \CClehui\RpcClient\GuzzleHandler\StreamSocketHandler(),
    'handler' => new \CClehui\RpcClient\GuzzleHandler\SocketHandler(),
];

//同步调用demo
$url = 'http://0.0.0.0/temp/test.php;
$params = [];
$rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();
$rpc_client->setGuzzleClientConfig($config);
$res = $rpc_client->callRemote($url, $params);

//异步调用demo (promise机制)
$url = 'http://0.0.0.0/temp/test.php';
$params = [];
$promises = [];
$rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();();
$rpc_client->setGuzzleClientConfig($config);

for ($i = 1; $i <= 2; $i++) {
    $promises[$i] = $rpc_client->callRemote($url, $params, 'GET', [], true);
}

$result_list = \GuzzleHttp\Promise\settle($promises)->wait();

foreach($result_list as $key => $item) {
    $response = $item['value'];
    
    $response = (string)$response->getBody();
    echo $response . "\n";
}
```

更详细的使用demo在 examples\HttpRpcDemo.php中

### StreamSocketHandler
见demo中的config配置， 可以配置该handler

### SocketHandler
见demo中的config配置， 可以配置该handler
