<?php
class Container
{
    protected static $instances = [];
    protected $bindings = [];
    
    /**
     * 绑定一个类型到容器
     * @param string $abstract 抽象类型（接口或类名）
     * @param mixed $concrete 具体实现（闭包或类名）
     * @param bool $shared 是否共享实例（单例）
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }
    
    /**
     * 注册一个共享绑定（单例）
     * @param string $abstract 抽象类型
     * @param mixed $concrete 具体实现
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }
    
    /**
     * 解析一个类型的实例
     * @param string $abstract 要解析的类型
     * @param array $parameters 构造函数参数
     * @return mixed 解析出的实例
     * @throws BindingResolutionException
     */
    public function make($abstract, array $parameters = [])
    {
        if (isset(static::$instances[$abstract])) {
            return static::$instances[$abstract];
        }
        
        $concrete = $this->getConcrete($abstract);
        $object = $this->build($concrete, $parameters);
        
        if ($this->isShared($abstract)) {
            static::$instances[$abstract] = $object;
        }
        
        return $object;
    }
    
    /**
     * 增加自动解析构造函数参数的能力
     * @param string $concrete 具体类名
     * @param array $parameters 手动提供的参数
     * @return object 实例化对象
     */
    protected function build($concrete, array $parameters = [])
    {
        // 如果是闭包，直接执行
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }
        
        // 获取反射类
        $reflector = new \ReflectionClass($concrete);
        
        // 检查是否可实例化
        if (!$reflector->isInstantiable()) {
            throw new \Exception("类 {$concrete} 不可实例化");
        }
        
        // 获取构造函数
        $constructor = $reflector->getConstructor();
        
        // 如果没有构造函数，直接实例化
        if (is_null($constructor)) {
            return new $concrete;
        }
        
        // 获取构造函数参数
        $dependencies = $constructor->getParameters();
        
        // 解析构造函数的依赖
        $instances = $this->resolveDependencies($dependencies, $parameters);
        
        // 创建实例
        return $reflector->newInstanceArgs($instances);
    }
} 