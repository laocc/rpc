<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', dirname(__DIR__));
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
        echo json_encode(["{$username}注册成功:{$password}"], 256);
//        return "{$username}注册成功:{$password}";
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

    public function testAction($id, $data)
    {
        $all = ((microtime(true) - $id) * 1000);

        $str = "time={$all},len=" . strlen($data) / 3;

        (new \Yac('time'))->set('state' . $id, json_encode($_SERVER));

        return [
            'len' => strlen($data) / 3,
//            'data' => $data,
            'time' => $all,
        ];


    }

}

$sev = new \laocc\rpc\Server(new UserModel());
$sev->action = 'Action';
$sev->token = 'myToken';
$sev->password = 'pwd';
$sev->agent = 'myAgent';
$sev->sign = 2;
//$sev->shield(['loginAction']);
$sev->listen();