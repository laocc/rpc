<?php
declare(strict_types=1);

namespace laocc\rpc;

use function esp\core\esp_error;
use esp\http\Http;
use esp\http\HttpResult;

class Rpc
{
    private string $token = '';
    private array $_allow = [];
    private string $url;
    public Http $http;
    public HttpResult $result;

    public function __construct(array $conf = [])
    {
        if (empty($conf)) {
            if (defined('_RPC')) {
                $conf = _RPC;
            } else {
                esp_error('未指定rpc也未定义_RPC');
            }
        }
        $masterIP = defined('_RPC') ? _RPC['ip'] : ($conf['master'] ?? '');

        if (file_exists(_RUNTIME . '/master.lock') and ($conf['ip'] === $masterIP)) {
            $conf['ip'] = '127.0.0.1';
        }

        $option = [];
        $option['host_domain'] = $conf['host'];
        $option['host'] = $conf['ip'];
        $option['port'] = $conf['port'];
        $option['timeout'] = intval($conf['timeout'] ?? 5);
        $option['dns'] = intval($conf['dns'] ?? 0);
        $option['domain2ip'] = intval($conf['domain2ip'] ?? 0);
        $option['encode'] = 'json';
        $option['decode'] = 'json';
        $option['ua'] = 'esp/http http/cURL http/rpc rpc/1.1.2';
        if (isset($conf['token'])) $this->token = $conf['token'];
        if (isset($conf['ua'])) $option['ua'] = $conf['ua'];

        $this->http = new Http($option);
        $this->url = sprintf('%s://%s:%s', 'http', $conf['ip'], $conf['port']);
    }

    /**
     * 设置Token，不设不加sign
     *
     * @param string $token
     * @return $this
     */
    public function token(string $token)
    {
        $this->token = $token;
        return $this;
    }

    public function setOption(string $key, string $value): Rpc
    {
        $this->http->set($key, $value);
        return $this;
    }

    public function setUrl(string $url): Rpc
    {
        $this->url = $url;
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

    public function encode(string $code): Rpc
    {
        $this->http->encode($code);
        return $this;
    }

    public function decode(string $code): Rpc
    {
        $this->http->decode($code);
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

    public function sign(string $json, string $rand): string
    {
        return md5("{$rand}.{$json}.{$this->token}");
    }

    /**
     * 签名验证
     *
     * @param string $json
     * @return bool
     */
    public function signTrue(string $json): bool
    {
        $rand = getenv('HTTP_RAND');
        $sign = getenv('HTTP_SIGN');
        if (empty($rand) or empty($sign)) return false;
        if (empty($this->token)) return false;

        return strval($sign) === $this->sign($json, $rand);
    }


    public function request(string $uri, array $data = [], bool $isPost = true)
    {
        $json = '';
        if ($data) {
            $json = json_encode($data, 320);
            $this->http->data($json);
        }

        if (isset($this->token)) {
            $rand = strval(microtime(true));
            $this->http->headers('rand', $rand);
            $this->http->headers('sign', $this->sign($json, $rand));
        }

        if ($isPost) {
            $this->result = $this->http->post($this->url . $uri);
        } else {
            $this->result = $this->http->get($this->url . $uri);
        }

        if ($err = $this->result->error(true, $this->_allow)) return $err;
        if ($this->result->_decode !== 'json') return $this->result->html();

        return $this->result->data();
    }

}