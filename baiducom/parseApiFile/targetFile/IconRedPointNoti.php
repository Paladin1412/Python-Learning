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

/**
 * 红点/熊头开关
 *
 * @author fanwenli
 * @path("/icon_red_point_noti/")
 */
class IconRedPointNoti
{	
    /** @property 缓存key前缀 */
    private $strIconRedPointNotiCachePre;
    
    /** @property 内部缓存时长 */
    public $intCacheExpired;
	
	/**
    *
    * 红点/熊头开关
    *
    * @route({"GET", "/info"})
    * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
    * @throws({"BadRequest", "status", "400 Bad request"})
    * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"}){
    *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
    *      ]
    * }
    */
    public function getIconRedPointContent($intMsgVersion = 0){
        //$out = array('data' => array());
        
        //输出格式初始化
        $out = Util::initialClass(false);
        
        $cacheKey = $this->strIconRedPointNotiCachePre;
        
        $cacheKeyVersion = $cacheKey . '_version';
        
        $version = GFunc::cacheZget($cacheKeyVersion);
        $content = GFunc::cacheZget($cacheKey);
        if($content === false){
            $content = GFunc::getRalContent('icon_red_point');
            
            //set status, msg & version
            $out['ecode'] = GFunc::getStatusCode();
            $out['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());
            
            //设置缓存
            GFunc::cacheZset($cacheKey, $content, $this->intCacheExpired);
            
            //设置版本缓存
            GFunc::cacheZset($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $out['version'] = intval($version);

        //过滤数据
        if(!empty($content)){
            $filterModel = new FilterModel();
            $content = $filterModel->getFilterByArray($content);
        }
        
        $tips_icon = array(0 => array(), 1 => array(), 2 => array());
        $rpl_tmp =  array();
        if(is_array($content)) {
            foreach($content as $k => $v) {
                if($v['version'] > $rpl_tmp['version'] && $v['version'] > 0) {
                    $rpl_tmp = $v;
                }
            }
            
            if(!empty($rpl_tmp)) {
                $tips_icon[0] = $rpl_tmp['no_point'];
                $tips_icon[1] = $rpl_tmp['point'];
                $tips_icon[2] = $rpl_tmp['bear'];
                sort($tips_icon[0]);
                sort($tips_icon[1]);
                sort($tips_icon[2]);
            }
        }
        
        $out['data'] = $tips_icon;
        
        return Util::returnValue($out,false);
    }
}
