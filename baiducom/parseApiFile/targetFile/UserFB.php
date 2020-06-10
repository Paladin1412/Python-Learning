<?php

use utils\Bos;
use utils\ErrorCode;
use utils\Util;
use utils\GFunc;

/**
 *
 * 用户反馈接口获取用户反馈信息,下载用户反馈文件
 * @path("/us/")
 */

class UserFB
{
    /**
     *
     * 获取用户反馈的文件夹信息
     * @param({"strPath","$.path[2:]"})
     * @route({"GET","/info"})
     * @return array
     */
    public function getInfo($strPath)
    {
        $strPath = $this->getPath($strPath);
        $strPath = urldecode($strPath);
        $strPath = trim(trim($strPath),'/') . '/';

        /*
         * todo 地址写进配置里
         */
        $bos = new Bos('imeres', 'imeres');
        $result = $bos->listObjects(array('prefix'=>'ime-res/us/'.$strPath));
        $data = array();
        foreach ($result['data']['contents'] as $v)
        {
            $obj['keyword'] = basename($v['key']);
            $data[] = $obj;
        }

        return $data ;
    }

    /**
     *
     * 获取用户反馈zip文件
     * @param({"strPath","$.path[2:]"})
     * @route({"GET","/file"})
     * @return string
     */
    public function getFile($strPath)
    {
        $strPath = $this->getPath($strPath);
        $strPath = urldecode($strPath);
        
        //Edit by fanwenli on 2018-08-08, set bos domain with conf
        $strBosDomain = Util::getGFuncGlobalConf();

        /*
         * todo 地址写进配置里
         */
        $filePath = $strBosDomain . '/imeres/ime-res/us/'.$strPath;

        header("HTTP/1.1 302 Found");
        header("status: 302 Found");
        header("Location: " . $filePath);

        exit;
    }

    /**
     * @desc 用于图片搜索的的反馈
     * @param({"cuid", "$._GET.cuid"})
     * @route({"POST","/imagefb"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function imageSearchFB($cuid = '') {
        if(empty($cuid)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "common params error");
        }
        if(empty(trim($_POST['query']))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "当前图搜关键词不能为空");
        }
        if(empty(trim($_POST['resource_id'])) || empty(trim($_POST['thumbnail'])) || empty(trim($_POST['image']))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "资源信息缺失");
        }
        $fbtypes = explode(',', trim($_POST['fb_type']));
        if(empty($_POST['fb_type']) || count($fbtypes) == 0) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "反馈类型缺失");
        }
        // 反馈类型100 默认为其他类型
        $otherType = 100;
        if(in_array($otherType, $fbtypes) && empty(trim($_POST['fb_detail']))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "请填写反馈详细信息");
        }
        // 限制提交速率， 30s 最多提交50次
        $limitRateCacheKey = 'image_search_fb_limit_rate_' . md5($cuid);
        $accessTimes = GFunc::getCacheInstance()->incr($limitRateCacheKey);
        if($accessTimes == 1) {
            // 设置过期时间
            if(!GFunc::getCacheInstance()->expire($limitRateCacheKey, 30)) {
                // 如果有效期设置失败， 删除该 key
                GFunc::getCacheInstance()->del($limitRateCacheKey);
            }
        }
        if($accessTimes >= 50 || false === $accessTimes || is_null($accessTimes)) {
            return ErrorCode::returnError(ErrorCode::UNKNOW_ERROR, "您提交的太快啦");
        }

        // 防止用户对同一个资源在一天之类反复提交
        $limitCacheKey = 'image_search_fb_limit_' . md5($cuid . trim($_POST['resource_id']));
        $result = GFunc::cacheGet($limitCacheKey);
        if($result !== false) {
            // 在规定的时间内限制同一个用户对同一个资源进行重复提交
            return ErrorCode::returnError(ErrorCode::UNKNOW_ERROR, "您在今天已经对该资源进行了反馈");
        }
        $reourceFeedBack = IoCload("models\\ResourceFeedBackModel");
        $reourceFeedBackExt = IoCload("models\\ResourceFeedBackExtModel");
        try {
            $reourceFeedBack->startTransaction();
            $result = true;
            $feedbackId = -1;
            foreach($fbtypes as $fbtype) {
                $feedbackId = $reourceFeedBack->save(trim($_POST['resource_id']), 1, $fbtype, array(
                    'search_keyword' => trim($_POST['query']),
                    'resource_thumbnail' => trim($_POST['thumbnail']),
                    'resource_url' => trim($_POST['image']),
                ));
                if(false === $feedbackId) {
                    $result = false;
                    break;
                }
            }
            // 如果有详情， 就记录下来
            if($feedbackId > 0 && !empty(trim($_POST['fb_detail'])) && $result) {
                $extRes = $reourceFeedBackExt->insert([
                    'feedback_id' => $feedbackId,
                    'cuid' => $cuid,
                    'fb_detail' => trim($_POST['fb_detail']),
                    'create_time' => time(),
                ]);
                if(!$extRes) {
                    $result = false;
                }
            }
            if(!$result) {
                $reourceFeedBack->rollback();
                return ErrorCode::returnError(ErrorCode::DB_ERROR, "请稍后再试");
            }
            $reourceFeedBack->commit();
            // 成功后限制重复提交
            GFunc::cacheSet($limitCacheKey, trim($_POST['resource_id']), GFunc::getCacheTime('hours') * 24);
        } catch (\Throwable $th) {
            $reourceFeedBack->rollback();
            return ErrorCode::returnError(ErrorCode::DB_ERROR, "请稍后再试");
        }
        $response = Util::initialClass(false);
        return Util::returnValue($response);
    }


    /**
     * 获取路径
     *
     * @param $path 请求路径数组
     *
     * @return string
     */
    private function getPath($path){
        if(is_array($path)){
            $path = implode('/', $path);
        }
        if(strlen($path)!==0){
            $path = '/'.$path;
        }
        if(substr($_SERVER['PATH_INFO'], - 1, 1 ) === '/'){
            $path .= '/';
        }
        if(strlen($path)==0){
            $path = '/';
        }
        return $path;
    }

}
