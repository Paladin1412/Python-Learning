<?php
use utils\GFunc;
use utils\Util;
use tinyESB\util\Logger;

/***************************************************************************
*
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

/**
*
* ss
* 说明：搜索前的推荐词接口
*
* @author zhoubin05
* @path("/ss/")
*/
class SearchSug{


    /**
     * 一次获取大搜热词的个数
     * @var unknown
     */
    const SEARCH_SUG_KEYWORDS_LENGTH = 20;

    private $cache; 
     
    /** v5 res 地址 **/
    private $strResDomain;
    
    function __construct() {
        $this->cache =  GFunc::getCacheInstance();
        $this->strResDomain = Util::randStr(GFunc::getGlobalConf('domain_res'));
    }
    
     /**
      * @desc 获取大搜热词，缓存不过期，但是一小时更新一次
      * @route({"GET", "/list"})    
      * @return({"header", "Content-Type: application/json; charset=UTF-8"})
      * @return({"body"}) 
      */
    public function getSearchSugList(){   
           
        return $this->_getPmData();
    }
    
    /**
     * 获取pm在res后台配置的数据并过滤
     * @return
     */
    private function _getPmData() {

        $searchSugModel = IoCload("models\\SearchSugRecommendModel");
        $cacheData = $searchSugModel->cache_getRecommend();
        
        $filterConditionModel = IoCload("models\\FilterConditionModel");
        $cacheFilter = $filterConditionModel->cache_getFilter();
         
         
        //整理过滤条件，把过滤id作为下标创建一个新的过滤条件数组
        $nfc = array();
        foreach ($cacheFilter as $v) {
            $nfc[$v['id']] = json_decode($v['condition_filter'], true);
        }
        
       
        $res = array();
 
        foreach ($cacheData as $k => $v)
        {
            $val = $this->_arrFilter($v, $nfc, true);
            if($val !== null) {
                $res = $v;
                break; //只获取第一条有效数据
            }
        }
        
        //Edit by fanwenli on 2018-11-25, hotword of search content could be got from duomo
        if(!empty($res)) {
            $res = $this->getResourceFromDuomo($res);   
        }
        
        
        $top_words = $this->_getTopWords();
     
        
        $tp =  array(
            'content' => array(
                'name'=>"内容" , 
                'qt' => 1,
            ), 
            'pic' => array(
                'name' => '图片', 
                'qt' => 2,
            ) , 
            'emoji' => array(
                'name' => '表情',
                'qt' => 3,
            ),
            //Edit by fanwenli on 2017-08-25, add type of video
            //Edit by fanwenli on 2019-04-26, video change to coupon
            'video' => array(
                //'name' => '视频',
                'name' => '折扣券',
                'qt' => 4,
            ),
        );
     
        //获取垂直分类tag
        $svcModel = IoCload("models\\SearchVerticalCateModel");
        $svc = $svcModel->cache_getTags();

        $res_svc = array();
   
        //根据过滤条件过滤
        foreach ($svc as $k => $v) {
            $val = $this->_arrFilter($v, $nfc, true);
          
            if($val !== null) {
                
                if(!isset($res_svc[$v['qt']])) {
                    $res_svc[$v['qt']] = array();
                } 
                
                if(count($res_svc[$v['qt']]) >= 10) {
                    //每类搜索的垂类最多下发10个
                    continue;
                }
                
                $res_svc[$v['qt']][] = array(
                    'svc_id' => $v['id'],
                    'prefix' => $v['prefix'],
                    'prefix_full' => '#' . $v['prefix'] . '#',
                    'hint' =>  $v['hint'] ,
                    'sug_id'=> $v['sug_id'],
                    'icon' => $v['icon'],
                    'pos_1' => $v['pos1'],
                    'pos_2' => $v['pos2'],
                    'pos_3' => $v['pos3'],
                    'pos_4' => $v['pos4'],
                    'fill_data' => array(),
                );
              
            }
        }
      
        //获取特殊分类定位关键词
        $ssqlModel = IoCload("models\\SearchSpecQueryLocModel");
        $ssql = $ssqlModel->cache_getQuerys();
      
        $res_ssql = array();
        
        //根据过滤条件过滤
        foreach ($ssql as $k => $v) {
            $val = $this->_arrFilter($v, $nfc, true);
            
            if($val !== null) {
                if(!isset($res_ssql[$v['qt']])) {
                    $res_ssql[$v['qt']] = array();
                }
                
                $res_ssql[$v['qt']] = array_merge($res_ssql[$v['qt']], explode(PHP_EOL, $v['querys']) );
            }
        }
        
      
        foreach ($tp  as $k => $v) {
            $pm_data_prefix = substr($k, 0, 1);
            $pos_1_k = $pm_data_prefix . 'pos1';
            $pos_2_k = $pm_data_prefix . 'pos2';
            $pos_3_k = $pm_data_prefix . 'pos3';
            $pos_4_k = $pm_data_prefix . 'pos4';
            
            //Edit by fanwenli on 2019-04-26, video change to coupon
            if($v['qt'] == 4) {
                $v['qt'] = 6;
            }
            
            $arrData[] = array(
                'tab_name' => $v['name'],
                'pm_data' => array(
                    'pos_1' => isset($res[$pos_1_k]) ? $res[$pos_1_k]: '',
                    'pos_2' => isset($res[$pos_2_k]) ? $res[$pos_2_k]: '',
                    'pos_3' => isset($res[$pos_3_k]) ? $res[$pos_3_k]: '',
                    'pos_4' => isset($res[$pos_4_k]) ? $res[$pos_4_k]: '',
                ),
                'qt' => $v['qt'],
                'fill_data' => $k == 'content' ?  $this->_getTopWords(true) : array(),
                'tags' => isset($res_svc[$v['qt']]) ? $res_svc[$v['qt']] : array(),
                'ssql' =>  isset($res_ssql[$v['qt']]) ? $res_ssql[$v['qt']] : array(),
            );
        }
        
        
        return $arrData;
    }
    
    /**
     * 
     * @param unknown $v   需要验证的数据
     * @param unknown $all_conds    过滤条件总集合
     * @param string $allow_empty_filter_val  是否允许数据的过滤条件为空值
     * @param string $colum_name 过滤条件在数据中的字段名称是
     * @return NULL|unknown
     */
    public function _arrFilter($v, $all_conds,  $allow_empty_filter_val = false, $colum_name = 'filter_id') {
        
        $conditionFilter = IoCload("utils\\ConditionFilter");
        
        $nfc = $all_conds;
        
        $cf = array();
         
        //过滤条件强验证，空的过滤条件视为无效数据，多个过滤条件中有空值过滤条件也视为无效数据（PM定）
        if(!empty($v[$colum_name])) {
        
            if(is_array($v[$colum_name])) { //支持多个过滤id
                foreach ($v[$colum_name] as $fv) {
                    if(isset($nfc[$fv])) {
                        $cf = array_merge($cf,$nfc[$fv]);
                    }else {
                        return null;
                    }
        
                }
            }else { //单个过滤id
                if(isset($nfc[$v[$colum_name]]) ) {
                    $cf = $nfc[$v[$colum_name]];
                }else {
                    return null;
                }
        
            }
        
        
        } else {
            if(!$allow_empty_filter_val) {
                return null;
            }
        }
         
        
        $new_cf = array();
        foreach ($cf as $ck => $cv) {
        
            if($cv['operator'] == 'nin' || $cv['operator'] == 'in') {
                //尝试解析换行分割的多个值则
                $value_ary = array();
                foreach ($cv['array'] as $cck => $ccv) {
                    $val = explode(PHP_EOL, $ccv);
                    foreach ($val as $valc => $valv) {
                        array_push($value_ary, $valv);
                    }
                }
                 
                $cv['array'] = $value_ary;
            }
            array_push($new_cf, $cv);
        }
         
        //提取filter_condition_id,并过滤数据
        if($conditionFilter->filter($new_cf))
        {
            return $v;
        }
        
        return null;
    }
    
    
    
    
    /**
     * 获取大搜热词缓存数据
     * @param string $only_keyword
     * @return multitype:|multitype:unknown |Ambigous <boolean, \utils\mixed, mix, unknown>
     */
    public function _getTopWords($only_keyword = false) {
        
        $topbaiduwords = 'ime_v5_top_baidu_words_data_for_search_sug';
        $sugData = GFunc::cacheZget($topbaiduwords);
        if(false === $sugData){
            $sugData = $this->_getTopbaiduHotwords();
            //存到缓存一定有数据
            if(is_array($sugData['keywords']) && !empty($sugData['keywords'])){
                GFunc::cacheZset($topbaiduwords, $sugData['keywords'], 3600);
                $sugData = $sugData['keywords'];
            } else {
                return array();
            }
        }
        
        if($only_keyword) {
            $tmp =  array();
            foreach ($sugData as $k => $v) {
                $tmp[] = $v['keyword'];
            }
            return $tmp;
        }
        
        return $sugData;
    }

    /**
     * 获取大搜热词
     * @return boolean|mix   
     */
    private function _getTopbaiduHotwords(){
        //换个热词数量多的接口
        //发起ral请求
        //ral_set_pathinfo('/api/hotspot');
        //ral_set_querystring('b=8&s=11&pic=1');
        $user = 'input';
        $pass = 'QfwFcR98CzHWLh24';
        $time = time();
        $params = array('user'=> $user,'time'=>$time);
        $token = md5(http_build_query($params).$pass);
        $params['token'] = $token;
        $params['b'] = '1';
        $query = http_build_query($params);

        ral_set_pathinfo('/api/buzz');
        ral_set_querystring($query);


        $search_sug = ral("top_baidu_service", 'get', null, rand());
        $hotwords = array();
        $hotwords['keywords'] = array_slice($search_sug['keywords'], 0, self::SEARCH_SUG_KEYWORDS_LENGTH);
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
    
    /**
     * 内容搜索的sug从51biaoqing补全
     * @param array $arr 原始配置字段
     * @param int $limit 上限个数
     * @return array
     */
    private function getResourceFrom51($arr = array(), $limit = 4) {
        //字段key数组
        $words_id_arr = array();
        
        //find column in arr
        //Edit by fanwenli on 2018-08-24, change judge from isset to limit
        //while(true) {
        for($i = 1;$i <= $limit;$i++) {
            $key = 'epos'.$i;
            if(!isset($arr[$key]) || trim($arr[$key]) == '') {
                $words_id_arr[] = $key;
            }
        }
        
        //get words arr number
        $words_id_count = count($words_id_arr);
        
        if($words_id_count > 0) {
            $searchSugModel = IoCload("models\\SearchSugRecommendModel");
            $cacheData = $searchSugModel->cache_getRecommendFrom51();
               
            if(is_array($cacheData) && !empty($cacheData)){
                //get random from cache data
                $data_rand_key = array_rand($cacheData,$words_id_count);
                
                //set new value in arr
                if($words_id_count == 1) {
                    $arr[$words_id_arr[0]] = $cacheData[$data_rand_key];
                } else {
                    foreach($data_rand_key as $rand_key => $rand_val) {
                        $arr[$words_id_arr[$rand_key]] = $cacheData[$rand_val];
                    }
                }
            }
        }
        
        return $arr;
    }
    
    /**
      * @desc 获取ai表情大搜热词，缓存不过期，但是一小时更新一次
      * @route({"GET", "/ai_emoji_list"})    
      * @return({"header", "Content-Type: application/json; charset=UTF-8"})
      * @return({"body"}) 
      */
    public function getAiEmojiSearchSugList(){   
        //输出格式初始化
        $out = Util::initialClass();
        
        $searchSugModel = IoCload("models\\SearchSugRecommendModel");
        $cacheData = $searchSugModel->cache_getRecommend();
        
        $filterConditionModel = IoCload("models\\FilterConditionModel");
        $cacheFilter = $filterConditionModel->cache_getFilter();
        
        //整理过滤条件，把过滤id作为下标创建一个新的过滤条件数组
        $nfc = array();
        foreach ($cacheFilter as $v) {
            $nfc[$v['id']] = json_decode($v['condition_filter'], true);
        }
        
       
        $res = array();
 
        foreach ($cacheData as $k => $v)
        {
            $val = $this->_arrFilter($v, $nfc, true);
            if($val !== null) {
                $res = $v;
                break; //只获取第一条有效数据
            }
        }
        
        //get 10 result
        $res = $this->getResourceFromDuomo($res, 10);
        
        if(!empty($res)) {
            $arrEmoji = array();
            foreach($res as $key => $val) {
                //search emoji hotwords position
                if(preg_match('/^epos\d+$/i',$key,$arr)) {
                    $arrEmoji['emoji'][] = trim($val);
                }
            }
            
            $out['data'] = $arrEmoji;
        }
        
        return Util::returnValue($out);
    }
    
    /**
     * 内容搜索的sug从多模补全
     * @param array $arr 原始配置字段
     * @param int $limit 上限个数
     * @return array
     */
    private function getResourceFromDuomo($arr = array(), $limit = 4) {
        //字段key数组
        $words_id_arr = array();
        
        //find column in arr
        for($i = 1;$i <= $limit;$i++) {
            $key = 'epos'.$i;
            if(!isset($arr[$key]) || trim($arr[$key]) == '') {
                $words_id_arr[] = $key;
            }
        }
        
        //get words arr number
        $words_id_count = count($words_id_arr);
        
        if($words_id_count > 0) {
            $searchSugModel = IoCload("models\\SearchSugRecommendModel");
            $cacheData = $searchSugModel->cache_getRecommendFromDuomo(10);
               
            if(is_array($cacheData) && !empty($cacheData)){
                //get random from cache data
                $data_rand_key = array_rand($cacheData,$words_id_count);
                
                //set new value in arr
                if($words_id_count == 1) {
                    $arr[$words_id_arr[0]] = $cacheData[$data_rand_key];
                } else {
                    foreach($data_rand_key as $rand_key => $rand_val) {
                        $arr[$words_id_arr[$rand_key]] = $cacheData[$rand_val];
                    }
                }
            }
        }
        
        return $arr;
    }
}
