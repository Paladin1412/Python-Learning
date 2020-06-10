<?php
/**
 *
 * @desc 通知中心业务接口--统一配置
 * @path("/noti_switch_noti/")
 */
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\ErrorMsg;
use utils\ErrorCode;
use utils\GFunc;
use utils\ApcCache;

ClassLoader::addInclude(__DIR__.'/noti');

class NotiSwitchNoti
{
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strNotiSwitchNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;

    /*
     * apc cache
     */
    private $apcCache;

    
    /***
    * 构造函数
    * @return void
    */
    public function  __construct() {
        //输出格式初始化
        $this->out = Util::initialClass(false);
        // $this->intApcCacheExpied = GFunc::getCacheTime();
        $this->apcCache = new ApcCache();
    }
    
    /**
    * @desc 统一配置
    * @route({"GET", "/info"})
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"strFrom", "$._GET.from"}) $strFrom 初始渠道号,不需要客户端传
    * @param({"strRom", "$._GET.rom"}) $strRom rom os版本号,不需要客户端传
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": 1,
            "version": 123
            ]
        }
    */
    public function getNotiSwitch($strVersion, $strPlatform, $notiVersion = 0, $strFrom = '', $strRom = '')
    {
        //get redis obj
        //$redis = GFunc::getCacheInstance();
        $this->apcCache = new ApcCache();
        
        $conf = GFunc::getConf('Noti');
        $strUnifyConfResRoot = $conf['properties']['strUnifyConfResRoot'];
        
        //version
        $intVersion = Util::getVersionIntValue($strVersion);
        
        //set version with request version
        UnifyConf::setLocalVersion($notiVersion);
        
        //统一开关配置
        $arrUnifyConf = UnifyConf::getUnifyConf($this->apcCache, $this->strNotiSwitchNotiCachePre, $this->intCacheExpired, $strUnifyConfResRoot, Util::getClientIP(), $strFrom);
        
        $data = $arrUnifyConf;
        
        //os: ios android and so on...
        $strPhoneOs = Util::getPhoneOS($strPlatform);
        
        $strRom = Util::formatVer($strRom);
        
        //ios testflight 开关
        $data['ios_testflight_switch'] = isset($arrUnifyConf['ios_testflight_switch']) ? $arrUnifyConf['ios_testflight_switch'] : 0;
        
        //忽略小米数据上传白名单
        if(1 == $arrUnifyConf['ignore_mi_wl']){
            $data['ignore_mi_wl'] = 1;
        }
        
        //小米商店设置项精品/热词入口服务端开关，三个值分别代表精品、热词、发现tab的是否展示。
        /*if(!empty($arrUnifyConf['mi_tab_show'])){
            $data['mi_tab_show'] = is_array($arrUnifyConf['mi_tab_show'])? $arrUnifyConf['mi_tab_show']:json_decode($arrUnifyConf['mi_tab_show'], true);
        } else {
            $data['mi_tab_show'] = UnifyConf::$arrDefaultUnifyConf['mi_tab_show'];
        }*/
        
        //================UC导流================
        //UC浏览器是否显示窗口及窗口大小
        //3种情况：没有 0，大的 1，小的 2
        $data['uc_redi'] = $arrUnifyConf['uc_redi'];
        
        //流行词是否在熊头提醒的开关
        $data['hwiconremind'] = $arrUnifyConf['hwiconremind'];
        
        //先判断OEM版本是否走LC升级平台进行升级,注意通知里没有这个通知版本更新里用到
        $data['oem_lc_switch'] = $arrUnifyConf['oem_lc_switch'];
        
        //客户端用户搜索浮层开关,0搜索浮层点击x才会收起 1搜索浮层点击x和浮层外部区域会收回 2搜索浮层关闭
        //没有默认取值且增加统一过滤后有可能不下发因此需要判断
        if(isset($arrUnifyConf['search_float'])){
            $data['search_float'] = $arrUnifyConf['search_float'];
        }
        
        //moplus开关
        $data['moplus'] = $arrUnifyConf['moplus'];
        
        /*
        //sug配置相关
        $data['sug'] = array(
            'sug_switch' => $arrUnifyConf['sug_switch'], //客户端是否发起sug请求的云开关 true:开启 false:关闭（默认)
            'app' => $arrUnifyConf['app'], //客户端sug应用词源连接的服务器 0:百度 1：小米(默认)
            'search' => $arrUnifyConf['search'], //客户端sug搜索词源连接的服务器 0:百度 1：小米(默认)
            'sug_min_net_level' => $arrUnifyConf['sug_min_net_level'], //发起sug请求的最低网络要求 0：所有网络  1：2G网络 2：3G网络 3：4G网络 5：WIFI
            'sug_version' => NotiComm::checkSugWhiteUpdate($this->strNotiSwitchNotiCachePre . '_new_noti_sug_app_version', $this->intCacheExpired),
            'sug_statistic_mi' => $arrUnifyConf['sug_statistic_mi'], //小米sug统计开关 true:开启 false：关闭(默认)
            'sug_mi_scene_rate' => intval($arrUnifyConf['sug_mi_scene_rate']), //小米应用场景统计比例 0~100 整数  默认0
            'sug_mi_browser_rate' => intval($arrUnifyConf['sug_mi_browser_rate']), //小米浏览器统计比例 0~100 整数  默认0
            'sug_icon_list_version' => NotiComm::getSugIconListVersion(),  //sug band icon白名单版本号
        );
        */
        
        /*if(isset($arrUnifyConf['sug_mi_card_remind_period'])){
            $data['sug']['sug_mi_card_remind_period'] = intval($arrUnifyConf['sug_mi_card_remind_period']);//小米sug卡片提醒框展示周期(单位天)，int型
        }
        
        if(isset($arrUnifyConf['sug_mi_card_remind_max_count'])){
            $data['sug']['sug_mi_card_remind_max_count'] = intval($arrUnifyConf['sug_mi_card_remind_max_count']);//小米sug卡片提醒框最大展示次数
        }
        
        if(isset($arrUnifyConf['sug_mi_card_show_switch'])){
            $data['sug']['sug_mi_card_show_switch'] = intval($arrUnifyConf['sug_mi_card_show_switch']);//小米sug卡片展示开关 1 为开，0为关
        }
        
        //小米cand条
        $data['cand_search_type'] = 'true' == $arrUnifyConf['cand_search_type'] ? 1 : 0;
        
        //小米cand icon 资源 相关
        //有数据情况:{"mi_cand": {"mi_cand_icon_switch": 0}}
        //没有数据情况:{"mi_cand": []}
        $data['mi_cand'] = array();
        //小米cand条运营icon功能使用小米端还是百度的数据(1百度，0小米)，int型,客户端默认为0，服务端有可能不下发这个字段（请求数据库失败或PM配置不符合条件）客户端以之前的值为准。
        if(isset($arrUnifyConf['mi_cand_icon_switch'])){
            $data['mi_cand']['mi_cand_icon_switch'] = $arrUnifyConf['mi_cand_icon_switch'];
        }
        
        //小米引导切换主线通知开关
        $miContentAry = isset($arrUnifyConf['mi_msg_content']) ? explode('|', $arrUnifyConf['mi_msg_content']) :  array('','');
        //小米引导切换主线通知
        $data['mi_noti'] = array(
            'mi_msg_interval_time' => intval($arrUnifyConf['mi_msg_interval_time']), //小米引导切换主线通知展示间隔(小时) 默认72小时
            'title' => isset($miContentAry[0]) ? $miContentAry[0] : '',
            'content' => isset($miContentAry[1]) ? $miContentAry[1] : '',
        );
        
        //AR表情图片收集开关 0: 关闭 1: 打开
        $data['ar_img_col_switch'] = isset($arrUnifyConf['ar_img_col_switch']) ? $arrUnifyConf['ar_img_col_switch'] : 0;
        $data['ar_img_col_switch'] = intval($arrNoti['ar_img_col_switch']);
        
        $data['acs_switch'] = $arrUnifyConf['acs_switch'];
        
        if($intVersion >= 6050000) {
            //测试通知中心延迟
            $data['down_balance_time_set'] = is_array($arrUnifyConf['down_balance_time_set'])? $arrUnifyConf['down_balance_time_set']:json_decode($arrUnifyConf['down_balance_time_set'], true);
            
            if(isset($data['down_balance_time_set']['start_time_h']) && $data['down_balance_time_set']['start_time_h'] != '' && isset($data['down_balance_time_set']['end_time_h']) && $data['down_balance_time_set']['end_time_h'] != ''){
                $start_time_h = intval($data['down_balance_time_set']['start_time_h']);
                $end_time_h = intval($data['down_balance_time_set']['end_time_h']);
                
                //如果开始小时数在终止小时数后，数据不下发
                if($start_time_h > $end_time_h){
                    $data['down_balance_time_set'] = UnifyConf::$arrDefaultUnifyConf['down_balance_time_set'];
                } else {
                    $data['down_balance_time_set']['start_time_m'] = 0;
                    $data['down_balance_time_set']['end_time_m'] = 0;
                    
                    //后台设置的随即分钟数
                    if(isset($data['down_balance_time_set']['random']) && $data['down_balance_time_set']['random'] != ''){
                        $data['down_balance_time_set']['end_time_m'] = intval($data['down_balance_time_set']['random']);
                        unset($data['down_balance_time_set']['random']);
                    }
                }
            }
            
            //6.5版本以上下发小米数据分享开关
            if(isset($arrUnifyConf['mi_data_share_switch']) && $arrUnifyConf['mi_data_share_switch'] !== '') {
                $data['mi_data_share_switch'] = $arrUnifyConf['mi_data_share_switch'];
            }
        } else {
            $data['down_balance_time_set'] = UnifyConf::$arrDefaultUnifyConf['down_balance_time_set'];
        }*/
        
        //7.4版本以上下发智能强引导开关和IOS语音麦克风模式开关
        if($intVersion >= 7040000 ){
            $data['int_guidance_switch'] = $arrUnifyConf['int_guidance_switch'];
            if($arrUnifyConf['ios_microphone_mode_switch'] !== '') {
                $data['ios_microphone_mode_switch'] = $arrUnifyConf['ios_microphone_mode_switch'];
            }
        }
        
        //7.6版本以上下发安卓离线语音开关
        if($intVersion >= 7060000 ){
            if($arrUnifyConf['offline_voice_switch'] !== '') {
                $data['offline_voice_switch'] = $arrUnifyConf['offline_voice_switch'];
            }
        }
        
        //语音气泡提醒开关
        isset($arrUnifyConf['voice_tips']) && ($data['voice_tips'] = $arrUnifyConf['voice_tips']);
        
        //表情icon 红点/熊头提示开关 0：不显示(默认) 1：红点 2：熊头
        $data['emojis'] = $arrUnifyConf['tips_switch_emojis'];
        
        //iOS语音面板提示开关
        $data['ios_speech_alert_switch']=0;
        
        if(isset($arrUnifyConf['ios_speech_alert_switch']) && $arrUnifyConf['ios_speech_alert_switch']===1){
            $data['ios_speech_alert_switch']=1;
        }

        //qm sdk 数据收集开发
        $data['qm_sdk_switch'] = isset($arrUnifyConf['qm_sdk_switch']) ? $arrUnifyConf['qm_sdk_switch'] : 1;

        //mtj 数据上传开关
        $data['mtj_switch'] = isset($arrUnifyConf['mtj_switch']) ? $arrUnifyConf['mtj_switch'] : 1;
        
        $this->out['data'] = $data;
        
        $this->out['version'] = UnifyConf::getVersion();
        
        //code
        $this->out['ecode'] = UnifyConf::getCode();
        //msg
        $this->out['emsg'] = ErrorMsg::getMsg($this->out['ecode']);
        
        return Util::returnValue($this->out,false);
    }
    
    
    
    /**
    *
    * 设置当前类缓存
    * @param $cacheKey 缓存key
    * @param $cacheTtl 缓存时长
    * @return void
    */
    public function setClassCache($cacheKey = '',$cacheTtl = 0){
        $cacheKey = trim($cacheKey);
        if($cacheKey !== ''){
            $this->strAdNotiCachePre = $cacheKey;
            $this->intCacheExpired = intval($cacheTtl);
        }
    }
    
    /**
    * @desc 统一配置
    * @route({"GET", "/info_new"})
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": 1,
            "version": 123
            ]
        }
    */
    public function getNotiSwitchNew() {
        //输出格式初始化
        $this->out = Util::initialClass(true);

        //get newest cache key
        $objDbx = IoCload('models\\NotiSwitchModel');
        $strCacheKey = Util::getCacheVersionPrefix($objDbx->_tbl) . $this->strNotiSwitchNotiCachePre . '_new_list';
        
        $arrData = GFunc::cacheZget($strCacheKey);
        if($arrData === false) {
            $arrFields = array('noti_key','type','value','filter_id');
            $arrConds = array('status=' => 100);
            $arrResult = $objDbx->select($arrFields, $arrConds);
            
            //get version
            $intVersion = Util::getDataVersion($objDbx->_tbl);
            
            //set cache with 5 min when it could not get version
            if($intVersion === 0) {
                $intCacheTime = Gfunc::getCacheTime('5mins');
            } else {
                $intCacheTime = Gfunc::getCacheTime('30mins');
            }
            
            if(!empty($arrResult)) {
                foreach($arrResult as $key => $val) {
                    //设置int
                    if($val['type'] == 'int') {
                        $arrResult[$key]['value'] = intval($val['value']);
                    }
                    
                    unset($arrResult[$key]['type']);
                }
            }
            
            $arrData = array(
                'data' => $arrResult,
                'version' => $intVersion,
            );
            
            GFunc::cacheZset($strCacheKey, $arrData, $intCacheTime);
        }
        
        //过滤条件筛选
        $objFilter = IoCload('utils\\ConditionFilter');
        $arrData['data'] = $objFilter->getFilterConditionFromDB($arrData['data'], "filter_id");
        if(!empty($arrData['data'])) {
            $arrReturn = array();
            foreach($arrData['data'] as $val) {
                $arrReturn[$val['noti_key']] = $val['value'];
            }
            
            $this->out['data'] = $arrReturn;
        }

        //Edit by fanwenli on 2020-01-06, set 1 for cloud_count_switch when there has no setting
        if(is_object($this->out['data'])) {
            $this->out['data'] = array('cloud_count_switch' => 1);
        } elseif(is_array($this->out['data']) && !isset($this->out['data']['cloud_count_switch'])) {
            $this->out['data']['cloud_count_switch'] = 1;
        }

        //Edit by fanwenli on 2020-01-13, set yes for ios_dataflowstat_switch when there has no setting
        if(!isset($this->out['data']['ios_dataflowstat_switch'])) {
            $this->out['data']['ios_dataflowstat_switch'] = 1;
        }
        // 增加证据预测默认值
        if(!isset($this->out['data']['app_sentence_predict_switch'])) {
            $this->out['data']['app_sentence_predict_switch'] = 1;
        }
        // 增加IOS内核Log收集开关
        if(!isset($this->out['data']['ios_modulelog_core_switch'])) {
            $this->out['data']['ios_modulelog_core_switch'] = 0;
        }
        // 华为简版实时数据手机
        if(!isset($this->out['data']['hlr_switch'])) {
            $this->out['data']['hlr_switch'] = 0;
        }
        
        $this->out['version'] = $arrData['version'];
        
        return Util::returnValue($this->out, true, true);
    }
}
