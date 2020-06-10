<?php

/**
 * 斗图tab
 * @path("/doutu/")
 */
use utils\Util;

class Doutu {
    /**
     *
     * 斗图tab数据下发
     * @agroup http://agroup.baidu.com/inputserver/md/article/1814951
     *
     * @route({"GET", "/get"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": {}
     *      "version": 1526352952,
     *      ]
     * }
     */
    public function get($intMsgVersion = 0) {
        $model = new \models\DoutuTabModel();
        //输出格式初始化
        $rs = Util::initialClass();
        $data = array(
            "categories" => $model->getContent(),
        );
        $rs['data'] = $data;
        $rs['version']=$model->getVersion();
        return Util::returnValue($rs, true, true);
    }
}
