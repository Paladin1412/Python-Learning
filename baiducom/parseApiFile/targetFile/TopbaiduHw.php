<?php
/***************************************************************************
*
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

/**
*
* topbaiduHw
* 说明：获取大搜热词接口
*
* @author lipengcheng02
* @path("/topbaiduhw/")
*/
class TopbaiduHw{

	/**
	 * 大搜缓存key前缀
	 * @var string
	 */
	const TOPBAIDU_HOTWORDS_CACHE_KEY = 'ime_v5_topbaidu_hotwords';

	/**
	 * 大搜缓存时间标记key前缀
	 * @var unknown
	 */
	const TOPBAIDU_HOTWORDS_CACHE_TIMESTAMP_KEY = 'ime_v5_topbaidu_hotwords_cache_expire_time';

	/**
	 * 一次获取大搜热词的个数
	 * @var unknown
	 */
	const TOPBAIDU_HOTWORDS_KEYWORDS_LENGTH = 20;

	/** @property*/
	private $cache;	
	 
	 /**
	  * @desc 获取大搜热词，缓存不过期，但是一小时更新一次
	  * @route({"GET", "/list"})    
	  * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	  * @return({"body"}) 
	  */
	public function getTopbaiduHotwordsList(){		
		$strTmCacheKey = self::TOPBAIDU_HOTWORDS_CACHE_TIMESTAMP_KEY;		
		$strTimestamp = $this->cacheGet($strTmCacheKey); 	   
		$strCacheKey = self::TOPBAIDU_HOTWORDS_CACHE_KEY;
		$strNow = time();
		//$strNow = date("YmdH");			 
		if(false === $strTimestamp){
			$data = $this->getTopbaiduHotwords();
			//存到缓存一定有数据			
			if(is_array($data['keywords']) && !empty($data['keywords'])){ 
				$this->cache->set($strCacheKey, $data);  
				$this->cache->set($strTmCacheKey, $strNow);
				//客户端要求毫秒
				$data['update_time'] = strval($strNow * 1000);
				return $data;
			} else {
				return false; 
			}
		}		
		//被动永久缓存超过一小时就强制更新缓存
		if(!empty($strTimestamp) && floor($strTimestamp/3600) < floor($strNow/3600)){			
			$data = $this->getTopbaiduHotwords();
			//始终更新过期时间，保证1小时内只有1次远端请求
			$this->cache->set($strTmCacheKey, $strNow);			
			if(is_array($data['keywords']) && !empty($data['keywords'])){
				$this->cache->set($strCacheKey, $data);
				//客户端要求毫秒
				$data['update_time'] = strval($strNow * 1000);
				return $data;
			} 
		}		
		$res = $this->cacheGet($strCacheKey);
		//客户端要求毫秒
		$res['update_time'] = strval($strTimestamp * 1000);		
		return $res;		 
	}

	/**
	 * 获取大搜热词
	 * @return boolean|mix	 
	 */
	private function getTopbaiduHotwords(){
// 		//用户名密码
// 		$user = 'caoym6';
// 		$pass = 'QXNZq7KUB0rbnc2J';	
// 		$time = time();
// 		$params = [];
// 		$params['user'] = $user;
// 		$params['time'] = $time;
// 		$token = md5(http_build_query($params).$pass);  
// 		$params['token'] = $token;
// 		//榜单ID
// 		$params['b'] = 1;                  
// 		$query = http_build_query($params);
// 		//发起ral请求
// 		ral_set_pathinfo('/api/buzz');
// 		ral_set_querystring($query);
        
        //换个热词数量多的接口
	    //发起ral请求
	    ral_set_pathinfo('/api/hotspot');
	    ral_set_querystring('b=8&s=11&pic=1');
		$topbaidu_hotwords = ral("top_baidu_service", 'get', null, rand());
		$hotwords = array();
		$hotwords['keywords'] = array_slice($topbaidu_hotwords['keywords'], 0, self::TOPBAIDU_HOTWORDS_KEYWORDS_LENGTH);
		return $hotwords;		
	}
	
	/**
	 * 获取缓存数据
	 * @param string $cache_key
	 * @return boolean|mix
	 */
	private function cacheGet($cache_key){
	    $cache_get_status = false;
	    $result = $this->cache->get($cache_key, $cache_get_status);
	    if(false === $cache_get_status || null === $result ) {
	        
	        return false;  //获取错误或者无此缓存
	    }
	    return $result;
	}
}
