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
 * logo菜单广告
 *
 *
 * @author fanwenli
 * @path("/logo_ads/")
 */
class Logoads
{
    
    
    /**
     *
     */
    function __construct() {
        
    }


    /**
     *
     * @route({"POST","/list"})
     *
     * logo菜单广告
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
    public function getList() {
        //set type and inputgd as follow
        $_GET['type'] = 'logo';
        $_GET['inputgd'] = 1;
        
        $Plugin_class = IoCload("Plugin");
        $Plugin = $Plugin_class->getList();
        
        $Plugin = is_array($Plugin)? $Plugin : json_decode($Plugin, true);
        
        //set plugins content if type is prod_ad
        if(isset($Plugin['plugins']) && !empty($Plugin['plugins'])) {
            foreach($Plugin['plugins'] as $plugin_key => $plugin_val) {
                if($plugin_val['pub_item_type'] == 'prod_ad') {
                    $Plugin['plugins'][$plugin_key]['query_info'] = '&ad_type=prod_ad&ad_id=0&ad_zone=10&ad_pos=' . $plugin_key;
                    $Plugin['plugins'][$plugin_key]['ad_id'] = 0;
                    $Plugin['plugins'][$plugin_key]['ad_zone'] = 10;
                    $Plugin['plugins'][$plugin_key]['ad_pos'] = $plugin_key;
                }
            }
        }

        return $Plugin;
    }

}
