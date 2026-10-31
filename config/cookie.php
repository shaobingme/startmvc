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
 * Cookie 默认安全策略（Cookie::set / delete 的默认值，可被调用参数覆盖）
 *
 * 调用：config('cookie.cookie_secure')
 */
return [
    'cookie_secure' => 'auto',       // Cookie仅通过HTTPS传输：true强制开启 / false关闭 / 'auto'自动检测HTTPS
    'cookie_samesite' => 'Lax',      // 防CSRF：Lax / Strict / None（None必须配合secure）
];
