<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 */
/**
 * @author wanzhongkun(wanzhongkun@baidu.com)
 * @desc 链接跳转跟踪类
 * @path("/trace/")
 */
class Trace{
	
    /**  @property */
    private $storage;  
    
	//签名加密salt
	const SIGN_SALT = 'iudfu(lkc#xv345y82$dsfjksa';
	
	/** @property 内部缓存默认过期时间(单位: 秒) */
	private $intCacheExpired;
	
	/**
	 * @desc 302到指定的url或返回404（地址无效）
	 * @route({"GET", "/"})
	 * @param({"url", "$._GET.url"}) string $url urlencode过的url,包含协议头(http://,https://,ftp://等)
	 * @param({"sign", "$._GET.sign"}) string $sign 签名
	 * @return({"status", "$status"})
	 * @return({"header", "$location"})
	 * @return({"body"})
	 * 验证通过则：
	 * HTTP/1.1 302 Found
	 * Location: 要跳转的url
	 * 验证不通过则：
	 * HTTP/1.1 404 Not Found
	 */
	function traceCommon($url, $sign, &$status = '', &$location = ''){
	    $url = trim($url);
	    $sign = trim($sign);
	    if (strlen($url) > 0 && (strlen($sign) === 32 || strlen($sign) === 0) && $this->checkSign($url, $sign)) {
	        $status = "302 Found";
	        $location = "Location: ".$url . $this->addPassThroughParams();
	    }
	    else{
	        $status = "404 Not Found";
	        return ;
	    }
	}	
	
	/**
	 * @desc 302到指定的url或返回404（地址无效）
	 * @route({"GET", "/cpd"})
	 * @param({"url", "$._GET.url"}) string $url urlencode过的url,包含协议头(http://,https://,ftp://等)
	 * @param({"sign", "$._GET.sign"}) string $sign 签名
	 * @param({"ad_zone", "$._GET.ad_zone"}) int $ad_zone 广告区域	 
	 * @param({"src_id", "$._GET.src_id"}) string $src_id 资源id
	 * @param({"cpd_num", "$._GET.cpd_num"}) int $cpd_num 广告cpd计数数量
	 * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
	 * @return({"status", "$status"})
	 * @return({"header", "$location"})
	 * @return({"body"})
	 * 验证通过则：
	 * HTTP/1.1 302 Found
	 * Location: 要跳转的url
	 * 验证不通过则：
	 * HTTP/1.1 404 Not Found
	 */
	function traceForCpd($url, $sign, $ad_zone = 0, $src_id = "", $cpd_num = 0, $cuid = "", &$status = '', &$location = ''){
		$url = trim($url);
		$sign = trim($sign);
		if (strlen($url) > 0 && (strlen($sign) === 32 || strlen($sign) === 0) && $this->checkSign($url, $sign)) {
		    $status = "302 Found";
		    $location = "Location: ".$url . $this->addPassThroughParams();
		    //需要进行CPD计费方式，则在跳转下载地址成功进行计数
		    if(intval($cpd_num) > 0){
		        $cpd_key = "CPD_".strval($ad_zone)."_".strval($src_id);
		        $get_status = null;
		        //getNoCacheVersion是和incr一样都没有缓存key前缀的
		        $dowload_cnt = $this->storage->getNoCacheVersion($cpd_key, $get_status);
		        if($get_status){
		            if(is_null($dowload_cnt) || intval($dowload_cnt) < intval($cpd_num)){
		                if(!empty($cuid) && $cuid !== ''){
		                    $cpd_cuid_key = $cpd_key."_".$cuid;
		                    $get_status = null;
		                    $get_res = $this->storage->get($cpd_cuid_key, $get_status);
		                    if($get_status && is_null($get_res)){
		                        $incr_status = null;
		                        $this->storage->incr($cpd_key, $incr_status);
		                        if($incr_status){
		                            $set_status = null;
		                            $this->storage->set($cpd_cuid_key, time(), $this->intCacheExpired, $set_status);
		                        }		                        
		                    }
		                }
		            }
		        }
		    }					
		}
		else{
			$status = "404 Not Found";
			return ;
		}
	}
	
	/**
	 * @desc 302到指定的url或返回404（地址无效）
	 * @route({"GET", "/rscnoti"})
	 * @param({"url", "$._GET.url"}) string $url urlencode过的url,包含协议头(http://,https://,ftp://等)
	 * @param({"sign", "$._GET.sign"}) string $sign 签名
	 * @param({"ad_zone", "$._GET.ad_zone"}) int $ad_zone 广告区域
	 * @param({"ad_id", "$._GET.ad_id"}) string $ad_id 广告id
	 * @param({"rsc_noti_cnt", "$._GET.rsc_noti_cnt"}) int $rsc_noti_cnt 广告资源请求用户数
	 * @param({"ad_exptime", "$._GET.ad_exptime"}) int $ad_exptime 广告过期时间
	 * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
	 * @return({"status", "$status"})
	 * @return({"header", "$location"})
	 * @return({"body"})
	 * 验证通过则：
	 * HTTP/1.1 302 Found
	 * Location: 要跳转的url
	 * 验证不通过则：
	 * HTTP/1.1 404 Not Found
	 */
	function traceForRscNoti($url, $sign, $ad_zone = 0, $ad_id = "", $rsc_noti_cnt = 0, $ad_exptime = '', $cuid = '', &$status = '', &$location = ''){
	    $url = trim($url);
	    $sign = trim($sign);
	    if (strlen($url) > 0 && (strlen($sign) === 32 || strlen($sign) === 0) && $this->checkSign($url, $sign)) {
	        $status = "302 Found";
	        $location = "Location: ".$url . $this->addPassThroughParams();
	        //广告资源请求用户数
	        if(intval($rsc_noti_cnt) > 0 && !empty($cuid) && $cuid !== ''){
	            $rsc_noti_key = "RscNoti_".strval($ad_zone)."_".strval($ad_id);
	            $scard_status = null;
	            $rsc_noti_got = $this->storage->scard($rsc_noti_key, $scard_status);
	            if($scard_status && intval($rsc_noti_got) < intval($rsc_noti_cnt)){
	                $sadd_status = null;
	                $expireat_status = null;
	                $this->storage->sadd($rsc_noti_key, $cuid, $sadd_status);
	                $this->storage->expireat($rsc_noti_key, $cuid, $expireat_status);
	            }
	        }	        
	    }
	    else{
	        $status = "404 Not Found";
	        return ;
	    }
	}
	
	/**
	 * @desc 校验签名
	 * @param $url 要跳转的url
	 * @param $sign 签名
	 * @return boolean
	 */
	private function checkSign($url, $sign){
		$url = trim($url);
		$sign = trim($sign);
		if (strlen($sign) === 32) {//签名非空，可在校验成功的情况下跳转所有域名，包括非百度域名
			if ($this->genSign($url) === $sign) {
				return true;
			}
		}
		else {//签名长度不符一定要校验域名
			if ($this->checkDomain($url)) {
				return true;
			}
			return false;
		}
		return false;
	}
	
	/**
	 * @desc 校验url和域名
	 * @param $url 要跳转的url
	 * @return boolean
	 */
	private function checkDomain($url){
		$pattern = '/(?:http|https|ftp):\/\/[\s\S]*?\.(?:baidu\.com|dwz\.cn)[\s\S]*?$/';
		if (preg_match($pattern, $url)) {
			return true;
		}
		return false;
	}
	
	/**
	 * @desc 获取签名
	 * @param $url 要跳转的url
	 * @return string 32bit md5 string
	 */
	private function genSign($url){
		return md5($url.self::SIGN_SALT);
	}


    /**
     * 给目标地址添加客户端通用参数 (目前仅对目标地址为 /v5/oem/sst(oem华为导流主线中转地址 有效) )
     * @return string
     */
	private function addPassThroughParams() {
	    $strParams = '';
	    $arrBaseURI = explode('?', $_SERVER['REQUEST_URI']);
	    if(isset($arrBaseURI[1])) {
            $arrParams = array();
	        parse_str($arrBaseURI[1], $arrParams);

	        if(isset($arrParams['url'])) {
	            //检测是否是oem导流中转地址
                $arrMidUrl = pathinfo($arrParams['url']);
                if(isset($arrMidUrl['dirname'])) {
                    $arrMidUrlInfo = explode('v5/', $arrMidUrl['dirname']);
                    if('oem/sst' === $arrMidUrlInfo[1] || 'oem/sst/' === $arrMidUrlInfo[1]) {
                        $bolStartWrite = false;
                        $arrNewParams =  array();
                        foreach($arrParams as $k => $v) {
                            //从env开始记录
                            if('env' === $k) {
                                $bolStartWrite = true;
                            }

                            if(true === $bolStartWrite) {
                                $arrNewParams[$k] = $v;
                            }
                        }
                        $strParams = '&' . http_build_query($arrNewParams);
                    }
                }

            }

        }

	    return $strParams;

    }
}