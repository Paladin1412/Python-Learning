<?php
/***************************************************************************
 *
* Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use utils\CacheVersionSwitchScope;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;
use models\SkinadsModel;

/**
 *
 * 皮肤精品的推荐、排行
 *
 *
 * @author fanwenli
 * @path("/skin_ads/")
 */
class Skinads
{
    /** @property 排序 */
    private $sort = '';
    
    /** @property 数量 */
    private $num = 0;
    
    /** @property  */
    private $dsf = 0;
    
    /** @property  平台 */
    private $platform = '';
    
    /** @property  版本号 */
    private $ver_name = '5.4.0.0';
    
    /** @property */
    private $foreign_access = false;
    
    /** @property */
    private $sf = 0;
    
    /** @property 屏幕宽度 */
    private $screen_w = 640;
    
    /** @property 屏幕高度 */
    private $screen_h = 320;
    
    /** @property wap站识别客户端系统参数 */
    private $wap_os = '';
    
    const CACHE_PRE = 'skin_ads_';
    const AD_ZONE_SKIN_PREVIEW = 30;
    const DEFAULT_SKIN_HEIGHT = '26.8%';

    /**
     *
     */
    function __construct() {
        //set inputgd as follow
        $_GET['inputgd'] = 1;
        
        //sort type
        if(isset($_GET['sort'])) {
            $this->sort = $_GET['sort'];
        }
        
        $this->num = intval($_GET['num']);
        
        $this->dsf = intval($_GET['dsf']);
        
        if(isset($_GET['platform'])) {
            $this->platform = trim($_GET['platform']);
        }
        
        if(isset($_GET['version'])) {
            $this->ver_name = trim($_GET['version']);
        }
        
        if(isset($_GET['foreign_access'])) {
            $this->foreign_access = $_GET['foreign_access'];
        }
        
        $this->sf = intval($_GET['sf']);
        
        if(isset($_GET['screen_w'])) {
            $this->screen_w = intval($_GET['screen_w']);
        }
        
        if(isset($_GET['screen_h'])) {
            $this->screen_h = intval($_GET['screen_h']);
        }
        
        if(isset($_GET['wap_os'])) {
            $this->wap_os = trim($_GET['wap_os']);
        }
    }


    /**
     *
     * @route({"GET","/market"})
     *
     * 皮肤精品的推荐、排行
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
    public function market() {
        /*$skin_list = $this->getSkinthemeList();
        
        $ads_res = $this->getAdsList();*/
        
        $skin_list_ph = new PhasterThread(array($this,"getSkinthemeList"), array());
        $ads_res_ph = new PhasterThread(array($this,"getAdsList"), array());
        $skin_list = $skin_list_ph->join();
        $ads_res = $ads_res_ph->join();
		
		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter, $this->dsf);
		
		//merge skin list with ads
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads[$j]) || isset($skin_list['list'][$k])) {
            if(isset($new_ads[$j])) {
                $merged[$j] = $new_ads[$j];
                $j++;
            } elseif(isset($skin_list['list'][$k])) {
                if($skin_list['list'][$k]['pub_item_type'] == 'prod_ad') {
                    $skin_list['list'][$k]['ad_id'] = 0;
                    
                    switch($this->sort) {
                        case '':
                            $ad_zone = 4;
                            break;
                        case 'skinonly':
                            $ad_zone = 5;
                            break;
                        case 'down':
                            $ad_zone = 5;
                            break;
                        default:
                            $ad_zone = -1;
                    }
                    
                    $skin_list['list'][$k]['ad_zone'] = $ad_zone;
                    $ad_pos = $this->dsf + $j;
                    $skin_list['list'][$k]['ad_pos'] = $ad_pos;
                    
                    $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone= ' .$ad_zone. ' &ad_pos=' . $ad_pos;
                    $skin_list['list'][$k]['url'] .= '&' . $ad_info;
                }
                
                $merged[$j] = $skin_list['list'][$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $skin_list['list'] = $merged;
        
        return $skin_list;
    }
    
    /**
     *
     * @route({"GET","/opcate"})
     *
     * 皮肤运营分类
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
    public function opcateAll() {
        $skin_operation_list_ph = new PhasterThread(array($this,"getSkinthemeOperationCategory"), array());
        $ads_res_ph = new PhasterThread(array($this,"getSkinthemeOperationCategoryAdsList"), array());
        $skin_operation_list = $skin_operation_list_ph->join();
        $ads_res = $ads_res_ph->join();

		//filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter, $this->dsf);
		
		//merge skin list with ads
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads[$j]) || isset($skin_operation_list['list'][$k])) {
            if(isset($new_ads[$j])) {
                $merged[$j] = $new_ads[$j];
                $j++;
            } elseif(isset($skin_operation_list['list'][$k])) {
                if($skin_operation_list['list'][$k]['pub_item_type'] == 'prod_ad') {
                    $skin_operation_list['list'][$k]['ad_id'] = 0;
                    $skin_operation_list['list'][$k]['ad_zone'] = 3;
                    $ad_pos = $this->dsf + $j;
                    $skin_operation_list['list'][$k]['ad_pos'] = $ad_pos;
                    
                    $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=3&ad_pos=' . $ad_pos;
                    $skin_operation_list['list'][$k]['pic'] .= '?' . $ad_info;
                }
                
                $merged[$j] = $skin_operation_list['list'][$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $skin_operation_list['list'] = $merged;
        
        return $skin_operation_list;
    }
    
    /**
     *
     * @route({"GET", "/cate"})
     *
     * 皮肤普通分类
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
    public function cateAll() {
        $skin_cate_ph = new PhasterThread(array($this,"getSkinthemeCategory"), array());
        $ads_res_ph = new PhasterThread(array($this,"getSkinthemeCateAllAdsList"), array());
        $skin_cate = $skin_cate_ph->join();
        $ads_res = $ads_res_ph->join();
        
        //filter the Expired ads
		$ads_res = Util::filterExptimeAds($ads_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter, $this->dsf);
		
		//merge skin list with ads
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads[$j]) || isset($skin_cate['list'][$k])) {
            if(isset($new_ads[$j])) {
                $merged[$j] = $new_ads[$j];
                $j++;
            } elseif(isset($skin_cate['list'][$k])) {
                if($skin_cate['list'][$k]['pub_item_type'] == 'prod_ad') {
                    $skin_cate['list'][$k]['ad_id'] = 0;
                    $ad_zone = 6;
                    $skin_cate['list'][$k]['ad_zone'] = $ad_zone;
                    $ad_pos = $this->dsf + $j;
                    $skin_cate['list'][$k]['ad_pos'] = $ad_pos;
                    $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=' . $ad_zone . '&ad_pos=' . $ad_pos;
                    $skin_cate['list'][$k]['pic'] .= '?'. $ad_info;
                }
                
                $merged[$j] = $skin_cate['list'][$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $skin_cate['list'] = $merged;
        
        return $skin_cate;
    }
    
    /**
     *
     * @route({"GET", "/cate/*\"})
     * @param({"cat", "$.path[2]"}) string $cat 某自然分类
     *
     * 皮肤普通分类下的皮肤
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
    public function cate($cat = '') {
        $skin_cate_item_ph = new PhasterThread(array($this,"getSkinthemeCategoryItem"), array($cat));
        $ads_res_ph = new PhasterThread(array($this,"getSkinthemeCateAdsList"), array($cat));
        $skin_cate_item = $skin_cate_item_ph->join();
        $ads_res = $ads_res_ph->join();
        
        //get ads url res
        $ads_url_res = $this->getSkinthemeAdsUrlRes($ads_res);
        
		//filter the Expired ads
		$ads_url_res = Util::filterExptimeAds($ads_url_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_url_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter, $this->dsf);
		
		//merge skin list with ads
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads[$j]) || isset($skin_cate_item['list'][$k])) {
            if(isset($new_ads[$j])) {
                $merged[$j] = $new_ads[$j];
                $j++;
            } elseif(isset($skin_cate_item['list'][$k])) {
                if($skin_cate_item['list'][$k]['pub_item_type'] == 'prod_ad') {
                    $skin_cate_item['list'][$k]['ad_id'] = 0;
                    $ad_zone_id = -1;
                    foreach($ads_res as $ad) {
                        $ad_zone_id = $ad['ad_zone'];
                    }
                    
                    $skin_cate_item['list'][$k]['ad_zone'] = intval($ad_zone_id);
                    
                    $ad_pos = $this->dsf + $j;
                    $skin_cate_item['list'][$k]['ad_pos'] = $ad_pos;
                    $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=' . $ad_zone_id . '&ad_pos=' . $ad_pos;
                    $skin_cate_item['list'][$k]['url'] .= '&'. $ad_info;
                }
                
                $merged[$j] = $skin_cate_item['list'][$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $skin_cate_item['list'] = $merged;
        
        return $skin_cate_item;
    }
    
    /**
     *
     * @route({"GET", "/opcate/*\"})
     * @param({"cat", "$.path[2]"}) string $cat 某运营分类
     *
     * 皮肤运营分类下的皮肤
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
    public function opcate($cat = '') {
        $skin_opcate_item_ph = new PhasterThread(array($this,"getSkinthemeOpcateItem"), array($cat));
        $ads_res_ph = new PhasterThread(array($this,"getSkinthemeOpcateAdsList"), array($cat));
        $skin_opcate_item = $skin_opcate_item_ph->join();
        $ads_res = $ads_res_ph->join();
        
        
        //get ads url res
        $ads_url_res = $this->getSkinthemeAdsUrlRes($ads_res);
        
		//filter the Expired ads
		$ads_url_res = Util::filterExptimeAds($ads_url_res);
		
		//filter the ads
		$ads_res_filter = Util::filterAds($ads_url_res);
		
		//set ads's position
		$new_ads = Util::setAdsPosition($ads_res_filter, $this->dsf);
		
		//merge skin list with ads
        $j = 0;
        $k = 0;
        $merged = array();
        while(isset($new_ads[$j]) || isset($skin_opcate_item['list'][$k])) {
            if(isset($new_ads[$j])) {
                $merged[$j] = $new_ads[$j];
                $j++;
            } elseif(isset($skin_opcate_item['list'][$k])) {
                if($skin_opcate_item['list'][$k]['pub_item_type'] == 'prod_ad') {
                    $skin_opcate_item['list'][$k]['ad_id'] = 0;
                    $ad_zone_id = -1;
                    foreach($ads_res as $ad) {
                        $ad_zone_id = $ad['ad_zone'];
                    }
                    
                    $skin_opcate_item['list'][$k]['ad_zone'] = intval($ad_zone_id);
                    
                    $ad_pos = $this->dsf + $j;
                    $skin_opcate_item['list'][$k]['ad_pos'] = $ad_pos;
                    $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=' . $ad_zone_id . '&ad_pos=' . $ad_pos;
                    $skin_opcate_item['list'][$k]['url'] .= '&'. $ad_info;
                }
                
                $merged[$j] = $skin_opcate_item['list'][$k];
                $j++;
                $k++;
            } else {
                break;
            }
        }
        
        $skin_opcate_item['list'] = $merged;
        
        return $skin_opcate_item;
    }
    
    /**
     * 获取皮肤列表
     * @return
     */
    public function getSkinthemeList() {
        $umode = 0;
        if(isset($_GET['umode'])) {
            $umode = intval($_GET['umode']);
        }
        
        $recommend_type = 0;
        if(isset($_GET['recommend_type'])) {
            $recommend_type = intval($_GET['recommend_type']);
        }
        
        $class = IoCload("Skintheme");
        $list = $class->market($this->sort,$_GET['sf'],$this->num,$this->platform,$this->screen_w,$this->screen_h,$this->ver_name,$umode,$this->foreign_access,$recommend_type);
        
        return $list;
    }
    
    /**
     * 获取广告信息
     * @return
     */
    public function getAdsList() {
        $end = $this->dsf + $this->num - 1;
        switch($this->sort) {
            case 'skinonly':
                $ad_zone = 5;
                break;
            case 'down':
                $ad_zone = 5;
                break;
            case '':
                $ad_zone = 4;
        }
        
        $cache_key = __Class__ . '_list_data_' . $this->dsf . '_' . $this->num . '_' . $ad_zone . '_cachekey';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte": ' . $end . '}}';
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));

		return $ads_res;
	}
	
	
	/**
     * 运营分类&&猜你喜欢
     * @return
     */
    public function getSkinthemeOperationCategory() {
        $class = IoCload("Skintheme");
        $list = $class->operationCategory($this->sf, $this->num, $this->platform, $this->ver_name, $this->foreign_access);
        
        return $list;
    }
    
    /**
     * 获取运营分类&&猜你喜欢广告信息
     * @return
     */
    public function getSkinthemeOperationCategoryAdsList() {
        $end = $this->dsf + $this->num - 1;
        $ad_zone = 3;
        
        $cache_key = __Class__ . '_list_data_' . $this->dsf . '_' . $this->num . '_' . $ad_zone . '_cachekey';
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte": ' . $end . '}}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));

		return $ads_res;
	}
	
	/**
     * 自然分类下所有皮肤主题
     * @return
     */
    public function getSkinthemeCategory() {
        $class = IoCload("Skintheme");
        $list = $class->category($this->sf, $this->num, $this->platform, $this->ver_name, $this->foreign_access, $this->wap_os);
        
        return $list;
    }
	
	/**
     * 获取皮肤普通分类的广告信息
     * @return
     */
    public function getSkinthemeCateAllAdsList() {
        $end = $this->dsf + $this->num - 1;
        $ad_zone = 6;
        
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte":' . $end . '}}';
        $cache_key = __Class__ . '_cate_ads_url_data_' . $ad_zone . '_' . $this->dsf . '_' . $this->num . '_cachekey';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));
        
        return $ads_res;
	}
	
	/**
     * 某自然分类下所有皮肤主题
     * @param $cate 某自然分类
     * @return
     */
    public function getSkinthemeCategoryItem($cate = '') {
        $class = IoCload("Skintheme");
        $list = $class->cateSkins($cate, $this->sf, $this->num, $this->platform, $this->screen_w, $this->screen_h, $this->ver_name, $this->foreign_access, $this->wap_os);
        
        return $list;
    }
	
	/**
     * 获取皮肤普通分类下的皮肤广告信息
     * @param $cate 某自然分类
     * @return
     */
    public function getSkinthemeCateAdsList($cate = '') {
        $cache_key = __Class__ . '_cate_list_data_' . $cate . '_cachekey';
        $ads_url = '/res/json/input/r/online/adzone_ip_mapping/?onlycontent=1&search={"url_path":"/v5/skin_ads/cate/' . $cate . '"}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));

		return $ads_res;
	}
	
	/**
     * 根据生成皮肤广告url获取广告数据
     * @param $ads_url 广告链接
     * @return
     */
    public function getSkinthemeAdsUrlRes($ad_arr = array()) {
        $ad_zone_id = -1;
        
        foreach($ad_arr as $ad) {
            $ad_zone_id = $ad['ad_zone'];
        }
        
        $end = $this->dsf + $this->num - 1;
        $ads_url = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $ad_zone_id . ',"ad_pos":{"$gte":' . $this->dsf . '},"ad_pos":{"$lte":' . $end . '}}';
        
        $cache_key = __Class__ . '_cate_ads_url_data_' . $ad_zone_id . '_' . $this->dsf . '_' . $this->num . '_cachekey';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));
        
        return $ads_res;
	}
	
	/**
	 * @route({"GET","/preview"})
	 * @param({"adZone", "$._GET.adZone"}) 广告区位
	 * @param({"token", "$._GET.token"}) 皮肤token
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"})
	 */
	public function previewSkin($adZone=25, $token='') {
	    $result = array(
	        'code' => 0,
	        'msg' => '',
	        'data' => array(),
	    );
	    if (empty($token)) {
	        $result['code'] = 1;
	        $result['msg'] = 'params error';
	        return $result;
	    } else {
	        $arrToken = explode(',', $token);
        }
	    //获取广告数据
	    $adsUrl = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":' . $adZone . '}';
	    $cacheKey = md5(self::CACHE_PRE . 'preview_skin_ads_data_cachekey' . $adsUrl);
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
	    $adsRes = Util::ralGetContent($adsUrl, $cacheKey, GFunc::getCacheTime('ads_cache_time'));
	    //过滤条件判断
	    $adsRes = Util::filterAds($adsRes);
	    //包装trace地址和时间判断
	    $time = time();
	    foreach ($adsRes as $adsResK => $adsResV) {
	        //通过皮肤token判断是否有对应的广告
	        if (!empty($adsResV['skin_ids'])) {
	            $skinIdsKey = md5(self::CACHE_PRE . $adsResV['skin_ids']);
	            $skinIds = GFunc::cacheGet($skinIdsKey);
	            if (false === $skinIds || null === $skinIds) {
    	            $orpFetchUrl = new \Orp_FetchUrl();
    	            $httpproxy = $orpFetchUrl->getInstance(array('timeout' => 3000));
    	            $skinIds = $httpproxy->get($adsResV['skin_ids']);
    	            GFunc::cacheSet($skinIdsKey, $skinIds, GFunc::getCacheTime('ads_cache_time'));
	            }
	            if (false !== $skinIds) {
	                $getSkinToken = false;
	                $arrSkinIds = explode("\n", $skinIds);
	                if (!empty($arrSkinIds)) {
	                    foreach ($arrSkinIds as $arrSkinIdsK => $arrSkinIdsV) {
	                        foreach ($arrToken as $arrTokenV) {
                                if (trim($arrTokenV) == trim($arrSkinIdsV)) {
                                    $getSkinToken = true;
                                    break 2;
                                }
                            }
	                    }
	                }
	                if (false === $getSkinToken) {
	                    unset($adsRes[$adsResK]);
	                    continue;
	                }
	            } else {
	                $result['code'] = 1;
	                $result['msg'] = 'get skin file error';
	                return $result;
	            }
	        }
	        //时间判断
	        if ($time < $adsResV['sttime'] || $time > $adsResV['exptime']) {
	            unset($adsRes[$adsResK]);
	            continue;
	        }
	        //如果是CPC或者CPM模式，判断点击数是否达到配置的数值，如果达到，则不下发
	        if (2 == $adsResV['ad_mode']['ad_mode'] || 3 == $adsResV['ad_mode']['ad_mode']) {
    	        $cacheCountKey = md5(self::CACHE_PRE . 'preview_skin_ads_data_cachekey' . $adsResV['ad_id']);
    	        $count = GFunc::cacheGetOrigin($cacheCountKey);
    	        if (intval($count) >= $adsResV['ad_mode']['number']) {
    	            unset($adsRes[$adsResK]);
    	            continue;
    	        }
	        }
	        //CPM模式直接发起回调，如果有多条广告只对第一条广告进行回调
	        $isCallback = true;
	        if (true === $isCallback && 2 == $adsResV['ad_mode']['ad_mode']) {
    	        $objSkinadModel = new SkinadsModel();
	            $callbackRes = $objSkinadModel->skinCallback($adsResV, self::CACHE_PRE);
	            $isCallback = false;
	        }
            if ($this->isSkinPreview($adsResV)) {
                $adsRes[$adsResK] = $this->calcAdHeight($adsResV);
            }
	    }
        //按照优先级排序
        $adsRes = Util::arraySort($adsRes, 'priority', 'desc');
        $result['data'] = array_values($adsRes);
	    
	    return $result;
	}

    private function isSkinPreview($adsResV)
    {
        return $adsResV['ad_zone'] == self::AD_ZONE_SKIN_PREVIEW;
    }

    private function calcAdHeight($adsResV)
    {
        if (isset($adsResV['ad_height'])) {
            $adsResV['height'] = $adsResV['ad_height']['number'] . $adsResV['ad_height']['ad_height'];
            return $adsResV;
        }

        $adsResV['height'] = self::DEFAULT_SKIN_HEIGHT;
        return $adsResV;
    }

	/**
	 * @route({"GET","/previewSkinCallback"})
     * http://agroup.baidu.com/inputserver/md/article/764524
	 * @param({"adId", "$._GET.adId"}) int id
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"})
	 */
	public function previewSkinCallback($adId=0) {
	    $result = array(
	        'code' => 0,
	        'msg' => '',
	        'data' => array(),
	    );
	    //根据id获取广告数据
	    $adsUrl = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_id":' . $adId . '}';
	    $cacheKey = md5(self::CACHE_PRE . 'preview_skin_ads_data_cachekey' . $adsUrl);
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
	    $adsRes = Util::ralGetContent($adsUrl, $cacheKey, GFunc::getCacheTime('ads_cache_time'));
	    if (empty($adsRes)) {
	        $result['code'] = 1;
	        $result['msg'] = '获取广告信息错误';
	    } else {
	        $adsResVal = current($adsRes);
	    }
	    $objSkinadModel = new SkinadsModel();
	    $callbackRes = $objSkinadModel->skinCallback($adsResVal, self::CACHE_PRE);
	    if (false === $callbackRes) {
	        $result['code'] = 1;
            $result['msg'] = 'incr error';
	    }
	    
	    return $result;
	}
	
	/**
     * 获取皮肤运营分类下的皮肤广告信息
     * @param $cate 某运营分类
     * @return
     */
    public function getSkinthemeOpcateAdsList($cat = '') {
        $cache_key = __Class__ . '_opcate_list_data_' . $cat . '_cachekey';
        $ads_url = '/res/json/input/r/online/adzone_ip_mapping/?onlycontent=1&search={"url_path":"/v5/skin_ads/opcate/' . $cat . '"}';
        $obj = new CacheVersionSwitchScope(GFunc::getCacheInstance(), "advertisement");
        $ads_res = Util::ralGetContent($ads_url,$cache_key,GFunc::getCacheTime('ads_cache_time'));

		return $ads_res;
	}
	
	/**
     * 某运营分类下所有皮肤主题
     * @param $cate 某自然分类
     * @return
     */
    public function getSkinthemeOpcateItem($cate = '') {
        $class = IoCload("Skintheme");
        $list = $class->operationCategorySkins($cate, $this->sf, $this->num, $this->platform, $this->screen_w, $this->screen_h, $this->ver_name, $this->foreign_access);
        
        return $list;
    }
}
