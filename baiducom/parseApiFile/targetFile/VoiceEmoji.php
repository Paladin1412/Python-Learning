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
use utils\GFunc;
use models\VoiceEmojiModel;

/**
 * 世界大会通过关键词获取图片接口
 *
 * @author fanwenli
 * @path("/voiceemoji/")
 */
class VoiceEmoji
{	
        
    //每页显示最多条数
	const MAX_PAGE_SIZE = 200;
	
	//默认每页显示条数
	const GENERAL_PAGE_SIZE = 6;
	
	/**
     *
     * memcache 运营活动列表 key
     * @var string
     */
    const CACHE_VOICE_EMOJI_KEYWORD_LIST_KEY = 'ime_api_v5_voice_emoji_list';
    
    
    /** @property 内部缓存默认过期时间(单位: 秒) */
    private $intCacheExpired;
    
    
    /**
	 *
	 * 详情获取
	 *
	 * @route({"POST", "/list"})
	 * @param({"strKeywords","$._POST.keywords"}) 客户端上传所需下发图片所属关键词id的json数组
	 * @param({"sf","$._POST.sf"}) int $sf start_from 分页起始记录
	 * @param({"num","$._POST.num"}) int $num 分页显示每页的条数，最多200条，默认6条
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "pic_path": "http://image.l99.com/12/MjAwOTA4MDMxMjEwMjJfMjIwLjI0OS45Mi4xOThfNzM0MjUx.jpg",
	 *		"pic_type": "jpg",
	 *		"pic_width": "660",
	 *		"pic_height": "495",
     *		"thumbnail_path": "http://img5.imgtn.bdimg.com/it/u=2791865517,1490903948&fm=21&gp=0.jpg",
     *		"thumbnail_type": "jpg",
     *		"thumbnail_width": "293",
     *		"thumbnail_height": "220"
	 *      ]
	 * }
	 */
	public function getList($strKeywords, $sf = 0, $num = self::GENERAL_PAGE_SIZE){
		$out = array();
		
		$sf = abs(intval($sf));
    	$num = abs(intval($num));
    	if ($num < 1) {
    		$num = self::GENERAL_PAGE_SIZE;
    	}
    	elseif ($num > self::MAX_PAGE_SIZE){
    		$num = self::MAX_PAGE_SIZE;
    	}
		
		$keywords_arr = json_decode($strKeywords, true);
		
		if(!empty($keywords_arr)){
			//设置缓存key
			$cache_key = self::CACHE_VOICE_EMOJI_KEYWORD_LIST_KEY . '_' . implode('_', $keywords_arr) . '_' . $sf . '_' . $num;
			
			$out = GFunc::cacheGet($cache_key);
			
			if($out === false) {
            	$voiceEmojiModel = IoCload('models\\VoiceEmojiModel');
            	
            	$out = $voiceEmojiModel->fetchResult($keywords_arr, $sf, $num);
            	
            	//设置缓存
            	GFunc::cacheSet($cache_key, $out, $this->intCacheExpired);
        	}
		}
        
        return $out;
	}
}
