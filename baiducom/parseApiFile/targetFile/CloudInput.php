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


require_once __DIR__.'/utils/CurlRequest.php';
ClassLoader::addInclude(__DIR__.'/utils');



/**
 * 
 * 云输入相关接口
 * 
 * 
 * @author yangxugang
 * @path("/cloudinput/") 
 */
class CloudInput
{
  
    /** v5 res 地址 **/
	public $strResDomain;
	
	/** @property */
	public $strCloudResRoot;
	
	/** @property */
	public $strWhiteLibRoot;
	
	/** @property 资源服务根路径*/
	public $strResRoot;
	
	/** @property 资源服务更新状态*/
	public $strResUpdateCachePre;
	
	/** 缓存超时时间 **/
	public $intCacheExpired;
	
	/**
	 * 查询一页的大小
	 *
	 */
	const PAGE_SIZE = 100;
	
	/**
	 * 最大请求次数
	 * 
	 *
	 */
	const MAX_REQ_COUNT = 10000;
	
	/**
	 *
	 * 新格式词库打包工具
	 * @var string
	 */
	const CLOUD_WORD_SERVICE_ID = 110001;

	/** @property 内部缓存实例(apc内存缓存) */
    private $apc_cache;
    
	/**
	 * 
	 */
	function __construct() {
	    $this->intCacheExpired = GFunc::getCacheTime('30mins');
	    $this->strResDomain = Util::randStr(GFunc::getGlobalConf('domain_res'));
	}
	
	
	/**
	 * 获取http请求结果
	 *
	 * @param $strUrl 请求url
	 * @param $strMethod http method
	 * @param $arrPostField http post field
	 * @param $intTimeout 超时 单位秒
	 * @param $arrHeader header
	 *
	 * @return array
	 */
	public function getHttpRequestResponse($strUrl, $strMethod, $arrPostField = null, $intTimeout = 1, $arrHeader = null){
		$arrParams = array (
			'url' => $strUrl,
			'method' => $strMethod,
			'post_fields' => $arrPostField,
			'timeout' => $intTimeout,
		);
	
		if( (null !== $arrHeader) && (0 !== count($arrHeader)) ){
			$arrParams['header'] = $arrHeader;
		}
	
		$objCurlRequest = new CurlRequest ();
		$objCurlRequest->init( $arrParams );
		$arrResult = $objCurlRequest->exec();
		curl_close ($objCurlRequest->ch);
	
		return $arrResult;
	}
	
	/**
	 *
	 * 递归创建目录
	 * 
	 * @param $strDir 路径
	 * @return bool
	 */
	function mkDirs($strDir){
		return is_dir($strDir) || ($this->mkDirs(dirname($strDir)) && mkdir($strDir,0777));
	}
	
	/**
	 * 打包词库
	 *
	 * @param $intVersionBegin 开始版本
	 * @param $intVersionEnd 最新版本
	 *
	 * @return bool
	 */
	public function packageWord($intVersionBegin, $intVersionEnd){
		$bolRet = true;
		$strAppPath = APP_PATH . "/v5/";
		$strPkgTool = $strAppPath . "build_cell_tool";
		$strPkgDict = $strAppPath . "hz_build_cell.bin";
		
		$intPos = strpos(APP_PATH, '/app');
		$strWordsFileDir = substr(APP_PATH, 0, $intPos) . "/data/app/v5/cloudinput/";
		
		//修改打包工具可执行
		$strCmd = 'chmod +x ' . $strPkgTool;
		exec($strCmd, $arrOutput, $intRet);
		
		//创建零时目录
		$this->mkDirs($strWordsFileDir);
		
		//获取资源信息
		$intPos = 0;
		$strWords = '';
		$strSearch = urlencode('{"owner":1, "cand.pos_1.type":{"$ne":11}}');
		for($intPos = 1; $intPos < self::MAX_REQ_COUNT; $intPos++){
			$intSkip = ($intPos - 1)*self::PAGE_SIZE;
			$arrHeader = array(
				'pathinfo' => $this->strCloudResRoot,
				'querystring'=> 'search=' . $strSearch . '&onlycontent=1&skip=' . $intSkip . '&limit=' . self::PAGE_SIZE,
			);
	
			$strResult = ral("resJsonService", "get", null, rand(), $arrHeader);
			if(false === $strResult){
				Logger::warning('cloud input check white update request res get words failed');
				return false;
			}
			$arrCloudRes = json_decode($strResult, true);
			if(empty($arrCloudRes)){
				break;
			}
			foreach ($arrCloudRes as $key => $value){
				$arrKeys = $value['keys'];
		
				foreach ($arrKeys as $strOneWord){
				    //支持v5后台textarea控件
				    $aryStr =  explode(PHP_EOL, $strOneWord);
				    foreach($aryStr as $kStr) {
				        if(!empty($kStr)) {

				            //20200513 add by zhoubin05 for @毛竺东 这个接口只有云输入服务端会调用 20200512 接口日访问量5713
                            //原逻辑：云输入通过这个接口来得知是否有新词库需要下发客户端,如果有，本接口输出一个版本号给云输入，同时将版本号对应的词库打包上传到bos
                            //云输入接收到版本号下发客户端，客户端发现比本地新时从bos下载对应词库
                            //改动点： 下发词库的内容中，pm不希望有查过3个字（含）以上的词出现在打包的词库中
                            //如果经过过滤后没有需要打包的词，则返回「status=0」词库没有更新状态

                            $arrSpw = explode('(',$kStr);
                            if(mb_strlen($arrSpw[0]) > 0 && mb_strlen($arrSpw[0]) <= 2) {
                                $strWords = $strWords . $kStr . "\n";
                            }


				        }
				        
				    }
				}
			}
		}

		if(empty($strWords)) {
		    return null;
        }
		
		//打包
		$strTempUtf8AddFilePath = $strWordsFileDir . $intVersionEnd . '.utf8';
		file_put_contents($strTempUtf8AddFilePath , $strWords);
		$strTempUnicodeAddFilePath = $strWordsFileDir . $intVersionEnd . '.unicode';
		
		$strTempUtf8DelFilePath = $strWordsFileDir . $intVersionEnd . '.del.utf8';
		file_put_contents($strTempUtf8DelFilePath, '');
		$strTempUnicodeDelFilePath = $strWordsFileDir . $intVersionEnd . '.del.unicode';
		
		$strWordsPkgPath = $strWordsFileDir . $intVersionEnd . '.pkg';
		
		//utf-8 to unicode
		$strCmd = 'iconv -f UTF-8 -t UTF-16 ' . $strTempUtf8AddFilePath . ' -o ' . $strTempUnicodeAddFilePath;
		exec($strCmd, $arrOutput, $intRet);
		
		$strCmd = 'iconv -f UTF-8 -t UTF-16 ' . $strTempUtf8DelFilePath . ' -o ' . $strTempUnicodeDelFilePath;
		exec($strCmd, $arrOutput, $intRet);
		
		$strCategory = 0;
		$strTitle = 'cloutinput';
		$strAuthor = 'baidu';
		$strKeywords = 'cloutinput';
		$strContent = 'cloutinput';
		
		$strCmd = $strPkgTool . ' ' . $strPkgDict . ' ' . $strTempUnicodeAddFilePath . ' ' . $strTempUnicodeDelFilePath . ' ' . $strWordsPkgPath . ' 1953524066 2004051043 ' . self::CLOUD_WORD_SERVICE_ID . ' 0 ' . 0 . ' ' . $intVersionEnd . ' ' . $strCategory . ' 0 0 0 ' . $intVersionEnd . ' 0 0 "' . $strTitle . '" "' . $strAuthor . '" "' . $strKeywords . '" "' . $strContent . '"';
		exec($strCmd, $arrOutput, $intRet);
		
		if($intRet != 0 && $intRet != 139 && $intRet != 11){
			Logger::warning('cloud input check white update package word failed exec cmd :[' . $strCmd . '] ret:[' . $intRet . ']');
			$bolRet = false;
		}else{
			//上传bcs
			$arrHeader = array(
				'pathinfo' => $this->strWhiteLibRoot . '/' . $intVersionEnd,
			);
			
			$handle = fopen($strWordsPkgPath, "rb");
			$strPkgContent = fread($handle, filesize ($strWordsPkgPath));
			fclose($handle);
			
			if(false === $strPkgContent || 'null' === $strPkgContent){
			    Logger::warning('cloud input check white update upload package word failed file_get_contents false');
			    $bolRet = false;
			}else{	
    			$strResult = ral("resJsonService", "post", $strPkgContent, rand(), $arrHeader);
    			if( false === $strResult || 200 !== intval(ral_get_protocol_code()) ){
    				Logger::warning('cloud input check white update upload package word failed');
    				$bolRet = false;
    			}
			}
		}
		
		//如果打包失败则不删除保留打包文件便于分析问题
		if($bolRet){
		    Logger::warning('cloud input check white update package word failed exec cmd :[' . $strCmd . '] ret:[' . $intRet . ']');
    		unlink($strTempUtf8AddFilePath);
    		unlink($strTempUtf8DelFilePath);
    		unlink($strTempUnicodeAddFilePath);
    		unlink($strTempUnicodeDelFilePath);
    		unlink($strWordsPkgPath);
		}
		
		return $bolRet;
	}
	
	/**
	 *
	 * @route({"GET","/check"})
	 * 检查云输入资源是否更新并打包，提供给内核云输入服务端定时调用
	 *
	 * @param({"strWhiteVersion","$._GET.version"})
	 * 白名单版本 资源最后修改时间
	 *
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 *
	 * @return({"body"})
	 *	{
	 *		"version":1234, 最新版本如果无更新则为请求版本
	 *		"status":0, -1  获取是否有更新失败  -2 打包失败 0 无更新  1有更新且打包成功   
	 *		"error_info":"" 错误信息
	 *	}
	 */
	public function checkUpdate($strWhiteVersion){
		$arrData = array();
		$intWhiteVersion = intval($strWhiteVersion);
		$arrData['version'] = $intWhiteVersion;
		//-1  获取是否有更新失败  -2 打包失败 0 无更新  1有更新且打包成功  
		$arrData['status'] = 0;
		$arrData['error_info'] = '';
		
		$strSearch = sprintf('{"update_time":{"$gt":%s}}', $strWhiteVersion);
		$strUrl = $this->strResDomain . $this->strCloudResRoot . '?' . 'search=' . urlencode($strSearch) . '&onlycontent=1&searchbyori=1&skip=0&limit=1';
		$arrResult = $this->getHttpRequestResponse($strUrl, 'HEAD');
		if (isset( $arrResult['http_code'] ) && intval( $arrResult['http_code'] ) === 200) {
			preg_match_all('|Last-Modified: (.*)\r\n|U', $arrResult['header'], $arrMatch);
			$intLastModify = strtotime($arrMatch[1][0]);
		}else{
			Logger::warning('cloud input check white update request res get version failed');
			$arrData['status'] = -1;
			$arrData['error_info'] = 'cloud input check white update request res get version failed';
		}
	
		//有更新则打包
		if(isset($intLastModify) && ($intLastModify > $intWhiteVersion)){
			$bolResult = $this->packageWord($intWhiteVersion, $intLastModify);
			$arrData['version'] = $intLastModify;
			if(false === $bolResult){
				$arrData['status'] = -2;
				$arrData['error_info'] = 'package word failed';
			}elseif (null === $bolResult) {
                $arrData['status'] = 0;
                $arrData['error_info'] = 'package word is empty after filter';
            } else{
				$arrData['status'] = 1;
			}
		}
		
		return $arrData;
	}
	
	
	/**
	 *
	 * @route({"GET","/rescheck"})
	 * 通用资源更新检查
	 *
	 * @param({"strResName","$._GET.res"})
	 * 白名单版本 资源最后修改时间
	 *
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 *
	 * @return({"body"})
	 *	1234, 最新版本  空字符串表示线上无资源或者获取资源信息失败
	 *	
	 */
	public function checkResUpdate($strResName){

	    $flag = 1;
	    $_GET['nc'];
	    $version = "";
	    
	    $cache_key = $this->strResUpdateCachePre .$strResName;

	    if($_GET['nc'] != 1)  {
	        $strCache = GFunc::cacheGet($cache_key);
	        if (false !== $strCache){
	            if($flag === 1) {
	                $filterid = 102;
	                if($strResName == 'game_keyboard_words') {
                        $conditionFilter = IoCload("utils\\ConditionFilter");
                        $filterConditionModel = IoCload("models\\FilterConditionModel");
                        $cacheFilter = $filterConditionModel->cache_getFilterFormResImeFilter();
                        foreach ($cacheFilter as $key => $value)
                        {
                            if($value['filter_id'] === $filterid && $conditionFilter->filter($value['filter_conditions']))
                            {
                                if(is_numeric($strCache)&&$strCache>1583151000)
                                {
                                    $strCache = $strCache+1;
                                }
                            }
                        }
                    }
                }
	            return $strCache;
	        }
	    }
	    
	    $strUrl = $this->strResDomain . $this->strResRoot . "{$strResName}/?&onlycontent=1&searchbyori=1&skip=0&limit=1";
	    
	    $arrResult = $this->getHttpRequestResponse($strUrl, 'HEAD');
	    if (isset( $arrResult['http_code'] ) && intval( $arrResult['http_code'] ) === 200) {
	        preg_match_all('|Last-Modified: (.*)\r\n|U', $arrResult['header'], $arrMatch);
	        $version =  strtotime($arrMatch[1][0]);
	        //set cache
	        GFunc::cacheSet($cache_key, $version, $this->intCacheExpired);
	    }


//        if($flag === 1)
//        {
//            $filterid = 102;
//            if($strResName == 'game_keyboard_words')
//            {
//                $conditionFilter = IoCload("utils\\ConditionFilter");
//                $filterConditionModel = IoCload("models\\FilterConditionModel");
//                $cacheFilter = $filterConditionModel->cache_getFilterFormResImeFilter();
//                foreach ($cacheFilter as $key => $value)
//                {
//                    if($value['filter_id'] === $filterid && $conditionFilter->filter($value['filter_conditions']))
//                    {
//                        $version = $version+1;
//                    }
//                }
//            }
//        }
//
          return $version;
	}
	
	/**
	 *
	 * @route({"GET","/sapplist"})
	 *
	 * 客户端上传信息
	 *
	 *
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 *
	 * @return({"body"})
	 * {
            "data": {
                "a.b.c": [
                    2,
                    1
                ],
                "1.2.3": [
                    2
                ],
                "4.5.6": [
                    1
                ]
            },
            "status": 1,
            "error_info": ""
        }
	 */
	public function getSugList() {
	    
	    $resCacheKey = "ime_v5_sugapp_whitelist_cache_key";
	    
	    $arrData = array();
	    $arrData['data'] = array();
	    $arrData['status'] = 0;
	    $arrData['error_info'] = '';
	    
	    $cacheData = $this->apc_cache->get($resCacheKey);
	    
	    if( null !== $cacheData) {
	        $data = $cacheData['sug_app'];
	        $filter_conditions = $cacheData['cond'];
	    }else {
	       
	        $strUrl = $this->strResDomain . $this->strResRoot . 'sug_app/?onlycontent=1';
  
	        $arrResult = $this->getHttpRequestResponse($strUrl, 'GET');
	      
	        if (isset( $arrResult['http_code'] ) && intval( $arrResult['http_code'] ) === 200) {
	            $data = json_decode($arrResult['body'], true);
	            $cacheData = array();
	            $cacheData['sug_app'] = $data ;
         
	            //获取全部过滤条件
	            $filter_conditions = array();
	             
	            //$strUrl = $this->strResDomain . $this->strResRoot . 'imefilter/?onlycontent=1'; //服务端通用过滤
	            $strUrl = $this->strResDomain . $this->strResRoot . 'filter/?onlycontent=1'; //云输入条件过滤
	            $arrResult = $this->getHttpRequestResponse($strUrl, 'GET');
   
	            if (isset( $arrResult['http_code'] ) && intval( $arrResult['http_code'] ) === 200) {
	                $filter_conditions = json_decode($arrResult['body'], true);
	                $cacheData['cond'] = $filter_conditions;
	                $this->apc_cache->set($resCacheKey, $cacheData, Gfunc::getCacheTime('30mins'));
	            }else{
	                Logger::warning('sapplist request res get filter failed');
	                $arrData['error_info'] = 'sapplist request res get filter failed';
	                return $arrData;
	            }
	            
	            
	        }else{
	            Logger::warning('sapplist request res get sug_app failed');
	            $arrData['error_info'] = 'sapplist request res get sug_app failed';
	            return $arrData;
	        }
	    }
   
	
	    $nfc = array();
	    foreach ($filter_conditions as $v) {
	        $nfc[$v['filter_id']] = $v['filter_conditions'];
	    }  


	    $conditionFilter = IoCload("utils\\ConditionFilter");

	    $res = array();
 
	    foreach ($data as $k => $v)
	    {
	        $cf = array();
	        $ct_flag = false; //跳出标记
	        //过滤条件强验证，空的过滤条件视为无效数据，多个过滤条件中有空值过滤条件也视为无效数据（PM定）
            if(!empty($v['filter_condition_id'])) {

                if(is_array($v['filter_condition_id'])) { //支持多个过滤id
                    foreach ($v['filter_condition_id'] as $fv) {
                        if(isset($nfc[$fv])) {
                            $cf = array_merge($cf,$nfc[$fv]);
                        }else {
                            $ct_flag = true;
                        }
                        
                    }
                }else { //单个过滤id
                    if(isset($nfc[$v['filter_condition_id']]) ) {
                        $cf = $nfc[$v['filter_condition_id']];
                    }else {
                        $ct_flag = true;
                    }
                    
                }
                
                if($ct_flag) {
                    continue;
                }
        
            } else {
                continue;  
            }
	        
	        
	        $new_cf = array();
	        foreach ($cf as $ck => $cv) {
	         
	            if($cv['operator'] == 'nin' || $cv['operator'] == 'in') {
	                //尝试解析换行分割的多个值则
	                $value_ary = array();
	                foreach ($cv['array'] as $cck => $ccv) {
	                    $val = explode(PHP_EOL, $ccv);
	                    foreach ($val as $valc => $valv) {
	                        array_push($value_ary, $valv);
	                    }
	                }
	                
	                $cv['array'] = $value_ary;
	            } 
	            array_push($new_cf, $cv);
	        }
	       
	        //提取filter_condition_id,并过滤数据
	        if($conditionFilter->filter($new_cf))
	        {
	            $res[] = $v;
	        }
	        
	    }
  
	    //整理格式，以一个白名单包名对应多个框id的形式返回
	    $rt = array();
	    
        foreach ($res as $v) {
	        $package_name =  array();
	        
	        if(isset($v['package_name']) && !empty($v['package_name']) && is_array($v['package_name'])) {
	            foreach ($v['package_name'] as $pv) {
	                $tmp = explode(PHP_EOL, $pv);
	                //解析textarea框的多行输入形式
	                foreach ($tmp as $tv) {
	                    if(!empty($tv)) {
	                        array_push($package_name, $tv);
	                    }
	                }
	            }
	        
	        }
	        
	        foreach ($package_name as $pv) {
	            $pkg_key = urlencode($pv);//防止中文包名造成的非法下标，先转一下
	            foreach ($v['sugdata'] as $sv) {
	                if(!isset($rt[$pkg_key])) {
	                    $rt[$pkg_key]= array();
	                    array_push($rt[$pkg_key] , $sv['ctrid']);
	                } else {
	                    if ( array_search($sv['ctrid'], $rt[$pkg_key]) === false ) {
	                        array_push($rt[$pkg_key] , $sv['ctrid']);
	                    }
	                }
	            }
	            
	        }
	    }

        //20190812 add by zhoubin05  for http://newicafe.baidu.com/issue/inputserver-2377/show
        //客户端存在服务端下发空白名单时,无法覆盖客户端本地已有白名单的问题，造成无法更新本地白名单
        //当发现下发数据为空时，下发一个不存在的包名和框id(pm提供)。以保证能覆盖客户端的本地数据。
        if(empty($rt)) {
            $rt = array('com.baidu.inputfake' => array(8));
        }

	   
	    $arrData['data'] =  trim(bd_B64_encode(json_encode($rt),0));
        $arrData['status'] = 1;
	
	    return $arrData;
	}
	
	
	/**
	 * @desc 获取app白名单（哪些app需要向内核发起"关键词推荐"请求）
	 * @route({"GET", "/tapplist"})
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"})
	 {
	 data: ['com.tencent.mobileqq','com.tencent.wechat')]
	 }
	 */
	public function getAppsWhiteList() {
	    $apnModel = IoCload("models\\TipsAppListModel");
	    
	    //Edit by fanwenli on 2018-04-19, set construction with new style about error code & error msg
	    $out = Util::initialClass(false);
	    
	    $out['data'] = $apnModel->getPackages();
	    $out['ecode'] = $apnModel->getStatusCode();
	    $out['emsg'] = $apnModel->getErrorMsg();
	    $out['version'] = $apnModel->intMsgVer;
	    
	    return Util::returnValue($out,false,true);
	    
	}
	
	/**
     * @desc 获取app白名单（哪些app需要向内核发起"关键词推荐"请求）
     * @route({"GET", "/silist"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
            "ecode": 0,
            "emsg": "success",
            "data": {
                "version": 1540800250,
                "list": [
                    {
                        "sug_id": 1,
                        "icon_url": "http://www.baidu.com",
                        "icon_msg": "111111",
                        "action_type_list": [
                            1,
                            2,
                            4
                        ]
                    },
                    {
                        "sug_id": 2,
                        "icon_url": "http://www.sina.com",
                        "icon_msg": "3333",
                        "action_type_list": [
                            3,
                            4
                        ]
                    },
                    {
                        "sug_id": 2,
                        "icon_url": "http://www.google.com",
                        "icon_msg": "88888",
                        "action_type_list": [
                            3
                        ]
                    }
                ]
            }
        }
     */
    public function getSugIconsList() {
        $out = Util::initialClass(false);
        
        $modSugIcon = IoCload('models\\SugIconListModel');
        $arrData = $modSugIcon->getData();
        
        $arrList = array('version' => $arrData['version']);
        
        $arrTmp =  array();



        foreach($arrData['data'] as $k => $v) {
            $key = $v['sug_id'];
            //sug标识组是否存在
            if(!isset($arrTmp[$key])) {
                $arrTmp[$key] = array();    
            }
            
            foreach($v['sug_data'] as $vk => $vv) {
                if(!empty($vv['icon_url'])) {
                    //icon地址归类是否存在
                    if(isset($arrTmp[$key][$vv['icon_url']])) {
                        //如果icon_msg是空的，但当前$vv的icon_msg有值则赋予结果数组
                        if(empty($arrTmp[$key][$vv['icon_url']]['icon_msg']) && !empty($vv['icon_msg'])) {
                            $arrTmp[$key][$vv['icon_url']]['icon_msg'] = $vv['icon_msg'];     
                        }
                        //如果当前$vv的sug_action_type不在结果数组中则加入
                        if(!in_array($vv['sug_action_type'], $arrTmp[$key][$vv['icon_url']]['action_type_list'])) {
                            array_push($arrTmp[$key][$vv['icon_url']]['action_type_list'], $vv['sug_action_type']);    
                        }
                        
                            
                    } else {
                        $arrTmp[$key][$vv['icon_url']] = array(
                            'icon_msg' => $vv['icon_msg'],
                            'action_type_list' => array($vv['sug_action_type']),
                        );  
                    }    
                }
                
            }
        }
        
        $arrFin = array();
        
        foreach($arrTmp as $k => $v) {
            foreach($v as $vk => $vv) {
                $arrOne =  array(
                    'sug_id' => intval($k),
                    'icon_url' => $vk,
                    'icon_msg' => $vv['icon_msg'],
                    'action_type_list' => $vv['action_type_list'],
                );
                array_push($arrFin, $arrOne);    
            }    
        }
        
        $arrList['list'] = $arrFin;
        
        $out['data'] = $arrList;
        
        return Util::returnValue($out,false);
        
    }


    /**
     * @desc 获取app白名单（哪些app需要向内核发起"关键词推荐"请求）
     * @route({"GET", "/sbilist"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     *
    {
        "ecode": 0,
        "emsg": "success",
        "data": {
        "version": 1564651085,
        "list": [
            {
            "sug_id": 3,
            "sug_action_type": 1,
            "icon_url": "http://douyu.com",
            "icon_msg": "555555",
            "content": "阿发说过"
            },
            {
            "sug_id": 1,
            "sug_action_type": 1,
            "icon_url": "http://imeres.baidu.com/imeres/ime-res/android_apk/2018-11-08/suglogobaidu.png",
            "icon_msg": "百度搜索建议",
            "content": "哈哈哈哈哈哈"
            },
            {
            "sug_id": 3,
            "sug_action_type": 5,
            "icon_url": "http://www.baidu.com",
            "icon_msg": "222",
            "content": "嗯嗯嗯嗯"
            },
            {
            "sug_id": 3,
            "sug_action_type": 2,
            "icon_url": "http://sina.com",
            "icon_msg": "5555",
            "content": ""
            }
        ]
        }
    }
     */
    public function getSugBandIconsList() {
        $out = Util::initialClass(false);

        $modSugIcon = IoCload('models\\SugIconListModel');
        $arrData = $modSugIcon->getData();

        $arrList = array('version' => $arrData['version']);

        $arrTmp =  array();


        $arrUniquKey =  array();

        foreach($arrData['data'] as $k => $v) {

            foreach($v['sug_data'] as $vk => $vv) {
                $key = $v['sug_id'].'_'. $vv['sug_action_type'];

                if(!in_array($key, $arrUniquKey)) {
                    $arrTmp[] = array(
                        'sug_id' => $v['sug_id'],
                        'sug_action_type' => $vv['sug_action_type'],
                        'icon_url' => !empty($vv['icon_url']) ? $vv['icon_url'] : '' ,
                        'icon_msg' => !empty($vv['icon_msg']) ? $vv['icon_msg'] : '' ,
                        'content' => !empty($vv['content']) ? $vv['content'] : '' ,
                    );

                    array_push($arrUniquKey, $key);
                }

            }

        }


        $arrList['list'] = $arrTmp;

        $out['data'] = $arrList;

        return Util::returnValue($out,false);

    }



    /**
     * @desc 获取app白名单（哪些app需要向内核发起"关键词推荐"请求）
     * @route({"GET", "/display_text"})
     * @param({"intDateType", "$._GET.data_type"}) 数据类型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     *

     */
    public function getDisplayTextListById($intDateType='') {
        $out = Util::initialClass(false);

        $modSugIcon = IoCload('models\\DisplayTextModel');
        $arrData = $modSugIcon->getData($intDateType);

        $arrList['version'] = $arrData['version'];

        $arrList['list'] = $arrData['data'];

        $out['data'] = $arrList;

        return Util::returnValue($out,false, true);

    }
	
}
