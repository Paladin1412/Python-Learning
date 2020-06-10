<?php
use ai;
use utils\DbConn;
use utils\GFunc;
use utils\Util;
use utils\AiApi;
use models\FilterModel;
/**
 *
 * ai输入法接口
 * 
 * @path("/ai/")
 */
class Ai
{
    /** @property 通知中心问候语数据key pre*/
    private $strAiGreetingsNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /**
     * @desc ai结果检索
     * @route({"POST", "/search"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function search()
    {
        $objAI = IoCload('ai\\AiBase');
        $arrApiResult = $objAI->talk();
        return $arrApiResult;
    }
    
    /**
     *
     * 通知中心问候语数据接口
     *
     * @route({"GET", "/ai_greetings_noti"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": ".....",
     *      "version": 1526352952,
     *      ]
     * }
    */
    public function getAiGreetings($intMsgVersion = 0){
        //输出格式初始化
        $out = Util::initialClass(false);
        
        $cacheKey = $this->strAiGreetingsNotiCachePre;
        $cacheKeyVersion = $cacheKey . '_version';
        
        $version = GFunc::cacheZget($cacheKeyVersion);
        $content = GFunc::cacheZget($cacheKey);
        if($content === false){
            $content = GFunc::getRalContent('ai_greetings');
            
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
        
        $arrList = array();
        //数据处理
        if(!empty($content)) {
            $now = time();
            
            foreach($content as $key => $val) {
                //数据还未到达生效时间
                if(isset($val['start_time']) && intval($val['start_time']) > $now) {
                    continue;
                }
                
                //数据过期时间设置过并且已过期
                if(isset($val['expired_time']) && intval($val['expired_time']) > 0 && intval($val['expired_time']) <= $now) {
                    continue;
                }
                
                //入口没设置或包名没有填写或者内容没写
                if(trim($val['entrance']) === '' || (count($val['package_name']) == 1 && trim($val['package_name'][0]) === '') || (count($val['content']) == 1 && trim($val['content'][0]) === '')) {
                    continue;
                }
                
                //临时数组
                $arrTemp = array();
                //入口
                $arrTemp['entrance'] = $val['entrance'];
                //包名
                $arrTemp['package_name'] = Util::packageToArray($val['package_name']);
                
                //数据生效时间
                $arrTemp['start_time'] = intval($val['start_time']);
                
                //数据过期时间
                $arrTemp['expired_time'] = intval($val['expired_time']);
                
                switch(intval($val['set_time']['time_type'])) {
                    //设置时间段
                    case 1:
                        //开始时间
                        $arrTemp['begin_time'] = intval($val['set_time']['time']['begin_time']);
                        //结束时间
                        $arrTemp['end_time'] = intval($val['set_time']['time']['end_time']);
                        $arrTemp['active_week'] = '';
                        $arrTemp['begin_hour'] = 0;
                        $arrTemp['end_hour'] = 0;
                        break;
                    //设置重复时间
                    case 2:
                        $arrTemp['begin_time'] = 0;
                        $arrTemp['end_time'] = 0;
                        //有效星期几
                        $arrTemp['active_week'] = trim($val['set_time']['repeated_time']['active_week']);
                        //有效开始小时数
                        $arrTemp['begin_hour'] = intval($val['set_time']['repeated_time']['begin_hour']);
                        //有效结束小时数
                        $arrTemp['end_hour'] = intval($val['set_time']['repeated_time']['end_hour']);
                        break;
                    default:
                        $arrTemp['begin_time'] = 0;
                        $arrTemp['end_time'] = 0;
                        $arrTemp['active_week'] = '';
                        $arrTemp['begin_hour'] = 0;
                        $arrTemp['end_hour'] = 0;
                }
                //内容词条
                $arrTemp['content'] = Util::packageToArray($val['content']);
                //优先级
                $arrTemp['priority'] = intval($val['priority']);
                
                $arrList[] = $arrTemp;
            }
        }
        
        
        if(!empty($arrList)){
            $out['data'] = $arrList;
        }
        
        return Util::returnValue($out,false);
    }
    
    /**
     *
     * ai输入法搜索白名单
     *
     * @route({"GET", "/ai_search_wl_noti"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": [],
     *      "version": 1526352952,
     *      ]
     * }
    */
    public function AiSearchWlNoti($intMsgVersion = 0){
        //输出格式初始化
        $rs = Util::initialClass(false);
        
        $cacheKey = 'ai_search_wl_noti';
        $cacheKeyVersion = $cacheKey . '_version';
        
        $version = GFunc::cacheZget($cacheKeyVersion);
        $content = GFunc::cacheZget($cacheKey);
        if($content === false){
            $content = GFunc::getRalContent('ai_input_search_wl');
            
            //set status, msg & version
            $rs['ecode'] = GFunc::getStatusCode();
            $rs['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());
            
            //设置缓存
            GFunc::cacheZset($cacheKey, $content, $this->intCacheExpired);
            //设置版本缓存
            GFunc::cacheZset($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $rs['version'] = intval($version);
        
        //过滤数据
        if(!empty($content)){
            $filterModel = new FilterModel();
            $content = $filterModel->getFilterByArray($content);
        }
        $arrList = array();
        //数据处理
        $type=-1;//类型判断 0安卓 1 iOS
        if(!empty($content)) {
          foreach($content as $contentValue){
                if($type===-1){
                    $type=$contentValue['type'];
                }else{
                    if($type!==$contentValue['type']){
                        $arrList=array();
                        break;
                    }
                }
                $arrTemp=array();
                if($type===0){
                    $arrTemp['ctrid']=$contentValue['ctrid'];
                    $arrTemp['keyboard']=-1;
                    $arrTemp['return']=-1;
                }
                if($type===1){
                    $arrTemp['ctrid']=-1;
                    $arrTemp['keyboard']=$contentValue['keyboard'];
                    $arrTemp['return']=$contentValue['return'];
                }
                foreach($contentValue['package_name'] as $cv){
                    if(!isset($arrList[$cv])){
                        $arrList[$cv]=array();
                    }
                    if(!in_array($arrTemp, $arrList[$cv])){//最新修改的配置优先
                        $arrList[$cv][]=$arrTemp;
                    }
                }
           }
        }
        $arrRslist=array();
        foreach ($arrList as $key =>$value){
            $arrRslist[]=array('packageName'=>$key,'value'=>$value);
        }
        $rs['data']=$arrRslist;

        return Util::returnValue($rs,false);
    }
    
    /**
     *
     * ai输入法热词白名单
     *
     * @route({"GET", "/ai_hotwords_wl_noti"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": ".....",
     *      "version": 1526352952,
     *      ]
     * }
    */
    public function AiHotwordsWlNoti($intMsgVersion = 0){
        //输出格式初始化
        $rs = Util::initialClass();
        
        $cacheKey = 'ai_hotwords_wl_noti';
        $cacheKeyVersion = $cacheKey . '_version';
        
        $version = GFunc::cacheZget($cacheKeyVersion);
        $content = GFunc::cacheZget($cacheKey);

        if($content === false){
            $content = GFunc::getRalContent('ai_input_hotwords_wl');
            
            //set status, msg & version
            $rs['ecode'] = GFunc::getStatusCode();
            $rs['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());
            
            //设置缓存
            GFunc::cacheZset($cacheKey, $content, $this->intCacheExpired);
            //设置版本缓存
            GFunc::cacheZset($cacheKeyVersion, $version, $this->intCacheExpired);
        }
        
        $rs['version'] = intval($version);
        
        //过滤数据
        if(!empty($content)){
            $filterModel = new FilterModel();
            $content = $filterModel->getFilterByArray($content);
        }
        $arrList = array();
        //数据处理
        if(!empty($content)) {
          foreach($content as $contentValue){//最新修改的配置优先，新配置会覆盖老的相同框
                $arrTempHotWords= explode("\n", $contentValue['hotwords']);
                foreach($arrTempHotWords as $athw){
                    if($athw!==''){
                        $temp= explode('|', $athw);
                        if(isset($temp[0]) && isset($temp[1]) &&!in_array(array('word'=>$temp[0],'url'=>$temp[1]), $arrList)){
                            $arrList[]=array('word'=>$temp[0],'url'=>$temp[1]);
                        }
                    }
                }
           }
        }

        $rs['data']=array();
        $rs['data']['list'] = $arrList;

        return Util::returnValue($rs);
    }

    /**
     *
     * @desc AiBoard 默认面板
     *
     * @route({"GET", "/ai_board_default_panel"})
     * @param({"version", "$._GET.message_version"}) 通知中心下发的时间戳
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function aiBoardDefaultPanel($version = 0) {
        $model = IoCload(\models\AiBoardDefaultPanel::class);
        //输出格式初始化
        $rs = Util::initialClass();
        $data = $model->getResource($version);
        $rs['data'] = empty($data) ? new stdClass() : $data;

        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs);
    }

    /**
     *
     * @desc ai 探索版下载
     *
     * @route({"GET", "/explore_version_download"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function exploreVersionDownload() {
        $mobile_detect = new \models\MobileDetect();
        if ($mobile_detect->is('iOS')) {
            header('Location: https://itunes.apple.com/cn/app/id1437373030?mt=8');
        } elseif ($mobile_detect->is('AndroidOS')) {
            header('Location: https://srf.baidu.com');
        } else {
            header('Location: http://srf.baidu.com/android-wap/index.php');
        }
    }
}