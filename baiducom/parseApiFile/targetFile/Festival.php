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
use models\FestivalModel;

require_once __DIR__.'/utils/CurlRequest.php';

/**
 * 节日
 *
 * @author fanwenli
 * @path("/festival/")
 */
class Festival
{
	//每页显示最多条数
	const MAX_PAGE_SIZE = 200;
	//默认每页显示条数
	const GENERAL_PAGE_SIZE = 12;
	
	/** @property 内部缓存实例 */
	private $cache;
        
    //内部缓存key前缀
	const INTERNAL_CACHE_KEY_PREFIX = 'st_festival_';
        
	/** @property 内部缓存默认过期时间(单位: 秒) */
	private $intCacheExpired;
	
	/**
	 *
	 * 外部设置缓存
	 * 目前个性化词库推荐用到,预热脚本用到
	 *
	 * @route({"GET","/cache"})
	 *
	 * @param({"strKey","$._GET.key"}) $strKey cache key
	 * @param({"strValue","$._GET.value"}) $strValue cache value
	 * @param({"intTtl","$._GET.ttl"}) $intTtl 过期时间默认不过期
	 *
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 *
	 * @return({"body"}) void
	 */
	public function setCache($strKey, $strValue, $intTtl = 0){
		$intTtl = intval($intTtl);
		$arrValue = json_decode($strValue, true);
		//如果为json则存储为数组
		if(null !== $arrValue){
			$this->cache->set($strKey, $arrValue, $intTtl);
		}else{
			$this->cache->set($strKey, $strValue, $intTtl);
		}
	}
        
    /**
	 *
	 * 节日详情获取
	 *
	 * @route({"GET", "/items/*\/detail"})
	 * @param({"id", "$.path[2]"}) string $id 节日标识
	 * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "data": [
	 *          {
	 *              "id": 9, 识别号
	 *              "name": "春节", 节日名称
	 *              "img_logo": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:Y6mI2%2FNyMM6jx%2Bmnxap0AnW8Hhg%3D",
	 *              "img_logo_480": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_480.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:4HaPlyvXp3IT%2FsBvA2gGEhCM7wQ%3D",
	 *              "img_logo_720": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_720.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:KzA7UsmG4%2B4JFNGxPDz0a35NLy8%3D",
	 *              "img_logo_2x": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_2x.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:wYV9z6Hf5wmk7V6fCD2Xk9LLuvY%3D",
	 *              "img_logo_3x": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_3x.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:ZVeV3PAKIoGQ0ABXP7eLl53lF9g%3D",					            
	 *              "is_color_wash": 1, 是否刷色
	 *              "begin_time": "2015-12-02", 节日开始时间
	 *              "end_time": "2015-12-10", 节日结束时间
	 *              "status": "100", 状态
	 *          }
	 *      ]
	 * }
	 */
	public function itemDetail($id, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '7.0.0.0', $cuid = '', $foreign_access = false){
		$out = array();
		
		$festModel = IoCload('models\\FestivalModel');
		$festModel->cache_ttl = $this->intCacheExpired;
        $res = $festModel->fetchFestivalResult((int)$id, 0, 0);
        
        if(!empty($res)){
            $festFilterModel = Iocload("models\\FestivalFilter");
            $festFilterModel->cache = $this->cache;
			$festFilterModel->cache_ttl = $this->intCacheExpired;
            if($festFilterModel->getFestivalFilter((int)$res['filter_id'])) {
            	$unset_arr = array('filter_id','status');
				$this->unsetItems($res,$unset_arr);
            	$out['data'] = $res;
            }
        }
        
        return $out;
	}

	/**
	 *
	 * 节日列表获取
	 *
	 * @route({"GET","/list"})
	 * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
	 * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "list": [
	 *          {
	 *              "id": 9, 识别号
	 *              "name": "春节", 节日名称
	 *              "img_logo": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:Y6mI2%2FNyMM6jx%2Bmnxap0AnW8Hhg%3D",
	 *              "img_logo_480": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_480.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:4HaPlyvXp3IT%2FsBvA2gGEhCM7wQ%3D",
	 *              "img_logo_720": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_720.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:KzA7UsmG4%2B4JFNGxPDz0a35NLy8%3D",
	 *              "img_logo_2x": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_2x.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:wYV9z6Hf5wmk7V6fCD2Xk9LLuvY%3D",
	 *              "img_logo_3x": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_3x.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:ZVeV3PAKIoGQ0ABXP7eLl53lF9g%3D",					            
	 *              "is_color_wash": 1, 是否刷色
	 *              "begin_time": "2015-12-02", 节日开始时间
	 *              "end_time": "2015-12-10", 节日结束时间
	 *              "status": "100", 状态
	 *          }
	 *      ]
	 * }
	 */
	public function getList($sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '7.0.0.0', $cuid = '', $foreign_access = false){
		$out = array('list' => array());
		
		$sf = abs(intval($sf));
    	$num = abs(intval($num));
    	if ($num < 1) {
    		$num = self::GENERAL_PAGE_SIZE;
    	}
    	elseif ($num > self::MAX_PAGE_SIZE){
    		$num = self::MAX_PAGE_SIZE;
    	}
		
		$festModel = IoCload('models\\FestivalModel');
		$festModel->cache_ttl = $this->intCacheExpired;
		$res = $festModel->fetchFestivalResult('', $sf, $num);
		
		if(!empty($res)){
			$festFilterModel = Iocload("models\\FestivalFilter");
			$festFilterModel->cache = $this->cache;
			$festFilterModel->cache_ttl = $this->intCacheExpired;
			
			foreach($res as $val){
				if($festFilterModel->getFestivalFilter((int)$val['filter_id'])) {
					$unset_arr = array('filter_id','status');
					$this->unsetItems($val,$unset_arr);
					$out['list'][] = $val;
				}
            }
        }
        
        return $out;
	}
        
    /**
	 *
	 * 节日搜索
	 *
	 * @desc 根据节日名称搜索结果
	 * @route({"GET", "/search"})
	 * @param({"k", "$._GET.k"}) string $k 搜索关键词
	 * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
	 * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "list": [
	 *          {
	 *              "id": 9, 识别号
	 *              "name": "春节", 节日名称
	 *              "img_logo": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:Y6mI2%2FNyMM6jx%2Bmnxap0AnW8Hhg%3D",
	 *              "img_logo_480": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_480.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:4HaPlyvXp3IT%2FsBvA2gGEhCM7wQ%3D",
	 *              "img_logo_720": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_720.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:KzA7UsmG4%2B4JFNGxPDz0a35NLy8%3D",
	 *              "img_logo_2x": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_2x.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:wYV9z6Hf5wmk7V6fCD2Xk9LLuvY%3D",
	 *              "img_logo_3x": "http://bs.baidu.com/emot-pack-test/%2Ffestival_logologo_3x.pic?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:ZVeV3PAKIoGQ0ABXP7eLl53lF9g%3D",					            
	 *              "is_color_wash": 1, 是否刷色
	 *              "begin_time": "2015-12-02", 节日开始时间
	 *              "end_time": "2015-12-10", 节日结束时间
	 *              "status": "100", 状态
	 *          }
	 *      ]
	 * }
	 */
	public function search($k,$sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '7.0.0.0', $cuid = '', $foreign_access = false){
		$out = array();
		
		$sf = abs(intval($sf));
    	$num = abs(intval($num));
    	if ($num < 1) {
    		$num = self::GENERAL_PAGE_SIZE;
    	}
    	elseif ($num > self::MAX_PAGE_SIZE){
    		$num = self::MAX_PAGE_SIZE;
    	}
		
		$festModel = IoCload('models\\FestivalModel');
		$festModel->cache_ttl = $this->intCacheExpired;
		$res = $festModel->fetchFestivalSearch($k, $sf, $num);
		
		if(!empty($res)){
			$festFilterModel = Iocload("models\\FestivalFilter");
			$festFilterModel->cache = $this->cache;
			$festFilterModel->cache_ttl = $this->intCacheExpired;
			foreach($res as $val){
				if($festFilterModel->getFestivalFilter((int)$val['filter_id'])) {
					$unset_arr = array('filter_id','status');
					$this->unsetItems($val,$unset_arr);
					$out['list'][] = $val;
				}
            }
        }
        
        return $out;
	}
	
	/**
     * @desc 清除不需要的字段
     * @param array $res 数据数组
     * @param array $unset_arr 需要清除的字段数组
     */
	private function unsetItems(&$res, $unset_arr = array()){
		if(is_array($res) && !empty($res) && is_array($unset_arr) && !empty($unset_arr)){
			foreach($unset_arr as $val){
    			if(array_key_exists($val,$res)){
    				unset($res[$val]);
    			}
    		}
		}
	}
}
