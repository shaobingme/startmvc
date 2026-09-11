<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

namespace startmvc\core\console;

/**
 * 命令行输出
 *
 * 统一负责终端排版：着色、按显示宽度对齐、表格。
 * 颜色按终端能力自动降级：输出被重定向/管道、或终端不支持 ANSI 时
 * 退回纯文本，避免在日志与文件中留下转义序列。
 */
class Output
{
    /**
     * 是否输出 ANSI 颜色
     * @var bool
     */
    protected $color;

    /**
     * @param bool|null $color 显式指定是否着色；null 表示按终端能力自动检测
     */
    public function __construct($color = null)
    {
        $this->color = ($color === null) ? self::detectColor() : (bool)$color;
    }

    /**
     * 输出一行
     * @param string $text
     * @return void
     */
    public function line($text = '')
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    /**
     * 输出空行
     * @return void
     */
    public function blank()
    {
        $this->line();
    }

    /**
     * 成功 / 正常提示（绿色）
     * @param string $text
     * @return void
     */
    public function info($text)
    {
        $this->line($this->paint($text, '32'));
    }

    /**
     * 警告（黄色）
     * @param string $text
     * @return void
     */
    public function warn($text)
    {
        $this->line($this->paint($text, '33'));
    }

    /**
     * 错误（红色）
     * @param string $text
     * @return void
     */
    public function error($text)
    {
        $this->line($this->paint($text, '31'));
    }

    /**
     * 次要信息（灰色）
     * @param string $text
     * @return void
     */
    public function muted($text)
    {
        $this->line($this->paint($text, '90'));
    }

    /**
     * 输出表格
     *
     * 按「显示宽度」对齐——中文等全角字符按 2 列计算，避免列错位。
     *
     * @param array $headers 表头
     * @param array $rows 数据行，每行为索引数组
     * @return void
     */
    public function table(array $headers, array $rows)
    {
        $matrix = array_merge([array_values($headers)], array_map('array_values', $rows));

        $columns = 0;
        foreach ($matrix as $row) {
            $count = count($row);
            if ($count > $columns) {
                $columns = $count;
            }
        }

        // 逐列求最大显示宽度
        $widths = array_fill(0, $columns, 0);
        foreach ($matrix as $row) {
            for ($i = 0; $i < $columns; $i++) {
                $cell = isset($row[$i]) ? (string)$row[$i] : '';
                $width = $this->width($cell);
                if ($width > $widths[$i]) {
                    $widths[$i] = $width;
                }
            }
        }

        $divider = '+' . implode('+', array_map(function ($w) {
            return str_repeat('-', $w + 2);
        }, $widths)) . '+';

        $this->line($divider);
        $this->line($this->renderRow(array_values($headers), $widths));
        $this->line($divider);
        foreach ($rows as $row) {
            $this->line($this->renderRow(array_values($row), $widths));
        }
        $this->line($divider);
    }

    /**
     * 渲染一行单元格
     * @param array $cells
     * @param array $widths
     * @return string
     */
    protected function renderRow(array $cells, array $widths)
    {
        $out = [];
        foreach ($widths as $i => $width) {
            $cell = isset($cells[$i]) ? (string)$cells[$i] : '';
            $padding = $width - $this->width($cell);
            $out[] = ' ' . $cell . str_repeat(' ', $padding > 0 ? $padding : 0) . ' ';
        }
        return '|' . implode('|', $out) . '|';
    }

    /**
     * 字符串在终端中的显示宽度（全角字符按 2 计）
     * @param string $text
     * @return int
     */
    protected function width($text)
    {
        if (function_exists('mb_strwidth')) {
            return mb_strwidth($text, 'UTF-8');
        }
        return strlen($text);
    }

    /**
     * 按 ANSI 转义着色
     * @param string $text
     * @param string $code
     * @return string
     */
    protected function paint($text, $code)
    {
        if (!$this->color) {
            return $text;
        }
        return "\033[{$code}m{$text}\033[0m";
    }

    /**
     * 自动检测终端是否支持 ANSI 颜色
     *
     * 输出被重定向到文件或管道时不着色，避免日志里混入转义序列。
     *
     * @return bool
     */
    protected static function detectColor()
    {
        // 尊重 NO_COLOR 约定：设置该环境变量即关闭颜色
        if (getenv('NO_COLOR') !== false) {
            return false;
        }
        if (!defined('STDOUT')) {
            return false;
        }
        // 非终端输出（重定向、管道、被父进程捕获）不着色
        if (function_exists('stream_isatty') && !@stream_isatty(STDOUT)) {
            return false;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return true;
        }

        // Windows：尝试开启 VT100 转义支持（Win10+ 可用），失败则退回无颜色
        if (function_exists('sapi_windows_vt100_support')) {
            return @sapi_windows_vt100_support(STDOUT, true);
        }
        return false;
    }
}
