<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use utils\KsarchRedis;
use models\FilterModel;
use utils\Util;
use utils\GFunc;

/**
 * 黑名单下发通用接口
 *
 * @author fanwenli
 * @path("/blacklist/")
 */
class Blacklist
{	
    /** @property 黑名单内部缓存key */
    private $blacklist_data_cache_key;
    
    /** @property 标点内部缓存key */
    private $punctuation_data_cache_key;
    
    /** @property ios语音助手黑名单内部缓存key */
    private $ios_voice_data_cache_key;
    
    /** @property 内部缓存时长 */
    public $intCacheExpired;
    
    /**
     *
     * 智能强引导黑名单
     *
     * @route({"GET", "/int_guidance"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
     *      ]
     * }
     */
    public function getIntGuidanceContent($intMsgVersion = 0){
        $cacheKey = $this->blacklist_data_cache_key;
        $strProtoName = 'blacklist';
        $limit = 0;
        //智能强引导黑名单类型为1
        $strSearch = sprintf('{"content.blacklist_type":%s}',1);
        //get data from function
        $out = GFunc::getNotiReturn($strProtoName, $cacheKey, $this->intCacheExpired, $strSearch);
        
        $blacklist_arr = array();
        //整理数据
        if(!empty($out['data'])){
            foreach($out['data'] as $val){
                $blacklist_arr[] = array(
                    'id' => $val['blacklist_id'],
                    'description' => $val['description'],
                    'app_ids' => Util::packageToArray($val['package_name']),
                );
            }
        }
        
        if(!empty($blacklist_arr)){
            $out['data'] = bd_B64_encode(json_encode($blacklist_arr),0);
        }
        
        return Util::returnValue($out,false,true);
    }
    
    /**
    *
    * 语音标点黑名单
    *
    * @route({"GET", "/punctuation"})
    * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
    * @throws({"BadRequest", "status", "400 Bad request"})
    * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"}){
    *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
    *      ]
    * }
    */
    public function getPunctuationContent($intMsgVersion = 0){
        //$out = array('data' => array());
        
        //输出格式初始化
        $out = Util::initialClass(false);
        
        $cacheKey = $this->punctuation_data_cache_key;
        
        $cacheKeyVersion = $cacheKey . '_version';
        
        $version = GFunc::cacheZget($cacheKeyVersion);
        $punctuation = GFunc::cacheZget($cacheKey);
        if($punctuation === false){
            $punctuation = GFunc::getRalContent('punctuation_blacklist');
            
            //set status, msg & version
            $out['ecode'] = GFunc::getStatusCode();
            $out['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());
            
            //设置缓存
            GFunc::cacheZset($cacheKey, $punctuation, $this->intCacheExpired);
            
            //设置版本缓存
            GFunc::cacheZset($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);

        //过滤数据
        if(!empty($punctuation)){
            $filterModel = new FilterModel();
            $punctuation = $filterModel->getFilterByArray($punctuation);
        }
        
        $blacklist_arr = array();
        //整理数据
        if(!empty($punctuation)){
            foreach($punctuation as $val){
                if(isset($val['package_name']) && !empty($val['package_name'])){
                    foreach($val['package_name'] as $p_name){
                        //已设置过相应包名
                        if(!in_array($p_name,$blacklist_arr)){
                            array_push($blacklist_arr, $p_name);
                        }
                    }
                }
            }
        }
        
        $out['data'] = bd_B64_encode(json_encode($blacklist_arr),0);
        
        return Util::returnValue($out,false,true);
    }
    
    
    /**
    *
    * IOS语音助手黑名单
    *
    * @route({"GET", "/ios_voice"})
    * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
    * @throws({"BadRequest", "status", "400 Bad request"})
    * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"}){
    *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
    *      ]
    * }
    */
    public function getIosVoiceContent($intMsgVersion = 0){
        //输出格式初始化
        $out = Util::initialClass(false);
        
        $cacheKey = $this->ios_voice_data_cache_key;
        
        $cacheKeyVersion = $cacheKey . '_version';
        
        $version = GFunc::cacheZget($cacheKeyVersion);
        $content = GFunc::cacheZget($cacheKey);
        if($content === false){
            $content = GFunc::getRalContent('ios_voice_blacklist');
            
            //set status, msg & version
            $out['ecode'] = GFunc::getStatusCode();
            $out['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());
            
            //设置缓存
            GFunc::cacheZset($cacheKey, $content, $this->intCacheExpired);
            
            //设置版本缓存
        	GFunc::cacheZset($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);

        //过滤数据
        if(!empty($content)){
            $filterModel = new FilterModel();
            $content = $filterModel->getFilterByArray($content);
        }
        
        $arrBlacklist = array();
        //整理数据
        if(!empty($content)){
            foreach($content as $val){
                if(isset($val['package_name']) && trim($val['package_name']) != ''){
                    //临时数组
                    $arrTmp = array(
                        'package_name' => $val['package_name'],
                        'keyboard' => array(),
                        'return' => array(),
                    );
                    
                    //键盘类型
                    if(isset($val['keyboard']) && !empty($val['keyboard'])) {
                        foreach($val['keyboard'] as $strKeyboard){
                            //键盘类型设置过
                            if(isset($strKeyboard['keyboard'])) {
                                $arrTmp['keyboard'][] = $strKeyboard['keyboard'];
                            }
                        }
                    }
                    
                    //回车键类型
                    if(isset($val['return']) && !empty($val['return'])) {
                        foreach($val['return'] as $strReturn){
                            //回车键类型设置过
                            if(isset($strReturn['return'])) {
                                $arrTmp['return'][] = $strReturn['return'];
                            }
                        }
                    }
                    
                    //数组写入黑名单列表
                    array_push($arrBlacklist, $arrTmp);
                }
            }
        }
        
        if(!empty($arrBlacklist)){
            $out['data'] = $arrBlacklist;
        }
        
        return Util::returnValue($out,false);
    }
}
