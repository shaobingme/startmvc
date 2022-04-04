<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Lib\Http;

class Csrf
{
    public static function token(){
        $token = base64_encode(md5(time() . rand(1000,9999)));
        Session::set('csrf_token', $token);
        return $token;
    }
    public static function check(){
        if (Request::post('__csrf_token__') == '' || Request::post('__csrf_token__') != Session::get('csrf_token')) {
            return false;
        } else {
            return true;
        }
    }
    public static function unsetToken(){
        Session::delete('csrf_token');
    }
}