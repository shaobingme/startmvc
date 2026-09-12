<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

// 日志配置：框架内所有日志（含 Exception 异常处理器）统一经由 Logger 写入，
// 组件不再各自拼路径、各写各的文件（见 startmvc/core/Logger.php）
return [
    // 日志总开关：false 时所有写入直接丢弃（返回 false，不产生任何 IO）
    'enabled' => true,

    // 日志目录（绝对路径）。留空则使用 runtime/logs
    'path' => null,

    // 最低记录级别，低于该级别的日志被静默丢弃
    // 严重度递增：debug < info < notice < warning < error < critical < alert < emergency
    // 生产环境建议改为 'warning'，避免 debug 日志堆积
    'level' => 'debug',

    // 是否按级别分文件：
    //   true  → 2026-09-12_error.log（与历史日志文件命名一致，天然按天轮转）
    //   false → 2026-09-12.log（所有级别混写一个文件）
    'split' => true,
];
