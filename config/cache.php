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
    'drive' => 'file', //默认驱动支持file,redis,memcached缓存

    /**
     * 数据表写入后自动失效的缓存标签
     *
     * 键为逻辑表名（不带表前缀），值为该表写入时要失效的标签（字符串或数组）。
     * 留空数组即关闭（默认），此时框架不会注册任何监听器，写库路径零额外开销。
     *
     * 例：'articles' => ['article', 'home'] 表示 sm_articles 发生
     *     insert/update/delete/truncate/drop 时，同时失效 article 与 home 标签下的缓存。
     * 值写 true 表示直接以表名本身作为标签，等价于 'articles' => ['articles']。
     *
     * 注意：标签化缓存请设置较短的 TTL——flush 只递增标签版本号，
     * 旧条目要等自身 TTL 到期后才会被驱动回收。
     */
    'autoFlushTag' => [
        // 'articles' => ['article', 'home'],
        // 'users'    => true,
    ],

    'file'=> [
		'cacheDir'=>'cache/',
		'cacheTime'=>3600
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'database' => 0,
        'cacheTime'=>3600
    ],
    'memcached' => [
        'host' => '127.0.0.1',
        'port' => 11211,
        'cacheTime'=>3600
    ],
];