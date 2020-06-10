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
 * Class ContentDown
 * Created on 2019-12-10 15:50
 * Created by zhoubin05
 * @path("/contentdown/")
 */
class ContentDown
{


    /**
     * @desc
     * @route({"GET", "/skintoken"})
     * @return({"body"})
     *
     */
    public function getSkinTokens() {

        $arrOut = Util::initialClass(false);

        $strCacheKey = Util::getCacheVersionPrefix('ime_content_down') . '_ime_content_down_datawithversion';


        $arrSrcData = GFunc::cacheZget($strCacheKey);

        if(false === $arrSrcData || null === $arrSrcData) {
            $modContentdown = IoCload('models\\ContentDownModel');
            $arrSrcData = $modContentdown->getDataWithVersion();
            GFunc::cacheZset($strCacheKey, $arrSrcData, GFunc::getCacheTime('30mins'));

        }

        $arrOut['version'] =  $arrSrcData['version'];

        $conditionFilter = IoCload("utils\\ConditionFilter");

        $arrSrcData['data'] = $conditionFilter->getFilterConditionFromDB($arrSrcData['data']);

        if(!empty($arrSrcData['data'])) {
            $arrTmp = current($arrSrcData['data']);

            $arrTmp['id'] = intval($arrTmp['id']);

            $arrTmp['download_env'] = intval($arrTmp['download_env']);


            switch (intval($arrTmp['ctype'])) {
                case 1:
                    $arrContent = array();
                    $arrCotTmp = explode(PHP_EOL,$arrTmp['content']);
                    foreach($arrCotTmp as $strCot) {
                        $strTmp = trim($strCot);
                        if(!empty($strTmp)) {
                            array_push($arrContent, $strTmp);
                        }

                    }

                    $arrTmp['content'] = $arrContent;
                    unset($arrTmp['dlink']);
                    unset($arrTmp['md5']);
                    break;
                default:
                    $arrOut['md5'] = isset($arrTmp['md5']) ? $arrTmp['md5'] : '';
                    break;
            }

            if(!empty($arrTmp['dlink'])) {
                $strDownloadUrl = GFunc::getGlobalConf('micweb_webroot_bos').$arrTmp['dlink'];
                $strSingSlat = 'iudfu(lkc#xv345y82$dsfjksa';
                $strEncodeUrl = urlencode($strDownloadUrl);
                $strSign = md5($strDownloadUrl . $strSingSlat);
                $arrTmp['dlink'] = GFunc::getGlobalConf('domain_v5') . 'v5/trace?url='
                    . $strEncodeUrl . '&sign=' . $strSign;
            }


            $arrOut['data'] = $arrTmp;

        } else {
            $arrOut['ecode'] = 1;
            $arrOut['emsg'] = 'success ! but no date for response';
        }

        return Util::returnValue($arrOut);

    }




}
