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
 * Class SocketHandler
 * HttpHandler 使用 socket 扩展 做数据处理
 * @package CClehui\RpcClient\GuzzleHandler
 */
class SocketHandler {

    const EOL = "\r\n";

    /**
     *  默认的连接超时时间
     */
    const CONNECT_TIMEOUT = 5;

    const TIMEOUT = 3;

    const SOCKET_NON_BLOCK = 0;//非阻塞

    const SOCKET_BLOCK = 1;//阻塞

    public function __construct() {

    }

    public function __destruct() {
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

            $socket = $this->sendRequest($request, $options);

            $promise = new Promise(
                function () use ($socket, $request, $options, &$promise) {
                    try {
                        $response = $this->handleResponse($socket, $request, $options);
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

    public function handleResponse(Socket $socket, RequestInterface $request, array $options = []) {
        if (!$socket) {
            throw new \Exception("handleResponse param error");
        }

        $response = $socket->readAll();
        $socket->close();

        $parts = explode(self::EOL . self::EOL, $response, 2);
        if (count($parts) !== 2) {
            throw new BadResponseException("Cannot create response from data", $request);
        }

        list($headers, $body) = $parts;
        $headers = explode(self::EOL, $headers);

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

        $host = $request->getUri()->getHost();
        $port = $request->getUri()->getPort() ?: 80;

        $socket = new Socket($host, $port, $options);

        $socket->create()->connect();

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

        $socket->write($http_data); // non block

        return $socket;
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