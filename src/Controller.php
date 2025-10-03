<?php
declare(strict_types=1);

namespace laocc\rpc;

use esp\error\Error;

abstract class Controller extends \esp\core\Controller
{

    /**
     * @return array|void|null
     * @throws Error
     */
    public function _initPost()
    {
        if (!defined('_RpcToken')) throw new Error('未定义 _RpcToken');

        $timestamp = getenv('HTTP_TIMESTAMP');
        $now = microtime(true);
        if ($now - floatval($timestamp) > 5) {
            return ['error' => 1, 'message' => "两台服务器时间差5秒以上，本服务器当前={$now}，客户端={$timestamp}"];
        }
        if (_RpcToken === 'not_check') return null;//不检查签名

        $mchKey = getenv('HTTP_KEY');
        $sign = getenv('HTTP_SIGN');
        $md5 = md5($mchKey . $timestamp . _RpcToken . 'RpcSalt@EspCore');

        if ($md5 !== $sign) {
            return ['error' => 1, 'message' => "Rpc签名错误"];
        }

    }

    public function _init()
    {
        return 400;
    }


}