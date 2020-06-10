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
 * 精品list广告
 *
 *
 * @author fanwenli
 * @path("/boutique_list_ads/")
 */
class Boutiquelistads
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
     * 精品list广告
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
            "apps": [
            {
                "pid": "13",
                "id": "1412064608",
                "apk_name": "com.tencent.mm",
                "name": "微信",
                "subhead": "1.可以发语音、文字消息、表情、图片...",
                "version_name": "5.0.0",
                "icon": "http://res.mi.baidu.com/imeres/emot-pack-test/%2Fime_api_v4_appstore_icon_1412064608.pic",
                "thum1": "http://res.mi.baidu.com/imeres/emot-pack-test/%2Fime_api_v4_appstore_thum1_icon_1412064608.pic",
                "thum2": "http://res.mi.baidu.com/imeres/emot-pack-test/%2Fime_api_v4_appstore_thum2_icon_1412064608.pic",
                "durl": "http://mime.shahe.baidu.com/v5/trace/cpd?url=http%3A%2F%2Fm.baidu.com%2Fapi%3Faction%3Dredirect%26token%3Dshurufa%26from%3D1010184n%26type%3Dapp%26dltype%3Dnew%26tj%3Dsoft_6907657_2786482313_%25E5%25BE%25AE%25E4%25BF%25A1%26blink%3D2a64687474703a2f2f67646f776e2e62616964752e636f6d2f646174612f7769736567616d652f316239333932656164633362646466312f5765436861745f3438302e61706b2a54%26crversion%3D1%26f%3Dsource%2BNATURAL%40boardid%2Bnone%40pos%2B2%40terminal_type%2Bclient&sign=b6feb27cfe96da1fe6627f56d6c96b52&rsc_from=imeapp&apk_name=com.tencent.mm&src_id=1412064608&cpd_num=500000000&sorder=1&rsc_name=%E5%BE%AE%E4%BF%A1&ad_type=prod_ad&ad_id=0&ad_zone=15&ad_pos=0&ad_type=prod_ad&ad_id=0&ad_zone=15&ad_pos=0",
                "size": "24157752",
                "mtime": "1464675955",
                "pub_item_type": "prod_ad",
                "cpd_num": "500000000",
                "desc": "1.可以发语音、文字消息、表情、图片、视频。30M流量可以收...",
                "type": "1",
                "ad_id": 0,
                "ad_zone": 15,
                "ad_pos": 0
            }
            ],
            "lastpage": "1",
            "count": 7
        }
     */
    public function getList() {
        //set type and inputgd as follow
        $_GET['inputgd'] = 1;
        
        $page = intval($_GET['page']);
        
        $Appstore_class = IoCload("Appstore");
        $list = $Appstore_class->getList($page);
        
        $list = is_array($list)? $list : json_decode($list, true);

        $apps = $list['apps'];
        $new_apps = array();
        
        foreach($apps as $k => $app) {
            if($app['pub_item_type'] == 'prod_ad') {
                $app['ad_id'] = 0;
                $app['ad_zone'] = 15;
                $app['ad_pos'] = $page - 1 + $k;
                $ad_info = 'ad_type=prod_ad&ad_id=' . $app['ad_id'] . '&ad_zone=' . $app['ad_zone'] . '&ad_pos=' . $app['ad_pos'];
                $app['durl'] .= '&' . $ad_info;
            }
            
            $new_apps[$k] = $app;
        }
        
        $list['apps'] = $new_apps;

        return $list;
    }

}
