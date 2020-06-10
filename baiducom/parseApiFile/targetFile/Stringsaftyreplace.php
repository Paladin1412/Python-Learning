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
use models\FilterModel;
use utils\Util;
use utils\GFunc;

/**
 * 语料和谐及数据源管理下发接口
 *
 * @author fanwenli
 * @path("/string_safty_replace/")
 */
class Stringsaftyreplace
{	
    /** @property 内部缓存key */
    private $list_data_cache_key;
    
    /** @property 内部缓存时长 */
    public $intCacheExpired;
    
    /** 获取数据后的版本号，服务器返回状态等 */
    private $arrResource;
    
    /**
     *
     * 和谐语料列表数据
     *
     * @route({"GET", "/list"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *          "ecode": 0,
     *          "emsg": success,
     *          "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn",
     *          "version": 123123123123
     *      ]
     * }
     */
     public function getList(){
        //$out = array('data' => array());
        $out = Util::initialClass(false);
        
        $cache_key = $this->list_data_cache_key;
        
        $out['data'] = $this->getListContent($cache_key);
        
        $out['ecode'] = isset($this->arrResource['ecode'])?$this->arrResource['ecode']:$out['ecode'];
        
        $out['emsg'] = isset($this->arrResource['emsg'])?$this->arrResource['emsg']:$out['emsg'];
        
        $out['version'] = intval($this->arrResource['version']);
        
        //return $out;
        return Util::returnValue($out,false,true);
    }
    
    /**
     * @desc 获取黑名单数据
     * @param $cache_key 缓存key
     * @return array
    */
    private function getListContent($cache_key){
        $out = array();
        
        //version cache
        $cacheKeyVersion = $cache_key . '_version';
        
        $version = GFunc::cacheGet($cacheKeyVersion);
        $list = GFunc::cacheGet($cache_key);
        if($list === false){
            $list = GFunc::getRalContent('string_safty_replace');
            
            //set status, msg & version
            $this->arrResource['ecode'] = GFunc::getStatusCode();
            $this->arrResource['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());
            
            //设置缓存
            GFunc::cacheSet($cache_key, $list, $this->intCacheExpired);
            //设置版本缓存
            GFunc::cacheSet($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $this->arrResource['version'] = $version;
        
        //过滤数据
        if(!empty($list)){
            $filterModel = new FilterModel();
            $list = $filterModel->getFilterByArray($list);
        }
        
        $list_arr = array();
        
        //整理数据
        if(!empty($list)){
            foreach($list as $val){
                $replace = array();
                
                //目标词和替换词
                if(isset($val['word_replace']) && !empty($val['word_replace'])){
                    $i = 0;
                    foreach($val['word_replace'] as $word_replace_key => $word_replace_val){
                        $tmp_word_find_arr = explode('|',$word_replace_val['find']);
                        $tmp_word_replace_arr = explode('|',$word_replace_val['replace']);
                        
                        //choose which arr is the main arr
                        if(count($tmp_word_find_arr) > $tmp_word_replace_arr) {
                            $main_arr = $tmp_word_find_arr;
                        } else {
                            $main_arr = $tmp_word_replace_arr;
                        }
                        
                        foreach($main_arr as $word_key => $word_val) {
                            $tmp_word_find = '';
                            if(isset($tmp_word_find_arr[$word_key])) {
                                $tmp_word_find = $tmp_word_find_arr[$word_key];
                            }
                            
                            $tmp_word_replace = '';
                            if(isset($tmp_word_replace_arr[$word_key])) {
                                $tmp_word_replace = $tmp_word_replace_arr[$word_key];
                            }
                            
                            $replace[$i][] = $tmp_word_find;//目标词
                            $replace[$i][] = $tmp_word_replace;//替换词
                            $i++;
                        }
                    }
                }
                
                $list_arr[] = array(
                    'id' => $val['id'],
                    'description' => $val['description'],
                    'replace' => $replace,
                );
            }
        }
        
        if(!empty($list_arr)){
            $out = bd_B64_encode(json_encode($list_arr),0);
        }
        
        return $out;
    }
}
