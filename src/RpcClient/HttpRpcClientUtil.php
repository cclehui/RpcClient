<?php
namespace CClehui\RpcClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\PromiseInterface;
use Monolog\Handler\Mongo;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
同步调用demo
$url = 'http://115.28.38.4/temp/test.php';
$params = [];
$rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();
$res = $rpc_client->callRemote($url, $params);

异步调用demo (promise机制)
$url = 'http://115.28.38.4/temp/test.php';
$params = [];
$promises = [];

$rpc_client = new \CClehui\RpcClient\HttpRpcClientUtil();

for ($i = 1; $i <= 2; $i++) {
$promises[$i] = $rpc_client->callRemote($url, $params, 'GET', [], true);
}

$result_list = \GuzzleHttp\Promise\settle($promises)->wait();

foreach($result_list as $key => $item) {
$response = $item['value'];

$response = (string)$response->getBody();
echo $response . "\n";
}

 */
class HttpRpcClientUtil {


    //rpc的 server request环境
    protected static $REQUEST = null;

    //rpc环境变量
    protected static $ENV = [];

    //trace id
    protected static $rpc_trace_id = null;

    //log instance
    protected static  $log_instance = null;

    protected  $timeout = 5; //调用接口总超时时间

    protected $guzzle_client = null;

    /**
     * @var array | array
     */
    protected $guzzle_client_config = []; // client的配置

    protected $pass_rpc_trace_id = true; //请求的时候是否带上rpc_trade_id

    public function __construct($rpc_trace_id = null) {
        //初始化 rpc trace id
        if (!self::$rpc_trace_id) {
            self::setRpcTraceId($rpc_trace_id);
        }
    }

    /**
     * setRpcTraceId
     * 重新设置rpc trace id
     *
     * @param mixed $rpc_trace_id
     * @static
     * @access public
     * @return void
     */
    public static function setRpcTraceId($rpc_trace_id) {
        if (!$rpc_trace_id) {
            $rpc_trace_id = self::getUniqRpcTraceId();
        }

        self::$rpc_trace_id = $rpc_trace_id;

        return self::$rpc_trace_id ;
    }

    /**
     * getRpcTraceId
     * 获取rpc trace id
     *
     * @static
     * @access public
     * @return void
     */
    public static function getRpcTraceId() {
        if (!self::$rpc_trace_id) {
            self::setRpcTraceId();
        }

        return self::$rpc_trace_id;
    }

    /**
     * setPassRpcTraceId
     * 请求的时候是否传递rpc_trace_id
     *
     * @param mixed $pass
     * @access public
     * @return void
     */
    public function setPassRpcTraceId($pass = true) {
        $this->pass_rpc_trace_id = $pass ? true : false;
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

    /**
     * setLogInstance
     * 设置log对象
     *
     * @param $log_instance
     */
    public static function setLogInstance(Logger $log_instance) {
        self::$log_instance = $log_instance;
    }


    //设置超时时间
    public function setTimeout($timeout) {
        $this->timeout = max(intval($timeout), 1);
    }

    /**
     * @param array $config
     */
    public function setGuzzleClientConfig(array $config) {
        $this->guzzle_client_config = $config;
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

        $rpc_trace_id = null;

        //调用链跟踪
        if (self::$REQUEST && method_exists(self::$REQUEST, 'getString')) {

            $rpc_trace_id = self::$REQUEST->getString('rpc_trace_id');

        } else if (isset($_REQUEST['rpc_trace_id'])) {
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

        //不传递rpc_trace_id
        if (!$this->pass_rpc_trace_id) {
            unset($params['rpc_trace_id']);
        }

        $start_ts = microtime(true);

        $log_prefix = "url:$url, method:$method, params: " . json_encode($params) . ", headers:" . json_encode($headers) . ", ";

        try {
            $request_options['timeout'] = $this->timeout;
            $request_options['http_errors'] = false;
            $request_options['headers'] = $headers;

            switch ($method) {
                case 'POST' :
                    switch ($headers['Content-Type']) {
                        case 'application/json':
                            $request_options['json'] = $params;
                            break;
                        default:

                            $request_options['form_params'] = $params;
                            break;
                    }

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

    /**
     * @param array $promises
     * @return array
     */
    public function getPromisesResponse(array $promises) {
        if (!$promises) {
            return [];
        }

        $result = [];

        $need_wait_promises = [];

        foreach ($promises as $key => $value) {

            if ($value instanceof PromiseInterface) {
                $need_wait_promises[$key] = $value;

            } else {
                $result[$key] = $value;
            }
        }

        if ($need_wait_promises) {
            $promise_results = \GuzzleHttp\Promise\settle($need_wait_promises)->wait();

            foreach ($promise_results as $key => $item) {
                //$result[$key] = (string)$item['value']->getBody();
                $result[$key] = $item['value']; //\GuzzleHttp\Psr7\Response
                //$result[$key] = $item;
            }
        }

        return $result;
    }

    //获取guzzle的 client
    protected function getGuzzleClient() {

        if (!$this->guzzle_client) {
            $this->guzzle_client = new Client($this->guzzle_client_config);
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

        if (!self::$log_instance) {
            self::$log_instance = new Logger("180403_rpc_log");

            $default_log_handler = new StreamHandler(STDOUT, Logger::INFO);

            self::$log_instance->pushHandler($default_log_handler);
        }


        self::$log_instance->info("rpc_trace_id:" . $rpc_trace_id . ", " . $log_str);

    }

}
