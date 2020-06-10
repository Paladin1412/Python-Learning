<?php
/**
 *
 * @desc 颜文字
 * @path("/emoticon/")
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use utils\Util as Util;
use utils\DbConn;
use utils\GFunc;

class Emoticon
{
    //每页显示最多条数
    const MAX_PAGE_SIZE = 200;
    //默认每页显示条数
    const GENERAL_PAGE_SIZE = 12;
    
    /** @property 运营分类lite intend缓存key */
    private $strEmoticonLiteIntendCombineCache;
    
    /** @property 缓存时间 */
    private $intCacheExpired;

    /**
     * @desc emoticon 分类列表
     * @route({"GET", "/cate/*\/list"})
     * @param({"cat", "$.path[2]"}) int $cat 某自然分类 (newest,hottest,buildin(内置),virtab,buildhot(最热tab))
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "title": "最新",
            "list": [
                {
                    "id": "21",
                    "name": "熊咚咚2",
                    "type": "normal",
                    "os": "all",
                    "short_name": "",
                    "version_code": "0",
                    "author": "",
                    "url": "",
                    "ios_file_size": "",
                    "android_file_size": "",
                    "zip_size": "",
                    "pub_item_type": "",
                    "desc": "平时呆萌，偶尔贱萌，最爱戴墨镜装大哥范儿，每天都会握拳打鸡血的励志熊。",
                    "downloads": "100",
                    "thumb": "",
                    "pic_1": "",
                    "pic_2": "",
                    "pic_3": ""
                }
            ],
            "ver": 1441700356
        }
     */
    public function catlist($cat = '', $plt = 'a1', $ver_name = '6.0.0.0', $sf = 0, $num = self::GENERAL_PAGE_SIZE) {
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $ver_name = Util::formatVer($ver_name);
            $res = array(
                'list' => array(
                ),
            );

            $emoticonModel = IoCload('models\\EmoticonModel');
            $data = $emoticonModel->getEmoticonsByCate($cat, $plt, $ver_name, $sf, $num);
            $data && $res = $data;
            GFunc::cacheSet($key, $res);
        }

        return $res;
    }

     /**
      * @desc 运营分类
      * @route({"GET", "/operate"})
      * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
      * @param({"ver_name", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
      * @return({"header", "Content-Type: application/json; charset=UTF-8"})
      * @return({"body"})
        {
            "list": [
                {
                    "type": "detail",
                    "tag": "1",
                    "os": "all",
                    "image": "",
                    "pub_item_type": "non_ad",
                    "data": {
                        "id": "1",
                        "name": "熊咚咚",
                        "short_name": "",
                        "os", "all",
                        "version_code": "0",
                        "author": "",
                        "desc": "平时呆萌，偶尔贱萌，最爱戴墨镜装大哥范儿，每天都会握拳打鸡血的励志熊。",
                        "downloads": "100",
                        "type": "normal",
                        "zip_size": "",
                        "android_file_size": "",
                        "pub_item_type": "",
                        "thumb": "",
                        "url": "",
                        "pic_1": "",
                        "pic_2": "",
                        "pic_3": "",
                        "share_pic": "",
                        "share_qrcode": "",
                        "share_type": 0,
                        "share": {
                            "title": "",
                            "description": "",
                            "url": "",
                            "image": "",
                            "thumb": "",
                            "platform": [
                                {
                                    "name": "weixin",
                                    "content": {
                                        "title": "「熊咚咚」颜文字好逗！",
                                        "description": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧！",
                                        "url": "",
                                        "image": "",
                                        "thumb": ""
                                    }
                                },
                                {
                                    "name": "weixincircle",
                                    "content": {
                                        "title": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧！",
                                        "description": "",
                                        "url": "circle",
                                        "image": "",
                                        "thumb": ""
                                    }
                                },
                                {
                                    "name": "weibo",
                                    "content": {
                                        "title": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧：&platform=weibo",
                                        "description": "",
                                        "url": "",
                                        "image": "",
                                        "thumb": ""
                                    }
                                },
                                {
                                    "name": "qq",
                                    "content": {
                                        "title": "「熊咚咚」颜文字好逗！",
                                        "description": "",
                                        "url": "",
                                        "image": "",
                                        "thumb": ""
                                    }
                                },
                                {
                                    "name": "qzone",
                                    "content": {
                                        "title": "「熊咚咚」颜文字好逗！",
                                        "description": "",
                                        "url": "",
                                        "image": "",
                                        "thumb": ""
                                    }
                                },
                                {
                                    "name": "system",
                                    "content": {
                                        "title": "「熊咚咚」颜文字好逗！",
                                        "description": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧！",
                                        "url": "",
                                        "image": "",
                                        "thumb": ""
                                    }
                                }
                            ]
                        }
                    }
                },
                {
                    "type": "list",
                    "tag": "ex",
                    "os": "all",
                    "image": "",
                    "pub_item_type": "non_ad",
                    "data": {}
                }
            ]
        }
     */
    public function operationCategory($plt = 'a1', $ver_name = '6.0.0.0')
    {
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $ver_name = Util::formatVer($ver_name);
            $res = array(
                "list" => array(
                ),
            );
            $emoticonModel = IoCload('models\\EmoticonModel');
            $data = $emoticonModel->getOpcates($plt, $ver_name);

            //Edit by fanwenli on 2016-06-28, get data from database together
            $data_id_arr = array();
            foreach ($data as $k => &$v)
            {
                if($v['type'] == 'detail'){
                    $data_id_arr[] = intval($v['tag']);
                }
            }

            //get data from database by in
            if(!empty($data_id_arr)){
                $data_from_share = $emoticonModel->getEmoticonDetailWithShareDataByArray($data_id_arr);

                foreach ($data as $k => &$v){
                    switch($v['type']){
                        case 'detail':
                            //type is detail and you could find it in database
                            if(isset($data_from_share[intval($v['tag'])])){
                                $v['data'] = $data_from_share[intval($v['tag'])];
                            }
                            break;
                        default:
                            $v['data'] = (object) array();
                            break;
                    }

                    //无法获取详情的去除
                    if (!$v['data']){
                        unset($data[$k]);
                    }
                }
            }

            $res['list'] = array_values($data);

            //Edit by fanwenli on 2016-08-25, combine with lite intend
            //Edit by fanwenli on 2018-01-24, add cache key and CacheExpired
            $res['list'] = Util::liteIntendCombine('emoticon', $res['list'], $this->strEmoticonLiteIntendCombineCache, $this->intCacheExpired);
            GFunc::cacheSet($key, $res);
        }

        return $res;
    }

    /**
     * @desc emoticon 运营分类列表
     * @route({"GET", "/operate/*\/list"})
     * @param({"cat", "$.path[2]"}) int $cat 某自然分类
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "title": "xxx",
            "list": [
                {
                    "id": "21",
                    "name": "熊咚咚2",
                    "short_name": "",
                    "url": "",
                    "version_code": "0",
                    "type": "normal",
                    "os": "all",
                    "author": "",
                    "desc": "平时呆萌，偶尔贱萌，最爱戴墨镜装大哥范儿，每天都会握拳打鸡血的励志熊。",
                    "downloads": "100",
                    "thumb": "",
                    "pic_1": "",
                    "pic_2": "",
                    "pic_3": "",
                    "ios_file_size": "",
                    "zip_size": "",
                    "android_file_size": "",
                    "pub_item_type": ""
                }
            ]
        }
     */
    public function operationCategoryEmoticons($cat = '', $plt = 'a1', $ver_name = '6.0.0.0', $sf = 0, $num = self::GENERAL_PAGE_SIZE)
    {
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $ver_name = Util::formatVer($ver_name);
            $res = array(
                'title' => '',
                'list' => array(),
            );
            $emoticonModel = IoCload('models\\EmoticonModel');
            $data = $emoticonModel->getEmoticonsByOpcate($cat, $plt, $ver_name, $sf, $num);
            $data && $res = $data;
            GFunc::cacheSet($key, $res);
        }

        return $res;
    }

    /**
     * @desc emoticon 详细信息
     * @route({"GET", "/items/*\/detail"})
     * @param({"id", "$.path[2]"}) int  $id
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到皮肤主题，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "data": {
                "id": "1",
                "name": "熊咚咚",
                "short_name": "",
                "os": "all",
                "version_code": "0",
                "url": "",
                "author": "",
                "type": "normal",
                "desc": "平时呆萌，偶尔贱萌，最爱戴墨镜装大哥范儿，每天都会握拳打鸡血的励志熊。",
                "downloads": "100",
                "thumb": "",
                "pic_1": "",
                "pic_2": "",
                "pic_3": "",
                "ios_file_size": "",
                "android_file_size": "",
                "zip_size": "",
                "pub_item_type": "",
                "share_pic": "",
                "share_qrcode": "",
                "share_type": 0,
                "share": {
                    "title": "",
                    "description": "",
                    "url": "",
                    "image": "",
                    "thumb": "",
                    "platform": [
                        {
                            "name": "weixin",
                            "content": {
                                "title": "「熊咚咚」颜文字好逗！",
                                "description": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧！",
                                "url": "",
                                "image": "",
                                "thumb": ""
                            }
                        },
                        {
                            "name": "weixincircle",
                            "content": {
                                "title": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧！",
                                "description": "",
                                "url": "",
                                "image": "",
                                "thumb": ""
                            }
                        },
                        {
                            "name": "weibo",
                            "content": {
                                "title": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧：&platform=weibo",
                                "description": "",
                                "url": "",
                                "image": "",
                                "thumb": ""
                            }
                        },
                        {
                            "name": "qq",
                            "content": {
                                "title": "「熊咚咚」颜文字好逗！",
                                "description": "",
                                "url": "",
                                "image": "",
                                "thumb": ""
                            }
                        },
                        {
                            "name": "qzone",
                            "content": {
                                "title": "「熊咚咚」颜文字好逗！",
                                "description": "",
                                "url": "",
                                "image": "",
                                "thumb": ""
                            }
                        },
                        {
                            "name": "system",
                            "content": {
                                "title": "「熊咚咚」颜文字好逗！",
                                "description": "「熊咚咚」颜文字现已加入百度输入法豪华午餐，快来享用吧！",
                                "url": "",
                                "image": "",
                                "thumb": ""
                            }
                        }
                    ]
                }
            }
        }
     */
    public function detail($id = 0, $plt = '')
    {
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $res = array(
                'data' => array(),
            );
            $emoticonModel = IoCload('models\\EmoticonModel');
            $data = $emoticonModel->getEmoticonDetailWithShareData($id);

            if ($data) {
                $res['data'] = $data;
            } else {
                throw new NotFound();
            }

            GFunc::cacheSet($key, $res);
        }

        return $res;
    }

    /**
     * @desc 颜文字下载
     * @route({"GET", "/items/*\/file"})
     * @param({"id", "$.path[2]"}) string $id
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param ({"version", "$._GET.version"}) 版本号 不需要客户端传，从加密参数中获取     *
     * @param ({"is_zip", "$._GET.is_zip"})
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     * 验证通过则：
     * HTTP/1.1 302 Found
     * Location: 下载链接
     * 验证不通过则：
     * HTTP/1.1 404 Not Found
     */
    public function download($id = '', $plt = '', $version = '', $is_zip = 0, &$status = '', &$location = '')
    {
        $id = intval(trim($id));
        //$isIOS = strtolower(substr(trim($plt), 0, 1)) == 'i';
        $isIOS = Util::getOS($plt) == 'ios';

        if ($id && $id > 0)
        {
            $emoticonModel = IoCload('models\\EmoticonModel');
            $data = $emoticonModel->cache_getEmoticonFilesPath($id);
            $version_value = Util::getVersionIntValue($version);

            //NOTICE: android7.0.0.0/ios7.1.0.0 及以后版本 is_zip=0 下载 pack_file is_zip=1 下载 zip包文件；
            //android7.0.0.0/ios7.1.0.0 以前版本 区分平台下载 ios_file/android_file
            $key = 'android_file';

            if($isIOS)
            {
                //ios 7.2.0.0 支持颜文字自造
                $key = ($version_value >= 7020000) ? ($is_zip ? 'zip_file' : 'pack_file') : 'ios_file';
            }
            else
            {
                //安卓7.0.0.0 支持颜文字自造
                $key = ($version_value >= 7000000) ? ($is_zip ? 'zip_file' : 'pack_file') : 'android_file';
            }

            if(!empty($data[$key]))
            {
                $domainV4 = GFunc::getGlobalConf('domain_v4');
                $url =  $domainV4 . $data[$key];
                $status = "302 Found";
                $location = "Location: " . $url;
                return;
            }
        }

        $status = "404 Not Found";
        return ;
    }

    /**
     * @desc 颜文字启用
     * @route({"GET", "/items/*\/enable"})
     * @param({"id", "$.path[2]"}) string $id
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * 只做access log
     *
     */
    public function enable($id = '', $plt = '')
    {
        return array('success' => true);
    }

}
