<?php
use utils\Util;
use utils\ErrorCode;
use utils\ErrorMsg;
/**
 * 二次元皮肤、字体文件下发
 * @author chendaoyan
 * @date 2018年3月8日
 * @path("/skinfont/")
 */
class SkinFont {
    private static $cachePre = 'skinfont_';
    
    /**
     * 二次元皮肤、字体列表下发
     * @route({"GET", "/getlist"})
     * @param({"type", "$._GET.type"}) string $type 请求类型 1 全部 2皮肤 3字体
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getList($type=1) {
        $result = Util::initialClass();
        //从资源服务获取数据
        $cacheKey = self::$cachePre . __Class__ . __FUNCTION__ . '_' . $type . '_cachekey';
        $resUrl = '/res/json/input/r/online/skin-font/?onlycontent=1';
        $list = Util::ralGetContent($resUrl, $cacheKey);
        if (!empty($list)) {
            $conditionFilter = IoCload("utils\\ConditionFilter");
            foreach ($list as $listK => $listVV) {
                if (!$conditionFilter->filter($listVV['filter_conditions'])) {
                    unset($list[$listK]);
                }
            }
            $listV = current($list);
            switch ($type) {
                case 1:
                    $result['data']->file = $listV['file'];
                    $result['data']->size = $listV['size'];
                    break;
                case 2:
                    $result['data']->file = $listV['file2'];
                    $result['data']->size = $listV['size2'];
                    break;
                case 3:
                    $result['data']->file = $listV['file3'];
                    $result['data']->size = $listV['size3'];
                    break;
                
                default:
                    $result['data']->file = $listV['file'];
                    $result['data']->size = $listV['size'];
                    break;
            }
        } else {
            $result['ecode'] = ErrorCode::RES_ERROR;
            $result['emsg'] = ErrorMsg::getMsg(ErrorCode::RES_ERROR);
        }
        
        return $result;
    }

    /**
     *
     * 特技字体
     * @route({"GET", "/stunt_fonts"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": {"url":xxxx},
     *      "version": 1526352952,
     *      ]
     * }
     */
    public function getStuntFonts($intMsgVersion = 0){
        $model = IoCload(\models\StuntFonts::class);
        //输出格式初始化
        $rs = Util::initialClass();
        $rs['data'] = $model->getResource();
        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs);
    }
}