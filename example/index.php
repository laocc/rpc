<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';

$url = 'http://rpc.kaibuy.top/server.php';

$cli = new \laocc\rpc\Client($url);
//$cli->sign = 2;
$cli->token = 'myToken';
$cli->agent = 'pwd';

$v = $cli->register('laocc', 'pwd');

if ($v instanceof \Error) {
    echo '错了';
}


if (is_object($v)) {
    echo '真的错了';
}

print_r($v);
