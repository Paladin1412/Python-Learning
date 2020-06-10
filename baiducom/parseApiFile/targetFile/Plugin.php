<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

/**
 *
 * plugin
 * 说明：插件（发现)相关接口
 *
 * @author zhoubin
 * @path("/plugin/")
 */
class Plugin
{
    
    private $db;
    
    private $xdb; //新库实例
    
    /** @property v5 */
    private $domain_v5;
    
    /**
     *
     * bcs prefix
     * @var string
     */
    const BCS_PLUGIN_PREFIX = '/ime_api_v5_plugin_';
    
    /**
     *
     * handwrite bcs prefix
     * @var string
     */
    const BCS_HANDWRITE_PREFIX = '/ime_api_v5_handwrite_';
    
    /**
     *
     * bcs plugin recommend info prefix
     * @var string
     */
    const BCS_PLUGIN_RECOMMEND_TIP_INFO_PREFIX = '/plugin_rmd_tip_info';
    
    /**
     *
     * store one page count
     * @var int
     */
    const PLUGIN_STORE_ONE_PAGE_COUNT = 12;
    
    /**
     *
     * online flag
     * @var int
     */
    const PLUGIN_ONLINE_FLAG = 100;
    
    /**
     *
     * not recommend flag
     * @var string
     */
    const PLUGIN_NOT_RECOMMEND_FLAG = '2000';
    
    /**
     *
     * redis 插件列表key
     * @var string
     */
    const CACHE_PLUGIN_LIST_KEY = 'ime_api_v5_plugin_list_i85_v1_';
    
    /**
     *
     * redis 插件prefix
     * @var string
     */
    const CACHE_PLUGIN_ITEM_PREFIX = 'ime_api_v5_plugin_item_i85';
    
    /**
     *
     * redis 插件推荐key
     * @var string
     */
    const CACHE_PLUGIN_RECOMMEND_TIP_KEY = 'ime_api_v5_plugin_rmd_tip_info_i85';
    
    /**
     *
     * redis 插件排序第一位缓存key
     * @var string
     */
    const CACHE_PLUGIN_FIRST_LIST_KEY = 'ime_api_v5_plugin_first_i85_';
    
    /**
     *
     * redis 插件排序第二位缓存key
     * @var string
     */
    const CACHE_PLUGIN_SECOND_LIST_KEY = 'ime_api_v5_plugin_second_i85_';
    
    /**
     *
     * redis 插件排序第三位缓存key
     * @var string
     */
    const CACHE_PLUGIN_THIRD_LIST_KEY = 'ime_api_v5_plugin_third_i85_';
    
    /**
     *
     * redis 手写模板列表prefix
     * @var string
     */
    const CACHE_HANDWRITE_LIST_PREFIX = 'ime_api_v5_handwrite_list';
    
    /** @property 内部缓存实例(apc内存缓存) */
    private $apcCache;
    
    /** @property 内部缓存默认过期时间(单位: 秒) */
    private $intCacheExpired;
    
    
    /**
     *
     * pcs one page cout
     *
     */
    const PCS_LIST_PAGE_COUNT = 100;
    
    /**
     *
     * tip not support
     *
     */
    const DEFAULT_TIP_NOT_SUPPORT = '您的手机硬件暂不支持哦~';
    
    /**
     *
     * tip update
     *
     */
    const DEFAULT_TIP_UPDATE = '请升级到新版本再使用该功能哦~';
    
    
    const DEFAULT_TIP = '请升级到新版本再使用该功能哦~';
    
    
    /**
     *
     * 推荐相关信息表
     *
     */
    private static $strAppStoreRmdTable = 'app_store_recommend';
    
    /**
     *
     * redis 精品应用推荐key
     * @var string
     */
    const CACHE_APP_STORE_RECOMMEND_LIST_KEY = 'ime_api_v5_app_store_rmd_list_';
    
    /**
     *
     * BCS
     *
     */
    private $_bcs;
    
    
    /**
     *
     * 插件信息表
     *
     */
    private static $_plugin_table = 'plugin';
    
    /**
     *
     * 推荐相关信息表
     *
     */
    private static $_plugin_rmd_table = 'plugin_recommend';
    
    
    /**
     *
     * jump down url
     * for report
     *
     */
    private $strJumpDownUrl;
    
    /**
     * 离线语音插件排除的输入法版本号列表
     * @var array
     */
    static $OFFLINE_VOICE_EXCEPT_VERSION_LIST = array(
        "5.4.3.20",
        "5.4.3.21",
        "5.4.3.22",
        "5.4.3.23",
        "5.4.3.24",
        "5.4.3.25",
    );
    
    /**
     *
     * 离线语音key
     * @var array
    */
    static $OFFLINE_VOICE_KEY_LIST = array(
        'com.baidu.input.plugin.kit.offlinevoicerec',
        'com.baidu.input.plugin.kit.emojidiy',
    );
    
    /**
     *
     * os android ios
     *
     */
    private $strOs;
    
    /**
     * 输入法版本号
     * @var string
     */
    private $version;
    
    /**
     * 平台 ios / android
     * @var string
     */
    private $plt;
    
    /**
     * @var string
     * 系统ROM版本
     */
    public $rom='';
    
    /**
     * @var string
     * android平台正序imei
     *
     */
    public  $imei='';
    
    /**
     * 平台号
     * @var string
     */
    public  $platform;
    
    /**
     * 类只会在无缓存时才会被实例化, 所以可以在构造方法中连接数据库
     * @return void
     */
    function __construct()
    {
        $this->strOs = isset($_GET['platform']) ? $this->getPhoneOS($_GET['platform']) : '';
        $this->plt = $this->strOs === 'android' ? 'android' : 'ios' ;
        $this->version = isset($_GET['version']) ? str_replace('-', '.',$_GET['version']) : ''; //转换格式 5-0-0-1 to 5.0.0.1
        $this->imei = isset($_GET['cuid']) ? $_GET['cuid'] : '';
        $this->rom = isset($_GET['rom']) ? $_GET['rom'] : '';
        $this->cc = isset($_GET['cc']) && !empty($_GET['cc']) ? true : false;
        $this->platform = isset($_GET['platform']) ? $_GET['platform'] : '';
        
    }
    
    
    /**
     * @route({"POST","/list/"})
     * 根据请求类型获取插件列表信息
     * @return array
     */
    public function getList(){
        //分平台，android和ios
        $platform = $this->getPhoneOS($this->platform);
        $cpu_info = isset($_POST['plugin'])  ? $_POST['plugin'] : '';
        //by lipengcheng02:如果cpu信息为空，说明是ral方式传递的post数据，用下面的方式来获取
        if($cpu_info == ''){
            $post_info = json_decode(file_get_contents("php://input"),true);
            $cpu_info = $post_info['plugin'];
        }
        //for test1 test2
        //$this->version = "5.0.0.1";
        //$cpu_info = '{"cpu":"freq : 1401600|features\t: swp half thumb fastmult vfp edsp neon vfpv3 |core:1"}';
        
        $memory = isset($_REQUEST['mem']) ? $_REQUEST['mem'] : '0';
        $cpu = $this->parseCpuInfo($cpu_info);
        //将解析不了的输出到日志
        if('-1' === $cpu['isok'])
        {
            //@error_log($cpu_info, 0);
        }
        
        //获取type
        $request_type = $_GET['type'];
        if(!isset($request_type) || (('logo' !== $request_type) && ('store' !== $request_type)))
        {
            header('X-PHP-Response-Code: '. 400, true, 400);
            exit();
        }
   
        $data =  array();
        if('logo' === $request_type)
        {
            $data = $this->getLogoList($platform, $cpu, $memory);
        }
        
        if('store' === $request_type)
        {
            $data = $this->getStoreList($platform, $cpu, $memory);
        }
        header("Content-Type:application/json");
        return $data;
    }
    
    
    /**
     * logo菜单列表
     *
     * @param
     *      参数名称：platform
     *      是否必须：是
     *      参数说明：平台
     *
     * @param
     *      参数名称：cpu
     *      是否必须：是
     *      参数说明：cpu
     *
     * @param
     *      参数名称：memory
     *      是否必须：是
     *      参数说明    memory
     * @return array
     */
    public function getLogoList($platform, $cpu, $memory)
    {  
        $rmd_info = $this->getRecommendTipInfo();
        
        $logo_memu_list = array();
        $logo_memu_list['tipversion'] = $rmd_info['tiplogversion'];
        $logo_memu_list['tipbear'] = $rmd_info['tipbear'];
        $logo_memu_list['logo'] = $rmd_info['logo'];
        $logo_memu_list['logo480'] = $rmd_info['logo480'];
        $logo_memu_list['logo720'] = $rmd_info['logo720'];
        $logo_memu_list['logo1080'] = $rmd_info['logo1080'];
        $logo_memu_list['logo2x'] = $rmd_info['logo2x'];
        $logo_memu_list['logo3x'] = $rmd_info['logo3x'];
        $logo_memu_list['is_color_wash'] = $rmd_info['is_color_wash'];
        $logo_memu_list['plugins'] = array();
        
        if(empty($rmd_info['logormdlist'])){
            return $logo_memu_list;
        }
        
        $plugin_list = $this->getPluginList($platform);
        
        $plugin_list_count = count($plugin_list);
    
        //取客户端两位版本号对应数值
        $input_version = substr($this->version, 0, 3);
        $client_version = $this->getVersionIntVal($input_version, 2);
    
        $first_array = array();
        
        //取推荐版本
        $rmd_version = $this->getRecommendVersion($rmd_info['logormd_suggest_version']);
        
        //
        $cache_first_key = self::CACHE_PLUGIN_FIRST_LIST_KEY . 'logo' . $platform . $this->version;
        //先从cache里获取缓存的所以插件列表
        $first_array = GFunc::cacheGet($cache_first_key);
        $is_cache_first = true;
        if(false === $first_array)
        {
            $first_array = array();
            $is_cache_first = false;
        }
        //含有子插件的插件高低端主信息必须一致
        //推荐的插件显示在最前面
        if( !$is_cache_first && (0 !== $rmd_version) && ($client_version >= $rmd_version)) {
            for ($pos = 0; $pos < $plugin_list_count; $pos ++) {
                if (! $this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])) {
                    continue;
                }
                
                // get recommend position
                $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
                
                // 不推荐
                if (self::PLUGIN_NOT_RECOMMEND_FLAG === $one_plugin['rmdpos']) {
                    continue;
                }
                
                $rmd_input_version = $rmd_info['logormd_suggest_version'][$one_plugin['rmdpos'] - 1];
                // 推荐插件版本必须相等
                if ($rmd_version !== $rmd_input_version) {
                    continue;
                }
                
                $one_plugin['id'] = $plugin_list[$pos]['id'];
                $one_plugin['name'] = $plugin_list[$pos]['name'];
                $one_plugin['desc'] = $plugin_list[$pos]['desc'];
                $one_plugin['logo_rmd'] = $this->getIsRecommend($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
                $one_plugin['logodown'] = $plugin_list[$pos]['logo'];
                $one_plugin['logo55down'] = (null === $plugin_list[$pos]['logo55']) ? "" : $plugin_list[$pos]['logo55'];
                $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
                // 含有对应版本推荐
                $have_equal_rmd = false;
                // 保存推荐版本同客户端版本是否相等
                $one_sub['rmdequal'] = ($client_version === $rmd_input_version);
                // 保存插件位置
                $one_sub['pos'] = $pos;
                // 保存推荐位置
                $one_sub['rmdpos'] = $one_plugin['rmdpos'];
                $one_sub['reason'] = '';
                $one_sub['grade'] = $plugin_list[$pos]['grade'];
                $one_sub['version'] = $plugin_list[$pos]['version'];
                $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
                $one_sub['size'] = $plugin_list[$pos]['size'];
                $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
                $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
                $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
                
                {
                $is_new = true;
                for ($i = 0; $i < count($first_array); $i ++) {
                    // 超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $first_array[$i]['id']) {
                        $is_new = false;
                        
                        // 等级相同的不同不同版本插件 判断推荐位置
                        $sub_pos = - 1;
                        for ($j = 0; $j < count($first_array[$i]['subclasses']); $j ++) {
                            if ($plugin_list[$pos]['grade'] === $first_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
                        
                        // 没有相同grade插件则直接插入，有则判断推荐位置
                        if (- 1 === $sub_pos) {
                            array_push($first_array[$i]['subclasses'], $one_sub);
                        } else {
                            // 已经保存
                            $version_first = $first_array[$i]['subclasses'][$sub_pos]['version_name'];
                            // 未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            // 高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                            }
                            
                            // 判断客户端输入法版本同推荐版本
                            if ($client_version === $rmd_input_version) {
                                // 判断推荐位置
                                if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                    // 更新sub class
                                    $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                                    
                                    // 更新插件主信息
                                    $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                    $first_array[$i]['id'] = $one_plugin['id'];
                                    $first_array[$i]['name'] = $one_plugin['name'];
                                    $first_array[$i]['desc'] = $one_plugin['desc'];
                                    $first_array[$i]['logo_rmd'] = $one_plugin['logo_rmd'];
                                    $first_array[$i]['logodown'] = $one_plugin['logodown'];
                                }
                            } else {
                                if (! $first_array[$i]['subclasses'][$sub_pos]['rmdequal']) {
                                    // 判断推荐位置
                                    if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                        // 更新sub class
                                        $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                                        
                                        // 更新插件主信息
                                        $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                        $first_array[$i]['id'] = $one_plugin['id'];
                                        $first_array[$i]['name'] = $one_plugin['name'];
                                        $first_array[$i]['desc'] = $one_plugin['desc'];
                                        $first_array[$i]['logo_rmd'] = $one_plugin['logo_rmd'];
                                        $first_array[$i]['logodown'] = $one_plugin['logodown'];
                                    }
                                }
                            }
                            //logo
                            }
                        }
                    }
                    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($first_array, $one_plugin);
                }
                }
                                                                                                     
            }
                                                                                                        
            //sort
            //按照推荐位排序
            usort($first_array, function($a, $b)
            {
                if ($a['rmdpos'] == $b['rmdpos'])
                {
                   return 0;
                }
                return ($a['rmdpos'] > $b['rmdpos']) ? 1 : -1;
            });
                            
            //等级排序
            for($i = 0; $i < count($first_array); $i++)
            {
                usort($first_array[$i]['subclasses'], function($a, $b)
                {
                    if ($a['grade'] == $b['grade'])
                    {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
            
            //去掉排序字段
            for($i = 0; $i < count($first_array); $i++)
            {
                for($j = 0; $j < count($first_array[$i]['subclasses']); $j++)
                {
                    unset($first_array[$i]['subclasses'][$j]['grade']);
                    unset($first_array[$i]['subclasses'][$j]['rmdequal']);
                    unset($first_array[$i]['subclasses'][$j]['rmdpos']);

                    //$first_array[$i]['subclasses'][$j]['version_name'] = $this->getVersionString($first_array[$i]['subclasses'][$j]['version_name']);
                    $first_array[$i]['subclasses'][$j]['version_name'] = $plugin_list[$first_array[$i]['subclasses'][$j]['pos']]['version_name_str'];
                }
            }
        
            //去掉排序字段
            for($i = 0; $i < count($first_array); $i++)
            {
                unset($first_array[$i]['rmdpos']);
            }
            
        }//if
            
            
            
        if(!$is_cache_first)
        {
            GFunc::cacheSet($cache_first_key, $first_array, $this->intCacheExpired);
        }
        
        //support reason
        for($i = 0; $i < count($first_array); $i++)
        {
            //非高低端
            if( 1 === count($first_array[$i]['subclasses']) )
            {
                //不在推送区间
                $min_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if('' !== $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'])
                {
                    $max_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                }
                else
                {
                    $max_version = 100;
                }

                //推荐配置的版本低于支持最小版本因此提示升级输入法
                if( !(($client_version >= $min_version) && ($client_version <= $max_version)) )
                {
                    $first_array[$i]['subclasses'][0]['support'] = '0';
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }

                //
                $first_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory)
                        && $this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu))? '1' : '0';
                //配置推荐不支持插件
                if('0' === $first_array[$i]['subclasses'][0]['support'])
                {
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }

            }
            else
            {
                //指令集是否支持
                if($this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu))
                {
                    //高端支持
                    if($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory))
                    {
                        $first_array[$i]['subclasses'][0]['support'] = '1';
                        for( $j = 1; $j < count($first_array[$i]['subclasses']); $j++ )
                        {
                            $first_array[$i]['subclasses'][$j]['support'] = '0';
                            $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    }
                    else
                    {
                        for( $j = 0; $j < count($first_array[$i]['subclasses']); $j++ )
                        {
                            //不考虑3中包情况
                            if(1 === $j)
                            {
                                $first_array[$i]['subclasses'][$j]['support'] = '1';
                            }
                            else
                            {
                                $first_array[$i]['subclasses'][$j]['support'] = '0';
                                $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                }
                else
                {
                    for( $j = 0; $j < count($first_array[$i]['subclasses']); $j++ )
                    {
                        $first_array[$i]['subclasses'][$j]['support'] = '0';
                        $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }

            }
        }

        $logo_memu_list['plugins'] = $first_array;

        //去掉位置字段
        for($i = 0; $i < count($logo_memu_list['plugins']); $i++)
        {
            for($j = 0; $j < count($logo_memu_list['plugins'][$i]['subclasses']); $j++)
            {
                unset($logo_memu_list['plugins'][$i]['subclasses'][$j]['pos']);
            }
        }

        return $logo_memu_list;
}
        
        
    /**
     * 手机指令集是否支持
     * @param
     *      参数名称：$plugin
     *      是否必须：是
     *      参数说明：插件信息
     *
     * @param
     *      参数名称：$cpu
     *      是否必须：是
     *      参数说明：cpu信息
     * @return array
     */
    public function getIsFeaturesSupport($plugin, $cpu)
    {
        $cpu_features_support = false;
    
        //如果获取不到cpu信息且对客户端性能有要求
        if( 'all' === $plugin['cpufeatures'] )
        {
            return true;
        }
    
        //add by zhoubin05 20170615 for 获取不到cpu信息的华为输入法下发离线语音插件 
        //(影响离线语音插件版本 1.0.0.0  因为此版本限定了cpu指令集，导致后继的业务判断会涉及到cpu的主频判断)
        if($plugin['id'] === 'com.baidu.input.plugin.kit.offlinevoicerec') {
            $cpu =  $this->getFakeCpuInfo('platform='.$_GET['platform']);
        }
        
        //取不到cpu信息直接返回不支持
        if('-1' === $cpu['isok'])
        {
            return false;
        }
    
        if(isset($cpu['features']))
        {
            $plugin_features = explode('|', $plugin['cpufeatures']);
            $cpu_features_support = (($plugin['cpufeatures'] === 'all') || ($plugin_features == array_intersect($plugin_features, $cpu['features'])));
        }
    
        return $cpu_features_support;
    }
    
    
    
    
    /**
     * store列表
     *
     *
     * @param
     *      参数名称：platform
     *      是否必须：是
     *      参数说明：平台
     *
     * @param
     *      参数名称：cpu
     *      是否必须：是
     *      参数说明：cpu
     *
     * @param
     *      参数名称：memory
     *      是否必须：是
     *      参数说明    memory
     * @return array
     */
    public function getStoreList($platform, $cpu, $memory) {
        //get page
        $page = intval($_GET['page']);
        if($page <= 0)
        {
            $page = 1;
        }
    
        $plugin_list = $this->getPluginList($platform);
        $plugin_list_count = count($plugin_list);
    
        $rmd_info = $this->getRecommendTipInfo();
    
        //取客户端两位版本号对应数值
        $input_version = substr($this->version, 0, 3);
        $client_version = $this->getVersionIntVal($input_version, 2);
       
        $first_array = array();
        $second_array = array();
        $third_array = array();
    
        //取推荐版本
        $rmd_version = $this->getRecommendVersion($rmd_info['storermd_suggest_version']);
    
        //
        $cache_first_key = self::CACHE_PLUGIN_FIRST_LIST_KEY . 'store' . $platform . $this->version;
        //先从cache里获取缓存的所以插件列表
        $first_array = GFunc::cacheGet($cache_first_key);
        $is_cache_first = true;
        if(false === $first_array)
        {
            $first_array = array();
            $is_cache_first = false;
        }
        //含有子插件的插件高低端主信息必须一致
        //推荐的插件显示在最前面
        if( !$is_cache_first && (0 !== $rmd_version) && ($client_version >= $rmd_version) )
        {
            for ($pos = 0; $pos < $plugin_list_count; $pos++) {
                if(!$this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])){
                    continue;
                }
    
                //get recommend position
                $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
    
                //不推荐
                if (self::PLUGIN_NOT_RECOMMEND_FLAG === $one_plugin['rmdpos']) {
                    continue;
                }
                $rmd_input_version = $rmd_info['storermd_suggest_version'][$one_plugin['rmdpos']-1];
                //推荐插件版本必须相等
                if($rmd_version !== $rmd_input_version){
                    continue;
                }
    
                $one_plugin['id'] = $plugin_list[$pos]['id'];
                $one_plugin['name'] = $plugin_list[$pos]['name'];
                $one_plugin['name2'] = $plugin_list[$pos]['name2'];
                $one_plugin['desc'] = $plugin_list[$pos]['desc'];
                $one_plugin['store_rmd'] = $this->getIsRecommend($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
                $one_plugin['logo_down'] = $plugin_list[$pos]['storelogo'];
                $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
                $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
                $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
    
                //保存推荐版本同客户端版本是否相等
                $one_sub['rmdequal'] = ($client_version === $rmd_input_version);
                //保存插件位置
                $one_sub['pos'] = $pos;
                //保存推荐位置
                $one_sub['rmdpos'] = $one_plugin['rmdpos'];
                $one_sub['reason'] = '';
                $one_sub['grade'] = $plugin_list[$pos]['grade'];
                $one_sub['version'] = $plugin_list[$pos]['version'];
                $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
                $one_sub['size'] = $plugin_list[$pos]['size'];
                $one_sub['md5'] = $plugin_list[$pos]['md5'];
                $one_sub['download'] = $plugin_list[$pos]['download'];
                $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
                $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
                $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            {
                $is_new = true;
                for ($i = 0; $i < count($first_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $first_array[$i]['id']) {
                        $is_new = false;

                        //等级相同的不同不同版本插件 判断推荐位置
                        $sub_pos = -1;
                        for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $first_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }

                        //没有相同grade插件则直接插入，有则判断推荐位置
                        if (-1 === $sub_pos) {
                            array_push($first_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $first_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                            }

                            //判断客户端输入法版本同推荐版本
                            if ($client_version === $rmd_input_version) {
                                //判断推荐位置
                                if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                    //更新sub class
                                    $first_array[$i]['subclasses'][$sub_pos] = $one_sub;

                                    //更新插件主信息
                                    $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                    $first_array[$i]['id'] = $one_plugin['id'];
                                    $first_array[$i]['name'] = $one_plugin['name'];
                                    $first_array[$i]['name2'] = $one_plugin['name2'];
                                    $first_array[$i]['desc'] = $one_plugin['desc'];
                                    $first_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                    $first_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                    $first_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                    $first_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                                }
                            } else {
                                if (!$first_array[$i]['subclasses'][$sub_pos]['rmdequal']) {
                                    //判断推荐位置
                                    if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                        //更新sub class
                                        $first_array[$i]['subclasses'][$sub_pos] = $one_sub;

                                        //更新插件主信息
                                        $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                        $first_array[$i]['id'] = $one_plugin['id'];
                                        $first_array[$i]['name'] = $one_plugin['name'];
                                        $first_array[$i]['name2'] = $one_plugin['name2'];
                                        $first_array[$i]['desc'] = $one_plugin['desc'];
                                        $first_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                        $first_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                        $first_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                        $first_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                                    }

                                }

                            }
                        }

                    }
                }

                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($first_array, $one_plugin);
                }
            }
    
            }
            //sort
            //按照推荐位排序
            usort($first_array, function ($a, $b) {
                if ($a['rmdpos'] == $b['rmdpos']) {
                    return 0;
                }
                return ($a['rmdpos'] > $b['rmdpos']) ? 1 : -1;
            });
                    
            //等级排序
            for ($i = 0; $i < count($first_array); $i++) {
                usort($first_array[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
                    
        }//if
    
        if(!$is_cache_first)
        {
            GFunc::cacheSet($cache_first_key, $first_array, $this->intCacheExpired);
        }
    
        //support reason
        for ($i = 0; $i < count($first_array); $i++) {   
            //非高低端
            if (1 === count($first_array[$i]['subclasses'])) {
                //不在推送区间
                $min_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if('' !== $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'])
                {
                    $max_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                }
                else
                {
                    $max_version = 100;
                }
    
                //推荐配置的版本低于支持最小版本因此提示升级输入法
                if( !(($client_version >= $min_version) && ($client_version <= $max_version)) )
                {
                    $first_array[$i]['subclasses'][0]['support'] = '0';
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }
    
                $first_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $first_array[$i]['subclasses'][0]['support']) {
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
    
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $first_array[$i]['subclasses'][0]['support'] = '1';
                        for ($j = 1; $j < count($first_array[$i]['subclasses']); $j++) {
                            $first_array[$i]['subclasses'][$j]['support'] = '0';
                            $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                            //不考虑3中包情况
                            if (1 === $j) {
                                $first_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $first_array[$i]['subclasses'][$j]['support'] = '0';
                                $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                        $first_array[$i]['subclasses'][$j]['support'] = '0';
                        $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $cache_second_key = self::CACHE_PLUGIN_SECOND_LIST_KEY . 'store' . $platform . $this->version;
        //先从cache里获取缓存的所以插件列表
        $second_array = GFunc::cacheGet($cache_second_key);
        $is_cache_second = true;
        if(false === $second_array){
            $second_array = array();
            $is_cache_second = false;
        }
       
        //正常的该版本可使用的插件，按照上线时间排序，新的插件显示在前面
        for ($pos = 0; !$is_cache_second && ($pos < $plugin_list_count); $pos++) {
            //get recommend position
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
    
            $min_version = $plugin_list[$pos]['input_version_min_push'];
            if ('' !== $plugin_list[$pos]['input_version_max_push']) {
                $max_version = $plugin_list[$pos]['input_version_max_push'];
            } else {
                $max_version = 100;
            }
    
            if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                continue;
            }
                
            //
            if(!$this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])){
                continue;
            }
                
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
                
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
            $one_plugin['name2'] = $plugin_list[$pos]['name2'];
            $one_plugin['desc'] = $plugin_list[$pos]['desc'];
            $one_plugin['store_rmd'] = '0';
            $one_plugin['logo_down'] = $plugin_list[$pos]['storelogo'];
            $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
            $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
            $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['reason'] = '';
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['md5'] = $plugin_list[$pos]['md5'];
            $one_sub['download'] = $plugin_list[$pos]['download'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                for ($i = 0; $i < count($second_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $second_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $second_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($second_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $second_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $second_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $second_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                $second_array[$i]['id'] = $one_plugin['id'];
                                $second_array[$i]['name'] = $one_plugin['name'];
                                $second_array[$i]['name2'] = $one_plugin['name2'];
                                $second_array[$i]['desc'] = $one_plugin['desc'];
                                $second_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                $second_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                $second_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                $second_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($second_array, $one_plugin);
                }
            }
                
            //等级排序
            for ($i = 0; $i < count($second_array); $i++) {
                usort($second_array[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
        }
    
        if(!$is_cache_second)
        {
            GFunc::cacheSet($cache_second_key, $second_array, $this->intCacheExpired);
        }
    
        //support reason
        for ($i = 0; $i < count($second_array); $i++) {
            //非高低端
            if (1 === count($second_array[$i]['subclasses'])) {
                        
                $second_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $second_array[$i]['subclasses'][0]['support']) {
                    $second_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $second_array[$i]['subclasses'][0]['support'] = '1';
                        for ($j = 1; $j < count($second_array[$i]['subclasses']); $j++) {
                            $second_array[$i]['subclasses'][$j]['support'] = '0';
                            $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                            if (1 === $j) {
                                $second_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $second_array[$i]['subclasses'][$j]['support'] = '0';
                                $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                        $second_array[$i]['subclasses'][$j]['support'] = '0';
                        $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $cache_third_key = self::CACHE_PLUGIN_THIRD_LIST_KEY . 'store' . $platform . $this->version;
        //先从cache里获取缓存的所以插件列表
        $third_array = GFunc::cacheGet($cache_third_key);
        $is_cache_third = true;
        if(false === $third_array)
        {
            $third_array = array();
            $is_cache_third = false;
        }
    
        //不可使用但是推送的插件（标注支持的输入法版本，刺激用户升级）
        for ($pos = 0; !$is_cache_third && ($pos < $plugin_list_count); $pos++) {
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
    
            if ('' === $plugin_list[$pos]['input_version_sugest_update']) {
                continue;
            }
    
            $min_version = $plugin_list[$pos]['input_version_sugest_update'];
            if (!($client_version >= $min_version)) {
                continue;
            }
                
            //
            if(!$this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])){
                continue;
            }
                
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
                
            if (!$is_repeat) {
                foreach($second_array as $second_plugin) {
                    if ($second_plugin['id'] === $plugin_list[$pos]['id']) {
                        $is_repeat = true;
                        break;
                    }
                }
            }
                
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
            $one_plugin['name2'] = $plugin_list[$pos]['name2'];
            $one_plugin['desc'] = $plugin_list[$pos]['desc'];
            $one_plugin['store_rmd'] = '0';
            $one_plugin['logo_down'] = $plugin_list[$pos]['storelogo'];
            $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
            $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
            $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['reason'] = '';
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['md5'] = $plugin_list[$pos]['md5'];
            $one_sub['download'] = $plugin_list[$pos]['download'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                for ($i = 0; $i < count($third_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $third_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $third_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($third_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $third_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $third_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $third_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                $third_array[$i]['id'] = $one_plugin['id'];
                                $third_array[$i]['name'] = $one_plugin['name'];
                                $third_array[$i]['name2'] = $one_plugin['name2'];
                                $third_array[$i]['desc'] = $one_plugin['desc'];
                                $third_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                $third_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                $third_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                $third_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($third_array, $one_plugin);
                }
            }
                
            //等级排序
            for ($i = 0; $i < count($third_array); $i++) {
                usort($third_array[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
        }
    
        if(!$is_cache_third)
        {
            GFunc::cacheSet($cache_third_key, $third_array, $this->intCacheExpired);
        }
        //support reason
        for ($i = 0; $i < count($third_array); $i++) {
            //非高低端
            if (1 === count($third_array[$i]['subclasses'])) {
                //不在推送区间
                $min_version = $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if ('' !== $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_max_push']) {
                    $max_version = $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                } else {
                    $max_version = 100;
                }
                //不在推荐区间
                if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                    $third_array[$i]['subclasses'][0]['support'] = '0';
                    $third_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }
    
                $third_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $third_array[$i]['subclasses'][0]['support']) {
                    $third_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $third_array[$i]['subclasses'][0]['support'] = '1';
                        for ($j = 1; $j < count($third_array[$i]['subclasses']); $j++) {
                            $third_array[$i]['subclasses'][$j]['support'] = '0';
                            $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                            if (1 === $j) {
                                $third_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $third_array[$i]['subclasses'][$j]['support'] = '0';
                                $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                        $third_array[$i]['subclasses'][$j]['support'] = '0';
                        $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
        $sort_store_list = array();
        $sort_store_list = array_merge($first_array, $second_array);
        $sort_store_list = array_merge($sort_store_list, $third_array);
        $plugin_count = count($sort_store_list);
    
        $store_list = array();
        $store_list['lastpage'] = (($page * self::PLUGIN_STORE_ONE_PAGE_COUNT) >= $plugin_count) ? '1' : '0';
        $store_list['plugins'] = array();
        $store_list['app_rcmd'] = $this->getIsShowBanner();
    
        $begin = ($page - 1) * self::PLUGIN_STORE_ONE_PAGE_COUNT;
        $end = (($page * self::PLUGIN_STORE_ONE_PAGE_COUNT) < $plugin_count) ? ($page * self::PLUGIN_STORE_ONE_PAGE_COUNT) : $plugin_count;
    
        for($i = $begin; $i < $end; $i++)
        {
            array_push($store_list['plugins'], $sort_store_list[$i]);
    }
    
        //去掉排序字段
        for ($i = 0; $i < count($store_list['plugins']); $i++) {
            
            for ($j = 0; $j < count($store_list['plugins'][$i]['subclasses']); $j++) {
            //$store_list['plugins'][$i]['subclasses'][$j]['version_name'] = $this->getVersionString($store_list['plugins'][$i]['subclasses'][$j]['version_name']);
                $store_list['plugins'][$i]['subclasses'][$j]['version_name'] = $plugin_list[$store_list['plugins'][$i]['subclasses'][$j]['pos']]['version_name_str'];
            
                unset($store_list['plugins'][$i]['subclasses'][$j]['grade']);
                unset($store_list['plugins'][$i]['subclasses'][$j]['pos']);
                unset($store_list['plugins'][$i]['subclasses'][$j]['rmdequal']);
                unset($store_list['plugins'][$i]['subclasses'][$j]['rmdpos']);
            }
        }
    
        //去掉排序字段
        for ($i = 0; $i < count($store_list['plugins']); $i++) {
           unset($store_list['plugins'][$i]['rmdpos']);
        }
        
        return $store_list;
    }
    
    
    /**
     * 是否显示banner
     *
     * @return string '1' or '0'
     */
    public function getIsShowBanner(){
        $arrRmdInfo = $this->getAppRecommendInfo();   
        return $arrRmdInfo['app_rcmd'];
    }
    
    /**
     * 获取APP推荐信息
     * @return array
     */
    public function getAppRecommendInfo(){
        $arrRmdInfo = array();
    
        //get from cache
        $strOs = $this->getPhoneOS($this->platform);
        
        $strCacheKey = self::CACHE_APP_STORE_RECOMMEND_LIST_KEY . $strOs;
        $arrRmdInfo = GFunc::cacheGet($strCacheKey);
        if (false !== $arrRmdInfo){
            $arrRmdInfo['banner'] = json_decode($arrRmdInfo['banner'], true);
            $arrRmdInfo['recommend'] = json_decode($arrRmdInfo['recommend'], true);
            return $arrRmdInfo;
        }
    
        //get from db
        $arrRmdInfo = $this->getAppRecommendInfoFromDb();
        
      
        if(0 === count($arrRmdInfo)){
            $arrRmdInfo['app_rcmd'] = '0';
            $arrRmdInfo['banner'] = array();
            $arrRmdInfo['recommend'] = array();
        }else{
            $arrRmdInfo['banner'] = json_decode($arrRmdInfo['banner'], true);
            $arrRmdInfo['recommend'] = json_decode($arrRmdInfo['recommend'], true);
        }
    
        //set cache
        $arrCacheRmdInfo = array();
        $arrCacheRmdInfo['app_rcmd'] = $arrRmdInfo['app_rcmd'];
        $arrCacheRmdInfo['banner'] = json_encode($arrRmdInfo['banner']);
        $arrCacheRmdInfo['recommend'] = json_encode($arrRmdInfo['recommend']);
        GFunc::cacheSet($strCacheKey, $arrCacheRmdInfo, $this->intCacheExpired);
    
        return $arrRmdInfo;
    }
    
    
    
    /**
     * 从DB获取APP推荐信息
     * @return array
     */
    function getAppRecommendInfoFromDb() {
        $strSql = 'select * from ' . self::$strAppStoreRmdTable . ' where uid = 1';
        $arrArgs = array();
            
        $arrQueryResult = $this->getXDB()->queryf ( $strSql );
        
        if( empty($arrQueryResult)){
            return array();
        }
    
        return $arrQueryResult[0];
    }
    
    
    
    /**
     * 是否为logo菜单或商店推荐
     *
     * @param
     *      参数名称：$rmdlist
     *      是否必须：是
     *      参数说明：logo菜单或商店推荐列表
     *
     * @param
     *      参数名称：$key
     *      是否必须：是
     *      参数说明：插件key
     *
     * @return string
     */
    public function getIsRecommend($rmdlist, $key)
    {
        if(!isset($rmdlist))
        {
            return '0';
        }
    
        if(in_array($key, $rmdlist))
        {
            return '1';
        }
        else
        {
            return '0';
        }
    }
    
    /**
     * logo菜单或商店推荐位置
     *
     * @param
     *      参数名称：$rmdlist
     *      是否必须：是
     *      参数说明：logo菜单或商店推荐列表
     *
     * @param
     *      参数名称：$key
     *      是否必须：是
     *      参数说明：插件key
     *      @return string
     */
    public function getRecommendPos($rmdlist, $key)
    {
        if(!isset($rmdlist))
        {
            return self::PLUGIN_NOT_RECOMMEND_FLAG;
        }
    
        $pos = array_search($key, $rmdlist);
        if(false !== $pos)
        {
            return strval($pos + 1);
        }
        else
        {
            return self::PLUGIN_NOT_RECOMMEND_FLAG;
        }
    }
    
    
    /**
     * 从BCS获取插件列表信息
     *
     * @param
     *      参数名称：platform
     *      是否必须：是
     *      参数说明：平台
     * @param $id id
     * @return array
     */
    public function getPluginListFromBcs($platform, $id = '')
    {
        $plugin_list = array();
    
    
        if($id === ''){
            $sql = 'select * from ' . self::$_plugin_table . ' where status = ' . self::PLUGIN_ONLINE_FLAG. ' and platform = "' . $platform . '" order by cdate desc';
            $args = array();
            $plugin_list = $this->getXDB()->queryf( $sql );
        }else{
            $sql = 'select * from ' . self::$_plugin_table . ' where id="%s" and ' . ' status = ' . self::PLUGIN_ONLINE_FLAG. ' and platform = "' . $platform . '" order by cdate desc';
            $args = array($id);
            $plugin_list = $this->getXDB()->queryf( $sql, $id );
        }
        
        if(empty($plugin_list))
        {
            return array();
        }
        $plc = count($plugin_list);
        for($i = 0; $i < $plc; $i++)
        {
            $plugin_list[$i]['key'] = $plugin_list[$i]['bcskey'];
            $plugin_list[$i]['desc'] = $plugin_list[$i]['description'];
            $plugin_list[$i]['grade'] = intval($plugin_list[$i]['grade']);
            $plugin_list[$i]['version'] = intval($plugin_list[$i]['version']);
            $plugin_list[$i]['version_name'] = intval($plugin_list[$i]['version_name']);
            $plugin_list[$i]['version_name_str'] = $plugin_list[$i]['version_name_str'];
            $plugin_list[$i]['cpufreq'] = intval($plugin_list[$i]['cpufreq']);
            $plugin_list[$i]['core'] = intval($plugin_list[$i]['core']);
            $plugin_list[$i]['memory'] = intval($plugin_list[$i]['memory']);
            $plugin_list[$i]['input_version_min_push'] = intval($plugin_list[$i]['min_push']);
            $plugin_list[$i]['input_version_max_push'] = intval($plugin_list[$i]['max_push']);
            $plugin_list[$i]['input_version_min_support'] = $this->getVersionString($plugin_list[$i]['min_sup']);
            $plugin_list[$i]['input_version_max_support'] = ( 100 === intval($plugin_list[$i]['max_sup']) )? '' : $this->getVersionString($plugin_list[$i]['max_sup']);
            $plugin_list[$i]['input_version_sugest_update'] = intval($plugin_list[$i]['min_up']);
                
            //$plugin_list[$i]['download'] = $this->getJumpDownUrl($plugin_list[$i]['download'], $plugin_list[$i]['id'], $plugin_list[$i]['version'], $plugin_list[$i]['version_name_str']);
            $plugin_list[$i]['download'] = $this->getTraceUrl($plugin_list[$i]['download'], $plugin_list[$i]['id'], $plugin_list[$i]['version'], $plugin_list[$i]['version_name_str']);
            }
    
            return $plugin_list;
    }

    
    
    /**
     * 获取插件列表信息
     *
     * @param
     *      参数名称：platform
     *      是否必须：是
     *      参数说明：平台
     *      @return array
     */
    public function getPluginList($platform, $id = '')
    {
        $cache_key = self::CACHE_PLUGIN_LIST_KEY . $platform . '_' . $id;
        
        //先从cache里获取缓存的所以插件列表
        $plugin_list = GFunc::cacheZget($cache_key);
        //有时候这里缓存会获取一个true值，造成后继数据错误
        if(false === $plugin_list || true === $plugin_list) {
           $plugin_list = $this->getPluginListFromBcs($platform, $id);
            //set cache
            if(is_array($plugin_list) && count($plugin_list) > 0)
            {
                GFunc::cacheZset($cache_key, $plugin_list, $this->intCacheExpired);
            } 
            
        } 
        
        return $plugin_list;
        
    }
    
    
    /**
     * 获取两位数字版本
     * @param
     *      参数名称：$version
     *      是否必须：是
     *      参数说明：整数version 值 如52
     *
     * @return string 52转5.2
     */
    public function getVersionString($version)
    {
        $value = '';
        $version_str = strval($version);
        $len = strlen($version_str);
        for($i=0; $i < $len; $i++)
        {
            if($i === 0)
            {
                $value = substr($version_str,$i,1);
            }
            else
            {
                $value = $value . '.' . substr($version_str,$i,1);
            }
        }
    
        return $value;
    }

    
    
    /**
     * 获取是否下发离线语音
     *
     * @param $strPluginKey 插件key
     *
     * @return bool
     */
    public function getOfflineVoiceIsIssue($strPluginKey){
        $bolIsIssue = true;
        if(in_array($strPluginKey, self::$OFFLINE_VOICE_KEY_LIST) && in_array($this->version, self::$OFFLINE_VOICE_EXCEPT_VERSION_LIST)){
            $bolIsIssue = false;
        }
    
        return $bolIsIssue;
    }
    
    /**
    * 某些版本或者机型无法获取CPU信息，但又需要正常下发某些插件时，需要组织伪cpu信息以使业务流正常执行下去
    * @param $condition 条件，函数会根据不同的条件获取不同的伪cpu信息，以便需求扩展
    * 
    * @return array
    */
    public function getFakeCpuInfo($condition) {
        $cpu_info = isset($_POST['plugin']) ? $_POST['plugin'] : '' ;
    
        //by lipengcheng02:如果cpu信息为空，说明是ral方式传递的post数据，用下面的方式来获取
        if($cpu_info == ''){
            $post_info = json_decode(file_get_contents("php://input"),true);
            $cpu_info = $post_info['plugin'];
        }
        
        //平台为华为版本时
        if($condition === 'platform=p-a1-3-72' && isset($cpu_info)) {
                
            $dcpu_info = json_decode($cpu_info, true);
            if (isset($dcpu_info['cpu']))
            {
                $filter_str =  str_replace('\t', '', $dcpu_info['cpu']);
                $filter_str =  str_replace('\n', '', $filter_str);
                $filter_str =  str_replace('\v', '', $filter_str);
                $filter_str =  str_replace('\f', '', $filter_str);
                $split_arr = explode('|', $filter_str);
                
                //无法获取主频(freq)信息，但可以获取指令集(features)和核心(core)信息时
                //{"cpu":"features\t: fp asimd evtstrm aes pmull sha1 sha2 crc32 wp half thumb fastmult vfp edsp neon vfpv3 tlsi vfpv4 idiva idivt |core:8"}
                if(count($split_arr) == 2 && strpos($split_arr[0], 'freq') === false) {
                    $dcpu_info['cpu'] = 'freq : 99999999|' . $dcpu_info['cpu']; //虚构cpu主频信息，使业务流不会在此情况下因无freq信息无法下发插件（离线语音插件）
                    $cpu_info = json_encode($dcpu_info);
                }
            }
           
        }
        
        
        return $this->parseCpuInfo($cpu_info);
    }
    
    /**
     * 解析客户端cpu info
     *
     * @param
     *      参数名称：$cpu_info
     *      是否必须：是
     *      参数说明：客户端CPU信息
     *      例如{"cpu":"freq : 1600000|features\t: swp half thumb fastmult vfp edsp neon vfpv3 tls vfpv4 idiva idivt|core:1 "}
     * @return array
     */
    public function parseCpuInfo($cpu_info)
    {
        //$cpu_info = '{"cpu":"freq : 1401600|features\t: swp half thumb fastmult vfp edsp neon vfpv3 |core:1"}';
        $info = array();
    
        if(!isset($cpu_info))
        {
            $info['isok'] = '0';
            return $info;
        }
    
    
        $dcpu_info = json_decode($cpu_info, true);
        if (!isset($dcpu_info['cpu']))
        {
            $info['isok'] = '-1';
            return $info;
        }
    
        $filter_str =  str_replace('\t', '', $dcpu_info['cpu']);
        $filter_str =  str_replace('\n', '', $filter_str);
        $filter_str =  str_replace('\v', '', $filter_str);
        $filter_str =  str_replace('\f', '', $filter_str);
    
        $split_arr = explode('|', $filter_str);
        
        
        if(3 !== count($split_arr))
        {
            $info['isok'] = '-1';
            return $info;
        }
    
        $split_arr_freq = explode(':', str_replace(' ', '', $split_arr[0]));
        if(2 !== count($split_arr_freq) || !is_numeric(trim($split_arr_freq[1])))
        {
            $info['isok'] = '-1';
            return $info;
        }
    
        $info['freq'] = trim($split_arr_freq[1]);
    
        $split_arr_feat = explode(':', $split_arr[1]);
        if(2 !== count($split_arr_feat))
        {
            $info['isok'] = '-1';
            return $info;
        }
    
        $info['features'] = explode(' ', $split_arr_feat[1]);
    
        foreach($info['features'] as $k=>$v)
        {
            if($v === '')
            {
                unset($info['features'][$k]);
            }
        }
    
        if(!isset($info['features']) || (count($info['features']) === 0))
        {
            $info['isok'] = '-1';
            return $info;
        }
    
        //core
        $split_arr_core = explode(':', str_replace(' ', '', $split_arr[2]));
        if(2 !== count($split_arr_core))
        {
            $info['isok'] = '-1';
            return $info;
        }
        $info['core'] = trim($split_arr_core[1]);
    
        $info['isok'] = '1';
        return $info;
    }
    
    
    
    /**
     * 手机排除指令集是否支持
     * @param
     *      参数名称：cpufreq
     *      是否必须：是
     *      参数说明：cpu主频
     *
     * @param
     *      参数名称：cpufeatures
     *      是否必须：是
     *      参数说明：cpu指令集
     * @return array
     */
    public function getIsSupportExFeatures($plugin, $cpu, $memory)
    {
        //add by zhoubin05 20170615 for 获取不到cpu信息的华为输入法下发离线语音插件 
        //(影响离线语音插件版本 1.0.0.0  因为此版本限定了cpu内存，导致后继的业务判断会涉及到cpu的主频判断)
        if($plugin['id'] === 'com.baidu.input.plugin.kit.offlinevoicerec') {
            $cpu =  $this->getFakeCpuInfo('platform='.$_GET['platform']);
        }
        
        //如果获取不到cpu信息且对客户端性能有要求
        if( (0 === intval($plugin['memory'])) && ((0 === intval($plugin['core'])) || (1 === intval($plugin['core']))) )
        {
            return true;
        }
    
        //取不到cpu信息直接返回不支持
        if('-1' === $cpu['isok'])
        {
            return false;
        }
    
        $memory_support = false;
        $cpu_freq_support = false;
        $cpu_core_support = false;
    
        $memory_support = intval($memory) >= intval($plugin['memory']) ? true : false;
        if(!$memory_support)
        {
            return false;
        }
    
        if(isset($cpu['core']))
        {
            $cpu_core_support = intval($cpu['core']) >= intval($plugin['core']) ? true : false;
        }
    
        if(!$cpu_core_support)
        {
            return false;
        }
    
        if(isset($cpu['freq']))
        {
            $cpu_freq_support = intval($cpu['freq']) >= intval($plugin['cpufreq']) ? true : false;
        }
    
        if(!$cpu_freq_support)
        {
            return false;
        }
    
        return true;
    }
    
    
    
    
    
    /**
     * 获取trace跳转下载地址
     *
     * @param
     *      参数名称：$strUrl
     *      是否必须：是
     *      参数说明：下载地址
     *
     * @param
     *      参数名称：$strId
     *      是否必须：是
     *      参数说明：插件id
     *
     * @param
     *      参数名称：$strVersion
     *      是否必须：是
     *      参数说明：version
     *
     * @param
     *      参数名称：$strVersionName
     *      是否必须：是
     *      参数说明：version name
     *
     * @return string
     *
     */
    public function getTraceUrl($strUrl, $strId, $strVersion, $strVersionName){
        //部分用户反馈下载到99%无法安装，客户端RD发现下载文件内容被修改，导致md5不一致，验证不通过，可能是被运营商劫持
        //将http地址修改为https http://res.mi.baidu.com 修改为 https://imeres.baidu.com
        $strUrl = str_replace('http://res.mi.baidu.com', 'https://imeres.baidu.com', $strUrl);
        
        $strTraceUrl = $this->domain_v5 . 'v5/trace?url=' . urlencode($strUrl) . '&sign=' . md5($strUrl . 'iudfu(lkc#xv345y82$dsfjksa') . '&rsc_from=plugin' . '&apk_name=' . $strId . '&version=' . $strVersion . '&vername=' . $strVersionName;
        return $strTraceUrl;
    }
    
    
    /**
     * 获取推荐版本
     * @param
     *      参数名称：$version_list
     *      是否必须：是
     *      参数说明：版本列表
     *
     * @return int 两位版本数值 5.0 50
     */
    public function getRecommendVersion($version_list)
    {
        $rmd_version_list = $version_list;
        $input_version = substr($this->version, 0, 3);
        $client_version = $this->getVersionIntVal($input_version, 2);
    
        //有推荐版本
        if (in_array($client_version, $rmd_version_list))
        {
            return $client_version;
        }
        else
        {
            //没有推荐版本
            usort($rmd_version_list, function($a, $b)
            {
                if ($a == $b)
                {
                    return 0;
                }
                return ($a > $b) ? -1 : 1;
            });
                
            //取已有最高推荐版本
            foreach($rmd_version_list as $version)
            {
                if($client_version > $version)
                {
                    return $version;
                }
            }
        }
    
        //没有推荐
        return 0;
    }
    
    /**
     * 获取两位数字版本前提每位版本只有一位
     * @param
     *      参数名称：$version
     *      是否必须：是
     *      参数说明：version
     *
     * @param
     *      参数名称：$figure
     *      是否必须：是
     *      参数说明：版本位数如5.0 为 2
     * @return int
     */
    public function getVersionIntVal($version, $figure)
    {
        $value = 0;
        if(2 == $figure)
        {
            $split_version = explode('.', $version);
            if(2 === count($split_version))
            {
                $value = intval($split_version[0]) * 10 + intval($split_version[1]);
            }
        }
    
        if(4 == $figure)
        {
            $split_version = explode('.', $version);
            if(4 === count($split_version))
            {
                $value = intval($split_version[0])*1000 + intval($split_version[1])*100 + intval($split_version[2])*10 + intval($split_version[3]);
            }
        }
    
        return $value;
    }
    
    /**
     * logo菜单、商店推荐列表
     * @return array
     */
    public function getRecommendTipInfo()
    {
        $rmd_info = array();
    
        //get from cache
        $cache_key = self::CACHE_PLUGIN_RECOMMEND_TIP_KEY;
        
        //先从cache里获取缓存的所以插件列表
        //$rmd_info = GFunc::cacheGet($cache_key);
        $bolStatus = false;
        $rmd_info = $this->apcCache->get($cache_key, $bolStatus);
    
        //cache 没有从bcs获取
        if(null !== $rmd_info)
        {
            return $rmd_info;
        }
    
        //get from db
        $sql = 'select * from ' . self::$_plugin_rmd_table . ' where uid = 1';
        $args = array();
       
        $query_result = $this->getXDB()->queryf ( $sql );
    
        if(!empty($query_result))
        {
            $rmd_info = $query_result[0];
            $rmd_info['logormdlist'] = json_decode($rmd_info['logormdlist'], true);
            $rmd_info['storermdlist'] = json_decode($rmd_info['storermdlist'], true);
            $rmd_info['logormd_suggest_version'] = json_decode($rmd_info['logormd_suggest_version'], true);
            $rmd_info['storermd_suggest_version'] = json_decode($rmd_info['storermd_suggest_version'], true);
            //set cache
            $this->apcCache->set($cache_key, $rmd_info, $this->intCacheExpired);
            //GFunc::cacheSet($cache_key, $rmd_info, $this->intCacheExpired);
        }
        else
        {
            $rmd_info['tiplog'] = '0';
            $rmd_info['tiplogversion'] = '0';
            $rmd_info['tipbear'] = '0';
            $rmd_info['tip_not_support'] = self::DEFAULT_TIP_NOT_SUPPORT;
            $rmd_info['tip_update_input'] = self::DEFAULT_TIP_UPDATE;
            $rmd_info['logormdlist'] = array();
            $rmd_info['storermdlist'] = array();
            $rmd_info['logormd_suggest_version'] = array();
            $rmd_info['storermd_suggest_version'] = array();
            $rmd_info['logo'] = '';
            $rmd_info['logo480'] = '';
            $rmd_info['logo720'] = '';
            $rmd_info['logo1080'] = '';
            $rmd_info['logo2x'] = '';
            $rmd_info['logo3x'] = '';
            $rmd_info['is_color_wash'] = 0;
        }
    
        return $rmd_info;
    }
        
    
    
    /**
     * 根据平台号获取手机操作系统类型
     * @param $platform string 手机平台号
     *
     * @return string (ios, symbian, mac, android)
     */
    private function getPhoneOS($platform) {
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
     * @return db
     */
    private function getDB(){
        return DBConn::getDb();
    }

    /**
     *新库
     * @return db
     */
    private function getXDB(){
        return DBConn::getXdb();
    }


    
    /**
     * @route({"POST","/detail/"})
     * 获取插件详情
     * 插件支持多版本并存，由于兼容之前版本id 为apk包名,因此需要将所有插件按照logo菜单算法计算出插件列表，然后从中选取
     * @return array
     */
    public function detailAction() {
        //for test3
        //$this->version = "5.0.0.1";
        //获取type
        $request_type = $_GET['type'];
        if(!isset($request_type))
        {
            header('X-PHP-Response-Code: '. 400, true, 400);
            exit();
        }
    
        //获取plugin id
        $id = $_GET['id'];
        if(!isset($id))
        {
            header('X-PHP-Response-Code: '. 400, true, 400);
            exit();
        }
      
        $platform = $this->strOs;
        $cpu_info = '';
        $cpu = $this->parseCpuInfo($cpu_info);
        $memory = 512;
    
        $plugin_list = $this->getPluginList($platform);
        $plugin_list_count = count($plugin_list);

        $rmd_info = $this->getRecommendTipInfo();
     
        //取客户端两位版本号对应数值
        $input_version = substr($this->version, 0, 3);
        $client_version = $this->getVersionIntVal($input_version, 2);
    
        $first_array = array();
        $second_array = array();
        $third_array = array();
    
        //取推荐版本
        $rmd_version = $this->getRecommendVersion($rmd_info['logormd_suggest_version']);
    
        //
        $cache_key = self::CACHE_PLUGIN_FIRST_LIST_KEY . 'detail' . $platform . $input_version;
        //先从cache里获取缓存的所以插件列表
        $sort_plugin_list = GFunc::cacheGet($cache_key);
        $is_cache = true;
        if(false === $sort_plugin_list) {
            $sort_plugin_list = array();
            $is_cache = false;
        }
      
        //含有子插件的插件高低端主信息必须一致
        //推荐的插件显示在最前面
        if( !$is_cache && (0 !== $rmd_version) && ($client_version >= $rmd_version) )
        {  
            for ($pos = 0; $pos < $plugin_list_count; $pos++) {
                //get recommend position
                $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
    
                //不推荐
                if (self::PLUGIN_NOT_RECOMMEND_FLAG === $one_plugin['rmdpos']) {
                    continue;
                }
                $rmd_input_version = $rmd_info['logormd_suggest_version'][$one_plugin['rmdpos']-1];
                //推荐插件版本必须相等
                if($rmd_version !== $rmd_input_version){
                    continue;
                }
    
                $one_plugin['id'] = $plugin_list[$pos]['id'];
                $one_plugin['name'] = $plugin_list[$pos]['name'];
                $one_plugin['desc'] = $plugin_list[$pos]['desc'];
                $one_plugin['logo'] = $plugin_list[$pos]['logo'];
                $one_plugin['logo55'] = (null === $plugin_list[$pos]['logo55'])? "" : $plugin_list[$pos]['logo55'];
                $one_plugin['store_logo'] = $plugin_list[$pos]['storelogo'];
                $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
                $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
    
                //保存推荐版本同客户端版本是否相等
                $one_sub['rmdequal'] = ($client_version === $rmd_input_version);
                //保存插件位置
                $one_sub['pos'] = $pos;
                //保存推荐位置
                $one_sub['rmdpos'] = $one_plugin['rmdpos'];
                $one_sub['grade'] = $plugin_list[$pos]['grade'];
                $one_sub['version'] = $plugin_list[$pos]['version'];
                $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
                $one_sub['size'] = $plugin_list[$pos]['size'];
                $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
                $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
                $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
                {
                    $is_new = true;
                for ($i = 0; $i < count($first_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $first_array[$i]['id']) {
                        $is_new = false;

                        //等级相同的不同不同版本插件 判断推荐位置
                        $sub_pos = -1;
                        for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $first_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }

                        //没有相同grade插件则直接插入，有则判断推荐位置
                        if (-1 === $sub_pos) {
                            array_push($first_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $first_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                            }

                            //判断客户端输入法版本同推荐版本
                            if ($client_version === $rmd_input_version) {
                                //判断推荐位置
                                if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                    //更新sub class
                                    $first_array[$i]['subclasses'][$sub_pos] = $one_sub;

                                    //更新插件主信息
                                    $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                    $first_array[$i]['id'] = $one_plugin['id'];
                                    $first_array[$i]['name'] = $one_plugin['name'];
                                    $first_array[$i]['desc'] = $one_plugin['desc'];
                                    $first_array[$i]['logo'] = $one_plugin['logo'];
                                    $first_array[$i]['logo55'] = $one_plugin['logo55'];
                                    $first_array[$i]['store_logo'] = $one_plugin['store_logo'];
                                    $first_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                    $first_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                                }
                            } else {
                                if (!$first_array[$i]['subclasses'][$sub_pos]['rmdequal']) {
                                    //判断推荐位置
                                    if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                        //更新sub class
                                        $first_array[$i]['subclasses'][$sub_pos] = $one_sub;

                                        //更新插件主信息
                                        $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                        $first_array[$i]['id'] = $one_plugin['id'];
                                        $first_array[$i]['name'] = $one_plugin['name'];
                                        $first_array[$i]['desc'] = $one_plugin['desc'];
                                        $first_array[$i]['logo'] = $one_plugin['logo'];
                                        $first_array[$i]['logo55'] = $one_plugin['logo55'];
                                        $first_array[$i]['store_logo'] = $one_plugin['store_logo'];
                                        $first_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                        $first_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                                    }

                                }

                            }
                        }

                    }
                }

                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($first_array, $one_plugin);
                }
                }
    
            }
        }//if
    
    
        //正常的该版本可使用的插件，按照上线时间排序，新的插件显示在前面
        for ($pos = 0; ($pos < $plugin_list_count) && !$is_cache; $pos++) {
            //get recommend position
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
    
            $min_version = $plugin_list[$pos]['input_version_min_push'];
            if ('' !== $plugin_list[$pos]['input_version_max_push']) {
                $max_version = $plugin_list[$pos]['input_version_max_push'];
            } else {
                $max_version = 100;
            }
    
            if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                continue;
            }
    
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
    
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
            $one_plugin['desc'] = $plugin_list[$pos]['desc'];
            $one_plugin['logo'] = $plugin_list[$pos]['logo'];
            $one_plugin['logo55'] = (null === $plugin_list[$pos]['logo55'])? "" : $plugin_list[$pos]['logo55'];
            $one_plugin['store_logo'] = $plugin_list[$pos]['storelogo'];
            $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
            $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                for ($i = 0; $i < count($second_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $second_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $second_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($second_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $second_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $second_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $second_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                $second_array[$i]['id'] = $one_plugin['id'];
                                $second_array[$i]['name'] = $one_plugin['name'];
                                $second_array[$i]['desc'] = $one_plugin['desc'];
                                $second_array[$i]['logo'] = $one_plugin['logo'];
                                $second_array[$i]['logo55'] = $one_plugin['logo55'];
                                $second_array[$i]['store_logo'] = $one_plugin['store_logo'];
                                $second_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                $second_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($second_array, $one_plugin);
                }
            }
    
        }
    
        //不可使用但是推送的插件（标注支持的输入法版本，刺激用户升级）
        for ($pos = 0; ($pos < $plugin_list_count) && !$is_cache; $pos++) {
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
    
            if ('' === $plugin_list[$pos]['input_version_sugest_update']) {
                continue;
            }
    
            $min_version = $plugin_list[$pos]['input_version_sugest_update'];
            if (!($client_version >= $min_version)) {
                continue;
            }
    
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
    
            if (!$is_repeat) {
                foreach($second_array as $second_plugin) {
                    if ($second_plugin['id'] === $plugin_list[$pos]['id']) {
                        $is_repeat = true;
                        break;
                    }
                }
            }
    
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
            $one_plugin['desc'] = $plugin_list[$pos]['desc'];
            $one_plugin['logo'] = $plugin_list[$pos]['logo'];
            $one_plugin['logo55'] = (null === $plugin_list[$pos]['logo55'])? "" : $plugin_list[$pos]['logo55'];
            $one_plugin['store_logo'] = $plugin_list[$pos]['storelogo'];
            $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
            $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                for ($i = 0; $i < count($third_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $third_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $third_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($third_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $third_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $third_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $third_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                $third_array[$i]['id'] = $one_plugin['id'];
                                $third_array[$i]['name'] = $one_plugin['name'];
                                $third_array[$i]['desc'] = $one_plugin['desc'];
                                $third_array[$i]['logo'] = $one_plugin['logo'];
                                $third_array[$i]['logo55'] = $one_plugin['logo55'];
                                $third_array[$i]['store_logo'] = $one_plugin['store_logo'];
                                $third_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                $third_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($third_array, $one_plugin);
                }
            }
    
        }
    
        if(!$is_cache)
        {
            $sort_plugin_list = array();
            $sort_plugin_list = array_merge($first_array, $second_array);
            $sort_plugin_list = array_merge($sort_plugin_list, $third_array);
    
            //等级排序
            for ($i = 0; $i < count($sort_plugin_list); $i++) {
                usort($sort_plugin_list[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
    
    
            //去掉排序字段
            for ($i = 0; $i < count($sort_plugin_list); $i++) {
                unset($sort_plugin_list[$i]['rmdpos']);
            }
    
            for ($i = 0; $i < count($sort_plugin_list); $i++) {
                //去掉排序字段
                for ($j = 0; $j < count($sort_plugin_list[$i]['subclasses']); $j++) {
                    //$sort_plugin_list[$i]['subclasses'][$j]['version_name'] = $this->getVersionString($sort_plugin_list[$i]['subclasses'][$j]['version_name']);
                    $sort_plugin_list[$i]['subclasses'][$j]['version_name'] = $plugin_list[$sort_plugin_list[$i]['subclasses'][$j]['pos']]['version_name_str'];
                        
                    unset($sort_plugin_list[$i]['subclasses'][$j]['grade']);
                    unset($sort_plugin_list[$i]['subclasses'][$j]['pos']);
                    unset($sort_plugin_list[$i]['subclasses'][$j]['rmdequal']);
                    unset($sort_plugin_list[$i]['subclasses'][$j]['rmdpos']);
                }
            }
            GFunc::cacheSet($cache_key , $sort_plugin_list, $this->intCacheExpired);
        }
    
        $plugin_detail = array();
        foreach ($sort_plugin_list as $plugin)
        {
           
            if($plugin['id'] === $id)
            {
                $plugin_detail = $plugin;
            }
        }

        if(0 === count($plugin_detail))
        {
            header('X-PHP-Response-Code: '. 404, true, 404);
            exit();
        }
       
        $detail['detail'] = $plugin_detail;
    
        return $detail;
    }
    
    
    /**
     * @route({"GET","/info/"})
     * 获取插件信息
     * 插件支持多版本并存，由于兼容之前版本id 为apk包名,因此需要将所有插件按照logo菜单算法计算出插件列表，然后从中选取
     * @return array
     */
    public function infoAction(){
        //分平台，android和ios
        $platform = $this->strOs;
    
        $cpu_info = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    
        //for test1 test2
        //$this->version = "5.0.0.1";
        //$cpu_info = '{"cpu":"freq : 1401600|features\t: swp half thumb fastmult vfp edsp neon vfpv3 |core:1"}';
    
        $version = isset($_REQUEST['version']) ? $_REQUEST['version'] : ''; 
    
        $memory = isset($_REQUEST['mem']) ? $_REQUEST['mem'] : '0';  
        $cpu = $this->parseCpuInfo($cpu_info);
    
        //获取plugin id
        $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';  
    
        $arrPluginInfo = $this->getPluginInfo($id, $platform, $version, $cpu, $memory);
        return $arrPluginInfo;
    }
    
    
    /**
     * 获取插件信息
     * 算法同插件商店列表相同
     * @param $id plugin id
     * @param $platform 平台
     * @param $cpu cpu
     * @param $memory memory
     * @return array
     */
    private function getPluginInfo($id, $platform, $version, $cpu, $memory) {
        $plugin_list = $this->getPluginList($platform, $id);
        $plugin_list_count = count($plugin_list);
    
        $rmd_info = $this->getRecommendTipInfo();
    
        //取客户端两位版本号对应数值
        $input_version = substr($version, 0, 3);
        $client_version = $this->getVersionIntVal($input_version, 2);
    
        $first_array = array();
        $second_array = array();
        $third_array = array();
    
        //取推荐版本
        $rmd_version = $this->getRecommendVersion($rmd_info['storermd_suggest_version']);
    
        //
        $cache_first_key = self::CACHE_PLUGIN_FIRST_LIST_KEY . 'info' . $platform . $input_version . $id;
        //先从cache里获取缓存的所以插件列表
        $first_array = GFunc::cacheGet($cache_first_key);
        $is_cache_first = true;
        if(false === $first_array)
        {
            $first_array = array();
            $is_cache_first = false;
        }
        //含有子插件的插件高低端主信息必须一致
        //推荐的插件显示在最前面
        if( !$is_cache_first && (0 !== $rmd_version) && ($client_version >= $rmd_version) )
        {
            for ($pos = 0; $pos < $plugin_list_count; $pos++) {
                if(!$this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])){
                    continue;
                }
    
                //get recommend position
                $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
    
                //不推荐
                if (self::PLUGIN_NOT_RECOMMEND_FLAG === $one_plugin['rmdpos']) {
                    continue;
                }
                $rmd_input_version = $rmd_info['storermd_suggest_version'][$one_plugin['rmdpos']-1];
                //推荐插件版本必须相等
                if($rmd_version !== $rmd_input_version){
                    continue;
                }
    
                $one_plugin['id'] = $plugin_list[$pos]['id'];
                $one_plugin['name'] = $plugin_list[$pos]['name'];
                $one_plugin['name2'] = $plugin_list[$pos]['name2'];
                $one_plugin['desc'] = $plugin_list[$pos]['desc'];
                $one_plugin['store_rmd'] = $this->getIsRecommend($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
                $one_plugin['logo_down'] = $plugin_list[$pos]['storelogo'];
                $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
                $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
                $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
    
                //保存推荐版本同客户端版本是否相等
                $one_sub['rmdequal'] = ($client_version === $rmd_input_version);
                //保存插件位置
                $one_sub['pos'] = $pos;
                //保存推荐位置
                $one_sub['rmdpos'] = $one_plugin['rmdpos'];
                $one_sub['reason'] = '';
                $one_sub['grade'] = $plugin_list[$pos]['grade'];
                $one_sub['version'] = $plugin_list[$pos]['version'];
                $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
                $one_sub['size'] = $plugin_list[$pos]['size'];
                $one_sub['md5'] = $plugin_list[$pos]['md5'];
                $one_sub['download'] = $plugin_list[$pos]['download'];
                $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
                $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
                $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
                $is_new = true;
                $intFirstCountI = count($first_array);
                for ($i = 0; $i < $intFirstCountI; $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $first_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 判断推荐位置
                        $sub_pos = -1;
                        $intFirstSubCountJ = count($first_array[$i]['subclasses']);
                        for ($j = 0; $j < $intFirstSubCountJ; $j++) {
                            if ($plugin_list[$pos]['grade'] === $first_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则判断推荐位置
                        if (-1 === $sub_pos) {
                            array_push($first_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $first_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                            }
    
                            //判断客户端输入法版本同推荐版本
                            if ($client_version === $rmd_input_version) {
                                //判断推荐位置
                                if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                    //更新sub class
                                    $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                    //更新插件主信息
                                    $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                    $first_array[$i]['id'] = $one_plugin['id'];
                                    $first_array[$i]['name'] = $one_plugin['name'];
                                    $first_array[$i]['name2'] = $one_plugin['name2'];
                                    $first_array[$i]['desc'] = $one_plugin['desc'];
                                    $first_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                    $first_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                    $first_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                    $first_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                                }
                            } else {
                                if (!$first_array[$i]['subclasses'][$sub_pos]['rmdequal']) {
                                    //判断推荐位置
                                    if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                        //更新sub class
                                        $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                        //更新插件主信息
                                        $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                        $first_array[$i]['id'] = $one_plugin['id'];
                                        $first_array[$i]['name'] = $one_plugin['name'];
                                        $first_array[$i]['name2'] = $one_plugin['name2'];
                                        $first_array[$i]['desc'] = $one_plugin['desc'];
                                        $first_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                        $first_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                        $first_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                        $first_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                                    }
    
                                }
    
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($first_array, $one_plugin);
                }
            }
            //sort
            //按照推荐位排序
            usort($first_array, function ($a, $b) {
                if ($a['rmdpos'] == $b['rmdpos']) {
                    return 0;
                }
                return ($a['rmdpos'] > $b['rmdpos']) ? 1 : -1;
            });
                    
            //等级排序
            $intFirstPosI = count($first_array);
            for ($i = 0; $i < $intFirstPosI; $i++) {
                usort($first_array[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
                    
        }//if
    
        if(!$is_cache_first)
        {
            GFunc::cacheSet($cache_first_key, $first_array, $this->intCacheExpired);
        }
    
        //support reason
        $intFirstReasonI = count($first_array);
        for ($i = 0; $i < $intFirstReasonI; $i++) {
            //非高低端
            if (1 === count($first_array[$i]['subclasses'])) {
                //不在推送区间
                $min_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if('' !== $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'])
                {
                    $max_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                }
                else
                {
                    $max_version = 100;
                }
    
                //推荐配置的版本低于支持最小版本因此提示升级输入法
                if( !(($client_version >= $min_version) && ($client_version <= $max_version)) )
                {
                    $first_array[$i]['subclasses'][0]['support'] = '0';
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }
    
                $first_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $first_array[$i]['subclasses'][0]['support']) {
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
    
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $first_array[$i]['subclasses'][0]['support'] = '1';
                        $intFirstSubJ = count($first_array[$i]['subclasses']);
                        for ($j = 1; $j < $intFirstSubJ; $j++) {
                            $first_array[$i]['subclasses'][$j]['support'] = '0';
                            $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        $intFirstSubJ = count($first_array[$i]['subclasses']);
                        for ($j = 0; $j < $intFirstSubJ; $j++) {
                            //不考虑3中包情况
                            if (1 === $j) {
                                $first_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $first_array[$i]['subclasses'][$j]['support'] = '0';
                                $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    $intFirstSubI = count($first_array[$i]['subclasses']);
                    for ($j = 0; $j < $intFirstSubI; $j++) {
                        $first_array[$i]['subclasses'][$j]['support'] = '0';
                        $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $cache_second_key = self::CACHE_PLUGIN_SECOND_LIST_KEY . 'info' . $platform . $input_version . $id;
        //先从cache里获取缓存的所以插件列表
        $second_array = GFunc::cacheGet($cache_second_key);
        $is_cache_second = true;
        if(false === $second_array){
            $second_array = array();
            $is_cache_second = false;
        }
    
        //正常的该版本可使用的插件，按照上线时间排序，新的插件显示在前面
        for ($pos = 0; !$is_cache_second && ($pos < $plugin_list_count); $pos++) {
            //get recommend position
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
    
            $min_version = $plugin_list[$pos]['input_version_min_push'];
            if ('' !== $plugin_list[$pos]['input_version_max_push']) {
                $max_version = $plugin_list[$pos]['input_version_max_push'];
            } else {
                $max_version = 100;
            }
    
            if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                continue;
            }
    
            //
            if(!$this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])){
                continue;
            }
    
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
    
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
            $one_plugin['name2'] = $plugin_list[$pos]['name2'];
            $one_plugin['desc'] = $plugin_list[$pos]['desc'];
            $one_plugin['store_rmd'] = '0';
            $one_plugin['logo_down'] = $plugin_list[$pos]['storelogo'];
            $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
            $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
            $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['reason'] = '';
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['md5'] = $plugin_list[$pos]['md5'];
            $one_sub['download'] = $plugin_list[$pos]['download'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                $intSecondI = count($second_array);
                for ($i = 0; $i < $intSecondI; $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $second_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        $intSecondSubJ = count($second_array[$i]['subclasses']);
                        for ($j = 0; $j < $intSecondSubJ; $j++) {
                            if ($plugin_list[$pos]['grade'] === $second_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($second_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $second_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $second_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $second_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                $second_array[$i]['id'] = $one_plugin['id'];
                                $second_array[$i]['name'] = $one_plugin['name'];
                                $second_array[$i]['name2'] = $one_plugin['name2'];
                                $second_array[$i]['desc'] = $one_plugin['desc'];
                                $second_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                $second_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                $second_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                $second_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($second_array, $one_plugin);
                }
            }
    
            //等级排序
            $intSecondSortI = count($second_array);
            for ($i = 0; $i < $intSecondSortI; $i++) {
                usort($second_array[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
        }
    
        if(!$is_cache_second)
        {
            GFunc::cacheSet($cache_second_key, $second_array, $this->intCacheExpired);
        }
    
        //support reason
        $intSecondReasonI = count($second_array);
        for ($i = 0; $i < $intSecondReasonI; $i++) {
            //非高低端
            if (1 === count($second_array[$i]['subclasses'])) {
                $second_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $second_array[$i]['subclasses'][0]['support']) {
                    $second_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $second_array[$i]['subclasses'][0]['support'] = '1';
                        $intSecondSubJ = count($second_array[$i]['subclasses']);
                        for ($j = 1; $j < $intSecondSubJ; $j++) {
                            $second_array[$i]['subclasses'][$j]['support'] = '0';
                            $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        $intSecondSubJ = count($second_array[$i]['subclasses']);
                        for ($j = 0; $j < $intSecondSubJ; $j++) {
                            if (1 === $j) {
                                $second_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $second_array[$i]['subclasses'][$j]['support'] = '0';
                                $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    $intSecondSubJ = count($second_array[$i]['subclasses']);
                    for ($j = 0; $j < $intSecondSubJ; $j++) {
                        $second_array[$i]['subclasses'][$j]['support'] = '0';
                        $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $cache_third_key = self::CACHE_PLUGIN_THIRD_LIST_KEY . 'info' . $platform . $input_version . $id;
        //先从cache里获取缓存的所以插件列表
        $third_array = GFunc::cacheGet($cache_third_key);
        $is_cache_third = true;
        if(false === $third_array)
        {
            $third_array = array();
            $is_cache_third = false;
        }
    
        //不可使用但是推送的插件（标注支持的输入法版本，刺激用户升级）
        for ($pos = 0; !$is_cache_third && ($pos < $plugin_list_count); $pos++) {
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['storermdlist'], $plugin_list[$pos]['key']);
    
            if ('' === $plugin_list[$pos]['input_version_sugest_update']) {
                continue;
            }
    
            $min_version = $plugin_list[$pos]['input_version_sugest_update'];
            if (!($client_version >= $min_version)) {
                continue;
            }
    
            //
            if(!$this->getOfflineVoiceIsIssue($plugin_list[$pos]['id'])){
                continue;
            }
    
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
    
            if (!$is_repeat) {
                foreach($second_array as $second_plugin) {
                    if ($second_plugin['id'] === $plugin_list[$pos]['id']) {
                        $is_repeat = true;
                        break;
                    }
                }
            }
    
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
            $one_plugin['name2'] = $plugin_list[$pos]['name2'];
            $one_plugin['desc'] = $plugin_list[$pos]['desc'];
            $one_plugin['store_rmd'] = '0';
            $one_plugin['logo_down'] = $plugin_list[$pos]['storelogo'];
            $one_plugin['thum1_down'] = $plugin_list[$pos]['thum1'];
            $one_plugin['thum2_down'] = $plugin_list[$pos]['thum2'];
            $one_plugin['pub_item_type'] = $plugin_list[$pos]['pub_item_type'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['reason'] = '';
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['md5'] = $plugin_list[$pos]['md5'];
            $one_sub['download'] = $plugin_list[$pos]['download'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                $intThirdI = count($third_array);
                for ($i = 0; $i < $intThirdI; $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $third_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        $intThirdSubJ = count($third_array[$i]['subclasses']);
                        for ($j = 0; $j < $intThirdSubJ; $j++) {
                            if ($plugin_list[$pos]['grade'] === $third_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($third_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $third_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $third_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $third_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                $third_array[$i]['id'] = $one_plugin['id'];
                                $third_array[$i]['name'] = $one_plugin['name'];
                                $third_array[$i]['name2'] = $one_plugin['name2'];
                                $third_array[$i]['desc'] = $one_plugin['desc'];
                                $third_array[$i]['store_rmd'] = $one_plugin['store_rmd'];
                                $third_array[$i]['logo_down'] = $one_plugin['logo_down'];
                                $third_array[$i]['thum1_down'] = $one_plugin['thum1_down'];
                                $third_array[$i]['thum2_down'] = $one_plugin['thum2_down'];
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($third_array, $one_plugin);
                }
            }
    
            //等级排序
            $intThirdSortI = count($third_array);
            for ($i = 0; $i < $intThirdSortI; $i++) {
                usort($third_array[$i]['subclasses'], function ($a, $b) {
                    if ($a['grade'] == $b['grade']) {
                        return 0;
                    }
                    return ($a['grade'] > $b['grade']) ? 1 : -1;
                });
            }
        }
    
        if(!$is_cache_third)
        {
            GFunc::cacheSet($cache_third_key, $third_array, $this->intCacheExpired);
            
        }
    
        //support reason
        $intThirdReasonI = count($third_array);
        for ($i = 0; $i < $intThirdReasonI; $i++) {
            //非高低端
            if (1 === count($third_array[$i]['subclasses'])) {
                //不在推送区间
                $min_version = $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if ('' !== $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_max_push']) {
                    $max_version = $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                } else {
                    $max_version = 100;
                }
                //不在推荐区间
                if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                    $third_array[$i]['subclasses'][0]['support'] = '0';
                    $third_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }
    
                $third_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $third_array[$i]['subclasses'][0]['support']) {
                    $third_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $third_array[$i]['subclasses'][0]['support'] = '1';
                        $intThridSubJ = count($third_array[$i]['subclasses']);
                        for ($j = 1; $j < $intThridSubJ; $j++) {
                            $third_array[$i]['subclasses'][$j]['support'] = '0';
                            $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        $intThridSubJ = count($third_array[$i]['subclasses']);
                        for ($j = 0; $j < $intThridSubJ; $j++) {
                            if (1 === $j) {
                                $third_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $third_array[$i]['subclasses'][$j]['support'] = '0';
                                $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    $intThridSubJ = count($third_array[$i]['subclasses']);
                    for ($j = 0; $j < $intThridSubJ; $j++) {
                        $third_array[$i]['subclasses'][$j]['support'] = '0';
                        $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $sort_store_list = array();
        $sort_store_list = array_merge($first_array, $second_array);
        $sort_store_list = array_merge($sort_store_list, $third_array);
    
        //去掉排序字段
        $intSortI = count($sort_store_list);
        for ($i = 0; $i < $intSortI; $i++) {
            //去掉排序字段
            unset($sort_store_list[$i]['rmdpos']);
            $intSortSubJ = count($sort_store_list[$i]['subclasses']);
            for ($j = 0; $j < $intSortSubJ; $j++) {
                //$store_list['plugins'][$i]['subclasses'][$j]['version_name'] = $this->getVersionString($store_list['plugins'][$i]['subclasses'][$j]['version_name']);
                $sort_store_list[$i]['subclasses'][$j]['version_name'] = $plugin_list[$sort_store_list[$i]['subclasses'][$j]['pos']]['version_name_str'];
    
                unset($sort_store_list[$i]['subclasses'][$j]['grade']);
                unset($sort_store_list[$i]['subclasses'][$j]['pos']);
                unset($sort_store_list[$i]['subclasses'][$j]['rmdequal']);
                unset($sort_store_list[$i]['subclasses'][$j]['rmdpos']);
            }
        }
    
    
        return $sort_store_list[0];
    }
    

    /**
     * @route({"POST","/downinfo/"})
     * 获取插件下载信息
     * 插件支持多版本并存，由于兼容之前版本id 为apk包名,因此需要将所有插件按照logo菜单算法计算出插件列表，然后从中选取
     * @return array
     */
    public function downinfoAction()
    {
        //获取plugin id
        $id = $_GET['id'];
        $platform = $this->strOs;
        $cpu_info = isset($_POST['plugin']) ? $_POST['plugin'] : '' ;
    
        //by lipengcheng02:如果cpu信息为空，说明是ral方式传递的post数据，用下面的方式来获取
        if($cpu_info == ''){
            $post_info = json_decode(file_get_contents("php://input"),true);
            $cpu_info = $post_info['plugin'];
        }
    
        //广告参数拼接到下载地址后面
        $query_info = isset($_POST['query_info']) ? $_POST['query_info'] : '' ; 
    
        //for test4
        //$this->version = "5.0.0.1";
        //$cpu_info = '{"cpu":"freq : 1401600|features\t: swp half thumb fastmult vfp edsp neon vfpv3 |core:1"}';
    
        $memory =isset($_REQUEST['mem']) ? $_REQUEST['mem'] : '0' ; 
        $cpu = $this->parseCpuInfo($cpu_info);
    
        $plugin_list = $this->getPluginList($platform);
        $plugin_list_count = count($plugin_list);
    
        $rmd_info = $this->getRecommendTipInfo();
    
        //取客户端两位版本号对应数值
        $input_version = substr($this->version, 0, 3);
        $client_version = $this->getVersionIntVal($input_version, 2);
    
        $first_array = array();
        $second_array = array();
        $third_array = array();
    
        //取推荐版本
        $rmd_version = $this->getRecommendVersion($rmd_info['logormd_suggest_version']);
    
        //
        $cache_first_key = self::CACHE_PLUGIN_FIRST_LIST_KEY . 'downinfo' . $platform . $input_version;
        //先从cache里获取缓存的所以插件列表
        $first_array = GFunc::cacheGet($cache_first_key);
        $is_cache_first = true;
    
        if(false === $first_array)
        {
            $first_array = array();
            $is_cache_first = false;
        }
    
        //含有子插件的插件高低端主信息必须一致
        //推荐的插件显示在最前面
        if( !$is_cache_first && (0 !== $rmd_version) && ($client_version >= $rmd_version) )
        {
            for ($pos = 0; $pos < $plugin_list_count; $pos++) {
                //get recommend position
                $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
    
                //不推荐
                if (self::PLUGIN_NOT_RECOMMEND_FLAG === $one_plugin['rmdpos']) {
                    continue;
                }
                $rmd_input_version = $rmd_info['logormd_suggest_version'][$one_plugin['rmdpos']-1];
                //推荐插件版本必须相等
                if($rmd_version !== $rmd_input_version){
                    continue;
                }
    
                $one_plugin['id'] = $plugin_list[$pos]['id'];
                $one_plugin['name'] = $plugin_list[$pos]['name'];
    
                //保存推荐版本同客户端版本是否相等
                $one_sub['rmdequal'] = ($client_version === $rmd_input_version);
                //保存插件位置
                $one_sub['pos'] = $pos;
                //保存推荐位置
                $one_sub['rmdpos'] = $one_plugin['rmdpos'];
                $one_sub['reason'] = '';
                $one_sub['grade'] = $plugin_list[$pos]['grade'];
                $one_sub['version'] = $plugin_list[$pos]['version'];
                $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
                $one_sub['size'] = $plugin_list[$pos]['size'];
                $one_sub['md5'] = $plugin_list[$pos]['md5'];
                $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
                $one_sub['download'] = $plugin_list[$pos]['download'] . $query_info;
    
                $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
                $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            {
                $is_new = true;
                for ($i = 0; $i < count($first_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $first_array[$i]['id']) {
                        $is_new = false;

                        //等级相同的不同不同版本插件 判断推荐位置
                        $sub_pos = -1;
                        for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $first_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }

                        //没有相同grade插件则直接插入，有则判断推荐位置
                        if (-1 === $sub_pos) {
                            array_push($first_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $first_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $first_array[$i]['subclasses'][$sub_pos] = $one_sub;
                            }

                            //判断客户端输入法版本同推荐版本
                            if ($client_version === $rmd_input_version) {
                                //判断推荐位置
                                if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                    //更新sub class
                                    $first_array[$i]['subclasses'][$sub_pos] = $one_sub;

                                    //更新插件主信息
                                    $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];

                                }
                            } else {
                                if (!$first_array[$i]['subclasses'][$sub_pos]['rmdequal']) {
                                    //判断推荐位置
                                    if ($first_array[$i]['subclasses'][$sub_pos]['rmdpos'] > $one_plugin['rmdpos']) {
                                        //更新sub class
                                        $first_array[$i]['subclasses'][$sub_pos] = $one_sub;

                                        //更新插件主信息
                                        $first_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
                                            
                                    }

                                }

                            }
                        }

                    }
                }

                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($first_array, $one_plugin);
                }
            }
    
            }
        }//if
    
        //sort
        //等级排序
        for ($i = 0; !$is_cache_first && ($i < count($first_array)); $i++) {
            usort($first_array[$i]['subclasses'], function ($a, $b) {
                if ($a['grade'] == $b['grade']) {
                    return 0;
                }
                return ($a['grade'] > $b['grade']) ? 1 : -1;
            });
        }
    
        if(!$is_cache_first)
        {
            GFunc::cacheSet($cache_first_key, $first_array, $this->intCacheExpired);
        }
    
        //support reason
        for ($i = 0; $i < count($first_array); $i++) {
            //非高低端
            if (1 === count($first_array[$i]['subclasses'])) {
                //不在推送区间
                $min_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if('' !== $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'])
                {
                    $max_version = $plugin_list[$first_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                }
                else
                {
                    $max_version = 100;
                }
    
                //推荐配置的版本低于支持最小版本因此提示升级输入法
                if( !(($client_version >= $min_version) && ($client_version <= $max_version)) )
                {
                    $first_array[$i]['subclasses'][0]['support'] = '0';
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }
    
                $first_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $first_array[$i]['subclasses'][0]['support']) {
                    $first_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
    
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$first_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $first_array[$i]['subclasses'][0]['support'] = '1';
                        for ($j = 1; $j < count($first_array[$i]['subclasses']); $j++) {
                            $first_array[$i]['subclasses'][$j]['support'] = '0';
                            $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                            //不考虑3中包情况
                            if (1 === $j) {
                                $first_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $first_array[$i]['subclasses'][$j]['support'] = '0';
                                $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    for ($j = 0; $j < count($first_array[$i]['subclasses']); $j++) {
                        $first_array[$i]['subclasses'][$j]['support'] = '0';
                        $first_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $cache_second_key = self::CACHE_PLUGIN_SECOND_LIST_KEY . 'downinfo' . $platform . $input_version;
        //先从cache里获取缓存的所以插件列表
        $second_array = GFunc::cacheGet($cache_second_key);
        $is_cache_second = true;
    
        if(false === $second_array)
        {
            $second_array = array();
            $is_cache_second = false;
        }
    
        //正常的该版本可使用的插件，按照上线时间排序，新的插件显示在前面
        for ($pos = 0; !$is_cache_second && ($pos < $plugin_list_count); $pos++) {
            //get recommend position
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
    
            $min_version = $plugin_list[$pos]['input_version_min_push'];
            if ('' !== $plugin_list[$pos]['input_version_max_push']) {
                $max_version = $plugin_list[$pos]['input_version_max_push'];
            } else {
                $max_version = 100;
            }
    
            if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                continue;
            }
                
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
                
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['reason'] = '';
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['md5'] = $plugin_list[$pos]['md5'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
            $one_sub['download'] = $plugin_list[$pos]['download'] . $query_info;
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                for ($i = 0; $i < count($second_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $second_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $second_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($second_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $second_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $second_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $second_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
    
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($second_array, $one_plugin);
                }
            }
    
        }
    
        //等级排序
        for ($i = 0; !$is_cache_second && ($i < count($second_array)); $i++) {
            usort($second_array[$i]['subclasses'], function ($a, $b) {
                if ($a['grade'] == $b['grade']) {
                    return 0;
                }
                return ($a['grade'] > $b['grade']) ? 1 : -1;
            });
        }
    
        if(!$is_cache_second)
        {
            GFunc::cacheSet($cache_second_key, $second_array, $this->intCacheExpired);
        }
    
        //support reason
        for ($i = 0; $i < count($second_array); $i++) {
            //非高低端
            if (1 === count($second_array[$i]['subclasses'])) {
                $second_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $second_array[$i]['subclasses'][0]['support']) {
                    $second_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$second_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $second_array[$i]['subclasses'][0]['support'] = '1';
                        for ($j = 1; $j < count($second_array[$i]['subclasses']); $j++) {
                            $second_array[$i]['subclasses'][$j]['support'] = '0';
                            $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                            if (1 === $j) {
                                $second_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $second_array[$i]['subclasses'][$j]['support'] = '0';
                                $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    for ($j = 0; $j < count($second_array[$i]['subclasses']); $j++) {
                        $second_array[$i]['subclasses'][$j]['support'] = '0';
                        $second_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $cache_third_key = self::CACHE_PLUGIN_THIRD_LIST_KEY . 'downinfo' . $platform . $input_version;
        //先从cache里获取缓存的所以插件列表
        $third_array = GFunc::cacheGet($cache_third_key);
        $is_cache_third = true;
        if(false === $third_array)
        {
            $third_array = array();
            $is_cache_third = false;
        }
    
        //不可使用但是推送的插件（标注支持的输入法版本，刺激用户升级）
        for ($pos = 0; !$is_cache_third && ($pos < $plugin_list_count); $pos++) {
            $one_plugin['rmdpos'] = $this->getRecommendPos($rmd_info['logormdlist'], $plugin_list[$pos]['key']);
    
            if ('' === $plugin_list[$pos]['input_version_sugest_update']) {
                continue;
            }
    
            $min_version = $plugin_list[$pos]['input_version_sugest_update'];
            if (!($client_version >= $min_version)) {
                continue;
            }
                
            //判断是否重复
            $is_repeat = false;
            foreach($first_array as $first_plugin) {
                if ($first_plugin['id'] === $plugin_list[$pos]['id']) {
                    $is_repeat = true;
                    break;
                }
            }
                
            if (!$is_repeat) {
                foreach($second_array as $second_plugin) {
                    if ($second_plugin['id'] === $plugin_list[$pos]['id']) {
                        $is_repeat = true;
                        break;
                    }
                }
            }
                
            if($is_repeat){
                continue;
            }
    
            $one_plugin['id'] = $plugin_list[$pos]['id'];
            $one_plugin['name'] = $plugin_list[$pos]['name'];
    
            //保存推荐版本同客户端版本是否相等
            $one_sub['rmdequal'] = false;
            //保存插件位置
            $one_sub['pos'] = $pos;
            //保存推荐位置
            $one_sub['rmdpos'] = $one_plugin['rmdpos'];
            $one_sub['reason'] = '';
            $one_sub['grade'] = $plugin_list[$pos]['grade'];
            $one_sub['version'] = $plugin_list[$pos]['version'];
            $one_sub['name'] = $plugin_list[$pos]['name'];
            $one_sub['version_name'] = $plugin_list[$pos]['version_name'];
            $one_sub['size'] = $plugin_list[$pos]['size'];
            $one_sub['mdate'] = $plugin_list[$pos]['mdate'];
            $one_sub['download'] = $plugin_list[$pos]['download'] . $query_info;
    
            $one_sub['min'] = $plugin_list[$pos]['input_version_min_support'];
            $one_sub['max'] = $plugin_list[$pos]['input_version_max_support'];
    
            if (!$is_repeat) {
                $is_new = true;
                for ($i = 0; $i < count($third_array); $i++) {
                    //超过1个插件 有可能为不同 version name 或 grade
                    if ($one_plugin['id'] === $third_array[$i]['id']) {
                        $is_new = false;
    
                        //等级相同的不同不同版本插件 推荐高版本插件不论支持与否
                        $sub_pos = -1;
                        for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                            if ($plugin_list[$pos]['grade'] === $third_array[$i]['subclasses'][$j]['grade']) {
                                $sub_pos = $j;
                                break;
                            }
                        }
    
                        //没有相同grade插件则直接插入，有则用高版本替换
                        if (-1 === $sub_pos) {
                            array_push($third_array[$i]['subclasses'], $one_sub);
                        } else {
                            //已经保存
                            $version_first = $third_array[$i]['subclasses'][$sub_pos]['version_name'];
                            //未保存
                            $version_second = $plugin_list[$pos]['version_name'];
                            //高版本插件替换低版本
                            if ($version_second > $version_first) {
                                $third_array[$i]['subclasses'][$sub_pos] = $one_sub;
    
                                //更新插件主信息
                                $third_array[$i]['rmdpos'] = $one_plugin['rmdpos'];
    
                            }
                        }
    
                    }
                }
    
                if ($is_new) {
                    $one_plugin['subclasses'] = array();
                    array_push($one_plugin['subclasses'], $one_sub);
                    array_push($third_array, $one_plugin);
                }
            }
    
        }
    
        //等级排序
        for ($i = 0; !$is_cache_third && ($i < count($third_array)); $i++) {
            usort($third_array[$i]['subclasses'], function ($a, $b) {
                if ($a['grade'] == $b['grade']) {
                    return 0;
                }
                return ($a['grade'] > $b['grade']) ? 1 : -1;
            });
        }
    
        if(!$is_cache_third)
        {
            GFunc::cacheSet($cache_third_key, $third_array, $this->intCacheExpired);
        }
    
        //support reason
        for ($i = 0; $i < count($third_array); $i++) {
            //非高低端
            if (1 === count($third_array[$i]['subclasses'])) {
                //不在推送区间
                $min_version = $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_min_push'];
                if ('' !== $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_max_push']) {
                    $max_version = $plugin_list[$third_array[$i]['subclasses'][0]['pos']]['input_version_max_push'];
                } else {
                    $max_version = 100;
                }
                //不在推荐区间
                if (!(($client_version >= $min_version) && ($client_version <= $max_version))) {
                    $third_array[$i]['subclasses'][0]['support'] = '0';
                    $third_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_update_input'];
                    continue;
                }
    
                $third_array[$i]['subclasses'][0]['support'] = ($this->getIsSupportExFeatures($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu, $memory) && $this->getIsFeaturesSupport($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu)) ? '1' : '0';
                //配置推荐不支持插件
                if ('0' === $third_array[$i]['subclasses'][0]['support']) {
                    $third_array[$i]['subclasses'][0]['reason'] = $rmd_info['tip_not_support'];
                }
            } else {
                //指令集是否支持
                if ($this->getIsFeaturesSupport($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu)) {
                    //高端支持
                    if ($this->getIsSupportExFeatures($plugin_list[$third_array[$i]['subclasses'][0]['pos']], $cpu, $memory)) {
                        $third_array[$i]['subclasses'][0]['support'] = '1';
                        for ($j = 1; $j < count($third_array[$i]['subclasses']); $j++) {
                            $third_array[$i]['subclasses'][$j]['support'] = '0';
                            $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                        }
                    } else {
                        for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                            if (1 === $j) {
                                $third_array[$i]['subclasses'][$j]['support'] = '1';
                            } else {
                                $third_array[$i]['subclasses'][$j]['support'] = '0';
                                $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                            }
                        }
                    }
                } else {
                    for ($j = 0; $j < count($third_array[$i]['subclasses']); $j++) {
                        $third_array[$i]['subclasses'][$j]['support'] = '0';
                        $third_array[$i]['subclasses'][$j]['reason'] = $rmd_info['tip_not_support'];
                    }
                }
    
            }
        }
    
        $sort_plugin_list = array();
        $sort_plugin_list = array_merge($first_array, $second_array);
        $sort_plugin_list = array_merge($sort_plugin_list, $third_array);
    
        //
        for ($i = 0; $i < count($sort_plugin_list); $i++) {
            //去掉排序字段
            for ($j = 0; $j < count($sort_plugin_list[$i]['subclasses']); $j++) {
                unset($sort_plugin_list[$i]['subclasses'][$j]['grade']);
                unset($sort_plugin_list[$i]['subclasses'][$j]['pos']);
                unset($sort_plugin_list[$i]['subclasses'][$j]['rmdequal']);
                unset($sort_plugin_list[$i]['subclasses'][$j]['rmdpos']);
                    
                //unset($sort_plugin_list[$i]['subclasses'][$j]['version']);
                unset($sort_plugin_list[$i]['subclasses'][$j]['version_name']);
            }
        }
    
        //去掉排序字段
        for ($i = 0; $i < count($sort_plugin_list); $i++) {
            unset($sort_plugin_list[$i]['rmdpos']);
        }
    
        $plugin_info = array();
        foreach ($sort_plugin_list as $plugin)
        {
            if($plugin['id'] === $id)
            {
                $plugin_info = $plugin;
                break;
            }
        }
    
        if(0 === count($plugin_info))
        {
            header('X-PHP-Response-Code: '. 404, true, 404);
            exit();
        }
    
        $downinfo = array();
        $downinfo['downinfo'] = $plugin_info;
    
        return $downinfo;
    }
    
    
    
    
    /**
     * @route({"GET","/handwrite/"})
     * 获取手写模板
     * 插件支持多版本并存，由于兼容之前版本id 为apk包名,因此需要将所有插件按照logo菜单算法计算出插件列表，然后从中选取
     * @return array
     */
    public function handwriteAction()
    {
        $handwrite_version = $_GET['hdwver'];
    
        if(!isset($handwrite_version))
        {
            header('X-PHP-Response-Code: '. 400, true, 400);
            exit();
        }
    
        $platform = $this->strOs;
        $inputversion = $this->version;
        //$handwrite_list = $this->getHandwriteListFromBcs($platform);
        $handwrite_list = $this->getHandwriteList();
        $list = array();
        
        //Edit by fanwenli on 2016-11-20
        $handwrite_list_count = count($handwrite_list);
        if($handwrite_list_count > 0){
            for($i = 0; $i < $handwrite_list_count; $i++)
            {
                if ($platform != $handwrite_list[$i]['platform']){
                    continue;
                }
    
                $min_input_version = (isset($handwrite_list[$i]['min_input_version']) && $handwrite_list[$i]['min_input_version']) ? $this->getVersionIntVal($handwrite_list[$i]['min_input_version'], 2) : 0;
                $max_input_version = (isset($handwrite_list[$i]['max_input_version']) && $handwrite_list[$i]['max_input_version']) ? $this->getVersionIntVal($handwrite_list[$i]['max_input_version'], 2) : 100;
                        
                $input_version_support = false;
                $input_version_support = $this->isIssue($this->version, $handwrite_list[$i]['inputversion'], $min_input_version, $max_input_version);
                
                if(!$input_version_support){
                    continue;
                }
                
                if(intval($handwrite_version) >= intval($handwrite_list[$i]['version'])){
                    continue;
                }
                
                $one_handwrite['id'] = $handwrite_list[$i]['id'];
                $one_handwrite['name'] = $handwrite_list[$i]['name'];
                $one_handwrite['version'] = $handwrite_list[$i]['version'];
                $one_handwrite['size'] = $handwrite_list[$i]['size'];
                $one_handwrite['md5'] = $handwrite_list[$i]['md5'];
                $one_handwrite['download'] = $handwrite_list[$i]['download'];
                
                array_push($list, $one_handwrite);
                
                //Edit by fanwenli on 2016-11-20
                break;
            }
        }
            
        if(isset($list[0])){
            return $list[0];
        }else{
            header('X-PHP-Response-Code: '. 404, true, 404);
            exit();
        }
    }
        
        
    /**
     * 返回手写插件列表
     *
     * @return array
     */
    private  function getHandwriteList()
    {
        $cache_key = self::CACHE_HANDWRITE_LIST_PREFIX;
        $plugin_list = GFunc::cacheGet($cache_key);
        if ($plugin_list === false)
        {
            //Edit by fanwenli on 2016-11-20
            //$sql = "select * from input_plugin_handwrite where status = 100";
            $sql = "select plugin_id,handwrite_name,handwrite_version,file_size,file_md5,file_url,push_platform,push_version,min_version,max_version from input_plugin_handwrite where status = 100";
            $plugin_list = $this->getXDB()->queryf($sql);
            if ($plugin_list)
            {
                foreach ($plugin_list as $key => $plugin)
                {
                    $plugin_list[$key] = array(
                        'id' => $plugin['plugin_id'],
                        'name' => $plugin['handwrite_name'],
                        //'desc' => $plugin['handwrite_desc'],
                        'version' => $plugin['handwrite_version'],
                        'size' => $plugin['file_size'],
                        'md5' => $plugin['file_md5'],
                        'download' => $plugin['file_url'],
                        'platform' => $plugin['push_platform'],
                        'inputversion' => $plugin['push_version'],
                        //'mdate' => date('Y-m-d H:i:s', $plugin['update_time']),
                        'min_input_version' => $plugin['min_version'],
                        'max_input_version' => $plugin['max_version'],
                    );
                }
    
                //手写模板版本号降序排列
                usort($plugin_list, function($a, $b)
                {
                    if ($a['version'] == $b['version'])
                    {
                        return 0;
                    }
                    return ($a['version'] > $b['version']) ? -1 : 1;
                });
            }
    
            GFunc::cacheSet($cache_key, $plugin_list, $this->intCacheExpired);
        }
        return $plugin_list;
    }
        
    /**
     * 判断是否下发通知
     *  @param string $client_version 客户端版本
     *  @param string $preg_version 正则过滤版本
     *  @param int $min_input_version
     *  @param int $max_input_version
     *
     *  @return bool
     */
    private function isIssue($client_version, $preg_version, $min_input_version, $max_input_version)
    {
        $client_version = str_replace('-', '.', $client_version);
    
        $input_version = substr($client_version, 0, 3);
        $input_version_val = $this->getVersionIntVal($input_version, 2);
        if ( $input_version_val < $min_input_version || $input_version_val > $max_input_version ){
            return false;
        }
    
        if('all' === $preg_version)
        {
            return true;
        }
    
        //将-替换为.
        $preg_version = str_replace('-', '.', $preg_version);
        $preg_version_pattern = $this->getPattern($preg_version);
    
        if( preg_match("#" . $preg_version_pattern . "#", $client_version) )
        {
            return true;
        }
        else
        {
            return false;
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
    private function getPattern($str)
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
    
    
    
    //handwrite
    /**
     * 从BCS获取手写模板表信息
     *
     * @param
     *      参数名称：platform
     *      是否必须：是
     *      参数说明：平台
     * @return array
     */
    private function getHandwriteListFromBcs($platform)
     {
     //get from cache
        $cache_key = self::CACHE_HANDWRITE_LIST_PREFIX . $platform;
        //先从cache里获取缓存的插件信息
        $cache_info = GFunc::cacheGet($cache_key);
    
        if(false !== $cache_info)
        {
           return $cache_info;
        }
    
        //cache 没有从bcs获取
        $opt = array (
            "start" => 0,
            "prefix" => self::BCS_HANDWRITE_PREFIX,
        );
    
        $plugin_name_list = array();
        $plugin_name_list = $this->_bcs->list_object($opt);
    
        $plugin_list = array();
    
        foreach($plugin_name_list as $plugin_name)
        {
            $plugin_info = $this->_bcs->get_object_info($plugin_name);
    
            //下线则不给出在列表
            if(intval($plugin_info['x-bs-meta-status']) !== self::PLUGIN_ONLINE_FLAG) {
                continue;
            }
                
            //平台匹配
            if($platform !== trim($plugin_info['x-bs-meta-platform'])) {
                continue;
            }
    
            $one_plugin['id'] = $plugin_info['x-bs-meta-id'];
            $one_plugin['name'] = $plugin_info['x-bs-meta-name'];
            $one_plugin['desc'] = $plugin_info['x-bs-meta-desc'];
            $one_plugin['version'] = $plugin_info['x-bs-meta-version'];
            $one_plugin['size'] = $plugin_info['x-bs-meta-size'];
            $one_plugin['md5'] = $plugin_info['x-bs-meta-md5'];
            $one_plugin['download'] = $plugin_info['_info']['url'];
            $one_plugin['platform'] = $plugin_info['x-bs-meta-platform'];
            $one_plugin['inputversion'] = $plugin_info['x-bs-meta-inputversion'];
            $one_plugin['min_input_version'] = $plugin_info['x-bs-meta-min-inputversion'];
            $one_plugin['max_input_version'] = $plugin_info['x-bs-meta-max-inputversion'];
            $one_plugin['status'] = $plugin_info['x-bs-meta-status'];
            $one_plugin['mdate'] = $plugin_info['x-bs-meta-mdate'];
    
            array_push($plugin_list, $one_plugin);
        }
    
        //手写模板版本号降序排列
        usort($plugin_list, function($a, $b)
        {
            if ($a['version'] == $b['version'])
            {
                return 0;
            }
            return ($a['version'] > $b['version']) ? -1 : 1;
        });
    
        //set cache
        GFunc::cacheSet($cache_key, $plugin_list, $this->intCacheExpired);
    
        return $plugin_list;
    }
    
}
