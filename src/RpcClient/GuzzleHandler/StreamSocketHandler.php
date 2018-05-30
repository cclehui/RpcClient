<?php

namespace CClehui\RpcClient\GuzzleHandler;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class StreamSocketHandler
 * HttpHandler 使用 stream_socket 做数据处理
 * @package CClehui\RpcClient\GuzzleHandler
 */
class StreamSocketHandler {

    const EOL = "\r\n";

    /**
     *  默认的连接超时时间
     */
    const CONNECT_TIMEOUT = 5;

    /*
     * 默认的写数据超时时间
     */
    const WRITE_TIMEOUT = 5;

    const SOCKET_NON_BLOCK = 0;//非阻塞

    const SOCKET_BLOCK = 1;//阻塞

    /**
     * @var null | resource
     */
    protected $stream_socket = null;

    public function __construct() {

    }

    public function __destruct() {
        if ($this->stream_socket) {
            stream_socket_shutdown($this->stream_socket, STREAM_SHUT_WR);
        }
    }

    public function __invoke(RequestInterface $request, array $options) {

        // Sleep if there is a delay specified.
        if (isset($options['delay'])) {
            usleep($options['delay'] * 1000);
        }

        $startTime = isset($options['on_stats']) ? microtime(true) : null;

        try {
            // Does not support the expect header.
            $request = $request->withoutHeader('Expect');

            // Append a content-length header if body size is zero to match
            // cURL's behavior.
            if (0 === $request->getBody()->getSize()) {
                $request = $request->withHeader('Content-Length', 0);
            }

            $send_res = $this->sendRequest($request, $options);

//            echo "yyyyyyyyyyyy\n";die;

            $stream_socket = $this->stream_socket;

            return new Promise(
                function () use ($stream_socket) {
                    $this->handleResponse($stream_socket);
            }
            );


        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Determine if the error was a networking error.
            $message = $e->getMessage();
            // This list can probably get more comprehensive.
            if (strpos($message, 'getaddrinfo') // DNS lookup failed
                || strpos($message, 'Connection refused')
                || strpos($message, "couldn't connect to host") // error on HHVM
                || strpos($message, "connection attempt failed")
            ) {
                $e = new ConnectException($e->getMessage(), $request, $e);
            }
            $e = RequestException::wrapException($request, $e);
            $this->invokeStats($options, $request, $startTime, null, $e);

            return \GuzzleHttp\Promise\rejection_for($e);
        }

    }

    public function handleResponse($stream_socket) {
        if (!$stream_socket || !is_resource($stream_socket)) {
            throw new \Exception("handleResponse param error");
        }

        echo "hhhhhhhhhhhhhhhhh\n";

        stream_set_blocking($stream_socket,self::SOCKET_BLOCK);
        $write_timeout = 3;//cclehui_test
        stream_set_timeout($this->stream_socket, $write_timeout);

        $response = stream_get_contents($stream_socket);
//        $response = http_chunked_decode(stream_get_contents($stream_socket));
//        $response = stream_socket_recvfrom($stream_socket, 2);

//        echo http_chunked_decode($response);

//        print_r(stream_get_meta_data($stream_socket));

        echo "xxxxxxxxxxxxxxxxxxx\n";

        echo $response;die;

    }


    /**
     * @param RequestInterface $request
     * @param array $options
     *
     * 发送请求非阻塞
     * @return bool
     */
    private function sendRequest(RequestInterface $request, array $options) {

        $target = "tcp://";
        $target .= $request->getUri()->getHost();
        $target .= ":" .  ($request->getUri()->getPort() ? : 80);

        $timeout = isset($options['connect_timeout']) ? : self::CONNECT_TIMEOUT;

        $error_no = $error_str = null;
        $this->stream_socket = stream_socket_client($target, $error_no, $error_str, $timeout);

        if (!$this->stream_socket) {
            throw new ConnectException("Connection error: $error_no, $error_str", $request);
        }

        $http_data = sprintf(
            "%s %s HTTP/%s" . self::EOL,
            strtoupper($request->getMethod()),
            $request->getRequestTarget(),
            $request->getProtocolVersion()
        );

        $headers = $request->getHeaders();
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // set content-length if not set
        if (!$request->hasHeader('Content-Length') && $body->getSize() > 0) {
            $headers['Content-Length'] = [$body->getSize()];
        }

        $headers['Connection'] = ['close'];

        foreach ($headers as $key => $values) {
            $value = implode(', ', $values);
            $http_data .= "{$key}: {$value}" . self::EOL;
        }

        $http_data .= self::EOL . $body->getContents() . self::EOL;

//        $write_timeout = 3;//cclehui_test
//        stream_set_timeout($this->stream_socket, $write_timeout);

        $res_int = stream_socket_sendto($this->stream_socket, $http_data);

        if ($res_int == -1) {
            throw new RequestException("send request error:$res_int", $request);
        }

        stream_set_blocking($this->stream_socket, self::SOCKET_NON_BLOCK);

        return true;
    }

    private function invokeStats(
        array $options,
        RequestInterface $request,
        $startTime,
        ResponseInterface $response = null,
        $error = null
    ) {
        if (isset($options['on_stats'])) {
            $stats = new TransferStats(
                $request,
                $response,
                microtime(true) - $startTime,
                $error,
                []
            );
            call_user_func($options['on_stats'], $stats);
        }
    }



}