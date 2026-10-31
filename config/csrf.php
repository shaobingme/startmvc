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
 * CSRF 防护配置
 *
 * 调用：config('csrf.token_name')
 */
return [
    'token_lifetime' => 3600,        // Token有效期（秒）
    'token_name' => 'csrf_token',    // Token字段名
    'auto_delete' => false,          // 验证后是否自动删除（false=可重复使用，true=一次性）
    'exclude' => [                   // 无需校验的路径（不含首尾斜杠，支持 * 通配符），如支付回调、开放API
        // 'api/webhook',
        // 'api/open/*',
    ],
];
