<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/30
 * Time: 20:48
 */

namespace CClehui\RpcClient;


abstract class RpcLogAbstract {

    public abstract function log($rpc_trace_id, $log_str);

}