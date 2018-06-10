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

        if (false === $connect_res) {
            $this->throwSocketException("Cannot connect socket to {$this->ip}:{$this->port}");
        }

        $this->timeout = $this->timeout - (microtime(true) - $start_ts);

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

        if (false === socket_write($this->socket, $message, strlen($message))) {
            $this->throwSocketException("Error occur when write to stream");
        }

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
        while ($partial = $this->readChunk($type, $chunkLength)) {
            $response .= $partial;
        }

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