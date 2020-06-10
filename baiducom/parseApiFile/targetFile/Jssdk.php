<?php

use utils\ErrorCode;
use utils\Util;
use utils\GFunc;

/**
 * jssdk白名单下发
 * User: chendaoyan
 * Date: 2020/4/15
 * Time: 14:58
 * @path("/jssdk/")
 */
class Jssdk {
    /**
     * @desc 下发包列表
     * @document http://agroup.baidu.com/inputserver/md/article/2695812
     * @route({"GET", "/whiteList"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function whiteList() {
        $result = Util::initialClass();

        $key = __CLASS__ . __METHOD__ . "baiduInputJSBridge";
        $resUrl = '/res/json/input/r/online/baiduInputJSBridge/?onlycontent=1';
        $filterContent = Util::ralGetContent($resUrl, $key, GFunc::getCacheTime('2hours'));
        if (!empty($filterContent)) {
            $content = current($filterContent);
            $result['data']->list = isset($content['domain']) ? $content['domain'] : array();
        } else if (false === $filterContent) {
            return ErrorCode::returnError("RES_ERROR", '资源服务数据获取失败', false, true);
        } else {
            $result['data']->list = array();
        }

        return Util::returnValue($result, true, true);
    }
}