<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';


$url = 'http://rpc.kaibuy.top/server.php';
//$url = '/server.php';

$cli = new \laocc\rpc\Client($url);

$cli->set([
    'token' => 'myToken',
    'agent' => 'myAgent',
    'sign' => 2,
    'fork' => false,
]);

$time = microtime(true);


$success = function ($index, $value) {
    if ($value instanceof \Error) {
        print_r($value);
    } else {
        print_r(['index' => $index, 'value' => $value]);
    }
};

$str = str_repeat('中华人民共和国', mt_rand(10, 20));

$cli->task('http://rpc.kaibuy.top/server.php?task', 'test', [1, $str], $success);
$cli->call('http://rpc.kaibuy.top/server.php?call', 'test', [2]);
//$cli->task($url, 'test', [1], $success);
//$cli->task($url, 'test', [2]);

$cli->send($success);


