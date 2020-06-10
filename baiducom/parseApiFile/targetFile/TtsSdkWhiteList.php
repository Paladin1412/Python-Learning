<?php

/**
 * @desc
 *
 * @author leiyuqing02
 * @path("/tts_sdk_white_list/")
 */

use utils\Util;

class TtsSdkWhiteList
{
    /**
     *
     * @desc tts sdk white list
     *
     * @route({"GET", "/get"})
     * @param({"message_version", "$._GET.message_version"}) $message_version message_version 客户端上传版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": array,
     *      "version": 1526352952
     * }
     */
    public function getWhiteList($message_version = 0) {
        $model = IoCload("models\\TtsSdkWhiteListModel");
        //输出格式初始化
        $rs = Util::initialClass();
        $rs['data'] = $model->getResource();
        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs);
    }
}