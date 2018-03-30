<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/30
 * Time: 20:57
 */

namespace CClehui\RpcClient;


class RpcLogEcho extends RpcLogAbstract {

    public function log($rpc_trace_id, $log_str) {

        echo date("Y-m-d H:i:s") . "\t" . $rpc_trace_id , "\t" , $log_str . "\n";

    }

}