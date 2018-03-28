<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;

/**
同步调用demo
$url = 'http://open.chenlehui.babytree-dev.com/meitun/test_handle';
$params = [];
$rpc_client = new util\RpcClientUtil();
$res = $rpc_client->callRemote($url, $params);

异步调用demo (promise机制)
$url = 'http://open.chenlehui.babytree-dev.com/meitun/test_handle';
$params = [];
$promises = [];

$rpc_client = new util\RpcClientUtil();

for ($i = 1; $i <= 2; $i++) {
$promises[$i] = $rpc_client->callRemote($url, $params, 'GET', [], true);
}

$result_list = \GuzzleHttp\Promise\settle($promises)->wait();

 */
class RpcClientUtil {


    //rpc的 server request环境
    protected static $REQUEST = null;

    //rpc环境变量
    protected static $ENV = [];

    //trace id
    protected static $rpc_trace_id = null;

    protected  $timeout = 5; //调用接口总超时时间

    protected $guzzle_client = null;

    public function __construct($rpc_trace_id) {
        //初始化 rpc trace id
        self::initSetRpcTraceId($rpc_trace_id);
    }

    /**
     * initSetRpcTraceId
     * 设置rpc trace id
     *
     * @param mixed $rpc_trace_id
     * @static
     * @access public
     * @return void
     */
    public static function initSetRpcTraceId($rpc_trace_id) {
        if (!$rpc_trace_id) {
            $rpc_trace_id = self::getUniqRpcTraceId();
        }

        self::$rpc_trace_id = $rpc_trace_id;
    }

    //设置rpc调用的 server端环境
    public static function setREQUEST($REQUEST) {
        self::$REQUEST = $REQUEST;
        self::$ENV['REQUEST'] = $REQUEST;
    }

    /**
     * setEnvValue
     * 设置环境变量
     *
     * @param mixed $key
     * @param mixed $value
     * @static
     * @access public
     * @return void
     */
    public static function setEnvValue($key, $value) {
        self::$ENV[$key] = $value;
    }


    //设置超时时间
    public function setTimeout($timeout) {
        $this->timeout = max(intval($timeout), 1);
    }

    /**
     * callRemote
     * 远程rpc 调用， 支持异步
     *
     * @param mixed $url
     * @param mixed $params
     * @param string $method
     * @param mixed $headers
     * @param mixed $is_async
     * @access public
     * @return void
     */
    public function callRemote($url, $params = [], $method = 'POST', $headers = [], $is_async = false) {
        if (!$url) {
            throw new \Exception("url 不能为空");
        }

        $client = $this->getGuzzleClient();

        //调用链跟踪
        if (self::$REQUEST) {
            $rpc_trace_id = self::$REQUEST->getString('rpc_trace_id');

        } else if ($_REQUEST['rpc_trace_id']) {
            $rpc_trace_id = $_REQUEST['rpc_trace_id'];
        }

        if (!$rpc_trace_id) {
            $rpc_trace_id = self::$rpc_trace_id;
        }

        if (!$rpc_trace_id) {
            self::setEnvValue("url", $url);
            self::setEnvValue("params", $params);
            $rpc_trace_id = self::getUniqRpcTraceId();
        }

        $params['rpc_trace_id'] = $rpc_trace_id;

        $start_ts = microtime(true);

        $log_prefix = "url:$url, method:$method, params: " . json_encode($params) . ", headers:" . json_encode($headers);

        try {
            $request_options['timeout'] = $this->timeout;
            $request_options['http_errors'] = false;
            $request_options['headers'] = $headers;

            switch ($method) {
                case 'POST' :
                    $request_options['form_params'] = $params;
                    if ($is_async) {//异步
                        $promise = $client->postAsync($url, $request_options);

                    } else {
                        $response = $client->post($url, $request_options);
                    }

                    break;
                case 'GET' :
                default :
                    $request_options['query'] = $params;
                    if ($is_async) {
                        $promise = $client->getAsync($url, $request_options);

                    } else {
                        $response = $client->get($url, $request_options);
                    }

                    break;
            }

            if ($is_async) {
                $promise->then(
                    function (\Psr\Http\Message\ResponseInterface $response) use($start_ts, $rpc_trace_id, $log_prefix) {
                        $res = (string)$response->getBody();

                        $end_ts = microtime(true);
                        $diff = $end_ts - $start_ts;
                        self::writeRpcLog($rpc_trace_id, "$start_ts, $end_ts, $diff, " . $log_prefix . "异步请求 success, response:$res");

                    },

                    function (\GuzzleHttp\Exception\RequestException $e) use($start_ts, $rpc_trace_id, $log_prefix) {

                        $end_ts = microtime(true);
                        $diff = $end_ts - $start_ts;
                        self::writeRpcLog($rpc_trace_id, "$start_ts, $end_ts, $diff, " . $log_prefix . "异步请求, error:" . $e->getMessage());
                    }
                );

                return $promise;

            } else {
                $res = (string)$response->getBody();

                $end_ts = microtime(true);
                $diff = $end_ts - $start_ts;
                self::writeRpcLog($rpc_trace_id, "$start_ts, $end_ts, $diff, " . $log_prefix . "同步请求 success, response:$res");

                return $res;
            }


        } catch (\Exception $e) {

            $end_ts = microtime(true);
            $diff = $end_ts - $start_ts;
            self::writeRpcLog($rpc_trace_id, "$start_ts, $end_ts, $diff, " . $log_prefix . "同步请求, error:" . $e->getMessage());

            throw $e;
        }

    }

    //获取guzzle的 client
    protected function getGuzzleClient() {

        if (!$this->guzzle_client) {
            $this->guzzle_client = new Client();
        }

        return $this->guzzle_client;
    }

    //获取rpc 调用的 唯一id
    public static function getUniqRpcTraceId() {

        $prefix = intval(microtime(true) * 1000) . "_" . crc32(json_encode(self::$ENV)) . "_";

        return uniqid($prefix);
    }

    //写log
    protected static function writeRpcLog($rpc_trace_id, $log_str) {
        $log_tag = "180328_rpc_log";

        LogCollectionUtil::write($log_tag, "rpc_trace_id:" . $rpc_trace_id . ", $log_str");
    }

}
