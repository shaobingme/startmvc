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

/**
 * HTTP 响应 —— 站点的唯一响应出口
 *
 * 控制器 / 中间件产出的内容统一交给本类的 send() 发送（状态码 + 响应头 + Cookie + 响应体），
 * 调试追踪面板（trace）也在这里单点附加。改造前这段逻辑散落在 App::run（两处）与
 * Controller::display（一处），且附加规则不一致：JSON 响应被追加了 29KB HTML 导致内容非法，
 * 而控制器自行 echo 的页面又完全没有 trace。
 *
 * 三条约定（改动这里务必守住）：
 * 1. send() 幂等 —— 重复调用只发送一次。响应异常在外层兜底发送时可能出现二次发送，
 *    幂等标记可避免内容被重复输出。
 * 2. headers_sent() 之后不再尝试设置状态码 / 响应头 / Cookie。否则 PHP 会抛
 *    "Cannot modify header information" 警告，而 Exception::handleError() 会把警告
 *    提升成 ErrorException，把一个纯时序问题放大成 500。
 * 3. trace 只附加在 HTML 页面上（见 shouldTrace()）。JSON / 文件下载 / 重定向 / 204
 *    这类响应体是给机器解析的，附加调试面板会直接破坏响应。
 */
class Response
{
    /**
     * 响应状态码
     * @var int
     */
    protected $statusCode = 200;

    /**
     * 响应头（键保留调用方写法，查找时大小写不敏感）
     * @var array
     */
    protected $headers = [];

    /**
     * 响应体
     * @var string
     */
    protected $content = '';

    /**
     * 待发送的 Cookie，元素为 [键名, 值, 选项]，
     * 在 send() 时统一交给 Cookie::set()（复用其前缀 / secure auto / SameSite 安全默认）
     * @var array
     */
    protected $cookies = [];

    /**
     * 待流式输出的文件真实路径（download() 设置，send() 时才 readfile）
     * @var string|null
     */
    protected $file = null;

    /**
     * 下载文件名（用于 Content-Disposition）
     * @var string|null
     */
    protected $fileName = null;

    /**
     * 是否已发送
     * @var bool
     */
    protected $sent = false;

    /**
     * trace 开关：null = 按响应类型自动判定，false = 强制不附加
     * @var bool|null
     */
    protected $trace = null;

    /* ==================== 既有 API（签名保持不变） ==================== */

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
     *
     * 幂等：重复调用只发送一次。
     * @return void
     */
    public function send()
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        // 响应头必须在产生任何输出之前设置；控制器若已 echo，
        // 这里整体降级跳过（而不是抛警告 —— 警告会被 handleError 提升成 ErrorException）
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $key => $value) {
                header($key . ': ' . $value);
            }
            foreach ($this->cookies as $cookie) {
                Cookie::set($cookie[0], $cookie[1], $cookie[2]);
            }
        }

        if ($this->file !== null) {
            // 流式输出，避免把大文件读进内存
            readfile($this->file);
        } else {
            echo $this->content;
        }

        if ($this->shouldTrace()) {
            App::outputTrace();
        }
    }

    /**
     * 返回JSON响应
     * @param mixed $data 数据
     * @param int $status 状态码
     * @return $this
     */
    public function json($data, $status = 200)
    {
        // 与 Controller::json 统一为同一实现：带 charset、中文不转义
        // （改造前这里只有 application/json 且不传 JSON_UNESCAPED_UNICODE，是两套逻辑）
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->setStatusCode($status);
        $this->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this;
    }

    /* ==================== 新增：批量 / 查询 ==================== */

    /**
     * 批量设置响应头
     * @param array $headers 头名 => 头值
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
        return $this;
    }

    /**
     * 获取状态码
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * 获取全部响应头
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * 获取指定响应头（大小写不敏感）
     * @param string $key 头名
     * @return string|null 不存在返回 null
     */
    public function getHeader($key)
    {
        $lower = strtolower($key);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lower) {
                return $v;
            }
        }
        return null;
    }

    /**
     * 获取响应内容
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * 获取 Content-Type 的媒体类型部分（不含 charset 等参数，小写）
     * @return string 未设置时返回空串
     */
    public function getContentType()
    {
        $value = $this->getHeader('Content-Type');
        if ($value === null) {
            return '';
        }
        $pos = strpos($value, ';');
        return strtolower(trim($pos === false ? $value : substr($value, 0, $pos)));
    }

    /**
     * 是否已发送
     * @return bool
     */
    public function isSent()
    {
        return $this->sent;
    }

    /* ==================== 新增：响应类型 ==================== */

    /**
     * 纯文本响应
     * @param string $content 内容
     * @param int $status 状态码
     * @return $this
     */
    public function text($content, $status = 200)
    {
        return $this->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setStatusCode($status)
            ->setContent($content);
    }

    /**
     * HTML 响应
     * @param string $content 内容
     * @param int $status 状态码
     * @return $this
     */
    public function html($content, $status = 200)
    {
        return $this->setHeader('Content-Type', 'text/html; charset=utf-8')
            ->setStatusCode($status)
            ->setContent($content);
    }

    /**
     * 无内容响应（默认 204）
     * @param int $status 状态码
     * @return $this
     */
    public function noContent($status = 204)
    {
        return $this->setStatusCode($status)->setContent('');
    }

    /**
     * 重定向响应
     * @param string $url 目标地址
     * @param int $status 状态码（301 永久 / 302 临时 / 303 / 307 / 308）
     * @return $this
     */
    public function redirect($url, $status = 302)
    {
        $this->setStatusCode($status)->setHeader('Location', $url);
        return $this;
    }

    /**
     * 文件下载响应（send() 时流式输出，不把文件读进内存）
     * @param string $file 文件真实路径
     * @param string|null $name 下载时展示的文件名，默认取 basename($file)
     * @param string|null $mime MIME 类型，默认 application/octet-stream
     * @return $this
     * @throws \RuntimeException 文件不存在或不可读时抛出（属于调用方错误，fail-fast）
     */
    public function download($file, $name = null, $mime = null)
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('下载文件不存在或不可读：' . $file);
        }

        $name = $name !== null ? $name : basename($file);

        $this->file = $file;
        $this->fileName = $name;
        $this->trace = false;   // 二进制响应体绝不能再附加调试面板
        $this->setStatusCode(200);
        $this->setHeader('Content-Type', $mime !== null ? $mime : 'application/octet-stream');
        $this->setHeader('Content-Length', (string) filesize($file));
        // 同时给出 ASCII 回退名与 RFC 5987 的 UTF-8 名，中文文件名在各浏览器下都能正确显示
        $this->setHeader(
            'Content-Disposition',
            'attachment; filename="' . $this->escapeFileName($name) . '"'
            . "; filename*=UTF-8''" . rawurlencode($name)
        );

        return $this;
    }

    /**
     * 收集一个待发送的 Cookie
     *
     * 键名会带上 config/common.php 的 cookie_prefix（与 Cookie::set 行为一致）；
     * 安全默认值（secure auto 检测、SameSite=Lax、HttpOnly）同样由 Cookie::set 提供。
     *
     * @param string $key 键名
     * @param string $value 值
     * @param array $options 选项，含义同 Cookie::set()：expire / path / domain / secure / httponly / samesite
     * @return $this
     */
    public function cookie($key, $value, array $options = [])
    {
        $this->cookies[] = [$key, $value, $options];
        return $this;
    }

    /**
     * 显式控制是否附加 trace 面板
     *
     * 传 false 用于「内容已直接输出」等不希望被追加调试信息的场景；
     * 传 true 可覆盖按 Content-Type 的自动判定。
     * 注意：无响应体的响应（204 / 304 / 3xx）与文件下载**始终**不附加，显式开关也不能覆盖。
     *
     * @param bool $allow 是否允许附加
     * @return $this
     */
    public function withTrace($allow = true)
    {
        $this->trace = (bool) $allow;
        return $this;
    }

    /* ==================== 内部实现 ==================== */

    /**
     * 本次响应是否应当附加 trace 面板
     *
     * 判定顺序：SAPI / 无响应体 → 显式开关 → 配置 → 响应类型。
     * @return bool
     */
    protected function shouldTrace()
    {
        // CLI 下没有浏览器可看；同时可避免测试脚本里 trace 模板读取 $_SERVER 报错
        if (PHP_SAPI === 'cli' || !$this->allowsTracePanel()) {
            return false;
        }

        // 显式开关优先于按响应类型的自动判定
        if ($this->trace !== null) {
            return $this->trace;
        }

        return (bool) config('trace') && $this->isHtmlResponse();
    }

    /**
     * 本响应是否属于「允许附加调试面板」的类型
     *
     * 无响应体的响应（204 / 304 / 3xx）与文件下载一律不允许 ——
     * 往这些响应体里塞 HTML 属协议违规，会破坏客户端解析。
     * @return bool
     */
    protected function allowsTracePanel()
    {
        if ($this->file !== null) {
            return false;
        }
        if ($this->statusCode === 204 || $this->statusCode === 304) {
            return false;
        }
        if ($this->statusCode >= 300 && $this->statusCode < 400) {
            return false;
        }
        return true;
    }

    /**
     * 响应体是否按 HTML 页面处理
     *
     * 未声明 Content-Type 时视为 HTML（浏览器默认行为）；
     * 声明了则只有 HTML 类媒体类型算页面，JSON / 纯文本 / 二进制都不算。
     * @return bool
     */
    protected function isHtmlResponse()
    {
        $type = $this->getContentType();
        return $type === '' || strpos($type, 'html') !== false;
    }

    /**
     * 清理下载文件名中的换行与引号，防止响应头注入
     * @param string $name 原始文件名
     * @return string
     */
    protected function escapeFileName($name)
    {
        return str_replace(['"', "\r", "\n"], '', $name);
    }
}
