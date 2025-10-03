<?php
declare(strict_types=1);

namespace laocc\rpc;

use esp\error\Error;
use esp\helper\library\request\Post;
use esp\helper\library\Result;
use esp\core\Controller as CoreController;

abstract class Controller extends CoreController
{
    protected Post $post;
    protected Result $result;

    /**
     * @return array|void|null
     * @throws Error
     */
    public function _initPost()
    {
        if (!defined('_RpcToken')) define('_RpcToken', '_RpcToken');

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

        if ($this->_request->controller === '_rpc_check_') {
            return ['error' => 0, 'message' => "Rpc Success"];
        }

        $this->post = new Post();
        $this->result = new Result();
    }

    public function _close($contReturn)
    {
        if (!$this->getRequest()->isPost()) return null;

        if (is_string($contReturn)) return $this->result->error($contReturn)->display();
        else if ($contReturn instanceof Result) return $contReturn->display();
        else return $this->result->display();

    }

    public function _init()
    {
        return 400;
    }


}