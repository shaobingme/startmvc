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
class Pagination{
    public $theme,$header,$first,$last,$prev,$next,$currentClass;
    function __construct (array $config=[]){
	    $config = include '../config/pagination.php';
    	$this->theme =isset($config['theme'])?$config['theme']:'%header% %first% %prev% %link% %next% %last%';
    	$this->header =isset($config['header'])?$config['header']:'共 %count% 条记录 第 %page% / %pageCount% 页';
    	$this->first =isset($config['first'])?$config['first']:'首页';
    	$this->last =isset($config['last'])?$config['last']:'末页';
    	$this->prev =isset($config['prev'])?$config['prev']:'上一页';
    	$this->next =isset($config['next'])?$config['next']:'下一页';
    	$this->currentClass =isset($config['currentClass'])?$config['currentClass']:'current';
    }
    function Show($count, $pageSize, $page, $url, $pageShowCount = 10){
        $pageCount = ceil($count / $pageSize);
        $header = '<a>' . str_replace(['%count%', '%page%', '%pageCount%'], [$count, $page, $pageCount], $this->header) . '</a>';
        $first = '<a href="' . $this->url($url, 1) . '">' . $this->first . '</a>';
        $last = '<a href="' . $this->url($url, $pageCount) . '">' . $this->last . '</a>';
        $prev = '<a' . ($page > 1 ? ' href="' . $this->url($url, $page - 1) . '"' : '') . '>' . $this->prev . '</a>';
        $next = '<a' . ($page < $pageCount ? ' href="' . $this->url($url, $page + 1) . '"' : '') . '>' . $this->next . '</a>';

        $link = '';
        $start = $page - $pageShowCount / 2;
        $start = $start < 1 ? 1 : $start;
        $end = $start + $pageShowCount - 1;
        if($end > $pageCount){
            $end = $pageCount;
            $start = $end - $pageShowCount + 1;
            $start = $start < 1 ? 1 : $start;
        }
        for($p = $start; $p <= $end; $p++){
            $link .= '<a';
            if($page == $p)
                $link .= ' class="'. $this->currentClass . '"';
            else
                $link .= ' href="' . $this->url($url, $p) . '"';
            $link .= '>' . $p . '</a>';
        }
        return str_replace([
            '%header%', '%first%', '%prev%', '%link%', '%next%', '%last%'
        ], [
            $header, $first, $prev, $link, $next, $last
        ], $this->theme);
    }
    function url($url, $page){
        return str_replace('{page}', $page, urldecode($url));
    }
}