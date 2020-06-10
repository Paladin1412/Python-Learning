<?php

use tinyESB\util\ClassLoader;
use utils\CacheVersionSwitchScope;
use utils\DbConn;
use utils\GFunc;
use utils\Util;

ClassLoader::addInclude(__DIR__.'/noti');

/**
 * 通知中心重构
 *
 * @author fanwenli and lipengcheng
 * @path("/notiv2/")
 */
class NotiV2
{
    /** @property 内部缓存实例(apc内存缓存) */
    private $apc_cache;
    
    /** @property 内部缓存实例(KsarchRedis缓存) */
    private $redis_cache;
    
    /** @property apc单个缓存过期时长*/
    private $apc_cache_key_expired_time;
    
    /** @property 单个缓存过期时长*/
    private $redis_cache_key_expired_time;
    
    /** ral multi 请求数组*/
    private $ral_multi_req = array();
    
    /** ral multi 请求数组关联的proto */
    private $ral_multi_req_relate = array();
    
    /** dbx phaster 请求数组*/
    private $dbx_phaster_req = array();
    
    /** dbx phaster 返回数组*/
    private $dbx_phaster_return = array();
    
    /** 客户端上传参数 **/
    private $client_post_arr = array();
    
    /** 联网类型 **/
    private $g_intSp = 0;
    
    /** 找不到proto或者database时设置版本号（时间戳）为-1 **/
    private $message_version_with_no_data = -1;

    /**
     *
     * 通知中心信息获取
     *
     * @route({"POST","/info"})
     *
     * @param({"strData","$._POST.noti"}) 客户端上传信息
     * @param({"platform", "$._GET.platform"}) 平台号
     * @param({"version", "$._GET.version"}) 输入法版本
     * @param({"intSp", "$._GET.sp"}) $intSp 联网类型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     *
     * @return({"body"})
     */
    public function getNoti($strData, $platform, $version, $intSp = 12){        
        $retdata = Util::initialClass();
        
        //decode客户端上传参数
        $this->client_post_arr = is_array($strData) ? $strData : json_decode($strData, true);
        
        $version = Util::getVersionIntValue($version);
        
        $this->g_intSp = $intSp;
        
        $arrNoti = array();
        
        //设置ral通知数组
        $this->setRalNotiArr($platform, $version);
        //设置dbx通知数组
        $this->setDbxNotiArr($platform, $version);
        
        //ral请求key数组
        $arrRalReq = $this->ral_multi_req;
        
        //Edit by fanwenli on 2019-11-19, add cache key pre
        // $arrDbxCacheKey = $this->setDbxCacheKey();
        
        //dbx请求key数组
        $arrDbxKey = array_keys($this->dbx_phaster_req);
        $arrDbxCacheKey = $arrDbxKey;
        
        //apc缓存key数组
        //$arrApcCacheKey = array_merge($arrRalReq,$arrDbxKey);
        //Edit by fanwenli on 2019-11-19, change dbx cache key with new one
        $arrApcCacheKey = array_merge($arrRalReq,$arrDbxCacheKey);
        
        //apc 缓存数据
        $apc_ral_noti_data = $this->apc_cache->multiGet($arrApcCacheKey);
        
        //判断是否有缓存，如有则直接返回并删除请求数据以避免以下请求，没有则进入下一步
        $this->setArrNoti($arrNoti, $apc_ral_noti_data);
        
        //apc设置缓存数组
        $arrApcCache = array();
        //判断如果ral缓存失效
        if(!empty($this->ral_multi_req)) {
            //redis 缓存数据
            $redis_ral_noti_data = $this->redis_cache->multiget($arrRalReq);
            //如上功能，且获取redis数据用来更新apc数据
            $arrApcCache = array_merge($arrApcCache, $this->setArrNoti($arrNoti, $redis_ral_noti_data['ret']));
        }
        
        //判断如果dbx数据还有请求
        if(!empty($this->dbx_phaster_req)) {
            //redis 缓存数据
            //$redis_dbx_noti_data = $this->redis_cache->multiget($arrDbxKey);
            //Edit by fanwenli on 2019-11-19, change dbx cache key with new one
            $redis_dbx_noti_data = $this->redis_cache->multiget($arrDbxCacheKey);
            // 过滤掉数据版本较老的数据， 促使重新读取数据库
            $redis_dbx_noti_data['ret'] = $this->filterExpiredData($redis_dbx_noti_data['ret']);
            //如上功能，且获取redis数据用来更新apc数据
            $arrApcCache = array_merge($arrApcCache, $this->setArrNoti($arrNoti, $redis_dbx_noti_data['ret']));
        }
        
        //如apc与redis都无数据，则ral取数据
        if(!empty($this->ral_multi_req)) {
            //获取v5后台所有的proto更新时间
            $header = array(
                'pathinfo' => '/res/json/input/r/online/',
                'querystring' => 'onlycontent=0',
                'Connection' => 'close',
            );
            
            $arrRalContent = ral("res_service", "get", null, null, $header);
            
            //数据处理
            $data = is_array($arrRalContent) ? $arrRalContent : json_decode($arrRalContent, true);
            
            $arrProto = array();
            //检查v5后台的proto
            if(isset($data['child']) && is_array($data['child']) &&!empty($data['child'])) {
                foreach($data['child'] as $strProto => $arrInfo) {
                    //只有类型为proto的才获取
                    if(isset($arrInfo['is_dir']) && $arrInfo['is_dir'] == 1) {
                        $arrProto[$strProto] = $arrInfo;
                    }
                }
            }
            
            //增量数据
            $arrIncrDdata = $this->setReturnArrNoti($arrNoti, $arrProto);
            //设置apc数组以及设置redis缓存值
            $this->setCacheNoti($arrApcCache, $arrIncrDdata);
        }
        
        //如apc与redis都无数据，则dbx取数据
        if(!empty($this->dbx_phaster_req)) {
            //执行数据库phaster操作
            $this->phasterThreadsJoin();
            
            //增量数据
            $arrIncrDdata = $this->setReturnArrNoti($arrNoti, $this->dbx_phaster_return);
            //设置apc数组以及设置redis缓存值
            $this->setCacheNoti($arrApcCache, $arrIncrDdata);
        }
        
        //如apc数据有过设置
        if(!empty($arrApcCache)) {
            //写入apc缓存
            $this->apc_cache->multiSet($arrApcCache, $this->apc_cache_key_expired_time);
        }
        
        //message time
        $arrMessageTime = array();
        if(is_array($arrNoti) && !empty($arrNoti)) {
            foreach($arrNoti as $strProtoKey => $intNotiVersion) {
                //设置为找不到proto或者database时设置版本号时，接口不返回该结果
                if($intNotiVersion == $this->message_version_with_no_data) {
                    continue;
                }
                
                //返回索引
                $arrReturnNotiKey = array();
                
                if(array_key_exists($strProtoKey,$arrRalReq)) {//客户端请求中索引与proto名称一致
                    $arrReturnNotiKey[0] = $strProtoKey;
                } elseif(in_array($strProtoKey,$arrRalReq)) {//客户端请求索引能找到对应的proto名称
                    $arrReturnNotiKey = array_keys($arrRalReq,$strProtoKey);
                }
                
                if(in_array($strProtoKey,$arrDbxKey)) {//客户端请求索引能找到对应的数据库请求key
                    $arrReturnNotiKey[0] = $strProtoKey;
                }
                
                //都没有的情况下跳过
                if(empty($arrReturnNotiKey)) {
                    continue;
                }
                
                //set message time whether client post version to server
                $this->setMessageTimeArray($arrMessageTime, $arrReturnNotiKey, $intNotiVersion);
            }
            
            //对返回结果进行处理
            $arrMessageTime = $this->returnHandle($arrMessageTime);
        }
        
        $retdata['data'] = $arrMessageTime;        
        return $retdata;
    }    
    
    /**
     * 设置通知数组
     * 所有的ral修改都在此方法内
     * 
     * @param $platform 平台号
	 * @param $version 输入法版本
     * @return array
     */
    public function setRalNotiArr($platform, $version) {
        //获取通知中心资源管理配置
        $arrNotiRes = NotiConf::getResConf($platform, $version);
        
        if(is_array($arrNotiRes) && !empty($arrNotiRes)) {
            //循环获取配置数据
            foreach($arrNotiRes as $arrNoti) {
                //判断key值，proto名称以及搜索字段都存在
                if(isset($arrNoti['noti_key']) && isset($arrNoti['proto'])) {
                    //key，proto，search给到ral并发请求数组
                    $this->setRalMulti($arrNoti['noti_key'], $arrNoti['proto']);
                    
                    //Edit by fanwenli on 2019-08-07, add relate proto
                    if(isset($arrNoti['proto_other']) && !empty($arrNoti['proto_other'])) {
                        $this->ral_multi_req_relate[$arrNoti['noti_key']] = $arrNoti['proto_other'];
                    }
                }
            }
        }
    }
    
    /**
     * 设置通知数组
     * 所有的dbx修改都在此方法内
     * 
     * @param $platform 平台号
	 * @param $version 输入法版本
     * @return array
     */
    public function setDbxNotiArr($platform, $version) {
        //获取通知中心数据库配置
        $arrNotiDbx = NotiConf::getDbxConf($platform, $version);
        if(is_array($arrNotiDbx) && !empty($arrNotiDbx)) {
            //循环获取配置数据
            foreach($arrNotiDbx as $strNotiKey => $arrNoti) {
                //the noti key's version could be found in client post array
                $notiFlag = $this->judgeClientVersion($strNotiKey);
                
                //判断客户端请求中带有对应请求索引key
                if($notiFlag) {
                    //版本号和sql语句字段都存在
                    if(isset($arrNoti['column']) && isset($arrNoti['query'])) {
                        //通知索引给key值局部变量
                        $this->dbx_phaster_req[$strNotiKey] = array(
                            'column' => $arrNoti['column'],
                            'query' => $arrNoti['query'],
                            'cache_pre' => $arrNoti['cache_pre'],
                        );
                    } else {
                        //多个元素
                        foreach($arrNoti as $val) {
                            if(isset($val['column']) && isset($val['query'])) {
                                //通知索引给key值局部变量
                                $this->dbx_phaster_req[$strNotiKey][] = array(
                                    'column' => $val['column'],
                                    'query' => $val['query'],
                                    'cache_pre' => $arrNoti['cache_pre'],
                                );
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 设置获取资源ral_multi的数组
     * @param $noti_key ral返回索引
     * @param $proto proto名称
     */
    public function setRalMulti($noti_key = '', $proto = '') {
        //the noti key's version could be found in client post array
        $notiFlag = $this->judgeClientVersion($noti_key);
        
        if($proto != '' && $notiFlag) {
            //set ral_multi array
            $this->ral_multi_req[$noti_key] = $proto;
        }
    }
    
    /**
     * 根据客户端上传当前字段是否包含当前请求字段版本号
     * @param $noti_key ral返回索引
     * @return boolean
     */
    public function judgeClientVersion($noti_key = '') {
        $out = false;
        
        //client post array is not empty and not_key is not empty
        if(!empty($this->client_post_arr) && $noti_key != '') {
            //the noti key's version could be found in client post array
            if(isset($this->client_post_arr[$noti_key]['message_version'])) {
                $out = true;
            }
        }
        
        return $out;
    }
    
    /**
     * 根据客户端上传当前字段版本号与服务端版本号比较，服务端大则返回true，否则返回false
     * @param $noti_key 通知索引
     * @param $server_version 服务端版本
     * @return boolean
     */
    public function cmpMsgVer($noti_key = '', $server_version = 0) {
        $out = false;
        
        $sign = $this->judgeClientVersion($noti_key);
        
        //the noti key's version could be found in client post array
        if($sign) {
            $server_version = intval($server_version);
            
            //normal items check
            $client_version = intval($this->client_post_arr[$noti_key]['message_version']);
            //server version is bigger than client version
            if($client_version < $server_version) {
                $out = true;
            }
        }
        
        return $out;
    }
    
    /**
     * 设置获取数据库最新更新时间的数组
     * @param $sql 查询语句
     * @param $column 版本字段名称
     * @param $search 搜索字段，包含搜索结构和值
     * @return int
     */
    public function setDbxPhaster($sql = '', $column = '') {
        $out = '';
        
        if($sql != '' && $column != '') {
            $objDb = $this->getPhasterDbX();
            
            $result = $objDb->queryf($sql);
            
            if(isset($result[0][$column])) {
                //是数字时间戳直接获取，否则转换为时间戳形式
                $out = !is_numeric($result[0][$column]) ? strtotime($result[0][$column]) : intval($result[0][$column]);
            } else {
                $out = 0;
            }
            
            $this->returnPhasterDbX($objDb);
        }
        
        return $out;
    }
    
    /**
     * 数据库phaster并行操作方法，暂只支持二维数组
     */
    private function phasterThreadsJoin(){
        //重构请求数组
        $phaster_request = array();
        foreach($this->dbx_phaster_req as $key => $val) {
            //支持二维数组
            if(isset($val['query']) && isset($val['column'])) {
                $phaster_request[$key] = new \PhasterThread(array($this, "setDbxPhaster"), array($val['query'],$val['column']));
            } else {
                foreach($val as $inner_key => $inner_val) {
                    if(isset($inner_val['query']) && isset($inner_val['column'])) {
                        $phaster_request[$key][$inner_key] = new \PhasterThread(array($this, "setDbxPhaster"), array($inner_val['query'],$inner_val['column']));
                    }
                }
            }
        }
        
        //执行phaster请求数组
        foreach ($phaster_request as $key => $phasterthread){
            //支持二维数组
            if(is_object($phasterthread)) {
                $this->dbx_phaster_return[$key] = $phasterthread->join();
            } else {
                foreach($phasterthread as $inner_key => $inner_phasterthread) {
                    if(is_object($inner_phasterthread)) {
                        $this->dbx_phaster_return[$key][$inner_key] = $inner_phasterthread->join();
                    }
                }
            }
        }
    }
    
    /**
     * 对返回结果进行逻辑处理
     * @param $message_time_arr message time数组
     * @return array
     */
    private function returnHandle($message_time_arr = array()){
        if(!empty($message_time_arr)) {
            //logo插件取“推荐插件最新更新时间”与“插件最新更新时间”之间最大值
            if($message_time_arr['message_time']['logo_ads_time_recommend'] > $message_time_arr['message_time']['logo_ads_time']) {
                $message_time_arr['message_time']['logo_ads_time'] = $message_time_arr['message_time']['logo_ads_time_recommend'];
            }
            //删除“推荐插件最新更新时间”
            unset($message_time_arr['message_time']['logo_ads_time_recommend']);
        }
        
        return $message_time_arr;
	}
	
	/**
	 * 获取phaster_xdb实例
	 *
	 * @return db
	 */
	public function getPhasterDbX(){
	    return DBConn::getPhasterXdb();
	}
	
	/**
	 * 归还phaster_xdb实例
	 * @param db
	 * @return db
	 */
	public function returnPhasterDbX($conn){
	    return DBConn::returnPhasterXdb($conn);
	}
	
	/**
     * 客户端和服务端的版本判断，如服务端大于客户端则注入数组
     * @param $message_time_arr message time数组
     * @param $arrNotiKey 通知索引数组
     * @param $server_version 服务端版本
     * @return array
     */
    private function setMessageTimeArray(&$arrMessageTime, $arrNotiKey = array(), $server_version = 0){
        if(!empty($arrNotiKey)) {
            foreach($arrNotiKey as $noti_key) {
                //server version is bigger than client's
                $updateFlag = $this->cmpMsgVer($noti_key, $server_version);
                if($updateFlag) {
                    /*$arrMessageTime[] = array(
                        'message_key' => $noti_key,
                        'message_version' => $server_version,
                    );*/
                    
                    $arrMessageTime[$noti_key] = array(
                        'message_version' => $server_version,
                    );
                }
            }
        }
    }
    
    /**
     * 判断有无缓存，如有，则删除请求直接返回缓存数据
     * @param $arrNoti 返回数据数组
     * @param $arrData 缓存数据数组
     * @return array 增量通知数据
     */
    private function setArrNoti(&$arrNoti, $arrData = array()){
        $out = array();
        
        if(is_array($arrData) && !empty($arrData)) {
            foreach($arrData as $noti_key => $noti_value){
                //找到缓存，则删除ral请求
                if($noti_value !== '' && !is_null($noti_value)) {
                    $arrNoti[$noti_key] = intval($noti_value);
                    
                    $out[$noti_key] = intval($noti_value);
                    
                    //判断是键值是ral的请求，或是在缓存中存在键值（proto名称）对应请求的键值（noti key）；剩余的就是dbx的键值
                    if(isset($this->ral_multi_req[$noti_key])) {
                        unset($this->ral_multi_req[$noti_key]);
                    } elseif(array_search($noti_key,$this->ral_multi_req) !== false) {
                        //找到多个符合键值的请求
                        $arrRalMultiReqKey = array_keys($this->ral_multi_req,$noti_key);
                        if(is_array($arrRalMultiReqKey) && !empty($arrRalMultiReqKey)) {
                            foreach($arrRalMultiReqKey as $ralMultiReqKey) {
                                unset($this->ral_multi_req[$ralMultiReqKey]);
                            }
                        }
                    } elseif(isset($this->dbx_phaster_req[$noti_key])) {
                        unset($this->dbx_phaster_req[$noti_key]);
                    }
                }
            }
        }
        
        return $out;
    }
    
    /**
     * 设置返回数组
     * @param $arrNoti 返回数据数组
     * @param $arrData 回源数组
     * @return array 源的所有数据
     */
    private function setReturnArrNoti(&$arrNoti, $arrData = array()){
        $out = array();
        
        if(!empty($arrData)) {
            foreach($arrData as $key => $val) {
                //ral请求数据
                if(isset($val['update_time'])) {
                    //判断客户端有请求才放入返回数组
                    /*if($this->judgeClientVersion($key)) {
                        $arrNoti[$key] = intval($val['update_time']);
                    }*/
                    
                    $arrNoti[$key] = intval($val['update_time']);
                    
                    //Edit by fanwenli on 2019-08-07, add relate proto's timestamp
                    if(isset($this->ral_multi_req_relate[$key]) && !empty($this->ral_multi_req_relate[$key])) {
                        foreach($this->ral_multi_req_relate[$key] as $relate_proto_name) {
                            //timestamp exists than add them into noti array timestamp
                            if(isset($arrData[$relate_proto_name]['update_time'])) {
                                $arrNoti[$key] += intval($arrData[$relate_proto_name]['update_time']);
                            }
                        }
                    }
                    
                    //所有源数据
                    $out[$key] = $arrNoti[$key];
                } else {
                    //判断客户端有请求才放入返回数组
                    /*if($this->judgeClientVersion($key)) {
                        $arrNoti[$key] = intval($val);
                    }*/
                    
                    //如果返回结果为数组，则返回其中最大值;如果是单个值，则返回自身
                    $arrNoti[$key] = intval(max($val));
                    
                    //所有源数据
                    $out[$key] = $arrNoti[$key];
                }
            }
            
            //ral请求中，如果有未获取到proto的情况，则设置版本号为预先设置的-1，防止错误请求打穿缓存回源
            if(!empty($this->ral_multi_req)) {
                foreach($this->ral_multi_req as $val) {
                    if(!isset($arrNoti[$val])) {
                        $arrNoti[$val] = intval($this->message_version_with_no_data);
                        
                        $out[$val] = $arrNoti[$val];
                    }
                }
            }
            
            //database请求中，如果有未获取到的情况，则设置版本号为预先设置的-1，防止错误请求打穿缓存回源
            if(!empty($this->dbx_phaster_req)) {
                foreach($this->dbx_phaster_req as $key => $val) {
                    if(!isset($arrNoti[$key])) {
                        $arrNoti[$key] = intval($this->message_version_with_no_data);
                        
                        $out[$key] = $arrNoti[$key];
                    }
                }
            }
        }
        
        return $out;
    }
    
    /**
     * 设置Apc缓存数组以及设置redis单条缓存
     * @param $arrApcCache apc缓存数组
     * @param $arrData 增量数组
     */
    private function setCacheNoti(&$arrApcCache, $arrIncrDdata = array()){
        if(!empty($arrIncrDdata)) {
            foreach($arrIncrDdata as $key => $val) {
                //set apc with json
                $arrApcCache[$key] = $val;

                //set redis cache
                $obj = new CacheVersionSwitchScope($this->redis_cache, $key);
                $this->redis_cache->set($key, $val, $this->redis_cache_key_expired_time);
                unset($obj);
            }

        }
    }
    
    /**
     * 设置dbx cache key
     * 
     * @return array
     */
    private function setDbxCacheKey(){
        $arrDbxCacheKey = array();
        if(!empty($this->dbx_phaster_req)) {
            foreach($this->dbx_phaster_req as $key => $val) {
                if(isset($val['cache_pre']) && trim($val['cache_pre']) != '') {
                    $arrDbxCacheKey[] = Util::getCacheVersionPrefix(trim($val['cache_pre'])) . $key;
                } else {
                    $arrDbxCacheKey[] = $key;
                }
            }
        }
        
        return $arrDbxCacheKey;
    }

    /**
     * 将过期的数据过滤
     * @return array 返回未过期的数据
     */
    private function filterExpiredData($waitCheckDatas) {
        if(empty($this->dbx_phaster_req)) {
            return;
        }
        foreach($this->dbx_phaster_req as $key => $val) {
            if(isset($val['cache_pre']) && trim($val['cache_pre']) != '') {
                if(!isset($waitCheckDatas[$key])) {
                    continue;
                }
                $newestVersion = Util::getCacheVersionPrefix(trim($val['cache_pre']));
                // 如果redis中的缓存比最新的版本低， 则删除低版本数据
                if(intval($waitCheckDatas[$key]) < intval($newestVersion)) {
                    unset($waitCheckDatas[$key]);
                }
            }
        }
        return $waitCheckDatas;
    }
}
