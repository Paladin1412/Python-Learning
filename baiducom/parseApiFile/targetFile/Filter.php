<?php

/* * *************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 */

/**
 * @author zhangqinyang(zhangqinyang@baidu.com)
 * @desc 场景接口
 * @path("/filter/")
 */
use utils\KsarchRedis;
use utils\GFunc;
use utils\Util;

class Filter {

    /** @property 内部缓存实例 */
    private $cache;

    /**
     * @desc返回过滤条件版本号
     * @route({"GET", "/version"})
     * @return {version: 14958585223}
     */
    public function version() {
        $filter_info = $this->getFilterInfo('{}', urlencode('{"update_time": -1}'), 0, 1000);
        return md5(json_encode($filter_info));
    }

    /**
     * @desc 返回云输入过滤条件
     * @route({"GET", "/cloud"})
     * @param({"skip", "$._GET.skip"}) 限制数量
     * @param({"limit", "$._GET.limit"}) 限制数量
     * @return({"body"})返回过滤条件
     * 数据格式如下：
     * [
     *     {
     *         field: "version",
     *         operator: "lte",
     *         value: "5.7.0.1",
     *         array: [
     *             ""
     *         ],
     *         file: ""
     *     },
     *     {
     *         field: "city",
     *         operator: "gte",
     *         value: "北京市",
     *         array: [
     *             ""
     *         ],
     *         file: ""
     *     }
     * ]
     */
    public function cloud($skip, $limit) {
        $filter_id = (int) $filter_id;
        $filter_info = $this->getFilterInfo('{}', urlencode('{"update_time": -1}'), $skip, $limit);
        if ($filter_info && is_array($filter_info)) {
            return $filter_info;
        } else {
            return array();
        }
    }

    /**
     * 根据条件获取过滤数据
     * @param $search 资源服务查询条件，如果没有则使用默认查询条件
     * @param $sort 排序
     * @param $skip 
     * @param $limit 限制
     * 
     * @return array
     *
     * 有消息, 返回对应的消息
     * 无消息, 返回空数组
     */
    protected function getFilterInfo($search, $sort = '', $skip = 0, $limit = 0) {

        $header = array(
            'pathinfo' => '/res/json/input/r/online/filter/',
            'querystring' => array(
                "search" => urlencode($search),
                'onlycontent' => 1,
                'searchbyori' => 1,
            ),
        );

        //如果排序不为空，则添加排序规则
        if ($sort) {
            $header['querystring']['sort'] = $sort;
        }

        if ($limit) {
            $header['querystring']['limit'] = $limit;
        }

        if ($skip) {
            $header['querystring']['skip'] = $skip;
        }

        $header['querystring'] = http_build_query($header['querystring']);
        $result = ral('res_service', 'get', null, rand(), $header);
        if (false === $result) {
            return false;
        }

        $result = array_values($result);
        return $result;
    }
    
    /**
     * @desc 过滤模块更新检测
     * @route({"GET", "/check"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": 1,
            "version": 123
        }
     */
    public function check() {
        //客户端版本号
        $version = intval($_GET['filter_version']);
        
        $result = Util::initialClass(false);
        $result['data'] = 0;
        $result['version'] = 0;
        
        $key = 'v5_filter_test_check';
        $arrContent = GFunc::cacheZget($key);
        if ($arrContent === false) {
            $arrContent = array(
                'data' => array(),
                'version' => 0,
            );
            
            $successed = true;
            $arrContent['data'] = GFunc::getRalContent('filter_test', 1, '', $successed);
            $arrContent['version'] = GFunc::getResVersion();
            
            $intCacheTime = GFunc::getCacheTime("15mins");
            if($successed === false) {
                $intCacheTime = GFunc::getCacheTime("30secs");
            }
            
            GFunc::cacheZset($key, $arrContent, $intCacheTime);
        }
        
        //return all content if server bigger than client
        if($arrContent['version'] > intval($version)) {
            $result['version'] = $arrContent['version'];
            
            //过滤数据
            if(!empty($arrContent['data'])) {
                $conditionFilter = IoCload("utils\\ConditionFilter");
                foreach($arrContent['data'] as $val) {
                    //filter success
                    if ($conditionFilter->filter($val['filter_conditions'])) {
                        $result['data'] = 1;
                    }
                }
            }
        }
        
        return Util::returnValue($result,false);
    }
}
