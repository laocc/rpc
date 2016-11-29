## RPC (Remote Procedure Call Protocol)
关于RPC概念无须多说，若不了解可以网上找找，资料很多。本程序实现了在PHP中网页之间模拟RPC通信，程序结构受yar的启发，如果需要纯C的RPC调用，请用yar。
本程序和yar不同之处在于：
- 可以运行在win环境下
- 可以异步/同步两种方式发送请求
- 在设置了TOKEN的情况下自动签名/自动认证
- 客户端认证简单文本认证
- Server端可以接收并处理APP发送的数据
- Server端方法名称设置统一后缀方式(在类yaf框架下特别有用)，可屏蔽一些系统方法，防止被误调用
- 简化合并了Client两种方式操作
- Client端指定编码方式(json或serialize)，也就是两种方法共存，默认serialize


## 使用场景
1. 一个网站可能有多个子站，不同子站可能都有用户注册、登录认证操作，而这些操作都须调用同一个数据源，在MVC层，Model基本一样，所以不至于每个子站用一套Model，尽管可能代码一模一样。
这时候，多数情况下可能就需要做一个统一的接口，大多数网站可能也是这么做的，那么本程序解决的就是在这种情况下很方便的组织好这些子站和接口之间的数据交换；
2. 现在APP横行天下，APP的接口开发也是一个浩瀚的工程；
3. 系统可能有些日志需要统一存储，可采用异步方式发送到另一个地方去处理；

## 应用演示
server.php
```php
<?php
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
        return "{$username}注册成功";
    }

    /**
     * 用户登录
     * @param $username
     * @param $password
     * @return bool
     */
    public function loginAction($username, $password)
    {
        //业务代码
        return true;
    }
}

$sev = new \laocc\rpc\Server(new UserModel());
$sev->action = 'Action';
$sev->token = 'my token';
$sev->password = 'pwd';
$sev->listen();

```

client.php

```php
<?php
$url = 'http://rpc.kaibuy.top/server.php';
$cli = new \laocc\rpc\Client($url);
$cli->token = 'my token';

$val = $cli->register('myName','myPwd');
var_dump($val);

```
就这样，就可以完成一个通信。如果直接打开client中的那个网址（Server端的访问地址），可直接显示当前接口模型中的所有接口方法，输入server.php中的那个password。

## 程序文档：
### Server端：
server很简单，总的来说只有2+4个设置:
```php
<?php
$sev = new \laocc\rpc\Server(new UserModel()); #UserModel是接口真正的类对像
$sev->action = 'Action';            #设置方法后缀，如此设置之后，registerAction在客户端则只要register即可
$sev->token = 'my token';           #设置token，若不设置，客户端也不要设
$sev->password = 'pwd';             #接口信息访问密码，若不设则禁止查看接口信息，可以设空字串
$sev->agent = 'ourAgentAuth';       #统一的客户端识别码，见后面介绍

$sev->shield(['loginAction']);      #屏蔽某些方法，被屏蔽后，接口文档中看不到，也不可访问
$sev->listen();                     #侦听
```
注：若用在类yaf的框架中，则在indexAction()中则是一个非常不错的方法。
```php
<?php
class UserController
{
    public function indexAction(){
    
        $sev = new \laocc\rpc\Server($this);
        $sev->shield(['indexAction']); #务必要屏蔽当前方法
        #其它设置和上面一样
        
        $sev->listen(); 
    }
}
```


### Client端：
Client端有两种方式：

1. 单个请求发送（同步模式）；
2. 批量发送（同步/异步可选）；

所谓同步是指发送后要等待数据返回，异步是指发送后就不管了；

#### 单个请求发送（同步模式）
```php
<?php
$url = 'http://rpc.kaibuy.top/server.php';  #Server接口访问地址
$cli = new \laocc\rpc\Client($url);         #创建Client
$cli->token = 'my token';                   #设置token
$cli->type = 'json';                        #设置编码方式为json，不设置则用serialize

$val = $cli->register('myName','myPwd');    #请求接口方法，register就是上面registerAction
var_dump($val);

$val = $cli->login('myName','myPwd');       #可以连续多次调用同一个已实例化的接口
var_dump($val);

```
`register()`表面上是请求client的某个方法，实际上它是通过__call请求到服务端，使用时和直接调用服务端的方法一模一样；

在服务端的方法里返回数据有两种情况：1：直接`return`；2：`echo/print_r`等方法打印出来，如果同时有两种情况，只以return的数据。在json编码的情况下，如果echo数字，client端收到的是字符串型值，其他没多大区别。

建议用return方式。




#### 批量发送（同步/异步可选）
```php
<?php
$url = 'http://rpc.kaibuy.top/server.php';
$cli = new \laocc\rpc\Client(true);  #true=异步(默认)，false=同步
$cli->token = 'my token';

/**
 * 接收到数据时的回调
 * @param int $index #发送编号，从1开始
 * @param array $value #返回的数据
 */
$success = function ($index, $value) {
    var_dump($index, $value);
};

/**
 * 出错时的回调
 * @param int $index #发送编号
 * @param int $err_no #错误代码
 * @param string $err_str #错误内容
 */
$error = function ($index, $err_no, $err_str) {
    var_dump($index, $err_no, $err_str);
};

$cli->append($url, 'test', [1, 2, 3], $success, $error);
$cli->append($url, 'test', [4, 5]);

$cli->send($success, $error);

```
- 回调可以在每项中设置(这样可以设置不同的回调)，也可以最后send时一并设置，上面的设置优先级大于send
- 如果是异步模式，$success回调中$value一直是null，也就是不读取返回值
- 如果是同步模式，则必须要设置$success回调

##### append参数：

1. 服务端URL
2. 请求的方法名称
3. 请求的数据，也就是相当于请求方法时的参数，用数组形式，按顺序填入方法的参数，若给的数据少于方法参数，则没分配到的都是null值，这须要注意。
4. 有数据时的回调
5. 出错时的回调

### 关于URL的说明：
一个完整的URL格式如下：
```
http://rpc.kaibuy.top:80/server/user.php
https://rpc.kaibuy.top:443/server/user.php
```
若中80和443是默认端口，可以省略，若不是这种端口，则需要在这URL中指定。


### 关于agent的说明：
客户端访问，实际上是模拟socket请求到服务器，此时有个通用的字段HTTP_USER_AGENT，我们可以利用这个做一下身份识别。
虽然上面有token方法，但我们也可以顺带着再做一层保护，原本想做IP绑定，但用在APP时绑IP显然不现实，APP的HTTP_USER_AGENT也可以自定义。
若客户端不设置，发送的则是访问者真实的AGENT。

如果设置，客户端也要同样设置。



