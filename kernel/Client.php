<?php
namespace laocc\rpc;

class Client
{
    private $_option = [];
    private $_url = [];
    private $_url_static;
    private $_index = 0;
    private $_isAsync = true;

    private $_form_key = [
        'action' => 'action',
        'sign' => 'sign',
        'data' => 'data',
        'type' => 'type',
    ];


    public function __construct($isAsync = true)
    {
        if (is_bool($isAsync) or is_int($isAsync)) {
            $this->_isAsync = boolval($isAsync);
        } elseif (is_string($isAsync)) {
            $this->_url_static = $isAsync;
        } else {
            throw new \Exception('若需要客户端请提供布尔型或一个网址URL参数');
        }
    }

    /**
     * 调用服务器端类方法的魔术方法
     */
    public function __call($name, $arguments)
    {
        $success = function ($index, $value) use (&$data) {
            $data = ($value);
        };
        $error = function ($index, $err_no, $err_str) {
            throw new \Exception($err_str, $err_no);
        };
        $this->_isAsync = false;

        if (empty($this->_url[0]))
            $this->_url[0] = $this->realUrl($this->_url_static, $name, $arguments) + ['success_call' => null, 'error_call' => null];

        $this->send($success, $error);
        return $data;
    }

    public function append($url, $action, $data = [], callable $success_call = null, callable $error_call = null)
    {
        $this->_url[++$this->_index] = $this->realUrl($url, $action, $data) + ['success_call' => $success_call, 'error_call' => $error_call];
        return $this->_index;
    }


    public function send(callable $success_call = null, callable $error_call = null)
    {
        foreach ($this->_url as $index => $item) {
            $pid = ($index === 0 or !$this->fork) ? 0 : pcntl_fork();

            if ($pid == -1) {
                die('could not fork');
            } else if ($pid) {
                pcntl_wait($status);
            } else {

                $success_call = $item['success_call'] ?: $success_call;
                $error_call = $item['error_call'] ?: $error_call;
                if (is_null($success_call) and $this->_isAsync === false) throw new \Exception('非异步请求，必须提供处理返回数据的回调函数');

                $fp = fsockopen($item['host'], $item['port'], $err_no, $err_str, intval($this->timeout ?: 1));
                if (!$fp) {
                    if (!is_null($error_call)) {
                        $error_call($index, $err_no, $err_str);
                    } else {
                        throw new \Exception($err_str, $err_no);
                    }
                } else {
                    $_data = http_build_query($item['data']);

                    $data = "POST {$item['uri']} {$item['version']}\r\n";
                    $data .= "Host:{$item['host']}\r\n";
                    $data .= "Content-type:application/x-www-form-urlencoded\r\n";
                    $data .= "User-Agent:{$item['agent']}\r\n";
                    $data .= "Content-length:" . strlen($_data) . "\r\n";
                    $data .= "Connection:Close\r\n\r\n{$_data}";

                    fwrite($fp, $data);
                    if ($this->_isAsync) {//异步，直接返index，不带数据
                        if (!is_null($success_call)) {
                            $success_call($index, null);
                        }
                    } else {
                        if (!is_null($success_call)) {

                            //接收数据
                            $value = $tmpValue = '';
                            $len = null;
                            while (!feof($fp)) {
                                $line = fgets($fp);
                                if ($line == "\r\n" and is_null($len)) {
                                    $len = 0;//已过信息头区
                                } elseif ($len === 0) {
                                    $len = hexdec($line);//下一行的长度
                                } elseif (is_int($len)) {
                                    $tmpValue .= $line;//中转数据，防止收到的一行不是一个完整包
                                    if (strlen($tmpValue) >= $len) {
                                        $value .= substr($tmpValue, 0, $len);
                                        $tmpValue = '';
                                        $len = 0;//收包后归0
                                    }
                                }
                            }
                            //接收结束

                            $success_call($index, $this->data_decode($value));
                        }
                    }
                    fclose($fp);
                }
            }
        }
        return true;
    }

    /**
     * 是否可用接口，并返回各部分数据
     * @param $url
     * @param null $match
     * @return int
     */
    private function is_url($url, &$match = null)
    {
        return preg_match('/^(https?)\:\/{2}([a-z][\w\.]+\.[a-z]{2,10})(?:\:(\d+))?(\/.*)?$/i', $url, $match);
    }

    private function realUrl($url, $action, $data)
    {
        if (!$this->is_url($url, $info)) throw new \Exception("请求调用地址不是一个合法的URL");
        if (!isset($info[3])) $info[3] = 80;
        if (!isset($info[4])) $info[4] = '/';

        $_data = [
            $this->_form_key['action'] => $action,
            $this->_form_key['data'] => $this->data_encode($data)
        ];
        if ($this->type) $_data[$this->_form_key['type']] = $this->type;

        $version = (strtoupper($info[1]) === 'HTTPS') ? 'HTTP/2.0' : 'HTTP/1.1';
        $port = intval($info[3] ?: 80);
        if ($version === 'HTTP/2.0' and $port === 80) $port = 443;
        elseif ($version === 'HTTP/1.1' and $port === 443) $port = 80;

        return [
            'version' => $version,
            'host' => $info[2],
            'port' => $port,
            'uri' => $info[4],
            'url' => $url,
            'agent' => ($this->agent ?: getenv('HTTP_USER_AGENT')),
            'data' => $this->sign_add($info[2], $_data),
        ];
    }


    private function data_encode($val)
    {
        if (strtolower($this->_type) === 'json') {
            return is_array($val) ? json_encode($val, 256) : $val;
        } else {
            return serialize($val);
        }
    }

    private function data_decode($val)
    {
        if (strtolower($this->_type) === 'json') {
            $arr = json_decode($val, true);
            return is_array($arr) ? $arr : $val;
        } else {
            return unserialize($val);
        }
    }


    /**
     * 加签名
     * @param $host
     * @param array $arr
     * @return array
     */
    private function sign_add($host, array $arr)
    {
        ksort($arr);
        $host .= $this->token;
        foreach ($arr as $k => $v) $host .= "&{$k}=$v";
        $arr[$this->_form_key['sign']] = md5($host);
        return $arr;
    }

    /**
     * 清空一个或全部
     * @param null $index
     */
    public function flush($index = null)
    {
        if (is_null($index)) {
            $this->_url = [];
            $this->_index = 0;
        } elseif (is_array($index)) {
            array_map('self::flush', $index);
        } else {
            unset($this->_url[$index]);
        }
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