<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

/**
 * Session 安全配置
 *
 * 调用：config('session.cookie_secure')
 * 键名前缀即本文件名，与 config/ 下其它分组规则一致。
 */
return [
    'cookie_httponly' => true,       // 防止JavaScript访问Cookie
    'cookie_secure' => 'auto',       // Cookie仅通过HTTPS传输：true强制开启 / false关闭 / 'auto'自动检测HTTPS
    'cookie_samesite' => 'Lax',      // 防CSRF：Lax / Strict / None（None必须配合secure）
    'use_only_cookies' => true,      // 只使用Cookie存储会话ID
    'cookie_lifetime' => 7200,       // 会话Cookie生存时间（秒）
    'gc_maxlifetime' => 7200,        // 垃圾回收时间（秒）
];
