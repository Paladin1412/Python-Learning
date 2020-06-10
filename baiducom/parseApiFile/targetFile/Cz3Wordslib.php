<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/cz3wordslib/")
 * Cz3Wordslib.php UTF-8 2018-9-28 15:58:39
 */
use utils\Util;
use utils\GFunc;
use models\FilterModel;

class Cz3Wordslib {

    /**
     *
     * cz3词库
     *
     * @route({"GET", "/get"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": ".....",
     *      "version": 1526352952,
     *      ]
     * }
     */
    public function get($intMsgVersion = 0) {
        //输出格式初始化
        $rs = Util::initialClass();

        $cacheKey        = 'cz3_wordslib';
        $cacheKeyVersion = $cacheKey . '_version';

        $version = GFunc::cacheZget($cacheKeyVersion);
        $content = GFunc::cacheZget($cacheKey);

        if ($content === false) {
            $content = GFunc::getRalContent('cz3_wordslib');

            //set status, msg & version
            $rs['ecode'] = GFunc::getStatusCode();
            $rs['emsg']  = GFunc::getErrorMsg();
            $version     = intval(GFunc::getResVersion());

            //设置缓存
            GFunc::cacheZset($cacheKey, $content, GFunc::getCacheTime('2hours'));
            //设置版本缓存
            GFunc::cacheZset($cacheKeyVersion, $version, GFunc::getCacheTime('2hours'));
        }

        $rs['version'] = intval($version);

        //过滤数据
        if (!empty($content)) {
            $filterModel = new FilterModel();
            $content     = $filterModel->getFilterByArray($content);
            if (!empty($content)) {
                $tempRs                 = current($content);
                $arrData=$tempRs['wordslib'];
                $arrData['name']        = $tempRs['name'];
                $arrData['versionCode'] = $tempRs['versionCode'];
                $arrData['description'] = $tempRs['description'];
                $rs['data']=$arrData;
            }
        }

        return Util::returnValue($rs,true,true);
    }

}
