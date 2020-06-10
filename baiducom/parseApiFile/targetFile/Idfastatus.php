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

require_once __DIR__.'/utils/CurlRequest.php';
/**
 * 
 * 查询idfa状态
 * 说明：激活接口保存idfa信息
 * 
 * @author yangxugang
 * @path("/idfa/") 
 */
class Idfastatus
{
	/** @property */
	public $strIdfaResRoot;
	
	/** @property */
	public $strChannelResRoot;
	
	/** @property 内部缓存实例 */
	private $cache;
	
	/**
	 *
	 * idfa max length
	 *
	 */
	const MAX_LENGTH_IDFA = 64;
	
	/**
	 *
	 * post idfa max size
	 * 1M
	 *
	 */
	const MAX_POST_IDFA_SIZE = 1073741824;
	
	
	/** @property 内部缓存默认过期时间(单位: 秒) */
	private $intCacheExpired;
	
	
	/**
	 * 批量查询单次查询idfa数量上限
	 *
	 */
	const MAX_PER_REQ_IDFA = 10000;
	
	/**
	 * 批量请求时间间隔
	 *
	 */
	const MIN_PER_BATCH_REQ_INTERVAL = 3;
	
	/**
	 * 查询单个idfa请求时间间隔
	 *
	 */
	const MIN_PER_SIN_REQ_INTERVAL = 1;
	
	/**
	 *
	 * cache channel info key prefix
	 *
	 */
	const CACHE_CHANNEL_INFO_PREFIX = 'idfa_channel_info_';
	
	/**
	 *
	 * cache channel call record key prefix
	 *
	 */
	const CACHE_CHANNEL_CALL_RECORD_PREFIX = 'idfa_status_call_new_';
	
	/**
	 * 获取路径
	 *
	 * @param $path 请求路径数组
	 *
	 * @return string
	 */
	private function getPath($path){
		if(is_array($path)){
			$path = implode('/', $path);
		}
		if(strlen($path)!==0){
			$path = '/'.$path;
		}
		if(substr($_SERVER['PATH_INFO'], - 1, 1 ) === '/'){
			$path .= '/';
		}
		if(strlen($path)==0){
			$path = '/';
		}
		return $path;
	}
	
	/**
	 * 获取渠道信息
	 *
	 * @param $strFrom 渠道
	 *
	 * @return array
	 */
	private function getChannelInfo($strFrom){
		$strCacheKey = self::CACHE_CHANNEL_INFO_PREFIX . $strFrom;
		$bolStatus = false;
		$arrResult = $this->cache->get($strCacheKey, $bolStatus);
		
		$arrSearch = array();
		$arrSearch['channel'] = $strFrom;
		$strSearch = 'search=' . urlencode(json_encode($arrSearch));
		if(null === $arrResult){
			$arrHeader = array(
				'pathinfo' => $this->strChannelResRoot,
				'querystring'=> "onlycontent=1&" . $strSearch,
			);
			
			$arrResult = ral("res_service", "get", null, rand(), $arrHeader);
			if(false === $arrResult){
				return false;
			}
			$arrChannelInfo = $arrResult;
			
			//只取一个
			foreach ($arrChannelInfo as $strKey => $arrValue){
				$arrChannelInfo = $arrValue;
				break;
			}
			
			$this->cache->set($strCacheKey, $arrChannelInfo, $this->intCacheExpired);
			
		}else{
			$arrChannelInfo = $arrResult;
		}
		
		return $arrChannelInfo;
	}
	
	/**
	 * 获取请求记录
	 *
	 * @param $strFrom 渠道
	 *
	 * @return array
	 */
	private function getCallRecord($strFrom){
		//获取访问记录
		$strCacheKey = self::CACHE_CHANNEL_CALL_RECORD_PREFIX . $strFrom;
		$bolStatus = false;
		$arrCallRecord = $this->cache->get($strCacheKey, $bolStatus);
		
		if(null === $arrCallRecord){
			$arrCallRecord = array();
			$arrCallRecord['call_times'] = 0;
			$arrCallRecord['idfa_count'] = 0;
			$arrCallRecord['single_call_times'] = 0;
			$arrCallRecord['batch_req_time'] = 0;
			$arrCallRecord['single_req_time'] = 0;
		}
		
		return $arrCallRecord;
	}
	
	/**
	 * 保存请求记录
	 *
	 * @param $strFrom 渠道
	 * @param $arrCallRecord 请求记录
	 *
	 * @return array
	 */
	private function setCallRecord($strFrom, $arrCallRecord){
		$strCacheKey = self::CACHE_CHANNEL_CALL_RECORD_PREFIX . $strFrom;
		
		$strEndToday = date('Y-m-d'.' 23:59:59',time());
		$intEndToday = strtotime($strEndToday);
		$intCacheSecond = $intEndToday - time();
		
		$this->cache->set($strCacheKey, $arrCallRecord, $intCacheSecond);
	}
	
	/**
	 * 获取当前时间单位毫秒
	 *
	 *
	 * @return float
	 */
	private function getMillisecond() {
	    return microtime(true);
	}
	
	/**
	 *
	 * @route({"POST","/info"})
	 *
	 * @return({"body"})
	 *	
	 */
	public function queryIdfaInfo(){
	    $strData = file_get_contents ( 'php://input' );
	    //上传文件
	    if(isset($_FILES['file'])){
	        if (isset ( $_FILES['file']['error'] ) && intval ( $_FILES['file']['error'] ) === 0 && isset ( $_FILES['file']['tmp_name'] )) {
	            $strFileName = $_FILES['file']['tmp_name'];
	            $strData = file_get_contents($strFileName);
	        }
	    }
	     
	    Verify::isTrue( ($strData !== null) , new BadRequest("param empty"));
	    
	    //检验idfa
	    $arrIdfaList = array();
	    $arrIdfaListTmp = explode("\n", $strData);
	    
	    $arrIdfaListTmp = array_unique($arrIdfaListTmp);
	    foreach ($arrIdfaListTmp as $strIdfa){
	        $strIdfa = str_replace("\n", '', $strIdfa);
	        if('' === trim($strIdfa)){
	            continue;
	        }
	        	
	        Verify::isTrue( strlen($strIdfa) <= self::MAX_LENGTH_IDFA, new BadRequest("idfa wrong"));
	        	
	        $arrIdfaList[] = $strIdfa;
	    }
	    
	    $intIdfaCount = count($arrIdfaList);
	    Verify::isTrue( 0 !== $intIdfaCount, new BadRequest("idfa empty"));
	    Verify::isTrue( $intIdfaCount <= self::MAX_PER_REQ_IDFA, new BadRequest("idfa count too large"));
	    
	    $objIdfaModel = IoCload("models\\IdfaModel");
	    $arrIdfaInfo = $objIdfaModel->getBatchIdfaStatus($arrIdfaList, true);
	    
	    return $arrIdfaInfo;
	}

    /**
     * 查询单个idfa信息
     * http://agroup.baidu.com/inputserver/md/article/1856543
     * @route({"GET","/oneidfainfo"})
     * @param({"idfa","$._GET.idfa"})
     *
     * @return({"body"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function queryOneIdfaInfo($idfa){
        $arrIdfaList = array(
            $idfa,
        );
        Verify::isTrue($idfa, new BadRequest("idfa empty"));

        $objIdfaModel = IoCload("models\\IdfaModel");
        $arrIdfaInfo = $objIdfaModel->getBatchIdfaStatus($arrIdfaList, true);
        foreach ($arrIdfaInfo as $k => $v) {
            $arrIdfaInfo[$k]['idfa'] = $v['uid'];
            $arrIdfaInfo[$k]['点击时间'] = !empty($v['create_time']) ? date('Y-m-d H:i:s', $v['create_time']) : 0;
            $arrIdfaInfo[$k]['渠道'] = $v['channel'];
            $arrIdfaInfo[$k]['回调接口'] = $v['callback'];
            $arrIdfaInfo[$k]['是否激活'] = 1 == $v['status'] ? '是' : '否';
            $arrIdfaInfo[$k]['激活时间'] = !empty($v['active_time']) ? date('Y-m-d H:i:s', $v['active_time']) : 0;
            $arrIdfaInfo[$k]['是否回调成功'] = 1 == $v['callback_result'] ? '是' : '否';
            foreach ($v as $key => $val) {
                unset($arrIdfaInfo[$k][$key]);
            }
        }

        return $arrIdfaInfo;
    }
	
	/**
	 *
	 * @route({"POST","/status"})
	 * 
	 * @param({"arrPath","$.path[0:]"})
	 * 请求路径
	 * 	
	 * @param({"strFrom","$._GET.from"}) 
	 * 渠道号
	 * 
	 * @param({"strTime","$._GET.time"}) 
	 * 请求时间，如1428654012
	 * 注意：此时间不能同当前时间偏差超过10分钟
	 * 
	 * @param({"strSecret","$._GET.secret"}) 
	 * md5(from . time . token . idfa_data)，即连接渠道、请求时间、渠道token、请求idfa数据md5
	 * 
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * 请求上传参数time同服务器时间偏差超过600秒 ，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: time wrong"}
	 * idfa列表文件太大，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: data too larger"}
	 * idfa列表的idfa数量超过1000，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: idfa count too larger"}
	 * idfa长度超过64，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: idfa wrong"}
	 * 请求渠道不存在，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: channel wrong"}
	 * 签名错误，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: secret wrong"}
	 * 请求接口错误，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: request interface wrong"}
	 * 请求接口时间不在授权时间段，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: request not in time"}
	 * 超过授权请求次数，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: call times use up"}
	 * 超过授权查询idfa数，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: query idfa count use up"}
	 * 批量查询接口在当前时刻不开通查询，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: this time not open"}
	 * 批量查询接口请求太频繁，返回错误信息：{"code":-1,"message":"tinyESB\\util\\exceptions\\BadRequest: request too often"}
	 * 
	 * @return({"body"})
	 *	{"idfa1":"1","idfa2":"0","idfa3":"0"}
	 */
	public function queryIdfaStatus($arrPath, $strFrom, $strTime = '', $strSecret = ''){
	    //避免传递header "Content-Type: text/plan"
	    $strData = file_get_contents ( 'php://input' );
	    //上传文件
	    if(isset($_FILES['file'])){
	        if (isset ( $_FILES['file']['error'] ) && intval ( $_FILES['file']['error'] ) === 0 && isset ( $_FILES['file']['tmp_name'] )) {
	            $strFileName = $_FILES['file']['tmp_name'];
	            $strData = file_get_contents($strFileName);
	        }
	    }
	    
		Verify::isTrue( ($strData !== null) && ('' !== $strFrom), new BadRequest("param empty"));
		//获取请求路径
		$strPath = $this->getPath($arrPath);
		
		//限制上传idfa数据大小
		$intDataLength = strlen($strData);
		Verify::isTrue( $intDataLength < self::MAX_POST_IDFA_SIZE, new BadRequest("data too larger"));
		
		//检验idfa
		$arrIdfaList = array();
		$arrIdfaListTmp = explode("\n", $strData);
		
		$arrIdfaListTmp = array_unique($arrIdfaListTmp);
		foreach ($arrIdfaListTmp as $strIdfa){
			$strIdfa = str_replace("\n", '', $strIdfa);
			if('' === trim($strIdfa)){
				continue;
			}
			
			Verify::isTrue( strlen($strIdfa) <= self::MAX_LENGTH_IDFA, new BadRequest("idfa wrong"));
			
			$arrIdfaList[] = $strIdfa;
		}
		
		$intIdfaCount = count($arrIdfaList);
		Verify::isTrue( 0 !== $intIdfaCount, new BadRequest("idfa empty"));
		Verify::isTrue( $intIdfaCount <= self::MAX_PER_REQ_IDFA, new BadRequest("idfa count too large"));
		
		//获取渠道信息
		$arrChannelInfo = $this->getChannelInfo($strFrom);
		Verify::isTrue( false !== $arrChannelInfo, "ral failed errno:[" . ral_get_errno() . ']error_msg:' . ral_get_error() . ']protocol_status:[' . ral_get_protocol_code() . ']');
		Verify::isTrue( 0 !== count($arrChannelInfo), new BadRequest("channel wrong"));
		
		//验证配置单次批量请求查询最大数量
		Verify::isTrue( $intIdfaCount <= intval($arrChannelInfo['per_patch_max_idfa_count']), new BadRequest("per query idfa count too large"));
		
		//获取是否验证签名
		$bolIsCheck = isset($arrChannelInfo['check_switch'])? ('check' === $arrChannelInfo['check_switch']) : true;
		
		//验证签名
		if($bolIsCheck){
		    //强验证需要验time及secret
		    Verify::isTrue( ('' !== $strTime) && ('' !== $strSecret), new BadRequest("param empty"));
		    
		    //请求时间同服务器时间偏差不超过600秒
		    Verify::isTrue( abs(time() - intval($strTime)) < 600, new BadRequest("time wrong"));
		    
		    Verify::isTrue( $strSecret === md5($strFrom . $strTime . $arrChannelInfo['token'] . $strData), new BadRequest("secret wrong"));
		}
		
		//请求接口验证
		Verify::isTrue( $arrChannelInfo['interface'] === $strPath, new BadRequest("request interface wrong"));
		
		//验证接口开放时间
		Verify::isTrue( (time() > intval($arrChannelInfo['begin_time'])) && (time() < intval($arrChannelInfo['end_time'])) , new BadRequest("request not in time"));
		
		//判断批量请求开放时刻
		if($intIdfaCount > 1){
			$arrOpenHour = explode('|', $arrChannelInfo['patch_open_hour']);
			$strCurHour = strval( intval(date("H")) );
			Verify::isTrue( in_array($strCurHour, $arrOpenHour), new BadRequest("this time not open"));
		}
		
		//获取访问记录
		$arrCallRecord = $this->getCallRecord($strFrom);
		
		if($intIdfaCount > 1){
			//限制请求接口间隔
			$intInterval = (0 == intval($arrCallRecord['batch_req_time']))? intval($arrChannelInfo['per_patch_min_interval']) : ($this->getMillisecond() - intval($arrCallRecord['batch_req_time']));
			Verify::isTrue($intInterval >= intval($arrChannelInfo['per_patch_min_interval']), new BadRequest("request too often"));
			
			//批量请求次数验证
			Verify::isTrue( $arrCallRecord['call_times'] < intval($arrChannelInfo['patch_max_call_count']), new BadRequest("batch call times use up"));
			
			//批量请求idfa量验证
			Verify::isTrue( $arrCallRecord['idfa_count'] < intval($arrChannelInfo['patch_max_idfa_count']), new BadRequest("batch query idfa count use up"));
			
			$arrCallRecord['call_times'] = $arrCallRecord['call_times'] + 1;
			$arrCallRecord['idfa_count'] = $arrCallRecord['idfa_count'] + $intIdfaCount;
			$arrCallRecord['batch_req_time'] = $this->getMillisecond();
		}else{
			//
			//$intInterval = (0 == intval($arrCallRecord['single_req_time']))? self::MIN_PER_SIN_REQ_INTERVAL : (time() - intval($arrCallRecord['single_req_time']));
			//Verify::isTrue($intInterval >= self::MIN_PER_SIN_REQ_INTERVAL, new BadRequest("request too often"));
			
			//批量请求次数验证
			Verify::isTrue( $arrCallRecord['single_call_times'] < intval($arrChannelInfo['single_max_call_count']), new BadRequest("single query call times use up"));
			$arrCallRecord['single_call_times'] = $arrCallRecord['single_call_times'] + 1;
			$arrCallRecord['single_req_time'] = $this->getMillisecond();
		}
		
		//获取idfa status
		$arrIdfaStatus = array();
		$objIdfaModel = IoCload("models\\IdfaModel");
		$arrIdfaStatus = $objIdfaModel->getBatchIdfaStatus($arrIdfaList);
		
		//更新请求信息
		$this->setCallRecord($strFrom, $arrCallRecord);
		
		//零时增加定位问题
		$this->reportIdfaStatus($arrIdfaList, $arrIdfaStatus);
		
		return $arrIdfaStatus;
	}
	
	/**
	 *
	 * 统计单个idfa查询返回结果
	 * 零时增加
	 *
	 * @param $arrIdfaList query idfa list
	 * @param $arrIdfaStatus idfa status
	 *
	 */
	public function reportIdfaStatus($arrIdfaList, $arrIdfaStatus){
	    if( 1 === count($arrIdfaList) ){
	        $strV4Inner = 'dbl-mic-input00.dbl01';
	         
	        $strIdfaStatus = urlencode(json_encode($arrIdfaStatus));
	         
	        $strUrl = 'http://' . $strV4Inner . '.baidu.com:8080/v4/?c=report&e=anal' . "&idfastatus=$strIdfaStatus" . '&inputgd=1';
	        //$strUrl = 'http://r6.mo.baidu.com/v4/?c=report&e=anal' . "&rptlc=$strLocation" . '&inputgd=1' . str_replace('/v5/noti/info', '', $_SERVER['QUERY_STRING']);
	        $arrResult = $this->getHttpRequestResponse($strUrl, 'GET');
	        if (isset( $arrResult['http_code'] ) && intval( $arrResult['http_code'] ) === 200) {
	             
	        }
	    }
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
    
}
