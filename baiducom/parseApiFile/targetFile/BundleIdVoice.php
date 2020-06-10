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
 * 分BundleID支持语音Option&Mode下发接口
 *
 * @author fanwenli
 * @path("/bundleid_voice/")
 */
class BundleidVoice
{	
    /** @property 内部缓存key */
    private $bundleid_voice_list_data_cache_key;
    
    /** @property 内部缓存时长 */
    public $intCacheExpired;
    
    /** @property 资源路径 */
    public $strResourceInnerUrl;
    
    /**
	 *
	 * 列表数据
	 *
	 * @route({"GET", "/list"})
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getList(){
		//$out = array('data' => array());
		$cacheKey = $this->bundleid_voice_list_data_cache_key;
		
		$out = $this->getListContent($cacheKey);
        
        return $out;
	}
	
	/**
     * @desc 获取黑名单数据
     * @param $cacheKey 缓存key
     * @return array
    */
	private function getListContent($cacheKey){
	    $out = Util::initialClass(false);
	    
	    $cacheKeyVersion = $cacheKey . '_version';
	    
	    $version = GFunc::cacheZget($cacheKeyVersion);
	    $list = GFunc::cacheZget($cacheKey);
        if($list === false){
        	$list = GFunc::getRalContent('bundle_id_voice');
        	
        	//set status, msg & version
        	$out['ecode'] = GFunc::getStatusCode();
        	$out['emsg'] = GFunc::getErrorMsg();
        	$version = intval(GFunc::getResVersion());
        	
        	//设置缓存
        	GFunc::cacheZset($cacheKey, $list, $this->intCacheExpired);
        	
        	//设置版本缓存
        	GFunc::cacheZset($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);
        
        //过滤数据
        if(!empty($list)){
        	$filterModel = new FilterModel();
			$list = $filterModel->getFilterByArray($list);
        }
        
        $list_arr = array();
        //整理数据
        if(!empty($list)){
        	foreach($list as $val){
        		$list_arr[] = array(
        		    'id' => $val['id'],
        		    'description' => $val['description'],
        		    'app_ids' => Util::packageToArray($val['package_name']),
        		    'option' => $val['option'],
        		    'mode' => $val['mode'],
        		);
        	}
        }
        
        $out['data'] = bd_B64_encode(json_encode($list_arr),0);
        
        return Util::returnValue($out,false);
	}
}
