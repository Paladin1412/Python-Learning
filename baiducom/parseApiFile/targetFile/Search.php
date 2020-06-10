<?php
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use tinyESB\util\ClassLoader;
use utils\Util;
use card\CardFacade;
use card\CardBase;
use utils\FjLib;
use utils\GFunc;
use utils\IdcMap;
use utils\ShortAddrClient;

include_once "utils/shortaddr/IdcMap.php";
ClassLoader::addInclude(__DIR__.'/card');
ClassLoader::addInclude(__DIR__.'/noti');

/**
 *
 * 搜索服务 daixi
 * @path("/search/")
 */
class Search
{
    /**
     * 平台 ios / android
     * @var string
     */
    private $plt;

    /**
     * 输入法版本号
     * @var string
     */
    private $version;

    /**
     * 平台 ios / android code
     * @var string
     */
    private $pltcode;


    /**
     * 渠道号
     * @var string
     */
    private $cfrom;

    /**
     * @var string
     * 系统ROM版本
     */
    public $rom;


    /**
     * @var string
     * 搜索类型
     */
    private $type;

    /**
     * @var array
     * 搜索类型
     */
    private $fin_list = array();

    /**
     * @var
     */
    private $domain;


    /**
     * @var
     */
    private $tn;

    /**
     * @var
     */
    private $from;


    /**
     * @var
     */
    private $pu;

    /**
     * @var
     */
    private $user_info;


    /**
     * @var
     */
    private $params;


    /**
     * @var string
     */
    private $cuid;


    /**
     * @var
     */
    private $sid;

    /**
     * @var
     */
    private $hostid;

    private $scene; //搜索场景
    
    //Edit by fanwenli on 2017-06-06, add svc source type. E.g. 51gif
    private $svc_source_type = 0;
    
    //Edit by fanwenli on 2017-06-06, add svc source type more address
    private $svc_source_type_more = array(
        1 => 'http://bdwap.51gif.com/wap/search?w=',//51gif更多地址
    );
    
    //Edit by fanwenli on 2017-08-21, add the language which translate from
    private $tans_from;
    
    //Edit by fanwenli on 2017-08-21, add the language which translate to
    private $tans_to;

    //客户端所在应用，用于气泡表情
    private $client;
    
    /***
     * 构造函数
     * @return void
     */
    public function  __construct()
    {
        $this->plt = isset($_GET['platform']) ? Util::getPhoneOS($_GET['platform']) : '';
        $this->version = isset($_GET['version']) ? $_GET['version'] : '';
        $this->pltcode =  isset($_GET['platform']) ?$_GET['platform']: '';
        $this->cfrom =  isset($_GET['from']) ?$_GET['from']: '';
        $this->rom = isset($_GET['rom']) ? $_GET['rom'] : '';
        $this->imei = isset($_GET['cuid']) ? $_GET['cuid'] : '';
        $this->type = isset($_GET['qt']) ? intval($_GET['qt']) : 0;

        $this->cuid = isset($_GET['cuid']) ? $_GET['cuid'] : '';
        $this->sid = md5($this->cuid);
        $this->user_info['cuid'] = $this->cuid;
        $this->params = $this->user_info;

        $this->domain = GFunc::getGlobalConf('domain_v5');
        $this->scene = isset($_GET['s_search_scene']) ? $_GET['s_search_scene'] : '';
        
        //Edit by fanwenli on 2017-08-21
        $this->tans_from = isset($_GET['tans_from']) ? trim($_GET['tans_from']) : '';
        $this->tans_to = isset($_GET['tans_to']) ? trim($_GET['tans_to']) : '';

        //增加参数，用于判断使用气泡表情的app
        $this->client = isset($_GET['client']) ? trim($_GET['client']) : 0;
        $os = Util::getOs($this->pltcode);
        if("ios" === $os){
            $this->client = 0;
        }
    }

    /**
     * 获取搜索查询内容
     *@route({"POST","/data"})
     *@return({"body"}){
    "error":0
    "data":
    [
    {
    "tplid":1000,
    "tpln":"",
    "intent":""
    }
    ]

    }
     */
    public function getData()
    {
        $flag = 1;

        $searchModel  = null;
        
        $content = isset($_POST['query'])  ? $_POST['query'] : '';

        $query = "";

        $svc_id = '';

        $cache_key = 'search_data_result_'.$this->type.'_';

        if(!empty($content)) {
            $query_b64 = B64Decoder::decode($content, 0);

            if (!$query_b64)
            {
                $result["error"] = -1;
                $result["data"]  = array();
                return $result;
            }
            else
            {
                $arr = json_decode($query_b64,true);
                $query = isset($arr["query"])?$arr["query"]:"";
                $this->tn = isset($arr["tn"])?$arr["tn"]:"";
                $this->from = isset($arr["from"])?$arr["from"]:"";
                $this->pu = isset($arr["pu"])?$arr["pu"]:"";
                $this->hostid = isset($arr["hostid"])?$arr["hostid"]:"";

                //Edit by fanwenli on 2017-09-20, there could not be substred when you choose translate
                if(mb_strlen($query) > 38 && $this->type != 5) {
                    //用户搜索大于38个字符则截取前38个字符，其他舍弃
                    $query = mb_substr($query, 0, 38, 'utf-8');
                }

                $_POST["user_query"] = $query;  //用户提交的关键字
                //query归一
                $query = $this->querySimp($query);
                //繁体转简体
                $fj = new FjLib();
                $query = $fj->trans($query);



                
                //Edit by fanwenli on 2019-04-26, add filter
                $filterConditionModel = IoCload("models\\FilterConditionModel");
                $cacheFilter = $filterConditionModel->cache_getFilter();

                //整理过滤条件，把过滤id作为下标创建一个新的过滤条件数组
                $nfc = array();
                foreach ($cacheFilter as $v) {
                    $nfc[$v['id']] = json_decode($v['condition_filter'], true);
                }

                //垂直分类搜索
                if(isset($_GET['svc_id']) && intval($_GET['svc_id']) > 0)
                {
                    $get_svc_id = intval($_GET['svc_id']);
                    $svcModel = IoCload("models\\SearchVerticalCateModel");
                    $tag_ids_ary = $svcModel->cache_getTagsIds();
                    if(in_array($get_svc_id, $tag_ids_ary))
                    {
                        //Edit by fanwenli on 2019-04-26, add vertical cate filter
                        $arrSvcIdInfo = $svcModel->cache_getTagById($get_svc_id);
                        $searchSug = IoCload("SearchSug");
                        $svc_judge = $searchSug->_arrFilter($arrSvcIdInfo, $nfc, true);
                        if($svc_judge !== null) {
                            $svc_id = $get_svc_id;
                            
                            $this->params['svc_id'] = $svc_id;
                            
                            //Edit by fanwenli on 2017-05-23, add svc source type. E.g. 51gif
                            $svc_id_content = $svcModel->cache_getTagById($svc_id);
                            $this->svc_source_type = $svc_id_content['source_type'];
                        }
                    }
                }

                $cache_key .= $svc_id . '_' . crc32($_POST["user_query"]);
                
                switch($this->type) {
                    case 1:
                        $this->params['isweather'] = $this->IsWeather($query);
                        if($this->params['isweather']) {
                            $cache_key .= "_".$this->user_info['cuid'];
                        }
                        break;
                    case 3:
                        //Edit by fanwenli on 2018-11-19, set different cache with different version
                        $version = intval(Util::getVersionIntValue($this->version));
                        if($version >= 8030000) {
                            $cache_key .= '_has_mark';
                        }
                        
                        //Edit by fanwenli on 2018-12-29, set a1, i5, i6 in special cache for special content
                        if($this->pltcode == 'a1' || $this->pltcode == 'i5' || $this->pltcode == 'i6') {
                            $cache_key .= '_ai';
                        }
                        
                        //Edit by fanwenli on 2019-13-13, change duomo special content
                        //Edit by fanwenli on 2019-12-09, change platform from p-a1-3-72 & p-a1-3-66 to p-a1-3-68 & p-a1-3-69
                        if($svc_id === '' && intval($_GET['doutu']) !== 1 && intval($_GET['qipao']) !== 1 && intval($_GET['wenzi']) !== 1 && ($this->pltcode == 'a1' || $this->pltcode == 'p-a1-3-68' || $this->pltcode == 'p-a1-3-69')) {
                            $strFirstChar = substr($this->imei,0,1);
                            //Edit by fanwenli on 2019-12-09, change cuid from 0 & 2 to 8 & 9
                            if($strFirstChar === 8 || $strFirstChar === 9 || $strFirstChar === '8' || $strFirstChar === '9') {
                                $cache_key .= '_duomo_special';
                                $this->params['special_source_type'] = 6;
                            }
                        }
                        
                        $searchModel = IoCload("models\\SearchEmojiModel");
                        $emoji_type = $searchModel->getEmojiType($query, $this->params);
                        $cache_key .= '_' . $emoji_type;
                        
                        //Edit by fanwenli on 2020-03-08, add tail when emoji type is ai
                        if($emoji_type == 10) {
                            $cache_key .= '_new';
                        }

                        //增加缓存key，用于判断使用气泡表情的app
                        $cache_key .= '_' . $this->client;
                        //app透传给后端
                        $this->params['client'] = $this->client;
                        
                        //Edit by fanwenli on 2019-06-14, set emoji number is 90 when source is 2 
                        if(isset($_GET['source']) && intval($_GET['source']) === 2) {
                            $cache_key .= '_source_' . intval($_GET['source']);
                            $this->params['pic_number'] = 60;
                        }
                        
                        break;
                    case 5:
                        //Edit by fanwenli on 2017-08-21, set cache key with tans_from & tans_to
                        $cache_key .= '_' . $this->tans_from . '_' . $this->tans_to;
                        break;
                }
                
                //真正用来搜索的关键字，如垂直分类搜索中，用户提交关键字为  "#搜酒店#莫泰", 实际用来搜索的只是"莫泰",此时 $_POST["user_query"]= "#搜酒店#莫泰",  $_POST["real_query"]="莫泰"
                $_POST["real_query"] = $query;
                
                $cache_data = GFunc::cacheZget($cache_key);
                if ($cache_data)
                {
                    //Edit by fanwenli on 2018-11-21, add count for duomo
                    if($this->type == 3) {
                        $searchModel->callbackCount($cache_data['data'],$emoji_type);
                    }
                    
                    $cache_data  = $this->AddPu($this->filterResult($cache_data));
                    
                    //Edit by fanwenli on 2019-12-10, add log collect when query is black words
                    $this->blacklistLog($query);

                    return $cache_data;
                }
                
            }
        }
        else
        {
            $result["error"] = -1;
            $result["data"]  = array();
            return $result;
        }

        if (!empty($query)) {

            if($flag == 1 && $this->type != 1 && $this->type != 5 )
            {
                if(Util::securityservice($query))
                {
                    $data["error"] = 0;
                    $data["data"]  =array();
                    GFunc::cacheZset($cache_key, $data, GFunc::getCacheTime('15mins'));
                    
                    //Edit by fanwenli on 2019-12-10, add log collect when query is black words
                    $this->blacklistLog($query, 'set');
                    
                    return $data;
                }
            }


            $fin_list = array(); //最终需要显示的卡片列表
            $conditions = array();

            //垂直分类搜素
            if(!empty($svc_id)) {
                //获取垂直分类tag
                $svcModel = IoCload("models\\SearchVerticalCateModel");
                $svc = $svcModel->cache_getTags();

                foreach ($svc as $k => $v) {
                    if($v['id'] == $svc_id) {
                        $this->fin_list = explode(',', $v['tplids']);

                        $stsModel = IoCload("models\\SearchTplSourceModel");
                        $stsResult = $stsModel->cache_getTplSourceFrom();
                        foreach ($stsResult as $sk => $sv) {
                            if(in_array($sv['tplid'], $this->fin_list)) {
                                if(!isset($conditions[$sv['source_from']])) {
                                    $conditions[$sv['source_from']] = array();
                                }
                                $conditions[$sv['source_from']][] = $sv['source_key'];
                            }
                        }
                        break;
                    }
                }
            }


            $data = array();
            if (!empty($this->type)) {
                switch ($this->type) {
                    case 1:
                        $searchModel = IoCload("models\\SearchContentModel");
                        break;
                    case 2:
                        $searchModel = IoCload("models\\SearchImageModel");
                        break;
                    case 3:
                        $searchModel = IoCload("models\\SearchEmojiModel");
                        break;
                    //Edit by fanwenli on 2017-08-07, add video card model
                    case 4:
                        $searchModel = IoCload("models\\SearchVideoModel");
                        break;
                    //Edit by fanwenli on 2017-08-21, add translate model
                    case 5:
                        $searchModel = IoCload("models\\SearchTranslateModel");
                        $this->params['tans_from'] = $this->tans_from;
                        $this->params['tans_to'] = $this->tans_to;
                        break;
                    //Edit by fanwenli on 2019-04-26, add coupon card in 6
                    case 6:
                        $searchModel = IoCload("models\\SearchVerticalModel");
                        $svcModel = IoCload("models\\SearchVerticalCateModel");
                        //coupon svc array
                        $arrCouponSvc = $svcModel->cache_getTagByTplId(1030);
                        $searchSug = IoCload("SearchSug");
                        
                        foreach($arrCouponSvc as $val) {
                            /*$svc_judge = $searchSug->_arrFilter($val, $nfc, true);
                            if($svc_judge !== null) {
                                $this->params['svc_id'] = $val['id'];
                                break;
                            }*/
                            //tab不需要过滤
                            $this->params['svc_id'] = $val['id'];
                            break;
                        }
                        break;
                    default:
                        $searchModel = IoCload("models\\SearchContentModel");
                }
                
                //Edit by fanwenli on 2017-07-13, if it is emoji's search, than judge whether the system use 51 source
                if($this->type == 3) {
                    $this->params['aremoji_num'] = !empty($_GET['aremoji_num']) ? intval($_GET['aremoji_num']) : 0;
                    $result = $searchModel->search($query, $conditions, $this->params);
                } else {
                    $result = $searchModel->search($query, $conditions,$this->params);
                }

                $rdata = $result['data'];

                /*
                if(in_array(1016,$this->fin_list)) {
                    $emojiModel = IoCload("models\\SearchEmojiModel");
                    $emoji_result = $emojiModel->search($query);
                    $rdata = array_merge($rdata,$emoji_result['data']['data']);
                }
                */

                //个源数据出错情况 error['uiapi'] = true; uiapi 出错 ; 目前只有 uiapi
                $rerror = $result['error'];

                $data = $this->builder($this->type, $rdata);

                //type=1 && uiapi 有错误 不做缓存    处理完成的数据有真实卡片才缓存
                //Edit by fanwenli on 2017-07-17, if type is 3, then set content into cache if baidu resource is not empty
                //if ($this->type != 1 || ($this->type == 1 && $rerror['uiapi'] === false && $data['data'][0]['tplid'] != 3)  )
                if (($this->type != 1 && $this->type != 3) || ($this->type == 1 && $rerror['uiapi'] === false && $data['data'][0]['tplid'] != 3) || ($this->type == 3 && isset($data['data']) && !empty($data['data'])) )
                {
                    $expiredTime = GFunc::getCacheTime('15mins');
                    // ai配图的缓存时间需要延长为2小时
                    if(!empty($emoji_type) && $emoji_type == models\SearchEmojiModel::AI_MATCH) {
                        $expiredTime = GFunc::getCacheTime('2hours');
                    }
                    GFunc::cacheZset($cache_key, $data, $expiredTime);
                }
                
                //Edit by fanwenli on 2018-11-21, add count for duomo
                if($this->type == 3) {
                    $searchModel->callbackCount($data['data'],$emoji_type);
                }
                
                //add pu
                $data = $this->AddPu($this->filterResult($data));

                return $data;
            } else {
                $data["error"] = 0;
                $data["data"]  =array();
                return $data;
            }
        }
        else
        {
            $data["error"] = 0;
            $data["data"]  =array();
            return $data;
        }
    }

    /**
     * @param array $arr
     * @param $type 搜索类型
     * @return array
     */
    private function builder($type,$arr=array())
    {
        if(isset($this->params['svc_id']) && $type != 3) {
            $arr = $this->svcBuilder($arr);
        } else {
            switch ($type)
            {
                case 1:
                    $arr = $this->contentBuilder($arr);
                    break;
                case 2:
                    $arr = $this->imageBuilder($arr);
                    break;
                case 3:
                    $arr = $this->emojiBuilder($arr);
                    break;
                //Edit by fanwenli on 2017-08-07, add video card builder
                case 4:
                    $arr = $this->videoBuilder($arr);
                    break;
                //Edit by fanwenli on 2017-08-21, add translate card builder
                case 5:
                    $arr = $this->translateBuilder($arr);
                    break;
                default:
                    $arr = $this->contentBuilder($arr);
                    break;
            }
        }
        return $arr;
    }

    /**
     * @param array $arr
     * @return array
     */
    private function contentBuilder($arr=array())
    {
        $result = array();
        $result['error'] = 0;
        $result['data'] = array();

        //新闻（大搜 realtime 字段）>百科>贴吧>应用>影视>小说>天气>股票>彩票>邮编>手机归属地>IP地址>精准问答>汇率>物流>旅游景点>自然结果
        //Edit by fanwenli on 2018-01-31, add scene before normal
        $sort = '1019,1000,1001,1020,1002,1004,1018,1013,1005,1011,1007,1008,1010,1009,1017,1006,1012,1029';//根据模板id先做一次强制默认排序(根据PM文档)

        $result['data'] = $this->dataSort($arr, $sort);

        $result['data'] = $this->contentStrategy($result['data']);

        $result['data'] = $this->AddrCreator($result['data']);

        return $result;
    }

    /**
     * @param array $data
     * @return
     */
    private function  contentStrategy($data = array())
    {
        if(count($data))
        {
            //如果设置了fin_list，只保留此列表中的卡片
            if(!empty($this->fin_list)) {
                $tmp = array();
                foreach($data as $k => $v) {
                    if(in_array($v['tplid'], $this->fin_list) ) {
                        $tmp[] = $v;
                    }
                }

                $data = $tmp;
            }

            //search tpl sort
            $searchModel = IoCload("models\\SearchContentModel");
            $qt = intval($this->type);
            $sort = $searchModel->cache_getSort($_POST['real_query'], $qt);

            if(!isset($sort['sort'])) {
                //search tpl default sort
                $sort = $searchModel->cache_getSort('', $qt);
            }

            $data = $this->dataSort($data, $sort['sort']);

            // add more card
            $more = array('url' => 'https://m.baidu.com/s?tn=bmbadr&word=' . urlencode($_POST["real_query"]) );
            $more_card = $this->getMore($more, 2);


            //add by zhoubin 20161230 新年祝福语/灯谜/对联关键字命中推卡片                
            $data = $this->addNewYearCard($data);

            //结果只取10条记录（不包括『查看更多卡片』）
            $data = array_slice($data, 0, 10);

            if(!empty($more_card)) {
                array_push($data, $more_card);
            }

            if(empty($data)) {
                //如果结果为空, 返回一张『更多』提示卡片
                $empty_data_tips_card = $this->getMore($more, 3);
                if(!empty($empty_data_tips_card)) {
                    array_push($data, $empty_data_tips_card);
                }
            }

        }else{

            //add by zhoubin 20161230 新年祝福语/灯谜/对联关键字命中推卡片                
            $data = $this->addNewYearCard($data);

            //如果结果为空, 返回一张『更多』提示卡片
            $more = array('url' => 'https://m.baidu.com/s?tn=bmbadr&word=' . urlencode($_POST["real_query"]) );
            $empty_data_tips_card = $this->getMore($more, 3);
            if(!empty($empty_data_tips_card)) {
                array_push($data, $empty_data_tips_card);
            }

        }


        return $data;
    }

    /**
     * @param array $data
     * @param array $sort
     * @return array
     */
    private function dataSort($data, $sort)
    {

        if(empty($sort)) {
            return $data;
        }

        if(gettype($sort) == 'string') {
            $sort = explode(',', $sort);
        }

        if(is_array($sort))
        {
            $sorted_ary = array();
            foreach($sort as $k => $v) {
                foreach($data as $dk => $dv) {
                    if($dv['tplid'] == $v) {
                        array_push($sorted_ary, $dv);
                        unset($data[$dk]);
                    }
                }
            }

            foreach($data as $k => $v) {
                array_push($sorted_ary, $v);
            }
            $data = $sorted_ary;
        }

        return $data;

    }


    /**
     * @param array $arr
     * @return array
     */
    private function imageBuilder($result = array())
    {
        //搜索内容不为空时增加more卡片
        if(!empty($result['data'])){
            $url = 'http://image.baidu.com/search/wiseala?tn=wiseala&ie=utf8&dulisearch=1&word='.urlencode($_POST["real_query"]).'&fr=baiduinput';
            $more = array('url' => $this->TraceAddr($url ,1,0));
            array_push($result['data'],$this->getMore($more, 1));
        }

        return $result;
    }


    /**
     * @param array $arr
     * @return array
     */
    private function emojiBuilder($result = array())
    {
        //搜索内容不为空时增加more卡片
        if(!empty($result['data'])){
            $url = 'http://image.baidu.com/search/wiseala?tn=wiseala&ie=utf8&dulisearch=1&word='.urlencode($_POST["real_query"].' 表情').'&fr=baiduinput';
            
            //Edit by fanwenli on 2017-06-06, change more address with each source
            if($this->svc_source_type > 0 && isset($this->svc_source_type_more[$this->svc_source_type])){
                $url = $this->svc_source_type_more[$this->svc_source_type] . urlencode($_POST["real_query"]);
            }
            
            //Edit by fanwenli on 2018-09-11, do not need "More" card
            /*$more = array('url' =>  $this->TraceAddr($url ,1,0));
            array_push($result['data'], $this->getMore($more, 1));*/
        }

        return $result;
    }
    
    /**
     * @param array $arr
     * @return array
     */
    private function videoBuilder($result = array())
    {
        //搜索内容不为空时增加more卡片
        /*if(!empty($result['data'])){
            //设置暂时更多链接为baidu官网
            $url = 'http://www.baidu.com';
            
            $more = array('url' =>  $this->TraceAddr($url ,1,0));
            array_push($result['data'], $this->getMore($more, 1));
        }*/

        return $result;
    }
    
    /**
     * @param array $arr
     * @return array
     */
    private function translateBuilder($result = array())
    {
        //搜索内容不为空时增加more卡片
        /*if(!empty($result['data'])){
            //设置暂时更多链接为baidu官网
            $url = 'http://www.baidu.com';
            
            $more = array('url' =>  $this->TraceAddr($url ,1,0));
            array_push($result['data'], $this->getMore($more, 1));
        }*/

        return $result;
    }
    
    /**
     * @param array $arr
     * @return array
     */
    private function svcBuilder($result = array())
    {

        return $result;
    }
    

    /**
     * @param array $arr
     * @param int $tplid
     * @return array
     */
    private function getMore($arr = array(), $tplid = 1){
        $out = array();

        //more card
        if(!empty($arr))
        {
            //20170104 edit by zhoubin05  走cardbase创建流程
            $obj = new CardBase();
            $obj->tplid = $tplid;
            $out = $obj->create($arr);
            if($out) {
                $out = $obj->setInfoContent($out);
                $out = ($out == false || $out == null) ? $arr : $out;
            } else {
                $out = $arr;
            }

        }

        return $out;
    }

    /**
     * 验证搜索有无返回结果
     *@route({"GET","/verification"})
     *@param({"tplid","$._GET.tplid"})
     *@param({"query","$._GET.query"})
     *@param({"qt","$._GET.qt"})
     *@return({"body"}){ 0 or 1}
    }
     */
    public function getVerification($tplid, $query, $qt)
    {
        $searchModel  = null;

        //return true or false
        $sign = 0;

        //include tplid, source_from, query
        $query = urldecode($query);
        $tplid = intval($tplid);

        if (!empty($query)) {
            $data = array();
            $query_type = intval($qt);

            if ($query_type > 0) {
                switch ($query_type) {
                    case 1:
                        $searchModel = IoCload("models\\SearchContentModel");
                        $result = $searchModel->searchForVerification($query, $tplid);
                        break;
                    case 2:
                        $searchModel = IoCload("models\\SearchImageModel");
                        $result = $searchModel->search($query);
                        break;
                    case 3:
                        $searchModel = IoCload("models\\SearchEmojiModel");
                        $result = $searchModel->search($query);
                        break;
                    //Edit by fanwenli on 2017-08-07, add video card search
                    case 4:
                        $searchModel = IoCload("models\\SearchVideoModel");
                        $result = $searchModel->search($query);
                        break;
                    default:
                        $searchModel = IoCload("models\\SearchContentModel");
                        $result = $searchModel->searchForVerification($query, $tplid);
                }

                //get data from model
                $data = $this->builder($query_type,$result);

                //found data in array
                if(isset($data['data']) && !empty($data['data'])){
                    $sign = 1;
                }
            }
        }

        return $sign;
    }

    /**
     * @param $arr
     * @return mixed
     */
    private function  AddrCreator($arr)
    {
        $idc = ral_get_idc();
        $loc = IdcMap::map($idc);
        $client = ShortAddrClient::getInstance("ipt", $loc, false);

        $arr_result = $arr;
        $url_array = array();
        $url_array_temp  =array();
        $share_link = array();
        $temp_arr = array();

        if (is_array($arr) && count($arr)) {
            foreach ($arr as $key => $val) {
                if (!empty($val['url'])) {
                    if ($this->IsBaiduUrl($arr_result[$key]['url'])) {
                        $arr_result[$key]['url'] = $val['url'] . "&from=" . $this->from;
                    } else {
                       // $arr_result[$key]['url'] = $this->TraceAddr($val['url'], $val['tplid'], 0)."&sid=".$this->sid;
                    }


                }
                if (!empty($val['share_info']['link'])) {
                    if ($this->IsBaiduUrl($val['share_info']['link'])) {
                        $share_link[$key] = $val['share_info']['link'];
                        $val['share_info']['link'] = $val['share_info']['link'] . "&from=" . $this->from;
                    } else {
                        $share_link[$key] = $val['share_info']['link'];
                    }
                    $url_array_temp[$key] = $this->TraceAddr($val['share_info']['link'], $val['tplid'], 1);
                    $url_array[$key] = $this->TraceAddr($val['share_info']['link'], $val['tplid'], 2);
                    $temp_arr[] = $this->TraceAddr($val['share_info']['link'], $val['tplid'], 2);
                    $temp_arr[] = $this->TraceAddr($val['share_info']['link'], $val['tplid'], 1);
                }

                //针对 新闻聚合 小说聚合  人物类影视 的特殊处理
                if($val['tplid'] == 1019)
                {
                    if(!empty($val['suburl0']))
                    {
                       // $arr_result[$key]['suburl0'] = $this->TraceAddr($val['suburl0'], $val['tplid'], 3)."&sid=".$this->sid;
                    }
                    if(!empty($val['suburl1']))
                    {
                      //  $arr_result[$key]['suburl1'] = $this->TraceAddr($val['suburl1'], $val['tplid'], 4)."&sid=".$this->sid;
                    }
                }
                elseif($val['tplid'] == 1013)
                {
                    if(!empty($val['url1']))
                    {
                       // $arr_result[$key]['url1'] = $this->TraceAddr($val['url1'], $val['tplid'], 3)."&sid=".$this->sid;
                    }
                    if(!empty($val['url2']))
                    {
                       // $arr_result[$key]['url2'] = $this->TraceAddr($val['url2'], $val['tplid'], 4)."&sid=".$this->sid;
                    }
                    if(!empty($val['url3']))
                    {
                       // $arr_result[$key]['url3'] = $this->TraceAddr($val['url3'], $val['tplid'], 5)."&sid=".$this->sid;
                    }
                }
                elseif ($val['tplid'] == 1002)
                {
                    if(!empty($val['film_url_0']))
                    {
                       // $arr_result[$key]['film_url_0'] = $this->TraceAddr($val['film_url_0'], $val['tplid'], 3)."&sid=".$this->sid;
                    }
                    if(!empty($val['film_url_1']))
                    {
                       // $arr_result[$key]['film_url_1'] = $this->TraceAddr($val['film_url_1'], $val['tplid'], 4)."&sid=".$this->sid;
                    }
                    if(!empty($val['film_url_2']))
                    {
                       // $arr_result[$key]['film_url_2'] = $this->TraceAddr($val['film_url_2'], $val['tplid'], 5)."&sid=".$this->sid;
                    }
                    if(!empty($val['film_url_3']))
                    {
                       // $arr_result[$key]['film_url_3'] = $this->TraceAddr($val['film_url_3'], $val['tplid'], 6)."&sid=".$this->sid;
                    }
                }

            }

            if(count($temp_arr)) {
                $addr_arr = $client->createShortAddrs(array_values($temp_arr));


                if (count($addr_arr)) {
                    foreach ($url_array as $key => $val) {
                        $short_addr = $addr_arr[$val];
                        $str = str_replace($share_link[$key], $short_addr, $arr_result[$key]['body_content']);
                        $arr_result[$key]['body_content'] = $str;
                    }
                    foreach ($url_array_temp as $key => $val) {
                        $short_addr = $addr_arr[$val];
                        $arr_result[$key]['share_info']['link'] = $short_addr;
                    }
                }
            }

        }
        return $arr_result;
    }

    /**
     * @param $url
     * @param $tplid
     * @param $addrtype
     * @return string
     */
    private  function TraceAddr($url,$tplid,$addrtype)
    {
//         $traceurl = $this->domain . 'v5/trace?url=' . urlencode($url)
//             .'&sign=' . md5($url . 'iudfu(lkc#xv345y82$dsfjksa')
//             ."&tplid=$tplid"
//             ."&addrtype=$addrtype";
        $traceurl = $url;
        return $traceurl;
    }

    /**
     * @param $url
     * @return bool
     */
    private function IsBaiduUrl($url) {
        $info = parse_url($url);
        if(isset($info['host']) && !empty($info['host'])  && $info['host'] == 'm.baidu.com' && $info['path'] =='/s') {
            return true;
        }
        return false;
    }


    /**
     * @param $arr
     * @return mixed
     */
    private function  AddPu($arr)
    {
        $isAdd = false;
        if($this->type == 1 && !empty($this->hostid))
        {
            $searchModel = IoCload("models\\SearchContentModel");
            $hostIds= $searchModel->cache_getHostId();
            $isAdd = $this->IsInHostId($hostIds,$this->hostid);
        }


        $tmp_arr = $arr;

        if(is_array($arr['data'])&&count($arr['data']))
        {
            foreach ($arr['data'] as $key => $val)
            {
                if($this->IsBaiduUrl($val['url']))
                {
                   // $tmp_arr['data'][$key]['url'] = $this->TraceAddr($val['url'].'&pu='.$this->pu,$val['tplid'],0)."&sid=".$this->sid;
                    $tmp_arr['data'][$key]['url'] = $this->TraceAddr($val['url'].'&pu='.$this->pu,$val['tplid'],0);
                }
                if(!$isAdd)
                {
                    $tmp_arr['data'][$key]['body_content'] .="\n";
                }

            }
        }
        
        //20180904 add by zhoubin05 for ai输入法也需要用到搜索服务，但是不需要‘更多’卡片
        //这里提供可选参数$_POST['del_more_card']供客户端调用,服务端检测到就删除下发数据中的更多卡片 
        if(1 === intval($_POST['del_more_card']) && !empty($tmp_arr['data'])) {
            foreach($tmp_arr['data'] as $k => $v) {
                if(in_array(intval($v['tplid']), array(1,2,3))) {
                    unset($tmp_arr['data'][$k]);
                }
            }    
        }
        

        return $tmp_arr;
    }


    /**
     * @param $query
     * @return bool
     */
    private function  IsWeather($query)
    {
        if(strpos($query, '天气') ===false)
        {
            return false;
        }
        else
        {
            return true;
        }
    }


    /**
     * @param $hostids
     * @param $hostid
     * @return bool
     */
    private function IsInHostId($hostids,$hostid)
    {
        $result = false;
        foreach ($hostids as $key=>$val) {
            if ($val['hostid'] == trim($hostid)) {
                $result = true;
                break;
            }
        }
        return $result;

    }

    /**
     * 将相同或类似的query统一成一个，以减少搜索消耗
     * @param $query
     * @return string
     */
    private function querySimp($query) {
        $query = preg_replace('/(\s+)/',' ', $query); //多个连续空格，制表符，换行都替换成一个空格
        return trim($query); //去除左右空格
    }

    /**
     *  新年祝福语/灯谜/对联关键字命中推卡片
     * @param $data 原输出卡片数据
     * @return $data
     */
    private function addNewYearCard($data) {
        //获取新年祝福数据
        $new_year_data = UnifyConf::getUnifyConf(GFunc::getCacheInstance(), '2017_new_year_conf_pre', GFunc::getCacheTime('10mins'), '/res/json/input/r/online/2017_new_year/', Util::getClientIP(), $_GET['from'], false);
        if(count($new_year_data) > 0) {
            //关键字命中检测
            $info = array_shift($new_year_data);

            foreach($info as $k => $v) {
                $keywords = explode(PHP_EOL,$v['keywords']);

                if(in_array($_POST["real_query"],$keywords)) {

                    $card_params = array('keyword' => $_POST["real_query"], 'url' => $v['url'], 'img' => $v['img'] );

                    $card = array();
                    switch($k) {
                        case 'zhufu':
                            $card = $this->getmore($card_params, 5); //祝福卡
                            break;
                        case 'chunlian':
                            $card = $this->getmore($card_params, 6); //春联卡
                            break;
                        case 'dengmi':
                            $card = $this->getmore($card_params, 7); //灯谜卡
                            break;
                        default:
                            break;
                    }

                    if(!empty($card)) {
                        array_push($data, $card);
                    }

                }
            }

            $data = $this->dataSort($data, '5,6,7'); //卡片置顶
        }

        return $data;
    }

    /**
     *  如果根据请求对结果有不同处理（缓存key一样的情况下）,可使用一下函数再次过滤
     *
     * @param $data 需要过滤的结果
     * @return array
     */
    private function filterResult($data) {
        if($this->type == 3 && $this->scene == 'audio') {
            //语音搜索表情场景获取的更多卡片与原来的不同 add by zhoubin05 20170308
            $more_card_id = count($data['data']) - 1;
            $more_card = array();
            if($data['data'][$more_card_id]['tplid'] == 1) {
                $more_card = $data['data'][$more_card_id];
                $more_card['tplid'] = 4;
                $more_card['targetIndex'] = 11; //索引标记，用户客户端点击「更多」卡片时可以跳转到普通搜索的相应结果位置
                $more_card['searchType'] = 'emoji'; //下发搜索类型给客户端
            }
            if(count($data['data']) > 10 ) {
                $data['data'] = array_slice($data['data'], 0, 10, true); //只保留10个结果
            }

            if(!empty($more_card)) {
                array_push($data['data'], $more_card);
            }
        }

        return $data;
    }
    
    /**
     * 获取推荐表情
     * @route({"POST","/recommand_emoji"})
     * @param({"version", "$._GET.version"}) string 版本号 不需要客户端传
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"body"}){
    "error":0
    "data":
    [
    {
    "tplid":1000,
    "tpln":"",
    "intent":""
    }
    ]

    }
     */
    public function getRecommandEmoji($version = '', $platform = '', $tab = array())
    {
        $out = Util::initialClass(false);
        
        //Edit by fanwenli on 2019-04-01, get type with 2 emoji from dbx
        $os = Util::getOs($platform);
        $version = Util::formatVer($version);
        
        $_POST['real_query'] = 'Recommand';
        
        //Edit by fanwenli on 2019-05-22, get tab info
        $intTabId = 0;
        $strTabName = '';
        $intSourceRequire = 1;
        if(is_array($tab) && !empty($tab)) {
            if(isset($tab['id'])) {
                $intTabId = intval($tab['id']);
            }
            
            if(isset($tab['name'])) {
                $strTabName = trim($tab['name']);
                
                //set for count
                $_POST["real_query"] = $strTabName;
            }
            
            if(isset($tab['source_require'])) {
                $intSourceRequire = intval($tab['source_require']);
            }
        }
        
        $searchModel = IoCload("models\\SearchEmojiModel");

        //Edit by fanwenli on 2020-03-02, add cache tab
        $strCacheKey = 'search_recommand_emoji_result_' . $os . '_' . $version . '_' . $intTabId . '_tab';
        $arrUid = GFunc::cacheZget($strCacheKey);
        if($arrUid === false) {
            $emojiModel = IoCload("models\\EmojiModel");
            $arrUid = $emojiModel->getSearchRecommendTabContent($os, $version, $intTabId);

            GFunc::cacheZset($strCacheKey, $arrUid, GFunc::getCacheTime('2hours'));
        }

        //表情包过滤
        $conditionFilter = IoCload("utils\\ConditionFilter");
        //表情包过滤完结果
        $arrUid = $conditionFilter->getFilterConditionFromDB($arrUid);

        //reset uid array
        if(!empty($arrUid)) {
            foreach($arrUid as $k => $v) {
                $arrUid[$k] = $v['uid'];
            }
        }

        //Edit by fanwenli on 2019-04-01, add cache key by os & version & tab
        $strCacheKey = 'search_recommand_emoji_result_' . implode('_', $arrUid) . '_' . $intTabId;
        $arrCacheContent = GFunc::cacheZget($strCacheKey);

        $this->type = 3;
        if($arrCacheContent === false) {
            //Edit by fanwenli on 2018-11-19, get pics from db
            $intNumber = 96;
            
            //Edit by fanwenli on 2019-11-08, it will be 96 when system returns
            $intArrayReturn = $intNumber;
            
            $arrDataDbx = array(
                'data' => array(),
            );
            
            $emojiModel = IoCload("models\\EmojiModel");
            $arrPicsDbx = $emojiModel->getSearchRecommendData($arrUid);
            if(is_array($arrPicsDbx) && !empty($arrPicsDbx)) {
                $arrDataDbx['status']['msg'] = 'success';
                foreach($arrPicsDbx as $key => $val) {
                    //get 96 images at most
                    if($key == $intNumber) {
                        break;
                    }
                    
                    $arrDataDbx['data']['ResultArray'][] = array(
                        'ObjUrl' => $val['ObjUrl'],
                        'Width' => $val['Width'],
                        'Height' => $val['Height'],
                        'ThumbnailUrl' => $val['ThumbnailUrl'],
                        'ThumWidth' => $val['ThumWidth'],
                        'ThumHeight' => $val['ThumHeight'],
                        'ShareUrl' => $val['ObjUrl'],
                        'id' => $val['id'],
                        'count_emojiId' => '',
                        'count_contsign' => '',
                    );
                }
                
                $arrDataDbx = $searchModel->emojiBulider($arrDataDbx);
            }
            
            $arrCacheContent = $arrDataDbx['data'];
            
            $intNumber -= count($arrPicsDbx);
            
            //Edit by fanwenli on 2019-05-24, source_require is 1 than use duomo's source
            if($intNumber >= 0) {
                switch($intSourceRequire) {
                    case 1:
                        //Edit by fanwenli on 2019-05-08, recommend tab use category api
                        if ($intTabId == 0) {
                            $data = $searchModel->getDuomoHotContent($intNumber);
                        } else {
                            $data = $searchModel->getDuomoCateContent($strTabName, $intNumber);
                        }

                        if (is_array($data) && !empty($data)) {
                            $data = $searchModel->bulidDuomoContent($data);
                            $data = $searchModel->emojiBulider($data);
                        }
                        break;
                    case 2:
                        //Edit by fanwenli on 2019-07-19, recommend tab use douya's source
                        $data = $searchModel->getDouyaHotContent($strTabName, $intNumber);
                        if (is_array($data) && !empty($data)) {
                            $data = $searchModel->bulidDouyaContent($data);
                            $data = $searchModel->emojiBulider($data);
                        }
                        break;
                }
                
                if(isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
                    $arrCacheContent = array_merge($arrCacheContent, $data['data']);
                }
            }
            
            //Edit by fanwenli on 2019-11-08, it will be 96 when system returns
            $arrCacheContent = array_slice($arrCacheContent, 0, $intArrayReturn);
            
            GFunc::cacheZset($strCacheKey, $arrCacheContent, GFunc::getCacheTime('2hours'));
        }
        
        //Edit by fanwenli on 2019-10-25, douya count will be set param
        switch($intSourceRequire) {
            //duomo
            case 1:
                $searchModel->callbackCount($arrCacheContent, 0, 1);
                break;
            //douya
            case 2:
                $searchModel->callbackCount($arrCacheContent, 0, 1);
                break;
        }
        
        $out['data'] = $arrCacheContent;
        
        return Util::returnValue($out,false);
    }
    
    /**
     * 搜索tab以及相关数据下发
     * @route({"GET","/tab"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function getTabContent() {
        $out = Util::initialClass(false);
        
        //get search sug obj
        $searchSug = IoCload("SearchSug");
        
        $strCacheKey = 'search_data_tab';
        $content = GFunc::cacheZget($strCacheKey);
        if($content === false) {
            $searchModel = IoCload("models\\SearchTabModel");
            $data = $searchModel->getTabContent();
            $version = $searchModel->getVersion();
            
            $result = array();
            if(!empty($data)) {
                //top words only for qt 1
                $arrTopWords = $searchSug->_getTopWords(true);
                
                //获取垂直分类tag
                $svcModel = IoCload("models\\SearchVerticalCateModel");
                $svc = $svcModel->cache_getTags();
                
                $arrResSvc = array();
                foreach ($svc as $v) {
                    $arrResSvc[$v['qt']][] = array(
                        'svc_id' => $v['id'],
                        'prefix' => $v['prefix'],
                        'prefix_full' => '#' . $v['prefix'] . '#',
                        'hint' =>  $v['hint'],
                        'sug_id'=> $v['sug_id'],
                        'icon' => $v['icon'],
                        'pos_1' => $v['pos1'],
                        'pos_2' => $v['pos2'],
                        'pos_3' => $v['pos3'],
                        'pos_4' => $v['pos4'],
                        'filter_id' => intval($v['filter_id']),
                        'fill_data' => array(),
                    );
                }
                
                //sug
                $searchSugModel = IoCload("models\\SearchSugRecommendModel");
                $arrSearchSug = $searchSugModel->cache_getRecommend();

                //获取特殊分类定位关键词
                $ssqlModel = IoCload("models\\SearchSpecQueryLocModel");
                $ssql = $ssqlModel->cache_getQuerys();
                
                $arrResSsql = array();
                foreach ($ssql as $v) {
                    $arrResSsql[$v['qt']][] = array(
                        'querys' => $v['querys'],
                        'filter_id' => intval($v['filter_id']),
                    );
                }
                
                foreach($data as $key => $val) {
                    $qt = intval($val['id']);//search type
                    
                    $arrPmData = array();
                    if(!empty($arrSearchSug)) {
                        foreach($arrSearchSug as $sug) {
                            //set pm data with qt equal with data
                            if($sug['qt'] == $qt) {
                                $arrPmData[] = array(
                                    'pos' => array(
                                        $sug['pos1'],
                                        $sug['pos2'],
                                        $sug['pos3'],
                                        $sug['pos4'],
                                    ),
                                    'filter_id' => intval($sug['filter_id']),//filter id
                                );
                            }
                        }
                    }
                    
                    $result[$key] = array(
                        'qt' => $qt,
                        'tab_name' =>trim($val['tab_name']),//name
                        'hint' => trim($val['hint']),//hint
                        'filter_id' => intval($val['filter_id']),//filter id
                        'pm_data' => $arrPmData,//sug
                        'fill_data' => array(),//top words
                        'tags' => isset($arrResSvc[$qt])?$arrResSvc[$qt]:array(),//search vertical
                        'ssql' => isset($arrResSsql[$qt])?$arrResSsql[$qt]:array(),//分类定位关键词
                    );
                    
                    //top words only for qt 1
                    if($qt == 1) {
                        $result[$key]['fill_data'] = $arrTopWords;
                    }
                }
            }
            
            $arrContent = array(
                'data' => $result,
                'version' => $version,
            );
            
            $content = json_encode($arrContent);
            
            GFunc::cacheZset($strCacheKey, $content, GFunc::getCacheTime('2hours'));
        }
        
        $arrContent = json_decode($content, true);
        
        if(isset($arrContent['data']) && !empty($arrContent['data'])) {
            $filterConditionModel = IoCload("models\\FilterConditionModel");
            $cacheFilter = $filterConditionModel->cache_getFilter();
            
            //整理过滤条件，把过滤id作为下标创建一个新的过滤条件数组
            $nfc = array();
            foreach ($cacheFilter as $v) {
                $nfc[$v['id']] = json_decode($v['condition_filter'], true);
            }
            
            foreach ($arrContent['data'] as $k => $v) {
                $val = $searchSug->_arrFilter($v, $nfc, true);
                //pass the filter
                if($val !== null) {
                    unset($arrContent['data'][$k]['filter_id']);
                    
                    //sug filter
                    if(isset($v['pm_data']) && !empty($v['pm_data'])) {
                        foreach($v['pm_data'] as $k_sug => $v_sug) {
                            $pm_data_val = $searchSug->_arrFilter($v_sug, $nfc, true);
                            //pass the filter
                            if($pm_data_val !== null) {
                                //reset array
                                $arrContent['data'][$k]['pm_data'] = $v_sug['pos'];
                                break;
                            } else {
                                unset($arrContent['data'][$k]['pm_data'][$k_sug]);
                            }
                        }
                    }
                    
                    //search vertical filter
                    if(isset($v['tags']) && !empty($v['tags'])) {
                        //reset tags
                        $arrTags = array();
                        foreach($v['tags'] as $k_tags => $v_tags) {
                            $tags_data_val = $searchSug->_arrFilter($v_tags, $nfc, true);
                            //pass the filter
                            if($tags_data_val !== null) {
                                unset($arrContent['data'][$k]['tags'][$k_tags]['filter_id']);
                                
                                $arrTags[] = $arrContent['data'][$k]['tags'][$k_tags];
                            }
                        }
                        
                        $arrContent['data'][$k]['tags'] = $arrTags;
                    }
                    
                    //分类定位关键词
                    if(isset($v['ssql']) && !empty($v['ssql'])) {
                        //reset tags
                        $arrSsql = array();
                        foreach($v['ssql'] as $k_ssql => $v_ssql) {
                            $ssql_data_val = $searchSug->_arrFilter($v_ssql, $nfc, true);
                            //pass the filter
                            if($ssql_data_val !== null) {
                                $arrSsql = array_merge($arrSsql, explode(PHP_EOL, $arrContent['data'][$k]['ssql'][$k_ssql]['querys']));
                            }
                        }
                        
                        $arrContent['data'][$k]['ssql'] = $arrSsql;
                    }
                    
                } else {
                    unset($arrContent['data'][$k]);
                }
            }
        }
        
        $out['data'] = $arrContent['data'];
        $out['version'] = $arrContent['version'];
        
        return Util::returnValue($out,false,true);
    }
    
    /**
     * 获取推荐表情tab以及数据
     * @route({"GET","/recommand_emoji_tab"})
     * @param({"version", "$._GET.version"}) string 版本号 不需要客户端传
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function getRecommandEmojiTab($version = '', $platform = '') {
        $out = Util::initialClass(false);
        
        $strCacheKey = 'search_recommand_emoji_tab';
        $arrCacheContent = GFunc::cacheZget($strCacheKey);
        
        if($arrCacheContent === false) {
            $emojiModel = IoCload("models\\EmojiModel");
            $arrCacheContent = $emojiModel->getSearchRecommendTab();
            
            GFunc::cacheZset($strCacheKey, $arrCacheContent, GFunc::getCacheTime('2hours'));
        }
        
        if(!empty($arrCacheContent)) {
            $i = 0;
            foreach($arrCacheContent as $intTabId => $arrData) {
                
                //Edit by fanwenli on 2019-10-21, judge skin_token when it is uploaded
                if($arrData['skin_token'] != '') {
                    $arrSkinToken = explode(';', $arrData['skin_token']);
                    $arrSkinToken = array_map('trim', $arrSkinToken);
                    
                    //skin token must be got and it is in array
                    if(!isset($_GET['skin_token']) || (isset($_GET['skin_token']) && !in_array(trim($_GET['skin_token']), $arrSkinToken))) {
                        continue;
                    }
                }
                //get emoji data
                $arrEmoji = $this->getRecommandEmoji($version, $platform, $arrData);
                
                //unset skin token
                unset($arrData['skin_token']);
                
                //Edit by fanwenli on 2019-05-24, unset source_require
                unset($arrData['source_require']);
                $out['data'][$i] = $arrData;
                $out['data'][$i]['data'] = $arrEmoji['data'];
                $i++;
            }
        }
        
        return Util::returnValue($out,false);
    }
    
    /**
     * 命中黑名单记录日志
     * 
     * @param $query 关键词
     * @param $type 操作 get 获取 set 设置
     * 
     * @return 
     */
    private function blacklistLog($query, $type = 'get') {
        // it works when there is emoji search
        if($this->type == 3) {
            $strCacheQuery = 'search_data_result_' . $this->type . '_blacklist_' . crc32($_POST["user_query"]);
            
            $sign = 0;
            switch($type) {
                case 'get':
                    $intCacheVal = GFunc::cacheZget($strCacheQuery);
                    if($intCacheVal !== false) {
                        $sign = intval($intCacheVal);
                    }
                    break;
                case 'set':
                    $sign = 1;
                    GFunc::cacheZset($strCacheQuery, $sign, GFunc::getCacheTime('15mins'));
                    break;
            }
            
            if($sign !== 0) {
                $model = IoCload("models\\SearchEmojiModel");
                $model->inputCount('other');
            }
        }
    }
}
