<?php

namespace CClehui\RpcClient\GuzzleHandler;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class StreamSocketHandler
 * HttpHandler 使用 stream_socket 做数据处理
 * @package CClehui\RpcClient\GuzzleHandler
 */
class StreamSocketHandler {

    /**
     *  默认的连接超时时间
     */
    const CONNECT_TIMEOUT = 5;

    const TIMEOUT = 3;

    const SOCKET_NON_BLOCK = 0;//非阻塞

    const SOCKET_BLOCK = 1;//阻塞

    /**
     * @var null | StreamSocket
     */
    protected $stream_socket = null;

    public function __construct() {

    }

    public function __destruct() {
        if ($this->stream_socket) {
            $this->stream_socket->close();
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

            $stream_socket = $this->sendRequest($request, $options);

//            $stream_socket = $this->stream_socket;

            $promise =  new Promise(
                function () use ($stream_socket, &$promise) {
                    try {
                        $response = $this->handleResponse($stream_socket);
                        $promise->resolve($response);

                    } catch (\Exception $e) {
                        $promise->reject($e);
                    }
            }
            );

            return $promise;


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

    public function handleResponse(StreamSocket $stream_socket) {
        $stream = $stream_socket->getStream();

        if (!$stream || !is_resource($stream)) {
            throw new \Exception("handleResponse param error");
        }
        stream_set_blocking($stream,self::SOCKET_BLOCK);
        $options = $stream_socket->getOptions();

        if (isset($options['timeout'])) {
            $sec = (int)$options['timeout'];
            $usec = ((int)($options['timeout'] * 1000000)) % 1000000;
            stream_set_timeout($stream, $sec, $usec);
        }

        $response = stream_get_contents($stream);

        $parts = explode(HttpUtil::EOL . HttpUtil::EOL, $response, 2);
        if (count($parts) !== 2) {
            throw new BadResponseException("Cannot create response from data", $stream_socket->getRequest());
        }

        list($headers, $body) = $parts;
        $headers = explode(HttpUtil::EOL, $headers);

        /// guzzle EasyHandle copy

        $startLine = explode(' ', array_shift($headers), 3);
        $headers = \GuzzleHttp\headers_from_lines($headers);
        $normalizedKeys = \GuzzleHttp\normalize_header_keys($headers);

        if (isset($normalizedKeys['content-encoding'])) {
            $headers['x-encoded-content-encoding']
                = $headers[$normalizedKeys['content-encoding']];
            unset($headers[$normalizedKeys['content-encoding']]);
            if (isset($normalizedKeys['content-length'])) {
                $headers['x-encoded-content-length']
                    = $headers[$normalizedKeys['content-length']];

                unset($headers[$normalizedKeys['content-length']]);
                $bodyLength = (int)strlen($body);
                if ($bodyLength) {
                    $headers[$normalizedKeys['content-length']] = $bodyLength;
                }
            }
        }

        foreach ($headers['Transfer-Encoding'] as $value) {
            if ($value == 'chunked') {
                $body = HttpUtil::httpChunkedDecode($body);
                break;
            }
        }

        return new Response(
            $startLine[1],
            $headers,
            $body,
            substr($startLine[0], 5),
            isset($startLine[2]) ? (string)$startLine[2] : null
        );
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

        $options['timeout'] = isset($options['timeout']) ? : self::TIMEOUT;
        $timeout = $options['timeout'];

        $start_ts = microtime(true);
        $error_no = $error_str = null;
        $stream = stream_socket_client($target, $error_no, $error_str, $timeout);

        if (!$stream) {
            throw new ConnectException("Connection error: $error_no, $error_str", $request);
        }

        $options['timeout'] = $options['timeout'] - (microtime(true) - $start_ts);
        if ($options['timeout'] < 0) {
            throw new RequestException("connect timeout", $request);
        }

        $http_data = sprintf(
            "%s %s HTTP/%s" . HttpUtil::EOL,
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
            $http_data .= "{$key}: {$value}" . HttpUtil::EOL;
        }

        $http_data .= HttpUtil::EOL . $body->getContents() . HttpUtil::EOL;

        stream_set_timeout($stream, $options['timeout']);
        $start_ts = microtime(true);

        $res_int = stream_socket_sendto($stream, $http_data);

        if ($res_int == -1) {
            throw new RequestException("send request error:$res_int", $request);
        }

        $options['timeout'] = $options['timeout'] - (microtime(true) - $start_ts);
        if ($options['timeout'] < 0) {
            throw new RequestException("send request timeout", $request);
        }

        stream_set_blocking($stream, self::SOCKET_NON_BLOCK);

        $this->stream_socket = new StreamSocket($stream, $request, $options);
//        stream_socket_shutdown($this->stream_socket, STREAM_SHUT_RD);

        return $this->stream_socket;
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