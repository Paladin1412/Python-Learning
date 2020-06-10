<?php
use utils\Bos;
use utils\Util;
use utils\ErrorCode;
use utils\GFunc;

/**
 * 下载
 * Class Download
 * Created on 2019-08-19 16:29
 * Created by zhoubin05
 * @path("/download/")
 */
class Download
{

    /**
     * @route({"GET","/direct"})
     * http://agroup.baidu.com/share/md/a8ee02b3076446c6a3ff37f8cc357ff8
     * 上传在接口/amisapi/tools/uploadFile
     *
     * @return
     */
    function directDownload() {

        $strSalt = "#)JuY!&M_f!NFI#*(!jfa";

        $strSign = strtoupper(trim($_GET['sign']));
        if(empty($strSign)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR,'param sign is empty');
        }

        $strTm = intval(trim($_GET['tm']));

        if(!isset($_GET['tm']) || $strTm < 0 ) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR,'param tm is empty or error');
        }

        $strKey = trim($_GET['download_key']);


        if($strSign !==  strtoupper(md5($strSalt . $strTm . $strKey)) ) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR,'sign error');
        }

        $strUrl = $this->getBosImeresOuterfileByFileMd5($strKey);

        if(!empty($strUrl)) {
            header("HTTP/1.1 302 Found");
            header("status: 302 Found");
            header("Location: " . $strUrl);
            return;
        } else {
            header('X-PHP-Response-Code: '. 404, true, 404);
            return;
        }


    }


    /**
     * 根据md5值获取文件
     * @param $strKey
     * @return
     * @throws Exception
     */
    private function getBosImeresOuterfileByFileMd5($strKey) {

        if(empty($strKey)) {
            return false;
        }

        $strCacheKey = 'file_download_'. GFunc::getGlobalConf("env") . '_' .$strKey;

        $strUrl = GFunc::cacheGet($strCacheKey);
        if(null === $strUrl || false === $strUrl) {
            $objResBos = new Bos('imeres','outerfile');

            $arrList = $objResBos->listObjects(array('prefix'=> GFunc::getGlobalConf("env")  . "/$strKey"));
            if(1 == $arrList['status'] && !empty($arrList['data']['contents'][0]['key']) ) {
                $strUrl = 'https://imeres.baidu.com/outerfile/' . $arrList['data']['contents'][0]['key'];
                GFunc::cacheSet($strCacheKey, $strUrl, GFunc::getCacheTime('2hours'));
            } else {
                GFunc::cacheSet($strCacheKey, '', GFunc::getCacheTime('15mins'));
            }


        }

        return (null === $strUrl || false === $strUrl) ? false : $strUrl;
    }




}
