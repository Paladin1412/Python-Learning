<?php

use models\AppletCompressModel;
use models\AppletEntryModel;
use tinyESB\util\Logger;
use utils\ErrorCode;
use utils\GFunc;
use utils\Util;

/**
 * Class Applet
 * @desc 小程序洗发
 * @path("/applet/")
 */
class Applet
{
    const CACHE_KEY = 'v1_apple_install_cache_%s';
    const CACHE_FIELD = 'v1_%s_%s';

    /**
     * @Requirement_doc
     * http://newicafe.baidu.com/issue/inputserver-2720/show?from=page
     *
     * @api_doc
     * http://agroup.baidu.com/inputserver/md/article/2544946
     *
     * @desc 获取小程序下发信息
     * @route({"GET", "/get_package"})
     * @param({"complete", "$._GET.complete"})    (full/lite)
     * @param({"version_name", "$._GET.version_name"}) 版本号
     * @param({"platform_num", "$._GET.platform_num"}) 平台 (32/64)
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getPackage($complete, $version_name, $platform_num)
    {
        $result = Util::initialClass(true);

        if (empty($complete) || empty($version_name) || empty($platform_num)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }

        /** @var \utils\KsarchRedisV2 $redis */
        $redis = IoCload('utils\\KsarchRedisV2');
        $cacheKey = sprintf(self::CACHE_KEY, $version_name);
        $field = sprintf(self::CACHE_FIELD, $complete, $platform_num);

        $resCache = $redis->hget($cacheKey, $field);
        if ($resCache == '[]') {
            Logger::debug("Applet.getPackage | get cache [] for $cacheKey:$field");
            return ErrorCode::returnError('EMPTY_RESULT', 'empty result');
        }
        if (!empty($resCache)) {
            Logger::debug("Applet.getPackage | get valid cache for $cacheKey:$field");
            $result['data'] = json_decode($resCache);
            // 20200420 小程序SDK依赖以下几个字段
            $result['errno'] = 0;
            $result['errmsg'] = "success";
            $result['tipmsg'] = "请求成功";
            return $result;
        }
        Logger::debug("Applet.getPackage | get empty cache for $cacheKey:$field");

        // 回源
        $model = new AppletCompressModel();
        $res = $model->getAllByVersion($version_name);
        if (!empty($res)) {
            Logger::debug("Applet.getPackage | get valid db version $version_name");

            foreach ($res as $entry) {
                $aField = sprintf(self::CACHE_FIELD, $entry['complete'], $entry['platform']);
                $redis->hset($cacheKey, $aField, $entry['json_conf']);
                if ($entry['platform'] == $platform_num && $entry['complete'] == $complete) {
                    $result['data'] = json_decode($entry['json_conf'], true);
                }
            }
            $redis->expire($cacheKey, GFunc::getCacheTime('hours'));
        } else {
            Logger::debug("Applet.getPackage | get empty db for version $version_name, set cache []");
            $redis->hset($cacheKey, $field, '[]');
            $redis->expire($cacheKey, GFunc::getCacheTime('hours'));
            return ErrorCode::returnError('EMPTY_RESULT', 'empty result');
        }

        $result['errno'] = 0;
        $result['errmsg'] = "success";
        $result['tipmsg'] = "请求成功";
        return $result;
    }

    /**
     * @desc 删除小程序缓存
     * @route({"GET", "/clear_cache"})
     * @param({"version_name", "$._GET.version_name"}) 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function clearCache($version_name)
    {
        $result = Util::initialClass(true);

        /** @var \utils\KsarchRedisV2 $redis */
        $redis = IoCload('utils\\KsarchRedisV2');
        $cacheKey = sprintf(self::CACHE_KEY, $version_name);

        $redis->del($cacheKey, $succ);
        $result['data'] = array('result' => $succ);
        return Util::returnValue($result, true, true);
    }

    /**
     * @desc 获取小程序缓存
     * @route({"GET", "/get_cache"})
     * @param({"version_name", "$._GET.version_name"}) 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function getCache($version_name)
    {
        $result = Util::initialClass(true);

        /** @var \utils\KsarchRedisV2 $redis */
        $redis = IoCload('utils\\KsarchRedisV2');
        $cacheKey = sprintf(self::CACHE_KEY, $version_name);

        $data = $redis->hgetall($cacheKey, $succ);
        $result['data'] = $data;
        return Util::returnValue($result, true, true);
    }

    /**
     * @desc 获取小程序 http://agroup.baidu.com/inputserver/md/article/2729102
     * @route({"GET", "/info"})
     * @param({"appKey", "$._GET.app_key"}) 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function getInfo($appKey = "") {
        $appKey = trim($appKey);
        if(empty($appKey)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'param app_key can not be empty!');
        }
        $cacheKey = 'applet_info_' . md5($appKey);
        $response = Util::initialClass();
        $cacheData = GFunc::cacheZget($cacheKey);
        if(false !== $cacheData) {
            $response['data'] = $cacheData;
            return Util::returnValue($response);
        }
        $appletModel = IoCload(AppletEntryModel::class);
        $entry = $appletModel->getAppletInfo($appKey);
        if(false === $entry) {
            // 获取小程序信息失败
            return ErrorCode::returnError(ErrorCode::API_ACCESS_ERR, 'acquire applet info failed');
        }
        $result = array();
        $resultFiled = array( 'app_name', 'app_key', 'app_desc', 'photo_addr',);
        foreach($entry as $key => $val) {
            if(in_array($key, $resultFiled)) {
                $result[$key] = $val;
            }
        }
        // 公司名称
        if(!empty($entry['qualification']['name'])) {
            $result['qualification'] = $entry['qualification']['name'];
        }
        // 小程序类型和标签以及打开app的连接
        if(!empty($entry['category'])) {
            $lables = array();
            $normalType = 1; // 普通类型的小程序
            $gameType = 2; // 游戏类型的小程序
            $result['app_type'] = $normalType;
            $result['open_link'] = sprintf('openswan?app_key=%s', $appKey);
            foreach($entry['category'] as $category) {
                // 如果是顶级分类， 且分类id为313则为小程序
                if(empty($category['parent']) && $category['category_id'] == 313) {
                    $result['app_type'] = $gameType;
                    $result['open_link'] = sprintf('openswangame?app_key=%s', $appKey);
                }
                if(!empty($category['category_name']) && !in_array($category['category_name'], $lables)) {
                    array_push($lables, $category['category_name']);
                }
            }
            $result['labels'] = $lables;
        }
        // 缓存防止qps 超限制
        GFunc::cacheZset($cacheKey, $result, GFunc::getCacheTime('5mins'));
        $response['data'] = $result;
        return Util::returnValue($response);
    }
}