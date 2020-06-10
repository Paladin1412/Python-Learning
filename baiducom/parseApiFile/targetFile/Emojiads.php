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
use utils\CacheVersionSwitchScope;

/**
 *
 * 表情商店广告
 *
 *
 * @author fanwenli
 * @path("/emoji_ads/")
 */
class Emojiads
{
    /** @property 数量 */
    private $num = 0;
    
    /** @property 起始位置*/
    private $dsf = 0;
    
    /** @property 类别*/
    private $cate = '';
    
    /** @property 是否野表情*/
    private $is_wild = 0;
    
    /** @property*/
    private $sf = 0;
    
    /** @property 平台号*/
    private $platform = 'a1';
    
    /** @property 版本号*/
    private $ver_name = '5.4.0.0';
    
    /**
     *
     */
    function __construct() {
        //set inputgd as follow
        $_GET['inputgd'] = 1;
        
        $this->num = intval($_GET['num']);
        
        $this->dsf = intval($_GET['dsf']);
        
        if(isset($_GET['cate'])) {
            $this->cate = trim($_GET['cate']);
        }
        
        if(isset($_GET['is_wild'])) {
            $this->is_wild = intval($_GET['is_wild']);
        }
        
        $this->sf = intval($_GET['sf']);
        
        if(isset($_GET['platform'])) {
            $this->platform = trim($_GET['platform']);
        }
        
        if(isset($_GET['version'])) {
            $this->ver_name = trim($_GET['version']);
        }
    }


    /**
     *
     * @route({"GET","/market"})
     *
     * 表情商店
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
    public function market() {
        $ads_res_ph = new PhasterThread(array($this,"getAdsList"), array());
        $emoji_market_ph = new PhasterThread(array($this,"getEmojiMarket"), array());
        
        //ads resource
        $ads_res = $ads_res_ph->join();
        //emoji market resource
        $emoji_market_res = $emoji_market_ph->join();
		
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter,$this->dsf);
		
		$j = 0;
		$k = 0;
		$merged = array();
		$new_emojiinfo = $emoji_market_res['emojiinfo'];

		while(isset($new_ads[$j]) || isset($new_emojiinfo['emojilist'][$k])) {
		    if(isset($new_ads[$j])) {
		        $merged[$j] = $new_ads[$j];
		        $j++;
		    } elseif(isset($new_emojiinfo['emojilist'][$k])) {
		        if($new_emojiinfo['emojilist'][$k]['pub_item_type'] == 'prod_ad') {
		            $new_emojiinfo['emojilist'][$k]['ad_id'] = 0;
		            switch($this->cate) {
		                case 'hot':
		                    $ad_zone = 0;
		                    break;
		                case 'hot_new':
		                    $ad_zone = 25;
		                    break;
		                case 'last':
		                    $ad_zone = 24;
		                    break;
		                case 'virtab':
		                    $ad_zone = 29;
		                    break;
		                default:
		                    $ad_zone = 0;
		            }
		            
		            $new_emojiinfo['emojilist'][$k]['ad_zone'] = $ad_zone;
		            $new_emojiinfo['emojilist'][$k]['ad_pos'] = $this->dsf + $j;
		            $ad_pos = $this->dsf + $j;
		            $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=' . $ad_zone . '&ad_pos=' . $ad_pos;
		            
		            if(strpos($new_emojiinfo['emojilist'][$k]['url'], '?') !== false) {
		                $new_emojiinfo['emojilist'][$k]['url'] = $new_emojiinfo['emojilist'][$k]['url'] . '&' . $ad_info;
		            } else {
		                $new_emojiinfo['emojilist'][$k]['url'] = $new_emojiinfo['emojilist'][$k]['url'] . '?' . $ad_info;
		            }
		        }
		        
		        $merged[$j] = $new_emojiinfo['emojilist'][$k];
		        $j++;
		        $k++;
		    } else {
		        break;
		    }
		}
		
		$new_emojiinfo['emojilist'] = $merged;
		$emoji_market_res['emojiinfo'] = $new_emojiinfo;
		
		if(isset($emoji_market_res['pageinfo'])) {
		    unset($emoji_market_res['pageinfo']);
		}
        
        return $emoji_market_res;
    }
    
    /**
     *
     * @route({"GET","/operate"})
     *
     * 表情banner广告位
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
        $emoji_operate_ph = new PhasterThread(array($this,"getEmojiOperateAll"), array());
        
        //ads resource
        $ads_res = $ads_res_ph->join();
        //emoji operate resource
        $emoji_operate_res = $emoji_operate_ph->join();
        
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter);
		
		$j = 0;
		$k = 0;
		$merged = array();
		while(isset($new_ads[$j]) || isset($emoji_operate_res['list'][$k])) {
		    if(isset($new_ads[$j])) {
		        $merged[$j] = $new_ads[$j];
		        $j++;
		    } elseif(isset($emoji_operate_res['list'][$k])) {
		        if($emoji_operate_res['list'][$k]['pub_item_type'] == 'prod_ad') {
		            $emoji_operate_res['list'][$k]['ad_id'] = 0;
		            $emoji_operate_res['list'][$k]['ad_zone'] = 23;
		            $emoji_operate_res['list'][$k]['ad_pos'] = $j;
		            $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=23&ad_pos=' . $j;
		            
		            switch($emoji_operate_res['list'][$k]['type']) {
		                case 'detail':
		                    if(strpos($emoji_operate_res['list'][$k]['data']['url'], '?') !== false) {
		                        $emoji_operate_res['list'][$k]['data']['url'] .= '&' . $ad_info;
		                    } else {
		                        $emoji_operate_res['list'][$k]['data']['url'] .= '?' . $ad_info;
		                    }
		                    break;
		                case 'list':
		                    if(strpos($emoji_operate_res['list'][$k]['image'], '?') !== false) {
		                        $emoji_operate_res['list'][$k]['image'] .= '&' . $ad_info;
		                    } else {
		                        $emoji_operate_res['list'][$k]['image'] .= '?' . $ad_info;
		                    }
		                    break;
		            }
		        }
		        
		        $merged[$j] = $emoji_operate_res['list'][$k];
		        $j++;
		        $k++;
		    } else {
		        break;
		    }
		}
		
		$emoji_operate_res['list'] = $merged;
		
        return $emoji_operate_res;
    }
    
    /**
     *
     * @route({"GET","/operate/*\/list"})
     * @param({"type", "$.path[2]"}) string $type 某自然分类
     *
     * 表情banner运营分类下表情
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
    public function operateList($type = '') {
        $ads_res_url_ph = new PhasterThread(array($this,"getEmojiOperateAdsList"), array($type));
        $emoji_operate_ph = new PhasterThread(array($this,"getEmojiOperate"), array($type));
        
        //ads resource url
        $ads_res_url = $ads_res_url_ph->join();
        //emoji operate resource
        $emoji_operate_res = $emoji_operate_ph->join();
        
        
        //get ads url res
        $ads_res = $this->getOperateAdsList($ads_res_url);
        
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter,$this->dsf);
		
		$j = 0;
		$k = 0;
		$merged = array();
		while(isset($new_ads[$j]) || isset($emoji_operate_res['list'][$k])) {
		    if(isset($new_ads[$j])) {
		        $merged[$j] = $new_ads[$j];
		        $j++;
		    } elseif(isset($emoji_operate_res['list'][$k])) {
		        if($emoji_operate_res['list'][$k]['pub_item_type'] == 'prod_ad') {
		            $emoji_operate_res['list'][$k]['ad_id'] = 0;
		            $ad_zone_id = -1;
		            foreach($ads_res_url as $i) {
		                $ad_zone_id = $i['ad_zone'];
		            }
		            
		            $emoji_operate_res['list'][$k]['ad_zone'] = intval($ad_zone_id);
		            
		            $ad_pos = $this->dsf + $j;
		            $emoji_operate_res['list'][$k]['ad_pos'] = $ad_pos;
		            $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=' . $ad_zone_id . '&ad_pos=' . $ad_pos;
		            if(strpos($emoji_operate_res['list'][$k]['url'], '?') !== false) {
		                $emoji_operate_res['list'][$k]['url'] .= '&' . $ad_info;
		            } else {
		                $emoji_operate_res['list'][$k]['url'] .= '?' . $ad_info;
		            }
		        }
		        
		        $merged[$j] = $emoji_operate_res['list'][$k];
		        $j++;
		        $k++;
		    } else {
		        break;
		    }
		}
		
		$emoji_operate_res['list'] = $merged;

        return $emoji_operate_res;
    }
    
    /**
     * 获取广告信息
     * @return
     */
    public function getAdsList() {
        $ads_cache_key = __Class__ . '_ads_list_data_cachekey';
        
        $end = $this->dsf + $this->num - 1;
        
        switch($this->cate) {
            case 'hot':
                $ad_zone = 0;
                break;
            case 'hot_new':
                $ad_zone = 25;
                break;
            case 'last':
                $ad_zone = 24;
                break;
            case 'virtab':
                $ad_zone = 29;
                break;
            default:
                $ad_zone = 0;
        }

        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte":' . $end . '}}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$ads_cache_key,GFunc::getCacheTime('ads_cache_time'));
        
		return $ads_res;
	}
	
	/**
     * 获取表情市场数据
     * @return
     */
    public function getEmojiMarket() {
        $class = IoCload("Emoji");
        $list = $class->market($this->cate, $this->sf, $this->num, $this->platform, $this->ver_name, $this->is_wild);
        
        return $list;
    }
    
    /**
     * 获取表情banner广告位信息
     * @return
     */
    public function getOperateAllAdsList() {
        $ads_cache_key = __Class__ . '_operate_ads_list_data_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":23}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$ads_cache_key,GFunc::getCacheTime('ads_cache_time'));
        
		return $ads_res;
	}
	
	/**
     * 获取表情banner数据
     * @return
     */
    public function getEmojiOperateAll() {
        $class = IoCload("Emoji");
        $list = $class->operationCategory($this->platform, $this->ver_name, $this->is_wild);
        
        return $list;
    }
    
    /**
     * 获取表情普通分类下的表情广告信息
     * @param $operate 某自然分类
     * @return
     */
    public function getEmojiOperateAdsList($operate = '') {
        $cache_key = __Class__ . '_operate_list_data_' . $operate . '_cachekey';
        $ads_url = '/res/json/input/r/online/adzone_ip_mapping/?onlycontent=1&search={"url_path":"/v5/emoji_ads/operate/' . $operate . '/list"}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));

		return $ads_res;
	}
	
	/**
     * 获取表情分类列表
     * @param $operate 某自然分类
     * @return
     */
    public function getEmojiOperate($operate = '') {
        $class = IoCload("Emoji");
        $list = $class->operateList($operate, $this->platform, $this->ver_name, $this->sf, $this->num, $this->is_wild);
        
        return $list;
    }
    
    /**
     * 获取表情banner运营分类下表情广告信息
     * @param $ads_url 广告链接
     * @return
     */
    public function getOperateAdsList($ad_arr = array()) {
        $ad_zone_id = -1;
        
        foreach($ad_arr as $ad) {
            $ad_zone_id = $ad['ad_zone'];
        }
        
        $end = $this->dsf + $this->num - 1;
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone_id . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte":' . $end . '}}';
        
        $cache_key = __Class__ . '_operate_ads_url_data_' . $ad_zone_id . '_' . $this->dsf . '_' . $this->num . '_cachekey';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));
        
        return $ads_res;
	}
}
