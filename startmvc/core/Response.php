<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
namespace startmvc\core;

class Response
{
    /**
     * 响应状态码
     * @var int
     */
    protected $statusCode = 200;
    
    /**
     * 响应头
     * @var array
     */
    protected $headers = [];
    
    /**
     * 响应内容
     * @var string
     */
    protected $content = '';
    
    /**
     * 设置状态码
     * @param int $code 状态码
     * @return $this
     */
    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        return $this;
    }
    
    /**
     * 设置响应头
     * @param string $key 头名
     * @param string $value 头值
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }
    
    /**
     * 设置内容
     * @param string $content 响应内容
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }
    
    /**
     * 发送响应
     * @return void
     */
    public function send()
    {
        // 设置状态码
        http_response_code($this->statusCode);
        
        // 设置响应头
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        
        // 输出内容
        echo $this->content;
    }
    
    /**
     * 返回JSON响应
     * @param mixed $data 数据
     * @param int $status 状态码
     * @return $this
     */
    public function json($data, $status = 200)
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setStatusCode($status);
        $this->setContent(json_encode($data));
        return $this;
    }
}
