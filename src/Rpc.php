<?php
declare(strict_types=1);

namespace laocc\rpc;

use esp\http\Http;
use esp\http\HttpResult;

class Rpc
{
    private array $_allow = [];
    private string $url;
    public Http $http;
    public HttpResult $result;

    public function __construct(string $host, string $ip = '127.0.0.1')
    {
        if (!defined('_RpcToken')) define('_RpcToken', '_RpcToken');
        if (!defined('_RpcKey')) define('_RpcKey', _UNIQUE_KEY);
        $port = defined('_RpcPort') ? _RpcPort : 44380;

        $option = [];
        $option['host_domain'] = $host;
        $option['host'] = ["{$host}:{$port}:$ip"];
        $option['timeout'] = 5;
        $option['dns'] = 0;
        $option['domain2ip'] = 0;
        $option['encode'] = 'json';
        $option['decode'] = 'json';
        $option['ua'] = 'esp/http http/cURL http/rpc rpc/1.1.2';

        $now = strval(microtime(true));

        $this->http = new Http($option);
        $this->http->headers('timestamp', $now);
        $this->http->headers('key', _RpcKey);
        $this->http->headers('sign', md5(_RpcKey . $now . _RpcToken . 'RpcSalt@EspCore'));

        $this->url = sprintf('%s://%s:%s', 'http', $host, $port);
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

    public function post(string $uri, array $data = [])
    {
        return $this->request($uri, $data, true);
    }

    public function debug(callable $fun): void
    {
        $fun($this->result);
    }


    public function request(string $uri, array $data = [], bool $isPost = true)
    {
        if ($data) {
            $json = json_encode($data, 320);
            $this->http->data($json);
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