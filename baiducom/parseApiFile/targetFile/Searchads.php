<?php
/***************************************************************************
 *
* Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

/**
 *
 * 搜索词广告
 *
 *
 * @author fanwenli
 * @path("/search_ads/")
 */
class Searchads
{
    
    /**
     *
     */
    function __construct() {
        //set inputgd as follow
        $_GET['inputgd'] = 1;
    }


    /**
     *
     * @route({"GET","/list"})
     *
     * 搜索词列表
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
            "data": {b64(array_string)
            },
        }
     */
    public function showList() {
        $topbaiduHw_list_ph = new PhasterThread(array($this,"getTopbaiduHwList"), array());
        $searchKeywordPackage_ph = new PhasterThread(array($this,"getSearchKeywordPackage"), array());
        $ads_res_ph = new PhasterThread(array($this,"getAdsList"), array());
        
        //hotwords
        $topbaiduHw_list = $topbaiduHw_list_ph->join();
        //search keyword package
        $searchKeywordPackage_res = $searchKeywordPackage_ph->join();
        //ads resource
        $ads_res = $ads_res_ph->join();
		
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter);
		
		//merge skin list with ads
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads[$j]) || isset($topbaiduHw_list['keywords'][$k])) {
            if(isset($new_ads[$j])) {
                $merged[$j] = $new_ads[$j];
                $j++;
            } elseif(isset($topbaiduHw_list['keywords'][$k])) {
                $merged[$j] = $topbaiduHw_list['keywords'][$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $topbaiduHw_list['keywords'] = $merged;
        
        $package_name = '';
        foreach($searchKeywordPackage_res as $name) {
            $package_name = $name['package_name'];
        }
        
        $topbaiduHw_list['package'] = $package_name;
         
        return $topbaiduHw_list;
    }
    
    /**
     * 获取大搜热词
     * @return
     */
    public function getTopbaiduHwList() {
        $class = IoCload("TopbaiduHw");
        $list = $class->getTopbaiduHotwordsList();
        
        return $list;
    }
    
    /**
     * 获取广告信息
     * @return
     */
    public function getAdsList() {
        $ads_cache_key = __Class__ . '_ads_list_data_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":20}';
        $ads_res = Util::ralGetContent($ads_url,$ads_cache_key,GFunc::getCacheTime('ads_cache_time'));
		
		return $ads_res;
	}
	
	/**
     * 获取搜索词包名
     * @return
     */
    public function getSearchKeywordPackage() {
        $search_keyword_cache_key = __Class__ . '_search_keyword_list_data_cachekey';
        $search_keyword_url = '/res/json/input/r/online/open_search_word_package/?onlycontent=1';
        $search_keyword_res = Util::ralGetContent($search_keyword_url,$search_keyword_cache_key,GFunc::getCacheTime('ads_cache_time'));
		
		return $search_keyword_res;
	}
}
