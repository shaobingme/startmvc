<?php
/**
 * StartMVC 官方扩展 —— 图片验证码助手函数
 *
 * 随扩展目录自包含：删除 extend/captcha/ 即彻底卸载（类、字体、函数一并移除）。
 * 由 App::loadUserFunctions() 统一加载（boot.php），CLI 与 HTTP 均可用。
 *
 * 在 Validator 里可以直接把函数名当规则名用：
 *
 *   $rules = ['yzm' => 'required|captcha'];
 *   $rules = ['yzm' => 'required|captcha``验证码不正确``'];   // 自定义错误文案
 *
 * 说明：本文件不定义类，只把 \extend\captcha\Captcha::check() 暴露成一个
 * 全局函数名——这是 Validator 唯一能「零 core 改动」接入自定义规则的途径
 * （另一条路 Validator::setTagMap() 是实例级的，Model 的声明式 $rules 用不上）。
 *
 * @package startmvc\extend\captcha
 */

if (!function_exists('captcha')) {
    /**
     * 校验图片验证码
     *
     * 一次性：校验通过后立即作废，同一张验证码不可重放。
     * 失败会累加尝试次数，达到上限后作废（强制换图）。
     *
     * @param mixed $value 用户提交的验证码
     * @return bool
     */
    function captcha($value)
    {
        return \extend\captcha\Captcha::check($value);
    }
}
