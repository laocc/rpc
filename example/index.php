<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';

$url = 'http://rpc.kaibuy.top/server.php';

$cli = new \laocc\rpc\Client($url);
$cli->token = 'my token';
$cli->agent = 'pwd';

$v = $cli->register('laocc', 'pwd');

var_dump($v, time());
