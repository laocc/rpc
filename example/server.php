<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';


class UserModel
{
    /**
     * 用户注册
     * @param $username
     * @param $password
     * @return string
     */
    public function registerAction($username, $password)
    {
        //业务操作
        return "{$username}注册成功:{$password}";
    }

    /**
     * 用户登录
     * @param $username
     * @param $password
     */
    public function loginAction($username, $password)
    {
        //业务代码
        return [$username, $password];
    }

    public function testAction($time)
    {
        return getmypid() . '/' . (microtime(true) - $time) * 1000;
    }

}

$sev = new \laocc\rpc\Server(new UserModel());
$sev->action = 'Action';
$sev->token = 'my token';
$sev->password = 'pwd';
$sev->agent = 'pwd';
//$sev->shield(['loginAction']);
$sev->listen();