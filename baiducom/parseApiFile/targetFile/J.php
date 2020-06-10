<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
use utils\GFunc;

/**
 *
 * j
 * 说明：页面跳转接口  迁自V4
 *
 * @author zhoubin
 * @path("/j/")
 */
class J
{
    /**
     * 手机输入法在移动搜索中的渠道号
     *
     * @var string
     */
    const IME_CHANNEL_IN_WISE = '1001560r';
    
     /**
     * @route({"GET","/app"})
     * 应用推荐app下载链接跳转方法 
     * @return
     */
    public function appAction () {
        $applink = trim($_REQUEST['applink']);
        if ($applink !== '') {               
            if (substr($applink, 0, 19) === 'http://mo.baidu.com') {
                header("location:".$applink);
                exit();
            } elseif (substr($applink, 0, 18) === 'http://m.baidu.com') {
                header("location:".$applink);
                exit();
            } elseif (substr($applink, 0, 19) === 'http://bs.baidu.com') {
                header("location:".$applink);
                exit();
            } elseif (substr($applink, 0, 19) === 'http://m.hao123.com') {
                header("location:".$applink);
                exit();
            }
        }
        
        header("location:http://mo.baidu.com/");
        exit();
    }
    
    
     /**
     * @route({"GET","/s"})
     * 流行词（搜索）链接跳转方法 
     * @return
     */
    public function sAction () {
        if(isset($_GET['url'])){
            $url=$_GET['url'];
            if(substr($url, 0,19)==='http://m.baidu.com/' 
            || substr($url, 0,20)==='http://hd.baidu.com/' 
            || substr($url, 0,23)==='http://hd.mi.baidu.com/'
            || substr($url, 0,23)==='http://huati.weibo.com/'
            || substr($url, 0,18)==='http://m.weibo.cn/'
            || substr($url, 0,23)==='http://tieba.baidu.com/'
            || substr($url, 0,24)==='http://itunes.apple.com/'
            || substr($url, 0,20)==='http://f.app111.org/'
            || substr($url, 0,16)==='itms-services://'
            || substr($url, 0,18)==='http://m.nadoo.cn/'
            || substr($url, 0,20)==='http://51.baidu.com/'
            || substr($url, 0,8)==='cydia://'
            || substr($url, 0,14)==='http://dwz.cn/'){
                header("location:" . $url);
                exit();
            }
        }
        header("location:http://m.baidu.com/?from=1000a");
        exit();
    }
    
     
     /**
     * 分享链接跳转方法
     * @route({"GET","/share"})
     * 流行词（搜索）链接跳转方法 
     * @return
     */
    public function shareAction(){
        $link = trim($_REQUEST['link']);
        if ($link !== '') {
            
            if($this->isPC()){
                //PC机跳转分享包的pc下载地址
                header("location:$link");
                exit();
            }else{
            
                //ios跳转safari
                if (($this->isiPhone() || $this->isiPad() )) {
                    header("location:$link");
                    exit();
                }
                //其他跳转分享包的pc下载地址
                header("location:$link");
                exit();
            }
        }
        header("location:$link");
        exit();
    }
    
   
    /**
    * 判断是否PC
    * @return boolean
    */
    function isPC(){
        if(isset($_SERVER['HTTP_USER_AGENT'])){
            $browserinfo = strtolower($_SERVER['HTTP_USER_AGENT']);
    
            if(strpos($browserinfo, 'windows nt') !== false || (strpos($browserinfo,'macintosh') !== false 
            && strpos($browserinfo,'mac os') !== false)){
                return true;
            }
        }
        return false;
    }
    
    /**
    * 判断是否pad
    * @return boolean
    */
    function isiPad(){
        if(isset($_SERVER['HTTP_USER_AGENT'])){
            $browserinfo = strtolower($_SERVER['HTTP_USER_AGENT']);
    
            if(strpos($browserinfo,'ipad') !== false && strpos($browserinfo,'mac os') !== false){
                return true;
            }
        }
        return false;
    }
    
    /**
    * 判断是否手机
    * @return boolean
    */
    function isiPhone(){
        if(isset($_SERVER['HTTP_USER_AGENT'])){
            $browserinfo = strtolower($_SERVER['HTTP_USER_AGENT']);
    
            if(strpos($browserinfo,'iphone') !== false && strpos($browserinfo,'mac os') !== false){
                return true;
            }
    
        }
        return false;
    }  
}
    