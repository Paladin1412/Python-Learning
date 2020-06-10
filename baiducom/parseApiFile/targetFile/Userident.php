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
use utils\Util;

/**
 * 免流套餐实时查询接口
 *
 * @author fanwenli
 * @path("/userident/")
 */
class Userident
{	
    /** @property appid 百度输入法是0003 */
    private $appid = '0003';
    
    /**
	 *
	 * 查询接口
	 *
	 * @route({"GET", "/find"})
	 * @param({"unikey", "$._GET.unikey"}) 端上随机生成的unikey
     * @param({"localip", "$._GET.localip"}) 用户端上获取的内网ip
     * @param({"cuid","$._GET.cuid"}) cuid 不需要客户端传
     * @param({"network","$._GET.network"}) 用户网络制式
     * @param({"track","$._GET.track"}) 用于统计移动端请求场景来源 1：冷启动 2：热启动 3：网络变化
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getFind($unikey = '', $localip = '', $cuid = '', $network = '',  $track = ''){
		$out = array('data' => array());
		
		$version = 0;
		if(isset($_GET['version'])) {
		    $version = Util::getVersionIntValue($_GET['version']);
		}
		
		//Edit by fanwenli on 2017-11-17, set default result when platform is i5 or i6
		//Edit by fanwenli on 2017-12-01, set default result when version less than 7.8
		if($version < 7080000 && isset($_GET['platform']) && ($_GET['platform'] == 'i5' || $_GET['platform'] == 'i6')) {
		    $arrResult = array(
		        "status" => 0,
		        "message" => "ok",
		        "data" => array(
		            "product" => "0",
		            "isp" => "UNKNOWN",
		            "state" => "0",
		            "openid" => "0",
		            "userkey" => "0",
		        ),
		    );
		} else {
		    $clientip = Util::getClientIP();
		    
		    $query_arr = array(
		        'unikey' => trim($unikey),
		        'localip' => trim($localip),
		        'clientip' => trim($clientip),
		        'appid' => trim($this->appid),
		        'cuid' => trim($cuid),
		        'network' => trim($network),
		        'track' => trim($track),
		    );

		    $query = http_build_query($query_arr);
		
		    //set header
            $arrHeader = array(
                'pathinfo' => '/userident/find',
                'querystring'=> $query,
            );
            
            $arrResult = ral("userident", "get", null, null, $arrHeader);
            
            //if return is not array, then set it to array
            $arrResult = is_array($arrResult)?$arrResult:json_decode($arrResult,true);
        }
        
        
        if(!empty($arrResult)){
            $out['data'] = bd_B64_encode(json_encode($arrResult),0);
        }
        
        return $out;
	}
}
