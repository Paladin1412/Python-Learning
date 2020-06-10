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
use utils\CustLog;

require_once __DIR__.'/utils/CurlRequest.php';

/**
 * 场景化数据接口
 *
 * @author fanwenli
 * @path("/scene/")
 */
class Scene
{	
    /** @property 内部缓存 voice key */
    private $voice_cache_key;
    
    /** @property 内部缓存map & search key */
    private $map_search_voice_cache_key;
    
    /** @property 内部缓存map & search mapping key */
    private $map_search_voice_mapping_cache_key;
    
    /** @property 内部缓存address book key */
    private $address_book_voice_cache_key;
    
    /** @property 内部缓存 ios voice key */
    private $ios_voice_cache_key;
    
    /** @property 内部缓存时长 */
    public $intCacheExpired;
    
    /** @property 是否是线上环境 */
    private $online;
      
    /**
	 *
	 * 场景化语音条
	 *
	 * @route({"GET", "/voice"})
	 * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getVoice($notiVersion = 0){
		//$out = array('data' => array());
		//输出格式初始化
        $out = Util::initialClass(false);
		
		$cacheKey = $this->voice_cache_key;
		
		$cacheKeyVersion = $cacheKey . '_version';
		
		$version = GFunc::cacheGet($cacheKeyVersion);
		$voice = GFunc::cacheGet($cacheKey);
        if($voice === false){
        	$voice = GFunc::getRalContent('scene_voice_cand');
        	
        	//set status, msg & version
        	$out['ecode'] = GFunc::getStatusCode();
        	$out['emsg'] = GFunc::getErrorMsg();
        	$version = intval(GFunc::getResVersion());
        	
        	//设置缓存
        	GFunc::cacheSet($cacheKey, $voice, $this->intCacheExpired);
        	
        	//设置版本缓存
        	GFunc::cacheSet($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);
        
        //过滤数据
        if(!empty($voice)){
        	$filterModel = new FilterModel();
			$voice = $filterModel->getFilterByArray($voice);
        }
        
        $arr = array();
        //整理数据
        if(!empty($voice)){
        	foreach($voice as $val){
        		//设置输出内容
        		$tmp_arr = array(
        			'ctrid' => $val['ctrid'],
        			'r_text' => $val['r_text'],
        			'style' => $val['style'],
        			's_text' => $val['s_text'],
        		);
        		
        		//判读有无设置包名
        		if(isset($val['package_name']) && !empty($val['package_name'])){
        			foreach($val['package_name'] as $p_name){
        				//已设置过相应包名对应框属性
        				if(isset($arr[$p_name])){
        					array_push($arr[$p_name] , $tmp_arr);
        				} else {
        					$arr[$p_name] = array($tmp_arr);
        				}
        			}
        		}
        	}
        }
        
        if(empty($arr)) {
            $arr = new \stdClass();
        }
        
        $out['data'] = bd_B64_encode(json_encode($arr),0);
        
        return Util::returnValue($out,false,true);
	}
	
	/**
	 *
	 * 场景化地图和搜索语音条
	 *
	 * @route({"GET", "/map_search_voice"})
	 * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
	 * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform当前平台号,不需要客户端传
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getMapSearchVoice($strVersion,$strPlatform){
		//$out = array('data' => array());
		//输出格式初始化
        $out = Util::initialClass(false);
		
		//Edit by fanwenli on 2016-12-01, get system's version
		$version = Util::getVersionIntValue($strVersion);
		
		$cacheKey = $this->map_search_voice_cache_key;
		
		$cacheKeyVersion = $cacheKey . '_version';
		
		$data_version = GFunc::cacheGet($cacheKeyVersion);
		$voice = GFunc::cacheGet($cacheKey);
        if($voice === false){
        	$voice = GFunc::getRalContent('scene_map_search_voice_cand');
        	
        	//set status, msg & version
        	$out['ecode'] = GFunc::getStatusCode();
        	$out['emsg'] = GFunc::getErrorMsg();
        	$data_version = intval(GFunc::getResVersion());
        	
        	//设置缓存
        	GFunc::cacheSet($cacheKey, $voice, $this->intCacheExpired);
        	
        	//设置版本缓存
        	GFunc::cacheSet($cacheKeyVersion, $data_version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($data_version);
        
        //过滤数据
        if(!empty($voice)){
        	$filterModel = new FilterModel();
			$voice = $filterModel->getFilterByArray($voice);
        }
        
        $arr = array();
        
        //整理数据
        if(!empty($voice)){
        	//======Edit by fanwenli on 2017-04-27, add machine do not use mapping with version=======//
        	$mapping_cache_key = $this->map_search_voice_mapping_cache_key;
        	
        	$map_search_machine_version = GFunc::cacheGet($mapping_cache_key);
        	if($map_search_machine_version === false){
        	    $map_search_machine_version = GFunc::getRalContent('map_search_machine_version');
        	    
        	    //设置缓存
        	    GFunc::cacheSet($mapping_cache_key, $map_search_machine_version, $this->intCacheExpired);
        	}
        	
        	$judge_arr = array();
        	$special_machine_judge_arr = array();
        	if(!empty($map_search_machine_version)) {
        	    foreach($map_search_machine_version as $val) {
        	        $judge_arr[] = ($version < Util::getVersionIntValue($val['version']) && $strPlatform == $val['ua']) ? 1 : 0;
        	        
        	        if($val['ua'] != '') {
        	            $special_machine_judge_arr[] = ($strPlatform != $val['ua'])?1:0;
        	        }
        	    }
        	}
        	
        	//normal machine judge, if there does not set machine use 7.3, or use machine with 7.3
        	if(!empty($special_machine_judge_arr)){
        	    if(in_array(0,$special_machine_judge_arr)){
        	        $special_machine_judge = false;
        	    } else {
        	        $special_machine_judge = true;
        	    }
        	    $judge_arr[] = ($version < 7030000 && $special_machine_judge)?1:0;
        	} else {
        	    //Edit by fanwenli on 2019-01-15, add ai judgement
        	    $judge_arr[] = ($version < 7030000 && $strPlatform != 'a11')?1:0;
        	}
        	
        	$mapping_judge = false;
        	if(!empty($judge_arr)) {
        	    if(in_array(1,$judge_arr)){
        	        $mapping_judge = true;
        	        
        	        //Edit by fanwenli on 2019-01-11, add log when system will return result by mapping
        	        $arrLogData = array(
        	            'url' => $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
        	            'version' => $strVersion,
        	            'platform' => $strPlatform,
        	            'cuid' => $_GET['cuid'],
        	        );
        	        
//        	        CustLog::write('scene_map_search_voice', $arrLogData);
        	    }
        	}
        	
        	//======Edit by fanwenli on 2017-04-27, add machine do not use mapping with version=======//
        	foreach($voice as $val){
        		//判读有无设置包名
        		if(isset($val['package_name']) && !empty($val['package_name'])){
        			foreach($val['package_name'] as $p_name){
        				//Edit by fanwenli on 2016-12-01, api use old value when system's version less than 7.3.0.0
        				//Edit by fanwenli on 2017-04-26, add machine do not use mapping with version
        				if($mapping_judge){
        					//online or not
        					if($this->online == 1){
        						switch($val['api']){
        							case 597:
        								$val['api'] = 1;
        								break;
        							case 599:
        								$val['api'] = 2;
        								break;
        							case 598:
        								$val['api'] = 3;
        								break;
        						}
        					} else {
        						switch($val['api']){
        							case 595:
        								$val['api'] = 1;
        								break;
        							case -595:
        								$val['api'] = 2;
        								break;
        							case -596:
        								$val['api'] = 3;
        								break;
        						}
        					}
        				}
        				
        				//框属性以及api赋值
        				$tmp_arr = array('ctrid' => $val['ctrid'], 'api' => $val['api']);
        				
        				//预设文字有则赋值无则为空
        				$tmp_arr['r_text'] = isset($val['r_text']) ? $val['r_text'] : '';
        				
        				//Edit by fanwenli on 2017-01-01, add nluKey
        				$tmp_arr['nluKey'] = $val['nluKey'];
        				
        				//已设置过相应包名对应框属性
        				if(isset($arr[$p_name])){
        					array_push($arr[$p_name] , $tmp_arr);
        				} else {
        					$arr[$p_name] = array($tmp_arr);
        				}
        			}
        		}
        	}
        }
        
        if(empty($arr)) {
            $arr = new \stdClass();
        }
        
        $out['data'] = bd_B64_encode(json_encode($arr),0);
        
        return Util::returnValue($out,false,true);
	}
	
	/**
	 *
	 * 场景化语音通讯录
	 *
	 * @route({"GET", "/address_book_voice"})
	 * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getAddressBookVoice($intMsgVersion = 0){
		//$out = array('data' => '');
		
		//输出格式初始化
		$out = Util::initialClass(false);
		
		$cacheKey = $this->address_book_voice_cache_key;
		$cacheKeyVersion = $cacheKey . '_version';
		
		$version = GFunc::cacheGet($cacheKeyVersion);
		$voice = GFunc::cacheGet($cacheKey);
        if($voice === false){
        	$voice = GFunc::getRalContent('scene_address_book_voice');
        	
        	//set status, msg & version
        	$out['ecode'] = GFunc::getStatusCode();
        	$out['emsg'] = GFunc::getErrorMsg();
        	$version = intval(GFunc::getResVersion());
        	
        	//设置缓存
        	GFunc::cacheSet($cacheKey, $voice, $this->intCacheExpired);
        	
        	//设置版本缓存
        	GFunc::cacheSet($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);
        
        //过滤数据
        if(!empty($voice)){
        	$filterModel = new FilterModel();
			$voice = $filterModel->getFilterByArray($voice);
        }
        
        $arr = array();
        //整理数据
        if(!empty($voice)){
        	foreach($voice as $val){
        		//判读有无设置包名
        		if(isset($val['package_name']) && !empty($val['package_name'])){
        			foreach($val['package_name'] as $p_name){
        				//框属性
        				$tmp_arr = array('ctrid' => $val['ctrid']);
        				
        				//预设文字有则赋值无则为空
        				$tmp_arr['r_text'] = isset($val['r_text']) ? $val['r_text'] : '';
        				
        				//已设置过相应包名对应框属性
        				if(isset($arr[$p_name])){
        					array_push($arr[$p_name] , $tmp_arr);
        				} else {
        					$arr[$p_name] = array($tmp_arr);
        				}
        			}
        		}
        	}
        }
        
        if(empty($arr)) {
            $arr = new \stdClass();
        }
        
        $out['data'] = bd_B64_encode(json_encode($arr),0);
        
        return Util::returnValue($out,false,true);
	}
	
	/**
	 *
	 * 场景化语音条
	 *
	 * @route({"GET", "/ios_voice"})
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getIosVoice(){
		//$out = array('data' => '');
		//输出格式初始化
        $out = Util::initialClass(false);
		
		$key = 'ios_scene_voice_cand';
		
		$cacheKey = $this->ios_voice_cache_key;
		
		$cacheKeyVersion = $cacheKey . '_version';
		
		$version = GFunc::cacheGet($cacheKeyVersion);
		$voice = GFunc::cacheGet($cacheKey);
		if($voice === false){
        	$voice = GFunc::getRalContent($key);
        	
        	//set status, msg & version
        	$out['ecode'] = GFunc::getStatusCode();
        	$out['emsg'] = GFunc::getErrorMsg();
        	$version = intval(GFunc::getResVersion());
        	
        	//设置缓存
        	GFunc::cacheSet($cacheKey, $voice, $this->intCacheExpired);
        	//设置版本缓存
        	GFunc::cacheSet($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);
        
        //过滤数据
        if(!empty($voice)){
        	$filterModel = new FilterModel();
			$voice = $filterModel->getFilterByArray($voice);
        }
        
        $arr = array();
        //整理数据
        if(!empty($voice)){
        	foreach($voice as $val){
        		//设置输出内容
        		$tmp_arr = array(
        			'keyboard' => $val['keyboard'],
        			'return' => $val['return'],
        			'api' => $val['api'],
        			//Edit by fanwenli on 2017-01-03, add nluKey
        			'nluKey' => $val['nluKey'],
        		);

        		//判读有无设置包名
        		if(isset($val['package_name']) && !empty($val['package_name'])){
        			foreach($val['package_name'] as $p_name){
        				//已设置过相应包名对应框属性
        				if(isset($arr[$p_name])){
        					array_push($arr[$p_name],$tmp_arr);
        				} else {
        					$arr[$p_name] = array($tmp_arr);
        				}
        			}
        		}
        	}
        }
        
        if(empty($arr)) {
            $arr = new \stdClass();
        }
        
        $out['data'] = bd_B64_encode(json_encode($arr),0);
        
        return Util::returnValue($out,false,true);
	}
}
