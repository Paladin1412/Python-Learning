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
use models\HetuModel;
use utils\Util;
use utils\InnerToken;

/**
 * 河图获取数据接口
 *
 * @author fanwenli
 * @path("/hetu/")
 */
class Hetu
{	
    /** @property 河图token */
    private $token;
    
    function __construct() {
        $appid = '10342115';
		$uid = '1884599608';
		$sk = '8CQm4V2URmn71CGjC25rbjSdlkvUFMZG';
		$it = new InnerToken(11);
		$this->token = $it->generateToken($appid, $uid, $sk);
    }
    
    /**
	 *
	 * 通用信息
	 *
	 * @route({"POST", "/general"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @param({"top_num","$._POST.top_num"})    列表显示数量
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getGeneralInfo($strImageB64,$top_num = 5){
		
		$type = 'general';
		
		$param = array('top_num' => $top_num);
		
		$out = $this->getListContent($type,$this->token,$strImageB64,$param);
        
        return $out;
	}
	
	/**
	 *
	 * 花卉信息
	 *
	 * @route({"POST", "/flower"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @param({"top_num","$._POST.top_num"})    列表显示数量
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getFlowerInfo($strImageB64,$top_num = 5){
		
		$type = 'flower';
		
		$param = array('top_num' => $top_num);
		
		$out = $this->getListContent($type,$this->token,$strImageB64,$param);
        
        return $out;
	}
	
	/**
	 *
	 * 动物信息
	 *
	 * @route({"POST", "/animal"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @param({"top_num","$._POST.top_num"})    列表显示数量
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getAnimalInfo($strImageB64,$top_num = 6){
		
		$type = 'animal';
		
		$param = array('top_num' => $top_num);
		
		$out = $this->getListContent($type,$this->token,$strImageB64,$param);
        
        return $out;
	}
	
	/**
	 *
	 * 汽车信息
	 *
	 * @route({"POST", "/car"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @param({"top_num","$._POST.top_num"})    列表显示数量
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getCarInfo($strImageB64,$top_num = 5){
		
		$type = 'car';
		
		$param = array('top_num' => $top_num);
		
		$out = $this->getListContent($type,$this->token,$strImageB64,$param);
        
        return $out;
	}
	
	/**
	 *
	 * logo信息
	 *
	 * @route({"POST", "/logo"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getLogoInfo($strImageB64){
		
		$type = 'logo';
		
		$out = $this->getListContent($type,$this->token,$strImageB64);
        
        return $out;
	}
	
	/**
	 *
	 * 以图搜图信息
	 *
	 * @route({"POST", "/search"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getSearchInfo($strImageB64){
		
		$type = 'search';
		
		$out = $this->getListContent($type,$this->token,$strImageB64);
        
        return $out;
	}
	
	/**
	 *
	 * 菜品信息
	 *
	 * @route({"POST", "/dish"})
	 * @param({"strImageB64","$._POST.image"})                  图片信息
	 * @param({"top_num","$._POST.top_num"})                    列表显示数量
	 * @param({"filter_threshold","$._POST.filter_threshold"})  可信度过滤阈值
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getDishInfo($strImageB64,$top_num = 5,$filter_threshold = 0.9){
		
		$type = 'dish';
		
		$param = array('top_num' => $top_num, 'filter_threshold' => $filter_threshold);
		
		$out = $this->getListContent($type,$this->token,$strImageB64,$param);
        
        return $out;
	}
	
	/**
	 *
	 * 人脸识别
	 *
	 * @route({"POST", "/recognize"})
	 * @param({"strImageB64","$._POST.image"})                  图片信息
	 * @param({"top_num","$._POST.top_num"})                    列表显示数量
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getRecognizeInfo($strImageB64,$top_num = 5){
		
		$type = 'recognize';
		
		$param = array('top_num' => $top_num);
		
		$out = $this->getListContent($type,$this->token,$strImageB64);
        
        return $out;
	}
	
	/**
	 *
	 * 地标识别
	 *
	 * @route({"POST", "/poi_service_recognize"})
	 * @param({"strImageB64","$._POST.image"})  图片信息
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
	 *      ]
	 * }
	 */
	public function getPoiServiceInfo($strImageB64){
		
		$type = 'poi_service_recognize';
		
		$out = $this->getListContent($type,$this->token,$strImageB64);
        
        return $out;
	}
	
	/**
     * @desc 获取数据
     * @param $type         数据类别
     * @param $token        token
     * @param $image_data   图片数据
     * @param $param        内容参数
     * @return array
    */
	public function getListContent($type,$token,$image_data = '',$param = array()){
	    $out = array();
	    
	    $HetuModel = IoCload('models\\HetuModel');
	    
	    switch($type) {
        	//通用接口
        	case 'general':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/classify/general';
        	    break;
        	//花卉
        	case 'flower':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/flower';
        	    break;
        	//宠物动物
        	case 'animal':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/animal';
        	    break;
        	//汽车
        	case 'car':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/car';
        	    break;
        	//logo
        	case 'logo':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v2/classify/logo';
        	    break;
        	//以图搜图
        	case 'search':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/realtime_search/similar/search';
        	    break;
        	//菜品
        	case 'dish':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v2/classify/dish';
        	    break;
        	//人脸识别
        	case 'recognize':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-starface/v2/recognize';
        	    break;
        	//地标识别
        	case 'poi_service_recognize':
        	    $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/PoiService/recognize';
        	    break;
        }
        	
        $hetulist = $HetuModel->getHetuContent($url,$token,$image_data,$param);
        
        if(!empty($hetulist)){
        	//$out = bd_B64_encode(json_encode($hetulist),0);
        	$out = $hetulist;
        }
        
        return $out;
	}
}
