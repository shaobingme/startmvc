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
    public $fileName='';

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
            return ['result' => false, 'error' => 'File upload error: ' . $file['error']];
        }

        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array($fileExt, $this->exts)) {
            return ['result' => false, 'error' => 'Invalid file extension'];
        }

        $saveDir = rtrim($this->savePath, '/') . '/';
        $saveUrl = rtrim($this->urlPath, '/') . '/';

        if ($this->autoSub) {
            $subDir = date('Y/m/d');
            $saveDir .= $subDir;
            $saveUrl .= $subDir;
        }
        if (!is_dir($saveDir)&&!mkdir($saveDir, 0755, true)) {
            return ['result' => false, 'error' => 'Failed to create directory'];
        }

        //$filename = $this->autoName ? uniqid() . '.' . $fileExt : $file['name'];
        $filename = $this->fileName !== '' ? $this->fileName.'.'. $fileExt : ($this->autoName ? uniqid() . '.' . $fileExt : $file['name']);
        $filePath = $saveDir . '/' . $filename;
        $urlPath = $saveUrl . '/' . $filename;

        if (!$this->replace && file_exists($filePath)) {
            return ['result' => false, 'error' => 'File already exists'];
        }

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['result' => false, 'error' => 'Failed to move uploaded file'];
        }

        return ['result' => true, 'url' => $urlPath,'filename'=>$filename];
    }
}