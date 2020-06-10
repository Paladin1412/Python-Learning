<?php
/***************************************************************************
 *
* Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use tinyESB\util\ClassLoader;
use utils\GFunc;
use utils\Util;
use models\VoiceTipsModel;

ClassLoader::addInclude(__DIR__.'/utils');
ClassLoader::addInclude(__DIR__.'/noti');

/**
 *
 * 长语音相关接口
 *
 *
 * @author zhoubin05
 * @path("/voice/")
 */
class Voice
{


    /**
     *
     */
    function __construct() {
    }


    /**
     *
     * @route({"GET","/list"})
     *
     * 客户端上传信息
     *
     *
     * @throws({"BadRequest", "status", "400 Bad request"})
     *
     * @return({"body"})
     * {
            "data": {b64(array_string)
            },
        }
     */
    public function getList() {

        $arrData = Util::initialClass(false);

        $voiceModel = IoCload('models\\VoiceModel');
        $arrData = $voiceModel->getList();
        
        $arrData['data'] =  bd_B64_encode(json_encode($arrData['data']),0);

        return Util::returnValue($arrData,false,true);
    }

    /**
     * @desc 获取app白名单（语音输入单词上屏包名白名单）
     * @route({"GET", "/applist"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
     data: ['com.tencent.mobileqq','com.tencent.wechat')]
     }
     */
    public function getWhiteList() {
        //$result = array('data' => array());
        //输出格式初始化
        $result = Util::initialClass(false);

        $once_up_screen_voice = UnifyConf::getUnifyConf(GFunc::getCacheInstance(), 'once_up_screen_voice_conf_pre', GFunc::getCacheTime('hours'), '/res/json/input/r/online/once_up_screen_voice/', Util::getClientIP(), $_GET['from'], false);
        
        $result['version'] = intval(UnifyConf::getVersion());
        $result['ecode'] = UnifyConf::getCode();
        $result['emsg'] = UnifyConf::getCodeMsg();
        
        if(!empty($once_up_screen_voice)) {
            $tmp = array_shift($once_up_screen_voice);
            if(!empty($tmp)) {
                $data = explode(PHP_EOL, $tmp['package_name'] );
                if(!empty($data) && is_array($data)) {
                    //return array('data' => $data);
                    $result['data'] = $data;
                }

            }
        }

        return Util::returnValue($result,false,true);

    }

    /**
     * @desc 获取 语音 度秘提示
     * @route({"GET", "/tips"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
     data: []
     }
     */
    public function getTips()
    {
        //$result = array('data' => array());

        $model = new VoiceTipsModel();
        $result = $model->getTips();

        return $result;
    }
    
    /**
     * @desc 通知中心重构后获取 语音 度秘提示
     * @route({"GET", "/tips_noti"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
     data: []
     }
     */
    public function getTipsNoti()
    {
        //输出格式初始化
        $out = Util::initialClass(false);

        $model = IoCload('models\\VoiceTipsModel');
        $result = $model->getTips();
        
        $out['data'] = $result['tips'];
        $out['ecode'] = $model->getStatusCode();
        $out['emsg'] = $model->getErrorMsg();
        $out['version'] = intval($model->intMsgVer);

        return Util::returnValue($out,false,true);
    }


    /**
     * @desc 获取语音翻译配置累彪
     * @route({"GET", "/vdtlist"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
     data: ['com.tencent.mobileqq','com.tencent.wechat')]
     }
     */
    public function getVdtList() {
        $voiceModel = IoCload('models\\VoiceDistinguishTranslateModel');
        $rt = $voiceModel->getList();

        return $rt;

    }
    
    
    /**
     * @desc 通知中心重构后获取语音翻译配置
     * @route({"GET", "/vdtlist_noti"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
     data: ['com.tencent.mobileqq','com.tencent.wechat')]
     }
     */
    public function getVdtListNoti() {
        //输出格式初始化
        $out = Util::initialClass(false);
        
        $voiceModel = IoCload('models\\VoiceDistinguishTranslateModel');
        $out['data'] = $voiceModel->getList();
        $out['ecode'] = $voiceModel->getStatusCode();
        $out['emsg'] = $voiceModel->getErrorMsg();
        $out['version'] = intval($voiceModel->intMsgVer);

        return Util::returnValue($out,false,true);

    }
    
    
    /**
     *
     * @desc personal voice 开关
     *
     * @route({"GET", "/personal_voice_switch"})
     * @param({"message_version", "$._GET.message_version"}) $message_version message_version 客户端上传版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": array,
     *      "version": 1526352952
     * }
     */
    public function getPersonalVoiceSwitch($message_version = 0) {
        $model = IoCload(\models\PersonalVoiceSwitch::class);
        //输出格式初始化
        $rs = Util::initialClass();
        $rs['data'] = $model->getResource();
        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs);
    }
}
