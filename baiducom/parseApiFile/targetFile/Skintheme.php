<?php

/***************************************************************************
 *
 * Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
 */
use models\SkinthemeModel;
use tinyESB\util\exceptions\NotFound;
use utils\DbConn;
use utils\Util;


//Edit by fanwenli on 2016-09-07, add ResServiceModel and FilterModel
use models\FilterModel;
use models\ResServiceModel;
use utils\GFunc;
use utils\ConditionFilter;

/**
 * @author wanzhongkun(wanzhongkun@baidu.com)
 * @desc 皮肤主题类
 * @path("/skin/")
 */
class Skintheme{

    //每页显示最多条数
    const MAX_PAGE_SIZE = 200;
    //默认每页显示条数
    const GENERAL_PAGE_SIZE = 12;
    //基于tag推荐最大条数
    const MAX_REC_CNT_TAG = 11;
    //基于下载推荐最大条数
    const MAX_REC_CNT_CF = 11;
    //基于mup推荐最大条数
    const MAX_REC_CNT_MUP = 11;
    /** @property 内部缓存默认过期时间(单位: 秒) */
    private $intCacheExpired;
    //内部缓存key前缀
    const INTERNAL_CACHE_KEY_PREFIX = 'st_int_';
    //连接跳转时，为不影响原有接口统计，加上的url区分参数
    const REDIRECT_URL_IGNORE_FLAG = '&inputgd=1';
    //ip库中所有ip的首位缓存key
    const IP_INDEX_ALL_MEMCACHE_KEY = 'colombo_iplib_index_all';
    //ip索引文件的缓存前缀
    const IP_INDEX_MEMCACHE_KEY_PREFIX = 'colombo_iplib_index_';
    /**
     * 单条ip数据缓存前缀
     * 格式ipstart|ipend|country|isp|province|city|county|country confidence|isp confidence|province confidence|city confidence|county confidence
     */
    const IP_DATA_MEMCACHE_KEY_PREFIX = 'colombo_iplib_data_';
    //单条ip数据包含信息的数量, 与上面定义的单条ip数据格式相关
    const IP_DATA_INFO_COUNT = 12;
    //数据库链接
    private $db;
    /** @property 资源路径前缀 */
    private $pre_path;
    /** @property V4接口域名 */
    private $domain_v4;
    /** @property V5接口域名 */
    private $domain_v5;
    /** @property 分享链接前缀地址 */
    private $share_url_pre;
    /** @property 分享视频地址 */
    private $share_url_video;
    /** @property 分享图片背景地址 */
    private $share_bg_pic;
    /** @property 分享类型 */
    private $share_type;
    /** @property 内部缓存实例 */
    private $cache;
    /** @property 用于内部缓存管理的存储实例 */
    private $storage;
    //iOS为app store审核临时排除的部分分类（接口中还要屏蔽该相关分类下的所有皮肤主题）
    private $except_cats = array(
        'dm',//卡通动漫
        'mx',//明星名人
        'ys',//影视娱乐
        'yx',//游戏地带
        'ty',//体育赛事
        'ktdm',//卡通动漫mo
    );
    /** @property lite intend缓存key */
    private $strSkinthemeLiteIntendCombineCache;

    /**
     * @return db
     */
    function getDB(){
        return DBConn::getXdb();
    }

    /**
     * @desc 皮肤主题详情，市场列表中有的字段沿用其，列表没有的其他字段与数据库字段名称一致。
     * @route({"GET", "/items/*\/detail"})
     * @param({"id", "$.path[2]"}) string $id 皮肤id以s开头，主题id以t开头。注：此处为兼容，可为id或token
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"isActivity", "$._GET.is_activity"}) string 运营活动皮肤传1  否则传0
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到皮肤主题，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "imgpre": "upload/imags/",
                            "data": {
                                "id": "321",
                                "name": "【初久】樱花的思念",
                                "author": "洛书千年·初久",
                                "down": "528121",
                                "display_types": "new",
                                "ver": "2",
                                "cateid": "tqx",
                                "url": "http://10.58.19.57:8890/v5/skin/items/t321/file?reserve=1",
                                "version_code": 1,
                                "silent_up": 0,
                                "size": "49.3K",
                                "token": "0c1b65e17d07b9722ce43bab8f47c3f6",
                                "cate": "静物风景",
                                "skin_cate_id": "tqx,txx",
                                "skin_cate_name": "静物风景,特效皮肤",
                                "theme_cate_id": "tqx,txx",
                                "theme_cate_name": "静物风景,特效主题",
                                "desc": "",
                                "remarks": "",
                                "is_skin": 0,
                                "imghd": "2012/08/22/n79r193yuh.png",
                                "imgt_300": "2012/08/22/n79r193yuh_300.jpg",
                                "imgs_300": "2012/08/22/n79r193yuh_300.jpg",
                                "imgshd_300": "2012/08/22/n79r193yuh.png",
                                "imgt_160": "2012/08/22/n79r193yuh_280.jpg",
                                "imgs_160": "2012/08/22/n79r193yuh_280.jpg",
                                "imgshd_160": "2012/08/22/n79r193yuh.png",
                                "imgt_150": "2012/08/22/n79r193yuh_200.jpg",
                                "imgs_150": "2012/08/22/n79r193yuh_200.jpg",
                                "imgshd_150": "2012/08/22/n79r193yuh.png",
                                "imgt_100": "2012/08/22/n79r193yuh_150.jpg",
                                "imgs_100": "2012/08/22/n79r193yuh_150.jpg",
                                "imgshd_100": "2012/08/22/n79r193yuh.png",
                                "mobile_title_pic": "2012/08/22/n79r193yuh_120.jpg",
                                "view_number": "0",
                                "pc_title_pic": "2012/08/22/n79r193yuh_240.jpg",
                                "pc_preview_pic": "",
                                "grade": null,
                                "gradetimes": null,
                                "tags": "樱花思念|静物风景|紫色|植物|唯美",
                                "share_text": "",
                                "share_delay": 2, //分享延迟提醒（天）
                                "share_url": "",    //"/share?android_id=385&ios_id=388",
                                "summary": "",
                                "recommend": "30",
                                "add_time": "2012-08-22 21:58:43",
                                "online_time": "2013-08-15 19:53:00",
                                "key_type": null,
                                "source": null,
                                "mobile_preview_pic": "",
                                "download_link_oriz": "",
                                "file_size_oriz": "",
                                "download_link_zip": "",
                                "platform_code": "all",
                                "except_platform_code": "empty",
                                "os": "all",
                                "is_support": "0",
                                "resolution": null,
                                "input_kind": null,
                                "pic_1_240": "2012/08/22/n79r193yuh_240.jpg",
                                "pic_1_180": "2012/08/22/n79r193yuh_180.jpg",
                                "pic_2_240": "",
                                "pic_2_180": "",
                                "pic_3_240": "",
                                "pic_3_180": "",
                                "pic_title_1": "",
                                "pic_title_2": "",
                                "pic_title_3": "",
                                "pic_1_250": "2012/08/22/n79r193yuh_250.jpg",
                                "pic_1_160": "2012/08/22/n79r193yuh_160.jpg",
                                "pic_1_100": "2012/08/22/n79r193yuh_100.jpg",
                                "pic_2_250": "",
                                "pic_2_160": "",
                                "pic_2_100": "",
                                "pic_3_250": "",
                                "pic_3_160": "",
                                "pic_3_100": "",
                                "iossupported": "",
                                "iossupportedversion": "",
                                "iphone_pic": "",
                                "min_version": "",
                                "max_version": "",
                                "except_version": "",
                                "min_width_px": "",
                                "max_width_px": "",
                                "min_height_px": "",
                                "max_height_px": "",
                                "hdbatch": null,
                                "pub_item_type": "non_ad",
                                "tj_video": "",  //特技皮肤视频素材
                                "tj_voice": "",  //特技皮肤音频素材
                                "tj_gif": "",    //特技皮肤动态图
                                "abilities": "",  //皮肤详情支持Tag
                                "promote_type": "",  //皮肤支持不支持推广
                                "tj_video_thumb": "",  //皮肤详情支持视频缩略图
                                "tj_gif_thumb": "",  //皮肤详情支持GIF缩略图
                                "share_type": "0",  //分享方式0:文字链接地址 1:二维码图片 2:定制图片分享
                                "share_pic": "", //分享定制图片
                                "share_bg_pic": "" //分享背景图片,
                                "share_qrcode": ""//二维码
                                "share": {
                                    "title": "",
                                    "description": "",
                                    "url": "分享链接",
                                    "image": "http://10.58.19.57:8001/upload/imags/2015/07/10/907kvv8a87.jpg",
                                    "thumb": "http://10.58.19.57:8001/upload/imags/2015/07/10/907kvv8a87.jpg",
                                    "video_url": "", //分享的视频地址
                                    "platform": [{
                                        "name": "weixin",
                                        "content": {
                                            "title": "",
                                            "description": "",
                                            "url": "",
                                            "image": " ",
                                            "thumb": "",
                                            "video_url": "", //分享的视频地址
                                        }
                                    },
                                    {
                                        "name": "weixincircle",
                                        "content": {
                                           "title": "",
                                            "description": "",
                                            "url": "",
                                            "image": "",
                                            "thumb": "",
                                            "video_url": "", //分享的视频地址
                                        }
                                    },
                                    {
                                        "name": "weibo",
                                        "content": {
                                            "title": "",
                                            "description": "",
                                            "url": "",
                                            "image": "",
                                            "thumb": "",
                                            "video_url": "", //分享的视频地址
                                        }
                                    },
                                    {
                                        "name": "qq",
                                        "content": {
                                            "title": "",
                                            "description": "",
                                            "url": "",
                                            "image": "",
                                            "thumb": "",
                                            "video_url": "", //分享的视频地址
                                        }
                                    },
                                    {
                                        "name": "qzone",
                                        "content": {
                                            "title": "",
                                            "description": "",
                                            "url": "",
                                            "image": "",
                                            "thumb": "",
                                            "video_url": "", //分享的视频地址
                                        }
                                    },
                                    {
                                        "name": "system",
                                        "content": {
                                            "title": "",
                                            "description": "",
                                            "url": "",
                                            "image": "",
                                            "thumb": "",
                                            "video_url": "", //分享的视频地址
                                        }
                                    }]
                                }
                            }
                        }
     */
    function itemDetail($id, $plt = 'a1', $ver_name = '5.4.0', $isActivity=0) {
        $res = array('domain' => $this->domain_v4, 'imgpre' => $this->pre_path, 'data' => array(), );
        if ('flutterMySkinDIY' == $id) {
            $res['data']['is_support'] = 0;
            return $res;
        }

        $id = trim($id);
        if (!empty($id) && mb_strlen($id) > 1) {
            //新版本分享出来的的token都是不带s或者t， 这里兼容一下，id为31位数字的皮肤太大，暂不考虑
            if (32 != strlen($id)) {
                $id = mb_substr($id, 1);
            }
            $os = Util::getOS($plt);

            /*
             * 2019七夕运营活动分享，活动过后此代码可删除
             */
            $skinModel = IoCload('models\\SkinthemeModel');
            if (1 == $isActivity) {
                $res['data']['share_type'] = 1;
                $res['data']['share_bg_pic'] = 'http://res.mi.baidu.com/pic/qixi_activity_background.png';
                $objRedis = GFunc::getCacheInstance();
                $data = $objRedis->getBigForLottery($id);
                if (false !== $data) {
                    $qrcode = $skinModel->createQrCodeAndUploadBos(md5(microtime()) . rand(0,100) . 'activity.png', $data['sharePath']);
                    if (false !== $qrcode) {
                        $qrcode = GFunc::getGlobalConf('bos_host') . '/imeres/' . $qrcode;
                    } else {
                        $qrcode = '';
                    }
                } else {
                    $qrcode = '';
                }
                $res['data']['share_qrcode'] = $qrcode;
                $res['data']['share'] =  $skinModel->getCpActivityShareInfo($data, $os);
                return $res;
            }

            if ($id == 'customTheme' || urldecode($id) == '默认皮肤iOS8') {
                //客户端本地请求 不查询数据库
                $res['data']['share_type'] = $this->share_type;
                $res['data']['share'] = $this->getSkinShareData($res, true, array(), $os);
            } else if (!empty($id) && mb_strlen($id) > 1) {
                $id == 'e0a17a09b79cfac9aa14daf650cf7275' && $id = '078b38929549d90527cbbd1704d6334d';
                $skin_data = $this->fetchOneSkinTheme($id,'itemDetail');
                //主题皮肤和表主题ID < 4000的+4000
                if (empty($skin_data) && is_numeric($id) && $id < 4000) {
                    $id += 4000;
                    $skin_data = $this->fetchOneSkinTheme($id,'itemDetail');
                }

                if ($skin_data) {
                    if (1 == $skin_data['type']) {
                        $skin_data['is_skin'] = 1;
                        $is_skin  = true;
                    } else {
                        $skin_data['is_skin'] = 0;
                        $is_skin  = false;
                    }
                    $res['data'] = $skinModel->processSkinFileds($skin_data);
                    $res['data']['version_code'] = $skin_data['version_code'];
                    $category_arr = $this->getCategoryFunction($skin_data['categories'],$skin_data['categories_name']);
                    $cateId = '';
                    if (!empty($category_arr['skin']['id'][0])) {
                        $cateId = 's' . $category_arr['skin']['id'][0];
                    } else if (!empty($category_arr['theme']['id'][0])) {
                        $cateId = 't' . $category_arr['theme']['id'][0];
                    }
                    $cateName = '';
                    if (!empty($category_arr['skin']['name'][0])) {
                        $cateName = $category_arr['skin']['name'][0];
                    } else if (!empty($category_arr['theme']['name'][0])) {
                        $cateName = $category_arr['theme']['name'][0];
                    }
                    $res['data']['cateid'] = $cateId;
                    $res['data']['cate'] = $cateName;
                    $res['data']['silent_up'] = $skin_data['silent_up'];
                    if(!empty($category_arr['skin']['id']))
                    {
                        $res['data']['skin_cate_id'] = implode(',',$category_arr['skin']['id']);
                        $res['data']['skin_cate_name'] = implode(',',$category_arr['skin']['name']);
                    }

                    if(!empty($category_arr['theme']['id']))
                    {
                        $res['data']['theme_cate_id'] = implode(',',$category_arr['theme']['id']);
                        $res['data']['theme_cate_name'] = implode(',',$category_arr['theme']['name']);
                    }
                    $res['data']['imghd'] = str_ireplace($this->pre_path, '', $skin_data['pic_1_0']);
                    $res = $this->skinImageMap($res, $skin_data);

                    //建议新增
                    $res['data']['mobile_title_pic'] = str_ireplace($this->pre_path, '', $skin_data['mobile_title_pic']);
                    $res['data']['view_number'] = $skin_data['view_number'];
                    $res['data']['pc_title_pic'] = str_ireplace($this->pre_path, '', $skin_data['pc_title_pic']);
                    $res['data']['pc_preview_pic'] = str_ireplace($this->pre_path, '', $skin_data['pc_preview_pic']);
                    $res['data']['grade'] = $skin_data['grade'];
                    $res['data']['gradetimes'] = $skin_data['gradetimes'];
                    $res['data']['tags'] = $skin_data['tags'];
                    $res['data']['share_text'] = $skin_data['share_text'];
                    $res['data']['summary'] = $skin_data['summary'];
                    //皮肤分享新增
                    $res['data']['share_url'] = $skin_data['share_url'];
                    $res['data']['share_delay'] = $skin_data['share_delay'];
                    $res['data']['weixin_title'] = $skin_data['weixin_title'];
                    $res['data']['weixin_desc'] = $skin_data['weixin_desc'];
                    $res['data']['weibo_text'] = $skin_data['weibo_text'];
                    //排序字段
                    $res['data']['recommend'] = $skin_data['recommend'];
                    $res['data']['add_time'] = $skin_data['add_time'];
                    $res['data']['online_time'] = $skin_data['online_time'];
                    //意义不大
                    $res['data']['key_type'] = $skin_data['key_type'];//null
                    $res['data']['source'] = $skin_data['source'];//null
                    $res['data']['mobile_preview_pic'] = str_ireplace($this->pre_path, '', $skin_data['mobile_preview_pic']);//null
                    $res['data']['download_link_oriz'] = str_ireplace($this->pre_path, '', $skin_data['download_link_oriz']);//大部分为空
                    $res['data']['file_size_oriz'] = str_ireplace($this->pre_path, '', $skin_data['file_size_oriz']);//大部分为空
                    $res['data']['download_link_zip'] = str_ireplace($this->pre_path, '', $skin_data['download_link_zip']);//大部分为空
                    //内部筛选
                    $res['data']['platform_code'] = $skin_data['platform_code'];
                    $res['data']['except_platform_code'] = $skin_data['except_platform_code'];
                    $res['data']['os'] = $skin_data['os'];
                    $res['data']['resolution'] = $skin_data['resolution'];
                    $res['data']['input_kind'] = $skin_data['input_kind'];
                    //没用到的图片
                    $res['data']['pic_1_240'] = str_ireplace($this->pre_path, '', $skin_data['pic_1_240']);
                    $res['data']['pic_1_180'] = str_ireplace($this->pre_path, '', $skin_data['pic_1_180']);
                    $res['data']['pic_2_240'] = str_ireplace($this->pre_path, '', $skin_data['pic_2_240']);
                    $res['data']['pic_2_180'] = str_ireplace($this->pre_path, '', $skin_data['pic_2_180']);
                    $res['data']['pic_3_240'] = str_ireplace($this->pre_path, '', $skin_data['pic_3_240']);
                    $res['data']['pic_3_180'] = str_ireplace($this->pre_path, '', $skin_data['pic_3_180']);
                    $res['data']['pic_title_1'] = str_ireplace($this->pre_path, '', $skin_data['pic_title_1']);
                    $res['data']['pic_title_2'] = str_ireplace($this->pre_path, '', $skin_data['pic_title_2']);
                    $res['data']['pic_title_3'] = str_ireplace($this->pre_path, '', $skin_data['pic_title_3']);
                    $res['data']['pic_1_250'] = str_ireplace($this->pre_path, '', $skin_data['pic_1_250']);
                    $res['data']['pic_1_160'] = str_ireplace($this->pre_path, '', $skin_data['pic_1_160']);
                    $res['data']['pic_1_100'] = str_ireplace($this->pre_path, '', $skin_data['pic_1_100']);
                    $res['data']['pic_2_250'] = str_ireplace($this->pre_path, '', $skin_data['pic_2_250']);
                    $res['data']['pic_2_160'] = str_ireplace($this->pre_path, '', $skin_data['pic_2_160']);
                    $res['data']['pic_2_100'] = str_ireplace($this->pre_path, '', $skin_data['pic_2_100']);
                    $res['data']['pic_3_250'] = str_ireplace($this->pre_path, '', $skin_data['pic_3_250']);
                    $res['data']['pic_3_160'] = str_ireplace($this->pre_path, '', $skin_data['pic_3_160']);
                    $res['data']['pic_3_100'] = str_ireplace($this->pre_path, '', $skin_data['pic_3_100']);

                    //skins专有字段
                    $res['data']['iossupported'] = $is_skin ? $skin_data['iossupported'] : '';
                    $res['data']['iossupportedversion'] = $is_skin ? $skin_data['iossupportedversion'] : '';
                    $res['data']['iphone_pic'] = $is_skin ? $skin_data['iphone_pic'] : '';
                    $res['data']['min_version'] = $is_skin ? $skin_data['min_version_int'] : '';
                    $res['data']['max_version'] = $is_skin ? $skin_data['max_version_int'] : '';
                    $res['data']['except_version'] = $is_skin ? $skin_data['except_version_int'] : '';
                    $res['data']['visible_version'] = $is_skin ? $skin_data['visible_version_int'] : '';
                    $res['data']['min_width_px'] = $is_skin ? $skin_data['min_width_px'] : '';
                    $res['data']['max_width_px'] = $is_skin ? $skin_data['max_width_px'] : '';
                    $res['data']['min_height_px'] = $is_skin ? $skin_data['min_height_px'] : '';
                    $res['data']['max_height_px'] = $is_skin ? $skin_data['max_height_px'] : '';
                    //themes专有字段
                    $res['data']['hdbatch'] = $is_skin ? '' : $skin_data['hdbatch'];

                    $res['data']['is_support'] = $this->isSupport($skin_data, $plt, $ver_name, $is_skin);

                    //分享方式
                    //Edit by fanwenli on 2015-11-05. Share type use database column
                    $res['data']['share_type'] = $skin_data['share_type'];
                    $res['data']['share_pic'] = $skin_data['share_pic'] ? ($res['domain'] . $res['imgpre'] . str_ireplace($this->pre_path, '', $skin_data['share_pic'])) : '';

                    $allowPlatform = array(
                        'a1' => 0,
                        'i5' => 0,
                        'i6' => 0,
                    );
                    if(array_key_exists($plt, $allowPlatform)  && version_compare($ver_name,'9.0.0.0','>='))
                    {
                        $this->share_bg_pic = 'https://imeres.baidu.com/pic/backgroud20191015.png';
                    }

                    $res['data']['share_bg_pic'] = $this->share_bg_pic;
                    $res['data']['share_qrcode'] = $skin_data['share_qrcode'] ? ($res['domain'] . $res['imgpre'] . str_ireplace($this->pre_path, '', $skin_data['share_qrcode'])) : '';

                    //查找运营活动皮肤
                    //兼容ios 7.5版本
                    $activitySkinToken = $skinModel->getActivitySkinToken($ver_name);
                    $intClientVerTwo = Util::getVersionIntValueTwo($ver_name);
                    if ('ios' == $os && 75000 == $intClientVerTwo) {
                        $res['data']['abilities'] = in_array($skin_data['token'], $activitySkinToken) ? 'Activity' : $skin_data['abilities'];
                    } else {
                        $res['data']['abilities'] = $skin_data['abilities'];
                        $res['data']['is_activity'] = in_array($skin_data['token'], $activitySkinToken) ? 1 : 0;
                    }
                    //分享数据
                    $activitySkinInfo = $skinModel->getShareUrlByToken($id);
                    $res['data']['pic_2_1_android'] = $skin_data['pic_2_1_android'];
                    $res['data']['pic_2_1_ios'] = $skin_data['pic_2_1_ios'];
                    $res['data']['share'] = $this->getSkinShareData($res,false, $activitySkinInfo, $os, $is_skin);
                    if (!empty($activitySkinInfo)) {
                        $qrcode = $skinModel->createQrCodeAndUploadBos(md5(microtime()) . rand(0,100) . 'activity.png', $activitySkinInfo['share_url']);
                        if (false !== $qrcode) {
                            $qrcode = GFunc::getGlobalConf('bos_host') . '/imeres/' . $qrcode;
                            $res['data']['share_qrcode'] = $qrcode;
                        }
                    }

                    unset($res['data']['weixin_title']);
                    unset($res['data']['weixin_desc']);
                    unset($res['data']['weibo_text']);
                }
                else
                {
                    throw new NotFound();
                }
            }
        }
        return $res;
    }

    /**
     * [skinImageMap description]
     * @param  [type] $res       [description]
     * @param  [type] $skin_data [description]
     * @return [type]            [description]
     */
    private function skinImageMap($res, $skin_data)
    {
        $preimgMap = array(
            300 => 300,
            280 => 160,
            200 => 150,
            150 => 100,
        );
        foreach ($preimgMap as $real => $size) {
            if (300 == $size) {
                $res['data']['imgt_'.$size] = str_ireplace($this->pre_path, '', $skin_data['pic_1_0']) . '@q_70';
            } else {
                $res['data']['imgt_'.$size] = str_ireplace($this->pre_path, '', $skin_data['pic_1_'.$real]);
            }
            $res['data']['imgs_'.$size] = '';
            $res['data']['imgshd_'.$size] = '';
            for ($k = 1; $k < 4; $k++){
                if (isset($skin_data['pic_'.$k.'_'.$real]) && mb_strlen(trim($skin_data['pic_'.$k.'_'.$real])) > 0) {
                    $res['data']['imgs_'.$size] .= ','.str_ireplace($this->pre_path, '', trim($skin_data['pic_'.$k.'_'.$real]));
                }
                if (isset($skin_data['pic_'.$k.'_0']) && mb_strlen(trim($skin_data['pic_'.$k.'_0'])) > 0) {
                    $res['data']['imgshd_'.$size] .= ','.str_ireplace($this->pre_path, '', trim($skin_data['pic_'.$k.'_0']));
                }
            }
            $res['data']['imgs_'.$size] = ltrim($res['data']['imgs_'.$size], ',');
            $res['data']['imgshd_'.$size] = ltrim($res['data']['imgshd_'.$size], ',');
        }

        return $res;
    }

    /**
     * @param array $skin 皮肤主题数据
     * @param string $plt 平台号
     * @param string $ver_name 版本号
     * @return int
     */
    private function isSupport($skin, $plt, $ver_name, $is_skin)
    {
        $ret = '0';
        $versionCheck = false;
        $verNameTwo = Util::getVersionIntValueTwo($ver_name);
        $ver_name = Util::getVersionIntVal($ver_name);
        //先判断版本号是否支持，一个皮肤有多个皮肤包，所以每个皮肤包的版本号也要判断
        if ($is_skin && !empty($skin['package_version'])) {
            $arrPackageVersion = explode('|', $skin['package_version']);
            foreach ($arrPackageVersion as $arrPackageVersionK => $arrPackageVersionV) {
                $packageVersionDetail = explode('-', $arrPackageVersionV);
                if ($ver_name >= @$packageVersionDetail[0] && $ver_name <= @$packageVersionDetail[1] && (0 == @$packageVersionDetail[3] || $ver_name != @$packageVersionDetail[3]) && (0 == @$packageVersionDetail[4] || @$packageVersionDetail[4] != $verNameTwo)) {
                    $versionCheck = true;
                    break;
                }
            }
        } else {
            $versionCheck = true;
        }

        $plt_name = strpos($plt, 'i') === 0 ? 'ios' : 'android';

        if($versionCheck
         && ($skin['platform_code'] == 'all' || strpos($skin['platform_code'], $plt) !== false)
         && ($skin['except_platform_code'] == 'empty' || strpos($skin['except_platform_code'], $plt) === false)
         && (($is_skin && ($skin['os'] == 'com' || 'all' == $skin['os'] || $skin['os'] == $plt_name)) || (!$is_skin && ($skin['os'] == 'all' || $skin['os'] == $plt_name)))) {
            $ret = '1';
        }

        return $ret;
    }

    /**
     * 获取分享连接
     * @param   $skin
     * @param   $isLocal
     * @param   $is_custom
     * @param   $smarty_type
     * @return
     */
//    private function getShareUrl($skin, $isLocal, $is_custom, $smarty_type)
//    {
//        $shareUrl = '';
//        if (!$isLocal)
//        {
//            $shareUrl = $this->share_url_pre . "?id={$skin['data']['id']}&ad_test={$skin['data']['share_type']}"
//                . (empty($skin['data']['is_skin']) ? "&type=t" : "&type=s");
//            $shareUrl .= ('&smarty_type=' . $smarty_type);
//            $shareUrl .= ('&is_custom=' . $is_custom);
//            !empty($skin['data']['share_url']) && $shareUrl .= ('&' . $skin['data']['share_url']);
//        }
//        return $shareUrl;
//    }

    /**
     * @desc 获取皮肤主题分享数据
     * @param  array $skin 皮肤主题数据
     * @param bool $isLocal 是否客户端本地请求 true 不会查询数据库
     * @param array $activitySkinInfo 明星打榜运营活动数据，活动结束可删除
     * @param string $os
     * @param int $isSkin
     * @return array
     */
    private function getSkinShareData($skin = array(), $isLocal = false, $activitySkinInfo=array(), $os='', $isSkin=1) {

        //Edit by fanwenli on 2015-12-04. If result has tj_video, type is video. If not but has tj_voice, type is voice. If it has not either of them but has gif, type is gif. If it has not these things, type is empty.
//        if(isset($skin['data']['tj_video']) && $skin['data']['tj_video'] != '') {
//            $smarty_type = 'video';
//        }
//        elseif(isset($skin['data']['tj_voice']) && $skin['data']['tj_voice'] != '') {
//            $smarty_type = 'voice';
//        }
//        elseif(isset($skin['data']['tj_gif']) && $skin['data']['tj_gif'] != '') {
//            $smarty_type = 'gif';
//        }
//        else {
//            $smarty_type = '';
//        }

        //Edit by fanwenli on 2015-12-04. Set is_custom is 0.
//        $is_custom = 0;
//        $shareUrl = $this->getShareUrl($skin, $isLocal, $is_custom, $smarty_type);
        $host = GFunc::getGlobalConf('activityHost');
        $shareUrl = $host . '/static/activitysrc/skinshare/index.html?token=' . $skin['data']['token'];
        //明星打榜运营活动
        if (!empty($activitySkinInfo)) {
            $shareUrl = $activitySkinInfo['share_url'];
        }

        //判断当前皮肤是哪个平台的皮肤，选取ios皮肤id拼到参数后面
//        $skinOs = 'android';
//        if ('ios' == $skin['data']['os']) {
//            $skinOs = 'ios';
//        } else if ('all' == $skin['data']['os'] || empty($skin['data']['os'])) {
//            if (false !== strpos($skin['data']['platform_code'], 'i')) {
//                $skinOs = 'ios';
//            } else if ((empty($skin['data']['platform_code']) || 'all' == $skin['data']['platform_code'])
//                && (empty($skin['data']['except_platform_code']) || 'empty' == $skin['data']['except_platform_code']
//                    || (false !== strpos($skin['data']['except_platform_code'], 'a') && 'all' != $skin['data']['except_platform_code'] && false === strpos($skin['data']['except_platform_code'], 'i')))) {
//                $skinOs = 'ios';
//            }
//        }

        //如果当前皮肤是ios的皮肤参数直接拼接当前皮肤id，如果不是，需要从share_url里解析出ios_id
//        $shareSkinId = $skin['data']['id'];
//        $skinCate = $skin['data']['cate'];
//        $skinCateId = $skin['data']['cateid'];
//        if ('ios' != $skinOs) {
//            if (!empty($skin['data']['share_url'])) {
//                $arrShareUrl = explode('&', $skin['data']['share_url']);
//                foreach ($arrShareUrl as $arrShareUrlV) {
//                    if (false !== strpos($arrShareUrlV, 'ios_id')) {
//                        $arrIosId = explode('=', $arrShareUrlV);
//                        $shareSkinId = $arrIosId[1];
//                        //获取对应ios皮肤数据
//                        $iosSkinInfo = $this->fetchOneSkinTheme($shareSkinId);
//                        $category_arr = $this->getCategoryFunction($iosSkinInfo['categories'],$iosSkinInfo['categories_name']);
//                        $skinCateId = '';
//                        if (!empty($category_arr['skin']['id'][0])) {
//                            $skinCateId = 's' . $category_arr['skin']['id'][0];
//                        } else if (!empty($category_arr['theme']['id'][0])) {
//                            $skinCateId = 't' . $category_arr['theme']['id'][0];
//                        }
//                        $skinCate = '';
//                        if (!empty($category_arr['skin']['name'][0])) {
//                            $skinCate = $category_arr['skin']['name'][0];
//                        } else if (!empty($category_arr['theme']['name'][0])) {
//                            $skinCate = $category_arr['theme']['name'][0];
//                        }
//                        break;
//                    }
//                }
//            }
//        }
//        $arrShareData = array(
//            "id" => 1 == $skin['data']['is_skin'] ? 's' . $shareSkinId : 't' . $shareSkinId,
//            'cate' => $skinCate,
//            'cateId' => $skinCateId,
//            'name' => $skin['data']['name'],
//        );
//        $strParams = '&native_url=' . urlencode('baiduimsettings2://SkinCategoryPreviewItem?parameters=' . urlencode(json_encode($arrShareData)));
//        $shareUrl = $shareUrl . $strParams;

        //分享第二张图片
        $bosHost = GFunc::getGlobalConf('bos_domain_pre_http');
        if ($isSkin) {
            if ('ios' == $os) {
                $image = !empty($skin['data']['pic_2_1_ios']) ? $bosHost . $skin['data']['pic_2_1_ios'] . '@w_300' : '';
                $thumb = !empty($skin['data']['pic_2_1_ios']) ? $bosHost . $skin['data']['pic_2_1_ios'] . '@w_100' : '';
            } else {
                $image = !empty($skin['data']['pic_2_1_android']) ? $bosHost . $skin['data']['pic_2_1_android'] . '@w_300' : '';
                $thumb = !empty($skin['data']['pic_2_1_android']) ? $bosHost . $skin['data']['pic_2_1_android'] . '@w_100' : '';
            }
        } else {
            $imgshd = !empty($skin['data']['imgshd_300']) ? explode(',', $skin['data']['imgshd_300']) : array();
            $image  = !empty($imgshd[1]) ? ($skin['domain'] . $skin['imgpre'] . $imgshd[1]) : '';
            $thumb  = !empty($skin['data']['pic_2_100']) ? ($skin['domain'] . $skin['imgpre'] . $skin['data']['pic_2_100']) : '';
        }

        $weiboTitle = $isLocal
            ? "我的百度输入法皮肤效果很赞吧！快用百度输入法，制作属于你的专属皮肤吧：http://srf.baidu.com?ad_test={$skin['data']['share_type']}"
            : "我正在用百度输入法，发现一款超赞的皮肤：{$skin['data']['name']}，好东西大家一起用：{$shareUrl}";
        $weixinTitle = $isLocal ? ''
            :"推荐「{$skin['data']['name']}」这款皮肤给你啦";
        $weixinDesc = $isLocal ? ''
            :"我正在用百度输入法，发现一款超赞的皮肤：{$skin['data']['name']}，好东西大家一起用！";
        $weixinCircleTitle = $isLocal ? ''
            : "百度输入法 {$skin['data']['name']} 这款皮肤好赞，分享给大家一起用！";


        //Edit by fanwenli on 2015-12-02. Sharing video url is judged by tj_video
        if($skin['data']['tj_video'] != '') {
            $video_url = $shareUrl;
        }
        else {
            $video_url = '';
        }

        //!empty($skin['data']['share_url'])    && $shareUrl .= ('&' . $skin['data']['share_url']);
        //!empty($skin['data']['weibo_text'])   && $weiboTitle = $skin['data']['weibo_text'] . ' ' . $shareUrl;
        //Edit by fanwenli on 2015-11-11. Changing weibo share text
        !empty($skin['data']['weibo_text'])   && $weiboTitle = $skin['data']['weibo_text'] . ' ' . $shareUrl;
        !empty($skin['data']['weixin_title']) && $weixinTitle = $skin['data']['weixin_title'];
        !empty($skin['data']['weixin_title']) && $weixinCircleTitle = $skin['data']['weixin_title'];
        !empty($skin['data']['weixin_desc'])  && $weixinDesc = $skin['data']['weixin_desc'];
        $qqTitle = $weixinTitle;
        $qzoneTitle = $weixinTitle;

        $res = array(
            "title" => '',
            "description" => '',
            "url" => $shareUrl,
            "image" => $image,
            "thumb" => $thumb,
            'video_url' => $video_url,
            'platform' => array(
                array(
                    'name' => 'weixin',
                    'content' => array(
                        "title" => $weixinTitle,
                        "description" => $weixinDesc,
                        "url" => $shareUrl ? $shareUrl . "&platform=weixin" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                        'video_url' => $video_url ? $video_url . "&platform=weixin" : '',
                    ),
                ),
                array(
                    'name' => 'weixincircle',
                    'content' => array(
                        "title" => $weixinCircleTitle,
                        "description" => '',
                        "url" => $shareUrl ? $shareUrl . "&platform=weixincircle" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                        'video_url' => $video_url ? $video_url . "&platform=weixincircle" : '',
                    ),
                ),
                array(
                    'name' => 'weibo',
                    'content' => array(
                        "title" => $weiboTitle . "&platform=weibo",
                        "description" => '',
                        "url" => '',
                        "image" => $image,
                        "thumb" => $thumb,
                        'video_url' => '',
                    ),
                ),
                array(
                    'name' => 'qq',
                    'content' => array(
                        "title" => $qqTitle,
                        "description" => $weixinDesc,
                        "url" => $shareUrl ? $shareUrl . "&platform=qq" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                        'video_url' => $video_url ? $video_url . "&platform=qq" : '',
                    ),
                ),
                array(
                    'name' => 'qzone',
                    'content' => array(
                        "title" => $qzoneTitle,
                        "description" => $weixinDesc,
                        "url" => $shareUrl ? $shareUrl . "&platform=qzone" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                        'video_url' => $video_url ? $video_url . "&platform=qzone" : '',
                    ),
                ),
                array(
                    'name' => 'system',
                    'content' => array(
                        "title" => $weixinTitle,
                        "description" => $weixinDesc,
                        "url" => $shareUrl ? $shareUrl . "&platform=system" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                        'video_url' => $video_url ? $video_url . "&platform=system" : '',
                    ),
                ),
            ),
        );

        return $res;
    }

    /**
     * @desc 搜索建议
     * @route({"GET", "/suggest"})
     * @param({"k", "$._GET.k"}) string $k 搜索关键词
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @param({"wap_os", "$._GET.wap_os"}) string $wap_os wap站识别客户端系统参数 客户端不需要传
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "suglist": [
                                "【bl1985】嫁给我吧",
                                "EXO-M队-520",
                                "EXO出道520",
                                "速度与激情5",
                                "HIT-5",
                                "【bl1985】LOVE"
                            ]
                        }
     */
    function suggest($k = '', $sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320,
        $ver_name = '5.4.0.0', $foreign_access = false, $wap_os = ''){

        $args = func_get_args();
        $cacheKeySuffix = '';
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $resolution_filter_ids = $conditionFilter->getEffectiveFilterIds(ConditionFilter::RESOLUTION_FILTER_TYPE);
        if(!empty($resolution_filter_ids)) {
            $cacheKeySuffix .= implode(',', $resolution_filter_ids);
        }

        $key = __CLASS__ . __METHOD__ . implode('_', $args) . $cacheKeySuffix;
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $res = array();
            $res['suglist'] = array();
            $k = trim($k);

            if ($wap_os && !$plt && mb_strlen($k) < 1)
            {
                $res['suglist'] = $this->getWapSuggest($wap_os, $sf, $num);
                return json_encode($res);
            }

            $wap_os && !$plt && $plt = $this->getPltByWapos($wap_os);
            $wap_os && $ver_name == '5.4.0.0' && $ver_name = $this->getVersionByWapos($wap_os);

            if (mb_strlen($k) < 1) {
                return json_encode($res);
            }
            $k = str_ireplace(array('%', '\'', '"', '#', '\\'), ' ', strip_tags($k));
            $sf = abs(intval($sf));
            $num = abs(intval($num));
            if ($num < 1) {
                $num = self::GENERAL_PAGE_SIZE;
            }
            elseif ($num > self::MAX_PAGE_SIZE){
                $num = self::MAX_PAGE_SIZE;
            }

            $skinModel = IoCload("models\\SkinthemeModel");

            $except_cats = $this->except_cats;

            $tmp = $skinModel->getSuggest($k, $sf, $num, $plt, $screen_w, $screen_h, $ver_name, '', $foreign_access, $except_cats, 0, '', $resolution_filter_ids);
            if (is_array($tmp) && count($tmp) > 0) {
                $i = 0;
                $sort_arr = array();
                foreach ($tmp as $value) {
                    if (isset($value['title'])) {
                        //如果sug数量大于3，则对于排序在3个之后的sug按id倒序排序，即保持sug中前三为热门下载，之后为最新
                        if ($i < 3) {
                            $res['suglist'][] = $value['title'];
                        }
                        elseif (isset($value['id'])){
                            $sort_arr[intval($value['id'])] = $value['title'];
                        }

                        $i++;
                    }
                }
                if ($sort_arr) {
                    krsort($sort_arr);
                    $res['suglist'] = array_merge($res['suglist'], array_values($sort_arr));
                }
            }

            GFunc::cacheSet($key, $res, Gfunc::getCacheTime('2hours') * 12);
        }

        return $res;
    }

    /**
     * 皮肤搜索建议（人工配置）
     * @param $wap_os wap站识别系统参数
     * @param $k      搜索词
     * @param $sf     页数
     * @param $num    个数
     * @return array
     */
    private function getWapSuggest($wap_os, $sf = 0, $num = self::GENERAL_PAGE_SIZE)
    {
        $plt = $this->getPltByWapos($wap_os);
        $ver_name = $this->getVersionByWapos($wap_os);
        $ver_name = Util::getVersionIntVal($ver_name, '-');

        $skin_cond = ' AND os in ("com","all","android") ';
        if (strtolower(substr($plt, 0, 1)) == 'i')
        {
            $skin_cond = ' AND iossupported = "Y" AND (os in ("iphone","com","all")) ';
        }

        $skin_cond  .= ' AND min_version_int <= "'.$ver_name.'" AND max_version_int >= "'.$ver_name.'" ';

        $sql =
            "select a.id,a.skin_id,b.title from input_skins_suggest a
                left join input_skinthemes b on a.skin_id=b.id
                where b.status=100
                and (b.platform_code='all' or FIND_IN_SET('{$plt}', REPLACE(b.platform_code, '|', ','))>0)
                and (b.except_platform_code='empty' or FIND_IN_SET('{$plt}', REPLACE(b.except_platform_code, '|', ','))=0)
                " . $skin_cond . "order by a.id asc";

        $sugs = $this->getDB()->queryf($sql . ' LIMIT %n,%n', $sf, $num);
        $ret = array();

        foreach ($sugs as $v)
        {
            if (!empty($v['title']))
            {
                $ret[] = $v['title'];
            }
        }

        return $ret;
    }

    /**
     * @desc 搜索结果，若搜索为空则显示猜你喜欢，若又为空，则显示官方推荐
     * @route({"GET", "/search"})
     * @param({"k", "$._GET.k"}) string $k 搜索关键词
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @param({"wap_os", "$._GET.wap_os"}) string $wap_os wap站识别客户端系统参数 客户端不需要传
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "imgpre": "upload/imags/",
                            "is_sug": 0,搜索要是无结果，则为1
                            "list": [
                                {
                                    "id": 1424,
                                    "author": "meak",
                                    "name": "叶祖新",
                                    "down": "553",
                                    "ver": "2",
                                    "cateid": "tmx",
                                    "url": "http://10.58.19.57:8890/v5/skin/items/t1424/file?reserve=1",
                                    "version_code": 1,
                                    "size": "628.5K",
                                    "token": "01dcb38338b17ab85f93f17330d0ea24",
                                    "cate": "明星名人",
                                    "skin_cate_id": "tmx,tys",
                                    "skin_cate_name": "明星名人,影视娱乐",
                                    "theme_cate_id": "tqx,txx",
                                    "theme_cate_name": "静物风景,特效主题",
                                    "desc": "",
                                    "remarks": "",
                                    "is_skin": 0,皮肤为1主题为0
                                    "imghd": "2014/12/11/ka92vxqf14.png",
                                    "imgt_300": "2014/12/11/ka92vxqf14_300.jpg",
                                    "imgs_300": "2014/12/11/ka92vxqf14_300.jpg,2014/12/11/nhkc5a98wt_300.jpg",
                                    "imgshd_300": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "imgt_160": "2014/12/11/ka92vxqf14_280.jpg",
                                    "imgs_160": "2014/12/11/ka92vxqf14_280.jpg,2014/12/11/nhkc5a98wt_280.jpg",
                                    "imgshd_160": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "imgt_150": "2014/12/11/ka92vxqf14_200.jpg",
                                    "imgs_150": "2014/12/11/ka92vxqf14_200.jpg,2014/12/11/nhkc5a98wt_200.jpg",
                                    "imgshd_150": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "imgt_100": "2014/12/11/ka92vxqf14_150.jpg",
                                    "imgs_100": "2014/12/11/ka92vxqf14_150.jpg,2014/12/11/nhkc5a98wt_150.jpg",
                                    "imgshd_100": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "pub_item_type": "non_ad",
                                    "display_types": "new",
                                    "tj_video": "",  //特技皮肤视频素材
                                    "tj_voice": "",  //特技皮肤音频素材
                                    "tj_gif": "",    //特技皮肤动态图
                                    "abilities": "",  //皮肤详情支持Tag
                                    "promote_type": "",  //皮肤支持不支持推广
                                    "tj_video_thumb": "",  //皮肤详情支持视频缩略图
                                    "tj_gif_thumb": "",  //皮肤详情支持GIF缩略图
                                }
                            ]
                        }
     */
    function search($k = '', $sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $cuid = '', $foreign_access = false, $wap_os = ''){
        $res = array('domain' => $this->domain_v4, 'imgpre' => $this->pre_path, 'is_sug' => 0, 'list' => array());
        //wap 识别系统
        $wap_os && !$plt && $plt = $this->getPltByWapos($wap_os);
        $wap_os && $ver_name == '5.4.0.0' && $ver_name = $this->getVersionByWapos($wap_os);

        $sf = abs(intval($sf));
        $num = abs(intval($num));
        if ($num < 1) {
            $num = self::GENERAL_PAGE_SIZE;
        }
        elseif ($num > self::MAX_PAGE_SIZE){
            $num = self::MAX_PAGE_SIZE;
        }
        //$sort = ' ORDER BY recommend DESC, online_time ';//主题搜索market接口排序

        $k = trim(urldecode($k));
        if (mb_strlen($k) < 1)
        {
            return $res;
        }
        else
        {
            $sort = 'search';
            $skinModel = IoCload('models\\SkinthemeModel');
            $res['list'] = $skinModel->getSkinthemeWithCache($sf, $num, $plt, $screen_w, $screen_h, $ver_name, $sort, $k, '', '',$foreign_access, 'search', array(array('plt' => $plt, 'ver_name' => $ver_name), array('plt' => $plt)));

            if ($sf < 1 && (!is_array($res['list']) || count($res['list']) < 1)) {
                //官方推荐
                $sort = "offcial_recommend";
                $res['list'] = $skinModel->getSkinthemeWithCache($sf, self::GENERAL_PAGE_SIZE, $plt, $screen_w, $screen_h, $ver_name, $sort, '', '', 'search',$foreign_access, 'search', array(array('plt' => $plt, 'ver_name' => $ver_name), array('plt' => $plt)));
                $res['is_sug'] = 1;
            }
        }

        return $res;
    }

    /**
     * @desc 市场，sort为down表示按“排行”排序，如果是skinonly则只筛选皮肤(for ios)，不传或其他值为推荐
     * @route({"GET", "/market"})
     * @param({"sort", "$._GET.sort"}) string $sort 排序规则
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"umode", "$._GET.umode"}) string $umode 模式
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @param({"recommend_type", "$._GET.recommend_type"}) 获取推荐类别 0-不推荐 1-经典 2-二次元
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "imgpre": "upload/imags/",
                            "list": [
                                {
                                    "id": 1424,
                                    "author": "meak",
                                    "name": "叶祖新",
                                    "down": "553",
                                    "ver": "2",
                                    "cateid": "tys",
                                    "url": "http://10.58.19.57:8890/v5/skin/items/t1424/file?reserve=1",
                                    "version_code": 1,
                                    "size": "628.5K",
                                    "token": "01dcb38338b17ab85f93f17330d0ea24",
                                    "cate": "影视娱乐",
                                    "skin_cate_id": "tys,tmx",
                                    "skin_cate_name": "影视娱乐,明星名人",
                                    "theme_cate_id": "tqx,txx",
                                    "theme_cate_name": "静物风景,特效主题",
                                    "desc": "",
                                    "remarks": "",
                                    "is_skin": 0, 皮肤为1主题为0
                                    "imghd": "2014/12/11/ka92vxqf14.png",
                                    "imgt_300": "2014/12/11/ka92vxqf14_300.jpg",
                                    "imgs_300": "2014/12/11/ka92vxqf14_300.jpg,2014/12/11/nhkc5a98wt_300.jpg",
                                    "imgshd_300": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "imgt_160": "2014/12/11/ka92vxqf14_280.jpg",
                                    "imgs_160": "2014/12/11/ka92vxqf14_280.jpg,2014/12/11/nhkc5a98wt_280.jpg",
                                    "imgshd_160": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "imgt_150": "2014/12/11/ka92vxqf14_200.jpg",
                                    "imgs_150": "2014/12/11/ka92vxqf14_200.jpg,2014/12/11/nhkc5a98wt_200.jpg",
                                    "imgshd_150": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "imgt_100": "2014/12/11/ka92vxqf14_150.jpg",
                                    "imgs_100": "2014/12/11/ka92vxqf14_150.jpg,2014/12/11/nhkc5a98wt_150.jpg",
                                    "imgshd_100": "2014/12/11/ka92vxqf14.png,2014/12/11/nhkc5a98wt.png",
                                    "pub_item_type": "non_ad",
                                    "display_types": "new",
                                    "tj_video": "",  //特技皮肤视频素材
                                    "tj_voice": "",  //特技皮肤音频素材
                                    "tj_gif": "",    //特技皮肤动态图
                                    "abilities": "",  //皮肤详情支持Tag
                                    "promote_type": "",  //皮肤支持不支持推广
                                    "tj_video_thumb": "",  //皮肤详情支持视频缩略图
                                    "tj_gif_thumb": "",  //皮肤详情支持GIF缩略图
                                    "recommend_level": "3" //皮肤推荐等级
                                }
                            ]
                        }
     * @return $array
     */
    public function market($sort = '', $sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320,
        $ver_name = '5.4.0.0', $umode = 0, $foreign_access = false, $recommend_type = 0)
    {
        //Edit by fanwenli on 2016-01-08. Add recommend_type for order
        $res = array('domain' => Util::getV5Domain(), 'imgpre' => $this->pre_path, 'list' => array());
        $sort = strtolower(trim($sort));
        $skinModel = IoCload('models\\SkinthemeModel');

        if ($sort != 'down')
        {
            $res['list'] = $skinModel->getMarketListWithCache($sf, $num, $plt, $screen_w, $screen_h, $ver_name, $sort, '', '', 'gftj', $foreign_access, 'market', $recommend_type);
        }
        else
        {
            $res['list'] = $skinModel->fetchSkinsByDownWithCache($sf, $num, $plt, $screen_w, $screen_h, $ver_name, $umode,
                '', 'gftj', $foreign_access, $this->except_cats, 'market');
        }

        return $res;
    }

    /**
     * @desc 皮肤主题下载(暂时跳转V4接口)
     * @route({"GET", "/items/*\/file"})
     * @param({"id", "$.path[2]"}) string $id 皮肤id以s开头，主题id以t开头
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @param({"versionName", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     * 验证通过则：
     * HTTP/1.1 302 Found
     * Location: 皮肤主题下载链接
     * 验证不通过则：
     * HTTP/1.1 404 Not Found
     */
    public function download($id = '', $cuid = '', &$status = '', &$location = '', $versionName='') {
        $is_skin = (mb_substr($id, 0, 1) == 's') ? 1 : 0;
        $id = mb_substr($id, 1);
        //兼容主题ID+4000
        !$is_skin && is_numeric($id) && $id < 4000 && $id +=4000;

        $skinModel = IoCload("models\\SkinthemeModel");
        $skin = $skinModel->cache_getDownInfo($id, $versionName);
        $downloadTimesStrategy = array(
            array(
                "match" => array($skinModel, "matchDownloadStrategy"),
                "handler" => function($params) {
                    if (!isset($params["id"]) || empty($params["id"])) {
                        return true;
                    }

                    if (!isset($params["cuid"]) || empty($params["cuid"])) {
                        return true;
                    }

                    $cache = GFunc::getCacheInstance();
                    $cacheKey = md5(__FUNCTION__ . __CLASS__ . __LINE__ . serialize($params) . date("Ymd"));
                    $data = $cache->getOrigin($cacheKey);
                    if (!$data) {
                        $cache->setOrigin($cacheKey, 1, GFunc::getCacheTime("hours") * 24);
                        return true;
                    }

                    // 每天限制下载3次
                    if ($data >= 3) {
                        return false;
                    }

                    $data += 1;
                    $cache->setOrigin($cacheKey, $data, GFunc::getCacheTime("hours") * 24);
                    return true;
                }
            )
        );
        $handler = null;
        foreach ($downloadTimesStrategy as $item) {
            if(call_user_func($item['match'], array("id"=>$id))) {
                $handler = $item["handler"];
                break;
            }
        }

        if (empty($handler) || call_user_func($handler, array("id"=>$id, "cuid"=> $cuid))) {
            // 当前皮肤id实时处理
            $skinModel->showDownloadTimesImmediatelyHandler($skin[0]['id']);
            // 关联皮肤实时处理
            if($skin[0]['corresponding_id']) {
                $skinModel->showDownloadTimesImmediatelyHandler($skin[0]['corresponding_id']);
            }
        }


        if (!empty($skin[0]['download_link'])) {
//            $url = $this->domain_v4.$skin[0]['download_link'];
            $url = Util::getV5Domain().$skin[0]['download_link'];

            $status = "302 Found";
            $location = "Location: ".$url;
            $cuid = trim($cuid);

            if (!$is_skin && !empty($cuid)) {
                //只统计主题的
                $this->RecommendCheck($cuid, $id);
            }
        } else {
            $status = "404 Not Found";
        }
        return ;
    }

    /**
     * @desc 查询某款皮肤主题
     * @param int $id
     * @param boolean $is_skin
     * @param string $token
     * @param string $from_method 调用的接口方法名字，用于记录缓存
     * @return array
     */
    private function fetchOneSkinTheme($id, $from_method = '')
    {
        $res = array();
        $id = trim($id);

        $internal_cache_key_prefix = self::INTERNAL_CACHE_KEY_PREFIX.'one_skin_';
        if ($id)
        {//id
            $cache_get_status = null;
            $res = $this->cache->get($internal_cache_key_prefix.$id, $cache_get_status);
            if ($cache_get_status === false || is_null($res))
            {
                $con = 'v.token="%s"';
                if(is_numeric($id)){
                    $con = 'v.id="%n"';
                }
                $sql = 'SELECT v.*,c.categories,c.categories_name FROM input_skinthemes v, input_skin_theme_category c WHERE '.$con.' AND v.status=100 and c.skin_theme_id=v.id';

                $res = $this->getDB()->queryf($sql, $id);

                if ($res !== false && isset($res[0]))
                {
                    $res = $res[0];
                    $this->cache->set($internal_cache_key_prefix.$id, $res, $this->intCacheExpired);
                    //deprecated on 20161227 by lipengcheng02
                    /*
                    if($from_method !== "")
                    {
                        $this->internalCacheManage($internal_cache_key_prefix.$id, $from_method);
                    }
                    */
                }
            }
        }

        return $res;
    }

    /**
     * @desc 自然分类
     * @route({"GET", "/cate"})
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @param({"wap_os", "$._GET.wap_os"}) string $wap_os wap站识别客户端系统参数 客户端不需要传
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "list": [
                                {
                                    "name": "卡通",
                                    "cateid": "skt",
                                    "pic": "upload/imags/2012/04/27/7y40w9085w_120.jpg",
                                    "pub_item_type": "non_ad"
                                },
                                {
                                    "name": "节日节气",
                                    "cateid": "tjr",
                                    "pic": "upload/imags/2012/04/11/fk1gdk546u_120.jpg",
                                    "pub_item_type": "non_ad"
                                }
                            ]
                        }
     */
    function category($sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $ver_name = '5.4.0.0', $foreign_access = false, $wap_os = ''){
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $res = array('domain' => $this->domain_v4, 'list' => array());
            $sf = abs(intval($sf));
            $num = abs(intval($num));
            if ($num < 1) {
                $num = self::GENERAL_PAGE_SIZE;
            }
            elseif ($num > self::MAX_PAGE_SIZE){
                $num = self::MAX_PAGE_SIZE;
            }
            $sort = ' ORDER BY pri DESC ';
            //识别wap站平台号
            $wap_os && !$plt && $plt = $this->getPltByWapos($wap_os);
            $wap_os && $ver_name == '5.4.0.0' && $ver_name = $this->getVersionByWapos($wap_os);

            $plt = addslashes($plt);
            $plat_sql = ' WHERE (plat_allow = "all" OR FIND_IN_SET("'.$plt.'", REPLACE(plat_allow, "|", ",")) > 0) ';
            $plat_sql .= ' AND (plat_deny = "" OR plat_deny IS NULL OR FIND_IN_SET("'.$plt.'", REPLACE(plat_deny, "|", ",")) = 0) ';
            $except_cat_sql = '';
            if ($foreign_access && strtolower(mb_substr($plt, 0, 1)) == 'i' && is_array($this->except_cats) && count($this->except_cats) > 0) {//iOS平台暂时去除
                $except_cats = implode('","', $this->except_cats);
                $except_cat_sql = ' AND category_id NOT IN ("'.$except_cats.'") ';
            }
            $sql = '( SELECT DISTINCT category_name AS name, CASE WHEN type =1 THEN CONCAT("s",category_id) ELSE CONCAT("t",category_id) END as cateid, pic, pri, pub_item_type FROM input_skin_theme_categories';
            $sql .= $plat_sql;
            $verNameTwo = Util::getVersionIntValueTwo($ver_name);
            $ver_name = Util::getVersionIntVal($ver_name, '-');
            $sql .= ' AND (min_version_int <= "'.$ver_name.'" AND (max_version_int >= "'.$ver_name.'" OR max_version_int = 0 OR max_version_int IS NULL OR max_version_int = "") AND (except_version_int=0 OR except_version_int != "'.$ver_name.'") AND (except_version_int_two=0 OR except_version_int_two!="' . $verNameTwo . '"))';
            $sql .= $except_cat_sql;
            $sql .= ' ) ';
            //die($sql);
            $res['list'] = $this->getDB()->queryf($sql.' '.addslashes($sort).' LIMIT %n,%n', $sf, $num);
            if (is_array($res['list']) && count($res['list'])) {
                foreach ($res['list'] as $k => &$v) {
                    unset($v['pri']);
                }
            }
            if ($res['list'] === false) {
                $res['list'] = array();
            }
            GFunc::cacheSet($key, $res);
        }


        return $res;
    }

    /**
     * 转化wap站wap_os
     * @param @wap_os wap站系统识别参数
     * @return String
     */
    private function getPltByWapos($wap_os)
    {
        $plt = '';
        switch ($wap_os)
        {
            case 'ios':
                $plt = 'i5';
                break;
            case 'ipad':
                $plt = 'i6';
                break;
            case 'android':
                $plt = 'a1';
                break;
            case 'apad':
                $plt = 'a9';
                break;
        }

        return $plt;
    }

    /**
     * 转化wap站wap_os
     * @param @wap_os wap站系统识别参数
     * @return String
     */
    public function getVersionByWapos($wap_os)
    {
        return '10.0.0.0';
    }

    /**
     * @desc 某自然分类下所有皮肤主题
     * @route({"GET", "/cate/*\"})
     * @param({"cat", "$.path[2]"}) int $cat 某自然分类
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @param({"wap_os", "$._GET.wap_os"}) string $wap_os wap站识别客户端系统参数 客户端不需要传
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "imgpre": "upload/imags/",
                            "list": [
                                {
                                    "id": 766,
                                    "author": "",
                                    "name": "汉服系列齐胸襦裙",
                                    "down": "216123",
                                    "ver": "2",
                                    "cateid": "tqx",
                                    "url": "http://10.58.19.57:8890/v5/skin/items/t766/file?reserve=1",
                                    "version_code": 1,
                                    "size": "285.4K",
                                    "token": "a3323a951b02ece47398091df33ab163",
                                    "cate": "静物风景",
                                    "skin_cate_id": "tqx,txx",
                                    "skin_cate_name": "静物风景,特效皮肤",
                                    "theme_cate_id": "tqx,txx",
                                    "theme_cate_name": "静物风景,特效主题",
                                    "desc": "汉族传统民族服装的一种（汉服），齐胸襦裙旧称高腰襦裙，是对隋唐五代时期特有的一种女子襦裙装的称呼。",
                                    "remarks": "",
                                    "is_skin": 0,
                                    "imghd": "2013/07/04/rhc5zb3wgz.png",
                                    "imgt_300": "2013/07/04/rhc5zb3wgz_300.jpg",
                                    "imgs_300": "2013/07/04/rhc5zb3wgz_300.jpg",
                                    "imgshd_300": "2013/07/04/rhc5zb3wgz.png",
                                    "imgt_160": "2013/07/04/rhc5zb3wgz_280.jpg",
                                    "imgs_160": "2013/07/04/rhc5zb3wgz_280.jpg",
                                    "imgshd_160": "2013/07/04/rhc5zb3wgz.png",
                                    "imgt_150": "2013/07/04/rhc5zb3wgz_200.jpg",
                                    "imgs_150": "2013/07/04/rhc5zb3wgz_200.jpg",
                                    "imgshd_150": "2013/07/04/rhc5zb3wgz.png",
                                    "imgt_100": "2013/07/04/rhc5zb3wgz_80.jpg",
                                    "imgs_100": "2013/07/04/rhc5zb3wgz_80.jpg",
                                    "imgshd_100": "2013/07/04/rhc5zb3wgz.png",
                                    "pub_item_type": "non_ad",
                                    "display_types": "new",
                                    "tj_video": "",  //特技皮肤视频素材
                                    "tj_voice": "",  //特技皮肤音频素材
                                    "tj_gif": "",    //特技皮肤动态图
                                    "abilities": "",  //皮肤详情支持Tag
                                    "promote_type": "",  //皮肤支持不支持推广
                                    "tj_video_thumb": "",  //皮肤详情支持视频缩略图
                                    "tj_gif_thumb": "",  //皮肤详情支持GIF缩略图
                                    "recommend_level": "3" //皮肤推荐等级
                                }
                            ]
                        }
     */
    function cateSkins($cat = '', $sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $foreign_access = false, $wap_os = ''){
        $res = array('domain' => $this->domain_v4, 'imgpre' => $this->pre_path, 'list' => array());
        //识别wap站平台号
        $wap_os && !$plt && $plt = $this->getPltByWapos($wap_os);
        $wap_os && $ver_name == '5.4.0.0' && $ver_name = $this->getVersionByWapos($wap_os);

        $cat = trim($cat);
        if (mb_strlen($cat) < 2)
        {
        //以s或t开头，分别表示皮肤/主题
            return $res;
        }
        else
        {
            if ($foreign_access && strtolower(mb_substr($plt, 0, 1)) == 'i' &&
                is_array($this->except_cats) && count($this->except_cats) > 0)
            {
                //iOS平台暂时去除
                $real_cat = mb_substr($cat, 1);
                if (in_array($real_cat, $this->except_cats))
                {
                    return $res;
                }
            }
            $sort = "cate_skin";
            $skinModel = IoCload('models\\SkinthemeModel');
            $res['list'] = $skinModel->getSkinthemeWithCache($sf, $num, $plt, $screen_w, $screen_h,
                $ver_name, $sort, '', $cat, 'market', $foreign_access, 'cateSkins');
        }

        return $res;
    }

    /**
     * @desc 运营分类&&猜你喜欢
     * @route({"GET", "/opcate"})
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "list": [
                                {
                                    "name": "其他",
                                    "cateid": "opqt",
                                    "pic": "upload/imags/2012/04/27/7y40w9085w_120.jpg",
                                    "pub_item_type": "non_ad"
                                }
                            ]
                        }
     */
    function operationCategory($sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $ver_name = '5.4.0.0', $foreign_access = false){
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $res = array('domain' => $this->domain_v4, 'list' => array());
            $skinModel = IoCload('models\\SkinthemeModel');

            $opcateList = $skinModel->getOpcate($sf, $num, $plt, $ver_name, $foreign_access);
            $res['list'] = $skinModel->checkOpcateTag($opcateList);

            //Edit by fanwenli on 2016-08-25, combine with lite intend
            //Edit by fanwenli on 2017-08-31, add cache key and cache time
            $res['list'] = Util::liteIntendCombine('skin', $res['list'], $this->strSkinthemeLiteIntendCombineCache, $this->intCacheExpired);
            GFunc::cacheSet($key, $res);
        }

        return $res;
    }



    /**
     * @desc 某运营分类下所有皮肤主题
     * @route({"GET", "/opcate/*\"})
     * @param({"cat", "$.path[2]"}) string 运营分类
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
                            "domain": "http://10.58.19.57:8001/",
                            "imgpre": "upload/imags/",
                            "list": [
                                {
                                    //refs 皮肤市场
                                }
                            ]
                        }
     */
    function operationCategorySkins($cat = '', $sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $cuid = '', $foreign_access = false){
        $res = array('domain' => $this->domain_v4, 'imgpre' => $this->pre_path, 'list' => array());
        $sort = '';//就按数据库里的自然顺序排序
        $cat = strtolower(trim($cat));
        $skinModel = IoCload('models\\SkinthemeModel');

        if (mb_strlen($cat) < 3) {
            //都以"op"开头
            return $res;
        } else {
            if (strtolower(mb_substr($cat, 2)) == 'guess' && $sf < 1) {
                //官方推荐
                $sort = 'offcial_recommend';
                $res['list'] = $skinModel->getSkinthemeWithCache($sf, $num, $plt, $screen_w, $screen_h, $ver_name, $sort, '', '', 'market', $foreign_access, 'operationCategorySkins');
            } else {
                $res['list'] = $this->FetchOpcateSkins($sf, $num, $plt, $screen_w, $screen_h, $ver_name, $sort, '', $cat, $foreign_access, 'operationCategorySkins');
            }
        }

        return $res;
    }

    /**
     * @desc 皮肤升级检测
     * @route({"POST", "/upgrade"})
     * @param({"cks", "$._POST.cks"}) 需要检测的皮肤ids/tokens s/t前缀，区分皮肤/主题 json [{id: "id or token", version_code: "0"}]
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"foreign_access", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * {
            "list": [
                {
                    "id": "275",
                    "token": "7dd47124cdcfaa12982642ca146dac30",
                    "version_code": "1",
                    "url": "http://10.58.19.57:8890/v5/skin/items/s266/file?reserve=1",
                    "silent_up": "1",
                    "is_skin": "1"
                }
            ]
        }
     */
    public function upgrade($cks, $plt = '', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $foreign_access = false) {
        //8.6ios迁移新通知中心，数据格式保持统一
        $ret = Util::initialClass(false);
        $cacheGetStatus = null;
        $key = self::INTERNAL_CACHE_KEY_PREFIX.'skin_update_v1_'.md5($cks . $plt . $screen_w . $screen_h . $ver_name . $foreign_access);
        $versionKey = self::INTERNAL_CACHE_KEY_PREFIX . 'skin_update_last_update_time';
        $cachedRet = $this->cache->get($key, $cacheGetStatus);
        $version = $this->cache->get($versionKey);
        if (false === $cacheGetStatus || is_null($cachedRet)) {
            $ret['list'] = array();
            $result = array();
            if ($cks && ($cSkins = json_decode($cks, true)) && is_array($cSkins)) {

                $skins = $this->fetchUpgradeSkins($cSkins, $plt, $screen_w, $screen_h, $ver_name, $foreign_access);

                if (!empty($skins)) {
                    foreach ($skins as $k => $s) {
                        $skins[$k]['url'] = $this->domain_v5 . 'v5/skin/items/'.($s['is_skin'] ? 's' : 't') . $s['id'].'/file?reserve=1&versioncode=' . $s['version_code'];
                        $result[] = $skins[$k];
                    }
                }
                $ret['list'] = $result;
                $ret['data'] = $result;
            }

            //获取最新更新时间
            $skinModel = IoCload('models\\SkinthemeModel');
            $version = $skinModel->getLastTimestamp();
            if (isset($version[0]['update_time'])) {
                $version = strtotime($version[0]['update_time']);
            } else {
                $version = 0;
            }
            $ret['version'] = $version;
            $this->cache->set($key, $result, $this->intCacheExpired / 4);
            $this->cache->set($versionKey, $version);
        } else {
            $ret['list'] = is_array($cachedRet) ? $cachedRet : array();
            $ret['data'] = is_array($cachedRet) ? $cachedRet : array();
            $ret['version'] = intval($version);
        }

        return Util::returnValue($ret, false, true);
    }

    /**
     * @desc 取运营分类下皮肤主题列表，可选排序和分类
     * @param int $sf 条目起始自
     * @param int $num 每次请求返回条数
     * @param string $plt 平台号
     * @param int $screen_w 屏幕像素宽
     * @param int $screen_h 屏幕像素高
     * @param string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * @param string $sort 排序字段，注意必须带有 ORDER BY，不强制排序请留空
     * @param string $keywords 搜索关键词，暂未开放搜索，预留字段
     * @param string $cat 皮肤主题所属运营分类id
     * @param bool $foreign_access 是否国外访问
     * @param string $from_method 调用的接口方法名字，用于记录缓存
     * @return array
     */
    private function fetchOpcateSkins($sf = 0, $num = self::GENERAL_PAGE_SIZE, $plt = 'a1', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $sort = '', $keywords = '', $cat = '', $foreign_access = false, $from_method = ''){
        /** 原有接口有分类筛选，新接口不提供；经鹏程确认，也已不再对layout做筛选*/
        list($sf, $num) = Util::paging($sf, $num);

        $cat = strtolower(trim($cat));
        $cacheKeyBase = $plt.$screen_w.$screen_h.$ver_name.$sort.$keywords.$cat.$sf.$num.$foreign_access;
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $skinModel = IoCload('models\\SkinthemeModel');
        $resolution_filter_ids = $conditionFilter->getEffectiveFilterIds(ConditionFilter::RESOLUTION_FILTER_TYPE);
        if(!empty($resolution_filter_ids)) {
            $cacheKeyBase .= implode(',', $resolution_filter_ids);
        }
        $internal_cache_key = self::INTERNAL_CACHE_KEY_PREFIX.'opcate_sts_'.md5($cacheKeyBase);
        $cache_get_status = null;
        $res = $this->cache->get($internal_cache_key, $cache_get_status);
        if ($cache_get_status === false || is_null($res)) {
            $res = array();
            $cat_filter = '';
            $order_strategy = "s.online_time desc";
            if (!empty($cat)) {
                $real_cat = addslashes(mb_substr($cat, 2));
                if (mb_strlen($real_cat) > 0){
                    $cat_filter = ' AND c.category_id = "'.$real_cat.'" ';
                }

                // 获取分类排序策略
                $category_sql = sprintf(
                    "SELECT `order_strategy` FROM `input_skin_theme_op_cate` WHERE category_id='%s'",
                    $real_cat
                );
                $order_strategies = $this->getDB()->queryf($category_sql);
                if (count($order_strategies) > 0) {
                    $_order_strategy = current($order_strategies);
                    $order_strategy_map = array(
                        1 => "s.online_time desc",
                        2 => "s.download_number desc"
                    );
                    $order_strategy = isset($order_strategy_map[$_order_strategy['order_strategy']])?
                        $order_strategy_map[$_order_strategy['order_strategy']]:$order_strategy;
                }
            }
            $except_cat_skin_sql = '';
            if ($foreign_access && strtolower(mb_substr($plt, 0, 1)) == 'i' && is_array($this->except_cats) && count($this->except_cats) > 0) {//iOS平台暂时去除
                $except_cats = implode('","', $this->except_cats);
                $except_cat_skin_sql = ' AND c.category_id NOT IN ("'.$except_cats.'")  AND s.category NOT IN ("'.$except_cats.'") ';
            }

            $skin_sql = "SELECT
                s.*,
                skin_sub.view_video_lock,
                c.`name`,
                c.category_id,
                case s.type when 1 then 1  else 0 end as is_skin,
                s.skin_theme_ver as ver,
                s_t_c.categories,
                s_t_c.categories_name
            FROM
                input_skin_theme_op_cate_list l, input_skin_theme_op_cate c ,input_skinthemes s ,input_skin_theme_category s_t_c, input_skinthemes_subsidiary AS skin_sub
            WHERE
                s.id = skin_sub.skin_id AND l.opcate_id = c.id and l.skin_id = s.id and s_t_c.skin_theme_id = s.id AND s.status=100 AND c.status=100 and l.skin_id>0" . $cat_filter.$except_cat_skin_sql;

            if (strtolower(substr($plt, 0, 1)) == 'i') {
                $resolution = null;
                switch ($screen_w){
                    case 320: $resolution = '480x320'; break;
                    case 640: $resolution = '960x640'; break;
                    default: $resolution = null;
                }
                $skin_sql .= ' AND s.iossupported = "Y" ';
                $skin_sql .= ' AND (((s.resolution = "'.$resolution.'" OR s.resolution is null OR s.resolution = "") AND s.os in  ("iphone","ios")) OR s.os in ("com","all")) ';
            }
            else{
                //因为原接口只限android跟ios，故上面判断完ios后下面就是android了
                $skin_sql .= ' AND s.os in ("com","all","android") ';
            }
            $skin_sql .= ' AND ' . $skinModel->getSkinResolutionCond($screen_w, $screen_h, "", $resolution_filter_ids);
            $skin_sql .= ' AND s.input_kind = 1 ';
            $skin_sql .= ' AND s.skin_theme_ver = 2 ';//原接口wap布局为2

            $plt = addslashes($plt);
            $skin_sql .= ' AND (s.platform_code = "all" OR FIND_IN_SET("'.$plt.'", REPLACE(s.platform_code, "|", ",")) > 0) ';
            $skin_sql .= ' AND (s.except_platform_code = "empty" OR FIND_IN_SET("'.$plt.'", REPLACE(s.except_platform_code, "|", ",")) = 0) ';
            $verName = Util::getVersionIntVal($ver_name, '-');
            $skin_sql .= ' AND s.visible_version_int <= "'.$verName.'" AND s.max_visible_version_int >= "'.$verName.'" ';
            $tmp = $this->getDB()->queryf($skin_sql . ' group by s.id  '.addslashes($sort).' order by '.$order_strategy.' LIMIT %n,%n', $sf, $num);

            if (count($tmp) > 0) {
                $i = 0;
                $preimgMap = array(
                    300 => 300,
                    280 => 160,
                    200 => 150,
                    150 => 100,
                );

                //查找运营活动皮肤
                // $skinModel = IoCload('models\\SkinthemeModel');
                $activitySkinToken = $skinModel->getActivitySkinToken($ver_name);
                $intClientVerTwo = Util::getVersionIntValueTwo($ver_name);
                $os = Util::getOS($plt);
                foreach ($tmp as $v) {
                    $res[$i] = $skinModel->processSkinFileds($v);
                    $res[$i]['url'] = $res[$i]['url'] . '&cate=' . $cat;
                    $res[$i]['cateid'] = '';
                    $res[$i]["view_video_lock"] = isset($v["view_video_lock"])?$v["view_video_lock"]:"0";

                    $category_arr = $this->getCategoryFunction($v['categories'],$v['categories_name']);
                    if(!empty($category_arr['skin']['id'])) {
                        $res[$i]['skin_cate_id'] = implode(',',$category_arr['skin']['id']);
                        $res[$i]['skin_cate_name'] = implode(',',$category_arr['skin']['name']);
                    }

                    if(!empty($category_arr['theme']['id'])) {
                        $res[$i]['theme_cate_id'] = implode(',',$category_arr['theme']['id']);
                        $res[$i]['theme_cate_name'] = implode(',',$category_arr['theme']['name']);
                    }

                    $res[$i]['opcate'] = $v['name'];
                    $res[$i]['opcateid'] = 'op'.$v['category_id'];

                    //兼容ios7.5版本icon展示
                    if ('ios' == $os && 75000 == $intClientVerTwo) {
                        $res[$i]['abilities'] = in_array($v['token'], $activitySkinToken) ? 'Activity' : $v['abilities'];
                    } else {
                        $res[$i]['abilities'] = $v['abilities'];
                        $res[$i]['is_activity'] = in_array($v['token'], $activitySkinToken) ? 1 : 0;
                    }
                    if (1 == intval($v['is_skin'])) {
                        $isDownload = $skinModel->isDownload($v['package_version'], $ver_name);
                        $res[$i]['force_update_client'] = $isDownload ? 0 : 1;
                    } else {
                        $res[$i]['force_update_client'] = 0;
                    }
                    $res[$i]['imghd'] = str_ireplace($this->pre_path, '', $v['pic_1_0']);
                    foreach ($preimgMap as $real => $size) {
                        if (300 == $size) {
                            $res[$i]['imgt_'.$size] = str_ireplace($this->pre_path, '', $v['pic_1_0']) . '@q_70';
                        } else {
                            $res[$i]['imgt_'.$size] = str_ireplace($this->pre_path, '', $v['pic_1_'.$real]);
                        }
                        $res[$i]['imgs_'.$size] = '';
                        $res[$i]['imgshd_'.$size] = '';
                        for ($k = 1; $k < 4; $k++){
                            if (isset($v['pic_'.$k.'_'.$real]) && mb_strlen(trim($v['pic_'.$k.'_'.$real])) > 0) {
                                $res[$i]['imgs_'.$size] .= ','.str_ireplace($this->pre_path, '', trim($v['pic_'.$k.'_'.$real]));
                            }
                            if (isset($v['pic_'.$k.'_0']) && mb_strlen(trim($v['pic_'.$k.'_0'])) > 0) {
                                $res[$i]['imgshd_'.$size] .= ','.str_ireplace($this->pre_path, '', trim($v['pic_'.$k.'_0']));
                            }
                        }
                        $res[$i]['imgs_'.$size] = ltrim($res[$i]['imgs_'.$size], ',');
                        $res[$i]['imgshd_'.$size] = ltrim($res[$i]['imgshd_'.$size], ',');
                    }
                    ++$i;
                }
            }

            if ($res !== false) {
                $this->cache->set($internal_cache_key, $res, $this->intCacheExpired);
            } else {
                $res = array();
            }
        }

        // $skinModel = IoCload('models\\SkinthemeModel');
        $res = $skinModel->showDownloadTimesDataHandler($res);
        return $res;
    }

    /**
     * 获取需要升级的皮肤主题
     * @param  array $sts  array(array('id', version_code)) 皮肤或主题
     * @return array
     */
    private function fetchUpgradeSkins($sts, $plt = 'a1', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $foreign_access = false)
    {
        $multiNameSkin = array(
            '七夕' => 'd2a61a8b32302a2312bf6283f2aa7e26',
            '万圣节' => '287158131e8686c65bfb1c4c3f6f2a69',
            '哆啦A梦' => '811cb68109e2c9613f702598ed3e0c55',
            '圣诞节' => '1d4b982b835172f4070ee0dcbd4f4f40',
            '小白熊' => '4eb8b4d428153a7c9e0ce027efd18469',
            '年轻多彩' => '8a5bd73e0561e4e029ae4f63c0c72303',
            '情人节' => '067ade80dcad615a2bf724841fca76bd',
            '章鱼小丸子' => '066245f3ee9fa28875d257d5ad4ffc7f',
            '简洁多彩' => 'f846c09d69d0888df2f313ae1de1c49a',
            '该吃药了' => '1b4fde656f7b25b788d53d3e5e18b475',
        );

        $versionName = Util::getVersionIntVal($ver_name, '-');
        $versionNameTwo = Util::getVersionIntValueTwo($ver_name, '-');
        $sCond = '';
        $sSql = '';
        $forceUpdateCond = '';

        $map = array();
        foreach ($sts as $st)
        {
            if (empty($st['id']) && !empty($st['name'])) {
                if (isset($multiNameSkin[$st['name']])) {
                    $st['id'] = 's' . $multiNameSkin[$st['name']];
                } else {
                    $os = Util::getOS($plt);
                    $skinModel = IoCload('models\\SkinthemeModel');
                    $skinInfo = $skinModel->getSkinInfoByName($st['name']);
                    if (false != $skinInfo) {
                        foreach ($skinInfo as $skinInfoK => $skinInfoV) {
                            $skinOs = $skinModel->getOsBySkinInfo($skinInfoV);
                            if ($os == $skinOs) {
                                $st['id'] = 's' . $skinInfoV['token'];
                                break;
                            }
                        }
                    } else {
                        continue;
                    }
                }
            }
            if (empty($st['id'])) {
                continue;
            }
            $idOrToken = $st['id'];
            $version = $st['version_code'];
            $sub = strtolower(mb_substr($idOrToken, 0, 1));
            $id = addslashes(mb_substr($idOrToken, 1));
            if (isset($st['name']) && isset($st['md5'])) {
                $map[$id] = array(
                    'name' => $st['name'],
                    'md5' => $st['md5'],
                );
            }

            if (in_array($sub, array('s', 't')) && !empty($id) && is_numeric($version))
            {
                $con = "i.token='{$id}'";
                if(is_numeric($id)){
                    $con = "i.id='{$id}'";
                }

                $sCond && $sCond .= " or ({$con} and p.version_code > '{$version}') ";
                !$sCond && $sCond .= " ({$con} and p.version_code > '{$version}') ";

                $forceUpdateCond && $forceUpdateCond .= " or {$con} ";
                !$forceUpdateCond && $forceUpdateCond .= " {$con} ";
            }
        }

        /**
         * 可见不可用定义：
         * skin.visible_version_int <= 客户端版本 < 所有皮肤包中最小的min_version_int
         * 可见定义：
         * skin.visible_version_int <= 客户端版本 <= skin.max_visible_version_int
         */
        $exceptCond = "((p.except_version_int=0 and ($versionNameTwo > p.except_version_int_two or $versionNameTwo < p.except_version_int_two)) or (p.except_version_int_two=0 and ($versionName > p.except_version_int or $versionName < p.except_version_int)))";
        $forceUpdateResult = array();
        if($forceUpdateCond) {
            $forceUpdateSql = "
                select i.id,
                i.token,
                i.silent_up,
                i.skin_file_type,
                case i.type when 1 then 1  else 0 end as is_skin,
                i.visible_version_int,
                min(p.min_version_int) as pmin_version_int,
                p.version_code
                from input_skinthemes as i
                left join input_skins_package AS p on i.id = p.skin_id
                where i.status=100 
                and ({$forceUpdateCond})
                and {$exceptCond}
                group by i.id;
            ";
            $ret = $this->getDB()->query($forceUpdateSql);
            foreach($ret as $retV) {
                if(1 != $retV['is_skin']) {
                    continue;
                }
                // 此为可见不可用判断
                if($retV['visible_version_int'] <= $versionName && $versionName < $retV['pmin_version_int']) {
                    // 删除不必要的字段 保证数据的一致性
                    unset($retV['visible_version_int']);
                    unset($retV['pmin_version_int']);
                    $retV['force_update_client'] = 1;
                    $forceUpdateResult[$retV['id'] . $retV['is_skin']] = $retV;
                }
            }
        }

        /**
         * 获取所有可升级可见皮肤包
         */
        if ($sCond)
        {
            $sSql = "select distinct
                i.id,
                i.token,
                i.silent_up,
                i.skin_file_type,
                case i.type when 1 then 1  else 0 end as is_skin,
                p.min_version_int,
                p.max_version_int,
                p.version_code
                from input_skinthemes as i
                left join input_skins_package AS p
                ON i.id = p.skin_id
            where i.status=100 
            and i.visible_version_int <= '{$versionName}' and '{$versionName}' <= i.max_visible_version_int 
            and ({$sCond}) and $exceptCond";
        }

        $ret = $sSql ? $this->getDB()->query($sSql) : array();

        //去重
        $result = array();
        if (!empty($ret) && is_array($ret)) {
            foreach ($ret as $retK => $retV) {
                if (isset($map[$retV['token']]) && !empty($map[$retV['token']])) {
                    $retV['name'] = $map[$retV['token']]['name'];
                    $retV['md5'] = $map[$retV['token']]['md5'];
                }

                if(1 == $retV['is_skin']) {
                    // 客户端在此皮肤包中不可用
                    if($versionName < $retV['min_version_int'] || $versionName > $retV['max_version_int']) {
                        continue;
                    }
                }
                $retV['force_update_client'] = 0;
                unset($retV['min_version_int']);
                unset($retV['max_version_int']);
                // 将多条数据中version code最大的数据保留, 一个皮肤只保留了一条数据
                if (array_key_exists($retV['id'] . $retV['is_skin'], $result)) {
                    if ($retV['version_code'] > $result[$retV['id'] . $retV['is_skin']]['version_code']) {
                        $result[$retV['id'] . $retV['is_skin']] = $retV;
                    }
                } else {
                    $result[$retV['id'] . $retV['is_skin']] = $retV;
                }
            }
                }
        // 将数据合并
        foreach($forceUpdateResult as $key => $val) {
            if(!array_key_exists($key, $result)) {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * @desc 返回version name的前两位，去点号后的正整数，如in:5-2-0-11 out:52
     * @param string $ver_name
     * @return int $ver_name_int
     */
    private function fetchVersionNameFirstTwo($ver_name){
        $ver_name = str_ireplace('-', '.', trim($ver_name));
        $ver_name_int = 0;
        if (mb_stripos($ver_name, '.') !== false) {
            $ver_name_int = mb_substr($ver_name, 0, mb_stripos($ver_name, '.', mb_stripos($ver_name, '.') + 1));
            $ver_name_int = abs(intval(str_ireplace('.', '', $ver_name_int)));
        }
        return $ver_name_int;
    }

    /**
     * @desc 猜你喜欢主程
     * @param int $sf 分页起始记录
     * @param int $num 分页显示每页的条数，最多200条，默认12条
     * @param string $cuid 明文的cuid
     * @param string $plt 平台号
     * @param int $screen_w 屏幕宽
     * @param int $screen_h 屏幕高
     * @param string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * @param string $dfrom 猜你喜欢运营位进入为market，搜索没结果进来的是search
     * @return array
     */
    private function guessYouLike($sf = 0, $num = self::GENERAL_PAGE_SIZE, $cuid = '', $plt = 'a1', $screen_w = 640, $screen_h = 320, $ver_name = '5.4.0.0', $dfrom = 'market', $foreign_access = false){

        $themepage = array();
        if (empty($cuid)) {
            return $themepage;
        }
        $sf = abs(intval($sf));
        $num = abs(intval($num));
        if ($num < 1) {
            $num = self::GENERAL_PAGE_SIZE;
        }
        elseif ($num > self::MAX_PAGE_SIZE){
            $num = self::MAX_PAGE_SIZE;
        }

        //全部推荐结果列表（限制12个以内）
        $recolist_final = array();

        //推荐去重数组
        $theme_set = array();

        //基于主题标签的推荐
        $query = array(
            "user_id" => $cuid,
            "prod_id" => 802,
            "cate_id" => 80201,
            "mod_id" => 1,
            "api_id" => 100,
            "indexbegin" => 0,
            "length" => 50,
            "data"=>array(
                "timethreshold" => strval(mktime(0,0,0,date('m')-12,date('d'),date('Y'))),
            ),
        );
        $nshead = array(
            'log_id' => rand(),
            'provider' => 'related',
            'body_len' => 0,
        );
        $retdata_tag = array();
        $retdata_tag = ral('rss_recosys', 'nshead_mcpack', $query, null, $nshead);

        //从下载历史中获取分类信息
        $history = array();
        if ($retdata_tag['userhistory'] !== null){
            $history = $retdata_tag['userhistory'];
        }

        $his_cate = array();
        $his_cate_cnt = array();
        $his_cate_num = array();
        if (is_array($history) && count($history) > 0) {
            foreach($history as $theme_id => $info_all){
                $theme_info_all = json_decode($info_all['info'], true);
                $theme_cate = $theme_info_all['Properties']['Category'][0];
                $his_cate['"'.$theme_cate.'"'] = true;
                $his_cate_cnt[$theme_cate] = 0;
                if(isset($his_cate_num[$theme_cate])){
                    $his_cate_num[$theme_cate]++;
                }
                else{
                    $his_cate_num[$theme_cate] = 1;
                }
            }
        }

        $except_cats_sql = '';
        if ($foreign_access && strtolower(mb_substr($plt, 0, 1)) == 'i' && is_array($this->except_cats) && count($this->except_cats) > 0) {
            $tmp = '';
            foreach($this->except_cats as $val) {
                $tmp .= ' AND c.categories not regexp "[[:<:]]s'.$val.'[[:>:]]" AND c.categories not regexp "[[:<:]]t'.$val.'[[:>:]]" ';
            }
            $except_cats_sql = $tmp;
        }
        //读取下载历史的cate作为推荐的筛选条件
        $cate_filter_theme = "";
        if (is_array($his_cate) && count($his_cate) > 0) {

            $cate_filter_theme = " AND (";
            $cat_id_arr = array_keys($his_cate);
            $tmp = array();
            foreach($cat_id_arr as $cat_val) {
                $tmp[] = ' c.categories regexp "[[:<:]]s'.$cat_val.'[[:>:]]" or c.categories regexp "[[:<:]]t'.$cat_val.'[[:>:]]" ';
            }
            $cate_filter_theme .= implode(' or ',$tmp);
            $cate_filter_theme = ") ";

            $cate_filter_theme .= $except_cats_sql;
        }

        //从推荐列表中读取id作为数据库筛选条件
        $recolist_tag = array();
        if ($retdata_tag['recolist'] !== null){
            $recolist_tag = $retdata_tag['recolist'];
        }

        $id_array = array();
        if (is_array($recolist_tag) && count($recolist_tag) > 0) {
            foreach ($recolist_tag as $theme){
                $id_array[] = $theme['docid'];
            }
        }

        if (is_array($id_array) && count($id_array) > 0) {

            $id_array_str = join(",", $id_array);
            $id_filter_theme = " AND input_skinthemes.id IN (".$id_array_str .")";

            $theme_recommend_sql = 'SELECT DISTINCT input_skinthemes.id,author,title,download_number,skin_theme_ver AS ver,file_size,category,token,content_text,remarks,case input_skinthemes.type when 1 then 1  else 0 end as is_skin,recommend,online_time,'
                .'pic_1_300,pic_1_280,pic_1_240,pic_1_200,pic_1_180,pic_1_150,pic_2_300,pic_2_280,pic_2_240,pic_2_200,pic_2_180,pic_2_150,pic_3_300,pic_3_280,pic_3_240,pic_3_200,pic_3_180,pic_3_150,'
                .'pic_1_0,pic_2_0,pic_3_0,"" AS iphone_pic,abilities,ad_type,tj_video_thumb,tj_gif_thumb,tj_video,tj_voice,tj_gif,download_days,c.categories,c.categories_name,recommend_level FROM input_skinthemes, input_skin_theme_category c where c.skin_theme_id = input_skinthemes.id AND c.type="theme" AND ( status=100 '.$cate_filter_theme.' '.$id_filter_theme;

            //操作系统筛选
            if (strtolower(substr($plt, 0, 1)) == 'i') {
                $theme_recommend_sql .= ' AND ( os in ("all","com") OR os = "ios") ';
            }
            else{
                $theme_recommend_sql .= ' AND ( os in ("com","all","android")) ';
            }

            //版本、推送平台、排除平台号筛选
            $theme_recommend_sql .= ' AND skin_theme_ver = 2 ';//原接口推荐和排行不采用老主题数据
            $plt = addslashes($plt);
            $theme_recommend_sql .= ' AND (platform_code = "all" OR FIND_IN_SET("'.$plt.'", REPLACE(platform_code, "|", ",")) > 0) ';
            $theme_recommend_sql .= ' AND (except_platform_code = "empty" OR FIND_IN_SET("'.$plt.'", REPLACE(except_platform_code, "|", ",")) = 0) ';
            $theme_recommend_sql .= ' ) ';

            //排序条件
            $sort =  "ORDER BY INSTR(',".$id_array_str.",'"." , CONCAT(',',id,','))";

            //查询结果
            $sql_result = $this->getDB()->queryf($theme_recommend_sql.' '.$sort);

            //进行cate数量的筛选（目前策略为每个类别最多推荐条数：该类别下载数量*3，总数限制为12套）
            $theme_cnt_tag = 0;
            $recolist_tag_final = array();

            if (is_array($sql_result) && count($sql_result) > 0) {
                foreach ($sql_result as $theme) {
                    if($theme_cnt_tag > self::MAX_REC_CNT_TAG){
                        break;
                    }
                    $theme_id = $theme['id'];
                    $theme_cate = $theme['category'];
                    //皮肤类别筛选
                    if(isset($his_cate_cnt[$theme_cate]) && $his_cate_cnt[$theme_cate] < 3*$his_cate_num[$theme_cate]){
                        $theme['method'] = 'tag';
                        $recolist_tag_final[] = $theme;
                        $his_cate_cnt[$theme_cate]++;
                        $theme_set[$theme_id] = true;
                        $theme_cnt_tag++;
                    }
                }
            }
            //推荐系统结果融合
            $recolist_final = $recolist_tag_final;
        }

        /** 推荐系统无结果，则返回mup性别进行推荐（仅限安卓有mup数据的用户） */
        if (is_array($recolist_final) && count($recolist_final) > 0) {

                $theme_basic_sql = 'SELECT DISTINCT input_skinthemes.id,author,title,download_number,skin_theme_ver AS ver,file_size,category,token,sc.category_name,content_text,remarks,case i.type when 1 then 1  else 0 end as is_skin,recommend,online_time,'
                .'pic_1_300,pic_1_280,pic_1_240,pic_1_200,pic_1_180,pic_1_150,pic_2_300,pic_2_280,pic_2_240,pic_2_200,pic_2_180,pic_2_150,pic_3_300,pic_3_280,pic_3_240,pic_3_200,pic_3_180,pic_3_150,'
                .'pic_1_0,pic_2_0,pic_3_0,"" AS iphone_pic,abilities,ad_type,tj_video_thumb,tj_gif_thumb,tj_video,tj_voice,tj_gif,download_days,c.categories,c.categories_name,recommend_level from input_skinthemes,input_skin_theme_categories sc, input_skin_theme_category c where input_skinthemes.category=sc.category_id AND c.skin_theme_id = input_skinthemes.id AND c.type="theme" and ( status=100 '.$cate_filter_theme;

            //排序规则，同一个性别下的推荐主题按上线时间降序排序
            $sort = ' ORDER BY online_time DESC';

            //根据性别进行个性化推荐
            $user_info = $this->getUserInfo($cuid, 'baiduinput');

            if(isset($user_info["attr"]["gender"])){
                $gender = $user_info["attr"]["gender"];
                if($gender == "男"){
                    //男性推荐主题
                    $male_filter_theme = " AND REMARKS = 'male'";
                    $theme_male_sql = $theme_basic_sql.' '.$male_filter_theme.' ) ';
                    $sql_male_result = $this->getDB()->queryf($theme_male_sql.' '.$sort.' LIMIT %n', self::MAX_REC_CNT_MUP);
                    if (is_array($sql_male_result) && count($sql_male_result) > 0) {
                        foreach($sql_male_result as $theme){
                            $theme['method'] = 'mup';
                            $recolist_final[] = $theme;
                        }
                    }
                }
                if($gender == "女"){
                    //女性推荐主题
                    $female_filter_theme = " AND REMARKS = 'female'";
                    $theme_female_sql = $theme_basic_sql.' '.$female_filter_theme.' ) ';
                    $sql_female_result = $this->getDB()->queryf($theme_female_sql.' '.$sort.' LIMIT %n', self::MAX_REC_CNT_MUP);

                    if (is_array($sql_female_result) && count($sql_female_result) > 0) {

                        foreach($sql_female_result as $theme){
                            $theme['method'] = 'mup';
                            $recolist_final[] = $theme;
                        }
                    }
                }
            }
        }

        //最终推荐信息分页、展示
        $recommend_theme_list = array_slice($recolist_final, $sf, $num);

        $themepage=array();
        $preimgMap = array(
            300 => 300,
            280 => 160,
            200 => 150,
            150 => 100,
        );
        if (is_array($recommend_theme_list) && count($recommend_theme_list) > 0) {
            $objSkinThemeModel = IoCload("models\\SkinThemeModel");
            $activitySkinToken = $objSkinThemeModel->getActivitySkinToken($ver_name);
            $intClientVerTwo = Util::getVersionIntValueTwo($ver_name);
            $os = Util::getOS($plt);
            foreach ($recommend_theme_list as $theme_info){
                $onetheme=array();
                $onetheme['id'] = $theme_info['id'];
                $onetheme['author'] = $theme_info['author'];
                $onetheme['name'] = $theme_info['title'];
                $onetheme['ver'] = $theme_info['ver'];
                $onetheme['down'] = $theme_info['download_number'];
                $onetheme['url'] = $this->domain_v5.'v5/skin/items/t'.$theme_info['id'].'/file?reserve=1&cate=rec&mtd='.$theme_info['method'].'&dfrom='.$dfrom;
                $onetheme['size'] = (floor((intval($theme_info['file_size'])*10/1024))/10).'K';
                $onetheme['cateid'] = '';
                $onetheme['token'] = $theme_info['token'];
                $onetheme['cate'] = '';

                //Edit by fanwenli on 2015-11-10. Skin or theme has many skin's category and theme's category.
                $onetheme['skin_cate_id'] = '';
                $onetheme['skin_cate_name'] = '';
                $onetheme['theme_cate_id'] = '';
                $onetheme['theme_cate_name'] = '';

                $category_arr = $this->getCategoryFunction($theme_info['categories'],$theme_info['categories_name']);
                if(!empty($category_arr['skin']['id'])) {
                    $onetheme['skin_cate_id'] = implode(',',$category_arr['skin']['id']);
                    $onetheme['skin_cate_name'] = implode(',',$category_arr['skin']['name']);
                }

                if(!empty($category_arr['theme']['id'])) {
                    $onetheme['theme_cate_id'] = implode(',',$category_arr['theme']['id']);
                    $onetheme['theme_cate_name'] = implode(',',$category_arr['theme']['name']);
                }

                $onetheme['desc'] = "";
                $onetheme['remarks'] = "";
                $onetheme['is_skin'] = 0;
                $onetheme['imghd'] = str_ireplace($this->pre_path, '', $theme_info['pic_1_0']);

                //特技皮肤素材
                $tj_pre = $this->domain_v4;

                //Edit by fanwenli on 2015-11-09. Judge url if url is http://
                $onetheme['tj_video'] = $this->regExpUrl($theme_info['tj_video'], $tj_pre);
                $onetheme['tj_voice'] = $this->regExpUrl($theme_info['tj_voice'], $tj_pre);
                $onetheme['tj_gif'] = $this->regExpUrl($theme_info['tj_gif'], $tj_pre);

                //Edit by fanwenli on 2015-11-09. Judge time by days
                $onetheme['display_types'] = $this->download_custom($theme_info['download_number'],$theme_info['online_time'],$theme_info['download_days']);

                //兼容ios7.5 icon展示
                if ('ios' == $os && 75000 == $intClientVerTwo) {
                    $onetheme['abilities'] = in_array($theme_info['token'], $activitySkinToken) ? 'Activity' : $theme_info['abilities'];
                } else {
                    $onetheme['abilities'] = $theme_info['abilities'];
                    $onetheme['is_activity'] = in_array($theme_info['token'], $activitySkinToken) ? 1 : 0;
                }
                $onetheme['promote_type'] = $theme_info['ad_type'];
                $onetheme['tj_video_thumb'] = $theme_info['tj_video_thumb'] ? ($tj_pre . $theme_info['tj_video_thumb']) : '';
                $onetheme['tj_gif_thumb'] = $theme_info['tj_gif_thumb'] ? ($tj_pre . $theme_info['tj_gif_thumb']) : '';

                foreach ($preimgMap as $real => $size) {
                    if (300 == $size) {
                        $onetheme['imgt_'.$size] = str_ireplace($this->pre_path, '', $theme_info['pic_1_0']) . '@q_70';
                    } else {
                        $onetheme['imgt_'.$size] = str_ireplace($this->pre_path, '', $theme_info['pic_1_'.$real]);
                    }
                    $onetheme['imgs_'.$size] = '';
                    $onetheme['imgshd_'.$size] = '';
                    for ($k = 1; $k < 4; $k++){
                        if (isset($theme_info['pic_'.$k.'_'.$real]) && mb_strlen(trim($theme_info['pic_'.$k.'_'.$real])) > 0) {
                            $onetheme['imgs_'.$size] .= ','.str_ireplace($this->pre_path, '', trim($theme_info['pic_'.$k.'_'.$real]));
                        }
                        if (isset($theme_info['pic_'.$k.'_0']) && mb_strlen(trim($theme_info['pic_'.$k.'_0'])) > 0) {
                            $onetheme['imgshd_'.$size] .= ','.str_ireplace($this->pre_path, '', trim($theme_info['pic_'.$k.'_0']));
                        }
                    }
                    $onetheme['imgs_'.$size] = ltrim($onetheme['imgs_'.$size], ',');
                    $onetheme['imgshd_'.$size] = ltrim($onetheme['imgshd_'.$size], ',');
                }

                //Edit by fanwenli on 2016-01-08. Adding column of recommend_level
                $onetheme['recommend_level'] = $theme_info['recommend_level'];

                array_push($themepage, $onetheme);
            }
        }

        return $themepage;
    }

    /**
     * @desc 发送给推荐系统点击请求
     * @param string $cuid
     * @param int $id
     */
    private function recommendCheck($cuid, $id){
        //暂时不用 by鹏程
        //      $srvconfarr = array(
        //          "recotype" => "item_based",
        //          "featuretype" => "LDA",
        //      );
        //      $srvconf = json_encode($srvconfarr);
        //      $query = array(
        //          "user_id" => $cuid,
        //          "prod_id" => 802,
        //          "cate_id" => 89902,
        //          "mod_id" => 1,
        //          "api_id" => 102,
        //          "indexbegin" => 0,
        //          "length" => 10,
        //          "data" => array(
        //              "item_id" => strval($id),
        //              "clickCnt" => 1,
        //              "limit" => 10,
        //              "srvconf" => $srvconf,
        //          ),
        //      );

        //      $nshead = array(
        //          'log_id' => rand(),
        //          'provider' => 'related',
        //          'body_len' => 0,
        //      );
        //      $retdata_cf = ral('rss_recosys', 'nshead_mcpack', $query, null, $nshead);

        /*
         * 发送给推荐系统点击请求
        */
        $srvconfarr = array(
            "recotype" => "content_based",
            "featuretype" => "label",
        );
        $srvconf = json_encode($srvconfarr);
        $query = array(
            "user_id" => $cuid,
            "prod_id" => 802,
            "cate_id" => 80201,
            "mod_id" => 1,
            "api_id" => 102,
            "indexbegin" => 0,
            "length" => 10,
            "data" => array(
                "item_id" => strval($id),
                "clickCnt" => 1,
                "limit" => 10,
                "srvconf" => $srvconf,
            ),
        );

        $nshead = array(
            'log_id' => rand(),
            'provider' => 'related',
            'body_len' => 0,
        );
        $retdata_tag = ral('rss_recosys', 'nshead_mcpack', $query, null, $nshead);
    }

    /**
     * @desc 获取用户mup
     * @param string $cuid
     * @param string $product
     * @return multitype:|mixed
     */
    private function getUserInfo($cuid, $product) {
        switch ($product) {
            case 'baidubrowser':
                $reqObj = array(
                    'command' => 'get_profile',
                    'params' => array(
                        'cuid' => $cuid,
                        'product' => 'baidubrowser',
                    ),
                );
                break;
            case 'baiduboxapp':
                $reqObj = array(
                    'command' => 'get_profile',
                    'params' => array(
                        'cuid' => $cuid,
                        'product' => 'baiduboxapp',
                    ),
                );
                break;
            case 'baiduinput':
                $reqObj = array(
                    'command' => 'get_profile',
                    'params' => array(
                        'cuid' => $cuid,
                        'product' => 'baiduinput',
                    ),
                );
                break;
            default:
                return array();
        }
        $userQueryInfoStr = ral('uprofile', '', json_encode($reqObj), 1);
        $userQueryInfo = json_decode($userQueryInfoStr, true);
        if ( ! $userQueryInfo || $userQueryInfo['errno'] != 0) {
            return array();
        }
        return $userQueryInfo['data'];
    }

    /**
     * @desc 记录内部缓存到sorted set中，用于批量清除
     * @param string $internal_cache_key 内部缓存的key
     * @param string $from_method 调用该方法的上级函数
     * @return multitype:|mixed
     */
    private function internalCacheManage($internal_cache_key = '', $from_method = ''){
        if($from_method !== "" && $from_method !== null){
            $set_key = "CacheManage::Skintheme::$from_method";
            $members = array();
            $member = array();
            $member['member'] = $internal_cache_key;
            $member['score'] = time();
            $members[] = $member;
            $zadd_status = null;
            $this->storage->zadd($set_key, $members, $zadd_status);
        }
    }

    /**
     * 查看日期间隔
     * @param  int $begin_time 开始日期时间戳
     * @param  int $end_time 结束日期时间戳
     * @return array
     */
    private function datediff($begin_time,$end_time)
    {
        if ( $begin_time < $end_time ) {
            $starttime = $begin_time;
            $endtime = $end_time;
        } else {
            $starttime = $end_time;
            $endtime = $begin_time;
        }

        $timediff = $endtime - $starttime;
        $days = intval( $timediff / 86400 );
        $remain = $timediff % 86400;
        $hours = intval( $remain / 3600 );
        $remain = $remain % 3600;
        $mins = intval( $remain / 60 );
        $secs = $remain % 60;
        $res = array( "day" => $days, "hour" => $hours, "min" => $mins, "sec" => $secs );

        return $res;
    }

    /**
     * 定制下载显示内容
     * @param  int $download_count 下载次数
     * @param  datetime $online_time 上线日期
     * @return string
     */
    private function download_custom($download_count,$online_time,$day = 0)
    {
        $out = '';

        $time = $this->datediff(strtotime($online_time), time());
        if($download_count < 1000 && $time['day'] < (int)$day) {
            $out = 'new';
        }

        return $out;
    }

    /**
     * 获取皮肤或主题对应的皮肤分类和主题分类
     * @param  string $category_id_str 皮肤或主题ID字符串
     * @param  string $category_name_str 皮肤或主题标题字符串
     * @return array
     */
    private function getCategoryFunction($category_id_str = '',$category_name_str = '')
    {
        $out = array('skin' => array(), 'theme' => array());

        $category_id_arr = explode(',',$category_id_str);
        $category_name_arr = explode(',',$category_name_str);
        if(is_array($category_id_arr) && !empty($category_id_arr)) {
            foreach($category_id_arr as $key_cate => $val_cate) {
                $cate_type = substr($val_cate,0,1);
                $cate_name = substr($val_cate,1);
                $category_name_arr[$key_cate] = substr($category_name_arr[$key_cate],1);

                //判断是皮肤分类还是主题分类
                switch($cate_type) {
                    case 's':
                        $out['skin']['id'][] = $cate_name;
                        $out['skin']['name'][] = $category_name_arr[$key_cate];
                        break;
                    case 't':
                        $out['theme']['id'][] = $cate_name;
                        $out['theme']['name'][] = $category_name_arr[$key_cate];
                        break;
                }
            }
        }

        return $out;
    }

    /**
     * 正则匹配获取url
     * @param string $url 原始url数据
     * @param string $http_header 需要添加的http头
     * @param string $reg_http_header 正则匹配http头
     * @return string
     */
    private function regExpUrl($url = '', $http_header = '', $reg_http_header = 'http\:\/\/')
    {
        $out = '';

        if(preg_match('/^'.$reg_http_header.'/i',$url)) {
            $out = $url;
        }
        else {
            $out = $url ? ($http_header . $url) : '';
        }

        return $out;
    }


    /**
     * @author fanwenli on 2016-09-07
     * @desc 热区下发接口
     * @route({"POST", "/hot_area"})
     * @param({"token", "$._POST.token"}) string 皮肤对应token
     * @param({"skin_version", "$._POST.skin_version"}) int 皮肤版本号
     * @param({"hot_area_version", "$._POST.hot_area_version"}) int 热区版本号
     * @param({"screen_size", "$._POST.screen_size"}) float 皮肤尺寸
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * {
            "list": [
                {
                    "id": "275",
                    "token": "7dd47124cdcfaa12982642ca146dac30",
                    "version_code": "1",
                    "url": "http://10.58.19.57:8890/v5/skin/items/s266/file?reserve=1",
                    "silent_up": "1",
                    "is_skin": "1"
                }
            ]
        }
     */
    public function hotArea($token, $skin_version = 0, $hot_area_version = 0, $screen_size = 0) {
        $out = array();

        //get content from cache
        //$skinModel = IoCload('models\\SkinthemeModel');
        //$resContent = $skinModel->getSkinHotAreaCache($token);
        
        //Edit by fanwenli on 2019-08-15, get all content and set in redis
        /*$internal_cache_key = SkinthemeModel::INTERNAL_CACHE_KEY_PREFIX .'hot_area_';
        $resContent = GFunc::cacheZget($internal_cache_key);
        if ($resContent == false) {
            //get content by skin
            $resModel = IoCload('models\\ResServiceModel');
            //Edit by fanwenli on 2019-08-15, get all content and set in redis
            //$strSearch = sprintf('{"content.token":"%s"}', $token);
            //$resContent = $resModel->getAllContent('skin-hot-space', $strSearch);
            $arrHeader = array(
                'pathinfo' => '/res/json/input/r/online/skin-hot-space/',
                'querystring'=> 'search=&onlycontent=1&searchbyori=1',
            );
            
            $resContent = ral("res_service", "get", null, rand(), $arrHeader);
            $resContent = is_array($resContent) ? $resContent : json_decode($resContent, true);
            
            //set content to cache
            //$skinModel->setSkinHotAreaCache($token, $resContent);
            GFunc::cacheZset($internal_cache_key, $resContent, SkinthemeModel::PERSIST_EXPIRED);
        }

        if (is_array($resContent) && !empty($resContent)) {
            //get res all content
            $resAll = array();
            foreach ($resContent as $key => $val) {
                //Edit by fanwenli on 2019-08-15, set array when token was set
                if(isset($val['token']) && trim($val['token']) == trim($token)) {
                    $resAll[] = $val;
                }
            }


            //判断客户端需要屏幕尺寸对应的屏幕数据
            $screen_size = floatval($screen_size);
            if ($screen_size <= 4.25) {
                $data_name = 'data_4';
            } elseif ($screen_size > 4.25 && $screen_size <= 4.6) {
                $data_name = 'data_4_5';
            } elseif ($screen_size > 4.6 && $screen_size <= 4.85) {
                $data_name = 'data_4_7';
            } elseif ($screen_size > 4.85 && $screen_size <= 5.25) {
                $data_name = 'data_5';
            } else {
                $data_name = 'data_5_5';
            }

            //screen width
            $screen_r = 0;
            if ($_GET['ua'] !== null) {
                $tmp_arr = explode('_', trim($_GET['ua']));
                $screen_w = intval($tmp_arr[1]);
                //屏幕宽度选取，中间值以下都取下限
                if ($screen_w <= 320 || ($screen_w > 320 && $screen_w <= $this->middleValue(320, 480))) {
                    $screen_r = 320;
                } elseif ($screen_w == 480 || ($screen_w < 480 && $screen_w > $this->middleValue(320, 480)) || ($screen_w > 480 && $screen_w <= $this->middleValue(480, 720))) {
                    $screen_r = 480;
                } elseif ($screen_w == 720 || ($screen_w < 720 && $screen_w > $this->middleValue(480, 720)) || ($screen_w > 720 && $screen_w <= $this->middleValue(720, 1080))) {
                    $screen_r = 720;
                } else {
                    $screen_r = 1080;
                }
            }

            //filter
            $filterModel = IoCload('models\\FilterModel');
            $arrFilterRes = $filterModel->getFilterByArray($resAll);
            if (isset($arrFilterRes) && is_array($arrFilterRes) && !empty($arrFilterRes)) {
                foreach ($arrFilterRes as $val) {
                    //服务端皮肤版本号
                    $skin_version_server = Util::getVersionIntValue($val['skin_version']);
                    //服务端热区版本号
                    $hot_area_version_server = $val['hot_area_version'];

                    //屏幕数据
                    $touch_area_url = '';
                    if (isset($val[$data_name]['data_' . $screen_r])) {
                        $touch_area_url = $val[$data_name]['data_' . $screen_r];
                    }
                    
                    if ($touch_area_url != '') {
                        //皮肤版本与客户端下发的一致且热区版本服务端比客户端来的大，下发数据并且以后还会更新
                        if ($skin_version_server == $skin_version && $hot_area_version_server > $hot_area_version) {
                            $out = array('touch_area_url' => $touch_area_url, 'hot_area_version' => $hot_area_version_server, "wont_update" => 0);
                            break;
                        }

                        //皮肤版本比客户端下发的大，下发当前热区版本号并提示不在更新
                        if ($skin_version_server > $skin_version) {
                            $out = array('hot_area_version' => $hot_area_version_server, "wont_update" => 1);
                            break;
                        }
                    }
                }
            }
        }*/

        return $out;
    }

    /**
     * 整数中间值选取
     * @param int $beg 整数开始数值
     * @param int $end 整数结束数值
     * @return int
     */
    private function middleValue($beg = 0, $end = 0)
    {
        $out = $beg + round(abs($end - $beg)/2);

        return $out;
    }


    /**
     * v4 皮肤市场接口迁移
     *
     * @route({"GET", "/v4skinmarket"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @param({"screen_h", "$._GET.screen_h"}) int $screen_h 屏幕高，不需要客户端传，从加密参数中获取
     * @param({"version", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"plt", "$._GET.plt"})
     * @param({"cate", "$._GET.cate"})
     * @param({"resolution", "$._GET.resolution"})
     * @param({"keywords", "$._GET.keywords"})
     * @param({"layout", "$._GET.layout"})
     * @param({"page", "$._GET.page"})
     * @param({"size", "$._GET.size"})
     * @param({"pagesize", "$._GET.pagesize"})
     * @return({"body"})
     * @return $array
     */
    public function vsmarket($platform='a1',$screen_w=640,$screen_h=320,$version="5.2.0.0",
        $plt='',$resolution='',$layout='',$cate='recommend',$size="",$page=1,$pagesize=12,$keywords="")
    {
        $args = func_get_args();
        $cacheKey = __CLASS__ . __METHOD__ . implode('_', $args);
        $skinpage = GFunc::cacheGet($cacheKey);
        if (false === $skinpage) {
            $preimgMap=array(
                300=>300,
                280=>160,
                200=>150,
                150=>100,
            );
            if(!isset($preimgMap[$size])){
                $size=150;
            }

            $pagesize <= 0 && $pagesize = 12;

            $orderby='add_time';
            $suffix='.bds';

            //推荐
            $real_cate=$cate;
            if($cate==='recommend')
            {
                $orderby='recommend';
                $real_cate=null;
            }

            //最新
            if($cate==='last')
            {
                $orderby='add_time';
                $real_cate=null;
            }

            //下载
            if ($cate==='download')
            {
                $orderby='download_number';
                $real_cate=null;
            }
            $skinModel = IoCload("models\\SkinthemeModel");
            $currentpage = $skinModel->v4find(1, $layout, 100,$real_cate,'1',$resolution,null,$keywords,$orderby,'desc',$pagesize,2,$plt, $page, strtolower($platform), $version, $screen_w, $screen_h);

            $cates = $skinModel->loadCategoriesList(1);
            $skinpage = array();
            $skinpage['cateinfo']=array();

            foreach ($cates as $key => $value)
            {
                array_push($skinpage['cateinfo'],array($key=>$value));
            }

            $skinpage['pageinfo']['total']      =$currentpage['total_page'];
            $skinpage['pageinfo']['current']    =$currentpage['current_page'];
            $skinpage['pageinfo']['count']      =$currentpage['total_record'];


            $skinpage['skininfo']['domain'] = $this->domain_v4;
            $skinpage['skininfo']['imgpre'] ='upload/imags/';

            $skinpage['skininfo']['skinlist']=array();
            $c = count($currentpage['data']);
            for($i=0;$i<$c;$i++){
                $skin=$currentpage['data'][$i];
                $oneskin=array();
                $oneskin['skin']['sid']=$skin['id'];
                $oneskin['skin']['author']=$skin['author'];
                $oneskin['skin']['name']=$skin['title'];
                $oneskin['skin']['uptime']=$skin['add_time'];
                $oneskin['skin']['down']=$skin['download_number'];
                $oneskin['skin']['score']=(intval($skin['grade']*100)/100);

                $primg=$skin['pic_1_'.$preimgMap[$size]];

                $oneskin['skin']['imgt']=str_replace('upload/imags/', '',$primg);
                $oneskin['skin']['imgtsize']=10000;

                $primghd=$skin['pic_1_0'];

                if(isset($skin['iphone_pic']) && strlen(trim($skin['iphone_pic']))>5){
                    $primghd=trim($skin['iphone_pic']);
                }
                $oneskin['skin']['imghd']=str_replace('upload/imags/', '',$primghd);
                $oneskin['skin']['imghdsize']=10000;


                $oneskin['skin']['imgs']=array();
                $oneskin['skin']['imgshd']=array();
                for($k=1;$k<=3;$k++){
                    $name='pic_'.$k.'_'.$preimgMap[$size];
                    $namehd='pic_'.$k.'_0';
                    if($skin[$name]!==null && trim($skin[$name])!==''){
                        array_push($oneskin['skin']['imgs'], array('p'=>str_replace('upload/imags/', '', $skin[$name])));
                        array_push($oneskin['skin']['imgshd'], array('p'=>str_replace('upload/imags/', '', $skin[$namehd])));
                    }
                }

                $oneskin['skin']['url']='v5/items/s'.$skin['id']."/file";
                $oneskin['skin']['size']=(floor((intval($skin['file_size'])*10/1024))/10).'K';
                $oneskin['skin']['cateid']=$skin['category'];
                $oneskin['skin']['token']=$skin['token'];
                $oneskin['skin']['cate']=$cates[$skin['category']];
                $oneskin['skin']['desc']=str_replace('\n',"\n",$skin['content_text']);
                $oneskin['skin']['remarks']=str_replace('\n',"\n",$skin['remarks']);
                array_push($skinpage['skininfo']['skinlist'], $oneskin['skin']);
            }

            //搜索的情况下考虑没有结果，给出4个最新
            if(trim($keywords)!=='' && empty($skinpage['skininfo']['skinlist'])){
                $real_cate = null;
                $keywords = null;
                $pagesize = null;
                $page = null;
                $currentpage = $skinModel->v4find(1, $layout, 100,$real_cate,'1',$resolution,null,$keywords,$orderby,'desc',$pagesize,2,$plt, $page, strtolower($platform), $version, $screen_w, $screen_h);
                //取4个最新
                $c = count($currentpage['data']);
                for($i=0;$i<$c;$i++){
                    if(intval($i) > 3){
                        break;
                    }
                    $skin=$currentpage['data'][$i];
                    $oneskin=array();
                    $oneskin['skin']['sid']=$skin['id'];
                    $oneskin['skin']['author']=$skin['author'];
                    $oneskin['skin']['name']=$skin['title'];
                    $oneskin['skin']['uptime']=$skin['add_time'];
                    $oneskin['skin']['down']=$skin['download_number'];
                    $oneskin['skin']['score']=(intval($skin['grade']*100)/100);

                    $primg=$skin['pic_1_'.$preimgMap[$size]];

                    $oneskin['skin']['imgt']=str_replace('upload/imags/', '',$primg);
                    $oneskin['skin']['imgtsize']=10000;

                    $primghd=$skin['pic_1_0'];

                    if(isset($skin['iphone_pic']) && strlen(trim($skin['iphone_pic']))>5){
                        $primghd=trim($skin['iphone_pic']);
                    }
                    $oneskin['skin']['imghd']=str_replace('upload/imags/', '',$primghd);
                    $oneskin['skin']['imghdsize']=10000;


                    $oneskin['skin']['imgs']=array();
                    $oneskin['skin']['imgshd']=array();
                    for($k=1;$k<=3;$k++){
                        $name='pic_'.$k.'_'.$preimgMap[$size];
                        $namehd='pic_'.$k.'_0';
                        if($skin[$name]!==null && trim($skin[$name])!==''){
                            array_push($oneskin['skin']['imgs'], array('p'=>str_replace('upload/imags/', '', $skin[$name])));
                            array_push($oneskin['skin']['imgshd'], array('p'=>str_replace('upload/imags/', '', $skin[$namehd])));
                        }
                    }

                    $oneskin['skin']['url']='v5/skin/items/s'.$skin['id'] . '/file';
                    $oneskin['skin']['size']=(floor((intval($skin['file_size'])*10/1024))/10).'K';
                    $oneskin['skin']['cateid']=$skin['category'];
                    $oneskin['skin']['token']=$skin['token'];
                    $oneskin['skin']['cate']=isset($cates[$skin['category']]) ? $cates[$skin['category']] : '';
                    $oneskin['skin']['desc']=str_replace('\n',"\n",$skin['content_text']);
                    $oneskin['skin']['remarks']=str_replace('\n',"\n",$skin['remarks']);
                    array_push($skinpage['skininfo']['skinlist'], $oneskin['skin']);

                }
            }

            GFunc::cacheSet($cacheKey, $skinpage);
        }
        return $skinpage;
    }

   /**
     * v4 主题市场接口迁移
     *
     * @route({"GET", "/v4thememarketv2"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"version", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cate", "$._GET.cate"})
     * @param({"size", "$._GET.size"})
     * @param({"page", "$._GET.page"})
     * @param({"pagesize", "$._GET.pagesize"})
     * @param({"keywords", "$._GET.keywords"})
     * @return({"body"})
     * @return $array
     */
    public function marketv2Action($platform='i1',$version='5.3.0.0',$cate='recommend',$size="",$page=1,$pagesize=12,$keywords="") {
        $args = func_get_args();
        $cacheKey = __CLASS__ . __METHOD__ . implode('_', $args);
        $themepage = GFunc::cacheGet($cacheKey);
        if (false === $themepage) {
            $os = Util::getPhoneOS($platform);

            //正则平台号
            $regexp_sql = ' and ((os = "all") or (os = "' . $os . '")) and ((platform_code = "all") or ((select "' . strtolower($platform) . '" REGEXP platform_code) = 1)) and ((except_platform_code = "empty") or ((select "' . strtolower($platform) . '" REGEXP except_platform_code) = 0))';

            if($pagesize <= 0)
            {
                $pagesize = 12;
            }

            $keywords = strip_tags($keywords);
            $replacechars = array('%','\'','"','#','\\');
            $keywords = str_replace($replacechars, " ", $keywords);
            if($page<1){
                $page=1;
            }

            $preimgMap=array(
                60=>100,
                80=>150,
                120=>180,
                160=>160,
                300=>300,
            );

            if(!isset($preimgMap[$size])){
                $size=160;
            }


            $themepage=array();
            //$pagesize=12;
            $orderby='id';
            $pri=null;
            $themever=null;
            /*
             * 推荐
             */
            $real_cate=$cate;
            if($cate==='recommend' ){
                $orderby='recommend desc,id';
                $real_cate=null;
                $pri=40;
                /*
                 * 推荐不采用老主题数据
                 */
                $themever=2;
            }

            /*
             * 最新
             */
            if($cate==='last'){
                $orderby='add_time';
                $real_cate=null;
            }

            /*
             * 下载
             */
            if($cate==='download'){
                $orderby='download_number';
                $real_cate=null;
                $themever=2; //下载排行不采用老主题数据
            }

            /*
             * 获取全部主题分类
             */
            $skinModel = IoCload('models\\SkinthemeModel');
            $all_cates = $skinModel->loadCategoriesList(2);

            $condition=' status=100 and type=2 ';
            $condition_args=array();

            //搜索的情况不考虑主题版本
            if($themever!==null && trim($keywords) === ''){
                $condition.=' and skin_theme_ver=? ';
                array_push($condition_args, $themever);
            }


            if($real_cate!==null){

                if(isset($all_cates[$real_cate])){
                    $condition.=' and category=? ';
                    array_push($condition_args, $real_cate);
                }

            }

            //如果是搜索，则不存在推荐优先级条件
            if($pri!==null && trim($keywords) === ''){
                $condition.=' and recommend >=? ';
                array_push($condition_args, $pri);
            }

            if(trim($keywords)!==''){
                $condition.=' and (';
                $likefieldsarr=array('title','author','content_text','remarks','tags');
                $isfirst=true;
                foreach ($likefieldsarr as $likefield){
                    $worldsarr=explode(' ', $keywords);
                    foreach ($worldsarr as $likeword){

                        if($likeword!==''){
                            if($isfirst){
                                $condition.=$likefield.' like ? ';
                            }else{
                                $condition.=' or '.$likefield.' like ? ';
                            }
                            array_push($condition_args, '%'.$likeword.'%');
                            $isfirst=false;
                        }
                    }
                }
                $condition.=')';
            }

            $orderby.=' desc';
            $condition .= $regexp_sql ;

            $themedata = $skinModel->getDataList('input_skinthemes', $condition,$condition_args,$orderby,$pagesize,$page);

            $themepage['cateinfo']=array();
            foreach ($all_cates as $cate_key=>$cate_name){
                array_push($themepage['cateinfo'], array($cate_key=>$cate_name));
            }

            $themepage['pageinfo']['total']     =intval($themedata['total_page']);
            if($themepage['pageinfo']['total']===0){
                $themepage['pageinfo']['total']=1;
            }
            $themepage['pageinfo']['current']   =intval($themedata['current_page']);
            if($themepage['pageinfo']['current']===0){
                $themepage['pageinfo']['current']=1;
            }
            $themepage['pageinfo']['count']     =intval($themedata['total_record']);

            $themepage['themeinfo']['domain']   = $this->domain_v4;
            $themepage['themeinfo']['imgpre']   = 'upload/imags/';

            $themepage['themeinfo']['themelist']=array();
            foreach ($themedata['data'] as $theme){
                $onetheme=array();
                $onetheme['theme']['name']      =$theme['title'];
                $onetheme['theme']['down']      =$theme['download_number'];
                $primg=$theme['pic_1_'.$preimgMap[$size]];
                $onetheme['theme']['imgt']      =str_replace('upload/imags/', '',$primg);
                $onetheme['theme']['imgtsize']  =10000;

                //更多图片
                $onetheme['theme']['imgs']=array();
                $onetheme['theme']['imgshd']=array();
                for($k=1;$k<=3;$k++){
                    $name='pic_'.$k.'_'.$preimgMap[$size];
                    $namehd='pic_'.$k.'_0';
                    if($theme[$name]!==null && trim($theme[$name])!==''){
                        array_push($onetheme['theme']['imgs'], array('p'=>str_replace('upload/imags/', '', $theme[$name])));
                        array_push($onetheme['theme']['imgshd'], array('p'=>str_replace('upload/imags/', '', $theme[$namehd])));
                    }
                }

                $onetheme['theme']['url']       ='v5/skin/items/t' . $theme['id'] . '/file';
                $onetheme['theme']['size']      =(floor((intval($theme['file_size'])*10/1024))/10).'K';
                $onetheme['theme']['cateid']    =$theme['category'];
                $onetheme['theme']['token']     =$theme['token'];
                array_push($themepage['themeinfo']['themelist'], $onetheme['theme']);
            }

            //搜索的情况下考虑没有结果，给出4个最新
            if(trim($keywords)!=='' && empty($themepage['themeinfo']['themelist'])){
                $condition=' status=100 ';
                $condition .= $regexp_sql;
                $condition_args = array();
                $orderby = 'id desc';
                $pagesize = null;
                $page = null;
                $themedata=$skinModel->getDataList('input_skinthemes',$condition,$condition_args,$orderby,$pagesize,$page);
                //取4个最新
                $recommend_cnt = 0;
                foreach ($themedata['data'] as $theme)
                {
                    if(intval($recommend_cnt) > 3)
                    {
                        break;
                    }

                    $onetheme=array();
                    $onetheme['theme']['name']      =$theme['title'];
                    $onetheme['theme']['down']      =$theme['download_number'];
                    $primg=$theme['pic_1_'.$preimgMap[$size]];
                    $onetheme['theme']['imgt']      =str_replace('upload/imags/', '',$primg);
                    $onetheme['theme']['imgtsize']  =10000;

                    //更多图片
                    $onetheme['theme']['imgs']=array();
                    $onetheme['theme']['imgshd']=array();
                    for($k=1;$k<=3;$k++){
                        $name='pic_'.$k.'_'.$preimgMap[$size];
                        $namehd='pic_'.$k.'_0';
                        if($theme[$name]!==null && trim($theme[$name])!==''){
                            array_push($onetheme['theme']['imgs'], array('p'=>str_replace('upload/imags/', '', $theme[$name])));
                            array_push($onetheme['theme']['imgshd'], array('p'=>str_replace('upload/imags/', '', $theme[$namehd])));
                        }
                    }
                    $onetheme['theme']['url']       ='v4/?c=theme&e=d&id='.$theme['id'];
                    $onetheme['theme']['size']      =(floor((intval($theme['file_size'])*10/1024))/10).'K';
                    $onetheme['theme']['cateid']    =$theme['category'];
                    $onetheme['theme']['token']     =$theme['token'];
                    array_push($themepage['themeinfo']['themelist'], $onetheme['theme']);
                    $recommend_cnt ++;
                }
            }

            GFunc::cacheSet($cacheKey, $themepage);
        }

        return $themepage;
    }

    /**
     * v4 主题市场接口迁移
     *
     * @route({"GET", "/v4thememarket"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"version", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"cate", "$._GET.cate"})
     * @param({"size", "$._GET.size"})
     * @param({"page", "$._GET.page"})
     * @param({"pagesize", "$._GET.pagesize"})
     * @param({"keywords", "$._GET.keywords"})
     * @return({"body"})
     * @return $array
     */
    public function marketAction($platform="i1",$version="5.2.0.0",$cate='recommend',$size="",$page=1,$pagesize=12,$keywords="") {
        $args = func_get_args();
        $cacheKey = __CLASS__ . __METHOD__ . implode('_', $args);
        $themepage = GFunc::cacheGet($cacheKey);
        if (false === $themepage) {
            $os = Util::getPhoneOS($platform);

            $regexp_sql = ' and ((os = "all") or (os = "' . $os . '")) and ((platform_code = "all") or ((select "' . strtolower($platform) . '" REGEXP platform_code) = 1)) and ((except_platform_code = "empty") or ((select "' . strtolower($platform) . '" REGEXP except_platform_code) = 0))';

            if($pagesize <= 0)
            {
                $pagesize = 12;
            }

            $keywords=strip_tags($keywords);
            $replacechars=array('%','\'','"','#','\\');
            $keywords= trim(str_replace($replacechars, " ", $keywords));

            if($page<1){
                $page=1;
            }

            $preimgMap=array(
                60=>100,
                80=>150,
                120=>180,
                160=>160,
                300=>300,
            );

            if(!isset($preimgMap[$size])){
                $size=160;
            }

            $themepage=array();
            //$pagesize=12;
            $orderby='add_time';
            $pri=null;
            $themever=null;
            /*
             * 推荐
             */
            $real_cate=$cate;
            if($cate==='recommend'){
                $orderby='recommend desc,id';
                $real_cate=null;
                $pri=40;
                /*
                 * 推荐不采用老主题数据
                 */
                $themever=2;
            }

            /*
             * 最新
             */
            if($cate==='last'){
                $orderby='add_time';
                $real_cate=null;
            }

            /*
             * 下载
             */
            if($cate==='download'){
                $orderby=' online_time desc, download_number ';
                $real_cate=null;
                $themever=2; //下载排行不采用老主题数据
            }

            /*
             * 获取全部主题分类
             */
            $skinModel = IoCload("models\\SkinthemeModel");
            $all_cates=$skinModel->loadCategoriesList(2);
            $condition=' status=100 and type=2 ';
            $condition_args=array();
            //搜索的情况不考虑主题版本
            if($themever!==null && trim($keywords) === ''){
                $condition.=' and skin_theme_ver=? ';
                array_push($condition_args, $themever);
            }

            if($real_cate!==null){

                if(isset($all_cates[$real_cate])){
                    $condition.=' and category=? ';
                    array_push($condition_args, $real_cate);
                }

            }
            if($pri!==null && empty($keywords)){
                $condition.=' and recommend >=? ';
                array_push($condition_args, $pri);
            }

            if(trim($keywords)!==''){
                $orderby = 'recommend desc, online_time';
                $condition.=' and (';
                $likefieldsarr=array('title','tags');
                $isfirst=true;
                foreach ($likefieldsarr as $likefield){
                    $worldsarr=explode(' ', $keywords);
                    foreach ($worldsarr as $likeword){

                        if($likeword!==''){
                            if($isfirst){
                                $condition.=$likefield.' like ? ';
                            }else{
                                $condition.=' or '.$likefield.' like ? ';
                            }
                            array_push($condition_args, '%'.$likeword.'%');
                            $isfirst=false;
                        }
                    }
                }
                $condition.=')';
            }

            $orderby.=' desc';
            $condition .= $regexp_sql;

            $themedata=$skinModel->getDataList('input_skinthemes',$condition,$condition_args,$orderby,$pagesize,$page);

            $themepage['cateinfo']=$all_cates;
            $themepage['pageinfo']['total']     =intval($themedata['total_page']);
            if($themepage['pageinfo']['total']===0){
                $themepage['pageinfo']['total']=1;
            }
            $themepage['pageinfo']['current']   =intval($themedata['current_page']);
            if($themepage['pageinfo']['current']===0){
                $themepage['pageinfo']['current']=1;
            }
            $themepage['pageinfo']['count']     =intval($themedata['total_record']);
            $themepage['themeinfo']['domain']   =$this->domain_v4;
            $themepage['themeinfo']['imgpre']   ='upload/imags/';

            $themepage['themeinfo']['themelist']=array();
            foreach ($themedata['data'] as $theme){
                $onetheme=array();
                $onetheme['theme']['name']      =$theme['title'];
                $onetheme['theme']['author'] = $theme['author'];
                $onetheme['theme']['down']      =$theme['download_number'];
                $primg=$theme['pic_1_'.$preimgMap[$size]];
                $onetheme['theme']['imgt']      =str_replace('upload/imags/', '',$primg);
                $onetheme['theme']['imgtsize']  =10000;
                $onetheme['theme']['url']       ='v5/skin/items/t'.$theme['id'] . '/file';
                $onetheme['theme']['size']      =(floor((intval($theme['file_size'])*10/1024))/10).'K';
                $onetheme['theme']['cateid']    =$theme['category'];
                $onetheme['theme']['token']     =$theme['token'];
                array_push($themepage['themeinfo']['themelist'], $onetheme['theme']);
            }

            GFunc::cacheSet($cacheKey, $themepage);
        }

        return $themepage;
    }

    /**
     * v4 主题市场接口迁移
     *
     * @route({"GET", "/v4tsug"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"version", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * * @param({"keyword", "$._GET.keyword"})
     * @return({"body"})
     * @return $array
     */
    public function tsugAction($platform='a1', $version='5.3.0.0', $keyword=''){
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $suglist = GFunc::cacheGet($key);
        if (false === $suglist) {
            //清除错误的字符
            $keyword = strip_tags($keyword);
            $replacechars = array('%','\'','"','#','\\');
            $keyword= str_replace($replacechars, " ", $keyword);
            $os = Util::getPhoneOS($platform);
            //返回sug列表suglist
            $suglist['suglist'] = array();

            if(trim($keyword)!==''){

                //查询条件
                $condition = '';
                $condition.=' title like ' . '"%'.$keyword.'%"';

                $regexp_sql = ' and status=100 and type=2 and ((os = "all") or (os = "' . $os . '")) and ((platform_code = "all") or ((select "' . strtolower($platform) . '" REGEXP platform_code) = 1)) and ((except_platform_code = "empty") or ((select "' . strtolower($platform) . '" REGEXP except_platform_code) = 0))';
                $condition .= $regexp_sql;
                $sql = "select * from input_skinthemes where " . $condition . ' order by download_number desc';

                //从数据库中读取sug数据
                $skinModel= IoCload("models\\SkinthemeModel");

                $sugdate = $skinModel->query($sql);

                foreach ($sugdate as $sug){

                    //判断是否下发
                    $support_os = isset($sug['os'])? $sug['os'] : 'all';
                    $platform_code = isset($sug['platform_code'])? $sug['platform_code'] : 'all';
                    $except_platform_code = isset($sug['except_platform_code'])? $sug['except_platform_code'] : '';

                    $onesug = array();
                    $onesug['id'] = $sug['id'];
                    $onesug['title'] = $sug['title'];
                    $onesug['download_number'] = $sug['download_number'];
                    array_push($suglist['suglist'], $onesug);
                }
            }

            //如果sug数量小于等于3，满足要求；如果sug数量大于3，则对于排序在3个之后的sug按id倒序排序
            //即保持sug中前三为热门下载主题，之后按主题从新到旧排序

            //假设将排序前三的主题download_number保持不变，将排序在3之后的主题的download_number置为-1
            $sug_cnt = count($suglist['suglist']);
            if($sug_cnt > 3){
                for($i = 0; $i < $sug_cnt; $i++){
                    if($i > 2){
                        $suglist['suglist'][$i]['download_number'] = '-1';
                    }
                }
            }

            //自定义排序：先按照下载量排序，后按照id降序排序；这样可以，保持前三位主题不变，之后由于下载量均为-1，将按主题从新到旧排序
            usort($suglist['suglist'], function($a, $b){
                if ($a['download_number'] == $b['download_number']){
                    if($a['id'] == $b['id']){
                        return 0;
                    }
                    return ($a['id'] > $b['id']) ? -1 : 1;
                }
                return ($a['download_number'] > $b['download_number']) ? -1 : 1;
            });

            //去除多余的辅助排序字段，只保留title字段，并保留前10条记录
            for($j = 0; $j < $sug_cnt; $j++){
                $suglist['suglist'][$j] = $suglist['suglist'][$j]['title'];
                if($j > 9){
                    unset($suglist['suglist'][$j]);
                }
            }

            GFunc::cacheSet($key, $suglist);
        }

        return $suglist;
    }

    /**
     * v4 皮肤市场接口迁移
     *
     * @route({"GET", "/v4ssug"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"version", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"keyword", "$._GET.keyword"})
     * @param({"resolution", "$._GET.resolution"})
     * @param({"plt", "$._GET.plt"})
     * @param({"screen_w", "$._GET.screen_w"})
     * @param({"screen_h", "$._GET.screen_h"})
     * @return({"body"})
     * @return $array
     */
    public function v4ssugAction($platform='i1',$version="5.3.0.0",$screen_w=640,$screen_h=32,$keyword='',$resolution='320',$plt='')
    {
        $args = func_get_args();
        $cacheKey = __CLASS__ . __METHOD__ . implode('_', $args);
        $suglist = GFunc::cacheGet($cacheKey);
        if (false === $suglist) {
            $os = Util::getPhoneOS($platform);
            //android平台不需要考虑分辨率
            if($plt==='iphone'){
                //该分辨率为老的iphone，基本不用
                if($resolution==='320'){
                    $resolution='480x320';
                    //主流分辨率
                }elseif($resolution==='640'){
                    $resolution='960x640';
                }else{
                    $resolution='480x320';
                }
            }
            //清除错误的字符
            $keyword = strip_tags($keyword);
            $replacechars = array('%','\'','"','#','\\');
            $keyword= str_replace($replacechars, " ", $keyword);

            //返回sug列表suglist
            $suglist['suglist'] = array();

            //查询条件
            $condition = ' status=100 and type =1 ';
            $regexp_sql = ' and os in ("com", "all", "'.$os.'") and ((platform_code = "all") or ((select "' . strtolower($platform) . '" REGEXP platform_code) = 1)) and ((except_platform_code = "empty") or ((select "' . strtolower($platform) . '" REGEXP except_platform_code) = 0))';
            $condition .= $regexp_sql;
            //查询参数
            $condition_args = array();

            //版本为2以上的皮肤iphone需要考虑分辨率
            if($plt === 'iphone'){
                $condition.=' and iossupported = ?';
                array_push($condition_args, 'Y');
                $condition.=" and (resolution = ? OR resolution is null OR resolution = '')";
                array_push($condition_args, $resolution);
            }

            //屏幕宽高适配
            $condition .= ' AND (min_width_px <= ? OR min_width_px is null OR min_width_px = "") AND (max_width_px >= ? OR max_width_px is null OR max_width_px = "" OR max_width_px = 0) ';
            $condition .= ' AND (min_height_px <= ? OR min_height_px is null OR min_height_px = "") AND (max_height_px >= ? OR max_height_px is null OR max_height_px = "" OR max_height_px = 0) ';
            array_push($condition_args, $screen_w, $screen_w, $screen_h, $screen_h);

            //关键词
            if(trim($keyword)!=='')
            {
                $condition.=' and title like ?';
                array_push($condition_args, '%'.$keyword.'%');

                //排序字段
                $condition .= ' order by download_number desc';
                $sql = 'select * from input_skinthemes where ' . $condition;

                //从数据库中读取sug数据
                $skinModel= IoCload("models\\SkinthemeModel");

                $sugdate = $skinModel->queryp($sql, $condition_args);

                foreach ($sugdate as $sug){

                    //根据使用平台号及排除平台号判断是否下发
                    $platform_code = isset($sug['platform_code'])? $sug['platform_code'] : 'all';
                    $except_platform_code = isset($sug['except_platform_code'])? $sug['except_platform_code'] : '';

                    $min_version = isset($sug['min_version'])? intval($sug['min_version_int']) : 0;
                    $max_version = isset($sug['max_version'])? intval($sug['max_version_int']) : 100;
                    $except_version = isset($sug['except_version'])? intval($sug['except_version_int']) : 0;

                    $onesug = array();
                    $onesug['id'] = $sug['id'];
                    $onesug['title'] = $sug['title'];
                    $onesug['download_number'] = $sug['download_number'];
                    array_push($suglist['suglist'], $onesug);
                }
            }

            //如果sug数量小于等于3，满足要求；如果sug数量大于3，则对于排序在3个之后的sug按id倒序排序
            //即保持sug中前三为热门下载皮肤，之后按皮肤从新到旧排序

            //假设将排序前三的皮肤download_number保持不变，将排序在3之后的皮肤的download_number置为-1
            $sug_cnt = count($suglist['suglist']);
            if($sug_cnt > 3){
                for($i = 0; $i < $sug_cnt; $i++){
                    if($i > 2){
                        $suglist['suglist'][$i]['download_number'] = '-1';
                    }
                }
            }

            //自定义排序：先按照下载量排序，后按照id降序排序；这样可以，保持前三位皮肤不变，之后由于下载量均为-1，将按皮肤从新到旧排序
            usort($suglist['suglist'], function($a, $b){
                if ($a['download_number'] == $b['download_number']){
                    if($a['id'] == $b['id']){
                        return 0;
                    }
                    return ($a['id'] > $b['id']) ? -1 : 1;
                }
                return ($a['download_number'] > $b['download_number']) ? -1 : 1;
            });

            //去除多余的辅助排序字段，只保留title字段，并保留前10条记录
            for($j = 0; $j < $sug_cnt; $j++){
                $suglist['suglist'][$j] = $suglist['suglist'][$j]['title'];
                if($j > 9){
                    unset($suglist['suglist'][$j]);
                }
            }

            GFunc::cacheSet($cacheKey, $suglist);
        }

        return $suglist;
    }

    /**
     *
     * @desc v4 迁移 皮肤下载
     * @route({"GET", "/sd"})
     * @param({"id", "$._GET.id"})
     * @param({"token", "$._GET.token"})
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     * 验证通过则：
     * HTTP/1.1 302 Found
     * Location: 皮肤主题下载链接
     * 验证不通过则：
     * HTTP/1.1 404 Not Found
     */
    public function v4d($id = '', $token = '', &$status = '', &$location = '')
    {
        $id = trim($id);
        $token = trim($token);
        $id = $token ? $token : $id;
        if ($id) {
            $skinModel = IoCload("models\\SkinthemeModel");

            $skin = $skinModel->cache_getOne($id);

            if (!empty($skin['download_link'])) {
                $url = $this->domain_v4.$skin['download_link'];

                $status = "302 Found";
                $location = "Location: ".$url;

                return;
            }
        }

        $status = "404 Not Found";
        return ;
    }

    /**
     * @desc 通过皮肤token获取是否是静默下载皮肤
     * @route({"GET", "/getSilentUpSkin"})
     * @param({"token", "$._GET.token"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * {
            "code": 0,
            "msg":"",
            "data": {
                    "id": "5430",
                    "token": "61446ed8f58c369f15d7b7d445ce637b",
                    "is_skin": "0",
                    "url": "http://r6.mo.baidu.com/v5/skin/items/t5430/file?reserve=1"
                }
            }
     */
    public function getSilentUpSkin($token='') {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => new stdClass(),
        );
        if (empty($token)) {
            $result['code'] = 1;
            $result['msg'] = '参数错误';
            return $result;
        }
        $objSkinthemeModel = IoCload('models\\SkinthemeModel');
        $skinInfo = $objSkinthemeModel->cache_getSkinInfoByToken($token, GFunc::getCacheTime('2hours'));
        if (!empty($skinInfo)) {
            $result['data']->id = $skinInfo[0]['id'];
            if (1 == $skinInfo[0]['type']) {
                $url = $this->domain_v5 . '/v5/skin/items/s' . $skinInfo[0]['id'] . '/file?reserve=1';
            } else {
                $url = $this->domain_v5 . '/v5/skin/items/t' . $skinInfo[0]['id'] . '/file?reserve=1';
            }
            $result['data']->url = $url;
        }
        
        return $result;
    }
    
    /**
     * @desc 通过皮肤token
     * @route({"GET", "/getSearchHotword"})
     * @param({"type", "$._GET.type"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * {
        "code": 0,
        "msg": "",
        "data": [
            "啊",
            "啊",
            "啊",
            "啊",
        ]
    }
     */
    public function getSearchHotword($type=2) {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => array(),
        );
        
        //pm在v4后台配置的搜索热词
        $objInputEkinEmojiHotword = IoCload('models\\InputSkinEmojiHotword');
        $defaultWord = $objInputEkinEmojiHotword->cache_getLastHotword($type, GFunc::getCacheTime('2hours'));
        $firstWord = $second = array();
        if (!empty($defaultWord)) {
            $filterIds = array();
            foreach ($defaultWord as $defaultWordV) {
                $filterIds[] = intval($defaultWordV['filter_id']);
            }
            $filterInfo = $objInputEkinEmojiHotword->cache_getFilter($filterIds);
            //过滤
            $conditionFilter = IoCload("utils\\ConditionFilter");
            //只取配置的一条数据
            foreach ($defaultWord as $defaultWordKey => $defaultWordVal) {
                if (!empty($firstWord)) {
                    break;
                }
                if (empty($defaultWordVal['filter_id'])) {
                    for ($i=1; $i <= 10; $i++) {
                        if (!empty($defaultWordVal['word' . $i])) {
                            $firstWord[] = $defaultWordVal['word' . $i];
                        }
                    }
                    continue;
                } else {
                    $filterCondition = $filterInfo[$defaultWordVal['filter_id']];
                    if (empty($filterCondition) || $conditionFilter->filter($filterCondition['filter_conditions'])) {
                        for ($i=1; $i <= 10; $i++) {
                            if (!empty($defaultWordVal['word' . $i])) {
                                $firstWord[] = $defaultWordVal['word' . $i];
                            }
                        }
                    }
                }
            }
        }
        //泽华跑出来的数据
        $objResourceUserQuerys = IoCload('models\ResourceUserQuerys');
        $arrSearchType = 2 == $type ? 'skin' : 'emoji';
        $topWords = $objResourceUserQuerys->cache_getLastHotword($arrSearchType, GFunc::getCacheTime('2hours'));
        if (!empty($topWords)) {
            foreach ($topWords as $topWordsK => $topWordsV) {
                $second[] = trim($topWordsV['resource_query']);
            }
        }
        //将两者数据合并并去重取10个
        $result['data'] = array_slice(array_unique(array_merge($firstWord, $second)), 0, 10);
        
        return $result;
    }

    /**
     * 运营活动分享计数
     * @route({"GET","/share"})
     * @param({"cuid", "$._GET.cuid"}) string 用户cuid
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function share($cuid = '') {
        $out = Util::initialClass(false);

        //activity deadline
        //$strActivityName = 'festival_activity_deadline';

        //timeline is 2020-02-05 23:59:59
        //$intDeadLine = strtotime('2020-02-05 23:59:59');

        //cache ttl, by secord
        //$ttl = $intDeadLine - time();

        //set redis when ttl bigger than 0
        //if($ttl > 0) {
            /*$now = date('Y-m-d');

            //delete | in cuid tail
            $arrTmp = explode('|', $cuid);
            $cuid = $arrTmp[0];

            //set redis conf
            $objRedis = IoCload('utils\\KsarchLottoryRedis');
            $strCacheKey = 'input_activity_cache_key_' . $cuid;

            //默认加1
            $field = 'available';
            $available = $objRedis->hget($strCacheKey, $field);

            //create new data when there has no content
            if($available === null) {
                $available = 1;

                //set available
                $objRedis->hset($strCacheKey, $field, $available);
            }

            //var_dump($objRedis->getHAll($strCacheKey));

            //share, lottory
            $field = 'share_' . $now;
            $intField = $objRedis->hget($strCacheKey, $field);
            if($intField === false) {
                $objRedis->hset($strCacheKey, $field, 1);
            } else {
                $objRedis->hIncreby($strCacheKey, $field, 1);
            }

            $field = 'lottory_' . $now;
            $intField = $objRedis->hget($strCacheKey, $field);
            if($intField === false) {
                $objRedis->hset($strCacheKey, $field, 1);
            } else {
                if($intField < 3) {
                    //先加available
                    $objRedis->hIncreby($strCacheKey, 'available', 1);
                    //再加lottory
                    $objRedis->hIncreby($strCacheKey, $field, 1);
                }
            }*/

            //set ttl
            //$objRedis->setCacheExpire($strCacheKey, $ttl);

        //}
        
        return Util::returnValue($out, false);
    }
}
