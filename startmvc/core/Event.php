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

        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $priority => $callback) {
                $responses[] = call_user_func_array($callback, [&$payload]);
            }
        }

        return $responses;
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
