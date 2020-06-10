<?php
use models\ActivityModel;
use models\ActivityFilter;
use models\ActivitySkinCoupon;
use models\ActivitySkinConfig;
use models\EcommerceWhiteDataModel;
use tinyESB\util\IoCFactory;
use utils\ErrorCode;
use utils\GFunc;
use EntitySearch\EntityProcessor\DefaultEntityProcessor;
use utils\Util;


/**
 * 
 * 输入法运营活动
 * @path("/activity/")
 *
 */

class Activity
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
    private $from;

    /**
     * @var string
     * 系统ROM版本
     */
    public $rom;
    
    /**
     * cache pre
     * @var string
     */
    private static $cachePre = 'activity_';
    
    //Edit by fanwenli on 2018-12-10, get all result from Dbx
    public $arrActivityFromDbx = array();

    public $weatherAppPackage = array(
            'com.tencent.xin', //ios wechat
            'com.tencent.mqq', // ios qq
            'com.tencent.mm', // android wechat
            'com.tencent.mobileqq', // android qq
            'com.android.browser',
            'com.tencent.mtt',
            'com.UCMobile',
            'com.vivo.browser',
            'com.huawei.browser',
            'com.coloros.browser',
            'com.qihoo.browser',
            'com.heytap.browser',
            'sogou.mobile.explorer',
            'com.xp.browser',
            'com.estrongs.android.pop',
            'com.ucmobile.lite',
            'cn.nubia.browser',
            'sogou.mobile.explorer.online',
            'com.baidu.browser.apps',
            'com.zui.browser',
            'com.qihoo.contents',
            'com.nearme.browser',
            'com.sogou.sgsa.novel',
            'com.browser2345',
            'com.mmbox.xbrowser',
            'com.nbc.browser',
            'com.ijinshan.browser_fast',
            'com.ume.browser',
            'com.browser_llqhz',
            'com.allzeus.browser',
            'mixiaba.com.Browser',
            'com.UCMobile.intl',
            'com.lenovo.browser',
            'com.mmbox.xbrowser.pro',
            'com.qwh.grapebrowser',
            'sogou.mobile.explorer.fangbei',
            'com.z28j.feel',
            'com.android.mobilebrowser',
            'com.prize.browser',
            'com.oupeng.mini.android',
            'sogou.mobile.explorer.OS360',
            'com.mx.browser',
            'com.eebbk.browser',
            'sogou.mobile.explorer.shenzhou',
            'com.baidu.searchbox',
            'com.apple.mobilesafari',
            'com.ucweb.iphone.lowversion ',
            'com.tencent.mttlite',
            'com.quark.browser ',
            'com.sogou.SogouExplorerMobile',
            'com.baidu.BaiduMobile',
            'com.google.chrome.ios',
            'com.sogou.sogousearch',
            'com.app.safebrowser ',
            'org.mozilla.ios.Firefox',
            'com.ucweb.iphone.pro',
            'com.mx.MxBrowser-iPhone',
            'com.oupeng.mini.x',
            );


    /***
     * 构造函数
     * @return void
     */
    public function  __construct()
    {
        $this->plt = isset($_GET['platform']) ? $this->getPhoneOS($_GET['platform']) : '';
        $this->version = isset($_GET['version']) ? $_GET['version'] : '';
        $this->pltcode =  isset($_GET['platform']) ?$_GET['platform']: '';
        $this->from =  isset($_GET['from']) ?$_GET['from']: '';
        $this->rom = isset($_GET['rom']) ? $_GET['rom'] : '';
        $this->imei = isset($_GET['cuid']) ? $_GET['cuid'] : '';
    }


    /**
     *
     * 运营活动列表
     *
     * @route({"GET","/list"})
     * @param({"token", "$._GET.token"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"body"}){
                [
                    {
                    "id": "1458115682",
                    "name": "servertest",
                    "desc": "servertest",
                    "icon_url": "http://res.mi.baidu.com/imeres/emot-pack-test/activityicon_1458115682.pic?authorization=bce-auth-v1%2F149796218973496bb2973cf92e75e140%2F2016-03-16T08%3A08%3A04Z%2F-1%2F%2F45f9f6a1c87dee384370ed60c36d36e234a9fb9dc6587390710892764b852d17",
                    "icon480": "",
                    "icon720": "",
                    "icon1080": "",
                    "icon2x": "",
                    "icon3x": "",
                    "activity_url": "http://baidu.applab.com.cn/baiduywz/index.php",
                    "begin": "1458057600",
                    "end": "1459353600",
                    "mdate": "1458115786",
                    "show_type": 1,
                    "candidate_word": "",
                    "tip_title": "",
                    "tip_content": "",
                    "background": "",
                    "background480": "",
                    "background720": "",
                    "background1080": "",
                    "background2x": "",
                    "background3x": "",
                    "scale_string": "",
                    "scale_string480": "",
                    "scale_string720": "",
                    "scale_string1080": "",
                    "scale_string2x": "",
                    "scale_string3x": "",
                    "draw_type": "0",
                    "candiate_title": "",
                    "logo": "",
                    "logo480": "",
                    "logo720": "",
                    "logo1080": "",
                    "logo2x": "",
                    "logo3x": "",
                    "is_color_wash": "0",
                    "action_type": "web",
                    "tab_address": "vocabulary",
                    "web_address": "http://www.baidu.com",
                    "sort": "0",
                    "tab_address_for_lite": "xxxx",
                    }
                ]
    		}
     * */


    public function getlist($token='')
    {
        $activies = array(
            'activities' => array(),
            'skin_activities' => array(),
        );
        $activityModel = IoCload("models\\ActivityModel");
        $activityFilterModel =IoCload("models\\ActivityFilter");
        //获取运营活动开关
        $cacheKey = self::$cachePre . __Class__ . __FUNCTION__ . '_' . '_cachekey';
        $activitySwitchRes = Util::ralGetContent('/res/json/input/r/online/activity_switch/?onlycontent=1', $cacheKey);
        if (is_array($activitySwitchRes)) {
            $filterRes = true;
            $conditionFilter = IoCload("utils\\ConditionFilter");
            foreach ($activitySwitchRes as $activitySwitchResK => $activitySwitchResV) {
                if (0 == $activitySwitchResV['value'] && $conditionFilter->filter($activitySwitchResV['filter_conditions'])) {
                    $filterRes = false;
                    break;
                }
            }
            if (false === $filterRes) {
                return $activies;
            }
        }
        
        //客户端相关信息
        $client_info = array();
        $client_info['platform'] = $this->plt;
        $client_info['input_version'] = str_replace('-', '.', $this->version);
        $client_info['channel'] = $this->from;
        //平台号
        $client_info['platcode'] = $this->pltcode;

        $list = $activityModel->getActivityList();
        
        //Edit by fanwenli on 2018-12-10, get all result from Dbx
        $this->arrActivityFromDbx = $list;

        $filter_ids = array();
        foreach ($list as $key => $value) {
            if(0 != intval($value['filter_id'])){
                $filter_ids[] = intval($value['filter_id']);
            }
        }
        $support_filter_ids = $activityFilterModel->filter(array_values(array_unique($filter_ids)));
        $priority_list = $activityModel->getPriorityInfo();

        $white_list = $activityModel->getWhiteList();

        //分别存放设置优先级的和没有设置优先级的列表
        $first_array = array();
        $second_array = array();
        $priorityActivity = array();
        $entity = array();
        $skinEntity = array();
        $entityProcessor = new DefaultEntityProcessor();

        $intCount = count($list);
        for($i=0; $i<$intCount; $i++){
            $list[$i]['skin_ids'] = empty($list[$i]['skin_ids'])?array():explode("|", $list[$i]['skin_ids']);
            $list[$i]['valid_skin_token'] = empty($list[$i]['valid_skin_token'])?array():explode("|", $list[$i]['valid_skin_token']);
            $list[$i]['app_package'] = empty($list[$i]['app_package'])?array():explode("|", $list[$i]['app_package']);
            $list[$i]["global_id"] =  sprintf("activity#%d", $list[$i]['id']);
            $list[$i]["max_show_times"] =  intval($list[$i]["max_show_times"]);
            //雅诗兰黛活动 id 1441795351 ios 9-0 不下发 零时修改活动结束后此代码可以删除
            if( ('1441795351' == $list[$i]['id']) && ('9-0' ===$this->rom) ){
                continue;
            }

            //判断是否支持
            if (! $this->getIsSupport($client_info, $list[$i], $support_filter_ids)) {
                continue;
            }

            //white check
            if (isset($list[$i]['publish_type'])) {
                if ('white' === $list[$i]['publish_type']) {
                    if (! in_array($this->imei, $white_list)) {
                        continue;
                    }
                }
            }
            $list[$i]['activity_url'] = $list[$i]['url'];
            $pos = $this->getPriorityPos($priority_list, $list[$i]['id']);
            $list[$i]['pos'] = $pos;
            $list[$i]['desc'] = $list[$i]['description'];
            $list[$i]['icon_url'] = $list[$i]['icon'];
            $list[$i]['icon_url_x'] = $list[$i]['icon_x'];
            //皮肤运营活动
            if (!empty($list[$i]['skin_token'])) {
                $arrSkinToken = explode('|', $list[$i]['skin_token']);
                $list[$i]['skin_token'] = $arrSkinToken;
                $skinEntity = $entityProcessor->processEntity($list[$i], "entity\\ActivityEntity");
                $activies['skin_activities'][] = $skinEntity;
                if (in_array($token, $arrSkinToken)) {
                    $list[$i]['activity_url'] .= '?token=' . $token;
                } else {
                    continue;
                }
            } else {
                $list[$i]['skin_token'] = array();
            }
            
            //判断优先级
            if (!empty($list[$i]['sort']) && array_key_exists($list[$i]['sort'], $priorityActivity)) {
                if ($list[$i]['logo_sort_priority'] > $priorityActivity[$list[$i]['sort']]['logo_sort_priority']) {
                    $unsetKey = $priorityActivity[$list[$i]['sort']]['id'];
                    if (isset($first_array[$unsetKey])) {
                        unset($first_array[$unsetKey]);
                    }
                    
                    if (isset($second_array[$unsetKey])) {
                        unset($second_array[$unsetKey]);
                    }
                    $priorityActivity[$list[$i]['sort']] = array(
                        'id' => $list[$i]['id'],
                        'logo_sort_priority' => $list[$i]['logo_sort_priority'],
                    );
                } else {
                    continue;
                }
            } else {
                $priorityActivity[$list[$i]['sort']] = array(
                    'id' => $list[$i]['id'],
                    'logo_sort_priority' => $list[$i]['logo_sort_priority'],
                );
            }
            //框属性
            if (!empty($list[$i]['ctrid'])) {
                $list[$i]['ctrid'] = explode('|', $list[$i]['ctrid']);
            } else {
                $list[$i]['ctrid'] = array();
            }

            $entity = $entityProcessor->processEntity($list[$i], "entity\\ActivityEntity");

            if ('0' === $pos) {
                $second_array[$entity->id] = $entity;
            } else {
                $first_array[$entity->id] = $entity;
            }
        }
        
        //优先级高的pos小
        usort($first_array, function($a, $b) {
            if ($a['pos'] == $b['pos']) {
                return 0;
            }
            return ($a['pos'] > $b['pos']) ? 1 : -1;
        });
        $activies['activities'] = array_merge($first_array, $second_array);

        //去掉排序字段及发布类型字段
        $intCount = count($activies['activities']);
        for($i = 0; $i < $intCount; $i++) {
            unset($activies['activities'][$i]->pos);
        }
        
        return $activies;
    }

    /**
     * 手机是否支持
     * @param
     * 		参数名称：client_info
     *      是否必须：是
     *      参数说明：客户端相关信息
     *
     * @param
     * 		参数名称：activity
     *      是否必须：是
     *      参数说明：运营活动相关信息
     *
     * @param
     * 		参数名称：support_filter_ids
     *      是否必须：是
     *      参数说明：支持的filter id
     *
     *
     * @return true | false
     */
    public function getIsSupport($client_info, $activity, $support_filter_ids)
    {
        if( 0 != $activity['filter_id'] && !in_array(intval($activity['filter_id']), $support_filter_ids) ){
            return false;
        }

        $activity_platform = explode('|', $activity['platform']);
        if( !(('all' === $activity['platform']) || (in_array($client_info['platform'], $activity_platform))) )
        {
            return false;
        }

        $strVersion = str_replace('-', '.', $this->version);
        $strPregVersionPattern = $this->getPattern($activity['input_version']);
        if( !( ('all' === $activity['input_version']) || preg_match("#" . $strPregVersionPattern . "#", $strVersion) ) )
        {
            return false;
        }

        $intVersion = $this->getVersionIntValue($strVersion, 2);
        if ( (('' !== $activity['min_version']) && ($intVersion < $this->getVersionIntValue($activity['min_version']))) ||
            (('' !== $activity['max_version']) && ($intVersion > $this->getVersionIntValue($activity['max_version']))) ){
            return false;
        }

        $activity_channel = explode('|', $activity['channel']);
        if( !(('all' === $activity['channel']) || (in_array($client_info['channel'], $activity_channel))) )
        {
            return false;
        }

        //排除平台号
        if ( isset($activity['eplatcode']) && ('' !== $activity['eplatcode']) ){
            $preg_pattern = $this->getPattern($activity['eplatcode']);
            if ( preg_match("#" . $preg_pattern . "#", $client_info['platcode']) ){
                return false;
            }
        }

        //推送平台号
        if ( isset($activity['platcode']) && ('all' !== $activity['platcode']) ){
            $preg_pattern = $this->getPattern($activity['platcode']);
            if ( !preg_match("#" . $preg_pattern . "#", $client_info['platcode']) ){
                return false;
            }
        }

        return true;
    }


    /**
     * 返回输入法版本数值
     * 如5.1.1.5 5010105
     *
     * @param
     * 		参数名称：strVersionName
     *      是否必须：是
     *      参数说明：version name
     *
     * @param
     * 		参数名称：intDigit
     *      是否必须：是
     *      参数说明：位数
     *
     *
     * @return string
     */
    public function getVersionIntValue($strVersionName, $intDigit = 4){
        $strVersionName = str_replace('-', '.', $strVersionName);

        $intVal = 0;
        $arrVersonDigit = explode('.', $strVersionName);
        for ($i = 0; $i < $intDigit; $i++){
            $intDigitVal = 0;
            switch ($i){
                case 0:
                    $intDigitVal = intval($arrVersonDigit[$i]) * 1000000;
                    break;
                case 1:
                    $intDigitVal = intval($arrVersonDigit[$i]) * 10000;
                    break;
                case 2:
                    $intDigitVal = intval($arrVersonDigit[$i]) * 100;
                    break;
                case 3:
                    $intDigitVal = intval($arrVersonDigit[$i]);
                    break;
                default:
                    break;
            }

            $intVal = $intVal + $intDigitVal;
        }

        return $intVal;
    }

     /**
     * 获取运营活动优先级
     *
     * @param
     * 		参数名称：$priority_list
     *      是否必须：是
     *      参数说明：运营活动优先级列表
     *
     * @param
     * 		参数名称：$id
     *      是否必须：是
     *      参数说明：运营活动id
     *
     * @return int 优先级位置从1开始 0表示没有设置优先级
     */
    public function getPriorityPos($priority_list, $id)
    {
        if(!isset($priority_list))
        {
            return '0';
        }

        $pos = array_search($id, $priority_list);
        if(false !== $pos)
        {
            return strval($pos + 1);
        }
        else
        {
            return '0';
        }

        return $pos;
    }

    /**
     * 根据平台号获取手机操作系统类型
     * @param $platform string 手机平台号
     *
     * @return string (ios, symbian, mac, android)
     */
    private function getPhoneOS($platform)
    {
        $platform = strtolower($platform);
        if (substr($platform, 0, 1) === 'i') {
            return 'ios';
        } elseif (substr($platform, 0, 1) === 's') {
            return 'symbian';
        } elseif (substr($platform, 0, 1) === 'm') {
            return 'mac';
        } else {
            return 'android';
        }
    }

    /**
     * 获取正则pattern
     *
     * @param string $str 使用|分割的字符串
     *
     *
     * @return string
     */
    public function getPattern($str)
    {
        if(empty($str))
        {
            return '';
        }

        $explode = explode('|', $str);
        $pattern = '';
        $count = count($explode);
        for($i = 0; $i < $count; $i++)
        {
            if(0 !== $i)
            {
                $pattern = $pattern . '|';
            }

            $pattern = $pattern . '^' . $explode[$i] . '$';
        }

        return $pattern;
    }

    /**
     * 下载皮肤领券接口
     * @route({"GET","/getCoupon"})
     * @param({"skinToken", "$._GET.skinToken"}) string
     * @param({"cuid", "$._GET.cuid"}) string cuid
     * @return({"header", "Access-Control-Allow-Origin: *"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getCoupon($skinToken='', $cuid='') {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => new stdClass(),
        );
        if (empty($cuid)) {
            $result['code'] = 1;
            $result['msg'] = '参数错误';
            return $result;
        }
        
        $objActivitySkinCoupon = new ActivitySkinCoupon();
        $objActivitySkinConfig = new ActivitySkinConfig();
        
        $couponConfig = $objActivitySkinConfig->getLastConfig();
        $bosHost = GFunc::getGlobalConf('bos_host');
        !empty($couponConfig['lottery_img']) && $couponConfig['lottery_img'] = $bosHost . $couponConfig['lottery_img'];
        
        $coupon = $objActivitySkinCoupon->getCouponByCuid($cuid);
        
        //如果已经领过券直接返回数据
        if (!empty($coupon)) {
            unset($coupon['id']);
            $result['data'] = !empty($couponConfig) ? array_merge($couponConfig, $coupon) : $coupon;
            return $result;
        }
        //如果没有领券则取一个券返回
        $coupon = $objActivitySkinCoupon->getRandomCoupon($cuid);
        if (!empty($coupon)) {
            unset($coupon['id']);
            $result['data'] = !empty($couponConfig) ? array_merge($couponConfig, $coupon) : $coupon;
        } else {
            $result['code'] = 1;
            $result['msg'] = '券码已经领取完！';
            $result['data'] = $couponConfig;
        }
        
        return $result;
    }

    /**
     * 运营活动面板头图和运营活动弹窗
     * http://agroup.baidu.com/inputserver/md/article/1745140
     * @route({"GET","/advertisement"})
     * @param({"requestFrom", "$._GET.request_from"}) string 请求来源,1表示运营活动面板头图，2表示运营活动弹窗
     * @param({"cuid", "$._GET.cuid"}) string 用户cuid
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function advertisement($requestFrom=2, $cuid='') {
        $result = Util::initialClass();
        $result['data']->list = array();

        //如果用户当天下发过数据，则不再下发
        $objActivityModel = IoCload(ActivityModel::class);
        $objRedis = GFunc::getCacheInstance();
        $hashVal = $objActivityModel->myHash($cuid);
        $dataKey = sprintf('%s_%s_%s_%s_%s', __CLASS__, __METHOD__, 'sended_data', date('Y-m-d'), $hashVal % 300);
        $existSendData = $objRedis->sismember($dataKey, $cuid);
        //同一个用户 当日已经下发 粉够 头图数据 则下发error （不可下发为空），下发空客户端会覆盖本地已有数据
        if (!(0 === $existSendData)) {
            $result['ecode'] = ErrorCode::ADVERTISEMENT_SENDED;
            $result['version'] = 0;
            return Util::returnValue($result, false, true);
        }

        //从资源服务获取渠道号
        $searchStr = urlencode('{"ad_type":'. $requestFrom .'}');
        $resUrl = '/res/json/input/r/online/activity_advertisement/';
        $strQuery = 'onlycontent=1&search=' . $searchStr;
        $resKey = md5(self::$cachePre . $resUrl . $strQuery);
        $advertisementRes = GFunc::cacheGet($resKey);
        if (false === $advertisementRes) {
            $advertisementRes = Util::getResource($resUrl, $strQuery, true);
            GFunc::cacheSet($resKey, $advertisementRes);
        }
        if (!empty($advertisementRes['headers'])) {
            $result['version'] = intval(strtotime($advertisementRes['headers']['Last-Modified']));
        } else {
            $result['version'] = 0;
        }
        //过滤
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $entityProcessor = new DefaultEntityProcessor();
        if ($advertisementRes['output']) {
            foreach ($advertisementRes['output'] as $advertisementResK => $advertisementResV) {
                //判断起始时间
                $time = time();
                if ((!empty($advertisementResV['sttime']) && $advertisementResV['sttime'] > $time) || (!empty($advertisementResV['exptime']) && $time > $advertisementResV['exptime'])) {
                    continue;
                }
                if ($conditionFilter->filter($advertisementResV['filter_conditions'])) {
                    //客户端要求必须有这个字段
                    array_walk($advertisementResV['tpl_data'], function(&$advertisementResVV) {
                        $advertisementResVV['query'] = '';
                    });
                    $arrDelimiter = array(
                        "\r\n",
                        "\n",
                        "\r",
                    );
                    //老数据为数组，兼容老数据，精确匹配的query
                    $queryInfo = array();
                    if (is_string($advertisementResV['query_info']) && !empty($advertisementResV['query_info'])) {
                        foreach ($arrDelimiter as $arrDelimiterV) {
                            if (false !== strpos($advertisementResV['query_info'], $arrDelimiterV)) {
                                $queryInfo = explode($arrDelimiterV, $advertisementResV['query_info']);
                                //去除空数据
                                foreach ($queryInfo as $queryInfoK => $queryInfoV) {
                                    if (empty($queryInfoV)) {
                                        unset($queryInfo[$queryInfoK]);
                                    }
                                }
                                $queryInfo = array_values($queryInfo);
                                break;
                            }
                        }
                        //只配置了一条数据，里面不包含换行符
                        empty($queryInfo) && $queryInfo[] = $advertisementResV['query_info'];
                    }
                    $advertisementResV['query_info'] = $queryInfo;

                    //模糊匹配的query
                    $fuzzyQueryInfo = array();
                    if (is_string($advertisementResV['fuzzy_query_info']) && !empty($advertisementResV['fuzzy_query_info'])) {
                        foreach ($arrDelimiter as $arrDelimiterV) {
                            if (false !== strpos($advertisementResV['fuzzy_query_info'], $arrDelimiterV)) {
                                $fuzzyQueryInfo = explode($arrDelimiterV, $advertisementResV['fuzzy_query_info']);
                                //去除空数据
                                foreach ($fuzzyQueryInfo as $queryInfoK => $queryInfoV) {
                                    if (empty($queryInfoV)) {
                                        unset($fuzzyQueryInfo[$queryInfoK]);
                                    }
                                }
                                $fuzzyQueryInfo = array_values($fuzzyQueryInfo);
                                break;
                            }
                        }
                        //只配置了一条数据，里面不包含换行符
                        empty($fuzzyQueryInfo) && $fuzzyQueryInfo[] = $advertisementResV['fuzzy_query_info'];
                    }
                    $advertisementResV['fuzzy_query_info'] = $fuzzyQueryInfo;

                    $skinToken = array();
                    //老数据为数组，兼容老数据
                    if (is_string($advertisementResV['skin_token']) && !empty($advertisementResV['skin_token'])) {
                        foreach ($arrDelimiter as $arrDelimiterV) {
                            if (false !== strpos($advertisementResV['skin_token'], $arrDelimiterV)) {
                                $skinToken = explode($arrDelimiterV, $advertisementResV['skin_token']);
                                foreach ($skinToken as $skinTokenK => $skinTokenV) {
                                    if (empty($skinTokenV)) {
                                        unset($skinToken[$skinTokenK]);
                                    }
                                }
                                $skinToken = array_values($skinToken);
                                break;
                            }
                        }
                        //只配置了一条数据，里面不包含换行符
                        empty($skinToken) && $skinToken[] = $advertisementResV['skin_token'];
                    }
                    $advertisementResV['skin_token'] = $skinToken;

                    $appPackageName = array();
                    //老数据为数组，兼容老数据
                    if (is_string($advertisementResV['app_package_name']) && !empty($advertisementResV['app_package_name'])) {
                        foreach ($arrDelimiter as $arrDelimiterV) {
                            if (false !== strpos($advertisementResV['app_package_name'], $arrDelimiterV)) {
                                $appPackageName = explode($arrDelimiterV, $advertisementResV['app_package_name']);
                                foreach ($appPackageName as $appPackageNameK => $appPackageNameV) {
                                    if (empty($appPackageNameV)) {
                                        unset($appPackageName[$appPackageNameK]);
                                    }
                                }
                                $appPackageName = array_values($appPackageName);
                                break;
                            }
                        }
                        //只配置了一条数据，里面不包含换行符
                        empty($appPackageName) && $appPackageName[] = $advertisementResV['app_package_name'];
                    }
                    $advertisementResV['app_package_name'] = $appPackageName;

                    $advertisementResV['global_id'] = sprintf('activityadvertisement#%s', $advertisementResV['id']);

                    //下发框属性
                    $ctrid = !empty($advertisementResV['ctrid']['ctrid']) ? explode('|', $advertisementResV['ctrid']['ctrid']) : array();
                    foreach ($ctrid as $key => $value) {
                        $ctrid[$key] = intval($value);
                    }
                    $advertisementResV['ctrid'] = $ctrid;

                    $entity = $entityProcessor->processEntity($advertisementResV, 'entity\\ActivityAdvertisementEntity');
                    $result['data']->list[] = $entity;
                }
            }
        }
        if (1 == $requestFrom && !empty($cuid)) {
            //天气实况信息
            $weatherData = $objActivityModel->getDataFromWeather($cuid, array(), $this->plt, 2, $this->weatherAppPackage);
            false !== $weatherData && $result['data']->list = array_merge($result['data']->list, $weatherData);
            //返回如果为false则不判断结果，否则则判断下发的数据是否全部为空，如果全部为空则下发14002状态码
            $necessaryCheck = array();
            //从粉够api读取数据
            $fengouResult = $objActivityModel->getDataFromFengou($cuid);
            if (false !== $fengouResult) {
                $result['data']->list = array_merge($result['data']->list, $fengouResult);
                $necessaryCheck[] = $fengouResult;
            }
            //获取京东数据
            $jingDongResult = $objActivityModel->getDataFromQingShan(2, $cuid);
            if (false !== $jingDongResult) {
                $result['data']->list = array_merge($result['data']->list, $jingDongResult);
                $necessaryCheck[] = $jingDongResult;
            }
            //获取拼多多数据
            $pinduoduoResult = $objActivityModel->getDataFromQingShan(3, $cuid);
            if (false !== $pinduoduoResult) {
                $result['data']->list = array_merge($result['data']->list, $pinduoduoResult);
                $necessaryCheck[] = $pinduoduoResult;
            }

            //默认需要下发异常code(14002)，只要有一个不为空，则不需要下发此code
            $checkRes = true;
            foreach ($necessaryCheck as $necessaryCheckV) {
                if (!empty($necessaryCheckV)) {
                    $checkRes = false;
                    break;
                }
            }
            if (empty($necessaryCheck)) {
                $checkRes = false;
            }
            if ($checkRes) {
                $result['ecode'] = ErrorCode::ADVERTISEMENT_DATA_EMPTY;
                return Util::returnValue($result, false, true);
            }

            //粉购、京东、拼多多都拿到数据才mark，当天已经下发过数据，如果用户不在商业化白名单里面不记录当天下发过数据
            if (!empty($necessaryCheck)) {
                $objRedis->sadd($dataKey, $cuid);
                $objRedis->expire($dataKey, GFunc::getCacheTime('2hours') * 12);
            }
        }

        return Util::returnValue($result, false, true);
    }

    /**
     * 非实时运营活动面板头图
     * http://agroup.baidu.com/inputserver/md/article/2558704
     * @route({"GET","/advertisementnonrealtime"})
     * @param({"requestFrom", "$._GET.request_from"}) string 请求来源,1表示运营活动面板头图，2表示运营活动弹窗
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function advertisementNonRealtime($requestFrom=2) {
        $result = Util::initialClass();

        $objActivityModel = IoCload(ActivityModel::class);
        //从资源服务获取广告数据
        $resourceList = $objActivityModel->getDataFromResource($requestFrom, 1);
        $result['data']->list = $resourceList['data'];
        $result['version'] = $resourceList['version'];

        return Util::returnValue($result, false, true);
    }

    /**
     * 实时运营活动面板头图
     * http://agroup.baidu.com/inputserver/md/article/2558711
     * @route({"GET","/advertisementrealtime"})
     * @param({"cuid", "$._GET.cuid"}) string 用户cuid
     * @param({"appPackageName", "$._GET.app_package_name"}) string 包名
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function advertisementRealtime($cuid, $appPackageName='') {
        $result = Util::initialClass();
        $result['data']->list = array();

        if (empty($appPackageName)) {
            $result['ecode'] = ErrorCode::PARAM_ERROR;
            return Util::returnValue($result, false, true);
        }
        //cuid白名单，不限制下发次数
        $whiteCuid = array(
            'BD0F1907BB9AB6A3A195C9EE457A6C23|O',//周智慧
            'BD0F1907BB9AB6A3A195C9EE457A6C23|0',//周智慧
            '75F8F53C3700CC4045494A6A3874E450|O',//测试机  小米5
            '75F8F53C3700CC4045494A6A3874E450|0',//测试机  小米5
            '8A1D2580103D45A754BBD5E66B9525BD|O',//测试机  魅族M5s
            '8A1D2580103D45A754BBD5E66B9525BD|0',//测试机  魅族M5s
            '1EAFE4D600F7B4A33562D779BD229696|O',//常敏强
            '1EAFE4D600F7B4A33562D779BD229696|0',//常敏强
            '929BC5D7DC2B9C5F760282AE7A124909|O',//毛竹东
            '929BC5D7DC2B9C5F760282AE7A124909|0',//毛竹东
        );

        $objActivityModel = IoCload(ActivityModel::class);
        $resourceData = $toutuData = $weatherAlarmData = $weatherData = $cctvNewsData = array();
        //从资源服务获取广告数据
        $resourceList = $objActivityModel->getDataFromResource(1, 2);
        if (!empty($resourceList['data'])) {
            foreach ($resourceList['data'] as $resourceListV) {
                //判断有没有下发过
                $isSended = $objActivityModel->checkIsSended('resource_v5', $cuid, $resourceListV->id);
                $now = time();
                if (false === $isSended) {
                    $resourceData[] = $resourceListV;
                    $expireTime = (int)$resourceList['id_timestamp'][$resourceListV->id] - $now;
                    if ($expireTime > 0) {
                        $objActivityModel->addToSended('resource_v5', $cuid, $resourceListV->id, $expireTime, (int)$resourceList['id_timestamp'][$resourceListV->id]);
                    }
                    break;
                } else if ($resourceListV->exptime > $isSended) {
                    //为了防止后台编辑过期时间导致的同一条数据重复下发，这里需要重新计算缓存过期时间
                    //比如：当前时间为4.1,第一次设置过期时间为4.2，那么过期时间设置为1天，但是后台又重新编辑，设置过期时间为4.2，此时需要重新设置过期时间，否则数据会重复下发
                    $expireTime = $resourceListV->exptime - $now;
                    $objActivityModel->addToSended('resource_v5', $cuid, $resourceListV->id, $expireTime, $resourceListV->exptime);
                }
            }
        }
        if (!empty($resourceData)) {
            $result['data']->list = $resourceData;
            return Util::returnValue($result, false, true);
        }

        //如果用户当天下发过数据，则不再下发
        $objRedis = GFunc::getCacheInstance();
        $hashVal = $objActivityModel->myHash($cuid);
        if (in_array($appPackageName, $this->weatherAppPackage)) {
            $cacheAppPackageName = "com.tencent.xin.qq";
        } else {
            $cacheAppPackageName = $appPackageName;
        }
        $dataKey = sprintf('%s_%s_%s_%s_%s_%s', __CLASS__, __METHOD__, 'sended_data', date('Y-m-d'), $hashVal % 300, $cacheAppPackageName);
        $existSendData = $objRedis->sismember($dataKey, $cuid);

        if (in_array($appPackageName, array('com.taobao.taobao', 'com.xunmeng.pinduoduo', 'com.jingdong.app.mall'))) {
            //@TODO
            if (!in_array($cuid, $whiteCuid) && 0 != $existSendData) {
                $result['ecode'] = ErrorCode::ADVERTISEMENT_SENDED;
                return Util::returnValue($result, false, true);
            }
            switch ($appPackageName) {
                case 'com.taobao.taobao':
                    $toutuData = $objActivityModel->getDataFromFengou($cuid, $whiteCuid);
                break;
                case 'com.jingdong.app.mall':
                    $toutuData = $objActivityModel->getDataFromQingShan(2, $cuid, $whiteCuid);
                break;
                case 'com.xunmeng.pinduoduo':
                    $toutuData = $objActivityModel->getDataFromQingShan(3, $cuid, $whiteCuid);
                break;
            }
            if (false === $toutuData) {
                return Util::returnValue($result, false, true);
            } else if (empty($toutuData)) {
                $result['ecode'] = ErrorCode::ADVERTISEMENT_DATA_EMPTY;
                return Util::returnValue($result, false, true);
            } else {
                //粉购、京东、拼多多都拿到数据才mark，当天已经下发过数据，如果用户不在商业化白名单里面不记录当天下发过数据
                $objRedis->sadd($dataKey, $cuid);
                $objRedis->expire($dataKey, GFunc::getCacheTime('2hours') * 12);
                $result['data']->list = $toutuData;
                return Util::returnValue($result, false, true);
            }
        } else if (in_array($appPackageName, $this->weatherAppPackage)) {
            //只有安卓下发央视新闻头图
            if ('android' == $this->plt) {
                //后台配置的数据
                $cctvNewsData = $objActivityModel->getDataFromCctv($cuid, $whiteCuid, $this->weatherAppPackage);
                if (empty($cctvNewsData)) {
                    //微博抓取的数据
                    $cctvNewsData = $objActivityModel->getDataFromCctvWeibo($cuid, $whiteCuid, $this->weatherAppPackage);
                }
                !empty($cctvNewsData) && $result['data']->list = array_merge($result['data']->list, $cctvNewsData);
            }
            //天气头图
            $weatherAlarmData = $objActivityModel->getDataFromWeather($cuid, $whiteCuid, $this->plt, 1, $this->weatherAppPackage);
            if (!empty($weatherAlarmData)) {
                $result['data']->list = array_merge($result['data']->list, $weatherAlarmData);
            } else {
                $weatherData = $objActivityModel->getDataFromWeather($cuid, $whiteCuid, $this->plt, 2, $this->weatherAppPackage);
                if ((in_array($cuid, $whiteCuid) || 0 == $existSendData) && !empty($weatherData)) {
                    $result['data']->list = array_merge($result['data']->list, $weatherData);
                    $objRedis->sadd($dataKey, $cuid);
                    $objRedis->expire($dataKey, GFunc::getCacheTime('2hours') * 12);
                }
            }
        } else {
            return ErrorCode::returnError('PARAM_ERROR', "app pacakge error", true, true);
        }

        return Util::returnValue($result, false, true);
    }

    /**
     * 运营活动面板头图实时请求白名单数据
     * http://agroup.baidu.com/inputserver/md/article/2558837
     * @route({"GET","/whiteData"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function whiteData() {
        $result = Util::initialClass();

        $cacheKey = md5(sprintf("%s_%s_%s", __CLASS__, __METHOD__, "white_data"));
        $data = GFunc::cacheGet($cacheKey);
        if (false === $data || null === $data) {
            $data = array();
            $objEcommerceWhiteDataModel = IoCload(EcommerceWhiteDataModel::class);
            $condition = array('status=100');
            $whiteData = $objEcommerceWhiteDataModel->select('*', $condition);
            if (false != $whiteData) {
                $data['whiteData'] = $whiteData;
                //获取最新更新时间戳
                $appends = array(
                    'ORDER BY update_time DESC',
                    'LIMIT 1'
                );
                $lastTimestamp = $objEcommerceWhiteDataModel->select('update_time', null, null, $appends);
                if (false !== $lastTimestamp) {
                    $data['lastTimestamp'] = $lastTimestamp;
                    $time = GFunc::getCacheTime('2hours');
                } else {
                    $data['whiteData'] = array();
                    $data['lastTimestamp'] = 0;
                    $time = GFunc::getCacheTime('1mins') * 5;
                }
            } else {
                $data['whiteData'] = array();
                $data['lastTimestamp'] = 0;
                $time = GFunc::getCacheTime('1mins') * 5;
            }

            GFunc::cacheSet($cacheKey, $data, $time);
        }

        $whiteData = $data['whiteData'];
        $lastTimestamp = $data['lastTimestamp'];
        if (!empty($whiteData) && 0 !== $lastTimestamp) {
            $result['data']->app_package_name_ctrid = array();
            $result['version'] = $lastTimestamp[0]['update_time'];

            $objActivityModel = IoCload(ActivityModel::class);
            //走过滤条件
            $conditionFilter = IoCload('utils\\ConditionFilter');
            $whiteData = $conditionFilter->getFilterConditionFromDB($whiteData);
            //数据组装
            $arrDelimiter = array(
                "\r\n",
                "\n",
                "\r",
            );
            foreach ($whiteData as $whiteDataK => $whiteDataV) {
                if (empty($whiteDataV['app_package_name']) || empty($whiteDataV['ctrid'])) {
                    continue;
                }
                //皮肤token
                $arrToken = !empty($whiteDataV['token']) ? $objActivityModel->explodeByArray($arrDelimiter, $whiteDataV['token']) : array();
                //包名、框属性
                $arrAppPackageName = $objActivityModel->explodeByArray($arrDelimiter, $whiteDataV['app_package_name']);
                $arrCtrid = explode(',', $whiteDataV['ctrid']);
                $result['data']->app_package_name_ctrid[] = array(
                    'ctrid' => $arrCtrid,
                    'app_package_name' => $arrAppPackageName,
                    'token' => $arrToken,
                );
            }
            if (empty($result['data']->app_package_name_ctrid)) {
                return ErrorCode::returnError('ADVERTISEMENT_WHITE_DATA_EMPTY', "data is empty");
            }
        } else {
            return ErrorCode::returnError('DB_ERROR', "query from db error");
        }

        return Util::returnValue($result, true, true);
    }
}

