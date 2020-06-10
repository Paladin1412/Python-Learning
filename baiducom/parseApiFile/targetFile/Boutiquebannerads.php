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
 * 精品banner广告
 *
 *
 * @author fanwenli
 * @path("/boutique_banner_ads/")
 */
class Boutiquebannerads
{

    /**
     *
     */
    function __construct() {
        
    }


    /**
     *
     * @route({"GET","/list"})
     *
     * 精品banner广告
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * [
            {
                "pub_item_type": "ad",
                "title": "发现banner【固定case】",
                "ad_desc": "预下载",
                "ad_id": 168,
                "ad_zone": 11,
                "ad_pos": 0,
                "ad_provider": 3,
                "priority": 6,
                "ad_type": 1,
                "sttime": 1435708800,
                "exptime": 1540944000,
                "rsc_network": 0,
                "image": {
                    "img_240": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/145882227043397.jpg",
                    "img_320": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/14588222751644.jpg",
                    "img_480": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/14588222801809.jpg",
                    "img_720": "http://mco.ime.shahe.baidu.com:8891/res/file/advertisement/files/145882228697856.jpg"
                },
                "max_cnt": -1,
                "click_ad": {
                    "click_type": "website",
                    "website": {
                        "click_title": "发现banner预下载",
                        "click_client": "com.baidu.input",
                        "click_link": "http://mco.ime.shahe.baidu.com:8015/v5/trace?url=http%3A%2F%2Fcq02-mic-iptest.cq02.baidu.com%3A8104%2Ftools%2Fpredownload.php%3Flink%3Dhttp%253a%252f%252fbos.pgzs.com%252fsjapp91%252fpcsuite%252fplugin%252f91assistant_Android_1.apk%26package%3Dcom.oem91.market&ad_zone=11&ad_pos=1&ad_id=168&ad_type=ad&ad_event=1&sign=97fce8ae1e6d7543429719358d801095",
                        "click_web_download_package": "com.oem91.market",
                        "click_web_download_link": "http://bos.pgzs.com/sjapp91/pcsuite/plugin/91assistant_Android_1.apk",
                        "pre_down": 0
                    }
                },
                "rsc_noti_cnt": 0,
                "push_delay": 0
            }
        ]
     */
    public function getList() {
        $ads_res = $this->getAdsList();
        
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter);
		
		//sort array with ad_pos
		$boutique_banner_ads_tmp = array();
		foreach($new_ads as $ad) {
		    $boutique_banner_ads_tmp[$ad['ad_pos']] = $ad;
		}
		
		//sort array by key
		ksort($boutique_banner_ads_tmp);
		
		//set array with key by 0
		$i = 0;
		$boutique_banner_ads = array();
		foreach($boutique_banner_ads_tmp as $ad) {
		    $ad['ad_pos'] = $i;
		    $boutique_banner_ads[$i] = $ad;
		    $i++;
		}
		
        return $boutique_banner_ads;
    }
	
	/**
     * 获取广告信息
     * @return
     */
    public function getAdsList() {
        $ad_zone = 14;
        $cache_key = __Class__ . '_list_data_' . $ad_zone . '_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . '}';
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));

		return $ads_res;
	}
}
