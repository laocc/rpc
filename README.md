# RPC (Remote Procedure Call Protocol)

基于esp，用于快速搭建RPC业务，自动进行身份认定。

注意：若客户端和服务端不是同一台服务器，则要保证两服务器时间相差不得大于5秒以上。

```shell

# 查询服务器当前时间
date '+%F %T.%9N'
date '+%F %T.%0N'

ssh root@127.0.0.1 "date '+%F %T.%3N'"

```


# 定义几个常量

```shell

define('_RpcToken', '_RpcToken');
# Token，客户端服务端必须一致，不指定则就用默认值`_RpcToken`
# 若=`not_check`则不检查签名，在多服务器、且业务量比较大的时候，无法做到同时更新所有服务器，则可以采用此方式过渡；

define('_RpcPort', 44380);
# 端口，若不指定则默认44380，需要在nginx中绑定此端口

define('_RpcHost', '.esp');
# 在nginx中绑定的主机名根域，不指定则就用默认值`.esp`

define('_RpcKey', 'myApp');
# 客户端的识别码，建议在每个主机中单独定义，在服务端用`getenv('HTTP_KEY')`读取
# 此值不指定则用`_UNIQUE_KEY`


```

# Nginx：

```
server    {
    listen 44380;
    server_name account.esp;
    index index.php;
    root /home/account/public/rpc;
    include /home/nginx/conf/php.conf;
    access_log off;
}
```

# 控制器

在所有业务端创建根控制器：注意必须引自`use laocc\rpc\Controller;`

```php
<?php

namespace application\rpc\controllers;

use laocc\rpc\Controller;

class _Base extends Controller
{
    public function _main()
    {
    
    }
}
```

其他控制器引自此控制器即可，

# 调用：

```php
    # 此方法在exp的Controller中已存在，可直接调用
    protected function rpc(string $uri, array $data = [])
    {
        $url = explode(':', $uri);
        $rpc = new Rpc($url[0]);
        $check = $rpc->post($url[1], $data);
        if (is_string($check)) return $check;
        return $check['data'];
    }

```

若此方法不适用，可以自行构造。

```php
    $check = $this->rpc('account:/admin/check', ['id' => $this->adminID]);
    print_r($check);
```

这里实际请求的是`http://account.esp/admin/check`

