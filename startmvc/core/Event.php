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
     * 移除事件监听器
     * @param string $event 事件名称
     * @return void
     */
    public static function forget($event)
    {
        unset(self::$listeners[$event]);
    }
}
