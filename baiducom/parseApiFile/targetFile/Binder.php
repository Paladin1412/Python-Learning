<?php
/***************************************************************************
 *
 * Copyright (c) 2018 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/
/**
 * $Id$
 * @author yangxugang01(yangxugang01@baidu.com)
 * @brief binder api
 */
require_once(__DIR__ . '/utils/CacheVersion.php');
require_once(__DIR__ . '/utils/ApcLock.php');
require_once(__DIR__ . '/utils/AdapterBinder.php');

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
use EntitySearch\EntityProcessor\DefaultEntityProcessor;
use utils\GFunc;
use utils\ErrorCode;
use utils\ErrorMsg;
use utils\AccessAuth;
use utils\CacheVersion;
use utils\ApcLock;
use utils\AdapterBinder;

/**
 *
 * @desc binder api
 * @path("/binder/")
 */
class Binder {
    /**
     * push 消息
     * 
     * @route({"POST", "/push"})
     * 
     * 数据必须json_encode为string 上传
     * json_decode后数据结构
     * {
	 *     "push_type": "all",//投送所有'all'或单个'one'
	 *     "class": "Activity",//类
	 *     "method": "updateCache",//接口可以为空字符串（一个类只有一个cache key version）
	 *     "param": ""//json_encode($arrParam)
	 *     "callback": "{"http_code":200,"body":{},"host":"host:port"}"//post 1.http return code；2.接口调用返回；3.本机host:port 
     * }
	 *  
     * @return array {"ecode":0,"emsg":"success","data":{"msg_id":"75d72277f3d9dba7a692dafb1001cc4f","msg_seq":"1524646061.62277100"}}
     * 
     */
    public function push() {
        //限制线上、线下交叉访问
        $bolAccess = AccessAuth::auth();
        Verify::isTrue($bolAccess, new BadRequest("forbid!"));
        
        $arrReturn = Util::initialClass();
        
        $arrResult = array();
        
        //curl -d 或 curl -v "@./data.txt" 
        $strData = file_get_contents ( 'php://input' );
        $arrData = json_decode($strData, true);
        
        //参数验证 push_type one all method 可以为空字符串''
        Verify::isTrue( isset($arrData['push_type']) && isset($arrData['class']) && is_string($arrData['class']) && '' !== $arrData['class'] && isset($arrData['method']), new BadRequest("binder param wrong!") );
        
        //每个消息请求都获取guid标识
        $strGuid = Util::create_guid();
        $arrData['msg_id'] = md5($strGuid);
        
        //发送消息方可以通过guid查询状态
        $arrResult['msg_id'] = $arrData['msg_id'];
        
        //异步执行
        $arrTaskData = array('v5', 'Phaster_Remote::task', array($arrData));
        $objTask = new PhasterTask($arrTaskData);
        
        $arrReturn['data'] = $arrResult;
        
        return $arrReturn;
    }
    
    /**
     * adapt 消息
     *
     * @route({"POST", "/adapt"})
     *
     * 数据必须json_encode为string 上传
     * json_decode后数据结构
     * {
     *     "type": "db",//db schema
     *     "info": "{"db":"web","table":"activity","action":"online"}"//不同type info包含信息不同
     * }
     *
     * @return array
     *
     */
    public function adapt() {
        //限制线上、线下交叉访问
        $bolAccess = AccessAuth::auth();
        Verify::isTrue($bolAccess, new BadRequest("forbid!"));
        
        //curl -d 或 curl -v "@./data.txt"
        $strData = file_get_contents ( 'php://input' );
        $arrData = json_decode($strData, true);
        
        //参数验证 push_type one all method 可以为空字符串''
        Verify::isTrue( isset($arrData['type']) && isset($arrData['info']), new BadRequest("param wrong!") );
        
        $arrReturn = Util::initialClass();
        
        $arrList = AdapterBinder::get($arrData['type'], $arrData['info']);
        
        foreach ($arrList as $arrPushData) {
            //每个消息请求都获取guid标识
            $strGuid = Util::create_guid();
            $arrPushData['msg_id'] = md5($strGuid);
            
            if (isset($arrData['username'])) {
                $arrPushData['username'] = $arrData['username'];
            }
            
            if (isset($arrData['param'])) {
                $arrPushData['param'] = $arrData['param'];
            }
            
            //异步执行
            $arrTaskData = array('v5', 'Phaster_Remote::task', array($arrPushData));
            $objTask = new PhasterTask($arrTaskData);
        }
        
        return $arrReturn;
    }
    
    /**
     * 请求本地运行
     *
     * @route({"POST", "/native"})
     *
     * @return array
     */
    public function native() {
        Verify::isTrue( AccessAuth::isInnerClinet(), new BadRequest('forbid') );
        
        $arrReturn = Util::initialClass();
        
        //curl -d 或 curl -v "@./data.txt"
        $strData = file_get_contents ( 'php://input' );
        
        $arrNativeData = array();
        $arrNativeData['class'] = 'Binder';
        $arrNativeData['method'] = 'nativeSync';
        $arrNativeData['param'] = $strData;
        
        //异步执行
        $arrTaskData = array('v5', 'Phaster_Native::task', array($arrNativeData));
        $objTask = new PhasterTask($arrTaskData);
        
        return $arrReturn;
    }
    
    /**
     * 本地运行
     *
     * @return boolean
     */
    public function nativeSync($strData) {
        $bolResult = true;
        
        if(is_array($strData)) {
            $arrData = $strData;
        } else {
            $arrData = json_decode($strData, true);
        }
        
        $strCurCacheVersion = CacheVersion::getWithCacheVersionKey($arrData['class']);
        if ($arrData['msg_seq'] <= $strCurCacheVersion) {
            return $bolResult;
        }
        
        //lock
        $strLockKey = $arrData['class'] . $arrData['method'];
        $objApcLock = new ApcLock($strLockKey, self::APC_LOCK_TTL);
        $bolIsLock = $objApcLock->lock();
        
        //定义方法名则调用，不判断类或方法是否存在，没有让报错；local、all 都必须调用
        if ('' !== $arrData['method']) {            
            $objClass = IoCload($arrData['class']);
            //将消息的所有信息作为参数传给调用函数
            $bolResult = $objClass->$arrData['method']($arrData);
        }
        
        //update cache key version
        if (true === $bolResult && isset($arrData['cache_version_key'])) {
            CacheVersion::set($arrData['cache_version_key'], $arrData['msg_seq']);
        }
        
        //unlock
        $objApcLock->unlock();
        
        return $bolResult;
    }
    
    /**
     * 获取消息id为guid的状态
     * 
     * @route({"GET", "/status"})
     * 
     * @param({"strGuid", "$._GET.guid"}) string guid
     * 
     * @return string
     */
    public function status($strGuid) {
        $arrReturn = Util::initialClass();
        
        $objRedis = IoCload('utils\\KsarchRedisV2');
        $strStatusKey = self::BINDER_REMOTE_STATUS_PRE . $strGuid;
        
        $bolSucceeded = false;
        $strStatus = $objRedis->getNoCacheVersion($strStatusKey, $bolSucceeded);
        
        $arrReturn['data'] = json_decode($strStatus, true);
        
        return $arrReturn;
    }
    
    /**
     * 获取cache key version
     *
     * @route({"GET", "/version"})
     *
     * @param({"strClass", "$._GET.class"}) api class
     *
     * @return string
     */
    public function version($strClass) {
        $arrReturn = Util::initialClass();
    
        $bolSucceeded = false;
        $strCacheVersion = CacheVersion::getWithCacheVersionKey($strClass);
    
        $arrReturn['data'] = $strCacheVersion;
    
        return $arrReturn;
    }
    
     /**
     * 通知结果缓存key
     */
    const BINDER_REMOTE_STATUS_PRE = 'binder_rsp';
    
    /**
     * apc lock expire second
     */
    const APC_LOCK_TTL = 60;
}