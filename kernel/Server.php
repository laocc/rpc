<?php
namespace laocc\rpc;

class Server
{
    private $_server;
    private $_option = [];
    private $_shield = [];
    private $_bind = [];

    private $_form_key = [
        'action' => 'action',
        'sign' => 'sign',
        'data' => 'data',
        'type' => 'type',
    ];

    public function __construct($sev)
    {
        if (!is_object($sev)) throw new \Exception('服务器对象须为类实例');
        $this->_server = $sev;
    }

    /**
     * 显示async服务端所有可用接口
     * @param $object
     */
    private function display_server($trace)
    {
        if (!is_object($this->_server)) return;
        $fun = [];
        $class = get_class($this->_server);
        foreach (get_class_methods($class) as $method) {
            if ($this->action and !preg_match("/.+{$this->action}$/i", $method)) continue;
            if (strpos($method, '_') !== 0 and !in_array($method, $this->_shield))
                $fun[$method] = $method;
        }
        if (empty($fun)) return;

        $self = get_class() . '/listen';
        $file = null;
        foreach ($trace as $trc) {
            if (!isset($trc['object'])) continue;

            if (!is_null($file)) {//已过了入口，查找最终调用者
                if (get_class($trc['object']) === $class) {
                    if (isset($trc['file'])) $file = $trc['file'];//记录最新调用者
                }
                if (get_class($trc['object']) !== $class) {
                    break;//不是调用者，返回最近调用者
                }
                continue;//得到过文件名
            }

            if ("{$trc['class']}/{$trc['function']}" === $self and isset($trc['file'])) {
                $file = $trc['file'];//进入本程序的入口
            }
            if (get_class($trc['object']) === $class) {
                $file = $trc['file'];//调用接口者
                break;
            }//不是真实调用者，继续查找
        }

        if (is_null($file)) return;
        $code = file_get_contents($file);
        if (empty($code)) return;
        $html = <<<HTML
<!DOCTYPE html><html lang="zh-cn"><head>
    <meta charset="UTF-8">
    <style>
    body {margin: 0;padding: 0;font-size: %s;color:#333;font-family:"Source Code Pro", "Arial", "Microsoft YaHei", "msyh", "sans-serif";}
div.nav{width:100%;height:2em;line-height:2em;font-size:2em;background:#17728A;color:#eee;text-indent:1em;border-bottom:2px solid #FFD82F;}
div.item{clear:both;margin:1em;}
div.head{width:100%;height:2em;line-height:2em;background:#50AC74;color:#fff;text-indent:1em;}
pre{margin:0;text-indent:2em;background: #F4FFFC;border:1px solid #50AC74;padding:0.5em 0;}
form{margin:5em;}
label{float:left;height:28px;line-height:28px;}
input{float:left;padding:0;margin:0;border: 1px solid #333;}
input[type=text]{width:10em;height:26px;}
input[type=submit]{width:5em;border-left:0;height:28px;background:#17728A;color:#fff;}
input[type=submit]:hover{background:#17588A;color:#f00;}
h3{width:100%;text-align:center;margin-top:10em;}
</style>
    <title>接口参数</title>
</head><body>
HTML;
        echo $html, "<div class='nav'>{$class} Interface</div>";

        if (is_null($this->password)) {
            echo '<h3>当前接口未设置查看密码，不能查看接口信息。请在创建async服务器时指定密码，如：$async->password = \'password\';</h3>';
            return;
        }

        if (!isset($_GET['pwd']) or $_GET['pwd'] !== $this->password) {
            $form = <<<HTML
<form action="?" method="get">
<label for="pwd">请输入接口查看密码：</label>
<input type="text" name="pwd" id="pwd" value="" autocomplete="off">
<input type="submit" value="进入">
</form>
HTML;
            echo $form, (isset($_GET['pwd']) ? '<label style="color:red;margin-left:2em;">密码错误</label>' : '');
            return;
        }

        preg_match_all('/(\/\*\*(?:.+?)\*\/).+?function\s+(\w+?)\((.*?)\)/is', $code, $match, PREG_SET_ORDER);
        foreach ($match as $item) {
            if (!in_array($item[2], $fun)) continue;
            echo "<div class='item'><div class='head'>{$item[2]}({$item[3]})</div><pre>{$item[1]}</pre></div>";
        }

        echo '</body></html>';
    }


    /**
     * 服务器端侦听请求
     */
    public function listen()
    {
        if (getenv('REQUEST_METHOD') === 'GET') {
            $this->display_server(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS));
            exit;
        }

        if (empty($_POST)) exit();

        $action = isset($_POST[$this->_form_key['action']]) ? $_POST[$this->_form_key['action']] : null;
        $data = isset($_POST[$this->_form_key['data']]) ? $_POST[$this->_form_key['data']] : null;
        $sign = isset($_POST[$this->_form_key['sign']]) ? $_POST[$this->_form_key['sign']] : null;
        $type = isset($_POST[$this->_form_key['type']]) ? $_POST[$this->_form_key['type']] : 'php';

        if (!$this->check_agent()) exit($this->data_encode($type, '认证失败'));

        $this->sign_check(getenv('HTTP_HOST'), $_POST, $type);

        if (empty($data) or is_null($action) or is_null($sign)) exit($this->data_encode($type, '无数据传入'));
        if (strpos($action, '_') === 0) exit($this->data_encode($type, '禁止调用系统方法'));
        $action .= $this->action;
        if (in_array($action, $this->_shield)) exit($this->data_encode($type, "当前服务端{$action}方法不可用"));

        if (!method_exists($this->_server, $action) or !is_callable([$this->_server, $action])) {
            exit($this->data_encode($type, "当前服务端不存在{$action}方法"));
        }

        $data = $this->data_decode($type, $data);
        if (!is_array($data)) $data = [$data];


        ob_start();
        $v = $this->_server->{$action}(...$data + array_fill(0, 10, null));
        if (!is_null($v)) {//优先取return值
            ob_end_clean();
            echo $this->data_encode($type, $v);
        } else {
            $v = ob_get_contents();
            ob_end_clean();
            echo $this->data_encode($type, $v);
        }
        ob_flush();
        exit;
    }


    private function check_agent()
    {
//        var_dump($this->agent);
        $ip = getenv('REMOTE_ADDR');
        $agent = getenv('HTTP_USER_AGENT');
        if (!$agent) return false;
        if (!$this->agent) return true;
        return $this->agent === $agent;
    }

    public function shield($action)
    {
        if (is_string($action)) $action = explode(',', $action);
        if (is_array($action)) $this->_shield = $action;
    }

    public function bind($ip)
    {
        if (is_string($ip)) $ip = explode(',', $ip);
        if (is_array($ip)) $this->_bind = $ip;
    }

    private function data_encode($type, $val)
    {
        if (strtolower($type) === 'json') {
            return is_array($val) ? json_encode($val, 256) : $val;
        } else {
            return serialize($val);
        }
    }

    private function data_decode($type, $val)
    {
        if (strtolower($type) === 'json') {
            $arr = json_decode($val, true);
            return is_array($arr) ? $arr : $val;
        } else {
            return unserialize($val);
        }
    }

    private function sign_check($host, $arr, $type)
    {
        ksort($arr);
        $host .= $this->token;
        foreach ($arr as $k => $v) {
            if ($k !== $this->_form_key['sign']) $host .= "&{$k}=$v";
        }
        if (!hash_equals(md5($host), $arr['sign'])) exit($this->data_encode($type, 'TOKEN验证失败'));
    }

    public function set(string $name, $value)
    {
        $this->_option[$name] = $value;
    }

    public function get($name)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : null;
    }

    public function __set(string $name, $value)
    {
        $this->_option[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : null;
    }

}