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
 * 非实时广告
 *
 *
 * @author fanwenli
 * @path("/nonrealtime_ads/")
 */
class Nonrealtimeads {
    
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
     * 非实时广告列表
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
        /*$ads_res_ph = new PhasterThread(array($this, "getAdsList"), array());
        $predownload_ph = new PhasterThread(array($this, "getPredownload"), array());

        //ads resource
        $ads_res = $ads_res_ph->join();
        //predownload resource
        $predownload_res = $predownload_ph->join();*/
        
        //Edit by fanwenli on 2019-05-21, get predownload from advertisement
        $ads_res = $this->getAdsList();
        $predownload_res = $this->getPredownload($ads_res);

        //filter the Expired ads
        $ads_res = Util::filterExptimeAds($ads_res);

        //noti filter the ads
        $ads_res = Util::notiFilterAds($ads_res);

        //filter the ads
        $ads_res_filter = Util::filterAds($ads_res);

        //set ads's position
        $new_ads = $this->setAdsPosition($ads_res_filter);

        //filter the predownload
        $predownload_res = Util::filterAds($predownload_res);

        $merged = array();
        $merged['nonrealtime_ads'] = $new_ads;

        $merged['pre_downloads'] = array();
        foreach ($predownload_res as $pkg) {
            if (isset($pkg) && is_array($pkg) && !empty($pkg)) {
                $merged['pre_downloads'][] = $pkg;
            }
        }

        return $merged;
    }
    
    /**
     * 获取广告信息
     * @return
     */
    public function getAdsList() {
        $ads_cache_key = __Class__ . '_ads_list_data_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":{"$in":[1,7,9,13,16,18,19,22,31]}}';
        $ads_res = Util::ralGetContent($ads_url,$ads_cache_key,GFunc::getCacheTime('ads_cache_time'));
		
		return $ads_res;
	}
	
	/**
     * 获取请求预下载数据
     * @param $ads_array 广告数组
     * 
     * @return
     */
    public function getPredownload($ads_array = array()) {
        /*$predownload_cache_key = __Class__ . '_predownload_list_data_cachekey';
        $predownload_url = '/res/json/input/r/online/predownload/?onlycontent=1';
        $predownload_res = Util::ralGetContent($predownload_url, $predownload_cache_key, GFunc::getCacheTime('ads_cache_time'));*/
        
        //Edit by fanwenli on 2019-05-21, get predownload from advertisement
        $predownload_res = array();
        if(!empty($ads_array)) {
            foreach($ads_array as $val) {
                if(isset($val['predownload']['pre_dlink']) && $val['predownload']['pre_dlink'] != '') {
                    $tmp = $val['predownload'];
                    $tmp['filter_conditions'] = $val['filter_conditions'];
                    $tmp['filter_id'] = $val['filter_id'];
                    $predownload_res[] = $tmp;
                }
            }
        }
        
        return $predownload_res;
    }
    
    
    /**
     * 设置广告位置
     * @param $ads_array 广告数组
     * @param $dsf 广告位置偏移量
     * @return array
     */
    private function setAdsPosition($ads_array = array()) {
        $new_ads = array();

        if (!empty($ads_array)) {
            foreach ($ads_array as $ad) {
                if (isset($ad['rsc_noti']['rsc_thumb'])) {
                    $new_ad_rsc_thumb = array();
                    foreach ($ad['rsc_noti']['rsc_thumb'] as $key => $img) {
                        $SIGN_SALT = 'iudfu(lkc#xv345y82$dsfjksa';
                        $img_url = urlencode($img);
                        $sign = md5($img . $SIGN_SALT);
                        $new_ad_rsc_thumb[$key] = GFunc::getGlobalConf('domain_v5') . '/v5/trace/rscnoti?url=' . $img_url . '&sign=' . $sign . '&ad_zone=' . $ad['ad_zone'] . '&ad_id=' . $ad['ad_id'] . '&rsc_noti_cnt=' . $ad['rsc_noti_cnt'] . '&ad_exptime=' . $ad['exptime'];
                    }

                    $ad['rsc_noti']['rsc_thumb'] = $new_ad_rsc_thumb;
                }

                $new_ads[] = $ad;
            }
        }

        return $new_ads;
    }

}
