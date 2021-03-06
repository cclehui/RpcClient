<?php

require_once __DIR__ . '/../vendor/autoload.php';

//配置 log输出instance
$logger_instance = new \Monolog\Logger("ccelhui_test");
//$log_handler = new \Monolog\Handler\StreamHandler(STDOUT, Monolog\Logger::WARNING);
$log_handler = new \Monolog\Handler\StreamHandler(STDOUT, Monolog\Logger::INFO);
$logger_instance->pushHandler($log_handler);
\CClehui\RpcClient\HttpRpcClientUtil::setLogInstance($logger_instance);

$request_num = 1;
$url = 'http://118.24.111.175/test.php'; // test.php中 sleep 1秒

$config = [
//        'handler' => new \GuzzleHttp\Handler\StreamHandler(),
    'handler' => new \CClehui\RpcClient\GuzzleHandler\StreamSocketHandler(),
//    'handler' => new \CClehui\RpcClient\GuzzleHandler\SocketHandler(),
];

//异步请求
$start_ts = microtime(true);
$async_result = HttpRpcDemo::AsyncHttpDemo($url, $argv, $request_num, $config);
$cost_time = microtime(true) - $start_ts;

echo "异步请求耗时:" . $cost_time . "\n";
//die;


//同步请求
$start_ts = microtime(true);
$async_result = HttpRpcDemo::HttpDemo($url, $argv, $request_num);
$cost_time = microtime(true) - $start_ts;
echo "同步请求耗时:" . $cost_time . "\n";


class HttpRpcDemo {


    /**
     * @param array $argv
     * @param int $num
     * @return array
     * @throws Exception
     */
    public static function HttpDemo($url, $argv = [], $num = 10, array $config = []) {

        $rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();
        $rpc_client->setGuzzleClientConfig($config);

        //环境变量用来构造 rpc_trace_id
        $rpc_client::setEnvValue("argv", $argv);

        $result = [];

        for ($i = 1; $i <= $num; $i++) {
            $url = $url ? $url : 'http://www.baidu.com';
            $params = [
                "temp" => $i,
            ];

            $result[$i] = $rpc_client->callRemote($url, $params, 'GET', [], false);
        }

        return $result;

    }


    //异步请求demo
    /**
     * @param array $argv
     * @param bool $is_async  是否异步请求
     * @return array
     * @throws Exception
     */
    public static function AsyncHttpDemo($url, $argv = [], $num = 10, array $config = []) {

        $rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();
        $rpc_client->setGuzzleClientConfig($config);
        $rpc_client->setTimeout(3);

        //环境变量用来构造 rpc_trace_id
        $rpc_client::setEnvValue("argv", $argv);

        $promises = [];

        //构建多个promise
        for ($i = 1; $i <= $num; $i++) {
            $url = $url ? $url : 'http://www.baidu.com';
            $params = [
                "temp" => $i,
            ];
            $promises[$i] = $rpc_client->callRemote($url, $params, 'GET', [], true);
        }

        //等待所有请求的完成
        $result = $rpc_client->getPromisesResponse($promises);
//        print_r($result);
        return $result;
//        $response_list = \GuzzleHttp\Promise\settle($promises)->wait();

//        $result = [];
//
//        foreach ($response_list as $key => $item) {
//
//
//            if (!isset($item['value'])) {
//                $result[$key] = null;
//
//            } else {
//                $response = $item['value'];
//                $result[$key] = (string)$response->getBody();
//            }
//        }
//
//
//        return $result;
    }

}