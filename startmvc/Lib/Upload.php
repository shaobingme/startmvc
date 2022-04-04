<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Lib;
class Upload{
    public $maxSize, $exts, $savePath, $urlPath, $autoSub, $autoName, $replace;
    function __construct(array $config = []){
        $this->maxSize = isset($config['maxSize']) ? $config['maxSize'] : 2097152;
        $this->exts = isset($config['exts']) ? $config['exts'] : ['jpg', 'gif', 'png', 'jpeg'];
        $this->savePath = isset($config['savePath']) ? $config['savePath'] : '/wwwroot/upload';
        $this->urlPath = isset($config['urlPath']) ? $config['urlPath'] : '/upload';
        $this->autoSub = isset($config['autoSub']) ? $config['autoSub'] : true;
        $this->autoName = isset($config['autoName']) ? $config['autoName'] : true;
        $this->replace = isset($config['replace']) ? $config['replace'] : true;
    }
    function upload(){
        $results = [];
        foreach($_FILES as $file){
            if(is_array($file['name'])){
                $files = [];
                for($i = 0; $i < count($file['name']); $i++){
                    foreach($file as $k => $v){
                        $files[$i][$k] = $v[$i];
                    }
                }
                foreach($files as $f){
                    $results[] = $this->file($f);
                }
            }else{
                $results[] = $this->file($file);    
            }            
        }
        return $results;
    }
    private function file($file){
        if(isset($file['error']) && $file['error'] != 0){
            switch($file['error']){
                case 1:
                    return ['result' => false, 'error' => '超过php.ini允许的大小'];
                    break;
                case 2:
                    return ['result' => false, 'error' => '超过服务器允许上传的大小'];
                    break;
                case 3:
                    return ['result' => false, 'error' => '文件上传不完整'];
                    break;
                case 4:
                    return ['result' => false, 'error' => '本地文件不存在'];
                    break;
                case 6:
                    return ['result' => false, 'error' => '找不到临时目录'];
                    break;
                case 7:
                    return ['result' => false, 'error' => '写文件到硬盘出错'];
                    break;
                case 8:
                    return ['result' => false, 'error' => '文件上传中断'];
                    break;
                default:
                    return ['result' => false, 'error' => '未知错误'];
            }
        }else{
            if($file['size'] > $this->maxSize)
                return ['result' => false, 'error' => '上传文件大小超过限制'];
            if(!is_uploaded_file($file['tmp_name']))
                return ['result' => false, 'error' => '上传文件错误'];
            $fileExt = $this->getExt($file);
            if(!in_array($fileExt, $this->exts))
                return ['result' => false, 'error' => '上传文件扩展名是不允许的扩展名'];
            $saveDir = $this->savePath . '/';
            $saveUrl = $this->urlPath . '/';
            if($this->autoSub){
                $subDir = date('Y') . '/' . date('m') . '/' .date('d') . '/';
                $saveDir .= $subDir;
                $saveUrl .= $subDir;
            }
            if(!$this->mkd($saveDir))
                return ['result' => false, 'error' => '上传目录没有写权限。'];
            if($this->autoName)
                $filename = date('YmdHis') . rand(100, 999) . '.' . $fileExt;
            else
                $filename = $file['name'];
            $filePath = ROOT_PATH . '/' . $saveDir . $filename;
            $urlPath = $saveUrl . $filename;
            if(!$this->replace && file_exists($filePath))
                return ['result' => false, 'error' => '文件已存在'];
            if(!move_uploaded_file($file['tmp_name'], $filePath))
                return ['result' => false, 'error' => '上传失败'];
            return ['result' => true, 'url' => $urlPath];
        }
    }
    private function getExt($file){
        $tempArr = explode('.', $file['name']);
        return $tempArr[count($tempArr) - 1];
    }
    private function mkd($dir){
        if(is_dir($dir))
            return true;
        $dirArr = explode('/', $dir);
        $dirPath = ROOT_PATH;
        foreach($dirArr as $d){
            if($d != ''){
                $dirPath .= '/' . $d;
                if(!is_dir($dirPath)){
                    if(!mkdir($dirPath))
                        return false;
                }
            }
        }
        return true;
    }
}