<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
return [
    'debug' => false,	//Debug模式，开发过程中开启，生产环境中请关闭
    'trace' => false,	//是否开启调试追踪，生产环境中请关闭
    'timezone' => 'Asia/Shanghai',	//系统时区
    'url_suffix' => '.html',	//URL后缀
    'default_module' => 'home',	//默认模块
    'default_controller' => 'Index',	//默认控制器
    'default_action' => 'index',	//默认方法
    'urlrewrite' => true,	//是否Url重写，隐藏index.php,需要服务器支持和对应的规则
    'session_prefix' => '',	//Session前缀
    'cookie_prefix' => '',	//Cookie前缀
    'locale'  => 'zh_cn',	//指定默认语言，小写
    'db_auto_connect'  => true,	//是否开启数据库自动连接
    'theme'  => '',	//指定模板子目录，方便多风格使用，为空时模板文件在view下
    
    // Session安全配置
    'session' => [
        'cookie_httponly' => true,       // 防止JavaScript访问Cookie
        'cookie_secure' => 'auto',       // Cookie仅通过HTTPS传输：true强制开启 / false关闭 / 'auto'自动检测HTTPS
        'cookie_samesite' => 'Lax',      // 防CSRF：Lax / Strict / None（None必须配合secure）
        'use_only_cookies' => true,      // 只使用Cookie存储会话ID
        'cookie_lifetime' => 7200,       // 会话Cookie生存时间（秒）
        'gc_maxlifetime' => 7200,        // 垃圾回收时间（秒）
    ],
    // Cookie安全配置（Cookie::set/delete 的默认安全策略，可被调用参数覆盖）
    'cookie' => [
        'cookie_secure' => 'auto',       // Cookie仅通过HTTPS传输：true强制开启 / false关闭 / 'auto'自动检测HTTPS
        'cookie_samesite' => 'Lax',      // 防CSRF：Lax / Strict / None（None必须配合secure）
    ],
    // CSRF 防护配置
    'csrf' => [
        'token_lifetime' => 3600,        // Token有效期（秒）
        'token_name' => 'csrf_token',    // Token字段名
        'auto_delete' => false,          // 验证后是否自动删除（false=可重复使用，true=一次性）
        'exclude' => [                   // 无需校验的路径（不含首尾斜杠，支持 * 通配符），如支付回调、开放API
            // 'api/webhook',
            // 'api/open/*',
        ],
    ],
];