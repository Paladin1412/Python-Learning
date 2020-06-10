<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use utils\KsarchRedis;
use models\LitePluginModel;
use utils\Util;

/**
 * lite版插件下载
 *
 * @author fanwenli
 * @path("/liteplugin/")
 */
class LitePlugin
{
    /**
    *
    * 详情获取
    *
    * @route({"POST", "/detail"})
    * @param({"strVersion","$._POST.version"}) 客户端上传版本
    * @throws({"BadRequest", "status", "400 Bad request"})
    * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"}){
    *      "file": "http://mco.ime.shahe.baidu.com:8891/res/file/lite-plugin-download/files/146960228415431.zip"
    *      ]
    * }
    */
    public function itemDetail($strVersion){
        $out = array();
        
        $LitePluginModel = IoCload('models\\LitePluginModel');
        
        $LitePlugin = $LitePluginModel->getLitePluginData($strVersion);
        
        if(!empty($LitePlugin)){
            foreach($LitePlugin as $val){
                //过滤不通过则进行下一条
                if(!$LitePluginModel->getFilter($val)) {
                    continue;
                }
                
                if(isset($val['file_upload'])){
                    $out['file_list'][] = array('file' => $val['file_upload']);
                }
            }
        }
        
        return $out;
    }
    
    /**
    *
    * 详情获取 -- 通知中心重构
    *
    * @route({"GET", "/info"})
    * @param({"strVersion","$._GET.version"}) 客户端上传版本
    * @throws({"BadRequest", "status", "400 Bad request"})
    * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"}){
    *      "file": "http://mco.ime.shahe.baidu.com:8891/res/file/lite-plugin-download/files/146960228415431.zip"
    *      ]
    * }
    */
    public function getLitePluginInfo($strVersion = 0){
        $out = Util::initialClass(false);
        
        $LitePluginModel = IoCload('models\\LitePluginModel');
        $LitePlugin = $LitePluginModel->getLitePluginData($strVersion);
        
        //set return array
        $data = array();
        if(!empty($LitePlugin)){
            foreach($LitePlugin as $val){
                //过滤不通过则进行下一条
                if(!$LitePluginModel->getFilter($val)) {
                    continue;
                }
                
                if(isset($val['file_upload'])){
                    $data[] = $val['file_upload'];
                }
            }
        }
        
        $out['ecode'] = $LitePluginModel->intStatusCode;
        $out['emsg'] = $LitePluginModel->strStatusMsg;
        $out['data'] = $data;
        $out['version'] = intval($LitePluginModel->intMsgVer);
        
        return Util::returnValue($out,false);
    }
}
