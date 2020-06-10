<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/vivo_associate_switch/")
 * VivoAssociateSwitch.php UTF-8 2019-7-25 14:27:36
 */
use utils\Util;
use utils\GFunc;
use models\FilterModel;

class VivoAssociateSwitch {

    /**
     *
     * vivo 智能联想开关
     *
     * @route({"GET", "/get"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": "{'switch':1}"
     * }
     */
    public function get() {
        //输出格式初始化
        $rs = Util::initialClass();
        
        $rs['data']->switch=0;
        
        $cacheKey = 'vivo_associate_switch';

        $content = GFunc::cacheZget($cacheKey);

        if ($content === false) {
            $content = GFunc::getRalContent('vivo_associate_switch');

            //set status, msg & version
            $rs['ecode'] = GFunc::getStatusCode();
            $rs['emsg']  = GFunc::getErrorMsg();

            //设置缓存
            GFunc::cacheZset($cacheKey, $content, GFunc::getCacheTime('10mins'));
        }

        //过滤数据
        if (!empty($content)) {
            $filterModel = new FilterModel();
            $content     = $filterModel->getFilterByArray($content);
            if (!empty($content)) {
                $tempRs            = current($content);
                $rs['data']->switch        = $tempRs['switch'];
            }
        }

        return Util::returnValue($rs, true);
    }


    /**
     *
     * vivo 智能联想脱敏算法增加云端更新能力
     *
     * @route({"GET", "/dsdata"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": "{'regex':'(?i)(soho|loft)'}"
     * }
     */
    public function getDesensitized() {
        //输出格式初始化
        $data = Util::initialClass();

        $data['data']->regex="(?i)([\\x21-\\x7E]{4,})([号栋层楼室路]?)";
        $data['data']->regex1="(?i)(soho|loft|zara|hazzys|MO&CO)";


        return Util::returnValue($data, true);
    }

}
