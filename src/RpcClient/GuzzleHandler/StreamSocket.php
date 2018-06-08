<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/8
 * Time: 18:43
 */

namespace CClehui\RpcClient\GuzzleHandler;


use Psr\Http\Message\RequestInterface;

class StreamSocket {

    protected $stream = null;

    protected $request = null;

    protected $options = null;

    public function __construct($stream, RequestInterface $request, array $options) {
        $this->stream = $stream;
        $this->request =  $request;
        $this->options = $options;
    }

    public function close() {
        if ($this->stream) {
            stream_socket_shutdown($this->stream, STREAM_SHUT_WR);
        }
    }

    /**
     * @return null
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * @param null $stream
     */
    public function setStream($stream)
    {
        $this->stream = $stream;
    }

    /**
     * @return null|RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param null|RequestInterface $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @return array|null
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array|null $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }



}