<?php
namespace laocc\rpc;

class Client
{
    private $_option = [];
    private $_task = [];
    private $_sev_url;
    private $_index = 0;

    private $_form_key = [
        'action' => 'action',
        'sign' => 'sign',
        'data' => 'data',
        'type' => 'type',
    ];

    public function __construct($isAsync = null)
    {
        if (is_string($isAsync)) $this->_sev_url = $isAsync;
    }

    /**
     * 调用服务器端类方法的魔术方法
     */
    public function __call($name, $arguments)
    {
        if (is_null($this->_sev_url)) throw new \Error('同步请求，请在创建client对象时提供服务端URL，如：new Client(URL);');

        $callback = function ($index, $value) use (&$data) {
            $data = $value;
        };

        if (empty($this->_task[0]))
            $this->_task[0] = $this->realUrl($this->_sev_url, $name, $arguments, false) + ['callback' => $callback];

        $this->send();

        return $data;
    }

    /**
     * 异步
     */
    public function task($url, $action, $data = [], callable $callback = null)
    {
        $this->_task[++$this->_index] = $this->realUrl($url, $action, $data, true) + ['callback' => $callback];
        return $this->_index;
    }

    /**
     * 同步
     */
    public function call($url, $action, $data = [], callable $callback = null)
    {
        $this->_task[++$this->_index] = $this->realUrl($url, $action, $data, false) + ['callback' => $callback];
        return $this->_index;
    }


    public function send(callable $callback = null)
    {
        if (empty($this->_task)) throw new \Error('当前队列任务为空');
        $timeout = intval($this->timeout ?: 1);

        foreach ($this->_task as $index => $item) {
            $pid = ($index === 0 or !$this->fork) ? 0 : pcntl_fork();

            if ($pid == -1) {
                die('could not fork');
            } else if ($pid) {
                pcntl_wait($status);
            } else {
                $callback = $item['callback'] ?: $callback;
                if (is_null($callback) and !$item['async']) throw new \Error('同步请求，必须提供处理返回数据的回调函数');

                //短连接
                $fp = fsockopen($item['host'], $item['port'], $err_no, $err_str, $timeout);

                //长连接
//                $fp = pfsockopen($item['host'], $item['port'], $err_no, $err_str, $timeout);
//                stream_set_blocking($fp, 0);//1阻塞;0非阻塞

                if (!$fp) {//连接失败
                    if (!is_null($callback)) {
                        $callback($index, new \Error($err_str, $err_no));
                    } else {//异步时，直接抛错
                        throw new \Exception($err_str, $err_no);
                    }
                } else {
                    $_data = http_build_query($item['data']);
                    $len = strlen($_data);

                    $data = "POST {$item['uri']} {$item['version']}\r\n";
                    $data .= "Host:{$item['host']}\r\n";
                    $data .= "Content-type:application/x-www-form-urlencoded\r\n";
                    $data .= "User-Agent:{$item['agent']}\r\n";
                    $data .= "Content-length:{$len}\r\n";
                    $data .= "Connection:Close\r\n\r\n{$_data}";

                    echo strlen($data) / 1024 / 1024, "\n";

                    $win = fwrite($fp, $data);
                    print_r(['fa' => $win, 'len' => $len, 'all' => strlen($data)]);

                    if (0 and $win !== $len) {
                        if (!is_null($callback)) {
                            $error = error_get_last();
                            if ($win === false) {
                                $callback($index, new \Error('数据发送失败', 1, $error));
                            } else {
                                $callback($index, new \Error('数据发送失败，与实际需发送数据长度相差=' . ($win - $len), 2, $error));
                            }
                            error_clear_last();
                        }
                        fclose($fp);
                        continue;
                    }

                    if ($item['async']) {//异步，直接返index，不带数据
                        if (!is_null($callback)) {
                            $callback($index, null);
                        }
                    } else {

                        //接收数据
                        $value = $tmpValue = '';
                        $len = null;
                        while (!feof($fp)) {
                            $line = fgets($fp);
//                            print_r($line);

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

//                        print_r("【{$value}】");
                        $data = $this->data_decode($value);
                        if (is_array($data) and isset($data['_message'])) {
                            $callback($index, new \Error($data['_message'], $data['_type']));

                        } elseif ($this->get('sign', 0) > 1) {//要对返回数据签名验证
                            if (!is_array($data)) {
                                $callback($index, new \Error('返回数据异常', -1));

                            } elseif (!Sign::check($this->_form_key['sign'], $this->token, $item['host'], $data)) {
                                $callback($index, new \Error('服务端返回数据TOKEN验证失败', 1001));

                            } else {
                                $callback($index, (isset($data['_value_']) ? $data['_value_'] : $data));
                            }
                        } else {
                            $callback($index, (isset($data['_value_']) ? $data['_value_'] : $data));
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

    private function realUrl($url, $action, $data, $async)
    {
        if (!$this->is_url($url, $info)) throw new \Exception("请求调用地址不是一个合法的URL");

        if (!isset($info[3])) $info[3] = 80;
        if (!isset($info[4])) $info[4] = '/';
        foreach ($data as $i => &$v) {
            if (!is_int($i)) throw new \Exception('送入接口的参数，不可以是含键名的数组');
        }

        $_data = [
            $this->_form_key['action'] => $action,
            $this->_form_key['data'] => $this->data_encode($data)
        ];
        if ($this->type) $_data[$this->_form_key['type']] = $this->type;//编码格式

        $version = (strtoupper($info[1]) === 'HTTPS') ? 'HTTP/2.0' : 'HTTP/1.1';
        $port = intval($info[3] ?: 80);
        if ($version === 'HTTP/2.0' and $port === 80) $port = 443;
        elseif ($version === 'HTTP/1.1' and $port === 443) $port = 80;

        if ($this->sign)//加签名
            $_data = Sign::create($this->_form_key['sign'], $this->token, $info[2], $_data);

        return [
            'version' => $version,
            'host' => $info[2],
            'port' => $port,
            'uri' => $info[4],
            'url' => $url,
            'agent' => ($this->agent ?: getenv('HTTP_USER_AGENT')),
            'data' => $_data,
            'async' => $async,
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
            return @unserialize($val);
        }
    }


    /**
     * 清空一个或全部
     * @param null $index
     */
    public function flush($index = null)
    {
        if (is_null($index)) {
            $this->_task = [];
            $this->_index = 0;
        } elseif (is_array($index)) {
            array_map('self::flush', $index);
        } else {
            unset($this->_task[$index]);
        }
    }


    public function set($name, $value = null)
    {
        if (is_array($name)) {
            $this->_option = $name + $this->_option;
        } else {
            $this->_option[$name] = $value;
        }
    }

    public function get($name, $autoValue = null)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : $autoValue;
    }

    public function __set($name, $value)
    {
        $this->_option[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : null;
    }


}