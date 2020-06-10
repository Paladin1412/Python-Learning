<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Util;

/**
 * 位置获取接口
 *
 * @author fanwenli
 * @path("/position/")
*/
class Position
{
    /**
     *
     * 返回apinfo的地址参数值，比如x,y坐标等
     *
     * @route({"POST", "/info"})
     * @param({"strApinfo","$._POST.apinfo"})
     * @param({"strCoor","$._POST.coor"})
     * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
     * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
     * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
     *      ]
     * }
    */
    public function getInfo($strApinfo, $strCoor, $strPlatform, $strVersion, $strCuid){
        //$out = array('data' => '');
        
        //输出格式初始化
        $out = Util::initialClass(false);
        /*
        Array
        (
            [country] => 中国
            [cc] => CN
            [province] => 上海
            [city] => 上海
            [street] => 祥科路
            [subinfo] => 浦东新区
            [x] => 13538024.223893
            [y] => 3634587.893152
            [timezone] => 
        )
        */
        
        $arrPosition = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo, $strCoor);
        
        if(!empty($arrPosition)){
            $out['data'] = bd_B64_encode(json_encode($arrPosition),0);
        } else {
            $out['data'] = '';
        }
        
        return Util::returnValue($out,false);
    }
}
