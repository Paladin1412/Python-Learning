<?php
/***************************************************************************
 *
* Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

/**
 *
 * 资源服务过滤
 *
 *
 * @author fanwenli
 * @path("/res/")
 */
class Res
{   
    /**
     *
     */
    function __construct() {
        
    }
    
    /**
     *
     * @route({"GET","/list/*\?"})
     * @param({"path", "$.path"}) string $path 路径
     *
     * 资源服务过滤
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
        }
     */
    public function resList($path = array()) {
        $querystring = '';
        
        if(isset($_GET['onlycontent'])) {
            $querystring .= 'onlycontent=' . $_GET['onlycontent'];
        }
        
        if(isset($_GET['withcontent'])) {
            $querystring .= 'withcontent=' . $_GET['withcontent'];
        }
        
        if(isset($_GET['search'])) {
            $querystring .= '&search=' . $_GET['search'];
        }
        
        if(isset($_GET['searchbyori'])) {
            $querystring .= '&searchbyori=' . $_GET['searchbyori'];
        }
        
        if(isset($_GET['limit'])) {
            $querystring .= '&limit=' . $_GET['limit'];
        }
        
        if(isset($_GET['sort'])) {
            $querystring .= '&sort=' . $_GET['sort'];
        }
        
        //delete array of top 2
        array_shift($path);
        array_shift($path);
        
        //request url
        $url = '/' . implode('/',$path) . '/?' . $querystring;
        $res = $this->getResList($url);
        
        //filter the ads
		$res = Util::filterAds($res);
		
		return $res;
    }
    
    /**
     * 获取Res request信息
     * @param $url 请求url
     * @return
     */
    public function getResList($url = '') {
        $ads_cache_key = __Class__ . '_res_list_data_cachekey_' . md5($url);
        $ads_url = $url;
        $ads_res = Util::ralGetContent($ads_url,$ads_cache_key,GFunc::getCacheTime('ads_cache_time'));
        
		return $ads_res;
	}
}
