<?php
declare(strict_types=1);

namespace laocc\rpc;

use esp\http\Http;
use esp\http\HttpResult;

class Rpc
{
    private array $_allow = [];
    private string $url;
    private array $option;
    public Http $http;
    public HttpResult $result;

    public function __construct(string $host, string $ip = '127.0.0.1', array $option = [])
    {
        if (!defined('_RpcToken')) define('_RpcToken', '_RpcToken');
        if (!defined('_RpcPort')) define('_RpcPort', 44380);
        if (!defined('_RpcKey')) define('_RpcKey', _UNIQUE_KEY);
        if (!defined('_RpcHost')) define('_RpcHost', '.esp');
        if (!strpos($host, '.')) $host = $host . _RpcHost;
        $port = _RpcPort;
        if (isset($option['ip'])) $ip = $option['ip'];
        if (isset($option['port'])) $port = $option['port'];

        $this->option = [];
        $this->option['host_domain'] = $host;
        $this->option['host'] = ["{$host}:{$port}:$ip"];
        $this->option['timeout'] = intval($option['timeout'] ?? 5);
        $this->option['dns'] = 0;
        $this->option['domain2ip'] = 0;
        $this->option['encode'] = 'json';
        $this->option['decode'] = 'json';
        $this->option['ua'] = 'esp/http http/cURL http/rpc rpc/1.1.2';
        if ($option['header'] ?? 0) $this->option['header'] = true;
        if (isset($option['ua'])) $this->option['ua'] = $option['ua'];
        $this->format($option);
    }


    /**
     * @param array $option
     * @return Rpc
     */
    public function format(array $option = []): Rpc
    {
        $option = $option + $this->option;
        $now = strval(microtime(true));
        $key = $option['key'] ?? _RpcKey;

        $this->http = new Http($option);
        $this->http->headers('timestamp', $now);
        $this->http->headers('key', $key);
        $this->http->headers('sign', md5($key . $now . _RpcToken . 'RpcSalt@EspCore'));
        $this->url = sprintf('%s://%s:%s', 'http', $option['host_domain'], _RpcPort);
        return $this;
    }

    /**
     * 增加额外的headers
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function headers(string $key, string $value): Rpc
    {
        $this->http->headers($key, $value);
        return $this;
    }

    public function allow(int $code): Rpc
    {
        $this->_allow[] = $code;
        return $this;
    }

    public function get(string $uri, array $data = [])
    {
        return $this->request($uri, $data, false);
    }

    public function post(string $uri, array|string $data = [])
    {
        return $this->request($uri, $data, true);
    }

    public function check(array $data = [])
    {
        return $this->request('/index/rpc_check', $data, true);
    }

    public function debug(callable $fun): void
    {
        $fun($this->result);
    }


    public function request(string $uri, array|string $data = [], bool $isPost = true)
    {
        if (!empty($data)) {
            if (is_array($data)) {
                $this->http->data(json_encode($data, 320));
            } else {
                $this->http->data($data);
            }
        }

        if ($isPost) {
            $this->result = $this->http->post($this->url . $uri);
        } else {
            $this->result = $this->http->get($this->url . $uri);
        }

        if ($this->result->_error === 510) return $this->result->html();

        if ($err = $this->result->error(true, $this->_allow)) return $err;

        if ($this->result->_decode !== 'json') return $this->result->html();

        return $this->result->data();
    }

}