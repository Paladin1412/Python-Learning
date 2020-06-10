<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use tinyESB\util\Verify;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

/**
 * 字体下发
 *
 * @author lipengcheng02
 * @path("/fonts/")
 */
class Fonts
{
    //每页显示最多条数
    const MAX_PAGE_SIZE = 200;
    
    //默认每页显示条数
    const GENERAL_PAGE_SIZE = 12;
    	
        
    /** @property 内部缓存key前缀 */
	public $strFontCachePre;
        
	/** @property 缓存时间*/
	public $intCacheExpired;
	
	/** @property 内部缓存实例 */
	private $cache;
	
	/** @property v5 */
	private $domain_v5;
	
	/**
	 * @return db
	 */
	function getDB(){
	    return DBConn::getXdb();
	}

	
	 /**
     * @route({"GET","/list"})
     * 获取字体列表     
     * @param({"sf", "$._GET.sf"}) 请求记录的开始条目, 默认是0
     * @param({"num", "$._GET.num"}) 请求的条目数量, 默认12
     * @return({"header", "Content-Type: application/json; charset=UTF-8"}) 返回格式是json
     * @return({"body"}) 返回字体列表
     * 数据结构如下:
     * 
     */
    public function getList($sf=0, $num=12){
        $sf = abs(intval($sf));
        $num = abs(intval($num));
        if($num < 1) {
            $num = self::GENERAL_PAGE_SIZE;
        }
        elseif ($num > self::MAX_PAGE_SIZE){
            $num = self::MAX_PAGE_SIZE;
        }
        $cache_key = $this->strFontCachePre.md5($sf.$num);
        $cache_get_status = null;
        $res = $this->cache->get($cache_key, $cache_get_status);
        if($cache_get_status === false || is_null($res)) {
            $sql  = 'select id, banner_pic, description, download_link, font_file_size, font_file_token from input_fonts where status = 100 limit %d, %d';
            $res = $this->getDB()->queryf($sql, $sf, $num);            
            foreach ($res as &$v){
                $v['id'] = intval($v['id']);
                $v['download_link'] =  $this->getTraceUrl($v['download_link']);
                $v['font_file_size'] =  number_format($v['font_file_size'] / 1048576, 2) . ' MB';                
            }
            if ($res !== false && !empty($res)) {
                $this->cache->set($cache_key, $res, $this->intCacheExpired);                
            }
            else{
                $res = array();
            }
        }
        return $res;
    }
    
  
    /**
     * @param
     * @return
     */
    private function getTraceUrl($strUrl){
        $strTraceUrl = $this->domain_v5 . 'v5/trace?url=' . urlencode($strUrl) . '&sign=' . md5($strUrl . 'iudfu(lkc#xv345y82$dsfjksa');
        return $strTraceUrl;
    }
}
