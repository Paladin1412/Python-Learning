<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\GFunc;
use models\UserstatModel;

/**
 * 节日
 *
 * @author fanwenli
 * @path("/userstat/")
 */
class Userstat
{
    private $storeKey = 'input_hash_activity_userstat';
    /**
     *
     * 节日详情获取
     *
     * @route({"GET", "/detail"})
     * @param({"strCuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "content": "[9key] total_cnt=1 decline_cnt=0 sen_cnt=3 ci_cnt=4"
     *      ]
     * }
     */
    public function itemDetail($strCuid = ''){
        $out = array();

        if(isset($strCuid) && $strCuid != '')
        {
            $cache = GFunc::getCacheInstance();

            $content = gzinflate($cache->hget($this->storeKey, $strCuid));

            $content && $out['content'] = $content;
        }

        return $out;
    }
}
