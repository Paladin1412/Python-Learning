<?php

/* * *************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 *
 * ************************************************************************ */

use models\CommercializeCctvNewsModel;
use utils\KsarchRedis;
use models\FilterModel;
use utils\Util;
use utils\GFunc;
use models\ActivityAdvertisementModel;
use models\ImeResourceModel;

/**
 * 数据下载通用接口
 *
 * @author fanwenli
 * @path("/data/")
 */
class Data {

    /** @property 内部缓存key */
    private $le_data_cache_key;

    /** @property 内部缓存时长 */
    public $intCacheExpired;

    /**
     *
     * 乐视6.0智能回复数据文件服务端更新
     *
     * @route({"POST", "/le"})
     * @param({"strVersion","$._POST.version"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
     *      ]
     * }
     */
    public function getLeContent($strVersion) {
        //$out = array('data' => array());
        //输出格式初始化
        $out = Util::initialClass(false);

        $strVersion = intval($strVersion);

        $cacheKey = $this->le_data_cacheKey;

        $cacheKeyVersion = $cacheKey . '_version';

        $version = GFunc::cacheGet($cacheKeyVersion);
        $le = GFunc::cacheGet($cacheKey);
        if ($le === false) {
            $le = GFunc::getRalContent('le_data_recover');

            //set status, msg & version
            $out['ecode'] = GFunc::getStatusCode();
            $out['emsg'] = GFunc::getErrorMsg();
            $version = intval(GFunc::getResVersion());

            //设置缓存
            GFunc::cacheSet($cacheKey, $le, $this->intCacheExpired);

            //设置版本缓存
            GFunc::cacheSet($cacheKeyVersion, $version, $this->intCacheExpired);
        }

        $out['version'] = intval($version);

        //过滤数据
        if (!empty($le)) {
            $filterModel = new FilterModel();
            $le = $filterModel->getFilterByArray($le);
        }

        $link = '';
        //整理数据
        if (!empty($le)) {
            foreach ($le as $val) {
                if (isset($val['file']) && $val['file'] != '') {
                    $link = $val['file'];
                    break;
                }
            }
        }

        if ($link != '') {
            $out['data'] = bd_B64_encode($link, 0);
        }

        return Util::returnValue($out, false);
    }
    
    /**
     *
     * 广告数据下发
     * @desc http://agroup.baidu.com/inputserver/md/article/2155751
     * @route({"POST", "/advertisement"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": "....."
     * 
     * }
     */
    public function getAdvData() {
        //输出格式初始化
        $out = Util::initialClass(false);
        
        //global_id
        $strGlobalId = isset($_POST['strGlobalId']) ? trim($_POST['strGlobalId']) : '';
        //B64 decode
        $strGlobalId = bd_B64_Decode($strGlobalId, 0);
        if($strGlobalId !== '') {
            $arrGlobalId = explode('#', $strGlobalId);

            //get source name & id
            if(isset($arrGlobalId[0]) && isset($arrGlobalId[1])) {
                $strSource = trim($arrGlobalId[0]);
                $intId = intval($arrGlobalId[1]);
                
                //Edit by fanwenli on 2019-12-13, add fengou item, format activityadvertisement#fengou#37265165720
                if(isset($arrGlobalId[2])) {
                    $strType = trim($arrGlobalId[1]);
                    $intId = intval($arrGlobalId[2]);
                } else {
                    $strType = '';
                }
                
                //Edit by fanwenli on 2019-12-13, add key field
                $strCacheKey = 'v5_data_advertisement_global_info_' . $strSource . $strType . '_' . $intId;
                $arrResult = GFunc::cacheZget($strCacheKey);
                if($arrResult === false) {
                    $arrResult = array();
                    //广告主
                    $strProvider = '';
                    //广告起止时间
                    $strBegTime = '';
                    $strEndTime = '';
                    //广告名称
                    $strTitle = '';
                    //广告动作
                    $arrAction = array();

                    switch($strSource) {
                        //面板头图及 App 弹窗 以及粉购
                        case 'activityadvertisement':
                            //Edit by fanwenli on 2019-12-13, add fengou item
                            if ('央视新闻' == $strType) {
                                $objCommercializeCctvNewsModel = new CommercializeCctvNewsModel();
                                $cond = array(
                                    'id = ' => $intId,
                                );
                                $result = $objCommercializeCctvNewsModel->select(array('title', 'source', 'start_time', 'end_time'), $cond);
                                if (isset($result[0]['title'])) {
                                    $strTitle = trim($result[0]['title']);
                                }
                                switch ($result[0]['source']) {
                                    case 1:
                                        $strProvider = '央视新闻人工配置';
                                        break;
                                    case 2:
                                        $strProvider = '央视新闻';
                                        break;
                                    default:
                                        $strProvider = '';
                                }
                                $strBegTime = !empty($result[0]['start_time']) ? date('Y-m-d H:i:s', $result[0]['start_time']) : '';
                                $strEndTime = !empty($result[0]['end_time']) ? date('Y-m-d H:i:s', $result[0]['end_time']) : '';
                            } else if ($strType != '') {
                                $objActivityAdvertisementModel = new ActivityAdvertisementModel();
                                $arrItem = array('title','name');
                                $result = $objActivityAdvertisementModel->select($arrItem, array('global_id="' . $strGlobalId . '"'));
                                if(!empty($result)) {
                                    if (isset($result[0]['name'])) {
                                        $strTitle = trim($result[0]['name']);
                                    }

                                    if (isset($result[0]['title'])) {
                                        $strProvider = trim($result[0]['title']);
                                    }
                                }
                            } else {
                                //从资源服务获取
                                $searchStr = urlencode('{"id":' . $intId . '}');
                                $resUrl = '/res/json/input/r/online/activity_advertisement/';
                                $strQuery = 'onlycontent=1&limit=1&search=' . $searchStr;
                                $result = Util::getResource($resUrl, $strQuery);

                                //Edit by fanwenli on 2019-11-18, return offline result
                                if (empty($result)) {
                                    $resUrl = '/res/json/input/r/offline/activity_advertisement/';
                                    $result = Util::getResource($resUrl, $strQuery);
                                }

                                if (!empty($result)) {
                                    foreach ($result as $val) {
                                        //Edit by fanwenli on 2019-11-13, add title and provider
                                        if (isset($val['title'])) {
                                            $strTitle = trim($val['title']);
                                        }

                                        if (isset($val['adv_owner'])) {
                                            $strProvider = trim($val['adv_owner']);
                                        }

                                        switch ($val['ad_type']) {
                                            case 1:
                                                $arrAction[] = '运营活动面板头图';
                                                break;
                                            case 2:
                                                $arrAction[] = '运营活动弹窗';
                                                break;
                                        }

                                        switch ($val['trigger_condition']) {
                                            case 1:
                                                $arrAction[] = '起面板触发';
                                                break;
                                            case 2:
                                                $arrAction[] = '文字上屏某query或语音上屏某query触发';
                                                break;
                                            case 3:
                                                $arrAction[] = '仅文字上屏某query';
                                                break;
                                            case 4:
                                                $arrAction[] = '仅语音上屏某query触发';
                                                break;
                                        }
                                        break;
                                    }
                                }
                            }
                            break;
                        case 'activity':
                            $baseDbxModel = IoCload("models\\BaseDbxModel");
                            $sql = 'select * from activity where id = ' . $intId;
                            $result = $baseDbxModel->query($sql);
                            
                            if(!empty($result)) {
                                $strBegTime = date('Y-m-d H:i:s', $result[0]['begin']);
                                $strEndTime = date('Y-m-d H:i:s', $result[0]['end']);
                                $strTitle = trim($result[0]['name']);
                                
                                $arrAction[] = '支持平台：' . trim($result[0]['platform']);
                                switch($result[0]['publish_type']) {
                                    case 'white':
                                        $arrAction[] = '白名单发布';
                                        break;
                                    case 'all':
                                        $arrAction[] = '全量发布';
                                        break;
                                }
                                
                                switch($result[0]['is_color_wash']) {
                                    case 0:
                                        $arrAction[] = '不刷色';
                                        break;
                                    case 1:
                                        $arrAction[] = '刷色';
                                        break;
                                }
                                
                                switch($result[0]['action_type']) {
                                    case 'web':
                                        $arrAction[] = '点击类型：web';
                                        break;
                                    case 'tab':
                                        $arrAction[] = '点击类型：tab';
                                        break;
                                    case 'app_detail':
                                        $arrAction[] = '点击类型：展示app详情页';
                                        break;
                                }
                                
                                switch($result[0]['show_type']) {
                                    case 1:
                                        $arrAction[] = '展示类型：熊头菜单';
                                        break;
                                    case 2:
                                        $arrAction[] = '展示类型：候选词展示';
                                        break;
                                    case 3:
                                        $arrAction[] = '展示类型：语音联想';
                                        break;
                                }
                                
                                switch($result[0]['switch']) {
                                    case 0:
                                        $arrAction[] = '无红点无熊头变形';
                                        break;
                                    case 1:
                                        $arrAction[] = '仅红点';
                                        break;
                                    case 2:
                                        $arrAction[] = '红点+熊头变形';
                                        break;
                                }
                            }
                            break;
                        case 'advertisement':
                            //从资源服务获取
                            $searchStr = urlencode('{"ad_id":' . $intId . '}');
                            $resUrl = '/res/json/input/r/online/advertisement/';
                            $strQuery = 'onlycontent=1&limit=1&search=' . $searchStr;
                            $result = Util::getResource($resUrl, $strQuery);

                            //Edit by fanwenli on 2019-11-18, return offline result
                            if(empty($result)) {
                                $resUrl = '/res/json/input/r/offline/advertisement/';
                                $result = Util::getResource($resUrl, $strQuery);
                            }

                            if(!empty($result)) {
                                foreach($result as $val) {
                                    switch($val['ad_provider']) {
                                        case 1:
                                            $strProvider = 'baidu';
                                            break;
                                    }

                                    $strBegTime = date('Y-m-d H:i:s', $val['sttime']);
                                    $strEndTime = date('Y-m-d H:i:s', $val['exptime']);
                                    $strTitle = trim($val['title']);

                                    if(isset($val['click_ad']['click_type'])) {
                                        switch ($val['click_ad']['click_type']) {
                                            case 'none':
                                                $arrAction[] = '不响应';
                                                break;
                                            case 'download':
                                                $arrAction[] = '点击直接下载';
                                                break;
                                            case 'website':
                                                $arrAction[] = '点击打开网址';
                                                break;
                                            case 'details':
                                                $arrAction[] = '点击显示详情页';
                                                break;
                                            case 'shortcut':
                                                $arrAction[] = '点击生成快捷方式';
                                                break;
                                            case 'banner_for_lite':
                                                $arrAction[] = '手助lite对应配置项';
                                                break;
                                            case 'third_app':
                                                $arrAction[] = '点击跳转第三方应用';
                                                break;
                                        }
                                    }

                                    break;
                                }
                            }
                            break;
                        case 'e_commerce_token':
                            $baseDbxModel = IoCload("models\\BaseDbxModel");
                            $sql = 'select * from input_ecommerce_token_conf where id = ' . $intId;
                            $result = $baseDbxModel->query($sql);

                            if(!empty($result)) {
                                $strBegTime = $result[0]['start_time'];
                                $strEndTime = $result[0]['expire_time'];
                                $strTitle = trim($result[0]['title']);
                                $strProvider = '百度';
                            }
                            break;
                        case 'custompage':
                            switch ($strType) {
                                case 'resource':
                                    $baseDbxModel = IoCload("models\\BaseDbxModel");
                                    $sql = 'select * from ime_resource where resource_id = ?';
                                    $result = $baseDbxModel->queryp($sql, array($intId));
                                    if(!empty($result)) {
                                        $strBegTime = date('Y-m-d H:i:s', $result[0]['resource_stime']);
                                        $strEndTime = date('Y-m-d H:i:s', $result[0]['resource_etime']);
                                        $strTitle = trim($result[0]['resource_desc']);
                                        $strProvider = Util::getResourceProviderById($result[0]['resource_provider']);
                                    }
                                    break;

                            }


                            break;
                        case 'solidwindowads':
                            // 多个广告位, 逻辑一致，所以不处理$strType
                            $resourceModel = IoCload(ImeResourceModel::class);
                            // 获取所有状态的resource 资源
                            $result = $resourceModel->getResourceInfoByIds($intId, -1);
                            if(!empty($result)) {
                                $result = current($result);
                                $strBegTime = date('Y-m-d H:i:s', $result['resource_stime']);
                                $strEndTime = date('Y-m-d H:i:s', $result['resource_etime']);
                                $strTitle = trim($result['resource_desc']);
                                $strProvider = Util::getResourceProviderById($result['resource_provider']);
                            }
                            break;
                        case 'cloud_res':
                            //从资源服务获取
                            $searchStr = urlencode('{"id":' . $intId . '}');
                            $resUrl = '/res/json/input/r/online/cloud_res/';
                            $strQuery = 'onlycontent=1&limit=1&search=' . $searchStr;
                            $result = Util::getResource($resUrl, $strQuery);

                            //Edit by fanwenli on 2019-11-18, return offline result
                            if (empty($result)) {
                                $resUrl = '/res/json/input/r/offline/cloud_res/';
                                $result = Util::getResource($resUrl, $strQuery);
                            }
                            $currentRes = current($result);
                            if (is_array($currentRes)) {
                                $strProvider = '百度-默认';
                                $strBegTime = '-';
                                $strEndTime = '-';
                                $strTitle = (string)$currentRes['desc'];
                            }
                            break;
                    }
                    
                    $arrResult = array(
                        'id' => $intId,
                        'provider' => $strProvider,
                        'begin' => $strBegTime,
                        'end' => $strEndTime,
                        'title' => $strTitle,
                        'action' => $arrAction,
                    );
                    
                    //set cache content and cache time is 15mins
                    GFunc::cacheZset($strCacheKey, $arrResult, GFunc::getCacheTime('15mins'));
                }
                
                $out['data'] = $arrResult;
            }
        }

        return Util::returnValue($out, false);
    }
}

