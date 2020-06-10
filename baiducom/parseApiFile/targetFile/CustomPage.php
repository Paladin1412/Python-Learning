<?php
/***************************************************************************
 *
 * Copyright (c) 2019 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use tinyESB\util\ClassLoader;
use utils\CustLog;
use utils\GFunc;
use utils\Util;
use models;


/**
 * flutter自定义页面布局及数据下发接口
 * Class CustomPage
 * Created on 2019-09-12 11:15
 * Created by zhoubin05
 * @path("/custom_page/")
 */
class CustomPage
{

    //内存缓存实例
    private $arrSingletonMap = array();


    /**
     * 函数描述
     * @param $strKey
     * @return mixed|null
     */
    private function singletonGet($strKey) {
        if(isset($this->arrSingletonMap[$strKey])) {
            return $this->arrSingletonMap[$strKey];
        }
        return null;
    }

    /**
     * 函数描述
     * @param $strKey
     * @param $mixValue
     * @return
     */
    private function singletonSet($strKey, $mixValue) {
        $this->arrSingletonMap[$strKey] = $mixValue;
    }


    /**
     * 获取页面缓存前缀
     *
     * amis后台编辑某页面后，会根据page_mark保存一个时间戳到缓存，这个就是页面Version(最后更新时间)
     * v5api获取这个时间作为缓存key的前缀，以保证后台更新这个key的时候，前端可以离开拿到最新数据
     * @param $strPageMark
     * @return bool|mixed|string
     */
    function getPageVersionCachePrefix($strPageMark) {
        $strCacheKey = Gfunc::getGlobalConf("env") .'_custompage_page_cachekey_prefix_'.$strPageMark;

        $mixCacheData = $this->singletonGet($strCacheKey);

        if(null === $mixCacheData) {
            //根据page_mark获取这个页面的缓存前缀
            //这个key的保存是在amis后台做的
            $mixCacheData = GFunc::getCacheInstance()->getNoCacheVersion($strCacheKey);
            if(null == $mixCacheData || false === $mixCacheData) {
                $this->singletonSet($strCacheKey, '');
            } else {
                $this->singletonSet($strCacheKey, $mixCacheData);
            }
        }

        return $mixCacheData;

    }

    /**
     * @route({"POST", "/layout"})
     * @param({"strPageMark", "$._POST.page_mark"}) string 页面标识
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    function getLayout($strPageMark) {

        $arrResult = Util::initialClass();

        try {

            //只获取符合条件的第一条数据（即过滤条件过滤后，update_time最新的page, update_time一样则获取page_id较大的）
            $arrCurrentPage = $this->getBestPageByPageMark($strPageMark);

            if(empty($arrCurrentPage) || intval($arrCurrentPage['page_id']) <= 0) {

                return  Util::returnValue($arrResult) ;

            } else {

                $arrPageData = $this->getPageDataByPageIdAndBuildModule($arrCurrentPage['page_id'], $strPageMark);

                if(!empty($arrPageData)) {

                    //根据类型处理
                    //如果是多分页页面，直接解析
                    if('multi_page' === $arrPageData['page_type']) {
                        $arrMultiPage = is_array(json_decode($arrPageData['page_data'], true)) ?
                            json_decode($arrPageData['page_data'], true) : array();

                        $arrPageData['page_data'] = new stdClass(); //共通页面内容可以这里获取，目前暂无

                        $arrFormatedPageData = array();

                        foreach ($arrMultiPage['multi_page'] as $k => $v) {
                            if(isset($v['page_mark'])) {
                                //这样做的目的是为了保证指定的page_mark 是当前客户端可以访问的
                                //根据page_mark选择合适的页面下发(区别在于page_mark虽然一样，但是page_id和page_title不一样)
                                $arrPage = $this->getBestPageByPageMark($v['page_mark']);

                                if(!empty($arrPage)) {

                                    $arrTmpPage = array(
                                        'page_id' => $arrPage['page_id'],
                                        'page_mark' => $arrPage['page_mark'],
                                        'page_title' => $arrPage['page_title'],
                                    );

                                    array_push($arrFormatedPageData, $arrTmpPage);

                                    if(!empty($arrMultiPage['active_page']['page_mark']) &&
                                        $arrMultiPage['active_page']['page_mark'] == $arrPage['page_mark']) {
                                        $arrPageData['active_page'] = $arrPage['page_mark'];
                                    }
                                }
                            }
                        }


                        //当前激活页容错
                        if(empty($arrPageData['active_page']) && isset($arrFormatedPageData[0])) {
                            $arrPageData['active_page'] =   $arrFormatedPageData[0]['page_mark'];
                        }

                        $arrPageData['multi_page_marks'] = !empty($arrFormatedPageData) ?
                            $arrFormatedPageData : array();


                    } else if('normal' === $arrPageData['page_type']) {
                        //如果是普通页面, 获取所有page_module内容

                        $arrModules = $this->getAllPageModuleDataByPageId($arrPageData['page_id'], $strPageMark);

                        $arrPageData['page_data'] = new stdClass(); //共通页面内容可以这里获取，目前暂无

                        $arrPageData['page_layout_content'] = $arrModules;

                    }


                }



                $arrResult['data'] = $arrPageData;


            }

        } catch (Exception $e) {

            $strMsg = 'Message:' . $e->getMessage() .' Code:'. $e->getCode().PHP_EOL
                . ' File:' .$e->getFile() .' on Line:'.$e->getLine()  . PHP_EOL
                . '  trace info:'.$e->getTraceAsString();
            $arrResult['ecode'] = 1;
            $arrResult['emsg'] = $strMsg;

            CustLog::write('v5_custompage_layout_error_log', array(
                    'msg' => $strMsg,
                    'cuid' => trim($_GET['cuid']),
                    'request' => $_SERVER['REQUEST_URI'],
                    'post_param' => $_POST,
                    'response' => $arrResult,
                ),array('trace' => 1, 'trace_limit' => 6));

        }

        return  Util::returnValue($arrResult) ;


    }


    /**
     * 函数描述
     * @param $intPageId
     * @param $strPageMark
     * @return array|bool|mixed
     */
    public function getAllPageModuleDataByPageId($intPageId, $strPageMark) {

        $strCachePrefix = $this->getPageVersionCachePrefix($strPageMark);

        $intPageId = intval($intPageId);

        $strAllPageModuleDataCacheKey= $strCachePrefix .'_getAllPageModuleDataByPageId'.'_'.$intPageId;


        $arrAllPageModuleData = $this->singletonGet($strAllPageModuleDataCacheKey);


        if(null === $arrAllPageModuleData) {
            //获取所有符合条件的页面
            $arrAllPageModuleData = GFunc::cacheZget($strAllPageModuleDataCacheKey);

            if(false == $arrAllPageModuleData ||  null == $arrAllPageModuleData) {

                $objCustomPageModel = IoCload('models\\CustomPageModel');
                $arrAllPageModuleData = $objCustomPageModel->getPageModuleByPageId($intPageId);

                $arrAllPageModuleData = !empty($arrAllPageModuleData) && is_array($arrAllPageModuleData) ? $arrAllPageModuleData : array();

                foreach($arrAllPageModuleData as $k => $v) {
                    $arrTmp = json_decode($v['module_data'], true);
                    $arrAllPageModuleData[$k]['module_data'] = is_array($arrTmp) && !empty($arrTmp) ?
                        $arrTmp : new stdClass() ;
                }


                $intCacheTime = empty($arrAllPageModuleData) ? GFunc::getCacheTime('10secs') : GFunc::getCacheTime('30mins');

                GFunc::cacheZset($strAllPageModuleDataCacheKey, $arrAllPageModuleData, $intCacheTime);

            }


            $this->singletonSet($strAllPageModuleDataCacheKey, $arrAllPageModuleData);


        }



        return $arrAllPageModuleData;

    }




    /**
     * 根据$strPageMark返回最匹配客户端的一个页面
     * @param $strPageMark
     * @return array|mixed
     */
    public function getBestPageByPageMark($strPageMark) {

        $strCachePrefix = $this->getPageVersionCachePrefix($strPageMark);

        $strLayoutCacheKey = $strCachePrefix .'_getBestPageByPageMark'.'_'.$strPageMark;

        $arrAllData = $this->singletonGet($strLayoutCacheKey);

        if(null === $arrAllData) {

            $objCustomPageModel  = IoCload("models\\CustomPageModel");

            //获取所有符合条件的页面
            $arrAllData = GFunc::cacheZget($strLayoutCacheKey);

            if(false == $arrAllData ||  null == $arrAllData) {

                $arrAllData = $objCustomPageModel->getVaildPageInfoByPageMark($strPageMark);

                $arrAllData = !empty($arrAllData) && is_array($arrAllData) ? $arrAllData : array();

                $intCacheTime = empty($arrAllData) ? GFunc::getCacheTime('10secs') : GFunc::getCacheTime('30mins');

                GFunc::cacheZset($strLayoutCacheKey, $arrAllData, $intCacheTime);
            }

            $this->singletonSet($strLayoutCacheKey, $arrAllData);

        }


        //过滤条件筛选
        $objFilter = IoCload(\utils\ConditionFilter::class);
        $arrAllData = $objFilter->getFilterConditionFromDB($arrAllData, "filter_id");

        //只获取符合条件的第一条数据（即过滤条件过滤后，update_time最新的page, update_time一样则获取page_id较大的）
        $arrBestPage = array_shift($arrAllData);

        return is_array($arrBestPage) && !empty($arrBestPage) ? $arrBestPage : array();
    }


    /**
     * 获取的同时构建整个基础页面数据结构
     * 整个页面的基础数据一次构建完成，保证获取数据时候的效率
     * @param $intPageId
     * @param $strPageMark
     * @return
     */
    public function getPageDataByPageIdAndBuildModule($intPageId, $strPageMark) {
        $intPageId =  intval($intPageId);
        $strCachePrefix = $this->getPageVersionCachePrefix($strPageMark);

        //根据id获取layout数据   (为了page_mark能和获取到的数据保持绝对一致)
        $strPageDataCacheKey = $strCachePrefix .'_getPageDataByPageIdAndBuildModule'.'_'. $intPageId;

        $arrPageData = $this->singletonGet($strPageDataCacheKey);

        if(null === $arrPageData) {
            $arrPageData = GFunc::cacheZget($strPageDataCacheKey);
            if(false == $arrPageData ||  null == $arrPageData) {

                $objCustomPageModel = IoCload("models\\CustomPageModel");
                $arrPageData = $objCustomPageModel->getVaildPageInfoByPageId($intPageId);
                $arrPageData = !empty($arrPageData)  && is_array($arrPageData) ? $arrPageData : array();

                $intCacheTime = empty($arrPageData) ? GFunc::getCacheTime('10secs') :
                    GFunc::getCacheTime('30mins');


                GFunc::cacheZset($strPageDataCacheKey, $arrPageData, $intCacheTime);
            }

            $this->singletonSet($strPageDataCacheKey, $arrPageData);
        }



        return $arrPageData;
    }


    /**
     * 函数描述
     * @param $intPageId
     * @param $indModuleMark
     * @param $strPageMark
     * @return array|bool|mixed
     */
    public function getOnePageModuleDataDetail($intPageId, $strModuleMark, $strPageMark) {
        $intPageId =  intval($intPageId);
        $strCachePrefix = $this->getPageVersionCachePrefix($strPageMark);

        //根据id获取layout数据   (为了page_mark能和获取到的数据保持绝对一致)
        $strPageModuleDataCacheKey = $strCachePrefix .'_getOnePageModuleDataDetail'.'_'. $intPageId.'_' . $strModuleMark;

        $arrPageModuleData =  $this->singletonGet($strPageModuleDataCacheKey);

        if(null === $arrPageModuleData) {
            $arrPageModuleData = GFunc::cacheZget($strPageModuleDataCacheKey);
            if(false == $arrPageModuleData ||  null == $arrPageModuleData) {

                $objCpmm = IoCload("models\\CustomPageModuleModel");
                $arrPageModuleData = $objCpmm->getFullModuleData($intPageId, $strModuleMark);
                $arrPageModuleData = !empty($arrPageModuleData) && is_array($arrPageModuleData)  ? $arrPageModuleData : array();

                $objRgmm = IoCload("models\\ResourceGroupMapModel");

                foreach($arrPageModuleData as $k => $v) {
                    //如果是资源组
                    if(1 == intval($v['module_resource_type']) && intval($v['resource_group_id']) > 0) {
                        $arrPageModuleData[$k]['resource_group_data'] =
                            $objRgmm->getVaildResourceByResourceGroupId(intval($v['resource_group_id']));
                    }
                }

                $intCacheTime = empty($arrPageModuleData) ? GFunc::getCacheTime('10secs') :
                    GFunc::getCacheTime('30mins');

                GFunc::cacheZset($strPageModuleDataCacheKey, $arrPageModuleData, $intCacheTime);
            }

            $this->singletonSet($strPageModuleDataCacheKey, $arrPageModuleData);
        }


        return $arrPageModuleData;
    }


    /**
     * @route({"POST", "/getdata"})
     * @param({"strModuleInfo", "$._POST.module_info"}) 客户端POST的页面模块请求
     *
     * 数据下发一定要和layout保持一致
     *
     * 比如 A，B两个用户，同样要获取page_mark=skin_homepage的页面
     * 由于A,B客户端的数据不一样，所以实际两人拿到的skin_homepage也可能是不一样的（包括布局，数据）
     *
     *
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    function getData($strModuleInfo) {
        try{

            $arrModuleInfo =  json_decode($strModuleInfo, true);

            $arrResult = Util::initialClass();

            if(is_array($arrModuleInfo)) {

                $arrReturn = array();



                $objFilter = IoCload(\utils\ConditionFilter::class);

                $objSkinThemes = IoCload("models\\SkinthemeModel");
                $arrFullSkin = $objSkinThemes->fullList();

                $arrVaildSkinIds = array();

                foreach($arrFullSkin as $arrSkinId) {
                    $arrVaildSkinIds[$arrSkinId['id']] = true;
                }

                foreach($arrModuleInfo as $mk => $mv) {

                    if(isset($mv['page_mark'])) {

                        //根据page_mark获取页面布局数据,即最符合客户端的页面
                        //如果以后需要强匹配，则可是使用page_id来获取module
                        $arrServerPageData = $this->getBestPageByPageMark($mv['page_mark']);

                        if(empty($arrServerPageData)) {
                            continue;
                        }

                        $arrServerPageData['module_data'] =
                                $this->getAllPageModuleDataByPageId($arrServerPageData['page_id'], $mv['page_mark']);


                        //只有普通页才需要继续解析
                        if(!empty($arrServerPageData) && 'normal' == $arrServerPageData['page_type']) {

                            $arrPageModuleInfo = array();

                            foreach($arrServerPageData['module_data'] as $k => $v) {
                                if($mv['module_mark'] === $v['module_mark']) {
                                    $arrPageModuleInfo = $v;
                                }
                            }

                            //只有在页面对应的数据中包含客户端请求的module_mark才继续处理
                            if(empty($arrPageModuleInfo)) {
                                continue;
                            }


                            $arrModuleData = $this->getOnePageModuleDataDetail(intval($arrServerPageData['page_id']),
                                $mv['module_mark'], $mv['page_mark']);

                            //过滤条件过滤（对根节点上的「资源」和「资源组」）
                            $arrModuleData = $objFilter->getFilterConditionFromDB($arrModuleData, "filter_id");

                            //当前模块是否要对皮肤资源排序
                            $bolSortSkin =  is_array($arrPageModuleInfo['module_data'])
                            && 1 === $arrPageModuleInfo['module_data']['sort_skin'] ? true : false;

                            //存放需要排序的皮肤资源
                            $arrSkinSort = array();

                            $arrFullData = array();

                            //需要按照实时下载数排序的皮肤id
                            $arrSortIds = array();

                            //单页数据条数
                            $intPageSize = 10;

                            foreach($arrModuleData as $k => $v) {
                                //对资源组中内容过滤
                                if(1 === intval($v['module_resource_type']) &&  is_array($v['resource_group_data'])
                                    && !empty($v['resource_group_data'])) {
                                    $arrTmpResGrpData = $v;
                                    $arrTmpResGrpData['resource_group_data'] =
                                        $objFilter->getFilterConditionFromDB($v['resource_group_data'], "filter_id");

                                    //过滤资源组中「皮肤型资源」
                                    foreach($arrTmpResGrpData['resource_group_data'] as $strRgdKey => $arrRgdVal) {
                                        //首先检测这个资源是否是皮肤资源
                                        $arrMRtmp = json_decode($arrRgdVal['resource_target_info'], true);

                                        //有这个字段就是皮肤「资源」
                                        if(isset($arrMRtmp['skin_select']['id']))
                                        {
                                            if(!isset($arrVaildSkinIds[$arrMRtmp['skin_select']['id']])) {
                                                //如果skin_id没有在$arrVaildSkinIds中
                                                //则说明对于当前客户端该皮肤是不下发的
                                                unset($arrTmpResGrpData['resource_group_data'][$strRgdKey]);
                                            }
                                        }
                                    }

                                    if(empty($arrTmpResGrpData['resource_group_data'])) {
                                        //如果资源组内所有资源都被过滤掉了，这个资源组就是无效的，故删除
                                        continue;
                                    }

                                    $arrFullData[] = $arrTmpResGrpData;

                                } else if(2 === intval($v['module_resource_type'])) {

                                    //获取「全部皮肤」, 每个人拿到的全部皮肤可能不一样

                                    if(is_array($arrFullSkin)) {
                                        $arrFullData = array_merge($arrFullData, $arrFullSkin);
                                    }

                                } else {

                                    //首先检测这个资源是否是皮肤资源
                                    $arrMRtmp = json_decode($v['resource_target_info'], true);

                                    //有这个字段就是皮肤「资源」
                                    if(isset($arrMRtmp['skin_select']['id']))
                                    {
                                        if(!isset($arrVaildSkinIds[$arrMRtmp['skin_select']['id']])) {
                                            //如果skin_id没有在$arrVaildSkinIds中
                                            //则说明对于当前客户端该皮肤是不下发的
                                            continue;
                                        }
                                    }

                                    if( true === $bolSortSkin) {

                                        //有这个字段就是皮肤「资源」
                                        if(isset($arrMRtmp['skin_select']['id'])) {

                                            //原始数据
                                            $arrSkinSort[] = $v;
                                            //皮肤存入需排序数组
                                            array_push($arrSortIds, $arrMRtmp['skin_select']['id']);

                                            //占位，以方便皮肤数据排序后代入，减少处理复杂度
                                            $arrFullData[] = array('skin_position' => 1);
                                            continue;
                                        }

                                    }
                                    //其他情况直接放入结果数组
                                    $arrFullData[] = $v;
                                }

                            }

                            if(true === $bolSortSkin && !empty($arrSortIds)) {

                                //如果是需要根据下载量实时排序的模块，且有需要排序的数据，单分页1页100条
                                //这是为了尽量少分页，以减少分页带来重复数据（由于数据变动导致）
                                $intPageSize = 100;

                                $bolSuccess = false;

                                $arrSkinSortedListByIds = $objSkinThemes->getMultiDownloadTimes($arrSortIds, $bolSuccess);

                                $arrSkinSortedSrcData = array();

                                //将原始数据按照排序数据顺序排序
                                foreach($arrSkinSortedListByIds as $k => $v) {
                                    $intSkinId = $v['skin_id'];

                                    foreach($arrSkinSort as $sk => $sv) {
                                        $arrMRtmp = json_decode($sv['resource_target_info'], true);
                                        if(!empty($arrMRtmp['skin_select']['id']) && $arrMRtmp['skin_select']['id'] == $intSkinId) {
                                            $arrSkinSortedSrcData[] = $sv;
                                            unset($arrSkinSort[$sk]);
                                        }
                                    }
                                }

                                foreach($arrFullData as $k => $v) {
                                    if(1 === $v['skin_position']) {
                                        //获取已排序的最高位
                                        $arrShiftTmp = array_shift($arrSkinSortedSrcData);

                                        if(!empty($arrShiftTmp)) {
                                            //有值则替换占位数据
                                            $arrFullData[$k] = $arrShiftTmp;
                                        } else {
                                            //没有值则删除占位，理论上不会进入，占位和已排序数据数量应该是绝对一致的
                                            unset($arrFullData[$k]);
                                        }

                                    }
                                }
                            }

                            // 资源池限制展示个数
                            if (isset($arrPageModuleInfo['module_data'])
                                && is_array($arrPageModuleInfo['module_data'])
                                && isset($arrPageModuleInfo['module_data']['show_resource_number'])
                                && !empty($arrPageModuleInfo['module_data']['show_resource_number'])) {
                                $show_resource_number = intval($arrPageModuleInfo['module_data']['show_resource_number']);
                                if ($show_resource_number < count($arrFullData)) {
                                    $arrFullData = array_slice($arrFullData, 0, $show_resource_number);
                                }
                            }

                            //开始组织数据


                            //对应page_mark的page_id，客户端上传的和服务端当前获取的可能不一致
                            //此时要标记字段reflush_layout=1 ,表示告诉客户端需要更新这个页面的layout
                            $arrPageModuleInfo['reflush_layout'] = $mv['page_id'] != $arrServerPageData['page_id'] ? 1 : 0;
                            $arrPageModuleInfo['page_id'] = $arrServerPageData['page_id'];
                            $arrPageModuleInfo['global_id'] = 'custompage_page#' . $arrServerPageData['page_id'];
                            $arrPageModuleInfo['page_mark'] = $arrServerPageData['page_mark'];
                            $arrTmpModuleDataCol = is_array($arrPageModuleInfo['module_data']) ?
                                $arrPageModuleInfo['module_data'] : array();

                            $arrPageModuleInfo['module_data'] = array_merge($arrTmpModuleDataCol,
                                    array(
                                        'global_id' => 'custompage_page_module#'.$arrServerPageData['page_id']
                                        .'#'.$arrPageModuleInfo['module_id'] .'_'
                                        .'0' //(intval($arrPageModuleInfo['module_sequnce']) +1)
                                    )
                                );


                            $arrPageModuleInfo = array_merge($arrPageModuleInfo,
                                $this->getPageNumDataAndFormat(intval($arrServerPageData['page_id']),
                                    $arrPageModuleInfo, $arrFullData, $mv['page_num'], $intPageSize));

                            $arrReturn[] = $arrPageModuleInfo;


                        }


                    }

                }


                $arrResult['data'] = $arrReturn;
            }


        } catch (Exception $e) {

            $strMsg = 'Message:' . $e->getMessage() .' Code:'. $e->getCode().PHP_EOL
                . ' File:' .$e->getFile() .' on Line:'.$e->getLine()  . PHP_EOL
                . '  trace info:'.$e->getTraceAsString();

            $arrResult['ecode'] = 1;
            $arrResult['emsg'] = $strMsg;

            CustLog::write('v5_custompage_getdata_error_log', array(
                'msg' => $strMsg,
                'cuid' => trim($_GET['cuid']),
                'request' => $_SERVER['REQUEST_URI'],
                'post_param' => $_POST,
                'response' => $arrResult,
            ),array('trace' => 1, 'trace_limit' => 6));


        }

        return  Util::returnValue($arrResult) ;


    }


    /**
     * 获取分页数据并格式化
     * @param $intPageId
     * @param $arrModuleInfo
     * @param $arrFullData
     * @param $intPageNum
     * @param int $intPageSize
     * @return array
     */
    function getPageNumDataAndFormat($intPageId, $arrModuleInfo, $arrFullData, $intPageNum, $intPageSize = 10) {


        $intPageNum = intval($intPageNum) > 1 ? intval($intPageNum) : 1;

        $arrReturnData = array();
        $intSum = count($arrFullData);

        $intTotalPage = ceil($intSum / $intPageSize);

        if($intPageNum > $intTotalPage) {
            $intPageNum = $intTotalPage;
        }

        $arrReturnData['page_num'] = $intPageNum;
        $arrReturnData['items_count'] = $intSum;
        $arrReturnData['page_count'] = $intTotalPage;
        $arrReturnData['is_last_page'] = $intPageNum == $intTotalPage ? 1 : 0;

        $arrReturnData['items'] = array();


        $intGetStart = ($intPageNum - 1) * $intPageSize;

        $arrTmpPageData = array_slice($arrFullData, $intGetStart, $intPageSize);



        //在客户端的展示位置（在模块内排第几个）
        $intShowNum = ($intPageNum - 1) * $intPageSize + 1;

        foreach($arrTmpPageData as $k => $v) {
            $arrTmp = $this->resourceDataFormat($intPageId, $arrModuleInfo, $v, $intShowNum);

            //resourceDataFormat中的skinDataFormatToResource中的summary()函数在沙盒偶现获取到空值
            //从而导致显示一个空白的资源，在这里做一层容错
            //理论上数据库运行正常是不应该有上面情况发生，由于先获取的fullList再获取的summary()，两者where条件一样
            //所以只要fullList有值，summary一定也有值
            if(!empty($arrTmp)) {
                $arrReturnData['items'][] = $arrTmp;
            }

            //位置顺序按照原样，不补位
            $intShowNum++;

        }

        return $arrReturnData;

    }


    function resourceDataFormat($intPageId, $arrModuleInfo, $arrOneResource, $intShowNum = null) {

        $intModuleId = intval($arrModuleInfo['module_id']);

        if(count($arrOneResource) === 1 && isset($arrOneResource['id'])) {
            //只有一个数据，且这个字段是'id' ，这个就是「全部皮肤」中的皮肤
            return Util::skinDataFormatToResource($arrOneResource, $intPageId, $intModuleId, true,$intShowNum, $arrModuleInfo  );
        }

        $arrReturn = array();

        switch (intval($arrOneResource['module_resource_type'])) {
            case 0:
                $arrReturn = Util::resourceDbDataFormat($arrOneResource, $intPageId, $arrModuleInfo, $intShowNum);
                //资源
                break ;
            case 1:

                //资源组
                $arrReturn['type'] = intval($arrOneResource['module_resource_type']);
                $arrReturn['global_id'] = "custompage_page_module_resourcegroup#".$intPageId.
                    '#'.$intModuleId .'_'.'0' //(intval($arrModuleInfo['module_sequnce']) + 1) //inputserver-2668 [Feature]
                    .'#'.$arrOneResource['resource_group_id'] ;

                if(null !== $intShowNum) {
                    $arrReturn['global_id'] .= '_' . '0'; //$intShowNum; //inputserver-2668 [Feature]
                } else {
                    $arrReturn['global_id'] .= '_'. '0'; //(intval($arrOneResource['resource_sequnce']) +1);//inputserver-2668 [Feature]
                }

                $arrReturn['items'] = array();
                foreach($arrOneResource['resource_group_data'] as $k => $v) {
                    $arrTmp = $this->resourceDataFormat($intPageId, $arrModuleInfo, $v,$intShowNum);
                    if(!empty($arrTmp)) {
                        $arrReturn['items'][] = $arrTmp;
                    }

                }

                //如果整个资源组格式化后都没数据则整个资源组都不要
                if(empty($arrReturn['items'])) {
                    return array();
                }

                break;
            case 2:
                //全部皮肤 理论上全部皮肤已经由上层处理过了，这里不应该会有这样的数据
                break ;
            default :
                $arrReturn = Util::resourceDbDataFormat($arrOneResource, $intPageId, $arrModuleInfo, $intShowNum);
                break;
        }


        return $arrReturn;

    }


}


