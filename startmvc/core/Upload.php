<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

namespace startmvc\core;

class Upload {
    public $maxSize = 2097152; // 2 MB
    public $exts = ['jpg', 'gif', 'png', 'jpeg'];
    public $savePath = BASE_PATH.'upload';
    public $urlPath = '/upload';
    public $autoSub = true;
    public $autoName = true;
    public $replace = true;
    public $fileName = '';

    /**
     * 是否校验文件真实内容（MIME 类型），默认开启。
     * 开启后可拦截“把 .php 改名为 .jpg”之类的伪装上传。
     */
    public $checkMime = true;

    /**
     * 扩展名 => 允许的真实 MIME 类型白名单。
     * 注意：向 $exts 添加新扩展名时，必须同步在此添加对应 MIME 映射，
     * 否则该扩展名无法通过内容校验，会被拒绝（安全默认策略）。
     */
    public $mimes = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'png'  => ['image/png'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        'webp' => ['image/webp'],
    ];

    /**
     * 图片类扩展名：使用 getimagesize() 解析图片结构进行校验，
     * 比 finfo 仅检查文件头魔数更严格。
     */
    public $imageExts = ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp'];

    /**
     * 是否对图片进行二次编码（GD 重绘后重新保存），默认关闭。
     * 开启后可彻底清除图片中嵌入的非图像数据（polyglot 文件里夹带的 PHP 代码等），
     * 是对抗“合法图片 + 恶意代码”混合型文件的最严格手段；
     * 代价是丢失 EXIF 信息与 GIF 动画，且需要 GD 扩展（GD 缺失时上传将被拒绝）。
     */
    public $reencode = false;

    function __construct(array $config = []) {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    function upload() {
        $results = [];
        foreach ($_FILES as $file) {
            if (is_array($file['name'])) {
                foreach ($file['name'] as $key => $value) {
                    $fileInfo = [];
                    foreach ($file as $k => $v) {
                        $fileInfo[$k] = $v[$key];
                    }
                    $results[] = $this->file($fileInfo);
                }
            } else {
                $results[] = $this->file($file);
            }
        }
        return $results;
    }

    private function file($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['result' => false, 'error' => '文件上传错误： ' . $file['error']];
        }

        // 文件大小校验（$maxSize 属性原本定义了但从未生效）
        if ($this->maxSize > 0 && $file['size'] > $this->maxSize) {
            return ['result' => false, 'error' => '文件大小超出限制'];
        }

        // 扩展名校验：统一小写 + 严格比较，防大小写绕过
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $this->exts, true)) {
            return ['result' => false, 'error' => '无效的文件扩展名'];
        }

        // 真实内容校验：防止将 .php 等危险文件改后缀伪装上传
        if ($this->checkMime && !$this->checkMimeType($file['tmp_name'], $fileExt)) {
            return ['result' => false, 'error' => '文件内容与扩展名不符，已拒绝'];
        }

        $saveDir = rtrim($this->savePath, '/') . '/';
        $saveUrl = rtrim($this->urlPath, '/') . '/';

        if ($this->autoSub) {
            $subDir = date('Y/m/d');
            $saveDir .= $subDir;
            $saveUrl .= $subDir;
        }
        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true)) {
            return ['result' => false, 'error' => '创建目录失败'];
        }

        // 生成安全的保存文件名：所有分支统一消毒，防路径穿越与双重扩展名
        if ($this->fileName !== '') {
            $base = $this->sanitizeFileName($this->fileName);
        } elseif ($this->autoName) {
            // 加密安全随机名，防上传路径被猜测/遍历
            $base = bin2hex(random_bytes(16));
        } else {
            $base = $this->sanitizeFileName(pathinfo($file['name'], PATHINFO_FILENAME));
        }
        if ($base === '') {
            $base = bin2hex(random_bytes(16));
        }
        // 最终扩展名一律使用已通过校验的小写扩展名，杜绝 evil.php.jpg 双重扩展名
        $filename = $base . '.' . $fileExt;
        $filePath = $saveDir . '/' . $filename;
        $urlPath = $saveUrl . '/' . $filename;

        if (!$this->replace && file_exists($filePath)) {
            return ['result' => false, 'error' => '文件已经存在'];
        }

        // 图片二次编码：重绘后重新保存，清除夹带的恶意数据
        if ($this->reencode && in_array($fileExt, ['jpg', 'jpeg', 'gif', 'png', 'webp'], true)) {
            if (!$this->reencodeImage($file['tmp_name'], $filePath, $fileExt)) {
                return ['result' => false, 'error' => '图片内容净化失败'];
            }
        } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['result' => false, 'error' => '移动上传文件失败'];
        }

        return ['result' => true, 'url' => $urlPath, 'filename' => $filename];
    }

    /**
     * 校验上传文件的真实内容是否与扩展名声称的类型一致
     *
     * @param string $tmpFile 上传临时文件路径
     * @param string $ext     已归一化（小写）的扩展名
     * @return bool
     */
    private function checkMimeType($tmpFile, $ext) {
        if (in_array($ext, $this->imageExts, true)) {
            // 图片：解析真实图片结构，仅伪造文件头（如 GIF89a 开头）无法通过
            $imgInfo = @getimagesize($tmpFile);
            if ($imgInfo === false) {
                return false;
            }
            $detected = $imgInfo['mime'];
        } else {
            // 其他类型：使用 finfo 检测真实 MIME 类型
            if (!function_exists('finfo_open')) {
                // 缺少 fileinfo 扩展时无法校验内容，安全起见拒绝
                return false;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);
        }

        // 未配置 MIME 白名单的扩展名一律拒绝（安全默认）
        if (empty($this->mimes[$ext])) {
            return false;
        }
        return in_array($detected, $this->mimes[$ext], true);
    }

    /**
     * 使用 GD 对图片重绘并重新编码保存，丢弃原文件中所有非图像数据
     *
     * @param string $tmpFile 上传临时文件路径
     * @param string $dest    保存目标路径
     * @param string $ext     已归一化（小写）的扩展名
     * @return bool
     */
    private function reencodeImage($tmpFile, $dest, $ext) {
        if (!function_exists('imagecreatefromstring')) {
            return false; // GD 扩展不可用，安全起见拒绝
        }
        $data = @file_get_contents($tmpFile);
        if ($data === false) {
            return false;
        }
        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return false;
        }
        imagesavealpha($img, true); // 保留 PNG/GIF/WEBP 透明通道
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $ok = imagejpeg($img, $dest, 90);
                break;
            case 'png':
                $ok = imagepng($img, $dest, 6);
                break;
            case 'gif':
                $ok = imagegif($img, $dest);
                break;
            case 'webp':
                $ok = function_exists('imagewebp') && imagewebp($img, $dest);
                break;
            default:
                $ok = false;
        }
        imagedestroy($img);
        return $ok;
    }

    /**
     * 消毒文件名：去除目录部分与危险字符，仅保留字母/数字/下划线/连字符/中文等安全字符，
     * 点号一律替换为下划线（扩展名由系统另行拼接，防止双重扩展名与路径穿越）。
     */
    private function sanitizeFileName($name) {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[^\w\-]/u', '_', $name);
        return trim($name, '_');
    }
}
