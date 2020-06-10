<?php
use utils\CustLog;
use utils\ErrorCode;
use utils\Util;
use utils\GFunc;

/**
 * 客户端9.0版本以后搜索相关接口
 * @author fanwenli
 * @desc 搜索类
 * @path("/searchV2/")
 */
class SearchV2 {
    /** @property 皮肤标签缓存 */
    private $searchTagCache;
    
    /** @property 皮肤预置文案缓存 */
    private $searchHintCache;
    
    /** @property 搜图首页推荐缓存 */
    private $searchIndexRecommendCache;
    
    /**
     * @route({"GET","/tag"})
     *
     * 皮肤标签列表
     * 
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function tag() {
        $ret = Util::initialClass(false);
        
        $arrTag = $this->getAllTag();
        
        //过滤
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $arrTag = $conditionFilter->getFilterConditionFromDB($arrTag);
        
        //set array in theme & color
        $ret['data'] = array(
            'recommend' => array(),
            'color' => array(),
        );
        
        if(!empty($arrTag)) {
            foreach($arrTag as $val) {
                //recommend: type is 1 color: type is 2
                $type = '';
                switch($val['type']) {
                    case 1:
                        $type = 'recommend';
                        break;
                    case 2:
                        $type = 'color';
                        break;
                }
                
                if($type != '') {
                    unset($val['type']);
                    
                    if($val['image'] != '') {
                        $arrBosPath = Util::addBosDomain(array($val['image']));
                        $val['image'] = $arrBosPath[0];
                    }
                    
                    $ret['data'][$type][] = $val;
                }
            }
        }
        
        return Util::returnValue($ret, false);
    }
    
    /**
     * get all content
     * 
     * 
     * return array
     * 
     */
    public function getAllTag() {
        $arrConf = GFunc::getConf('SearchV2');
        $strCacheKey = $arrConf['properties']['searchTagCache'];
        
        $objRedis = IoCload('utils\\KsarchRedis');
        $arrTag = GFunc::cacheZgetOrigin($objRedis, $strCacheKey);
        if($arrTag === false) {
            $objModel = IoCload("models\\SearchModel");
            $arrTag = $objModel->getTag();
            if (empty($arrTag)) {
                return $arrTag;
            }
            
            //set cache content and cache time is 15mins
            GFunc::cacheZsetOrigin($objRedis, $strCacheKey, $arrTag, GFunc::getCacheTime('15mins'));
        }
        
        return $arrTag;
    }
    
    /**
     * @route({"GET","/hint"})
     *
     * 皮肤搜索预置文案列表
     * 
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function hint() {
        $ret = Util::initialClass(false);
        
        $strCacheKey = $this->searchHintCache;
        $arrHint = GFunc::cacheZget($strCacheKey);
        if($arrHint === false) {
            $objModel = IoCload("models\\SearchModel");
            $arrHint = $objModel->getHint();
            
            //set cache content and cache time is 15mins
            GFunc::cacheZset($strCacheKey, $arrHint, GFunc::getCacheTime('15mins'));
        }
        
        //过滤
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $arrHint = $conditionFilter->getFilterConditionFromDB($arrHint);
        
        //set array in home & search
        $ret['data'] = array(
            'home' => '',
            'search' => '',
        );
        
        if(!empty($arrHint)) {
            foreach($arrHint as $val) {
                //only get newest item
                if(isset($val['home']) && isset($val['search'])) {
                    $ret['data']['home'] = trim($val['home']);
                    $ret['data']['search'] = trim($val['search']);
                    break;
                }
            }
        }
        
        return Util::returnValue($ret, false);
    }
    
    /**
     * @route({"GET","/index_recommend"})
     *
     * 搜图首页搜索推荐
     * 
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function indexRecommend() {
        $ret = Util::initialClass(false);
        
        $strCacheKey = $this->searchIndexRecommendCache;
        $arrResult = GFunc::cacheZget($strCacheKey);
        if($arrResult === false) {
            $objModel = IoCload("models\\SearchModel");
            $arrResult = $objModel->getIndexRecommend();
            
            //set cache content and cache time is 15mins
            GFunc::cacheZset($strCacheKey, $arrResult, GFunc::getCacheTime('15mins'));
        }
        
        //set array
        $ret['data'] = array();
        
        if(!empty($arrResult)) {
            foreach($arrResult as $val) {
                if($val['background'] != '') {
                    $arrBosPath = Util::addBosDomain(array($val['background']));
                    $val['background'] = $arrBosPath[0];
                }
                
                $ret['data'][] = array(
                    'id' => $val['id'],
                    'document' => trim($val['name']),
                    'image' => trim($val['background']),
                );
            }
        }
        
        return Util::returnValue($ret, false);
    }
}
