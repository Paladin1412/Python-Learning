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
 *
 * Class CorefileDown
 * Created on 2019-12-05 18:14
 * Created by zhoubin05
 * @path("/corefiledown/")
 *
 */
class CorefileDown
{


    /**
     * @desc
     * @route({"GET", "/ensys"})
     * @return({"body"})
     *
     */
    public function getEnSysBin() {
        $arrOut = Util::initialClass(false);

        $strCacheKey = Util::getCacheVersionPrefix('ime_corefile_down') . '_corefiledown_datawithversion';


        $arrSrcData = GFunc::cacheZget($strCacheKey);

        if(false === $arrSrcData || null === $arrSrcData) {
            $modCorefile = IoCload('models\\CorefileDownModel');
            $arrSrcData = $modCorefile->getDataWithVersion();
            GFunc::cacheZset($strCacheKey, $arrSrcData, GFunc::getCacheTime('30mins'));

        }

        $arrOut['version'] =  $arrSrcData['version'];

        $conditionFilter = IoCload("utils\\ConditionFilter");

        $arrSrcData['data'] = $conditionFilter->getFilterConditionFromDB($arrSrcData['data']);


        if(!empty($arrSrcData['data'])) {
            $arrTmp = current($arrSrcData['data']);

            $arrTmp['id'] = intval($arrTmp['id']);

            $arrTmp['download_env'] = intval($arrTmp['download_env']);

            $strDownloadUrl = GFunc::getGlobalConf('micweb_webroot_bos').$arrTmp['dlink'];
            $strSingSlat = 'iudfu(lkc#xv345y82$dsfjksa';
            $strEncodeUrl = urlencode($strDownloadUrl);
            $strSign = md5($strDownloadUrl . $strSingSlat);
            $arrTmp['dlink'] = GFunc::getGlobalConf('domain_v5') . 'v5/trace?url='
                . $strEncodeUrl . '&sign=' . $strSign;

            $arrOut['data'] = $arrTmp;

        }

        $arrOut['md5'] = isset($arrOut['data']['fmd5']) ? $arrOut['data']['fmd5'] : '';

        return Util::returnValue($arrOut);

    }




}
