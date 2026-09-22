<?php
namespace startmvc\core;

class Event
{
    /**
     * 已注册的事件监听器
     * @var array
     */
    protected static $listeners = [];

    /**
     * 静默深度：>0 时 fire() / fireRef() 不派发任何监听器
     * @var int
     */
    protected static $muteDepth = 0;
    
    /**
     * 注册事件监听器
     * @param string $event 事件名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级(越大越先执行)
     * @return void
     */
    public static function listen($event, callable $callback, $priority = 0)
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        
        // 确保同一优先级下的监听器不会被覆盖
        while (isset(self::$listeners[$event][$priority])) {
            $priority++;
        }
        
        self::$listeners[$event][$priority] = $callback;
        
        // 按优先级排序
        krsort(self::$listeners[$event]);
    }
    
    /**
     * 触发事件
     * @param string $event 事件名称
     * @param mixed $payload 事件数据
     * @return array 所有监听器的返回值
     */
    public static function fire($event, $payload = null)
    {
        $responses = [];

        if (self::$muteDepth > 0) {
            return $responses;
        }

        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $priority => $callback) {
                $responses[] = call_user_func($callback, $payload);
            }
        }
        
        return $responses;
    }
    
    /**
     * 触发事件（载荷按引用传递）
     *
     * 与 fire() 的唯一区别是载荷以引用传入：
     * - 监听器声明 `function (&$payload)` 时可直接改写载荷，后续监听器看到改写后的结果；
     * - 未声明引用（`function ($payload)`）的监听器只拿到副本，改动丢弃且**不会报错**，
     *   因此对既有 fire() 监听器完全向后兼容。
     *
     * 写入类钩子（model.before_* / model.after_*）使用本方法。
     * 监听器返回 false 表示否决本次操作，调用方通过
     * `in_array(false, $responses, true)` 判定；返回值与 fire() 一样按优先级顺序收集。
     *
     * @param string $event 事件名称
     * @param mixed $payload 事件载荷（引用传递）
     * @return array 所有监听器的返回值
     */
    public static function fireRef($event, &$payload)
    {
        $responses = [];

        if (self::$muteDepth > 0) {
            return $responses;
        }

        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $priority => $callback) {
                $responses[] = call_user_func_array($callback, [&$payload]);
            }
        }

        return $responses;
    }

    /**
     * 在回调期间静默所有事件派发
     *
     * 用于批量导入、数据迁移、Seeder 等场景——不希望为每一条记录触发
     * 审计日志 / 缓存失效 / 消息推送。回调结束后自动恢复，支持嵌套。
     *
     * 注意：静默的是"派发"而不是"注册"——回调里 Event::listen() 照常生效，
     * 只是静默期间不会被触发。fire() 与 fireRef() 都会静默。
     *
     * @param callable $callback 回调
     * @return mixed 回调的返回值
     */
    public static function mute(callable $callback)
    {
        self::$muteDepth++;

        try {
            return $callback();
        } finally {
            self::$muteDepth--;
        }
    }

    /**
     * 当前是否处于静默状态
     * @return bool
     */
    public static function isMuted()
    {
        return self::$muteDepth > 0;
    }

    /**
     * 指定事件是否注册了监听器
     *
     * 供热点路径提前短路：例如 DbCore 只在确实有人监听 db.changed 时，
     * 才去解析表名并做派发准备。
     *
     * @param string $event 事件名称
     * @return bool
     */
    public static function hasListeners($event)
    {
        return !empty(self::$listeners[$event]);
    }

    /**
     * 移除事件监听器
     * @param string $event 事件名称
     * @return void
     */
    public static function forget($event)
    {
        unset(self::$listeners[$event]);
    }
}
