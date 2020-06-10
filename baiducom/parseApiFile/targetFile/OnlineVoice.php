<?php
/***************************************************************************
 *
 * Copyright (c) 2019 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use tinyESB\util\ClassLoader;
use utils\GFunc;
use utils\Util;


ClassLoader::addInclude(__DIR__.'/utils');


/**
 * 在线语音相关
 * Class OnlineVoice
 * Created on 2020-03-16 14:35
 * Created by zhoubin05
 * @path("/onlinevoice/")
 */
class OnlineVoice
{

    /**
     * @desc
     * @route({"GET", "/folw_limit_conf"})
     * @return({"body"})
     *
     */
    public function getFolwLimitConf() {

        $arrOut = Util::initialClass(false);

        $strCacheKey = Util::getCacheVersionPrefix('ime_ol_voice_limit') . '_ime_ol_voice_limit_datawithversion';


        $arrSrcData = GFunc::cacheZget($strCacheKey);

        if(false === $arrSrcData || null === $arrSrcData) {
            $objOnlineVoice = IoCload('models\\OnlineVoiceModel');
            $arrSrcData = $objOnlineVoice->getDataWithVersion();
            GFunc::cacheZset($strCacheKey, $arrSrcData, GFunc::getCacheTime('30mins'));

        }

        $arrOut['version'] =  $arrSrcData['version'];

        $conditionFilter = IoCload("utils\\ConditionFilter");

        $arrSrcData['data'] = $conditionFilter->getFilterConditionFromDB($arrSrcData['data']);

        if(!empty($arrSrcData['data'])) {
            //只获取匹配的第一条 (匹配的更新时间最新的一条）
            $arrTmp = current($arrSrcData['data']);

            $arrOut['data'] = !empty($arrTmp['time_ranges']) ? array('time_ranges' => $arrTmp['time_ranges']) : new stdClass();

        }

        return Util::returnValue($arrOut);

    }




}
