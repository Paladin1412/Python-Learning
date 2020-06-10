<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use tinyESB\util\ClassLoader;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

/**
 * 版本更新
 *
 * @author fanwenli
 * @path("/soft_update_noti/")
*/
class SoftUpdateNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心资源缓存key pre*/
    private $strSoftUpdateNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /** wifi网络对应的sp代号 **/
    const WIFI_FLAG_OF_SP = 2;
    
    /** 手机信息 **/
    private $arrPhoneInfo = array();
    
    /** 用户信息相关参数 **/
    private $arrUserInfo = array();
    
    /** 版本更新相关参数 **/
    private $arrSoftInfo = array();
    
    /** 输入法默认包名(LC升级平台用到) **/
    const IME_DEFAULT_PACKAGENAME = 'com.baidu.input';
    
    /***
     * 构造函数
     * @return void
    */
    public function  __construct() {
        $this->objBase = new NotiBase();
        
        //输出格式初始化
        $this->out = Util::initialClass();
    }
    
    /**
     *
     * 通知中心轮询版本更新接口
     *
     * @route({"POST", "/info"})
     * @param({"strData","$._POST.strData"})
     * @param({"strRom", "$._GET.rom"}) $strRom rom,不需要客户端传
     * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
     * @param({"strCfrom", "$._GET.cfrom"}) $strCfrom cfrom当前渠道号,不需要客户端传
     * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
     * @param({"intSp", "$._GET.sp"}) $intSp 联网类型
     * @param({"strUid", "$._GET.uid"}) $strUid uid,不需要客户端传
     * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
     * @param({"strImei", "$._GET.imei"}) $strImei android平台正序imei,不需要客户端传
     * @param({"strFrom", "$._GET.from"}) $strFrom 初始渠道号,不需要客户端传
     * @param({"strModel", "$._GET.model"}) $strModel 手机型号,不需要客户端传
     * @param({"strScreenW", "$._GET.screen_w"}) $strScreenW 屏幕宽,不需要客户端传
     * @param({"strScreenH", "$._GET.screen_h"}) $strScreenH 屏幕高,不需要客户端传
     * @param({"strSdk", "$._GET.sdk"}) $strSdk 客户端系统SDK版本,不需要客户端传
     * @param({"intDebugLog", "$._GET.debuglog"}) $intDebugLog 是否打印调试日志0 不打印 1 打印
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
     *      ]
     * }
    */
    public function getUpdateInfo($strData, $strRom, $strPlatform, $strCfrom, $strVersion, $intSp = 12, $strUid = null, $strCuid = null, $strImei = '', $strFrom = '', $strModel = '', $strScreenW = 640, $strScreenH = 320, $strSdk = '', $intDebugLog = 0){
        //decode上传参数
        $arrUserPostInfo = json_decode($strData, true);
        
        //network
        $strNetWork = (intval($intSp) === self::WIFI_FLAG_OF_SP)?'wifi':'gprs';
        
        $intSp = intval($intSp);
        $bolDebugLog = (1 === intval($intDebugLog))? true : false;
        
        //版本升级需要获取userinfo softinfo
        $this->getUserInfo($arrUserPostInfo, $strUid, $strCuid, $strImei, $strPlatform, $strFrom, $strCfrom, $intSp, $strNetWork, Util::getClientIP());
        $this->getSoftInfo($arrUserPostInfo, $strVersion);
        
        //获取手机信息
        $this->getPhoneInfo($arrUserPostInfo, $strRom, $strPlatform, $strModel, $strScreenW, $strScreenH, $strSdk);
        
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $conf = GFunc::getConf('Noti');
        $strUnifyConfResRoot = $conf['properties']['strUnifyConfResRoot'];
        
        //LC增量升级接口地址
        $strLcIncUpgradeAddress = $conf['properties']['strLcIncUpgradeAddress'];
        //LC升级平台接口地址
        $strLcUpgradeAddress = $conf['properties']['strLcUpgradeAddress'];
        //debug模式默认关闭
        $intDebugFlag = $conf['properties']['intDebugFlag'];
        
        //统一开关缓存key以及缓存过期时间
        $conf = GFunc::getConf('NotiSwitchNoti');
        $strNotiSwitchNotiCachePre = $conf['properties']['strNotiSwitchNotiCachePre'];
        $intSwitchNotiCacheExpired = $conf['properties']['intCacheExpired'];
        
        //统一开关配置
        $arrUnifyConf = UnifyConf::getUnifyConf($redis, $strNotiSwitchNotiCachePre, $intSwitchNotiCacheExpired, $strUnifyConfResRoot, Util::getClientIP(), $strFrom);
        
        //lc switch
        $intOemLcSwitch = $arrUnifyConf['oem_lc_switch'];
        
        $this->out['data'] = NotiSoft::fetchVersionUpgradeMessage($this->objBase, $redis, $this->strSoftUpdateNotiCachePre, $this->intCacheExpired, Util::getPhoneOS($strPlatform), $strNetWork, $intOemLcSwitch, $strVersion, $strPlatform, $this->arrPhoneInfo, $this->arrUserInfo, $this->arrSoftInfo, $strLcIncUpgradeAddress, $strLcUpgradeAddress, $intDebugFlag, Util::getClientIP(), $bolDebugLog);
        
        return Util::returnValue($this->out);
    }
    
    /**
     * 用户信息相关参数提取
     * @param $arrUserPostInfo 用户post数据
     * @param $strUid uid
     * @param $strCuid cuid
     * @param $strPlatform platform
     * @param $strFrom 初始渠道号
     * @param $strCfrom 当前渠道号
     * @param $intSp 联网类型
     * @param $strNetWork network
     * @param $strClientIP client ip
     *
     * @return void
    */
    public function getUserInfo($arrUserPostInfo, $strUid, $strCuid, $strImei, $strPlatform, $strFrom, $strCfrom, $intSp, $strNetWork, $strClientIP){
        $this->arrUserInfo['uid'] = $strUid;
        $this->arrUserInfo['cuid'] = $strCuid;
        
        if (isset($arrUserPostInfo['imei']) && $arrUserPostInfo['imei'] !== '') {
            $this->arrUserInfo['imei'] = strrev($arrUserPostInfo['imei']);
        } else {
            $this->arrUserInfo['imei'] = $strImei;
        }
        
        if (isset($arrUserPostInfo['platform']) && $arrUserPostInfo['platform'] !== '') {
            $this->arrUserInfo['platform'] = $arrUserPostInfo['platform'];
        } else {
            $this->arrUserInfo['platform'] = $strPlatform;
        }
        
        $this->arrUserInfo['from'] = $strFrom;
        
        if (isset($arrUserPostInfo['channel']) && $arrUserPostInfo['channel'] !== '') {
            $this->arrUserInfo['cfrom'] = $arrUserPostInfo['channel'];
        } else {
            $this->arrUserInfo['cfrom'] = $strCfrom;
        }
        
        //cfrom参数没传的话用from参数
        if ($this->arrUserInfo['cfrom'] === '') {
            $this->arrUserInfo['cfrom'] = $this->arrUserInfo['from'];
        }
        
        $this->arrUserInfo['sp'] = $intSp;
        $this->arrUserInfo['network'] = $strNetWork;
        $this->arrUserInfo['client_ip'] = $strClientIP;
        
        if (isset($arrUserPostInfo['area'])) {
            $this->arrUserInfo['area'] = $arrUserPostInfo['area'];
        } else {
            $this->arrUserInfo['area'] = '';
        }
        
        if (isset($arrUserPostInfo['ukey'])) {
            $this->arrUserInfo['ukey'] = $arrUserPostInfo['ukey'];
        } else {
            $this->arrUserInfo['ukey'] = '';
        }
        
        if (isset($arrUserPostInfo['srver'])) {
            $this->arrUserInfo['srver'] = intval($arrUserPostInfo['srver']);
        } else {
            $this->arrUserInfo['srver'] = 0;
        }
        
        if (isset($arrUserPostInfo['cangjiever'])) {
            $this->arrUserInfo['cangjiever'] = intval($arrUserPostInfo['cangjiever']);
        }
        
        if (isset($arrUserPostInfo['zhuyinver'])) {
            $this->arrUserInfo['zhuyinver'] = intval($arrUserPostInfo['zhuyinver']);
        }
        
        if (isset ( $arrUserPostInfo ['location'] )) {
            $this->arrUserInfo ['location'] = array ();
            if (isset ( $arrUserPostInfo ['location'] ['mcc'] )) {
                $this->arrUserInfo ['location'] ['mcc'] = intval ( $arrUserPostInfo ['location'] ['mcc'] );
            }
            
            if (isset ( $arrUserPostInfo ['location'] ['mnc'] )) {
                $this->arrUserInfo ['location'] ['mnc'] = intval ( $arrUserPostInfo ['location'] ['mnc'] );
            }
            
            if (isset ( $arrUserPostInfo ['location'] ['lac'] )) {
                $this->arrUserInfo ['location'] ['lac'] = intval ( $arrUserPostInfo ['location'] ['lac'] );
            }
            
            if (isset ( $arrUserPostInfo ['location'] ['cid'] )) {
                $this->arrUserInfo ['location'] ['cid'] = intval ( $arrUserPostInfo ['location'] ['cid'] );
            }
        }
    }
    
    /**
     * 版本更新相关参数提取
     * @param $arrUserPostInfo 用户post数据
     * @param $strVersion input version
     *
     * @return void
    */
    public function getSoftInfo($arrUserPostInfo, $strVersion){
        //母包类型
        if (isset($arrUserPostInfo['soft']['original_type']) && $arrUserPostInfo['soft']['original_type'] !== '') {
            $this->arrSoftInfo['original_type'] = intval($arrUserPostInfo['soft']['original_type']);
        } else {
            $this->arrSoftInfo['original_type'] = 1;
        }
        
        //升级类型
        if (isset($arrUserPostInfo['soft']['type']) && $arrUserPostInfo['soft']['type'] !== '') {
            $this->arrSoftInfo['type'] = $arrUserPostInfo['soft']['type'];
        } else {
            $this->arrSoftInfo['type'] = 'apk';
        }
        
        //版本号
        if (isset($arrUserPostInfo['soft']['ver']) && $arrUserPostInfo['soft']['ver'] !== '') {
            $this->arrSoftInfo['version_name'] = str_replace('-', '.', $arrUserPostInfo['soft']['ver']);
        } else {
            $this->arrSoftInfo['version_name'] = str_replace('-', '.', $strVersion);
        }
        
        $this->arrUserInfo['version_name'] = $this->arrSoftInfo['version_name'];
        
        //版本值
        if (isset($arrUserPostInfo['soft']['vercode']) && $arrUserPostInfo['soft']['vercode'] !== '') {
            $this->arrSoftInfo['version_code'] = intval($arrUserPostInfo['soft']['vercode']);
        } else {
            $this->arrSoftInfo['version_code'] = 0;
        }
        
        $this->arrUserInfo['version_code'] = $this->arrSoftInfo['version_code'];
        //最大消息版本号
        if (isset($arrUserPostInfo['soft']['msgver']) && $arrUserPostInfo['soft']['msgver'] !== '') {
            $this->arrSoftInfo['msgver'] = intval($arrUserPostInfo['soft']['msgver']);
        } else {
            $this->arrSoftInfo['msgver'] = 0;
        }
        
        //最新消息时间
        if (isset($arrUserPostInfo['soft']['msgtime']) && $arrUserPostInfo['soft']['msgtime'] !== '') {
            $this->arrSoftInfo['msgtime'] = intval($arrUserPostInfo['soft']['msgtime']);
        } else {
            $this->arrSoftInfo['msgtime'] = 0;
        }
        
        //接收消息次数
        if (isset($arrUserPostInfo['soft']['msgrecord']) && $arrUserPostInfo['soft']['msgrecord'] !== '') {
            $this->arrSoftInfo['msgrecord'] = intval($arrUserPostInfo['soft']['msgrecord']);
        } else {
            $this->arrSoftInfo['msgrecord'] = 0;
        }
        
        //包名
        if (isset($arrUserPostInfo['soft']['package_name']) && $arrUserPostInfo['soft']['package_name'] !== '') {
            $this->arrSoftInfo['package_name'] = $arrUserPostInfo['soft']['package_name'];
        } else {
            $this->arrSoftInfo['package_name'] = self::IME_DEFAULT_PACKAGENAME;
        }
        
        //激活时间
        if (isset($arrUserPostInfo['soft']['active_time']) && $arrUserPostInfo['soft']['active_time'] !== '') {
            //如果是毫秒, 先转化为秒
            if (strlen($arrUserPostInfo['soft']['active_time']) >= 13) {
                $this->arrSoftInfo['active_time'] = intval($arrUserPostInfo['soft']['active_time']) / 1000;
            } else {
                $this->arrSoftInfo['active_time'] = intval($arrUserPostInfo['soft']['active_time']);
            }
        } else {
            $this->arrSoftInfo['active_time'] = 0;
        }
        
        //更新类型
        //针对iPhone版本: update_type = 0 通过cydia源更新, update_type = 1 应用内更新
        //针对Android：update_type = 0 默认的全量更新, update_type = 2增量更新
        if (isset($arrUserPostInfo['soft']['update_type']) && $arrUserPostInfo['soft']['update_type'] !== '') {
            $this->arrSoftInfo['update_type'] = intval($arrUserPostInfo['soft']['update_type']);
        } else {
            $this->arrSoftInfo['update_type'] = 0;
        }
        
        //当前待升级apk包的md5值, 增量更新用到
        if (isset($arrUserPostInfo['soft']['usermd5'])) {
            $this->arrSoftInfo['usermd5'] = $arrUserPostInfo['soft']['usermd5'];
        } else {
            $this->arrSoftInfo['usermd5'] = '';
        }
        
        //手机助手的md5值, 增量更新用到
        if (isset($arrUserPostInfo['soft']['appsearchmd5'])) {
            $this->arrSoftInfo['appsearchmd5'] = $arrUserPostInfo['soft']['appsearchmd5'];
        } else {
            $this->arrSoftInfo['appsearchmd5'] = '';
        }
    }
    
    /**
     * 获取手机信息
     * @param $arrUserPostInfo 用户post数据
     * @param $strRom phone rom
     * @param $strPlatform platform
     * @param $strModel model
     * @param $strScreenW screen width
     * @param $strScreenH screen height
     * @param $strSdk sdk
     *
     * @return void
    */
    public function getPhoneInfo($arrUserPostInfo, $strRom, $strPlatform, $strModel, $strScreenW, $strScreenH, $strSdk){
        //系统版本, 增量更新用到
        if (isset($arrUserPostInfo['brand'])) {
            $this->arrPhoneInfo['brand'] = $arrUserPostInfo['brand'];
        } else {
            $this->arrPhoneInfo['brand'] = '';
        }
        
        if (isset($arrUserPostInfo['model']) && $arrUserPostInfo['model'] !== '') {
            $this->arrPhoneInfo['model'] = $arrUserPostInfo['model'];
        } else {
            $this->arrPhoneInfo['model'] = $strModel;
        }
        
        if (isset($arrUserPostInfo['resolution']) && $arrUserPostInfo['resolution'] !== '') {
            $this->arrPhoneInfo['resolution'] = $arrUserPostInfo['resolution'];
        } else {
            $this->arrPhoneInfo['resolution'] = $strScreenW.'x'.$strScreenH;
        }
        
        if (isset($arrUserPostInfo['screen_size'])) {
            $this->arrPhoneInfo['screen_size'] = $arrUserPostInfo['screen_size'];
        } else {
            $this->arrPhoneInfo['screen_size'] = '';
        }
        
        if (isset($arrUserPostInfo['cpu'])) {
            $this->arrPhoneInfo['cpu'] = $arrUserPostInfo['cpu'];
        } else {
            $this->arrPhoneInfo['cpu'] = 'arm';
        }
        
        $this->arrPhoneInfo['phone_os'] = Util::getPhoneOS($strPlatform);
        if ($this->arrPhoneInfo['phone_os'] === 'android') {
            $this->arrPhoneInfo['android_os'] = Util::getAndroidOS($strPlatform);
        } elseif ($this->arrPhoneInfo['phone_os'] === 'ios') {
            $this->arrPhoneInfo['android_os'] = 'IPhone';
        }
        
        $this->arrPhoneInfo['sdk_version'] = intval($strSdk);
        if (isset($arrUserPostInfo['os_version'])) {
            $this->arrPhoneInfo ['os_version'] = str_replace ( '-', '.', $arrUserPostInfo ['os_version'] );
        } else {
            $this->arrPhoneInfo ['os_version'] = str_replace ( '-', '.', $strRom );
        }
        
        //主系统版本(例如android4.4主版本号为4, ios7.0.1主版本号为7)
        $osinfo = explode ( '.', $this->arrPhoneInfo ['os_version'] );
        if (count ( $osinfo ) > 0) {
            $this->arrPhoneInfo ['os_main_version'] = $osinfo [0];
        } else {
            $this->arrPhoneInfo ['os_main_version'] = '';
        }
        
        //系统内存状况(可用内存和总内存), 增量更新用到
        if (isset($arrUserPostInfo['ava_mem'])) {
            $this->arrPhoneInfo['ava_mem'] = $arrUserPostInfo['ava_mem'];
        } else {
            $this->arrPhoneInfo['ava_mem'] = '';
        }
        
        if (isset($arrUserPostInfo['tot_mem'])) {
            $this->arrPhoneInfo['tot_mem'] = $arrUserPostInfo['tot_mem'];
        } else {
            $this->arrPhoneInfo['tot_mem'] = '';
        }
    }
}
