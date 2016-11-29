<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';

$url = 'http://rpc.kaibuy.top/server.php';
//$url = 'http://rpc.kaibuy.top';
$cli = new \laocc\rpc\Client(true);
$cli->token = 'my token';

/**
 * 接收到数据时的回调
 * @param int $index #发送编号
 * @param array $value #返回的数据
 */
$success = function ($index, $value) {
    var_dump($index, $value);
};

/**
 * 出错时的回调
 * @param int $index #发送编号
 * @param int $err_no #错误代码
 * @param string $err_str #错误内容
 */
$error = function ($index, $err_no, $err_str) {
    var_dump($index, $err_no, $err_str);
};

$cli->append($url, 'test', [1, 2, 3], $success, $error);
$cli->append($url, 'test', [1, 2, 3]);

$cli->send($success, $error);

var_dump(time());
