<?php
/***************************************************************************
 *
* Copyright (c) 2018 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
use utils\ErrorMsg;
use utils\ErrorCode;


/**
 *
 * 华为简版插件下发
 *
 * @author lipengcheng02
 * @path("/hw_light_plugin/")
 */
class HwLightPlugin
{

    /** 输出数组格式 */
    private $out = array();

    /***
     * 构造函数
     * @return void
     */
    public function  __construct() {
        //输出格式初始化
        //$this->out = Util::initialClass();
        $this->out = array();
    }
    
     /**
     * @route({"GET","/info"})
     * 华为简版插件下发
     * @return
     */
    public function getLightPluginInfo () {
        $info = array();
        $info['downinfo']['id'] = 'com.baidu.input.plugin.kit.offlinevoicerec';
        $info['downinfo']['name'] = '离线语言';
        $info['downinfo']['subclasses'] = array();

        $subclass = array();
        $subclass['reason'] = '优化离线库大小及识别率';
        $subclass['version'] = '1';
        $subclass['size'] = '14837996';
        $subclass['md5'] = '27a54a86f258b812c8686a916595c576';
        $subclass['mdate'] = '1540265986';
        $subclass['download'] = 'http://imeres.baidu.com/imeres/ime-res/android_apk/2018-10-22/com.baidu.input.plugin.kit.offlinevoicerec';
        $subclass['min'] = '6.0';
        $subclass['max'] = '';
        $subclass['support'] = '1';
        $info['downinfo']['subclasses'][] = $subclass;

        $this->out = $info;

        //code
        //$this->out['ecode'] = 0;
        //msg
        //$this->out['emsg'] = ErrorMsg::getMsg($this->out['ecode']);

        return $this->out;
    }
}
    