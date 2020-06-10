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
use utils\CacheVersionSwitchScope;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

/**
 *
 * 颜文字广告
 *
 *
 * @author fanwenli
 * @path("/emoticon_ads/")
 */
class Emoticonads
{
    /** @property 数量 */
    private $num = 0;
    
    /** @property  */
    private $dsf = 0;
    
    /** @property 平台号*/
    private $platform = 'a1';
    
    /** @property 版本号*/
    private $ver_name = '6.0.0.0';
    
    /** @property */
    private $sf = 0;
    
    /**
     *
     */
    function __construct() {
        //set inputgd as follow
        $_GET['inputgd'] = 1;
        
        $this->num = intval($_GET['num']);
        
        $this->dsf = intval($_GET['dsf']);
        
        if(isset($_GET['platform'])) {
            $this->platform = trim($_GET['platform']);
        }
        
        if(isset($_GET['version'])) {
            $this->ver_name = trim($_GET['version']);
        }
        
        $this->sf = intval($_GET['sf']);
    }
    
    /**
     *
     * @route({"GET","/operate"})
     *
     * 颜文字banner广告位
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
            "domain": "http://10.58.19.57:8001/",
            "list": [
            {
                "pub_item_type": "ad",
                "title": "皮肤精品banner【固定case】",
                "ad_desc": "预下载",
                "ad_id": 490,
                "ad_zone": 3,
                "ad_pos": 0,
                "ad_provider": 1,
                "priority": 1,
                "ad_type": 1,
                "sttime": 1456761600,
                 "exptime": 1546099200,
                "rsc_network": 3,
                "image": {
                    "img_240": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/14778962295555.jpg",
                    "img_320": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/147789636929245.jpg",
                    "img_480": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/147789637345693.jpg",
                    "img_720": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/147789637600398.jpg"
                },
                "max_cnt": 1,
                "click_ad": {
                    "click_type": "website",
                    "website": {
                        "click_title": "发的发发呆的放大",
                        "click_client": "com.baidu.input",
                        "click_link": "http://mco.ime.shahe.baidu.com:8015/v5/trace?url=http%3A%2F%2Fcq02-mic-iptest.cq02.baidu.com%3A8104%2Ftools%2Fpredownload.php%3Flink%3Dhttp%253a%252f%252fbos.pgzs.com%252fsjapp91%252fpcsuite%252fplugin%252f91assistant_Android_1.apk%26package%3Dcom.oem91.market&ad_zone=3&ad_pos=0&ad_id=490&ad_type=ad&ad_event=1&sign=97fce8ae1e6d7543429719358d801095",
                        "click_web_download_package": "com.oem91.market",
                        "click_web_download_link": "http://bos.pgzs.com/sjapp91/pcsuite/plugin/91assistant_Android_1.apk",
                        "pre_down": 0
                    }
                },
                "rsc_noti_cnt": 0,
                "push_delay": 0
            },
            {
                "id": "30",
                "name": "瓜熟蒂落",
                "cateid": "opgsdl",
                "pic": "upload/imags/2016/05/25/6s4p6607e5.jpg",
                "pri": "100",
                "pub_item_type": "non_ad",
                "type": "list",
                "tag": "opgsdl"
            }]
        }
     */
    public function operate() {
        $ads_res_ph = new PhasterThread(array($this,"getOperateAllAdsList"), array());
        $emoticon_operate_ph = new PhasterThread(array($this,"getEmoticonOperateAll"), array());
        
        //ads resource
        $ads_res = $ads_res_ph->join();
        //emoticon operate resource
        $emoticon_operate_res = $emoticon_operate_ph->join();
        
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter);
		
		$j = 0;
		$k = 0;
		$merged = array();
		while(isset($new_ads[$j]) || isset($emoticon_operate_res['list'][$k])) {
		    if(isset($new_ads[$j])) {
		        $merged[$j] = $new_ads[$j];
		        $j++;
		    } elseif(isset($emoticon_operate_res['list'][$k])) {
		        if($emoticon_operate_res['list'][$k]['pub_item_type'] == 'prod_ad') {
		            $emoticon_operate_res['list'][$k]['ad_id'] = 0;
		            $emoticon_operate_res['list'][$k]['ad_zone'] = 26;
		            $emoticon_operate_res['list'][$k]['ad_pos'] = $j;
		            $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=26&ad_pos=' . $j;
		            
		            
		            switch($emoticon_operate_res['list'][$k]['type']) {
		                case 'detail':
		                    if(strpos($emoticon_operate_res['list'][$k]['data']['url'], '?') !== false) {
		                        $emoticon_operate_res['list'][$k]['data']['url'] .= '&' . $ad_info;
		                    } else {
		                        $emoticon_operate_res['list'][$k]['data']['url'] .= '?' . $ad_info;
		                    }
		                    break;
		                case 'list':
		                    if(strpos($emoticon_operate_res['list'][$k]['image'], '?') !== false) {
		                        $emoticon_operate_res['list'][$k]['image'] .= '&' . $ad_info;
		                    } else {
		                        $emoticon_operate_res['list'][$k]['image'] .= '?' . $ad_info;
		                    }
		                    break;
		            }
		        }
		        
		        $merged[$j] = $emoticon_operate_res['list'][$k];
		        $j++;
		        $k++;
		    } else {
		        break;
		    }
		}
		
		$emoticon_operate_res['list'] = $merged;
		
		return $emoticon_operate_res;
    }
    
    /**
     *
     * @route({"GET","/cate/*\/list"})
     * @param({"type", "$.path[2]"}) string $type 某自然分类
     *
     * 颜文字商店列表
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
            "domain": "http://10.58.19.57:8001/",
            "list": [
            {
                "pub_item_type": "ad",
                "title": "皮肤精品banner【固定case】",
                "ad_desc": "预下载",
                "ad_id": 490,
                "ad_zone": 3,
                "ad_pos": 0,
                "ad_provider": 1,
                "priority": 1,
                "ad_type": 1,
                "sttime": 1456761600,
                 "exptime": 1546099200,
                "rsc_network": 3,
                "image": {
                    "img_240": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/14778962295555.jpg",
                    "img_320": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/147789636929245.jpg",
                    "img_480": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/147789637345693.jpg",
                    "img_720": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/147789637600398.jpg"
                },
                "max_cnt": 1,
                "click_ad": {
                    "click_type": "website",
                    "website": {
                        "click_title": "发的发发呆的放大",
                        "click_client": "com.baidu.input",
                        "click_link": "http://mco.ime.shahe.baidu.com:8015/v5/trace?url=http%3A%2F%2Fcq02-mic-iptest.cq02.baidu.com%3A8104%2Ftools%2Fpredownload.php%3Flink%3Dhttp%253a%252f%252fbos.pgzs.com%252fsjapp91%252fpcsuite%252fplugin%252f91assistant_Android_1.apk%26package%3Dcom.oem91.market&ad_zone=3&ad_pos=0&ad_id=490&ad_type=ad&ad_event=1&sign=97fce8ae1e6d7543429719358d801095",
                        "click_web_download_package": "com.oem91.market",
                        "click_web_download_link": "http://bos.pgzs.com/sjapp91/pcsuite/plugin/91assistant_Android_1.apk",
                        "pre_down": 0
                    }
                },
                "rsc_noti_cnt": 0,
                "push_delay": 0
            },
            {
                "id": "30",
                "name": "瓜熟蒂落",
                "cateid": "opgsdl",
                "pic": "upload/imags/2016/05/25/6s4p6607e5.jpg",
                "pri": "100",
                "pub_item_type": "non_ad",
                "type": "list",
                "tag": "opgsdl"
            }]
        }
     */
    public function cateList($type = '') {
        $ads_res_ph = new PhasterThread(array($this,"getCateAdsList"), array($type));
        $emoticon_cate_ph = new PhasterThread(array($this,"getEmoticonCate"), array($type));
        
        //ads resource
        $ads_res = $ads_res_ph->join();
        //emoticon operate resource
        $emoticon_cate_res = $emoticon_cate_ph->join();
        
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter,$this->dsf);
		
		$j = 0;
		$k = 0;
		$merged = array();
		while(isset($new_ads[$j]) || isset($emoticon_cate_res['list'][$k])) {
		    if(isset($new_ads[$j])) {
		        $merged[$j] = $new_ads[$j];
		        $j++;
		    } elseif(isset($emoticon_cate_res['list'][$k])) {
		        if($emoticon_cate_res['list'][$k]['pub_item_type'] == 'prod_ad') {
		            $emoticon_cate_res['list'][$k]['ad_id'] = 0;
		            $ad_zone = -1;
		            
		            switch($type) {
		                case 'newest':
		                    $ad_zone = 27;
		                    break;
		                case 'hottest':
		                    $ad_zone = 28;
		                    break;
		            }
		            
		            $emoticon_cate_res['list'][$k]['ad_zone'] = $ad_zone;
		            $ad_pos = $this->dsf + $j;
		            $emoticon_cate_res['list'][$k]['ad_pos'] = $ad_pos;
		            
		            $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=$ad_zone&ad_pos=' . $ad_pos;
		            if(strpos($emoticon_cate_res['list'][$k]['url'], '?') !== false) {		
		                $emoticon_cate_res['list'][$k]['url'] .= '&' . $ad_info;
		            } else {
		                $emoticon_cate_res['list'][$k]['url'] .= '?' . $ad_info;
		            }
		        }
		        
		        $merged[$j] = $emoticon_cate_res['list'][$k];
		        $j++;
		        $k++;
		    } else {
		        break;
		    }
		}
		
		$emoticon_cate_res['list'] = $merged;
		
		return $emoticon_cate_res;
    }
    
    /**
     * 获取颜文字banner广告位信息
     * @return
     */
    public function getOperateAllAdsList() {
        $ads_cache_key = __Class__ . '_operate_all_ads_list_data_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":26}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$ads_cache_key,GFunc::getCacheTime('ads_cache_time'));
        
		return $ads_res;
	}
	
	/**
     * 获取颜文字banner数据
     * @return
     */
    public function getEmoticonOperateAll() {
        $class = IoCload("Emoticon");
        $list = $class->operationCategory($this->platform, $this->ver_name);
        
        return $list;
    }
    
    /**
     * 获取颜文字商店列表广告位信息
     * @param $type 类别
     * @return
     */
    public function getCateAdsList($type = '') {
        $ads_res = array();
        
        switch($type) {
            case 'newest':
                $ad_zone = 27;
                break;
            case 'hottest':
                $ad_zone = 28;
                break;
        }
        
        if(isset($ad_zone)) {
            $end = $this->dsf + $this->num - 1;
            $cache_key = __Class__ . '_cate_data_' . $this->dsf . '_' . $this->num . '_' . $ad_zone . '_cachekey';
            $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte": ' . $end . '}}';
            $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
            $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));
        }
        
		return $ads_res;
	}
	
	/**
     * 获取颜文字分类列表
     * @param $type 类别
     * @return
     */
    public function getEmoticonCate($type = '') {
        $class = IoCload("Emoticon");
        $list = $class->catlist($type, $this->platform, $this->ver_name, $this->sf, $this->num);
        
        return $list;
    }
}
