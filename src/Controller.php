<?php
declare(strict_types=1);

namespace laocc\rpc;

abstract class Controller extends \esp\core\Controller
{
    protected string $token = '';

    public function _init()
    {
        $mchKey = getenv('HTTP_MCHID');
        $timestamp = getenv('HTTP_TIMESTAMP');
        $sign = getenv('HTTP_SIGN');
    }


}