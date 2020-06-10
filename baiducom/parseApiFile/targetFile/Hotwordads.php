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
 * 热词列表广告
 *
 *
 * @author fanwenli
 * @path("/hotword_ads/")
 */
class Hotwordads
{
    
    /** @property 数量 */
    private $num = 0;
    
    /** @property  */
    private $dsf = 0;
    
    /** @property  平台 */
    private $platform = '';
    
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
        
        $this->sf = intval($_GET['sf']);
        
    }


    /**
     *
     * @route({"GET","/list"})
     *
     * 热词列表
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
        $default_search_word_ph = new PhasterThread(array($this,"getDefaultSearchWord"), array());
        $get_switch_info_ph = new PhasterThread(array($this,"getSwitchInfo"), array());
        $ads_res_ph = new PhasterThread(array($this,"getAdsList"), array());
        
        //default search word
        $default_search_word = $default_search_word_ph->join();
        //switch info
        $get_switch_info = $get_switch_info_ph->join();
        //ads resource
        $ads_res = $ads_res_ph->join();
        
        //filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter,$this->dsf);
        
        //judge switch, 0: self 1: top_baidu
        $switch = $this->getSwitchJudgement($get_switch_info);
        
        $hotwords = $this->getHotwords($switch);
		
		$list = array();
		switch($switch) {
		    case 0:
		        $list = $this->mergeAdsWithSelf($hotwords,$new_ads,$default_search_word);
		        break;
		    case 1:
		        $list = $this->mergeAdsWithTopBaidu($hotwords,$new_ads);
		        break;
		}
		
		return $list;
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
     * 获取默认词资源
     * @return
     */
    public function getDefaultSearchWord() {
        $search_word_cache_key = __Class__ . '_default_search_word_data_cachekey';
        $search_word_url = '/res/json/input/r/online/defaultSearchWord/?onlycontent=1';
        $search_word_res = Util::ralGetContent($search_word_url,$search_word_cache_key,GFunc::getCacheTime('ads_cache_time'));
		
		return $search_word_res;
	}
	
	/**
     * 获取广告信息
     * @return
     */
    public function getAdsList() {
        $end = $this->dsf + $this->num - 1;
        $ad_zone = 8;
        
        $cache_key = __Class__ . '_list_data_' . $this->dsf . '_' . $this->num . '_' . $ad_zone . '_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte": ' . $end . '}}';
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));
        
		return $ads_res;
	}
	
	/**
     * 获取开关信息
     * @return
     */
    public function getSwitchInfo() {
        $switch_info_cache_key = __Class__ . '_switch_info_data_cachekey';
        $switch_info_url = '/res/json/input/r/online/hotwordfrom/?onlycontent=1';
        $switch_info_res = Util::ralGetContent($switch_info_url,$switch_info_cache_key,GFunc::getCacheTime('ads_cache_time'));
		
		return $switch_info_res;
	}
	
	/**
     * 根据开关返回值，判断是取热词还是大搜词
     * @param $switch_info_arr 开关信息数组
     * @return
     */
    private function getSwitchJudgement($switch_info_arr = array()) {
        //judge switch, 0: self 1: top_baidu default: self(?)
        //$hotwordfrom = 0;
        $hotwordfrom = -1;
        
        if(!empty($switch_info_arr)){
            foreach($switch_info_arr as $val) {
                $hotwordfrom = $val['from'];
            }
        }
        
        return $hotwordfrom;
	}
	
	/**
     * 根据开关返回值，获取热词还是大搜词
     * @param $switch 开关
     * @return
     */
    private function getHotwords($switch = 0) {
        $hotwords_arr = array();
        
        switch($switch) {
            case 0:
                $hotwords_arr = $this->getHotwordsFromSelf();
                break;
            case 1:
                $hotwords_arr = $this->getHotwordsFromTopBaidu();
                break;
        }
        
        return $hotwords_arr;
    }
    
    /**
     * 获取热词
     * @return
     */
    private function getHotwordsFromSelf() {
        $class = IoCload("HotWords");
        $list = $class->getList($this->platform, $this->sf, $this->num);
        
        return $list;
    }
    
    /**
     * 获取大搜词
     * @return
     */
    private function getHotwordsFromTopBaidu() {
        $cache_key = __Class__ . '_hotwords_from_top_baidu_data_cachekey';
        
        $hotwords_arr = GFunc::cacheZget($cache_key);
        if($hotwords_arr == '' || is_null($hotwords_arr)){
            $header = array(
		        'pathinfo' => 'api/hotspot',
		        'querystring' => 'b=8&s=11&pic=1',
		    );
		    
		    //get content from 51gif
		    $hotwords_arr = ral('top_baidu_service', 'get', null, rand(), $header);
		    
		    //if return is not array, then set it to array
		    $hotwords_arr = is_array($hotwords_arr)?$hotwords_arr:json_decode($hotwords_arr,true);
		    
		    //设置缓存
            GFunc::cacheZset($cache_key, $hotwords_arr, GFunc::getCacheTime('ads_cache_time'));
		}
		
        return $hotwords_arr;
    }
    
    
    /**
     * 合并热词和广告
     * @param $hotwords_arr 大搜词
     * @param $new_ads_arr 广告
     * @param $default_word_arr 默认词数组
     * @return
     */
    private function mergeAdsWithSelf($hotwords_arr = array(), $new_ads_arr = array(), $default_word_arr = array()) {
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads_arr[$j]) || isset($hotwords_arr[$k])) {
            if(isset($new_ads_arr[$j])) {
                $merged[$j] = $new_ads_arr[$j];
                $j++;
            } elseif(isset($hotwords_arr[$k])) {
                $hotwords_arr[$k]['isnew'] = 0;
                $hotwords_arr[$k]['from'] = 'baiduinput';
                $merged[$j] = $hotwords_arr[$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $hotwords = array();
        $hotwords['top_baidu_url'] = '';
        
        //Edit by fanwenli on 2018-09-20, set default word is array of empty when it is not array
        if(!is_array($default_word_arr)) {
            $default_word_arr = array();
        }
        
        $defaultWordKey = array_rand($default_word_arr);
        $defaultSearchWord = $default_word_arr[$defaultWordKey]['defaultSearchWord'];
        if($defaultSearchWord == null) {
            $defaultSearchWord = '';
        }
        
        $hotwords['search_word'] = $defaultSearchWord;
        $hotwords['hotlist'] = $merged;
        
        return $hotwords;
    }
    
    /**
     * 合并大搜词和广告
     * @param $hotwords_arr 大搜词
     * @param $new_ads_arr 广告
     * @return
     */
    private function mergeAdsWithTopBaidu($hotwords_arr = array(), $new_ads_arr = array()) {
        $j = 0;
        $k = $this->sf;
        $merged = array();
        $hotwords_list = $hotwords_arr['keywords'];
        while((isset($new_ads_arr[$j]) || isset($hotwords_list[$k])) && $k <= $this->sf + $this->num - 1) {
            if(isset($new_ads_arr[$j])) {
                $merged[$j] = $new_ads_arr[$j];
                $j++;
            } elseif(isset($hotwords_list[$k])) {
                $merged[$j]['from'] = 'topbaidu';
                $merged[$j]['id'] = '0';
                $merged[$j]['type'] = '1';
                $merged[$j]['word'] = $hotwords_list[$k]['keyword'];
                $merged[$j]['word_desc'] = $hotwords_list[$k]['desc'];
                $merged[$j]['source_content'] = $merged[$j]['word'] . $merged[$j]['word_desc'];
                $merged[$j]['word_comment'] = '';
                $IME_CHANNEL_IN_WISE = '1001560r';
                $search_word = urlencode(trim($merged[$j]['word']));
                $merged[$j]['link'] = 'http://m.baidu.com/s?from=' . $IME_CHANNEL_IN_WISE . '&word=' . $search_word;
                $merged[$j]['pic'] = $hotwords_list[$k]['picurl'];
                $merged[$j]['isnew'] = $hotwords_list[$k]['isNew'];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $hotwords = array();
        $SIGN_SALT = 'iudfu(lkc#xv345y82$dsfjksa';
        $topbaidu_url = urlencode('http://top.baidu.com/m?csrc= fyb_inputfyb_auto');
        $sign = md5('http://top.baidu.com/m?csrc= fyb_inputfyb_auto' . $SIGN_SALT);
        $hotwords['top_baidu_url'] = GFunc::getGlobalConf('domain_v5') . 'v5/trace?url=' . $topbaidu_url . '&sign=' . $sign;
        $hotwords['hotlist'] = $merged;
        
        return $hotwords;
    }
    
}
