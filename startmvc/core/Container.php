<?php
namespace startmvc\core;
/**
 * 容器类
 * 用于管理和解析依赖关系
 */

class Container
{
    /**
     * 已解析的共享实例（单例缓存）
     * @var array
     */
    protected static $instances = [];

    /**
     * 绑定注册表（abstract => ['concrete' => mixed, 'shared' => bool]）
     * @var array
     */
    protected static $bindings = [];

    /**
     * 正在解析中的类（用于循环依赖检测）
     * @var array
     */
    protected static $building = [];

    /**
     * 获取容器实例
     * @return Container
     */
    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new static();
        }
        return $instance;
    }

    /**
     * 绑定一个类型到容器
     * @param string $abstract 抽象类型（接口或类名）
     * @param mixed $concrete 具体实现（闭包、类名或已实例化的对象）
     * @param bool $shared 是否共享实例（单例）
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        // 重新绑定后旧的共享实例缓存作废，确保新绑定立即生效
        unset(static::$instances[$abstract]);
        static::$bindings[$abstract] = compact('concrete', 'shared');
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
     * @param array $parameters 构造函数参数（支持按参数名或按位置索引）
     * @return mixed 解析出的实例
     * @throws \Exception
     */
    public function make($abstract, array $parameters = [])
    {
        if (isset(static::$instances[$abstract])) {
            return static::$instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        if (is_object($concrete) && !$concrete instanceof \Closure) {
            // 绑定的已是现成对象，直接返回
            $object = $concrete;
        } else {
            $object = $this->build($concrete, $parameters);
        }

        if ($this->isShared($abstract)) {
            static::$instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 获取绑定的具体实现（未绑定时返回抽象本身）
     * @param string $abstract 抽象类型
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        if (isset(static::$bindings[$abstract])) {
            return static::$bindings[$abstract]['concrete'];
        }
        return $abstract;
    }

    /**
     * 判断是否为共享绑定（单例）
     * @param string $abstract 抽象类型
     * @return bool
     */
    protected function isShared($abstract)
    {
        return isset(static::$bindings[$abstract]) ? (bool)static::$bindings[$abstract]['shared'] : false;
    }

    /**
     * 增加自动解析构造函数参数的能力
     * @param string|\Closure $concrete 具体类名或闭包
     * @param array $parameters 手动提供的参数
     * @return object 实例化对象
     * @throws \Exception
     */
    protected function build($concrete, array $parameters = [])
    {
        // 如果是闭包，直接执行
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }

        // 循环依赖检测：A 依赖 B、B 又依赖 A 时防止无限递归
        if (isset(static::$building[$concrete])) {
            throw new \Exception("检测到循环依赖：{$concrete}");
        }

        static::$building[$concrete] = true;
        try {
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
        } finally {
            unset(static::$building[$concrete]);
        }
    }

    /**
     * 解析构造函数参数列表
     * 解析顺序：手动传入参数（按名/按位）> 类类型依赖（递归 make）> 默认值 > 可空 > 报错
     *
     * @param \ReflectionParameter[] $dependencies 构造函数参数
     * @param array $parameters 手动提供的参数
     * @return array 解析出的参数值
     * @throws \Exception
     */
    protected function resolveDependencies(array $dependencies, array $parameters = [])
    {
        $instances = [];
        foreach ($dependencies as $index => $param) {
            $name = $param->getName();

            // 可变参数直接跳过（newInstanceArgs 会自然处理剩余值）
            if ($param->isVariadic()) {
                break;
            }

            // 按参数名传入
            if (array_key_exists($name, $parameters)) {
                $instances[] = $parameters[$name];
                continue;
            }
            // 按位置传入
            if (array_key_exists($index, $parameters)) {
                $instances[] = $parameters[$index];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // 类类型依赖：递归由容器解析
                $instances[] = $this->make($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $instances[] = $param->getDefaultValue();
                continue;
            }

            // 仅显式可空类型（?T）可解析为 null；
            // 无类型参数不在此列（PHP 的 allowsNull() 对无类型参数返回 true，会掩盖缺参错误）
            if ($type instanceof \ReflectionNamedType && $type->allowsNull()) {
                $instances[] = null;
                continue;
            }

            $declaringClass = $param->getDeclaringClass();
            $owner = $declaringClass ? $declaringClass->getName() : '';
            throw new \Exception("无法解析依赖 \${$name}" . ($owner ? "（{$owner} 构造函数）" : '') . '，且无默认值');
        }
        return $instances;
    }
}
