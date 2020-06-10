<?php

/** 
 * @desc 
 * 
 * @author jiangyang05
 * @path("/dataswitch/")
 * Vivo.php UTF-8 2018-6-20 22:14:49
 */
use utils\Util;
use utils\GFunc;
use models\FilterModel;

class DataSwitch{
     /**
     *
     * 数据开关
     *
     * @route({"GET", "/get"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": {"switch":1},
     *      "version": 1526352952,
     *      ]
     * }
    */
    public function getSwitch($intMsgVersion = 0){
        $dataSwitch = IoCload("\models\DataSwitchProtoModel");
        //输出格式初始化
        $rs = Util::initialClass();
        $switch=$dataSwitch->getSwitch();
        if($switch===false){
            $rs['ecode']=1;
            $rs['emsg']='resource content is empty';
        }else{
            $rs['data']=array("switch"=>$switch);
            $rs['version'] = intval($dataSwitch->getVersion());
        }
        return Util::returnValue($rs);
    }
}
