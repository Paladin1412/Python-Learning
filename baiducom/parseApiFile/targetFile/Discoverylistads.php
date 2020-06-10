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
 * 发现list广告
 *
 *
 * @author fanwenli
 * @path("/discovery_list_ads/")
 */
class Discoverylistads
{
    /** @property 页数 */
    private $page = 0;
    
    /**
     *
     */
    function __construct() {
        //set inputgd as follow
        $_GET['inputgd'] = 1;
        
        $this->page = intval($_GET['page']);
    }


    /**
     *
     * @route({"POST","/list"})
     *
     * 发现list广告列表
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
        "lastpage": "1",
        "plugins": [
            {
                "id": "com.baidu.input.plugin.kit.22222",
                "name": "test",
                "name2": "test",
                "desc": "test",
                "store_rmd": "0",
                "logo_down": "http://res.mi.baidu.com/imeres/emot-pack-test/%2Fplugin_store_logo_com.baidu.input.plugin.kit.22222_1427353631.pic",
                "thum1_down": "http://res.mi.baidu.com/imeres/emot-pack-test/%2Fpluginthum1_com.baidu.input.plugin.kit.22222_1427353631.pic",
                "thum2_down": "http://res.mi.baidu.com/imeres/emot-pack-test/%2Fpluginthum2_com.baidu.input.plugin.kit.22222_1427353631.pic",
                "pub_item_type": "non_ad",
                "subclasses": [
                    {
                        "reason": "",
                        "version": 1,
                        "version_name": "1.0.2.0",
                        "size": "129521",
                        "md5": "fe3770e6d98d2deafa3a3aa7f9bd394d",
                        "download": "http://10.58.19.57:8890/v5/trace?url=http%3A%2F%2Fres.mi.baidu.com%2Fimeres%2Femot-pack-test%2F%252Fime_api_v4_plugin_com.baidu.input.plugin.kit.22222_1427353631&sign=daf9afd39ccaecf87aa7be13cc0045ae&rsc_from=plugin&apk_name=com.baidu.input.plugin.kit.22222&version=1&vername=1.0.2.0",
                        "mdate": "1429587367",
                        "min": "5.0",
                        "max": "",
                        "support": "1"
                    }
                ]
            },
        ],
        "app_rcmd": "0"
        }
     */
    public function getList() {
        $_GET['type'] = 'store';
        
        //discovery resource
        $discovery_res = $this->getPluginList();
        
        $new_plugins = array();
        
        $i = 0;
        foreach($discovery_res['plugins'] as $plugin) {
            if($plugin['pub_item_type'] == 'prod_ad') {
                $plugin['ad_id'] = 0;
                $plugin['ad_zone'] = 12;
                $ad_pos = $this->page - 1 + $i;
                $plugin['ad_pos']= $ad_pos;
                
                $ad_info = 'ad_type=prod_ad&ad_id=0&ad_zone=12&ad_pos=' . $ad_pos;
                
                $new_subclasses = array();
                if(isset($plugin['subclasses']) && !empty($plugin['subclasses'])) {
                    foreach($plugin['subclasses'] as $subclass) {
                        $subclass['download'] .= '&' . $ad_info;
                        $new_subclasses[] = $subclass;
                    }
                }
                $plugin['subclasses'] = $new_subclasses;
            }
            
            $new_plugins[$i] = $plugin;
            $i++;
        }
        
        $discovery_res['plugins'] = $new_plugins;
        
        return $discovery_res;
    }
	
	/**
     * 获取表情市场数据
     * @return
     */
    public function getPluginList() {
        $class = IoCload("Plugin");
        $list = $class->getList();
        
        return $list;
    }
}
