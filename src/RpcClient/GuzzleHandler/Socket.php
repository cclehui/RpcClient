<?php

namespace CClehui\RpcClient\GuzzleHandler;

use Psr\Http\Message\RequestInterface;

class Socket {

    /**
     * @var resource|null socket instance
     */
    protected $socket = null;

    /**
     * @var int socket_create $domain parameter
     */
    protected $domain = AF_INET;

    /**
     * @var int socket_create $type parameter
     */
    protected $type = SOCK_STREAM;

    /**
     * @var int socket_create $type parameter
     */
    protected $protocol = SOL_SOCKET;

    protected $ip = null;

    protected $port = 80;

    /**
     * @var float|null
     */
    protected $timeout = 3;

    /**
     * Socket constructor.
     *
     * @param $ip string
     * @param int $port
     * @param array $options
     * @param int $domain
     * @param int $type
     * @param int $protocol
     */
    public function __construct($ip, $port = 80, array $options = [], $domain = AF_INET, $type = SOCK_STREAM, $protocol = SOL_TCP) {
        $this->ip = $ip;
        $this->port = $port ?: $this->port;
        $this->domain = $domain;
        $this->type = $type;
        $this->protocol = $protocol;

        $this->applyOptions($options);
    }

    /**
     * Socket destructor, call close method
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Create new socket if not exist
     *
     * @return $this
     * @throws SocketException;
     */
    public function create() {
        // if socket is already created - do nothing
        if (isset($this->socket)) {
            return $this;
        }

        $this->socket = socket_create($this->domain, $this->type, $this->protocol);

        //        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            $this->throwSocketException("Cannot create socket");
        }

        return $this;
    }

    /**
     * Connect to socket
     *
     * @return $this
     * @throws SocketException
     */
    public function connect() {
        $this->setSocketTimeOut(SO_SNDTIMEO);

        $start_ts = microtime(true);

        $connect_res = socket_connect($this->socket, $this->ip, $this->port);

        $this->costTime($start_ts);

        if (false === $connect_res) {
            $start_ts = microtime(true);

            $error_code = is_resource($this->socket) ? socket_last_error($this->socket) : socket_last_error();
            switch ($error_code) {
                case SOCKET_EINTR:
                case SOCKET_EINPROGRESS:
                    $readfs = array($this->socket);
                    $writefs = array($this->socket);
                    $excepts = NULL;

                    $timeout = $this->getSocketTimeOut();
                    $rt = socket_select($readfs, $writefs, $excepts, $timeout['sec'], $timeout['usec']);

                    if ($rt === false) {
                        $this->throwSocketException("socket_select connect socket fail {$this->ip}:{$this->port}");

                    } else if ($rt > 0) {
                        break;

                    } else {
                        $this->throwSocketException("socket_select connect timeout {$this->ip}:{$this->port}");
                    }
                    break;
                default:
                    $this->throwSocketException("cannot connect socket {$this->ip}:{$this->port}");
            }

            $this->costTime($start_ts);
        }

        if (!socket_set_nonblock($this->socket)) {
            $this->throwSocketException("socket_set_nonblock fail");
        }

        return $this;
    }

    /**
     * Write to socket
     * @param string $message
     *
     * @return $this
     * @throws SocketException
     */
    public function write($message) {
        if (!$this->socket) {
            throw new SocketException("Cannot write to empty socket.");
        }

        $start_ts = microtime(true);

        if (false === socket_write($this->socket, $message, strlen($message))) {
            $this->throwSocketException("Error occur when write to stream");
        }

        $this->costTime($start_ts);

        return $this;
    }

    /**
     * @param int $type socket_read $type parameter
     * @param int $chunkLength socket_read $length parameter
     *
     * @return string
     * @throws SocketException
     */
    public function readAll($type = PHP_BINARY_READ, $chunkLength = 65384) {
        if (!$this->socket) {
            throw new SocketException("Cannot read from empty socket");
        }

        socket_set_block($this->socket);
        $this->setSocketTimeOut(SO_RCVTIMEO);

        $response = "";
        do {
            $start_ts = microtime(true);

            $partial = socket_read($this->socket, $chunkLength, $type);

            if (false === $partial) {
                $error_code = socket_last_error($this->socket);

                if ($error_code == SOCKET_EINTR) {
                    //消费时间 超时抛异常
                    $this->costTime($start_ts);
                    continue;
                }

                $error_message = socket_strerror($error_code);
                socket_clear_error($this->socket);

                throw new SocketException("Error occur when read from stream, {$error_message}, $error_code");

            }

            if (!$partial) {
                break;
            }

            $response .= $partial;
            //消费时间 超时抛异常
            $this->costTime($start_ts);

        } while (true);

        return $response;
    }


    /**
     * @param int $type socket_read $type parameter
     * @param int $length socket_read $length parameter
     *
     * @return string
     * @throws SocketException
     */
    protected function readChunk($type, $length) {
        $partial = socket_read($this->socket, $length, $type);
        if (false === $partial) {
            $this->throwSocketException("Error occur when read from stream");
        }
        return $partial;
    }

    /**
     * Close socket
     *
     * @return $this
     */
    public function close() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }

        return $this;
    }

    protected function setSocketTimeOut($type) {
        $timeout = $this->getSocketTimeOut();
        if ($timeout['sec'] <= 0 && $timeout['usec'] <= 0) {
            $timeout['sec'] = 1;
        }
        if (!socket_set_option($this->socket, SOL_SOCKET, $type, $timeout)) {
            $this->throwSocketException("setSocketTimeOut error");
        }
    }

    protected function getSocketTimeOut() {
        $sec = (int)$this->timeout;
        $usec = ((int)($this->timeout * 1000000)) % 1000000;
        $timeout = array('sec' => $sec, 'usec' => $usec);
        return $timeout;
    }

    //消耗时间
    protected function costTime($start_ts, $now = null) {

        $now = $now ?: microtime(true);

        $this->timeout = $this->timeout - (microtime(true) - $start_ts);

        if ($this->timeout < 0) {
            $this->throwSocketException("timeout is less than 0");
        }
    }

    protected function applyOptions($options) {
        if (isset($options['timeout'])) {
            $this->timeout = (float)$options['timeout'];
        }
        return $this;
    }

    protected function throwSocketException($message) {

        if ($this->socket) {
            $last_error = socket_last_error($this->socket);
            $error_message = socket_strerror($last_error);

            socket_clear_error($this->socket);
            throw new SocketException("$message, {$error_message}, $last_error");

        } else {
            throw new SocketException("$message");
        }
    }


}