<?php

use BaiduBce\Services\Bos\BosOptions;
use utils\Bos;
use utils\CustLog;
use utils\Imagicks;
use utils\LogHelper;
use utils\ThApi;
use utils\Util;
use EntitySearch\EntityProcessor\DefaultEntityProcessor;
use utils\GFunc;
use utils\ErrorCode;
use utils\ErrorMsg;
use models\CoverAssessClient;
use models\ContentAssessClient;
use models\DiyAremojiModel;
use Bd_DB;

/**
 * AR 表情
 * @author chendaoyan
 * @path("/aremoji/")
 */
class ArEmoji {
    /** @property $domain_v4 */
    public $domain_v4;

    private static $cachePre = 'aremoji_';

    /** @property $bucket */
    private $bucket;

    private $bosLocation = 'imeres';

    private $defaultAremojiId = 1000000000;

    //bos保存路径
    private $strPath = 'aremoji';

    /**
     * AR表情列表
     * @route({"GET", "/getlist"})
     * http://agroup.baidu.com/inputserver/md/article/798542
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"verName", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"timestamp", "$._GET.timestamp"}) 通知中心下发的时间戳
     * @param({"phoneType", "$._GET.phonetype"}) 机型类型
     * @param({"isParis", "$._GET.isparis"}) 是否是paris
     * @param({"sdkversion", "$._GET.sdkversion"}) sdk版本号
     * @param({"isNewThumb", "$._GET.isNewThumb"}) 是否使用新的缩略图，默认否
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getList($verName='', $plt='', $timestamp=0, $phoneType=1, $isParis=0, $sdkversion='2.0', $isNewThumb=0) {
        $result = array(
            'code' => 0,
            'ecode' => 0, //8.6以上版本使用
            'msg' => '',
            'emsg' => '',//8.6以上版本使用
            'data' => array(),
        );
        if (empty($verName) || empty($plt)){
            $result['code'] = 1;
            $result['ecode'] = 1;
            $result['msg'] = '参数错误';
            $result['emsg'] = '参数错误';
            return $result;
        }
        $key = md5(self::$cachePre . 'v1_' . __CLASS__ . __FUNCTION__ . 'aremoji_list' . $verName . $plt . $phoneType . $timestamp . $isParis . $sdkversion . $isNewThumb);
        $versionKey = md5(self::$cachePre . 'v1_' . __CLASS__ . __FUNCTION__ . 'aremoji_version');
        $arrData = GFunc::cacheZget($key);
        $version = GFunc::cacheGet($versionKey);
        if (false === $arrData) {
            $objArEmojiModel = IoCload('models\\ArEmojiModel');
            $verName = Util::formatVer($verName);
            $list = $objArEmojiModel->getListByVersion($plt, $isParis);
            if (false === $list) {
                $result['code'] = 2;
                $result['ecode'] = 2;
                $result['msg'] = '数据库错误';
                $result['emsg'] = '数据库错误';
                return $result;
            }
            $entityProcessor = new DefaultEntityProcessor();
            $index = array('one', 'two', 'three', 'four', 'five');
            $bosHost = GFunc::getGlobalConf('bos_host_https');
            $domainV5Https = GFunc::getGlobalConf('domain_v5_https');
            $bucket = 'imeres';
            $arrData = array();
            //ai输入法和主线输入法版本号不同
            if (in_array($plt, array('a11', 'i9', 'i10'))) {
                $maxVersionFields = 'ai_max_version';
                $minVersionFields = 'ai_min_version';
            } else {
                $maxVersionFields = 'max_version';
                $minVersionFields = 'min_version';
            }
            $isIos = Util::getOS($plt) == 'ios';
            $version = 0;
            foreach ($list as $listk => $listV) {
                //版本号下发
                if (strtotime($listV['update_time']) > $version) {
                    $version = strtotime($listV['update_time']);
                }
                //版本判断
                if (!(version_compare($listV[$minVersionFields], $verName, '<=')
                    && (empty($listV[$maxVersionFields]) || version_compare($listV[$maxVersionFields], $verName, '>='))
                    && version_compare($listV['min_sdk_version'], $sdkversion, '<=')
                    && (empty($listV['max_sdk_version']) || version_compare($listV['max_sdk_version'], $sdkversion, '>=')))) {
                    continue;
                }

                $file = $objArEmojiModel->getFile($list[$listk], $phoneType);
                if (empty($file)) {
                    continue;
                }
                $list[$listk]['file'] = $domainV5Https . 'v5/aremoji/download?id=' . $listV['id'] . '&tag=' . md5($file) . '&phoneType=' . $phoneType;
                $list[$listk]['recommend_info'] = array();
                foreach ($index as $indexV) {
                    if (empty($listV['recommend_text_' . $indexV]) && empty($listV['recommend_word_' . $indexV]) && empty($listV['recommend_pic_' . $indexV])) {
                        continue;
                    }
                    $list[$listk]['recommend_info'][] = array(
                        'recommend_text' => !empty($listV['recommend_text_' . $indexV]) ? $listV['recommend_text_' . $indexV] : '',
                        'recommend_word' => !empty($listV['recommend_word_' . $indexV]) ? $listV['recommend_word_' . $indexV] : '',
                        'recommend_pic' => !empty($listV['recommend_pic_' . $indexV]) ? $bosHost . '/' . $bucket . '/' . $listV['recommend_pic_' . $indexV] : '',
                    );
                }
                $list[$listk]['share_chartlet'] = !empty($list[$listk]['share_chartlet']) ? $bosHost . '/' . $bucket . '/' . $listV['share_chartlet'] : '';
                if (1 == $isNewThumb && !empty($listV['new_thumb'])) {
                    $thumb = $listV['new_thumb'];
                } else {
                    $thumb = $listV['thumb'];
                }
                $list[$listk]['thumb'] = !empty($thumb) ? $bosHost . '/' . $bucket . '/' . $thumb : '';
                $list[$listk]['category'] = !empty($list[$listk]['category']) ? explode('|', $list[$listk]['category']) : array();
                $list[$listk]['update_time'] = strtotime($list[$listk]['update_time']);
                $list[$listk]['finished_click_img_url'] = !empty($listV['finished_click_img_url']) ? $bosHost . '/' . $bucket . '/' . $listV['finished_click_img_url'] : '';
                $list[$listk]['music_id'] = !empty($listV['music_id']) ? explode('|', trim($listV['music_id'], '|')) : array();
                $list[$listk]['parent'] = !empty($listV['parent']) ? explode('|', trim($listV['parent'], '|')) : array();
                //ios商业化相关字段判断
                if ($isIos) {
                    $list[$listk]['finished_click_type'] = $listV['finished_click_type_ios'];
                    $list[$listk]['finished_click_text'] = $listV['finished_click_text_ios'];
                    $list[$listk]['finished_click_img_url'] = !empty($listV['finished_click_img_url_ios']) ? $bosHost . '/' . $bucket . '/' . $listV['finished_click_img_url_ios'] : '';
                    $list[$listk]['finished_click_url'] = $listV['finished_click_url_ios'];
                }
                $arrData[] = $entityProcessor->processEntity($list[$listk], "entity\\ArEmojiEntity");
            }
            GFunc::cacheZset($key, json_encode($arrData));
            GFunc::cacheSet($versionKey, intval($version));
            $result['data'] = $arrData;
            $result['md5'] = md5(json_encode($arrData));
            $result['version'] = intval($version);
        } else {
            $result['data'] = !empty($arrData) ? json_decode($arrData,true) : array();
            $result['md5'] = !empty($arrData) ? md5($arrData) : '';
            $result['version'] = intval($version);
        }

        return $result;
    }

    /**
     * AR表情客户端so文件列表
     * @route({"GET", "/sofilelist"})
     * @param({"sdkVersion", "$._GET.sdk_version"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"timestamp", "$._GET.timestamp"}) 通知中心下发的时间戳
     * @param({"soVersion", "$._GET.soversion"}) so版本号
     * @param({"version", "$._GET.version"}) 通知中心下发的版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getSoFile($sdkVersion='', $timestamp=0, $soVersion=0, $version=0) {
        $result = array(
            'ecode' => 0, //8.6以上使用
            'code' => 0,
            'emsg' => '',//8.6以上使用
            'msg' => '',
            'data' => new stdClass(),
        );
        if (empty($sdkVersion)) {
            $result['code'] = 1;
            $result['ecode'] = 1;
            $result['msg'] = '参数错误';
            return $result;
        }
        //从资源服务获取数据
        $args = func_get_args();
        unset($args[0]);
        unset($args[2]);
        $cacheKey = self::$cachePre . __Class__ . __FUNCTION__ . '_' . implode('_', $args) . '_cachekey';
        $list = GFunc::cacheGet($cacheKey);
        if (false === $list) {
            $resUrl = '/res/json/input/r/online/aremoji_so/';
            $strQuery = 'onlycontent=1';
            $list = Util::getResource($resUrl, $strQuery, true);
            GFunc::cacheSet($cacheKey, $list);
        }
        if (!empty($list['output'])) {
            $data = array();
            $conditionFilter = IoCload("utils\\ConditionFilter");
            foreach ($list['output'] as $listK => $listV) {
                if ($sdkVersion != $listV['sdk_version']) {
                    continue;
                }
                if ($conditionFilter->filter($listV['filter_conditions'])) {
                    if ($listV['version'] <= $soVersion) {
                        continue;
                    }
                    unset($listV['filter_conditions']);
                    if (empty($data)) {
                        $data = $listV;
                    } else if (isset($data['version']) && $listV['version'] > $data['version']) {
                        $data = $listV;
                    }
                    break;
                }
            }
            if (!empty($data)) {
                $result['data'] = $data;
            }
        }
        //获取最新更新时间
        $result['version'] = intval(strtotime($list['headers']['Last-Modified']));
        return Util::returnValue($result, true, true);
    }

    /**
     * 获取对图库数据
     * @route({"GET", "/getduipiclist"})
     * @param({"keyword", "$._GET.keyword"}) string 关键词
     * @param({"num", "$._GET.num"}) string 关键词
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getDuiPic($keyword='', $num=10) {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => array(),
        );

        $objDuiPicModel = IoCload('models\\DuiPicModel');
        if (!empty($keyword)) {
            $list = $objDuiPicModel->cache_getListByKeyword($keyword);
            //打乱顺序
            shuffle($list);
            //获取前10个数据
            $list = array_slice($list, 0, $num);
        } else {
            //随机取10个
            $allId = $objDuiPicModel->cache_getAllId();
            if (empty($allId)) {
                return $result;
            }
            //打乱顺序
            shuffle($allId);
            //获取前10个数据
            $firstTenList = array_slice($allId, 0, $num);
            $arrId = array();
            foreach ($firstTenList as $firstTenListV) {
                $arrId[] = $firstTenListV['id'];
            }
            $list = $objDuiPicModel->cache_getListByIds($arrId);
        }
        $entityProcessor = new DefaultEntityProcessor();
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        $bucket = 'imeres';
        foreach ($list as $listK => $listV) {
            $listV['pic'] = $bosHost . '/' . $bucket . '/' . $listV['pic'];
            $result['data'][] = $entityProcessor->processEntity($listV, "entity\\DuiPicEntity");
        }
        return $result;
    }

    /**
     * 根据ID查询AR表情信息
     * @route({"GET", "/info"})
     * @param({"id", "$._GET.id"}) string 关键词
     * @param({"phoneType", "$._GET.phonetype"}) 机型类型
     * @param({"isNewThumb", "$._GET.isNewThumb"}) 是否使用新的缩略图，默认否
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getAremojiInfo($id, $phoneType=1, $isNewThumb=0, $plt='') {
        $result = array(
            'status' => 0,
            'msg' => '',
            'data' => new stdClass(),
        );
        if (empty($id)) {
            $result = array(
                'status' => 1,
                'msg' => '参数错误',
                'data' => new stdClass(),
            );
            return $result;
        }
        $objArEmojiModel = IoCload('models\\ArEmojiModel');
        $list = $objArEmojiModel->cache_getAremojiInfoById($id);
        if (!empty($list[0])) {
            $entityProcessor = new DefaultEntityProcessor();
            $index = array('one', 'two', 'three', 'four', 'five');
            $bosHost = GFunc::getGlobalConf('bos_host_https');
            $domainV5Https = GFunc::getGlobalConf('domain_v5_https');
            $bucket = 'imeres';
            $isIos = Util::getOS($plt) == 'ios';
            foreach ($list as $listk => $listV) {
                $file = $objArEmojiModel->getFile($list[$listk], $phoneType);
                if (empty($file)) {
                    continue;
                }
                $list[$listk]['file'] = $domainV5Https . 'v5/aremoji/download?id=' . $listV['id'] . '&tag=' . md5($file);
                $list[$listk]['recommend_info'] = array();
                foreach ($index as $indexV) {
                    if (empty($listV['recommend_text_' . $indexV]) && empty($listV['recommend_word_' . $indexV]) && empty($listV['recommend_pic_' . $indexV])) {
                        continue;
                    }
                    $list[$listk]['recommend_info'][] = array(
                        'recommend_text' => !empty($listV['recommend_text_' . $indexV]) ? $listV['recommend_text_' . $indexV] : '',
                        'recommend_word' => !empty($listV['recommend_word_' . $indexV]) ? $listV['recommend_word_' . $indexV] : '',
                        'recommend_pic' => !empty($listV['recommend_pic_' . $indexV]) ? $bosHost . '/' . $bucket . '/' . $listV['recommend_pic_' . $indexV] : '',
                    );
                }
                $list[$listk]['share_chartlet'] = !empty($list[$listk]['share_chartlet']) ? $bosHost . '/' . $bucket . '/' . $listV['share_chartlet'] : '';
                if (1 == $isNewThumb && !empty($listV['new_thumb'])) {
                    $thumb = $listV['new_thumb'];
                } else {
                    $thumb = $listV['thumb'];
                }
                $list[$listk]['thumb'] = !empty($thumb) ? $bosHost . '/' . $bucket . '/' . $thumb : '';
                $list[$listk]['category'] = !empty($list[$listk]['category']) ? explode('|', $list[$listk]['category']) : array();
                $list[$listk]['update_time'] = strtotime($list[$listk]['update_time']);
                $list[$listk]['finished_click_img_url'] = !empty($list[$listk]['finished_click_img_url']) ? $bosHost . '/' . $bucket . '/' . $list[$listk]['finished_click_img_url'] : '';
                $list[$listk]['music_id'] = !empty($list[$listk]['music_id']) ? explode('|', trim($list[$listk]['music_id'], '|')) : array();
                $list[$listk]['parent'] = !empty($listV['parent']) ? explode('|', trim($listV['parent'], '|')) : array();
                //ios商业化相关字段判断
                if ($isIos) {
                    $list[$listk]['finished_click_type'] = $listV['finished_click_type_ios'];
                    $list[$listk]['finished_click_text'] = $listV['finished_click_text_ios'];
                    $list[$listk]['finished_click_img_url'] = !empty($listV['finished_click_img_url_ios']) ? $bosHost . '/' . $bucket . '/' . $listV['finished_click_img_url_ios'] : '';
                    $list[$listk]['finished_click_url'] = $listV['finished_click_url_ios'];
                }
                $result['data'] = $entityProcessor->processEntity($list[$listk], "entity\\ArEmojiEntity");
            }
        } else {
            $result['status'] = 2;
            $result['msg'] = '查询数据为空';
        }

        return $result;
    }

    /**
     * 获取AR表情素材最新更新时间
     * @route({"GET", "/getlasttimestamp"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getLastTimestamp() {
        $result = array(
            'status' => 0,
            'msg' => '',
            'data' => array(
                'timestamp' => 0,
            ),
        );
        $objArEmojiModel = IoCload('models\\ArEmojiModel');
        $timestamp = $objArEmojiModel->cache_getLastUpdateTimestamp();
        //分类更新时间
        $objAremojiCategory = IoCload('models\\AremojiCategoryModel');
        $categoryTimestamp = $objAremojiCategory->cache_getLastUpdateTimestamp();
        $result['data']['timestamp'] = $categoryTimestamp > $timestamp ? $categoryTimestamp : $timestamp;
        return $result;
    }

    /**
     * 获取AR表情数据搜集的配置
     * @route({"GET", "/getdataconfig"})
     * @param({"sdk", "$._GET.sdk"}) string 1是sdk请求，2是客户端请求
     * @param({"sdkversion", "$._GET.sdkversion"}) string sdk版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getDataConfig($sdk=1, $sdkversion=0) {
        $result = Util::initialClass();
        $result['data']->list = new stdClass();
        if (!in_array($sdk, array(1, 2))) {
            $result['ecode'] = ErrorCode::PARAM_ERROR;
            $result['emsg'] = ErrorMsg::getMsg(ErrorCode::PARAM_ERROR);
            return $result;
        }
        //从资源服务获取数据
        $searchStr = urlencode('{"type":' . $sdk . '}');
        $resUrl = '/res/json/input/r/online/aremoji-data-config/?onlycontent=1&search=' . $searchStr;
        $cacheKey = md5(self::$cachePre . __Class__ . __FUNCTION__ . '_cachekey' . $resUrl);
        $list = Util::ralGetContent($resUrl, $cacheKey);
        if (!empty($list)) {
            foreach ($list as $listK => $listV) {
                if (1 == $sdk) { //sdk过滤条件
                    $conditionFilter = IoCload("utils\\SdkConditionFilter");
                    $filterRes = $conditionFilter->filter($listV['sdk_filter_conditions']);
                } else {// 通用过滤条件
                    $conditionFilter = IoCload("utils\\ConditionFilter");
                    $filterRes = $conditionFilter->filter($listV['filter_conditions']);
                }
                if (true === $filterRes) {
                    unset($listV['filter_conditions']);
                    unset($listV['sdk_filter_conditions']);
                    $result['data'] = $listV;
                    break;
                }
            }
        } else {
            $result['ecode'] = ErrorCode::RES_ERROR;
            $result['emsg'] = ErrorMsg::getMsg(ErrorCode::RES_ERROR);
        }

        return $result;
    }

    /**
     * 获取AR表情数据搜集的配置开关
     * @route({"GET", "/getdataconfigswitch"})
     * @param({"sdk", "$._GET.sdk"}) string 1是sdk请求，2是客户端请求
     * @param({"sdkversion", "$._GET.sdkversion"}) string sdk版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getDataConfigSwitch($sdk, $sdkversion=0) {
        $result = Util::initialClass();
        //获取数据下发开关
        $objArEmojiModel = IoCload('models\\ArEmojiModel');
        $sdkSwitch = $objArEmojiModel->getSdkSwitch($sdk, $sdkversion);
        if (false === $sdkSwitch) {
            $switch = 0;
        } else {
            $switch = 1;
        }
        $result['data']->switch = $switch;

        return $result;
    }

    /**
     * 获取AR表情分类
     * @route({"GET", "/getcategory"})
     * http://agroup.baidu.com/inputserver/md/article/803942
     * @param({"phoneType", "$._GET.phonetype"}) 机型类型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getCategoryList($phoneType=1) {
        $result = Util::initialClass();
        $key = self::$cachePre . __FUNCTION__ . __CLASS__ . 'aremoji_category' . $phoneType;
        $filterConditionKey = self::$cachePre . __FUNCTION__ . __CLASS__ . 'aremoji_category_filter_condition';
        $versionKey = self::$cachePre . __FUNCTION__ . __CLASS__ . 'last_update_time';
        $list = GFunc::cacheGet($key);
        $version = GFunc::cacheGet($versionKey);
        if (false === $list) {
            $objAremojiCategory = IoCload('models\\AremojiCategoryModel');
            $list = $objAremojiCategory->getListBySort();
            $arrFilterIds = array();
            foreach ($list as $offset => $listV) {
                if (strtotime($listV['update_time']) > $version) {
                    $version = strtotime($listV['update_time']);
                }
                //低端机
                if (1 == $phoneType && 3 == $listV['for_phone_type']) {
                    unset($list[$offset]);
                    continue;
                }
                //高端机
                else if (2 == $phoneType && 2 == $listV['for_phone_type']) {
                    unset($list[$offset]);
                    continue;
                }
                if (!empty($listV['filter_id'])) {
                    $arrFilterIds[] = intval($listV['filter_id']);
                }
                $list[$offset]['thumbnails'] = empty($list[$offset]['thumbnails'])?$list[$offset]['thumbnails']:sprintf("%s%s", $this->domain_v4, $list[$offset]['thumbnails']);
            }
            if (!empty($arrFilterIds)) {
                $arrFilterCondition = Util::getFilter($arrFilterIds);
            } else {
                $arrFilterCondition = array();
            }

            GFunc::cacheSet($filterConditionKey, $arrFilterCondition, GFunc::getCacheTime('hours') * 24);
            GFunc::cacheSet($key, $list);
            GFunc::cacheSet($versionKey, $version);
        }

        $arrFilterCondition = GFunc::cacheGet($filterConditionKey);
        //filter
        $objConditionFilter = IoCload('utils\\ConditionFilter');
        foreach ($list as $listKK => $listVV) {
            if (empty($listVV['filter_id'])) {
                continue;
            } else {
                if ($objConditionFilter->filter($arrFilterCondition[$listVV['filter_id']]['filter_conditions'])) {
                    continue;
                } else {
                    unset($list[$listKK]);
                }
            }
        }
        $list = array_values($list);

        $result['data']->list = $list;
        $result['version'] = $version;

        return Util::returnValue($result, true, true);
    }

    /**
     * AR表情上传
     * @route({"POST", "/uploadvideo"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function uploadVideo() {
        $result = Util::initialClass();
        if (empty($_FILES['video']['tmp_name'])) {
            $result['ecode'] = ErrorCode::PARAM_ERROR;
            $result['emsg'] = ErrorMsg::getMsg(ErrorCode::PARAM_ERROR);
            return Util::returnValue($result);
        }

        $aremojiModel = IoCload("models\\ArEmojiModel");
        $uploadRes = $aremojiModel->upload($_FILES['video']['tmp_name'], $_FILES['video']['name']);
        if (false === $uploadRes) {
            $result['ecode'] = ErrorCode::DATA_RETURN_NULL;
            $result['emsg'] = ErrorMsg::getMsg(ErrorCode::DATA_RETURN_NULL);
            return Util::returnValue($result);
        }
        //upload cover image
        $uploadCoverRes = '';
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploadCoverRes = $aremojiModel->upload($_FILES['image']['tmp_name'], $_FILES['image']['name']);
            if (false === $uploadCoverRes) {
                $uploadCoverRes = '';
            }
        }
        $activityHost = GFunc::getGlobalConf('activityHost');
        $result['data']->url = $activityHost . '/static/activitysrc/aremoji/index.html?token=' . urlencode($uploadRes) . '&img=' . urlencode($uploadCoverRes);

        //查询AR表情素材信息
        $aremojiId = intval($_POST['aremoji_id']);
        if (!empty($aremojiId)) {
            $condition = array(
                'id=' . intval($aremojiId),
            );
            $aremojiInfo = $aremojiModel->select('*', $condition);
            $result['data']->title = !empty($aremojiInfo[0]['share_title']) ? $aremojiInfo[0]['share_title'] : '';
            $result['data']->description = !empty($aremojiInfo[0]['share_desc']) ? $aremojiInfo[0]['share_desc'] : '';
        } else {
            $result['data']->title = '';
            $result['data']->description = '';
        }

        return Util::returnValue($result);
    }

    /**
     * 获取AR表情变声素材
     * @route({"GET", "/getsound"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getSound() {
        $result = Util::initialClass();
        $key = self::$cachePre . __FUNCTION__ . __CLASS__ . 'aremoji_sound';
        $filterConditionKey = self::$cachePre . __FUNCTION__ . __CLASS__ . 'aremoji_category_sound_filter_condition';
        $versionKey = self::$cachePre . __FUNCTION__ . __CLASS__ . 'aremoji_sound_version';

        $list = GFunc::cacheGet($key);
        $version = GFunc::cacheGet($versionKey);
        if (false === $list) {
            $objAremojiSound = IoCload('models\\AremojiSoundModel');
            $tmpList = $objAremojiSound->select('*', array('status=100'));
            $arrFilterIds = array();
            $idList = array();
            $list = array();
            $bosHost = GFunc::getGlobalConf('bos_host_https');
            $version = 0;
            foreach ($tmpList as $listV) {
                //版本号下发
                if (strtotime($listV['update_time']) > $version) {
                    $version = strtotime($listV['update_time']);
                }
                if (!empty($listV['filter_id'])) {
                    $arrFilterIds[] = intval($listV['filter_id']);
                }
                $listV['icon'] = $bosHost . '/' . $listV['icon'];
                $idList[$listV['id']] = $listV;
            }
            //排序
            $objAremojiSoundSort = IoCload("models\\AremojiSoundSortModel");
            $sortRes = $objAremojiSoundSort->select('*', array('id=1'));
            if (!empty($sortRes[0])) {
                $arrSortRes = explode(',', $sortRes[0]['data']);
                foreach ($arrSortRes as $arrSortResK => $arrSortResV) {
                    if (isset($idList[$arrSortResV])) {
                        $list[] = $idList[$arrSortResV];
                        unset($idList[$arrSortResV]);
                    } else {
                        continue;
                    }
                }
            }
            $list = array_merge($list, $idList);

            if (!empty($arrFilterIds)) {
                $arrFilterCondition = Util::getFilter($arrFilterIds);
            } else {
                $arrFilterCondition = array();
            }

            GFunc::cacheSet($filterConditionKey, $arrFilterCondition, GFunc::getCacheTime('hours') * 24);
            GFunc::cacheSet($key, $list);
            GFunc::cacheSet($versionKey, intval($version));
        }

        $arrFilterCondition = GFunc::cacheGet($filterConditionKey);
        //filter
        $entityProcessor = new DefaultEntityProcessor();
        $objConditionFilter = IoCload("utils\\ConditionFilter");
        foreach ($list as $listKK => $listVV) {
            if (empty($listVV['filter_id']) || $objConditionFilter->filter($arrFilterCondition[$listVV['filter_id']]['filter_conditions'])) {
                $list[$listKK] = $entityProcessor->processEntity($list[$listKK], "entity\\AremojiSoundEntity");
                continue;
            } else {
                unset($list[$listKK]);
            }
        }
        $list = array_values($list);

        $result['data'] = $list;
        $result['version'] = intval($version);

        return Util::returnValue($result, false , true);
    }

    /**
     * 统计展示、点赞、取消点赞、发送次数，这边只是给个空接口，统计在魏晓那边
     * @route({"POST", "/statistics"})
     * @param({"data", "$._POST.data"}) string 客户端发送过来的数据
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function statistics($data) {
        $result = Util::initialClass();
        $arrData = json_decode($data, true);
        if (is_array($arrData)) {
            foreach ($arrData as $arrDataV) {
                switch (intval($arrDataV['type'])) {
                    case 1 :
                        CustLog::write('aremoji_view', array($arrDataV));
                        break;
                    case 2 :
                        CustLog::write('aremoji_praise', array($arrDataV));
                        break;
                    case 3 :
                        CustLog::write('aremoji_cancel_praise', array($arrDataV));
                        break;
                    case 4 :
                        CustLog::write('aremoji_send', array($arrDataV));
                        break;
                }
            }
        } else {
            return ErrorCode::returnError('PARAM_ERROR', 'params error');
        }
        return $result;
    }

    /**
     * 表情帝
     * http://agroup.baidu.com/inputserver/md/article/992284
     * @route({"POST", "/emperor"})
     * @param({"page", "$._GET.page"}) int 素材ID
     * @param({"pageSize", "$._GET.page_size"}) int 素材ID
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function emperor($page=1, $pageSize=64) {
        $result = Util::initialClass(false);
        $arrHandledBlackList = array();
        $uid = 0;
        //屏蔽功能
        if (isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) {
            //get bduss info
            $bdussModel = IoCload('models\\BdussModel');
            $userInfo = $bdussModel->getUserInfoByBduss();
            if (false !== $userInfo) {
                $uid = intval($userInfo['uid']);
                //get black list
                $aremojiModel = IoCload('models\\ArEmojiModel');
                $arrHandledBlackList = $aremojiModel->getBlackList($uid);
            }
        }

        $diyAremojiModel = IoCload('models\\DiyAremojiModel');
        $list = $diyAremojiModel->getEmperorList($page, $pageSize);
        //置顶+打乱顺序
        if ($page == 1) {
            $top_count = 0;
            for ($i = 0; $i < DiyAremojiModel::TOP_LIMITED; $i++) {
                if ($list[$i]['emperor_top'] == DiyAremojiModel::EMPEROR_TOP) {
                    $top_count++;
                }
            }
            $top_list = array_slice($list, 0, $top_count);
            $list = array_slice($list, $top_count);
            shuffle($list);
            $list = array_merge($top_list, $list);
        } else {
            shuffle($list);
        }

        $entityProcessor = new DefaultEntityProcessor();
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        foreach ($list as $listK => $listV) {
            //屏蔽功能
            if (!empty($arrHandledBlackList['user']) && array_key_exists($listV['uid'], $arrHandledBlackList['user'])) {
                continue;
            } else if (!empty($arrHandledBlackList['aremoji']) && array_key_exists($listV['id'], $arrHandledBlackList['aremoji'])) {
                continue;
            }
            if ($listV['uid'] == $uid) {
                $listV['is_hide'] = 1;
            } else {
                $listV['is_hide'] = 0;
            }
            $listV['uid'] = trim(bd_B64_Encode($listV['uid'], 0));//兼容加密的bug，去掉多余的字符\u0000;
            $listV['file_url_watermark'] = sprintf("%s/%s", $bosHost, empty($listV['file_url_watermark'])?$listV['file_url']:$listV['file_url_watermark']);
            $listV['file_url'] = !empty($listV['file_url']) ? $bosHost . '/' . $listV['file_url'] : '';
            $listV['cover_url'] = !empty($listV['cover_url']) ? $bosHost . '/' . $listV['cover_url'] : '';
            $listV['cover_gif_url'] =  !empty($listV['cover_gif_url']) ? $bosHost . '/' . $listV['cover_gif_url'] : '';
            $listV['praise'] = $listV['praise'] + $listV['artificial_praise'];
            $result['data'][] = $entityProcessor->processEntity($listV, 'entity\DiyAremojiEntity');
        }

        return Util::returnValue($result, false);
    }

    /**
     * 表情广场
     * @route({"POST", "/square"})
     * @param({"page", "$._GET.page"}) int 素材ID
     * @param({"pageSize", "$._GET.page_size"}) int 素材ID
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function square($page=1, $pageSize=64) {
        $result = Util::initialClass(false);
        //屏蔽功能
        $arrHandledBlackList = array();
        $uid = 0;
        //get bduss info
        if (isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) {
            $bdussModel = IoCload('models\\BdussModel');
            $userInfo = $bdussModel->getUserInfoByBduss();
            if (false !== $userInfo) {
                $uid = intval($userInfo['uid']);
                //get black list
                $aremojiModel = IoCload('models\\ArEmojiModel');
                $arrHandledBlackList = $aremojiModel->getBlackList($uid);
            }
        }

        $diyAremojiModel = IoCload('models\\DiyAremojiModel');
        //取最新的100个表情
        // 首页置顶
        $list = array();
        if ($page == 1) {
            $list = $diyAremojiModel->getTopSquareList();
            $topCount = count($list);
            $pageSize -= $topCount;
        }
        $newList = $diyAremojiModel->getNewList('new_list', 0, 100);
        $emperorList = $diyAremojiModel->getSquareList($page, $pageSize);

        if ($page <= 4) {
            for ($i=0; $i < 32; $i++) {
                if (isset($emperorList[$i * 2])) {
                    $list[] = $emperorList[$i * 2];
                }
                if (isset($emperorList[$i * 2 + 1])) {
                    $list[] = $emperorList[$i * 2 + 1];
                }
                if (isset($newList[($page - 1) * 32 + $i])) {
                    $list[] = $newList[($page - 1) * 32 + $i];
                }
            }
        } else {
            $list = $emperorList;
        }
        $entityProcessor = new DefaultEntityProcessor();
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        foreach ($list as $listK => $listV) {
            //屏蔽功能
            if (!empty($arrHandledBlackList['user']) && array_key_exists($listV['uid'], $arrHandledBlackList['user'])) {
                continue;
            } else if (!empty($arrHandledBlackList['aremoji']) && array_key_exists($listV['id'], $arrHandledBlackList['aremoji'])) {
                continue;
            }
            if ($listV['uid'] == $uid) {
                $listV['is_hide'] = 1;
            } else {
                $listV['is_hide'] = 0;
            }
            $listV['uid'] = trim(bd_B64_Encode($listV['uid'], 0));//兼容加密的bug，去掉多余的字符\u0000;
            $listV['file_url_watermark'] = sprintf("%s/%s", $bosHost, empty($listV['file_url_watermark'])?$listV['file_url']:$listV['file_url_watermark']);
            $listV['file_url'] = !empty($listV['file_url']) ? $bosHost . '/' . $listV['file_url'] : '';
            $listV['cover_url'] = !empty($listV['cover_url']) ? $bosHost . '/' . $listV['cover_url'] : '';
            $listV['cover_gif_url'] =  !empty($listV['cover_gif_url']) ? $bosHost . '/' . $listV['cover_gif_url'] : '';
            $listV['praise'] = $listV['praise'] + $listV['artificial_praise'];
            $result['data'][] = $entityProcessor->processEntity($listV, 'entity\DiyAremojiEntity');
        }
        return Util::returnValue($result, false);
    }

    /**
     * 我发布的表情
     * @route({"POST", "/my"})
     * @param({"page", "$._GET.page"}) int 素材ID
     * @param({"pageSize", "$._GET.page_size"}) int 素材ID
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function my($page=1, $pageSize=10) {
        //通过bduss去查询用户信息
        if (!(isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) ) {
            return ErrorCode::returnError('PARAM_ERROR', 'ukey error', true);
        }
        $bdussModel = IoCload("models\\BdussModel");
        $userInfo = $bdussModel->getUserInfoByBduss();
        if (false !== $userInfo) {
            $uid = intval($userInfo['uid']);
        } else {
            return ErrorCode::returnError('BDUSS_ERR', 'bduss error', true);
        }
        $result = Util::initialClass(false);
        $diyAremojiModel = IoCload("models\\DiyAremojiModel");
        $start = ($page - 1) * $pageSize;
        $start = $start >= 0 ? $start : 0;
        $limit = intval($pageSize);
        $list = $diyAremojiModel->select('*', array('uid="' . $uid . '"', 'is_del != 1'), null, 'order by create_time desc limit ' . $start . ', ' . $limit);
        if (false === $list || null === $list) {
            return ErrorCode::returnError('DB_ERROR', '查询失败', true);
        }
        $entityProcessor = new DefaultEntityProcessor();
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        foreach ($list as $listK => $listV) {
            $listV['file_url_watermark'] = sprintf("%s/%s", $bosHost, empty($listV['file_url_watermark'])?$listV['file_url']:$listV['file_url_watermark']);
            $listV['file_url'] = !empty($listV['file_url']) ? $bosHost . '/' . $listV['file_url'] : '';
            $listV['cover_url'] = !empty($listV['cover_url']) ? $bosHost . '/' . $listV['cover_url'] : '';
            $listV['cover_gif_url'] =  !empty($listV['cover_gif_url']) ? $bosHost . '/' . $listV['cover_gif_url'] : '';
            $listV['praise'] = $listV['praise'] + $listV['artificial_praise'];
            $result['data'][] = $entityProcessor->processEntity($listV, 'entity\DiyAremojiEntity');
        }
        return Util::returnValue($result, false);
    }

    /**
     * 发布制作的表情
     * @route({"POST", "/uploaddiyvideo"})
     * @param({"config", "$._POST.config"}) int 素材ID
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function uploadDiyVideo($config='') {
        $result = Util::initialClass();
        $data = array();
        //通过bduss去查询用户信息
        if (!(isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) ) {
            return ErrorCode::returnError('PARAM_ERROR', 'ukey error');
        }
        $aremojiModel = IoCload("models\\ArEmojiModel");
        $bdussModel = IoCload("models\\BdussModel");
        $userInfo = $bdussModel->getUserInfoByBduss();

        if (false !== $userInfo) {
            $data['uid'] = $userInfo['uid'];
            $data['username'] = mb_convert_encoding($userInfo['username'], "UTF-8", "GBK");
        } else {
            return ErrorCode::returnError('BDUSS_ERR', 'bduss error');
        }
        //upload aremoji file
        if (!empty($_FILES['video']['tmp_name']) && false !== $aremojiModel->strposArr(mime_content_type($_FILES['video']['tmp_name']), array('jpg', 'png', 'jpeg', 'mp4', 'gif'))) {
            $uploadRes = $aremojiModel->upload($_FILES['video']['tmp_name'], $_FILES['video']['name'], array(
                'contentType'=>$_FILES['video']['type']
            ));
            if (false === $uploadRes) {
                return ErrorCode::returnError('PARAM_ERROR', '文件参数错误');
            }
        } else {
            return ErrorCode::returnError('PARAM_ERROR', '文件参数错误');
        }

        //upload cover image
        $uploadCoverRes = '';
        if (!empty($_FILES['cover']['tmp_name']) && false !== $aremojiModel->strposArr(mime_content_type($_FILES['cover']['tmp_name']), array('jpg', 'png', 'jpeg'))) {
            $uploadCoverRes = $aremojiModel->upload($_FILES['cover']['tmp_name'], $_FILES['cover']['name']);
            if (false === $uploadCoverRes) {
                $uploadCoverRes = '';
            }
        }
        //upload gif cover image
        $uploadCoverGifRes = '';
        if (!empty($_FILES['cover_gif']['tmp_name']) && false !== strpos(mime_content_type($_FILES['cover_gif']['tmp_name']), 'gif')) {
            $uploadCoverGifRes = $aremojiModel->upload($_FILES['cover_gif']['tmp_name'], $_FILES['cover_gif']['name']);
            if (false === $uploadCoverGifRes) {
                $uploadCoverGifRes = '';
            }
        }
        $data['file_url'] = !empty($uploadRes) ? $aremojiModel->getPath() . '/' . $uploadRes : '';
        $data['cover_url'] = !empty($uploadCoverRes) ? $aremojiModel->getPath() . '/' . $uploadCoverRes : '';
        $data['cover_gif_url'] = !empty($uploadCoverGifRes) ? $aremojiModel->getPath() . '/' . $uploadCoverGifRes : '';
        if (!empty($config)) {
            $data['config'] = $config;
            $arrConfig = json_decode($config, true);
            if (!empty($arrConfig['record_info']) && is_array($arrConfig['record_info'])) {
                $aremojiId = array();
                foreach ($arrConfig['record_info'] as $arrConfigV) {
                    if (isset($arrConfigV['id'])) {
                        $aremojiId[] = trim($arrConfigV['id']);
                    }
                }
                if (!empty($aremojiId)) {
                    $data['aremoji_id'] = '|' . implode('|', $aremojiId) . '|';
                }
            }
        }
        if (!isset($data['aremoji_id'])) {
            //如果客户端没有使用素材则默认素材id为1000000000，主要是为了排行使用
            $data['aremoji_id'] = '|' . $this->defaultAremojiId . '|';
        }
        //素材权重
        if (!empty($aremojiId)) {
            $objAremojiAdditionModel = IoCload('models\\AremojiAdditionModel');
            $arrAremojiInfo = $objAremojiAdditionModel->getAremojiInfoById(implode(',', $aremojiId));
            if (count($aremojiId) > 1) {
                //多个素材选取活照片类型为1（图片素材）的数据用其权重
                foreach ($arrAremojiInfo as $arrAremojiInfoV) {
                    if (1 == $arrAremojiInfoV['live_type']) {
                        $weight = isset($arrAremojiInfoV['weight']) ? $arrAremojiInfoV['weight'] : 1;
                        break;
                    }
                }
            } else {
                $weight = $arrAremojiInfo[0]['weight'];
            }
        }
        if (isset($weight)) {
            $data['weight'] = $weight;
        }
        $data['create_time'] = time();

        try {
            foreach (DiyAremojiModel::$type_map as $type_pre => $type) {
                if (strstr($_FILES['video']['type'], $type_pre)) {
                    $data['resource_type'] = $type;
                    break;
                }
            }
            if (!isset($data['resource_type'])) {
                throw new Exception('文件参数错误');
            }

            $data['is_del'] = CoverAssessClient::STATUS_WAITING_ASSESS;
            $blackListModel = new \models\BlackListModel();
            $blackList = $blackListModel->getDiyBlackList();
            if (in_array($data['uid'], $blackList)) {
                $data['is_del'] = CoverAssessClient::STATUS_ASSESSING;
            } elseif (!empty($data['cover_url'])) {
                // 拼接url
                $client = IoCload(CoverAssessClient::class);
                $url = sprintf("%s%s", CoverAssessClient::$bosResourceDomain, $data['cover_url']);
                $data['is_del'] = $client->submit($url);
                $data['ai_assess_information'] = $client->getAssessInformationWithJson();
            }

        } catch (Exception $e) {
            return ErrorCode::returnError('PARAM_ERROR', $e->getMessage());
        }

        $diyAremojiModel = IoCload("models\\DiyAremojiModel");
        $insertRes = $diyAremojiModel->insert($data);
        if (!$insertRes) {
            return ErrorCode::returnError('DB_ERROR', '保存失败');
        }

        $id = $diyAremojiModel->getInsertID();

        $redis = GFunc::getCacheInstance();
        // 将数据加入队列，让异步脚步处理后续操作, 内容审核接口异常
//        if ($data['is_del'] == ContentAssessClient::STATUS_ASSESS_API_EXCEPTION) {
//            $redis->lpushWithRetry(ContentAssessClient::STATUS_ASSESS_API_EXCEPTION_QUE, $id, 3);
//        }
        // 审核中

        if ($data['is_del'] == CoverAssessClient::STATUS_WAITING_ASSESS) {
            $redis->lpushWithRetry(ContentAssessClient::STATUS_WAITING_ASSESS_QUE, json_encode(array(
                "id" => $id,
                "type" => "arEmoji"
            )), 3);
        }

        // 将数据加入到待打水印的队列中
        $redis->lpushWithRetry("WAITING_WATER_MARK_QUE", $id, 3);

        $result['data']->id = $id;
        return Util::returnValue($result);
    }

    /**
     * 取消发布制作的表情
     * @route({"POST", "/canceluploaddiyvideo"})
     * @param({"id", "$._POST.id"}) int AR表情ID
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function cancelUploadDiyVideo($id) {
        //通过bduss去查询用户信息
        if (!(isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) ) {
            return ErrorCode::returnError('PARAM_ERROR', 'ukey error');
        } else if (empty($id)) {
            return ErrorCode::returnError('PARAM_ERROR', 'id error');
        }
        $bdussModel = IoCload("models\\BdussModel");
        $userInfo = $bdussModel->getUserInfoByBduss();
        if (false !== $userInfo) {
            $uid = intval($userInfo['uid']);
        } else {
            return ErrorCode::returnError('BDUSS_ERR', 'bduss error');
        }
        //id支持多个，用逗号分隔
        $arrId = explode(',', $id);
        array_walk($arrId, function(&$item, $key) {
            $item = intval($item);
        });
        $strId = implode(',', $arrId);
        $result = Util::initialClass();
        $diyAremojiModel = IoCload("models\\DiyAremojiModel");
        $updateRes = $diyAremojiModel->update(array('is_del'=>1), array('uid=' . $uid, 'id in (' . $strId . ')'));
        if (0 === $updateRes) {
            return ErrorCode::returnError('DATA_RETURN_NULL', '没有可取消的表情');
        } else if (is_integer($updateRes)) {
            return Util::returnValue($result);
        } else {
            return ErrorCode::returnError('DB_ERROR', '更新失败');
        }
    }

    /**
     * 排行
     * @route({"POST", "/sort"})
     * @param({"id", "$._GET.id"}) int 用户表情ID
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function sort($id) {
        $result = Util::initialClass(false);
        if (empty($id) || !is_numeric($id)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误', true);
        } else {
            $id = intval($id);
        }
        //屏蔽功能
        $arrHandledBlackList = array();
        $uid = 0;
        //get bduss info
        if (isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) {
            $bdussModel = IoCload("models\\BdussModel");
            $userInfo = $bdussModel->getUserInfoByBduss();
            if (false !== $userInfo) {
                $uid = intval($userInfo['uid']);
                //get black list
                $aremojiModel = IoCload("models\\ArEmojiModel");
                $arrHandledBlackList = $aremojiModel->getBlackList($uid);
            }
        }

        //查询表情信息
        $diyAremojiModel = IoCload("models\\DiyAremojiModel");
        $aremojiInfo = $diyAremojiModel->select('*', array("id=$id"));
        if (empty($aremojiInfo)) {
            return ErrorCode::returnError('DATA_FORMAT_ERROR', '查询不到信息', true);
        } else {
            $aremojiId = 0;
            if (empty($aremojiInfo[0]['config'])) {
                $aremojiId = $this->defaultAremojiId;
            } else {
                $arrConfig = json_decode($aremojiInfo[0]['config'], true);
                if (false === $arrConfig) {
                    $aremojiId = $this->defaultAremojiId;
                } else {
                    $arrLiveAremojiId = array();
                    $arrCommonAremojiId = array();
                    foreach ($arrConfig['record_info'] as $arrConfigK => $arrConfigV) {
                        if (1 == $arrConfigV['live_type'] && $arrConfigV['id'] > 0) {//活照片 + 普通 或者只用活照片
                            array_unshift($arrLiveAremojiId, $arrConfigV['id']);
                            break;
                        } else if (1 == $arrConfigV['live_type']) {//使用自己的照片
                            $arrLiveAremojiId[] = -100;
                        } else if (2 == $arrConfigV['live_type']) {
                            $arrCommonAremojiId[] = $arrConfigV['id'];
                        }
                    }
                    /*
                     * 如果用户传了普通素材和活照片素材，则按照活照片素材id进行排序
                     * 如果用户用自己的照片并且上传了普通素材则按照普通素id材排序
                     * 如果用户只用了自己的照片，则按照排序id为-100进行排序
                     * 如果用户什么都没有用，则按照id为1000000000进行排序
                     */
                    if (!empty($arrLiveAremojiId) && $arrLiveAremojiId[0] > 0) {//活照片 + 普通 或者只用活照片
                        $aremojiId = intval($arrLiveAremojiId[0]);
                    } else if (!empty($arrCommonAremojiId)) {
                        $aremojiId = intval($arrCommonAremojiId[0]);
                    } else if (!empty($arrLiveAremojiId)) {
                        $aremojiId = intval($arrLiveAremojiId[0]);
                    } else {
                        $aremojiId = $this->defaultAremojiId;
                    }
                }
            }
        }

        $list = $diyAremojiModel->select('*', array('aremoji_id like "%|' . $aremojiId . '|%"', 'is_del=0'), null, 'order by (month_praise + artificial_praise) desc');
        if (false === $list || null === $list) {
            return ErrorCode::returnError('DB_ERROR', '查询失败', true);
        }
        $entityProcessor = new DefaultEntityProcessor();
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        foreach ($list as $listK => $listV) {
            //屏蔽功能
            if (!empty($arrHandledBlackList['user']) && array_key_exists($listV['uid'], $arrHandledBlackList['user'])) {
                continue;
            } else if (!empty($arrHandledBlackList['aremoji']) && array_key_exists($listV['id'], $arrHandledBlackList['aremoji'])) {
                continue;
            }
            if ($listV['uid'] == $uid) {
                $listV['is_hide'] = 1;
            } else {
                $listV['is_hide'] = 0;
            }
            $listV['uid'] = trim(bd_B64_Encode($listV['uid'], 0));//兼容加密的bug，去掉多余的字符\u0000
            $listV['file_url_watermark'] = sprintf("%s/%s", $bosHost, empty($listV['file_url_watermark'])?$listV['file_url']:$listV['file_url_watermark']);
            $listV['file_url'] = !empty($listV['file_url']) ? $bosHost . '/' . $listV['file_url'] : '';
            $listV['cover_url'] = !empty($listV['cover_url']) ? $bosHost . '/' . $listV['cover_url'] : '';
            $listV['cover_gif_url'] =  !empty($listV['cover_gif_url']) ? $bosHost . '/' . $listV['cover_gif_url'] : '';
            $listV['aremoji_id'] = !empty($listV['aremoji_id']) ? explode('|', trim($listV['aremoji_id'], '|')) : array();
            $listV['praise'] = $listV['praise'] + $listV['artificial_praise'];
            $result['data'][] = $entityProcessor->processEntity($listV, 'entity\DiyAremojiEntity');
        }
        return Util::returnValue($result, false);
    }

    /**
     * 获取AR表情播放地址
     * @route({"POST", "/getMusic"})
     * @param({"musicId", "$._POST.music_id"}) tsid 可以为多个
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getMusic($musicId) {
        $result = Util::initialClass();
        $objRedis = IoCload('utils\\KsarchRedis');
        if (!empty($musicId)) {
            $musicId = explode(',', $musicId);
            $data = array();
            foreach ($musicId as $musicIdK => $musicIdV) {
                if (!is_numeric($musicIdV)) {
                    return ErrorCode::returnError('PARAM_ERROR', '参数错误');
                }
                $key = md5(self::$cachePre . __FUNCTION__ . '_' . $musicIdV);
                $tmpData = $objRedis->get($key);
                $ttl = $objRedis->ttl($key);
                if (false === $tmpData || null === $tmpData) {
                    $objAremojiMusicModel = IoCload('models\\AremojiMusicModel');
                    $musicInfo = $objAremojiMusicModel->select('*', array('id=' . intval($musicIdV)));
                    if (1 == $musicInfo[0]['cooperation']) {
                        $tmpData = array(
                            'id' => $musicIdV,
                            'path' => '',
                            'cooperation' => 1,
                        );
//                        跟太合音乐合作到期，关闭回调接口 2019-07-18
//                        if (!empty($musicInfo[0]['tsid']) && !empty($musicInfo[0]['resource_id'])) { //太合音乐
//                            $api = IoCload("utils\\ThApi");
//                            $api->callDMH('/OPENAPI/setSpUserBizID.json',array('bizId'=> 29));
//                            $info = $api->callDMH('/TRACKSHORT/selectShortRate.json',array('TSID'=> $musicInfo[0]['tsid'], 'resourceId'=>$musicInfo[0]['resource_id'], 'rate'=>128));
//                            if (!empty($info['path'])) {
//                                $tmpData['path'] = Util::myUrlEncode($info['path']);
//                                if (!empty($info['expireTime']) && is_numeric($info['expireTime'])) {
//                                    $expireTime = $info['expireTime'] - time();
//                                    if ($expireTime > 180) {
//                                        $objRedis->set($key, $tmpData, $expireTime - 180);
//                                    }
//                                }
//                            }
//                        }
                    } else {
                        $tmpData = array(
                            'id' => $musicIdV,
                            'path' => '',
                            'cooperation' => 2,
                        );
                        if (!empty($musicInfo[0]['path'])) {
                            $bosHost = GFunc::getGlobalConf('bos_host_https');
                            $bucket = 'imeres';
                            $tmpData['path'] = Util::myUrlEncode($bosHost . '/' . $bucket . '/' . $musicInfo[0]['path']);
                            $objRedis->set($key, $tmpData, GFunc::getCacheTime('2hours'));
                        }
                    }
                }
                if ('taihetest' == $_POST['log']) {
                    $expireTime = isset($expireTime) ? $expireTime : -1;
                    if (isset($expireTime)) {
                        $tag = 'start';
                    } else {
                        $tag = '----';
                    }
                    $errorMsg = $_POST['log'] . '|' . $tag . '|musicId:' . $musicIdV .  '|TTL:' . $ttl . '|expireTime:' . $expireTime . '|time:' . time();
                    LogHelper::getInstance()->Log($errorMsg);
                }
                $data[] = $tmpData;
            }
        } else {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }

        $result['data']= $data;

        return $result;
    }

    /**
     * @route({"POST", "/taiheCallback"})
     *
     * @param({"data", "$._POST.data"})
     * @param({"model", "$._GET.model"})
     * @param({"platform", "$._GET.platform"})
     * @param({"rom", "$._GET.rom"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function TaiheCallback($data, $model, $platform, $rom) {
        $result = Util::initialClass();
//        跟太合音乐合作到期，关闭回调接口 2019-07-18
//        $arrData = json_decode($data, true);
//        if (empty($arrData['musicInfo'])) {
//            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
//        }
//        //apiinfo
//        $country = '';
//        $province = '';
//        if (!empty($arrData['apiInfo'])) {
//            $arrApiInfo = json_decode(B64Decoder::decode($arrData['apiInfo'], 0), true);
//            $country = isset($arrApiInfo['country']) ? $arrApiInfo['country'] : '';
//            $province = isset($arrApiInfo['province']) ? $arrApiInfo['province'] : '';
//        }
//        //终端版本号
//        if (0 === strpos($platform, 'i')) {
//            $os = 'ios';
//        } else if (false !== strpos($platform, 'a')) {
//            $os = 'android';
//        } else {
//            $os = '';
//        }
//        $terminalType = $os . ' ' . $rom;
//        $params = array();
//        foreach ($arrData['musicInfo'] as $musicInfoK => $musicInfoV) {
//            //根据ID获取音乐信息
//            $objAremojiMusicModel = IoCload('models\\AremojiMusicModel');
//            $musicInfo = $objAremojiMusicModel->select('*', array('id=' . intval($musicInfoV['musicId'])));
//            if (isset($musicInfo[0]['cooperation']) && 1 == $musicInfo[0]['cooperation'] && !empty($musicInfo[0]['tsid']) && !empty($musicInfo[0]['resource_id'])) {
//                $params[] = array(
//                    'assetId' => $musicInfo[0]['tsid'],
//                    'rate' => 128,
//                    'playTime' => $musicInfoV['playTime'],
//                    'playType' => 4,
//                    'deviceModel' => $model,
//                    'useTime' => date('Y-m-d H:i:s', $musicInfoV['useTime']),
//                    'country' => $country,
//                    'province' => $province,
//                    'terminalType' => $terminalType,
//                    'resourceId' => $musicInfo[0]['resource_id'],
//                );
//            }
//        }
//        if (!empty($params)) {
//            $api = IoCload("utils\\ThApi");
//            $api->callDMH('/OPENAPI/setSpUserBizID.json',array('bizId'=> 29));
//            $info = $api->callDMH('/APILOGPUSH/uploadTrackPlay.json', array('log' => json_encode($params)));
//            if (false === $info) {
//                return ErrorCode::returnError('API_ACCESS_ERR', '日志回传失败');
//            }
//        } else {
//            return ErrorCode::returnError('NO_NEED_LOG', '不需要回传日志');
//        }
//
        return Util::returnValue($result);
    }

    /**
     * ar表情素材下载
     * @route({"GET", "/download"})
     * http://agroup.baidu.com/inputserver/md/article/1297860
     * @param({"id", "$._GET.id"})
     * @param({"phoneType", "$._GET.phoneType"})
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     */
    public function download($id=0, $phoneType=1, &$status='', &$location='') {
        $id = intval($id);
        $objArEmojiModel = IoCload('models\\ArEmojiModel');
        $key = md5(self::$cachePre . __FUNCTION__ . '_' . $id . $phoneType);
        $objRedis = IoCload('utils\\KsarchRedis');
        $aremojiInfo = $objRedis->get($key);
        if (false === $aremojiInfo || null === $aremojiInfo) {
            $aremojiInfo = $objArEmojiModel->select('*', array('id='=>$id));
            $objRedis->set($key, $aremojiInfo, GFunc::getCacheTime('2hours'));
        }

        if (!empty($aremojiInfo[0])) {
            $file = $objArEmojiModel->getFile($aremojiInfo[0], $phoneType);
            $bosHost = GFunc::getGlobalConf('bos_host_https');
            $url = $bosHost . '/' . $this->bosLocation . '/' . $file;
            $status = "302 Found";
            $location = "Location: ".$url;
        } else {
            $status = '404 Not Found';
        }

        return ;
    }

    /**
     * ar表情举报
     * @route({"POST", "/report"})
     *
     * @param({"id", "$._POST.id"})
     * @param({"cuid", "$._GET.cuid"})
     * @return({"body"})
     */
    public function report($id=0, $cuid='') {
        if(empty($id) || !isset($_POST["type"]) || empty($_POST["type"])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR);
        }

        $message = json_encode(array(
            'object_id'=>$id,
            'cuid'=>$cuid,
            'comment'=>isset($_POST['comment'])&&!empty($_POST["comment"]) ? $_POST["comment"] : "",
            'type'=>$_POST['type'],
            'object_type'=>\models\ResourceReportModel::OBJECT_TYPE_AR_EMOJI,
            'created_time'=>time(),
            'updated_time'=>time()
        ));

        $redis = GFunc::getCacheInstance();
        // 将数据加入到待打水印的队列中
        $redis->lpushWithRetry("V5_DIY_RESOURCE_REPORT_QUEUE_CACHE", $message, 3);

        $result = Util::initialClass();
        return Util::returnValue($result);
    }

    /**
     * 客户端上传AR表情并生成图片
     * http://agroup.baidu.com/inputserver/md/article/1334166
     * @route({"POST", "/uploadAndGeneratePic"})
     * @param({"activityId", "$._POST.activityID"}) 活动标识
     *
     * @return({"body"})
     */
    public function uploadAndGeneratePic($activityId='') {
        $result = Util::initialClass();
        //上传用户制作的内容
        $aremojiModel = IoCload('models\\ArEmojiModel');
        if (!empty($_FILES['result']['tmp_name'])) {
            $uploadVideoRes = $aremojiModel->upload($_FILES['result']['tmp_name'], $_FILES['result']['name']);
            if (false === $uploadVideoRes) {
                return ErrorCode::returnError('UPLOAD_ERROR', 'upload file error');
            }
        }
        $token = md5(uniqid(__CLASS__ . __FUNCTION__ . rand(1, 100000)));
        switch ($activityId) {
            case '201801'://年度表情包
                $res = $aremojiModel->springActivity($token, $uploadVideoRes);
                if (false === $res) {
                    return ErrorCode::returnError('UPLOAD_ERROR', 'upload file error');
                }
                $result['data']->token = $token;
                break;
            case '201901': //4.22地球日活动
                $res = $aremojiModel->springActivity($token, $uploadVideoRes, 'earth_activity');
                if (false === $res) {
                    return ErrorCode::returnError('UPLOAD_ERROR', 'upload file error');
                }
                $result['data']->token = $token;
                break;
            default: //暴漫活动
                //参数判断
                $allPathArr = array();
                for ($i=1; $i<6; $i++) {
                    if (isset($_FILES['image' . $i])) {
                        $allPathArr[] = $_FILES['image' . $i]['tmp_name'];
                        $objImagic = Imagicks::open($_FILES['image' . $i]['tmp_name']);
                        $objImagic->resize(false, 136)->saveTo($_FILES['image' . $i]['tmp_name']);
                        $objImagic->destroy();
                    }
                }
                //从客户端传过来的图片中随机取1-5张
                $rand = rand(1, count($allPathArr));
                $randKeys = array_rand($allPathArr, $rand);
                $pathArr = array();
                if (is_numeric($randKeys)) {
                    $pathArr[] = $allPathArr[$randKeys];
                } else {
                    foreach ($randKeys as $randKeyV) {
                        $pathArr[] = $allPathArr[$randKeyV];
                    }
                }
                //随机生成n张图片，并把传过来的图片插入其中,保证生成的一共是16张图片
                $totalArr = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16);
                shuffle($totalArr);
                $selectArr = array_splice($totalArr, 0, 16 - count($pathArr));
                $pathArr = array_merge($pathArr, $selectArr);
                shuffle($pathArr);
                try {
                    $path = ROOT_PATH . '/webroot/v5/static/img/kuang.png';
                    //分享图16宫格
                    $objImagic = Imagicks::open($path);
                    //透明层
                    $objTransparent = Imagicks::create('', 705, 705, '', 'png', false);
                    $objTransparent->getImage()->setImageOpacity(0.0);
                    for ($i=0; $i<4; $i++) {
                        for ($j=0; $j<4; $j++) {
                            $waterPath = $pathArr[$i * 4 + $j];
                            if (is_numeric($waterPath)) {
                                $waterPath = ROOT_PATH . '/webroot/v5/static/img/' . $waterPath . '.png';
                                $startX = $i * 156 + 3 * $i* 2 + 19 * $i + 3;
                                $startY = $j * 156 + 3 * $j* 2 + 19 * $j + 3;
                            } else {
                                //裁切完客户端传上来的图并且居中显示
                                $startX = $i * 156 + 3 * $i* 2 + 19 * $i + 3 + 20;
                                $startY = $j * 156 + 3 * $j* 2 + 19 * $j + 11;
                                $kuangResult = ROOT_PATH . '/webroot/v5/static/img/result_kuang.png';
                                $startResultX = 181 * $i;
                                $startResultY = 181 * $j;
                                //结果页图片
                                $objTransparent->watermark($kuangResult, $startResultX, $startResultY);
                            }
                            //分享页图片
                            $objImagic->watermark($waterPath, $startX, $startY);
                        }
                    }
                    //上传bos
                    $objBos = new Bos($this->bucket, $this->strPath);
                    $data = $objImagic->getImage()->getImageBlob();
                    $objImagic->destroy();
                    //上传结果页图片
                    $resultName = date('Y-m-d') . '/baoman' . md5(microtime() . rand(0, 10000)) . $token . '.png';
                    $uploadRes = $objBos->putObjectFromString($resultName, $data, array(BosOptions::CONTENT_TYPE => 'image/png'));
                    //生成并上传分享图
                    $sharePath = ROOT_PATH . '/webroot/v5/static/img/16-palaces-share-background.png';
                    $objShareImagic = Imagicks::open($sharePath);
                    $objShareImagic->watermarkFromBlob($data, 18, 426);
                    $shareData = $objShareImagic->getImage()->getImageBlob();
                    $objShareImagic->destroy();
                    $shareName = date('Y-m-d') . '/baomanshare' . md5(microtime() . rand(0, 10000)) . $token . '.png';
                    $shareUploadRes = $objBos->putObjectFromString($shareName, $shareData, array(BosOptions::CONTENT_TYPE => 'image/png'));
                    //上传透明图层
                    $data = $objTransparent->getImage()->getImageBlob();
                    $objTransparent->destroy();
                    $transparentName = date('Y-m-d') . '/baomantransparent' . md5(microtime() . rand(0, 10000)) . $token . '.png';
                    $uploadTransparentRes = $objBos->putObjectFromString($transparentName, $data, array(BosOptions::CONTENT_TYPE => 'image/png'));
                    if (1 != $uploadRes['status'] || 1 != $shareUploadRes['status'] || 1 != $uploadTransparentRes['status']) {
                        return ErrorCode::returnError('UPLOAD_ERROR', 'upload file error');
                    } else {
                        $redis = GFunc::getCacheInstance();
                        $redis->hSet($token, 'origin', $this->strPath . '/' . $resultName);
                        $redis->hSet($token, 'share', $this->strPath . '/' . $shareName);
                        $redis->hSet($token, 'transparent', $this->strPath . '/' . $transparentName);
                        $result['data']->token = $token;
                        //兼容8.4版本，新春运营活动
                        $data = array(
                            'token' => $token,
                            'url' => sprintf("%s/%s", $aremojiModel->getPath(), $uploadVideoRes)
                        );
                        $pushRes = $redis->rpush(self::$cachePre . 'videotogif', json_encode($data));
                    }
                } catch(Exception $e) {
                    return ErrorCode::returnError('GENERATE_PIC_ERROR', $e->getMessage());
                }

        }
        return Util::returnValue($result);
    }

    /**
     * 添加用户屏蔽信息
     * @route({"POST", "/addBlackList"})
     * @param({"id", "$._POST.id"}) int 用户或者ar表情id
     * @param({"type", "$._POST.type"}) int 屏蔽类型，1用户 2ar表情
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function addBlackList($id, $type) {
        $result = Util::initialClass();
        $row = array();
        $type = intval($type);
        //通过bduss去查询用户信息
        if (!(isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error']))) {
            return ErrorCode::returnError('PARAM_ERROR', 'ukey error');
        } else if (empty($id) || empty($type) || !in_array($type, array(1, 2))) {
            return ErrorCode::returnError('PARAM_ERROR', 'id or type error');
        }
        $aremojiModel = IoCload("models\\ArEmojiModel");
        $bdussModel = IoCload("models\\BdussModel");
        $userInfo = $bdussModel->getUserInfoByBduss();
        if (false !== $userInfo) {
            $row['user_id'] = $userInfo['uid'];
        } else {
            return ErrorCode::returnError('BDUSS_ERR', 'bduss error');
        }
        if (1 == $type) {
            $id = bd_B64_Decode($id, 0);
        } else {
            $id = intval($id);
        }
        $row['blacklist_id'] = $id;
        $row['type'] = $type;
        $row['create_time'] = date('Y-m-d H:i:s');
        $objDiyAremojiBlacklist = IoCload("models\\DiyAremojiBlacklistModel");
        $insertRes = $objDiyAremojiBlacklist->insert($row);
        if (!$insertRes) {
            return ErrorCode::returnError('DB_ERROR', '保存失败');
        } else {
            $key = md5(self::$cachePre . 'v1_aremoji_blacklist'. $userInfo['uid']);
            GFunc::cacheDel($key, true);
        }

        return Util::returnValue($result);
    }

    /**
     * 获取客户端不支持该素材的原因
     * @route({"POST", "/getTips"})
     * @param({"ids", "$._POST.ids"}) int 用户或者ar表情id
     * @param({"phoneType", "$._POST.phonetype"}) 机型类型
     * @param({"verName", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getTips($ids, $phoneType, $verName, $plt) {
        $result = Util::initialClass();
        if (empty($ids)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        $arrIds = explode(',', $ids);
        foreach ($arrIds as $arrIdsK => $arrIdV) {
            $arrIds[$arrIdsK] = intval($arrIdV);
        }
        $ids = implode(',', $arrIds);
        $key = md5(self::$cachePre . __CLASS__ . __FUNCTION__ . 'aremoji_tips' . $ids . $phoneType . $verName . $plt);
        $list = GFunc::cacheGet($key);
        if (false === $list || null === $list) {
            $objArEmojiModel = IoCload('models\\ArEmojiModel');
            $list = $objArEmojiModel->getAremojiInfoByIds($ids);
            GFunc::cacheSet($key, $list, GFunc::getCacheTime('10mins'));
        }
        if (empty($list)) {
            return ErrorCode::returnError('EMPTY_RESULT', '查询结果为空');
        } else {
            //ai输入法和主线输入法版本号不同
            if (in_array($plt, array('a11', 'i9', 'i10'))) {
                $maxVersionFields = 'ai_max_version';
                $minVersionFields = 'ai_min_version';
            } else {
                $maxVersionFields = 'max_version';
                $minVersionFields = 'min_version';
            }
            foreach ($list as $listK => $listV) {
                if (1 == $phoneType && 3 == $listV['for_phone_type']) {
                    return ErrorCode::returnError('NOT_SUPPORT_PHONETYPE', '当前机型暂不支持该表情');
                } else if (!(version_compare($listV[$minVersionFields], $verName, '<=')
                    && (empty($listV[$maxVersionFields]) || version_compare($listV[$maxVersionFields], $verName, '>=')))) {
                    return ErrorCode::returnError('CLIENT_VERSION_ERROR', '请升级客户端版本');
                } else if (100 != $listV['status']) {
                    return ErrorCode::returnError('AREMOJI_OFFLINE', '该表情已下线');
                }
            }
        }

        return Util::returnValue($result);
    }


    /**
     * AR表情列表，仅供季爱军使用
     * @route({"GET", "/getAllList"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getAllList() {
        $objArEmojiModel = IoCload('models\\ArEmojiModel');
        $list = $objArEmojiModel->select('*');
        return $list;
    }
}