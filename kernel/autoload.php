<?php
/**
 *
 * 类自动加载实现
 *
 * TODO 实际业务中建议用composer的autoload
 *
 * 如果想自己实现加载，可以把下面内容复制到自己业务代码中并再次加工
 *
 * 保留$namespace中现有部分，但要注意$root中dirname()的次数，最终指向站点根目录
 * 所谓站点根目录，一般是指[composer install]后生成的vendor路径的上一层
 *
 * 修改原有定义：
 *    $namespace = [
 *          'laocc\\gds\\' => "{$root}/vendor/laocc/gds/kernel/",
 *      ];
 *
 * 相同目录下的子目录只要遵循【子目录名即为命名空间的后续名称】的规则，则只需定义顶层目录即可
 * 例：本程序中kernel目录中的类命名空间为laocc\gds，则该目录中ext子目录的中的类命名空间名称在laocc\gds后接ext，即：laocc\gds\ext;
 *
 * 如果MVC中的MC也用命名空间，可以在$namespace中加以定义，但要注意根目录的问题，例如本例中的 demo=>example
 *
 */
function LaoCC_rpc_register($class)
{
    $root = dirname(__DIR__);
    $namespace = [
        'laocc\\rpc\\' => "{$root}/kernel/",
    ];
    $file = preg_replace_callback('/^([\w\\\]*?)(\w+?)$/i', function ($match) use ($namespace) {
        //正好命中namespace
        if (isset($namespace[$match[1]])) return $namespace[$match[1]] . ucfirst($match[2]) . '.php';
        //根据命名空间转换路径查找
        return str_replace('\\', '/', $match[1]) . ucfirst($match[2]) . '.php';

    }, strtolower($class));
    is_readable($file) and include $file;
}
spl_autoload_register('LaoCC_rpc_register', true, true);

