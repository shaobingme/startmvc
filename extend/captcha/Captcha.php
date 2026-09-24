<?php
/**
 * StartMVC 官方扩展 —— 图片验证码
 *
 * 可选扩展（extend 层），不声明就不加载。绘制需 GD + FreeType，校验只用 Session。
 *
 * @package extend\captcha
 * @license Apache-2.0（本类）；随附字体 SIL OFL 1.1，见同目录 OFL.txt
 */

namespace extend\captcha;

use startmvc\core\Session;

class Captcha
{
    /* ---------- 外观 ---------- */

    /** @var int 图片宽 */
    public $width = 120;
    /** @var int 图片高 */
    public $height = 30;
    /** @var int 字符个数 */
    public $codelen = 5;
    /** @var int|null 字号，null = 按画布高度与单格宽度自动推算 */
    public $fontsize = null;
    /** @var string|null 字体绝对路径，null = 同目录 captcha.ttf */
    public $font = null;
    /** @var string 候选字符集（已剔除 i l o I L O 0 1 等易混字符） */
    public $charset = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /* ---------- 策略：出图时写进会话，随验证码一起走 ---------- */

    /** @var string 会话键名 */
    public $sessionKey = 'captcha';
    /** @var int 有效期（秒），0 = 不过期 */
    public $expire = 300;
    /** @var int 同一张图的最大尝试次数，0 = 不限；达到即作废，强制换图 */
    public $maxAttempts = 5;
    /** @var bool 是否区分大小写。默认 false，避免移动端首字母自动大写导致失败 */
    public $caseSensitive = false;

    /** @var string 当前验证码，与图上逐字符一致（不做大小写转换） */
    protected $code = '';

    /**
     * @param int $codelen 字符个数
     * @param int $width   宽
     * @param int $height  高
     * @throws \RuntimeException 缺少 GD / FreeType 时抛出
     */
    public function __construct($codelen = 5, $width = 120, $height = 30)
    {
        if (!self::isSupported()) {
            throw new \RuntimeException('无法生成图片验证码：需要 GD 扩展且带 FreeType 支持（imagettftext）');
        }
        $this->codelen = max(1, (int)$codelen);
        $this->width   = max(30, (int)$width);
        $this->height  = max(16, (int)$height);
    }

    /**
     * 运行环境是否支持（供应用做能力探测，避免装完才发现用不了）
     * @return bool
     */
    public static function isSupported()
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext');
    }

    /**
     * 当前验证码原文（含大写，与图上一致）
     * @return string
     */
    public function code()
    {
        return $this->code;
    }

    /**
     * 渲染 PNG 并返回二进制（不输出、不设 header，便于单测 / 存文件 / 塞 Response）
     *
     * 首次调用会生成验证码并写入会话；同一实例重复调用则重绘同一张图。
     *
     * @return string
     * @throws \RuntimeException 字体不可用时抛出
     */
    public function render()
    {
        if ($this->code === '') {
            $this->generate();
        }
        $img = $this->draw();

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    /**
     * 直接输出图片（HTTP 用），并补上 no-store 系列响应头
     *
     * 旧版没设缓存头，浏览器会缓存验证码图，所以旧文档才要在 <img> 上写
     * onclick="this.src='?'+Math.random()" —— 现在不需要那个 hack。
     *
     * @return $this
     */
    public function output()
    {
        $png = $this->render();
        if (!headers_sent()) {
            header('Content-Type: image/png');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        echo $png;
        return $this;
    }

    /**
     * output() 的别名，只为兼容既有教程里的 `$c->img()` 写法
     * @return $this
     */
    public function img()
    {
        return $this->output();
    }

    /**
     * 校验用户输入（一次性）
     *
     * 无记录（未出图 / 已用过 / 已作废）→ 失败；过期 → 作废并失败；
     * 次数已达上限 → 作废并失败；比对成功 → 立即作废（不可重放）→ 成功；
     * 比对失败 → 次数 +1，达到上限则作废，否则保留让用户重输。
     *
     * @param mixed  $input 用户提交值
     * @param string $key   会话键名，与出图时的 sessionKey 一致
     * @return bool
     */
    public static function check($input, $key = 'captcha')
    {
        $rec = Session::get($key);
        if (!is_array($rec) || empty($rec['code']) || !is_scalar($input)) {
            return false;
        }
        $max = (int)($rec['max'] ?? 0);
        $tries = (int)($rec['tries'] ?? 0);

        if (!empty($rec['expire']) && time() - (int)($rec['time'] ?? 0) > (int)$rec['expire']) {
            Session::delete($key);
            return false;
        }
        if ($max > 0 && $tries >= $max) {
            Session::delete($key);
            return false;
        }

        $input = (string)$input;
        $code  = (string)$rec['code'];
        if (empty($rec['case'])) {
            $input = strtolower($input);   // 字符集是纯 ASCII，无需 mb_ 版本
            $code  = strtolower($code);
        }
        if ($input !== '' && hash_equals($code, $input)) {
            Session::delete($key);         // 一次性：杜绝重放
            return true;
        }

        $rec['tries'] = $tries + 1;
        if ($max > 0 && $rec['tries'] >= $max) {
            Session::delete($key);         // 次数用尽，强制换图
        } else {
            Session::set($key, $rec);
        }
        return false;
    }

    /**
     * 主动作废当前验证码
     * @param string $key 会话键名
     * @return void
     */
    public static function clear($key = 'captcha')
    {
        Session::delete($key);
    }

    /* ---------- 内部 ---------- */

    /**
     * 生成验证码并写入会话
     * @return void
     */
    protected function generate()
    {
        $max  = strlen($this->charset) - 1;
        $code = '';
        for ($i = 0; $i < $this->codelen; $i++) {
            // random_int（CSPRNG）而非 mt_rand：后者输出可从少量样本反推种子
            $code .= $this->charset[random_int(0, $max)];
        }
        $this->code = $code;

        Session::set($this->sessionKey, [
            'code'   => $code,
            'time'   => time(),
            'tries'  => 0,
            'expire' => (int)$this->expire,
            'max'    => (int)$this->maxAttempts,
            'case'   => (bool)$this->caseSensitive,
        ]);
    }

    /**
     * 绘制整张图：背景 → 干扰 → 文字
     *
     * 亮度分三带互不打架：背景 200~255、干扰 120~190、文字 0~90。
     * （旧版干扰线与文字同为 0~156，线可以比字还深，直接吃掉可读性。）
     *
     * @return resource GD 图像句柄
     * @throws \RuntimeException 字体不可用时抛出
     */
    protected function draw()
    {
        $font = $this->font ?: __DIR__ . DIRECTORY_SEPARATOR . 'captcha.ttf';
        if (!is_file($font) || imagettfbbox(12, 0, $font, 'A') === false) {
            throw new \RuntimeException('验证码字体不可用：' . $font);
        }

        $w = $this->width;
        $h = $this->height;
        $img = imagecreatetruecolor($w, $h);

        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate(
            $img, random_int(200, 255), random_int(200, 255), random_int(200, 255)
        ));

        for ($i = 0; $i < 6; $i++) {
            $c = imagecolorallocate($img, random_int(120, 190), random_int(120, 190), random_int(120, 190));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        for ($i = 0; $i < 120; $i++) {
            $c = imagecolorallocate($img, random_int(190, 240), random_int(190, 240), random_int(190, 240));
            imagestring($img, random_int(1, 5), random_int(0, $w), random_int(0, $h), '*', $c);
        }

        // 文字：按每字形自身包围盒在本格内居中，旋转后夹回画布。
        // 旧版固定用 $_x*$i + mt_rand(1,5) 当起点，字形一旋转就会被边缘切掉。
        $len   = strlen($this->code);
        $pad   = max(2, (int)round($w * 0.03));
        $cell  = ($w - $pad * 2) / $len;
        $size  = $this->fontsize ?: max(10, min((int)round($h * 0.6), (int)round($cell * 0.95)));
        $ref   = imagettfbbox($size, 0, $font, 'H');
        $baseY = (int)round(($h + $ref[1] - $ref[7]) / 2);

        for ($i = 0; $i < $len; $i++) {
            $char  = $this->code[$i];
            $angle = random_int(-25, 25);
            // 旋转后的包围盒，四个角都要参与取极值：imagettfbbox 的
            // box[0]/box[1] 只是「左下」角，旋转后它未必是最左/最下的那个点
            // （实测只用 box[1] 当底边时，300 次里有 5 次字形会漏到最下一行）
            $box = imagettfbbox($size, $angle, $font, $char);
            $xs = [$box[0], $box[2], $box[4], $box[6]];
            $ys = [$box[1], $box[3], $box[5], $box[7]];
            $left = min($xs);
            $right = max($xs);
            $top = min($ys);
            $bottom = max($ys);

            // 水平：本格内居中 + 轻微抖动，再夹回画布并留 2px 边距
            $x = $pad + $cell * $i + ($cell - ($right - $left)) / 2 - $left + random_int(-2, 2);
            $x = max(2 - $left, min($w - 3 - $right, $x));

            // 垂直：以公共基线为准，越界则回收
            $y = $baseY;
            if ($y + $top < 2) {
                $y = 2 - $top;
            } elseif ($y + $bottom > $h - 3) {
                $y = $h - 3 - $bottom;
            }

            $c = imagecolorallocate($img, random_int(0, 90), random_int(0, 90), random_int(0, 90));
            imagettftext($img, $size, $angle, (int)$x, (int)$y, $c, $font, $char);
        }

        return $img;
    }
}
