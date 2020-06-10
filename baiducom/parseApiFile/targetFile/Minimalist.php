<?php

/* * *************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 *
 * ************************************************************************ */

use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use models\MinimalistModel;
use models\FilterModel;
use utils\Util;
use utils\GFunc;
use utils\ErrorMsg;

require_once __DIR__ . '/utils/CurlRequest.php';

/**
 * 极简数据接口
 *
 * @author fanwenli
 * @path("/minimalist/")
 */
class Minimalist {

    /** @property android内部缓存 voice key */
    private $android_voice_cache_key;

    /** @property ios内部缓存 voice key */
    private $ios_voice_cache_key;

    /** @property 内部缓存时长 */
    public $intCacheExpired;

    /** ecode */
    private $ecode = 0;

    /** version */
    private $version;

    /**
     *
     * 安卓数据下发接口
     *
     * @route({"GET", "/android"})
     * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
     *      ]
     * }
     */
    public function getAndroid($strVersion) {
        //输出格式初始化
        $out = Util::initialClass(false);

        $cache_key = $this->android_voice_cache_key;

        $voice = $this->getVoiceContent($cache_key);

        $arr = array();

        //整理数据
        if (!empty($voice)) {
            foreach ($voice as $val) {
                //外层元素，表示数据是属于场景化语音条还是极简语音条
                $bar_type = $val['android']['btype'];

                //Edit by fanwenli on 2017-02-21, there will be download different items with different type
                $tmp_arr = array();

                //Edit by fanwenli on 2017-03-01, get version
                $intVersion = Util::getVersionIntValue($strVersion);

                //get ctrid from array or just int
                if (is_array($val['android']['ctrid'])) {
                    //Edit by fanwenli on 2017-03-01, if version bigger than 7.4, return array. Otherwise, return int
                    if ($intVersion >= 7040000) {
                        $ctrid = array();
                        foreach ($val['android']['ctrid'] as $ctrid_key => $ctrid_val) {
                            $ctrid[$ctrid_key] = $ctrid_val['ctrid'];
                        }
                    } else {
                        $ctrid = $val['android']['ctrid'][0]['ctrid'];
                    }
                } else {
                    $ctrid = $val['android']['ctrid'];
                }

                //android信息
                $android_arr = array();
                switch ($bar_type) {
                    //场景化语音
                    case 1:
                        $android_arr = array(
                            'ctrid' => $ctrid,
                            'style' => $val['android']['style'],
                            's_text' => $val['android']['s_text'],
                            'interaction' => $val['android']['interaction'],
                            'action' => $val['android']['action'],
                            'link' => $val['android']['link'],
                        );
                        break;
                    //极简语音
                    case 2:
                        $android_arr = array(
                            'r_text' => $val['android']['r_text'],
                            'screen' => $val['android']['screen'],
                            'style' => $val['android']['style'],
                            's_text' => $val['android']['s_text'],
                        );
                        break;
                    //空格键语音
                    case 3:
                        $android_arr = array(
                            'ctrid' => $ctrid,
                            'action' => $val['android']['action'],
                            'link' => $val['android']['link'],
                        );
                        break;
                    //复合语音, Edit by fanwenli on 2017-11-15
                    case 4:
                        $android_arr = array(
                            'ctrid' => $ctrid,
                            'r_text' => $val['android']['r_text'],
                            'screen' => $val['android']['screen'],
                            'bar_array' => $val['android']['bar_array'],
                        );
                        break;
                    //懒人短语
                    case 5:
                        $android_arr = array(
                            'ctrid' => $ctrid,
                            'style' => $val['android']['style'],
                            's_text' => $val['android']['s_text'],
                            'lazy_id' => $val['android']['lazyId']
                        );
                        break;
                    //整句预测, Edit by fanwenli on 2019-05-09
                    case 6:
                        //Edit by fanwenli on 2019-05-28, add switch
                        $switch = 1;
                        if(isset($val['android']['switch'])) {
                            $switch = intval($val['android']['switch']);
                        }
                        
                        $android_arr = array(
                            'ctrid' => $ctrid,
                            'screen' => $val['android']['screen'],
                            'switch' => $switch,
                        );
                        break;
                }

                //判读有无设置包名以及获取到android信息与否
                if (isset($val['package_name']) && !empty($val['package_name']) && !empty($android_arr)) {
                    foreach ($val['package_name'] as $p_name_arr) {
                        //Edit by fanwenli on 2017-02-21, support return with multi packages
                        $package_name_arr = explode("\n", trim($p_name_arr));
                        foreach ($package_name_arr as $p_name) {
                            $p_name = trim($p_name);

                            //已设置过相应包名对应框属性
                            if (isset($arr[$bar_type][$p_name])) {
                                array_push($arr[$bar_type][$p_name], $android_arr);
                            } else {
                                $arr[$bar_type][$p_name] = array($android_arr);
                            }
                        }
                    }
                }
            }
        }

        $out['ecode'] = $this->ecode;
        $out['emsg'] = ErrorMsg::getMsg($this->ecode);
        $out['version'] = $this->version;

        if (empty($arr)) {
            $arr = new \stdClass();
        }

        $out['data'] = bd_B64_encode(json_encode($arr), 0);

        return Util::returnValue($out, false, true);
    }

    /**
     *
     * 苹果数据下发接口
     *
     * @route({"GET", "/ios"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn",
     *      ]
     * }
     */
    public function getIos() {
        //$out = array('data' => '');
        //输出格式初始化
        $out = Util::initialClass(false);

        $cache_key = $this->ios_voice_cache_key;

        $voice = $this->getVoiceContent($cache_key);

        $arr = array();

        //整理数据
        if (!empty($voice)) {
            foreach ($voice as $val) {
                //外层元素，表示数据是属于场景化语音条还是极简语音条
                $bar_type = $val['ios']['type'];

                //判读有无设置包名以及获取到ios信息与否
                $package_name_arr = array();
                if (isset($val['package_name']) && !empty($val['package_name']) && $bar_type > 0) {
                    foreach ($val['package_name'] as $p_name_arr) {
                        $tmp_arr = explode("\n", trim($p_name_arr));
                        foreach ($tmp_arr as $package_name) {
                            if (!in_array($package_name, $package_name_arr)) {
                                array_push($package_name_arr, $package_name);
                            }
                        }
                    }

                    //ios键盘类型以及回车键类型
                    if (is_array($val['ios']['key']) && !empty($val['ios']['key'])) {
                        $keyboard_filters = $val['ios']['key'];
                    } else {
                        $keyboard_filters = array();
                    }

                    //ios信息
                    $ios_arr = array(
                        'id' => $val['minimalist_voice_cand_id'],
                        'description' => $val['comment'],
                        'type' => $bar_type,
                        'app_ids' => $package_name_arr,
                        'keyboard_filters' => $keyboard_filters,
                    );

                    switch ($bar_type) {
                        //场景化语音
                        case 1:
                            $ios_arr += array(
                                'bar_style' => $val['ios']['bar_style'],
                                'interaction' => $val['ios']['interaction'],
                            );
                            break;
                        //极简语音
                        case 2:
                            $ios_arr += array(
                                'hint' => $val['ios']['hint'],
                                'orientation' => intval($val['ios']['orientation']),
                                'bar_style' => $val['ios']['bar_style'],
                            );
                            break;
                        //空格键语音
                        case 3:
                            /* $ios_arr += array(
                              'keyboard_filters' => $keyboard_filters,
                              ); */
                            break;
                        //复合语音, Edit by fanwenli on 2017-11-15
                        case 4:
                            $ios_arr += array(
                                'hint' => $val['ios']['hint'],
                                'orientation' => intval($val['ios']['orientation']),
                                'bar_array' => $val['ios']['bar_array'],
                            );
                            break;
                        //懒人短语
                        case 5:
                            $ios_arr += array(
                                'bar_style' => $val['ios']['bar_style'],
                                'lazy_name' => $val['ios']['lazyName'],
                            );
                            break;
                        //整句预测, Edit by fanwenli on 2019-05-09
                        case 6:
                            $ios_arr += array(
                                'orientation' => intval($val['ios']['orientation']),
                            );
                            break;
                    }

                    //有无视觉样式文字
                    if (isset($val['ios']['bar_text'])) {
                        $ios_arr['bar_text'] = $val['ios']['bar_text'];
                    }

                    //有无执行动作
                    if (isset($val['ios']['action'])) {
                        $ios_arr['action'] = $val['ios']['action'];
                    }

                    //有无执行动作跳转链接
                    if (isset($val['ios']['link'])) {
                        $ios_arr['link'] = $val['ios']['link'];
                    }

                    $arr[] = $ios_arr;
                }
            }
        }

        $out['ecode'] = $this->ecode;
        $out['emsg'] = ErrorMsg::getMsg($this->ecode);
        $out['version'] = $this->version;

        $out['data'] = bd_B64_encode(json_encode($arr), 0);

        return Util::returnValue($out, false, true);
    }

    /**
     * 获取数据
     * @param $cache_key 内容缓存
     * 
     * @return array
     *
     */
    private function getVoiceContent($cache_key) {
        $MinimalistModel = IoCload('models\\MinimalistModel');
        $jsonData = $MinimalistModel->getCacheContent($cache_key);

        if ($jsonData == '' || is_null($jsonData)) {
            $data = GFunc::getRalContent('minimalist_voice_cand');

            //set status, msg & version
            $this->ecode = GFunc::getStatusCode();
            $version = intval(GFunc::getResVersion());
            
            $voice = array(
                'data' => $data,
                'version' => $version,
            );
            
            $jsonData = json_encode($voice);

            //设置缓存
            $MinimalistModel->setCacheContent($cache_key, $jsonData, $this->intCacheExpired);
        }
            
        $arrData = json_decode($jsonData, true);
        $voice = $arrData['data'];
        $version = $arrData['version'];
        
        $this->version = intval($version);

        //过滤数据
        if (!empty($voice)) {
            $filterModel = new FilterModel();
            $voice = $filterModel->getFilterByArray($voice);
        }

        return $voice;
    }

}
