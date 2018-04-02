<?php

require_once __DIR__ . '/../vendor/autoload.php';

$request_num = 2;
$url = 'http://open.chenlehui.babytree-dev.com/meitun/test_handle';

//异步请求
$start_ts = microtime(true);
$async_result = HttpRpcDemo::AsyncHttpDemo($url, $argv, $request_num);
$cost_time = microtime(true) - $start_ts;

echo "异步请求耗时:" . $cost_time . "\n";

//print_r($async_result);

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
    public static function HttpDemo($url, $argv = [], $num = 10) {

        $rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();

        //设置log 对象
        $rpc_client::setLogInstance(new \CClehui\RpcClient\RpcLogEcho());

        //环境变量用来构造 rpc_trace_id
        $rpc_client::setEnvValue("argv", $argv);

        $result = [];

        for ($i = 1; $i <= $num; $i++) {
            $url = $url ? $url : 'http://www.baidu.com';
            $params = [];
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
    public static function AsyncHttpDemo($url, $argv = [], $num = 10) {

        $rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();

        //设置log 对象
        $rpc_client::setLogInstance(new \CClehui\RpcClient\RpcLogEcho());

        //环境变量用来构造 rpc_trace_id
        $rpc_client::setEnvValue("argv", $argv);

        $promises = [];

        //构建多个promise
        for ($i = 1; $i <= $num; $i++) {
            $url = $url ? $url : 'http://www.baidu.com';
            $params = [];
            $promises[$i] = $rpc_client->callRemote($url, $params, 'GET', [], true);
        }

        //等待所有请求的完成
        $response_list = \GuzzleHttp\Promise\settle($promises)->wait();

        $result = [];

        foreach ($response_list as $key => $item) {


            if (!isset($item['value'])) {
                $result[$key] = null;

            } else {
                $response = $item['value'];
                $result[$key] = (string)$response->getBody();
            }
        }

        return $result;
    }

}