<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/9
 * Time: 20:02
 */

namespace CClehui\RpcClient\GuzzleHandler;


class HttpUtil {

    const EOL = "\r\n";

    /**
     * @param $data string
     *  http chunked 数据decode
     * @return string
     */
    public static function httpChunkedDecode($data) {
        $pos = 0;
        $temp = '';
        $total_length = strlen($data);
        while ($pos < $total_length) {

            // chunk部分(不包含CRLF)的长度,即"chunk-size [ chunk-extension ]"
            $len = strpos($data,self::EOL, $pos) - $pos;

            // 截取"chunk-size [ chunk-extension ]"
            $str = substr($data, $pos, $len);

            // 移动游标
            $pos += $len + 2;
            // 按;分割,得到的数组中的第一个元素为chunk-size的十六进制字符串
            $arr = explode(';', $str,2);

            // 将十六进制字符串转换为十进制数值
            $len = hexdec($arr[0]);

            // 截取chunk-data
            $temp .=substr($data, $pos, $len);

            // 移动游标
            $pos += $len + 2;
        }

        return $temp;
    }


}