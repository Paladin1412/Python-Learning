<?php
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Util;
use models\VuModel;
use models\NtThreeDWordModel;

require_once __DIR__.'/utils/B64Decoder.php';

/**
 *
 * vu
 * 说明：版本更新检测, 下载接口
 *
 * @author fanwenli
 * @path("/vu/")
 */
class Vu {
    /**
     * wifi网络对应的sp代号
     * @var int
     */
    const WIFI_FLAG_OF_SP = 2;

    /**
     * 输入法默认包名(LC升级平台用到)
     * @var string
     */
    const IME_DEFAULT_PACKAGENAME = 'com.baidu.input';

    /** @property 三维词库最新版本缓存key */
    private $vuWduLatestVersionCachePre;
    
    /**
     * 版本更新检测是否同步进行三维词库检测
     * @var boolean
     */
    const CHECK_WDU_UPDATE_SWITCH_IN_WIFI = true;


    /** @property 输入法版本更新消息noti_switch缓存key前缀 */
    private $vuVersionUpgradeMessageNswConfCachePre;

    /**
     * 手机相关信息
     * @var array
     *
     * 主要包含字段如下：
     * brand 品牌
     * model 机型
     * resolution 分辨率
     * screen_size 屏幕尺寸
     * cpu cpu类型 arm/intel
     * phone_os 手机操作系统类型 android/ios/mac/symbian
     * android_os 安卓系统类型 Android_Phone/Android_Pad
     */
    public $phoneinfo = array();

    /**
     * 用户相关信息
     * @var array
     *
     * 主要包含字段如下：
     * uid, cuid, imei
     * cfrom 当前渠道号
     * from 原始渠道号
     * sp 联网类型 取值0-12
     * network 联网类型 wifi/gprs
     * client_ip 客户端ip
     * area 基站信息
     * version_name 输入法版本号
     * version_code 输入法版本值
     * ukey 用户加密bduss串
    */
    public $userinfo = array();

    /**
     * 输入法版本相关信息
     * @var array
     */
    public $softinfo = array();


    //OEM版本升级走LC开关默认关闭
    private $oem_lc_switch = 0;

    /**
     * 平台 ios / android
     * @var string
     */
    private $platform;

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
     * android平台正序imei
     *
    */
    private $imei;

    /**
     * 当前渠道号
     * @var string
     */
    private $cfrom;

    /**
     * 客户端ip
     * @var string
     */
    private $client_ip;

    /**
     * @var int
     * 版本值
     */
    private $versioncode;

    /**
     * @var string
     * 机型
     */
    private $model;

    /**
     * @var int
     * 屏幕宽度
     */
	private $screenX;

	/**
     * @var int
     * 屏幕高度
     */
	private $screenY;

	/**
     * @var string
     * 客户端版本号
     */
	private $sdk;


    /** @property 内部缓存默认过期时间(单位: 秒) */
    private $intCacheExpired;


    /** @property 统一资源配置资源服务路径*/
    private $strUnifyConfResRoot;


    /** @property 内部缓存实例 */
	private $cache;


	/**
	 * @var string
	 * 客户端统一上传，各移动产品由统一的SDK生成和获取
	 */
	public  $cuid='';

	/**
	 * @var string
	 * android平台cuid的deviceid部分，
	 * iphone平台为mac地址的md5值，io7以后为openudid
	 *
	 */
	public  $cuid_a='';

	/**
	 * @var string
	 * andriod平台cuid的逆序imei部分
	 */
	public  $cuid_b='';

	/**
	 * @var string
	 * android平台bd_逆序imei，
	 * iphone平台为bd_正序udid， iphoneSDK为bd_mac地址md5值，io7以后为bd_openudid
	 *
	 */
	public  $uid='';

	/** @property LC升级平台接口地址*/
	private $strLcUpgradeAddress;

	/** @property LC增量升级接口地址*/
	private $strLcIncUpgradeAddress;

	/** @property debug模式默认关闭*/
	private $intDebugFlag;



    /**
     * 初始化, get以及post参数处理
     * @see ApiBaseController::init()
     */
    public function __construct() {

        $this->strOs = isset($_GET['platform']) ? Util::getPhoneOS($_GET['platform']) : '';
		$this->platform = $this->strOs === 'android' ? 'android' : 'ios' ;
        $this->version = isset($_GET['version']) ? $_GET['version'] : '';
		$this->imei = isset($_GET['imei']) ? $_GET['imei'] : '';
        $this->from = isset($_GET['from']) ? $_GET['from'] : '';
        $this->cfrom = isset($_GET['cfrom']) ? $_GET['cfrom'] : '';
        $this->client_ip = isset($_GET['client_ip']) ? $_GET['client_ip'] : Util::getClientIP();
        $this->versioncode = isset($_GET['versioncode']) ? $_GET['versioncode'] : '';
        $this->model = isset($_POST['model']) ? $_POST['model'] : '';

        self::filterHttpBlock($_REQUEST);
        foreach($_REQUEST as $key => $value){
        	switch($key){
        		case 'uid':
					if (trim($value) !== ''){
						$this->uid = Util::cleanUrlParam($value);
					}
					break;
				/*
				* 无线产品统一的用户识别ID，
				* android平台cuid=bd_倒序imei
				* iphone平台cuid=md5(MAC地址)
				* iosSDK平台cuid=md5(MAC地址)
				* mac平台cuid=md5(MAC地址)
				*/
				case 'cuid':
					$this->cuid = urldecode($value);
					$cuid_array = explode('|', $this->cuid);
					$this->cuid_a = Util::cleanUrlParam($cuid_array[0]);
					if(count($cuid_array)===2){
						$this->cuid_b = Util::cleanUrlParam($cuid_array[1]);
						if($this->imei===''){
							/*
							* uid有限使用cuid中的imei
							*/
							$this->uid='bd_'.$this->cuid_b;

							/*
							* imei使用cuid中的imei
							*/
							$this->imei=strrev($this->cuid_b);
						}
					}
					break;
				case 'ua':
					$ua_array=explode('_', trim($value));
					/*
				 	* ua=bd_宽_高_机型_版本_平台
					*/

					if(count($ua_array)===6){
						/*
						* 屏幕宽
						*/
						$this->screenX = intval($ua_array[1]);
						/*
						* 屏幕高
						*/
						$this->screenY = intval($ua_array[2]);
						/*
						* 机型-厂商
						*/
						$this->model = Util::cleanUrlParam($ua_array[3]);
						/*
						* 输入法版本
						*/
						$this->version = Util::cleanUrlParam($ua_array[4]);
						/*
						* 平台号
						*/
						$this->platform	= Util::cleanUrlParam($ua_array[5]);
					}
					break;
				/*
				* 当前渠道号
				*/
				case 'cfrom':
					$this->cfrom = Util::cleanUrlParam($value);
					break;
				/*
				* 原始渠道号、初始渠道号
				*/
				case 'from':
					$this->from = Util::cleanUrlParam($value);
					break;
				case 'uc':
					$ua_array=explode('_', trim($value));

					if(count($ua_array)>=4){
						/*
						* 客户端SDK版本号
						*/
						$this->sdk = Util::cleanUrlParam($ua_array[0]);
						/*
						* ROM版本号，去掉ROM信息取前10个字符
						*/
						$this->rom = Util::cleanUrlParam(mb_substr(trim($ua_array[1]),0,10));
						/*
						* 输入法版本值
						*/
						$this->versioncode=intval($ua_array[4]);
					}
					break;
			}
        }

        $this->fetchUserInfo();

        //先从缓存取
        $bolStatus = false;
        $noti_switch = $this->cache->get($this->vuVersionUpgradeMessageNswConfCachePre, $bolStatus);

        if ($noti_switch === false || is_null($noti_switch)) {
            //获取迁移ORP通知
            $search = '{"content.key":"oem_lc_switch"}';
            $header = array(
            	'pathinfo' => $this->strUnifyConfResRoot,
            	'querystring' => array(
                	"search" => urlencode($search),
                	'onlycontent' => 1,
                	'searchbyori' => 1,
            	),
            );

            $header['querystring'] = http_build_query($header['querystring']);
            $result = ral('res_service', 'get', null, rand(), $header);
            if (isset($result) && !empty($result)) {
                if (is_array($result)){
                    $result = array_pop($result);
                }

                if (isset($result['key']) && isset($result['value']) && $result['key'] == 'oem_lc_switch') {
                    $this->oem_lc_switch = intval($result['value']);
                    //拿到数据写缓存
                    $this->cache->set($this->vuVersionUpgradeMessageNswConfCachePre, $this->oem_lc_switch, $this->intCacheExpired);
                }
            }
        }else{
            $this->oem_lc_switch = $noti_switch;
        }
    }


    /**
     *
     * 手动检测版本更新接口
     *
     * @route({"POST", "/chk"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
        [
            {
                "update": "Y",     //是否有更新
                "vercode": "52",   //最新版本值
                "version": "3-7-1-12",   //最新版本号
                "dlink": "",        //下载地址
                "token": "700134832056d063183fed082666c4ab",   //下载文件md5值
                "input_sign": "rXDVbuerJ28Mn6DBgmizTx1KV_Sznx6LfPhoN8bnBLbME8atQmyjY41ZUbnBALm1pfao1AUBTIJmQuDenoP-RzY8zWOiBJoiw3MtOMY-HSsMzXMoMgNETo4Sbu6gl9iM9F6hGAeXdqymPSm1NZJRyB4PiLUXsYxOV0TRLwllQfjHuv8K_a2RigPW28jMuv8E_a2O8gOq-igpu2idguvwi-qOB\u0000", lc升级漏洞对token(md5)用rsa签名
                "size": 4895952,   //下载文件大小
                "summary": ""     //更新简介
                "force_update": 1,   //是否强制更新, 0否 1是
                "iconurl": "",   //增量更新下发字段（手机助手调用需要）
                "signmd5": "",   //增量更新下发字段，签名md5
                "update_time": "",  //增量更新下发字段，更新时间
                "patch_downurl": "",   //增量包下载地址
                "patch_size": 5345234,  //增量包大小
                "appsearch": {   //手机助手相关信息（增量更新用到）
                	"iconurl": "",   //应用图标的下载地址
                	"label": "",  //应用LABEL名称，用于下载名称展示
                	"packagename": "",  //应用的package名字
                	"vname": "",  //新版本号
                	"downurl": "",  //apk下载地址
                	"vcode": "",  //应用的可更新versioncode
                	"signmd5": "",  //服务器下发的apk对应的signmd5
                	"updatetime": "",  //更新软件发布时间
                	"size": "",  //更新包的大小
                	"patch_downurl": "",  //更新patch的下载路径
                	"patch_size": "",  //增量包的大小
                	"changelog": "",  //更新日志
                }
            }
        ]
        }
     * */
    public function chk() {
        $VuModel = IoCload('models\\VuModel');
        $VuModel->cache_ttl = $this->intCacheExpired;
        
        //Edit by fanwenli on 2017-02-07, add $autoDownload in this function
        if(isset($_REQUEST["auto_download"]) && $_REQUEST["auto_download"] !== '') {
            $auto_download_sign = intval($_REQUEST["auto_download"]);
        } else {
            //if there did not post the auto_download, then set it with 2
            $auto_download_sign = 2;
        }
        
        $res = $VuModel->fetchVersionUpdateMsg($this->oem_lc_switch, $this->platform, $this->phoneinfo, $this->userinfo, $this->softinfo, $this->strLcIncUpgradeAddress, $this->strLcUpgradeAddress, false, $auto_download_sign);

        return $res;
    }

    /**
     *
     * 带三维词库更新检测的版本更新检测接口
     *
     * @route({"POST", "/chkw"})
     * @throws({"BadRequest", "status", "400 Bad request"})
     * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
        [
            {
            	"update": "Y",     //是否有更新
            	"vercode": "52",   //最新版本值
            	"version": "3-7-1-12",   //最新版本号
            	"dlink": "",        //下载地址
            	"token": "700134832056d063183fed082666c4ab", //下载文件md5值
            	"size": 4895952,   //下载文件大小
            	"summary": ""     //更新简介
            	"wdu_update": "Y",     //三维词库是否有更新
            	"wduver": 20130516,   //最新三维词库版本
            	"vtype": 1              //最新三维词库类型
            	"force_update": 1,   //是否强制更新, 0否 1是
            	"iconurl": "",   //增量更新下发字段（手机助手调用需要）
            	"signmd5": "",   //增量更新下发字段，签名md5
            	"update_time": "",  //增量更新下发字段，更新时间
            	"patch_downurl": "",   //增量包下载地址
            	"patch_size": 5345234,  //增量包大小
            	"appsearch": {   //手机助手相关信息（增量更新用到）
            		"iconurl": "",   //应用图标的下载地址
            		"label": "",  //应用LABEL名称，用于下载名称展示
            		"packagename": "",  //应用的package名字
            		"vname": "",  //新版本号
            		"downurl": "",  //apk下载地址
            		"vcode": "",  //应用的可更新versioncode
            		"signmd5": "",  //服务器下发的apk对应的signmd5
            		"updatetime": "",  //更新软件发布时间
            		"size": "",  //更新包的大小
            		"patch_downurl": "",  //更新patch的下载路径
            		"patch_size": "",  //增量包的大小
            		"changelog": "",  //更新日志
            	}
            }
        ]
        }
     * */
    public function chkw() {
        $retdata = array();
        $retdata['update'] = 'N';

        $vtype = isset($_GET['vtype']) ? intval($_GET['vtype']) : 1;
        $wduver = isset($_GET['wduver']) ? intval($_GET['wduver']) : 0;
        $emgc = isset($_GET['emgc']) ? intval($_GET['emgc']) : 0;

        $emgc_flag = 0;//android planB检测版本升级紧急开关，紧急情况才是1，平日是0
        if ($emgc != 1 || $emgc_flag) {
        	//检测输入法版本是否有更新
        	$VuModel = IoCload('models\\VuModel');
        	$VuModel->cache_ttl = $this->intCacheExpired;
        	$retdata = $VuModel->fetchVersionUpdateMsg($this->oem_lc_switch, $this->platform, $this->phoneinfo, $this->userinfo, $this->softinfo, $this->strLcIncUpgradeAddress, $this->strLcUpgradeAddress);
        }
        //检测三维词库是否有更新(wifi条件下才检测)
        $check_wdu_switch = false;
        if (self::CHECK_WDU_UPDATE_SWITCH_IN_WIFI === true && $this->userinfo['network'] === 'wifi') {
            $check_wdu_switch = true;
        }
        $wdu_update = 'N';
        if ($check_wdu_switch === true) {
            $cache_key = $this->vuWduLatestVersionCachePre.'_'.$vtype;
            $latest_version = $this->cache->get($cache_key);
            if ($latest_version === false || is_null($latest_version)) {
                $NtThreeDWordModel = new NtThreeDWordModel($vtype);
                $latest_version = $NtThreeDWordModel->getLatestVersion($wduver);
                $this->cache->set($cache_key, $latest_version, $this->intCacheExpired);
            }
            $latest_version = intval($latest_version);
            if ($wduver < $latest_version) {
                $wdu_update = 'Y';
            }
            $retdata['wdu_update'] = $wdu_update;
            $retdata['wduver'] = $latest_version;
            $retdata['vtype'] = $vtype;
        }
        else {
            $retdata['wdu_update'] = $wdu_update;
        }

        return $retdata;
    }

    /**
     * 提取用户get, post相关数据
     * 同时对加密数据进行解密
     */
    public function fetchUserInfo() {
        //get参数
        $typeid	= trim($_GET["typeid"]);
        $update_type = trim($_GET["update_type"]);

        //post参数
        $clone_post = array();
        foreach ($_POST as $key => $value) {
            $clone_post[$key] = $value;
        }

        //参数解密
        if ($this->env === 2 && isset($clone_post['enfield'])) {
            $encode_fields_str = trim($clone_post['enfield']);
            $encode_fields_array = explode('|', $encode_fields_str);
            foreach ($encode_fields_array as $encode_field) {
                if (strlen($encode_field) > 0 && isset($clone_post[$encode_field]) && strlen($clone_post[$encode_field]) > 0) {
                    $encodestr = $clone_post[$encode_field];
                    $decode_result = bd_B64_Decode($encodestr, 0);
                    if ($decode_result !== false) {
                        $clone_post[$encode_field] = $decode_result;
                    }
                }
            }
        }

        //****************************手机相关信息获取****************************/
        if (isset($clone_post['brand'])) {
            $this->phoneinfo['brand'] = $clone_post['brand'];
        } else {
            $this->phoneinfo['brand'] = '';
        }

        if (isset($clone_post['model']) && $clone_post['model'] !== '') {
            $this->phoneinfo['model'] = $clone_post['model'];
        } else {
            $this->phoneinfo['model'] = $this->model;
        }

        if (isset($clone_post['resolution']) && $clone_post['resolution'] !== '') {
            $this->phoneinfo['resolution'] = str_replace('*', 'x', $clone_post['resolution']);
        } else {
            $this->phoneinfo['resolution'] = $this->screenX.'x'.$this->screenY;
        }

        if (isset($clone_post['screen_size'])) {
            $this->phoneinfo['screen_size'] = $clone_post['screen_size'];
        } else {
            $this->phoneinfo['screen_size'] = '';
        }

        if (isset($clone_post['cpu'])) {
            $this->phoneinfo['cpu'] = $clone_post['cpu'];
        } else {
            $this->phoneinfo['cpu'] = '';
        }

        $this->phoneinfo['phone_os'] = Util::getPhoneOS($this->platform);
        if ($this->phoneinfo['phone_os'] === 'android') {
            $this->phoneinfo['android_os'] = Util::getAndroidOS($this->platform);
        } else if ($this->phoneinfo['phone_os'] === 'ios') {
            $this->phoneinfo['android_os'] = 'IPhone';
        }

        $this->phoneinfo['sdk_version'] = intval($this->sdk);

        $this->phoneinfo ['os_version'] = str_replace ( '-', '.', $this->rom );
        $osinfo = explode ( '.', $this->phoneinfo ['os_version'] );
		if (count ( $osinfo ) > 0) {
			$this->phoneinfo ['os_main_version'] = $osinfo [0];
		} else {
			$this->phoneinfo ['os_main_version'] = '';
		}

        //****************************用户相关信息获取****************************/
        $this->userinfo['uid'] = $this->uid;
        $this->userinfo['cuid'] = $this->cuid;

        if (isset($clone_post['imei']) && $clone_post['imei'] !== '') {
            $this->userinfo['imei'] = strrev($clone_post['imei']);
        } else {
            $this->userinfo['imei'] = $this->imei;
        }

        if ($typeid !== '') {
            $this->userinfo['platform'] = $typeid;
        } else {
            $this->userinfo['platform'] = $this->platform;
        }

        $this->userinfo['from'] = $this->from;

        if (isset($clone_post['channel']) && $clone_post['channel'] !== '') {
            $this->userinfo['cfrom'] = $clone_post['channel'];
        } else {
            $this->userinfo['cfrom'] = $this->cfrom;
        }
        //没有传cfrom的用from
        if ($this->userinfo['cfrom'] === '') {
            $this->userinfo['cfrom'] = $this->from;
        }

        //联网类型
        if(isset($_REQUEST['sp']) && $_REQUEST['sp'] != ''){
            $this->userinfo['sp'] = intval($_REQUEST['sp']);
        } else {
            $this->userinfo['sp'] = 12;
        }

        $this->userinfo['network'] = ($this->userinfo['sp'] === self::WIFI_FLAG_OF_SP)?'wifi':'gprs';

        $this->userinfo['client_ip'] = $this->client_ip;

        if (isset($clone_post['area'])) {
            $this->userinfo['area'] = $clone_post['area'];
        } else {
            $this->userinfo['area'] = '';
        }

        //****************************输入法版本相关信息获取****************************/
        //母包类型
        if (isset($clone_post['original_type']) && $clone_post['original_type'] !== '') {
            $this->softinfo['original_type'] = intval($clone_post['original_type']);
        } else {
            $this->softinfo['original_type'] = 1;
        }

        //升级类型
        if (isset($clone_post['type']) && $clone_post['type'] !== '') {
            $this->softinfo['type'] = $clone_post['type'];
        } else {
            $this->softinfo['type'] = 'apk';
        }

        //版本号
        if (isset($clone_post['version_name']) && $clone_post['version_name'] !== '') {
            $this->softinfo['version_name'] = str_replace('-', '.', $clone_post['version_name']);
        } else {
            $this->softinfo['version_name'] = str_replace('-', '.', $this->version);
        }
        $this->userinfo['version_name'] = $this->softinfo['version_name'];

        //版本值
        if (isset($clone_post['version_code']) && $clone_post['version_code'] !== '') {
            $this->softinfo['version_code'] = intval($clone_post['version_code']);
        } else {
            $this->softinfo['version_code'] = $this->versioncode;
        }
        $this->userinfo['version_code'] = $this->softinfo['version_code'];

        //包名
        if (isset($clone_post['package_name']) && $clone_post['package_name'] !== '') {
            $this->softinfo['package_name'] = $clone_post['package_name'];
        } else {
            $this->softinfo['package_name'] = self::IME_DEFAULT_PACKAGENAME;
        }

        //激活时间
        if (isset($clone_post['active_time']) && $clone_post['active_time'] !== '') {
        	$this->softinfo['active_time'] = intval($clone_post['active_time']);
        	//如果是毫秒, 转化为秒
        	if (mb_strlen($this->softinfo['active_time']) > 12) {
        		$this->softinfo['active_time'] = $this->softinfo['active_time'] / 1000;
        	}

        } else {
            $this->softinfo['active_time'] = 0;
        }

        //更新类型, 目前只针对iPhone版本
        //update_type = 0 通过cydia源更新, update_type = 1 应用内更新
        $this->softinfo['update_type'] = intval($update_type);

        //系统版本, 增量更新用到
        if (isset($clone_post['os_version'])) {
        	$this->phoneinfo['os_version'] = $clone_post['os_version'];
        } else {
        	$this->phoneinfo['os_version'] = '';
        }

        //系统内存状况(可用内存和总内存), 增量更新用到
        if (isset($clone_post['ava_mem'])) {
        	$this->phoneinfo['ava_mem'] = $clone_post['ava_mem'];
        } else {
        	$this->phoneinfo['ava_mem'] = '';
        }

        if (isset($clone_post['tot_mem'])) {
        	$this->phoneinfo['tot_mem'] = $clone_post['tot_mem'];
        } else {
        	$this->phoneinfo['tot_mem'] = '';
        }

        //当前待升级apk包的md5值, 增量更新用到
        if (isset($clone_post['usermd5'])) {
        	$this->softinfo['usermd5'] = $clone_post['usermd5'];
        } else {
        	$this->softinfo['usermd5'] = '';
        }

        //手机助手的md5值, 增量更新用到
        if (isset($clone_post['appsearchmd5'])) {
        	$this->softinfo['appsearchmd5'] = $clone_post['appsearchmd5'];
        } else {
        	$this->softinfo['appsearchmd5'] = '';
        }
    }


    /**
	 * 解密http query string中被cen字段标识的需要秘密的参数，cen字段以"_"分割需要解密的参数名
	 * @param array $block 参数数组
	 * @return bool
	 */
	public static function filterHttpBlock(&$block){
		if(!isset($block['cen'])){
			return false;
		}
		$fields_str=trim($block['cen']);
		$fields_arr=explode('_', $fields_str);
		$fields_arr = array_unique($fields_arr);

		foreach ($fields_arr as $field){
			if(intval($field)===0 && strlen($field)>0 && isset($block[$field]) && strlen($block[$field])>0){

				$encodestr=$block[$field];
				/*
				 * url中的参数加密采用B64加密
				 */
				$decode_result = \B64Decoder::decode($encodestr, 0);

				if($decode_result!==false){
					$block[$field]=$decode_result;
				}
			}
		}
	}
}