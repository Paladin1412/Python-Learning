<?php
/***************************************************************************
 *
* Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\DbConn;


require_once __DIR__.'/utils/B64Decoder.php';
require_once __DIR__.'/utils/CurlRequest.php';
ClassLoader::addInclude(__DIR__.'/utils');
ClassLoader::addInclude(__DIR__.'/noti');


/**
 * 通知中心
 *
 * @author yangxugang
 * @path("/noti/")
 */
class Noti
{
	/**
	 * 城市信息
	 *
	 */
	private $arrCityInfo = array();

	/**
	 * 通知中心接口状态码与提示信息对应表
	 *
	 */
	public static $arrStatusMsg = array(
		-1 => 'No post data or post data name is wrong!',
		-2 => 'The post data of json format cannot be decoded!',
	);

	/**
	 * 输入法默认包名(LC升级平台用到)
	 * @var string
	 */
	const IME_DEFAULT_PACKAGENAME = 'com.baidu.input';

	 /**
     * 通知中心最多保留消息条数
     * @var int
     */
    const NOTI_NESSAGE_MAX_KEEP_NUM = 100;

	/**
	 * 流行词id(固定为1000)
	 *
	 */
	const HOT_WORDS_ID = 1000;

	/**
	 * 系统词库id起始值
	 *
	 */
	const SYS_WORDS_MIN_ID = 100000;

	/**
	 * wifi网络对应的sp代号
	 *
	 */
	const WIFI_FLAG_OF_SP = 2;

	/**
	 * 本地保存地理词库最大数目
	 * @var int
	 */
	const LOCAL_CELL_WORDS_MAX_KEEP_NUM = 3;

	/** @property 内部缓存实例(apc内存缓存) */
	private $cache;

	/** @property mic http root */
	private $strMicHttpRoot;

	/** @property 统一配置缓存key pre */
	private $strUnifyConfCachePre;

	/** @property 缓存过期时长*/
	private $intCacheExpired;

	/** @property 统一资源配置资源服务路径*/
	private $strUnifyConfResRoot;

	/** @property 通知中心限制重复请求时间间隔 6*3600 = 21600 秒即6小时*/
	private $intCallInterval;

	/** @property 通知中心请求资源缓存key pre*/
	private $strAdNotiCachePre;

	/** @property 通知中心请求资源内容缓存key pre*/
	private $strResContentCachePre;

	/** @property 资源服务内网地址*/
	private $strResourceInnerUrl;

	/** @property v4外网请求地址用于拼接下载地址*/
	private $strV4HttpRoot;
	
	/** @property v5外网请求地址用于拼接下载地址*/
	private $strV5HttpRoot;

	/** @property 通知中心资源配置路径*/
	private $strNotiResRoot;

	/** @property 剪切板黑名单配置路径*/
	private $strClipboardBlacklistRoot;

	/** @property 皮肤缓存key pre*/
	private $strSkinCachePre;

	/** @property 表情缓存key pre*/
	private $strEmojiCachePre;

	/** @property 教程缓存key pre*/
	private $strLessonCachePre;

	/** @property app推荐缓存key pre*/
	private $strAppRecommendCachePre;

	/** @property 活动缓存key pre*/
	private $strEventsCachePre;

	/** @property 分类词库及地理词库推荐消息缓存key pre*/
	private $strCellsCachePre;

	/** @property 剪切板黑民单配置缓存key pre*/
	private $strClipboardBlacklistCachePre;

	/** @property 广告热词消息缓存key pre*/
	private $strAdHotWordCachePre;

	/** @property 广告插件消息缓存key pre*/
	private $strPluginCachePre;

	/** @property 三维词库消息缓存key pre*/
	private $strWduCachePre;

	/** @property 流行词库消息缓存key pre*/
	private $strHotwordsCachePre;

	/** @property 分类词库消息缓存key pre*/
	private $strAdCellCachePre;

	/** @property 系统词库消息缓存key pre*/
	private $strWsysCachePre;

	/** @property 手写模版消息缓存key pre*/
	private $strHandwriteCachePre;

	/** @property 直达号消息缓存key pre*/
	private $strZhidahaoCachePre;

	/** @property 启动屏消息缓存key pre*/
	private $strSplashCachePre;

	/** @property 场景化更新消息缓存key pre*/
	private $strSceneCachePre;

	/** @property 统计控制客户端上传消息缓存key pre*/
	private $strReportCachePre;

	/** @property 新场景更新通知消息缓存key pre*/
	private $strNewSceneCachePre;

	/** @property 云输入链接打开类型消息缓存key pre*/
	private $strCloudInputTypeCachePre;

	/** @property 地理位置消息缓存key pre*/
	private $strLocationCachePre;

	/** @property 应用内侧滑配置消息缓存key pre*/
	private $strSlideCachePre;

	/** @property cc testuid*/
	private $arrTestUid;

	/** @property 表情最新版本缓存 key pre*/
	private $strEmojiVerCachePre;

	/** @property 获取仓颉最新版本缓存 key pre*/
	private $strCangjieVerCachePre;

	/** @property 获取注音最新版本缓存 key pre*/
	private $strZhuyinVerCachePre;

	/** @property 动态表情模板更新提醒缓存 key pre*/
	private $strDemojiLatestVerCachePre;

	/** @property android客户端调用本地框所适用的框信息缓存 key pre*/
	private $strSearchWithAppCachePre;

	/** @property 广告更新标记通知缓存 key pre*/
	private $strAdsCachePre;

	/** @property 通知中心颜文字“最热”通知缓存 key pre*/
	private $strEmoticonHotCachePre;

	/** @property 通知中心IOS内测版本更新消息缓存 key pre*/
	private $strIosBetaCachePre;

	/** @property 通知中心颜文字消息缓存 key pre*/
	private $strEmoticonCachePre;

	/** @property 通知中心版本升级消息缓存 key pre*/
	private $strSoftCachePre;

	/** @property LC升级平台接口地址*/
	private $strLcUpgradeAddress;

	/** @property LC增量升级接口地址*/
	private $strLcIncUpgradeAddress;

	/** @property debug模式默认关闭*/
	private $intDebugFlag;

	/** @property 资源服务根路径 */
	private $strResRoot;

	/** @property 版本更新缓存 */
	private $strSugWhiteCachePre;

	/** @property 新活动更新时间通知消息缓存key pre*/
	private $strActivityTimeCachePre;

	/** @property 更新TabOEM消息缓存key pre*/
	private $strTabOemCachePre;

	/** @property 更新IconOEM消息缓存key pre*/
	private $strIconOemCachePre;

	/** @property 更新SkinBearRemindOEM消息缓存key pre*/
	private $strSkinBearRemindOemCachePre;

	/** @property 更新DownBalanceTimeSet消息缓存key pre*/
	private $strDownBalanceTimeSetCachePre;

	/** @property 7.2个性化信息下发消息缓存key pre*/
	private $strCoreUserStatCachePre;

	/** @property 7.2手助liteicon下发缓存key pre*/
	private $strIconLiteCachePre;

	/** @property 7.2手助lite插件下载标志下发缓存key pre*/
	private $strPluginDownloadLiteCachePre;

	/** @property 7.2场景化语音条缓存key pre*/
	private $strSceneVoiceCandCachePre;

	/** @property 7.2乐视智能回复数据文件缓存key pre*/
	private $strLeDataRecoverCachePre;

	/** @property 7.2场景化地图和搜索语音条缓存key pre*/
	private $strSceneMapSearchVoiceCandCachePre;

	/** @property 7.2语音标点黑名单缓存key pre*/
	private $strPunctuationBlacklistCachePre;

	/** @property 7.3场景化语音通讯录缓存key pre*/
	private $strSceneAddressBookVoiceCachePre;

	/** @property 7.3极简语音缓存key pre*/
	private $strMinimalistVoiceCandCachePre;

	/** @property 7.3IOS场景化语音缓存key pre*/
	private $strIosSceneVoiceCandCachePre;
	
	/** @property 7.5智能强引导黑名单key pre*/
	private $strBlacklistIntGuidanceCachePre;
	
	/** @property 6.5彩蛋策略下发key pre*/
	private $strScreenEggsStrategyCachePre;
	
	/** @property 7.6分BundleID支持语音Option&Mode下发key pre*/
	private $strBundleIdVoiceCachePre;
	
	/** @property 7.6语料和谐及数据源管理下发key pre*/
	private $strStringSaftyReplaceCachePre;
	
	/** @property pushword资源服务路径*/
	private $strPushWordConfResRoot;
	
	/** @property pushword cache key pre*/
	private $strPushWordCachePre;
        
    /** @property 个性化动态热区开关 key pre*/
    private $strHotspotsCachePre;

    /** @property 高低端机型判断标准 key pre*/
    private $strPerformanceStdCachePre;
    
    /** @property IOS语音助手黑名单下发key pre*/
	private $strIosVoiceBlacklistCachePre;

    /** @property data_switch 开关下发的key  pre*/
	private $strNotiDataSwitchCachePre;

	/**
	 * 手机信息
	 *
	 */
	private $arrPhoneInfo = array();

	/**
	 * 用户信息相关参数
	 *
	 */
	private $arrUserInfo = array();

	/**
	 * 版本更新相关参数
	 *
	*/
	private $arrSoftInfo = array();

	/**
	 * 客户端接收到消息时间
	 *
	 */
	private $arrTimeInfo = array();

	/**
	 * 客户端激活时间
	 *
	 */
	private $intActiveTime = null;

	/**
	 * 词库信息
	 *
	 */
	private $WordsInfo = null;

	/**
	 * 输入法版本值
	 *
	 */
	private $intVersion = 0;
	
    /**
     * phaster线程池
     */
    private $phasterThreadPool = array();
	
	/**
	 * db
	 *
	 */
	public $objDb;

	/**
	 * dbx
	 */
	public $objDbX;

	/**
	 * 获取db实例
	 *
	 * @return db
	 */
	public function getDB(){
		return DBConn::getDb();
	}
	
	/**
	 * 获取phaster_xdb实例
	 *
	 * @return db
	 */
	public function getPhasterDbX(){
	    return DBConn::getPhasterXdb();
	}
	
	/**
	 * 归还phaster_xdb实例
	 * @param db
	 * @return db
	 */
	public function returnPhasterDbX($conn){
	    return DBConn::returnPhasterXdb($conn);
	}

	/**
	 * 获取xdb实例
	 *
	 * @return db
	 */
	public function getDbX() {
	    return DBConn::getXdb();
	}

	/**
	 * 获取客户端IP，屏蔽transmit等前端的影响
	 * @return string
	 */
	public static function getClientIP() {
		$strOnlineIp = Util::getClientIP();

		return $strOnlineIp;
	}

	/**
	 * 获取资源服务内网地址
	 * @return string
	 */
	public function getResourceInnerUrl(){
		$arrSplit = explode(';', $this->strResourceInnerUrl);
		$strUrl = $arrSplit[array_rand($arrSplit, 1)];
		return $strUrl;
	}

	/**
	 * 获取http请求结果
	 *
	 * @param $strUrl 请求url
	 * @param $strMethod http method
	 * @param $arrPostField http post field
	 * @param $intTimeout 超时 单位秒
	 * @param $arrHeader header
	 *
	 * @return array
	 */
	public function getHttpRequestResponse($strUrl, $strMethod, $arrPostField = null, $intTimeout = 1, $arrHeader = null){
		$arrParams = array (
			'url' => $strUrl,
			'method' => $strMethod,
			'post_fields' => $arrPostField,
			'timeout' => $intTimeout,
		);

		if( (null !== $arrHeader) && (0 !== count($arrHeader)) ){
			$arrParams['header'] = $arrHeader;
		}

		$objCurlRequest = new CurlRequest ();
		$objCurlRequest->init( $arrParams );
		$arrResult = $objCurlRequest->exec();
		curl_close ($objCurlRequest->ch);

		return $arrResult;
	}

	/**
	 * 通过path获取资源内容json格式
	 *
	 * @param $strPath 请求path
	 * @param $strQuery query string
	 *
	 * @return string
	 */
	public function getResource($strPath, $strQuery = null){
		//获取content信息
		if(null === $strQuery){
			$arrHeader = array(
				'pathinfo' => $strPath,
			);
		}else{
			$arrHeader = array(
				'pathinfo' => $strPath,
				'querystring'=> $strQuery,
			);
		}

		$strResult = ral("res_service", "get", null, rand(), $arrHeader);
		if(false === $strResult){
			return false;
		}

		return $strResult;
	}

	/**
	 * 通过path获取资源内容为string类型
	 * 对资源内容缓存
	 *
	 * @param $strPath 请求path
	 * @param $strQuery query string
	 *
	 * @return string
	 */
	public function getResourceString($strPath, $strQuery = null){
	    $strCacheKey = $this->strResContentCachePre . $strPath;
	    $bolStatus = false;
	    $strCache = $this->cache->get($strCacheKey, $bolStatus);
	    if (null !== $strCache){
	        return $strCache;
	    }
	    //获取content信息
	    $strResUrl = $this->getResourceInnerUrl();

	    $Orp_FetchUrl = new \Orp_FetchUrl();
	    $httpproxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));

	    $strUrl = $strResUrl . $strPath;
	    $strResult = $httpproxy->get($strUrl);

	    if($httpproxy->http_code() != 200) {
	        return false;
	    }

	    //set cache
	    $this->cache->set($strCacheKey, $strResult, $this->intCacheExpired);

	    return $strResult;
	}

	/**
	 * 请求v5接口
	 *
	 * @param $strPath 请求path
	 * @param $strQuery query string
	 *
	 * @return string
	 */
	public function getV5Resource($strPath, $strQuery = null){
	    //获取content信息
	    if(null === $strQuery){
	        $arrHeader = array(
	            'pathinfo' => $strPath,
	        );
	    }else{
	        $arrHeader = array(
	            'pathinfo' => $strPath,
	            'querystring'=> $strQuery,
	        );
	    }

	    $strResult = ral("api_v5_service", "get", null, rand(), $arrHeader);
	    if(false === $strResult){
	        return false;
	    }

	    return $strResult;
	}

	/**
	 * 手机信息相关参数解密
	 * @param $env int 加密版本,当前最新版本值为2
	 * @param $userPostInfo array 用户post数据
	 *
	 * @return array 解密之后的用户信息
	 */
	public function decodeUserNotiInfo($env, &$userPostInfo) {
		if ($env == 2 && isset($userPostInfo['enfield'])) {
			$encode_fields_str = trim($userPostInfo['enfield']);
			$encode_fields_array = explode('|', $encode_fields_str);
			foreach ($encode_fields_array as $encode_field) {
				if (strlen($encode_field) > 0 && isset($userPostInfo[$encode_field]) && (is_array($userPostInfo[$encode_field]) || strlen($userPostInfo[$encode_field]) > 0)) {
					if (is_array($userPostInfo[$encode_field])) {
						foreach ($userPostInfo[$encode_field] as $key => $value) {
							$decode_result = \B64Decoder::decode($value, 0);
							if ($decode_result !== false) {
								$userPostInfo[$encode_field][$key] = $decode_result;
							}
						}
					} else {
						$encodestr = $userPostInfo[$encode_field];
						$decode_result = \B64Decoder::decode($encodestr, 0);
						if ($decode_result !== false) {
							$userPostInfo[$encode_field] = $decode_result;
						}
					}
				}
			}
		}
	}

	/**
	 * 返回输入法版本数值
	 * 如5.1.1.5 5010105
	 *
	 * @param strVersionName version name
	 * @param intDigit 位数
	 *
	 * @return string
	 */
	public static function getVersionIntValue($strVersionName, $intDigit = 4){
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
	 * 根据平台号获取手机操作系统类型
	 * @param $platform string 手机平台号
	 *
	 * @return string (ios, symbian, mac, android)
	 */
	public static function getPhoneOS($platform) {
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
	 * 根据平台号获取安卓系统类型
	 * @param $platform string 手机平台号
	 *
	 * @return string (Android_Phone, Android_Pad)
	 */
	public static function getAndroidOS($platform) {
	    $platform = strtolower($platform);
	    if ($platform === 'a1') {
	        return 'Android_Phone';
	    } elseif ($platform === 'a9') {
	        return 'Android_Pad';
	    } elseif (substr($platform, 0, 4) === 'p-a1') {
	        return 'Android_Phone';
	    } elseif (substr($platform, 0, 4) === 'p-a9') {
	        return 'Android_Pad';
	    } else {
	        return 'Android_Phone';
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

	    $this->arrPhoneInfo['phone_os'] = $this->getPhoneOS($strPlatform);
	    if ($this->arrPhoneInfo['phone_os'] === 'android') {
            $this->arrPhoneInfo['android_os'] = self::getAndroidOS($strPlatform);
        } else if ($this->arrPhoneInfo['phone_os'] === 'ios') {
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
	 * 获取上次通知获得时间
	 * @param $arrUserPostInfo 用户post数据
	 * @param $strVersion 输入法版本
	 *
	 * @return array
	 */
	public function getTimeInfo($arrUserPostInfo, $strVersion){

		if (isset($arrUserPostInfo['message_time'])) {
			$this->arrTimeInfo['ad_skin'] = isset($arrUserPostInfo['message_time']['ad_skin']) ? intval($arrUserPostInfo['message_time']['ad_skin']) : 0;
			$this->arrTimeInfo['ad_emoji'] = isset($arrUserPostInfo['message_time']['ad_emoji']) ? intval($arrUserPostInfo['message_time']['ad_emoji']) : 0;
			$this->arrTimeInfo['lessons'] = isset($arrUserPostInfo['message_time']['lessons']) ? intval($arrUserPostInfo['message_time']['lessons']) : 0;
			$this->arrTimeInfo['recommends'] = isset($arrUserPostInfo['message_time']['recommends']) ? intval($arrUserPostInfo['message_time']['recommends']) : 0;
			$this->arrTimeInfo['events'] = isset($arrUserPostInfo['message_time']['events']) ? intval($arrUserPostInfo['message_time']['events']) : 0;
			$this->arrTimeInfo['cells'] = isset($arrUserPostInfo['message_time']['cells']) ? intval($arrUserPostInfo['message_time']['cells']) : 0;
			$this->arrTimeInfo['clipboard_blacklist'] = isset($arrUserPostInfo['message_time']['clipboard_blacklist']) ? intval($arrUserPostInfo['message_time']['clipboard_blacklist']) : 0;
			$this->arrTimeInfo['ad_hot_word'] = isset($arrUserPostInfo['message_time']['ad_hot_word']) ? intval($arrUserPostInfo['message_time']['ad_hot_word']) : 0;
			$this->arrTimeInfo['ad_plugin'] = isset($arrUserPostInfo['message_time']['ad_plugin']) ? intval($arrUserPostInfo['message_time']['ad_plugin']) : 0;

			$this->arrTimeInfo['w1000'] = isset($arrUserPostInfo['message_time']['w1000']) ? intval($arrUserPostInfo['message_time']['w1000']) : 0;
			$this->arrTimeInfo['wcell'] = isset($arrUserPostInfo['message_time']['wcell']) ? intval($arrUserPostInfo['message_time']['wcell']) : 0;
			$this->arrTimeInfo['wsys'] = isset($arrUserPostInfo['message_time']['wsys']) ? intval($arrUserPostInfo['message_time']['wsys']) : 0;
			$this->arrTimeInfo['wdu'] = isset($arrUserPostInfo['message_time']['wdu']) ? intval($arrUserPostInfo['message_time']['wdu']) : 0;

			$this->arrTimeInfo['handwrite'] = isset($arrUserPostInfo['message_time']['handwrite']) ? intval($arrUserPostInfo['message_time']['handwrite']) : 0;
			$this->arrTimeInfo['zhida'] = isset($arrUserPostInfo['message_time']['zhida']) ? intval($arrUserPostInfo['message_time']['zhida']) : 0;
			$this->arrTimeInfo['splash'] = isset($arrUserPostInfo['message_time']['splash']) ? intval($arrUserPostInfo['message_time']['splash']) : 0;

			$this->arrTimeInfo['scene'] = isset($arrUserPostInfo['message_time']['scene']) ? intval($arrUserPostInfo['message_time']['scene']) : 0;
			$this->arrTimeInfo['report'] = isset($arrUserPostInfo['message_time']['report']) ? intval($arrUserPostInfo['message_time']['report']) : 0;
			$this->arrTimeInfo['cloud_input_link'] = isset($arrUserPostInfo['message_time']['cloud_input_link']) ? intval($arrUserPostInfo['message_time']['cloud_input_link']) : 0;
			$this->arrTimeInfo['location'] = isset($arrUserPostInfo['message_time']['location']) ? intval($arrUserPostInfo['message_time']['location']) : 0;
			$this->arrTimeInfo['position'] = isset($arrUserPostInfo['message_time']['position']) ? intval($arrUserPostInfo['message_time']['position']) : 0;
			$this->arrTimeInfo['new_scene'] = isset($arrUserPostInfo['message_time']['new_scene']) ? intval($arrUserPostInfo['message_time']['new_scene']) : 0;

			//if( $this->intVersion >= 5030000 ){
			if( Util::boolVersionPlatform(5030000) ){
			    $this->arrTimeInfo['slide'] = isset($arrUserPostInfo['message_time']['slide']) ? intval($arrUserPostInfo['message_time']['slide']) : 0;
			    $this->arrTimeInfo['search_with_app'] = isset($arrUserPostInfo['message_time']['search_with_app']) ? intval($arrUserPostInfo['message_time']['search_with_app']) : 0;
			    $this->arrTimeInfo['ads'] = isset($arrUserPostInfo['message_time']['ads']) ? intval($arrUserPostInfo['message_time']['ads']) : 0;
			    $this->arrTimeInfo['emoticon_hot'] = isset($arrUserPostInfo['message_time']['emoticon_hot']) ? intval($arrUserPostInfo['message_time']['emoticon_hot']) : 0;
			}

			//$this->arrTimeInfo['ios_beta'] = isset($arrUserPostInfo['message_time']['ios_beta']) ? intval($arrUserPostInfo['message_time']['ios_beta']) : 0;
			$this->arrTimeInfo['ad_emoticon'] = isset($arrUserPostInfo['message_time']['ad_emoticon']) ? intval($arrUserPostInfo['message_time']['ad_emoticon']) : 0;
			//$this->arrTimeInfo['soft'] = isset($arrUserPostInfo['message_time']['soft']) ? intval($arrUserPostInfo['message_time']['soft']) : 0;

			//Edit by fanwenli, 2015-12-31
            $this->arrTimeInfo['activity_time'] = isset($arrUserPostInfo['message_time']['activity_time']) ? intval($arrUserPostInfo['message_time']['activity_time']) : 0;

			//Edit by fanwenli, 2016-03-08
            $this->arrTimeInfo['tab_oem'] = isset($arrUserPostInfo['message_time']['tab_oem']) ? intval($arrUserPostInfo['message_time']['tab_oem']) : 0;

            //Edit by fanwenli, 2016-03-11
            $this->arrTimeInfo['icon_oem'] = isset($arrUserPostInfo['message_time']['icon_oem']) ? intval($arrUserPostInfo['message_time']['icon_oem']) : 0;
            $this->arrTimeInfo['skin_bear_remind_oem'] = isset($arrUserPostInfo['message_time']['skin_bear_remind_oem']) ? intval($arrUserPostInfo['message_time']['skin_bear_remind_oem']) : 0;

			//Edit by fanwenli, 2016-04-05
			$this->arrTimeInfo['down_balance_time_set'] = isset($arrUserPostInfo['message_time']['down_balance_time_set']) ? intval($arrUserPostInfo['message_time']['down_balance_time_set']) : 0;

			//Edit by fanwenli, 2016-07-19
			$this->arrTimeInfo['core_user_stat'] = isset($arrUserPostInfo['message_time']['core_user_stat']) ? intval($arrUserPostInfo['message_time']['core_user_stat']) : 0;

			//Edit by fanwenli, 2016-07-21
			$this->arrTimeInfo['icon_lite'] = isset($arrUserPostInfo['message_time']['icon_lite']) ? intval($arrUserPostInfo['message_time']['icon_lite']) : 0;

			//Edit by fanwenli, 2016-07-27
			$this->arrTimeInfo['plugin_download_lite'] = isset($arrUserPostInfo['message_time']['plugin_download_lite']) ? intval($arrUserPostInfo['message_time']['plugin_download_lite']) : 0;

			//Edit by fanwenli, 2016-10-11
			$this->arrTimeInfo['scene_voice_cand'] = isset($arrUserPostInfo['message_time']['scene_voice_cand']) ? intval($arrUserPostInfo['message_time']['scene_voice_cand']) : 0;

			//Edit by fanwenli, 2016-10-14
			$this->arrTimeInfo['le_data_recover'] = isset($arrUserPostInfo['message_time']['le_data_recover']) ? intval($arrUserPostInfo['message_time']['le_data_recover']) : 0;

			//Edit by fanwenli, 2016-10-20
			$this->arrTimeInfo['scene_map_search_voice_cand'] = isset($arrUserPostInfo['message_time']['scene_map_search_voice_cand']) ? intval($arrUserPostInfo['message_time']['scene_map_search_voice_cand']) : 0;

			//Edit by fanwenli, 2016-10-24
			$this->arrTimeInfo['punctuation_blacklist'] = isset($arrUserPostInfo['message_time']['punctuation_blacklist']) ? intval($arrUserPostInfo['message_time']['punctuation_blacklist']) : 0;

			//Edit by fanwenli, 2016-12-05
			$this->arrTimeInfo['scene_address_book_voice'] = isset($arrUserPostInfo['message_time']['scene_address_book_voice']) ? intval($arrUserPostInfo['message_time']['scene_address_book_voice']) : 0;

			//Edit by fanwenli, 2016-12-12
			$this->arrTimeInfo['minimalist_voice_cand'] = isset($arrUserPostInfo['message_time']['minimalist_voice_cand']) ? intval($arrUserPostInfo['message_time']['minimalist_voice_cand']) : 0;
			//Edit by fanwenli, 2016-12-14
			$this->arrTimeInfo['ios_scene_voice_cand'] = isset($arrUserPostInfo['message_time']['ios_scene_voice_cand']) ? intval($arrUserPostInfo['message_time']['ios_scene_voice_cand']) : 0;

			//Edit by zhoubin05, 2016-12-14
            $this->arrTimeInfo['voice_pkg_white_list'] = isset($arrUserPostInfo['message_time']['voice_pkg_white_list']) ? intval($arrUserPostInfo['message_time']['voice_pkg_white_list']) : 0;

            //Edit by zhoubin05, 2016-12-14
            $this->arrTimeInfo['tips_icon_version'] = isset($arrUserPostInfo['message_time']['tips_icon_version']) ? intval($arrUserPostInfo['message_time']['tips_icon_version']) : 0;
            
            //Edit by fanwenli, 2017-04-26
            $this->arrTimeInfo['blacklist_int_guidance'] = isset($arrUserPostInfo['message_time']['blacklist_int_guidance']) ? intval($arrUserPostInfo['message_time']['blacklist_int_guidance']) : 0;
            
            //Edit by fanwenli, 2017-05-10
            $this->arrTimeInfo['screen_eggs_strategy'] = isset($arrUserPostInfo['message_time']['screen_eggs_strategy']) ? intval($arrUserPostInfo['message_time']['screen_eggs_strategy']) : 0;

		    //Edit by fanwenli, 2017-08-08
            $this->arrTimeInfo['bundle_id_voice'] = isset($arrUserPostInfo['message_time']['bundle_id_voice']) ? intval($arrUserPostInfo['message_time']['bundle_id_voice']) : 0;

            //Edit by fanwenli, 2017-08-15
            $this->arrTimeInfo['string_safty_replace'] = isset($arrUserPostInfo['message_time']['string_safty_replace']) ? intval($arrUserPostInfo['message_time']['string_safty_replace']) : 0;
            
            //Edit by fanwenli, 2018-04-25
            $this->arrTimeInfo['ios_voice_blacklist'] = isset($arrUserPostInfo['message_time']['ios_voice_blacklist']) ? intval($arrUserPostInfo['message_time']['ios_voice_blacklist']) : 0;
		
		}else{
			$this->arrTimeInfo['ad_skin'] = 0;
			$this->arrTimeInfo['ad_emoji'] = 0;
			$this->arrTimeInfo['lessons'] = 0;
			$this->arrTimeInfo['recommends'] = 0;
			$this->arrTimeInfo['events'] = 0;
			$this->arrTimeInfo['cells'] = 0;
			$this->arrTimeInfo['clipboard_blacklist'] = 0;
			$this->arrTimeInfo['ad_hot_word'] = 0;
			$this->arrTimeInfo['ad_plugin'] = 0;

			$this->arrTimeInfo['w1000'] = 0;
			$this->arrTimeInfo['wcell'] = 0;
			$this->arrTimeInfo['wsys'] = 0;
			$this->arrTimeInfo['wdu'] = 0;

			$this->arrTimeInfo['handwrite'] = 0;
			$this->arrTimeInfo['zhida'] = 0;
			$this->arrTimeInfo['splash'] = 0;

			$this->arrTimeInfo['scene'] = 0;
			$this->arrTimeInfo['report'] = 0;
			$this->arrTimeInfo['cloud_input_link'] = 0;
			$this->arrTimeInfo['location'] = 0;
			$this->arrTimeInfo['position'] = 0;
			$this->arrTimeInfo['new_scene'] = 0;

			//if( $this->intVersion >= 5030000 ){
			if( Util::boolVersionPlatform(5030000) ){
			    $this->arrTimeInfo['slide'] = 0;
			    $this->arrTimeInfo['search_with_app'] = 0;
			    $this->arrTimeInfo['ads'] = 0;
			    $this->arrTimeInfo['emoticon_hot'] = 0;
			}

			//$this->arrTimeInfo['ios_beta'] = 0;
			$this->arrTimeInfo['ad_emoticon'] = 0;
			//$this->arrTimeInfo['soft'] = 0;

			//Edit by fanwenli, 2015-12-31
            $this->arrTimeInfo['activity_time'] = 0;

            //Edit by fanwenli, 2016-03-08
            $this->arrTimeInfo['tab_oem'] = 0;

            //Edit by fanwenli, 2016-03-11
            $this->arrTimeInfo['icon_oem'] = 0;
            $this->arrTimeInfo['skin_bear_remind_oem'] = 0;

            //Edit by fanwenli, 2016-04-05
			$this->arrTimeInfo['down_balance_time_set'] = 0;

			//Edit by fanwenli, 2016-07-19
			$this->arrTimeInfo['core_user_stat'] = 0;

			//Edit by fanwenli, 2016-07-21
			$this->arrTimeInfo['icon_lite'] = 0;

			//Edit by fanwenli, 2016-07-27
			$this->arrTimeInfo['plugin_download_lite'] = 0;

			//Edit by fanwenli, 2016-10-11
			$this->arrTimeInfo['scene_voice_cand'] = 0;

			//Edit by fanwenli, 2016-10-14
			$this->arrTimeInfo['le_data_recover'] = 0;

			//Edit by fanwenli, 2016-10-20
			$this->arrTimeInfo['scene_map_search_voice_cand'] = 0;

			//Edit by fanwenli, 2016-10-24
			$this->arrTimeInfo['punctuation_blacklist'] = 0;

			//Edit by fanwenli, 2016-12-05
			$this->arrTimeInfo['scene_address_book_voice'] = 0;

			//Edit by fanwenli, 2016-12-12
			$this->arrTimeInfo['minimalist_voice_cand'] = 0;

			//Edit by fanwenli, 2016-12-14
			$this->arrTimeInfo['ios_scene_voice_cand'] = 0;

			//Edit by zhoubin05, 2016-12-14
			$this->arrTimeInfo['voice_pkg_white_list'] = 0;

			//Edit by zhoubin05, 2016-12-23
            $this->arrTimeInfo['tips_icon_version'] = 0;
            
            //Edit by fanwenli, 2017-04-26
            $this->arrTimeInfo['blacklist_int_guidance'] = 0;
            
            //Edit by fanwenli, 2017-05-10
            $this->arrTimeInfo['screen_eggs_strategy'] = 0;
            
            //Edit by fanwenli, 2017-08-08
            $this->arrTimeInfo['bundle_id_voice'] = 0;
            
            //Edit by fanwenli, 2017-08-15
            $this->arrTimeInfo['string_safty_replace'] = 0;
            
            //Edit by fanwenli, 2018-04-25
            $this->arrTimeInfo['ios_voice_blacklist'] = 0;
            
		}
	}

	/**
	 * 获取基站信息
	 * @param $arrUserPostInfo 用户post数据
	 *
	 * @return array
	 */
	public function getLocationInfo($arrUserPostInfo){
		$arrLocationInfo = array();
		if (isset ( $arrUserPostInfo ['location'] )) {
			$arrLocationInfo['location'] = array ();
			if (isset ( $arrUserPostInfo ['location'] ['mcc'] )) {
				$arrLocationInfo['location'] ['mcc'] = intval ( $arrUserPostInfo ['location'] ['mcc'] );
			}
			if (isset ( $arrUserPostInfo ['location'] ['mnc'] )) {
				$arrLocationInfo['location'] ['mnc'] = intval ( $arrUserPostInfo ['location'] ['mnc'] );
			}
			if (isset ( $arrUserPostInfo ['location'] ['lac'] )) {
				$arrLocationInfo['location'] ['lac'] = intval ( $arrUserPostInfo ['location'] ['lac'] );
			}
			if (isset ( $arrUserPostInfo ['location'] ['cid'] )) {
				$arrLocationInfo['location'] ['cid'] = intval ( $arrUserPostInfo ['location'] ['cid'] );
			}
		}

		return $arrLocationInfo;
	}

	/**
	 * 获取激活时间
	 * @param $arrUserPostInfo 用户post数据
	 *
	 * @return array
	 */
	public function getActiveTime($arrUserPostInfo){
		if(null === $this->intActiveTime){
			$this->intActiveTime = 0;
			if (isset($arrUserPostInfo['soft']['active_time']) && $arrUserPostInfo['soft']['active_time'] !== '') {
				//如果是毫秒, 先转化为秒
				if (strlen($arrUserPostInfo['soft']['active_time']) >= 13) {
					$this->intActiveTime = intval($arrUserPostInfo['soft']['active_time']) / 1000;
				} else {
					$this->intActiveTime = intval($arrUserPostInfo['soft']['active_time']);
				}
			}
		}

		return $this->intActiveTime;
	}

	/**
	 * 获取词库信息
	 * @param $arrUserPostInfo 用户post数据
	 *
	 * @return array
	 */
	public function getWordsInfo($arrUserPostInfo){
		if(null === $this->WordsInfo){
			$this->WordsInfo = array();
			foreach ($arrUserPostInfo['words'] as $key => $value) {
				$wordslibidstr = trim(str_replace('w', '', $key));
				//三维词库(有卸载版本号)
				if ($wordslibidstr === 'du') {
					$wduarray = array();
					if (isset($value['ver']) && $value['ver'] !== '') {
						$wduarray['ver'] = intval($value['ver']);
					} else {
						$wduarray['ver'] = 0;
					}

					if (isset($value['msgver']) && $value['msgver'] !== '') {
						$wduarray['msgver'] = intval($value['msgver']);
					} else {
						$wduarray['msgver'] = 0;
					}

					if (isset($value['unloadver']) && $value['unloadver'] !== '') {
						$wduarray['unloadver'] = intval($value['unloadver']);
					} else {
						$wduarray['unloadver'] = 0;
					}

					if (isset($value['msgtime']) && $value['msgtime'] !== '') {
						$wduarray['msgtime'] = intval($value['msgtime']);
					} else {
						$wduarray['msgtime'] = 0;
					}

					if (isset($value['msgrecord']) && $value['msgrecord'] !== '') {
						$wduarray['msgrecord'] = intval($value['msgrecord']);
					} else {
						$wduarray['msgrecord'] = 0;
					}

					//三维词库类型, 0：中文二元关系三维词库   1：中英二元关系三维词库
					if (isset($value['vtype']) && $value['vtype'] !== '') {
						$wduarray['vtype'] = intval($value['vtype']);
					} else {
						$wduarray['vtype'] = 0;
					}

					$this->WordsInfo['wdu'] = $wduarray;
				}
				//流行词, 分类词库, 系统词库
				else {
					$wordslibid = intval($wordslibidstr);
					if ($wordslibid >= self::HOT_WORDS_ID) {
						$wordsarray = array();
						$wordsarray['wid'] = $wordslibid;

						if (isset($value['ver']) && $value['ver'] !== '') {
							$wordsarray['ver'] = intval($value['ver']);
						} else {
							$wordsarray['ver'] = 0;
						}

						if (isset($value['msgver']) && $value['msgver'] !== '') {
							$wordsarray['msgver'] = intval($value['msgver']);
						} else {
							$wordsarray['msgver'] = 0;
						}

						if (isset($value['msgtime']) && $value['msgtime'] !== '') {
							$wordsarray['msgtime'] = intval($value['msgtime']);
						} else {
							$wordsarray['msgtime'] = 0;
						}

						if (isset($value['msgrecord']) && $value['msgrecord'] !== '') {
							$wordsarray['msgrecord'] = intval($value['msgrecord']);
						} else {
							$wordsarray['msgrecord'] = 0;
						}

						if ($wordslibid > self::SYS_WORDS_MIN_ID) {
							// 是否开启云优化自动更新开关
							// 0, 1分别代表未开启和开启
							// 2, 3分别代表未开启和开启
							// 由于历史版本的原因, auto_upgrade = 2或3才下发云优化消息
							if (isset($value['auto_upgrade'])) {
								$wordsarray['auto_upgrade'] = intval($value['auto_upgrade']);
							} else {
								$wordsarray['auto_upgrade'] = '';
							}
						}

						if ($wordslibid === self::HOT_WORDS_ID) {
							if (isset($value['vtype'])) {
								$wordsarray['vtype'] = intval($value['vtype']);
							} else {
								$wordsarray['vtype'] = 0;
							}
						}

						$this->WordsInfo['w'.$wordslibid] = $wordsarray;
					}
				}
			}
		}

		return $this->WordsInfo;
	}

	/**
	 * 获取更新通知消息
	 *
	 * @param $arrMessageAll 通知信息
	 * @param $strLastMessageTime int 启动屏幕消息版本
	 *
	 * @return array
	 *
	 * 有消息, 返回对应的消息
	 * 无消息, 返回空数组
	 */
	public function getChangeNotiInfo($arrMessageAll, $strLastMessageTime){
		$arrMessage = array();
		$arrMessage['lastmodify'] = (0 == intval($strLastMessageTime))? Util::getCurrentTime() : $strLastMessageTime;
		$arrMessage['info'] = array();

		$intLastModify = intval($strLastMessageTime);
		foreach($arrMessageAll as $arrOneMsg){
			if($arrOneMsg['update_time'] > intval($strLastMessageTime)){
				//获取最后修改时间排序规则不同最后修改时间不同
				if($arrOneMsg['update_time'] > $intLastModify){
					$intLastModify = $arrOneMsg['update_time'];
				}

				$arrMessage['info'][] = $arrOneMsg['info'];
			}
		}

		$arrMessage['lastmodify'] = strval($intLastModify);

		return $arrMessage;
	}

	/**
	 * 获取通知消息
	 * @param $strMessageKey 消息key
	 * @param $strLastMessageTime int 启动屏幕消息版本
	 * @param $strSearch 资源服务查询条件，如果没有则使用默认查询条件
	 * @param $intMsgVer 消息版本
	 *
	 * @return array
	 *
	 * 有消息, 返回对应的消息
	 * 无消息, 返回空数组
	 */
	public function getNotiInfoFromRes($strMessageKey, $strLastMessageTime, $strSearch = null, $intMsgVer = 0, $sort = '') {
		//default
		$arrMessage = array();
		$arrMessage['lastmodify'] = (0 == intval($strLastMessageTime))? Util::getCurrentTime() : $strLastMessageTime;
		$arrMessage['info'] = array();

		//先从cache里获取
		$strCacheKey = $this->strAdNotiCachePre . $strMessageKey . '_' . $intMsgVer;
		if(null === $strSearch){
			//$strSearch = sprintf('{"update_time":{"$gt":%d}, "content.class":"%s", "content.%s.start_time":{"$lte":%d}, "content.%s.expired_time":{"$gt":%d}}', $strLastMessageTime, $strMessageKey, $strMessageKey, time(), $strMessageKey, time());
			$strSearch = sprintf('{"content.class":"%s", "content.%s.expired_time":{"$gt":${now}}, "content.%s.noti_id":{"$gt":%d}}', $strMessageKey, $strMessageKey,$strMessageKey, $intMsgVer);
		}

		if(null !== $strSearch){
			$strCacheKey = $strCacheKey . '_' . md5($strSearch);
		}

		if('' !== $sort){
			$strCacheKey = $strCacheKey . '_' . md5($sort);
			$sort = '&sort=' . $sort;
		}

		$bolStatus = false;
		$arrCache = $this->cache->get($strCacheKey, $bolStatus);
		
		if (null !== $arrCache){
			$arrMessage = $this->getChangeNotiInfo($arrCache, $strLastMessageTime);
			$this->filterNoti($arrMessage);
			return $arrMessage;
		}

		$strSearch = str_replace('${now}', Util::getCurrentTimeMinute(), $strSearch);

	    $strMetaCacheKey = $this->strAdNotiCachePre . 'meta';
		$arrInfoMeta = $this->cache->get($strMetaCacheKey, $bolStatus);

		if (null === $arrInfoMeta){
    		$arrHeader = array(
    			'pathinfo' => $this->strNotiResRoot,
    			'querystring'=> "",
    		);
    		$strResult = ral("res_service", "get", null, rand(), $arrHeader);

    		if(false === $strResult){
    			Logger::warning('getNotiInfo list from res failed');
    			//ral交互失败，空数组写入缓存30s
    			$this->cache->set($strMetaCacheKey, array(), 30);
    			return $arrMessage;
    		}
    		//$arrInfoMeta = json_decode($strResult, true);
    		$arrInfoMeta = is_array($strResult)? $strResult : json_decode($strResult, true);

    		$this->cache->set($strMetaCacheKey, $arrInfoMeta, $this->intCacheExpired);
		}

		//获取content信息
		$arrHeader = array(
			'pathinfo' => $this->strNotiResRoot,
			'querystring'=> 'search=' . urlencode($strSearch) . '&onlycontent=1&searchbyori=1' . $sort,
		);
		$strResult = ral("res_service", "get", null, rand(), $arrHeader);

		if(false === $strResult){
			Logger::warning('getNotiInfo content from res failed');
			//ral交互失败，空数组写入缓存30s
			$this->cache->set($strCacheKey, array(), 30);
			return $arrMessage;
		}

		//$arrInfoOnlyContent = json_decode($strResult, true);
		$arrInfoOnlyContent = is_array($strResult)? $strResult : json_decode($strResult, true);

		//keyword update_time映射关系
		$arrUpdateTime = array();
		foreach($arrInfoMeta as $arrOneInfo){
			$arrUpdateTime[$arrOneInfo['keyword']] = $arrOneInfo['update_time'];
		}

		//update_time info 映射关系
		$arrMessageAll = array();
		foreach ($arrInfoOnlyContent as $key => $val){
			if(isset($arrUpdateTime[$key])){
				$arrOneMsg = array();
				$arrOneMsg['update_time'] = $arrUpdateTime[$key];
				$arrOneMsg['info'] = $val[$strMessageKey];

				$arrMessageAll[] = $arrOneMsg;
			}
		}

		//set cache
		$this->cache->set($strCacheKey, $arrMessageAll, $this->intCacheExpired);

		$arrMessage = $this->getChangeNotiInfo($arrMessageAll, $strLastMessageTime);

		$this->filterNoti($arrMessage);

		return $arrMessage;
	}

	/**
	 * @param $arrMessage 通知中心数组
	 * 通知中心条件过滤
	 */
	private  function  filterNoti(&$arrMessage)
	{
		if(is_array($arrMessage)) {
			$conditionFilter = IoCload("utils\\ConditionFilter");
			$arrInfo = array();
			foreach ($arrMessage['info'] as $k => $val) {
				if (isset($val['filter_conditions'])) {
					//条件过滤
					if ($conditionFilter->filter($val['filter_conditions'])) {
						unset($val['filter_conditions']);
						$arrInfo[] = $val;
					}
				} else {
					$arrInfo[] = $val;
				}
			}
			$arrMessage['info'] = $arrInfo;
		}
	}

	/**
	 *
	 * 外部设置缓存
	 * 目前个性化词库推荐用到,预热脚本用到
	 *
	 * @route({"GET","/cache"})
	 *
	 * @param({"strKey","$._GET.key"}) $strKey cache key
	 * @param({"strValue","$._GET.value"}) $strValue cache value
	 * @param({"intTtl","$._GET.ttl"}) $intTtl 过期时间默认不过期
	 *
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 *
	 * @return({"body"}) void
	 */
	public function setCache($strKey, $strValue, $intTtl = 0){
		$intTtl = intval($intTtl);
		$arrValue = json_decode($strValue, true);
		//如果为json则存储为数组
		if(null !== $arrValue){
			$this->cache->set($strKey, $arrValue, $intTtl);
		}else{
			$this->cache->set($strKey, $strValue, $intTtl);
		}
	}

	/**
	 *
	 * 统计用户上传基站信息情况
	 *
	 * @param $arrUserPostInfo
	 *
	 */
	public function reportLocation($arrUserPostInfo){
	    if (isset ( $arrUserPostInfo['location'] )) {
	        //all machine list 17 台 20160620
	        $arrV4InnerList = array('dbl-mic-input00.dbl01','dbl-mic-input01.dbl01','dbl-mic-input02.dbl01','hz01-mic-input03.hz01','hz01-mic-input04.hz01','hz01-mic-input05.hz01','cq02-mic-input00.cq02','cq02-mic-input01.cq02','cq02-mic-input02.cq02','nj03-mic-input00.nj03','nj03-mic-input01.nj03','nj03-mic-input02.nj03','nj03-mic-input03.nj03','szwg-mic-input00.szwg01','szwg-mic-input01.szwg01','szwg-mic-input02.szwg01','szwg-mic-input03.szwg01',);

	        $arrRport = $arrUserPostInfo['location'];
	        $strLocation = urlencode(json_encode($arrRport));

	        $strUrl = 'http://' . $arrV4InnerList[array_rand($arrV4InnerList, 1)] . '.baidu.com:8080/v4/?c=report&e=anal' . "&rptlc=$strLocation" . '&inputgd=1' . str_replace('/v5/noti/info', '', $_SERVER['QUERY_STRING']);
	        //$strUrl = 'http://r6.mo.baidu.com/v4/?c=report&e=anal' . "&rptlc=$strLocation" . '&inputgd=1' . str_replace('/v5/noti/info', '', $_SERVER['QUERY_STRING']);
	        $arrResult = $this->getHttpRequestResponse($strUrl, 'GET');
	        if (isset( $arrResult['http_code'] ) && intval( $arrResult['http_code'] ) === 200) {

	        }

	    }
	}
	
	//皮肤主题通知
	/**
	 * @param
	 * @return
	 */
	public function getSkinNoti($arrUserPostInfo,$intCurrentTime, &$arrNoti, $strPlatform, $strVersion, $strScreenW, $strScreenH, $bolForeignAccess){
	    if ( isset($arrUserPostInfo['ad_skin']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['ad_skin'] >= $this->intCallInterval) ) {
	        $arrSkinNoti = NotiSkin::getNoti($this, $this->cache, $this->strSkinCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo['ad_skin'], $arrUserPostInfo['ad_skin']['msgver'], $strPlatform, $strVersion, $strScreenW, $strScreenH, $bolForeignAccess);
	        $arrNoti['ad_skin'] = isset($arrSkinNoti['info'])? $arrSkinNoti['info'] : array();
	        $arrNoti['message_time']['ad_skin'] = $intCurrentTime;
	    }else{
	        $arrNoti['message_time']['ad_skin'] = $this->arrTimeInfo['ad_skin'];
	    }
	}
	
	//表情通知
	/**
	 * @param
	 * @return
	 */
	public function getEmojiNoti($arrUserPostInfo,$intCurrentTime, &$arrNoti, $strPlatform, $strVersion){
    	if ( isset($arrUserPostInfo['ad_emoji']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['ad_emoji'] >= $this->intCallInterval) ) {
    	    $arrEmojiNoti = NotiEmoji::getNoti($this, $this->cache, $this->strEmojiCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo['ad_emoji'], $arrUserPostInfo['ad_emoji']['msgver'], $this->getPhoneOS($strPlatform), $strVersion);
    	    $arrNoti['ad_emoji'] = isset($arrEmojiNoti['info'])? $arrEmojiNoti['info'] : array();
    	    $arrNoti['message_time']['ad_emoji'] = $intCurrentTime;
    	}else{
    	    $arrNoti['message_time']['ad_emoji'] = $this->arrTimeInfo['ad_emoji'];
    	}
	}
	
	/**
	 *
	 * 通知中心信息获取
	 *
	 * @route({"POST","/info"})
	 *
	 * @param({"strData","$._POST.noti"})
	 * 客户端上传信息
	 *
	 * @param({"strRom", "$._GET.rom"}) $strRom rom,不需要客户端传
	 * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
	 * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
	 * @param({"strCfrom", "$._GET.cfrom"}) $strCfrom cfrom当前渠道号,不需要客户端传
	 * @param({"strUid", "$._GET.uid"}) $strUid uid,不需要客户端传
	 * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
	 * @param({"intSp", "$._GET.sp"}) $intSp 联网类型
	 * @param({"intEnv", "$._GET.env"}) $intEnv 加密版本,不需要客户端传,当前最新版本值为2
	 * @param({"bolForeignAccess", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
	 * @param({"strScreenW", "$._GET.screen_w"}) $strScreenW 屏幕宽,不需要客户端传
	 * @param({"strScreenH", "$._GET.screen_h"}) $strScreenH 屏幕高,不需要客户端传
	 * @param({"strModel", "$._GET.model"}) $strModel 手机型号,不需要客户端传
	 * @param({"strSdk", "$._GET.sdk"}) $strSdk 客户端系统SDK版本,不需要客户端传
	 * @param({"strImei", "$._GET.imei"}) $strImei android平台正序imei,不需要客户端传
	 * @param({"strFrom", "$._GET.from"}) $strFrom 初始渠道号,不需要客户端传
	 * @param({"intDebugLog", "$._GET.debuglog"}) $intDebugLog 是否打印调试日志0 不打印 1 打印
	 *
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 *
	 * @return({"body"})
	 */
	public function getNoti($strData, $strRom, $strPlatform, $strVersion, $strCfrom, $strUid = null, $strCuid = null, $intSp = 12,
	    $strScreenW = 640, $strScreenH = 320, $intEnv = null, $bolForeignAccess = false, $strModel = '', $strSdk = '',
	    $strImei = '', $strFrom = '', $intDebugLog = 0){

		$intSp = intval($intSp);
	    $bolDebugLog = (1 === intval($intDebugLog))? true : false;

		$arrNoti = array();
		//current time
		$intCurrentTime = Util::getCurrentTime();
		$arrNoti['status'] = 0;
		$arrNoti['msg'] = '';
		$arrNoti['server_time'] = $intCurrentTime;

		if(null === $strData){
			$arrNoti['status'] = -1;
			$arrNoti['msg'] = self::$arrStatusMsg[$arrNoti['status']];
		}

		//decode上传参数
		$arrUserPostInfo = json_decode($strData, true);
		if (null === $arrUserPostInfo) {
			$arrNoti['status'] = -2;
			$arrNoti['msg'] = self::$arrStatusMsg[$arrNoti['status']];
		}


		//统计上传基站信息情况
		//$this->reportLocation($arrUserPostInfo);

		//status非0直接返回
		if(0 !== $arrNoti['status']){
			return $arrNoti;
		}
		//message_time init
		$arrNoti['message_time'] = array();

		//解密post数据
		$this->decodeUserNotiInfo($intEnv, $arrUserPostInfo);

		//获取手机信息
		$this->getPhoneInfo($arrUserPostInfo, $strRom, $strPlatform, $strModel, $strScreenW, $strScreenH, $strSdk);

		//获取版本值
		$this->intVersion = self::getVersionIntValue($strVersion);

		//获取上传获得通知时间
		$this->getTimeInfo($arrUserPostInfo, $strVersion);

		//获取版本号
		if (isset($arrUserPostInfo['soft']['ver']) && $arrUserPostInfo['soft']['ver'] !== '') {
			$strVersionName = str_replace('-', '.', $arrUserPostInfo['soft']['ver']);
		} else {
			$strVersionName = str_replace('-', '.', $strVersion);
		}

		//获取平台号
		if (isset($arrUserPostInfo['platform']) && $arrUserPostInfo['platform'] !== '') {
			$strPlatform = $arrUserPostInfo['platform'];
		}

		//获取渠道号
		if (isset($arrUserPostInfo['channel']) && $arrUserPostInfo['channel'] !== '') {
			$strCfrom = $arrUserPostInfo['channel'];
		}

		//network
		$strNetWork = (intval($intSp) === self::WIFI_FLAG_OF_SP)?'wifi':'gprs';

		//===============android轻量及sdk、ios调用苹果接口将位置信息上传服务端并转换 7.2以上===============
		$strApinfo = null;
		//安卓客户端如果没有获取地理位置的权限会导致不上传['position']['apinfo']字段
        //这会造成没有城市信息下发，和城市信息相关的接口会拿不到相关数据
        //安卓客户端从6.5版本会使用这个字段
        //edit by zhoubin05 20190509
        //edit by zhoubin05 20200506 某些系统会记录app获取权限的记录，用户会看到。为了避免这种情况，position
        //此时要用ip获取地理信息， 判断isset的目的是为了尽可能减少 getPosition方法的调用
        if ( ( isset($arrUserPostInfo['position']) || ('android' === $this->getPhoneOS($strPlatform) && Util::boolVersionPlatform(6050000)) )
            && $intCurrentTime - $this->arrTimeInfo['position'] > $this->intCallInterval){
            $strApinfo = $arrUserPostInfo['position']['apinfo'];
		    $arrPosition = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo);
		    $arrNoti['position'] = bd_B64_Encode(json_encode($arrPosition), 0);
		    $arrNoti['message_time']['position'] = $intCurrentTime;
		} else {
		    $arrNoti['position'] = array();
		    $arrNoti['message_time']['position'] = $this->arrTimeInfo['position'];
		}

		//统一开关配置
		$arrUnifyConf = UnifyConf::getUnifyConf($this->cache, $this->strUnifyConfCachePre, $this->intCacheExpired, $this->strUnifyConfResRoot, self::getClientIP(), $strFrom);
 
		//通知懒人语料
		//if( $this->intVersion >= 7030000 && isset($arrUnifyConf['ios_voice_switch'])){
		if( Util::boolVersionPlatform(7030000) && isset($arrUnifyConf['ios_voice_switch'])){
			$arrNoti['ios_voice_switch'] = $arrUnifyConf['ios_voice_switch'];
		}

		//IOS安装完重启提示
		//针对IOS 8.3/8.4的系统bug, 输入法版本>5.6.5, 需提升用户重新系统
		if($this->arrPhoneInfo['phone_os'] == 'ios' && version_compare($this->arrPhoneInfo ['os_version'],'8.3')>=0){
			$arrNoti['ios_reboot_alert'] = array(
				'enable' => $arrUnifyConf['ios_reboot_alert'],
			);
			$arrNoti['message_time']['ios_reboot_alert'] = $intCurrentTime;
		}

		//忽略小米数据上传白名单
		if(1 == $arrUnifyConf['ignore_mi_wl']){
			$arrNoti['ignore_mi_wl'] = 1;
		}

		//小米商店设置项精品/热词入口服务端开关，三个值分别代表精品、热词、发现tab的是否展示。
		if(!empty($arrUnifyConf['mi_tab_show'])){
			$arrNoti['mi_tab_show'] = is_array($arrUnifyConf['mi_tab_show'])? $arrUnifyConf['mi_tab_show']:json_decode($arrUnifyConf['mi_tab_show'], true);
		}
		else{
			$arrNoti['mi_tab_show'] = UnifyConf::$arrDefaultUnifyConf['mi_tab_show'];
		}

		//================UC导流================
		//UC浏览器是否显示窗口及窗口大小
		//3种情况：没有 0，大的 1，小的 2
		$arrNoti['uc_redi'] = $arrUnifyConf['uc_redi'];

		//流行词是否在熊头提醒的开关
		$arrNoti['hwiconremind'] = $arrUnifyConf['hwiconremind'];

		//先判断OEM版本是否走LC升级平台进行升级,注意通知里没有这个通知版本更新里用到
		$arrNoti['oem_lc_switch'] = $arrUnifyConf['oem_lc_switch'];

		//客户端用户搜索浮层开关,0搜索浮层点击x才会收起 1搜索浮层点击x和浮层外部区域会收回 2搜索浮层关闭
		//没有默认取值且增加统一过滤后有可能不下发因此需要判断
		if(isset($arrUnifyConf['search_float'])){
			$arrNoti['search_float'] = $arrUnifyConf['search_float'];
		}

		//moplus开关
		$arrNoti['moplus'] = $arrUnifyConf['moplus'];

		//sug配置相关
		$arrNoti['sug'] = array(
		    'sug_switch' => $arrUnifyConf['sug_switch'], //客户端是否发起sug请求的云开关 true:开启 false:关闭（默认)
		    'app' => $arrUnifyConf['app'], //客户端sug应用词源连接的服务器 0:百度 1：小米(默认)
		    'search' => $arrUnifyConf['search'], //客户端sug搜索词源连接的服务器 0:百度 1：小米(默认)
		    'sug_min_net_level' => $arrUnifyConf['sug_min_net_level'], //发起sug请求的最低网络要求 0：所有网络  1：2G网络 2：3G网络 3：4G网络 5：WIFI
		    'sug_version' => NotiComm::checkSugWhiteUpdate($this->strSugWhiteCachePre, $this->intCacheExpired),
		    'sug_statistic_mi' => $arrUnifyConf['sug_statistic_mi'], //小米sug统计开关 true:开启 false：关闭(默认)
		    'sug_mi_scene_rate' => intval($arrUnifyConf['sug_mi_scene_rate']), //小米应用场景统计比例 0~100 整数  默认0
		    'sug_mi_browser_rate' => intval($arrUnifyConf['sug_mi_browser_rate']), //小米浏览器统计比例 0~100 整数  默认0
		    'sug_icon_list_version' => NotiComm::getSugIconListVersion(),  //sug band icon白名单版本号
        );

		if(isset($arrUnifyConf['sug_mi_card_remind_period'])){
		    $arrNoti['sug']['sug_mi_card_remind_period'] = intval($arrUnifyConf['sug_mi_card_remind_period']);//小米sug卡片提醒框展示周期(单位天)，int型
		}

		if(isset($arrUnifyConf['sug_mi_card_remind_max_count'])){
		    $arrNoti['sug']['sug_mi_card_remind_max_count'] = intval($arrUnifyConf['sug_mi_card_remind_max_count']);//小米sug卡片提醒框最大展示次数
		}

		if(isset($arrUnifyConf['sug_mi_card_show_switch'])){
		    $arrNoti['sug']['sug_mi_card_show_switch'] = intval($arrUnifyConf['sug_mi_card_show_switch']);//小米sug卡片展示开关 1 为开，0为关
		}

        //小米cand条
		$arrNoti['cand_search_type'] = 'true' == $arrUnifyConf['cand_search_type'] ? 1 : 0;

		//小米cand icon 资源 相关
		//有数据情况:{"mi_cand": {"mi_cand_icon_switch": 0}}
        //没有数据情况:{"mi_cand": []}
		$arrNoti['mi_cand'] = array();
		//小米cand条运营icon功能使用小米端还是百度的数据(1百度，0小米)，int型,客户端默认为0，服务端有可能不下发这个字段（请求数据库失败或PM配置不符合条件）客户端以之前的值为准。
		if(isset($arrUnifyConf['mi_cand_icon_switch'])){
		    $arrNoti['mi_cand']['mi_cand_icon_switch'] = $arrUnifyConf['mi_cand_icon_switch'];
		}

		//小米引导切换主线通知开关
		$miContentAry =  isset($arrUnifyConf['mi_msg_content']) ? explode('|', $arrUnifyConf['mi_msg_content']) :  array('','');

        //小米引导切换主线通知
        $arrNoti['mi_noti'] = array(
            'mi_msg_interval_time' => intval($arrUnifyConf['mi_msg_interval_time']), //小米引导切换主线通知展示间隔(小时) 默认72小时
            'title' => isset($miContentAry[0]) ? $miContentAry[0] : '',
            'content' => isset($miContentAry[1]) ? $miContentAry[1] : '',
        );
        //ios testflight 开关
        $arrNoti['ios_testflight_switch'] = isset($arrUnifyConf['ios_testflight_switch']) ? $arrUnifyConf['ios_testflight_switch'] : 0;
        
        //AR表情图片收集开关 0: 关闭 1: 打开
        $arrNoti['ar_img_col_switch'] = isset($arrUnifyConf['ar_img_col_switch']) ? $arrUnifyConf['ar_img_col_switch'] : 0;
        $arrNoti['ar_img_col_switch'] = intval($arrNoti['ar_img_col_switch']);
        
        /*
		//皮肤主题通知
		if ( isset($arrUserPostInfo['ad_skin']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['ad_skin'] >= $this->intCallInterval) ) {
			$arrSkinNoti = NotiSkin::getNoti($this, $this->cache, $this->strSkinCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo['ad_skin'], $arrUserPostInfo['ad_skin']['msgver'], $strPlatform, $strVersion, $strScreenW, $strScreenH, $bolForeignAccess);
			$arrNoti['ad_skin'] = isset($arrSkinNoti['info'])? $arrSkinNoti['info'] : array();
			$arrNoti['message_time']['ad_skin'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['ad_skin'] = $this->arrTimeInfo['ad_skin'];
		}
		*/

		/*
		//表情通知
		if ( isset($arrUserPostInfo['ad_emoji']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['ad_emoji'] >= $this->intCallInterval) ) {
			$arrEmojiNoti = NotiEmoji::getNoti($this, $this->cache, $this->strEmojiCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo['ad_emoji'], $arrUserPostInfo['ad_emoji']['msgver'], $this->getPhoneOS($strPlatform), $strVersion);
			$arrNoti['ad_emoji'] = isset($arrEmojiNoti['info'])? $arrEmojiNoti['info'] : array();
			$arrNoti['message_time']['ad_emoji'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['ad_emoji'] = $this->arrTimeInfo['ad_emoji'];
		}
		*/
        
        /* $this->getSkinNoti($arrUserPostInfo, $intCurrentTime, $arrNoti, $strPlatform, $strVersion, $strScreenW, $strScreenH, $bolForeignAccess);
        $this->getEmojiNoti($arrUserPostInfo, $intCurrentTime, $arrNoti, $strPlatform, $strVersion); */
        
        $this->phasterThreadPool[] = new \PhasterThread(array($this, "getSkinNoti"), array($arrUserPostInfo, $intCurrentTime, &$arrNoti, $strPlatform, $strVersion, $strScreenW, $strScreenH, $bolForeignAccess));
        $this->phasterThreadPool[] = new \PhasterThread(array($this, "getEmojiNoti"), array($arrUserPostInfo, $intCurrentTime, &$arrNoti, $strPlatform, $strVersion));        
        $this->phasterThreadsJoin();
        
		//教程消息
		if ( ($intCurrentTime - $this->arrTimeInfo['lessons'] >= $this->intCallInterval) ) {
			$intLessonId = isset($arrUserPostInfo['lessonid'])? intval($arrUserPostInfo['lessonid']):0;
			$arrLessonNoti = NotiLesson::getNoti($this, $this->cache, $this->strLessonCachePre, $this->intCacheExpired, $strPlatform, $strVersionName, $this->arrTimeInfo['lessons'], $intLessonId);
			$arrNoti['lessons'] = isset($arrLessonNoti['info'])? $arrLessonNoti['info'] : array();
			$arrNoti['message_time']['lessons'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['lessons'] = $this->arrTimeInfo['lessons'];
		}

		//APP推荐消息
		if ( ($intCurrentTime - $this->arrTimeInfo['recommends'] >= $this->intCallInterval) ) {
			$intActiveTime = $this->getActiveTime($arrUserPostInfo);
			$intRecomappId = isset($arrUserPostInfo['recomappid'])? intval($arrUserPostInfo['recomappid']):0;
			$arrAppRmdNoti = NotiAppRecommend::getNoti($this, $this->cache, $this->strAppRecommendCachePre, $this->intCacheExpired, $strPlatform, $strVersionName, $strCfrom, $strNetWork, $strScreenW, $strScreenH, $this->arrTimeInfo['recommends'], $intRecomappId, $intActiveTime);
			$arrNoti['recommends'] = isset($arrAppRmdNoti['info'])? $arrAppRmdNoti['info'] : array();
			$arrNoti['message_time']['recommends'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['recommends'] = $this->arrTimeInfo['recommends'];
		}

		//活动消息
		if ( ($intCurrentTime - $this->arrTimeInfo['events'] >= $this->intCallInterval) ) {
			$intEventId = isset($arrUserPostInfo['eventid'])? intval($arrUserPostInfo['eventid']):0;
			$arrEventsNoti = NotiEvents::getNoti($this, $this->cache, $this->strEventsCachePre, $this->intCacheExpired, $strPlatform, $strVersionName, $this->arrTimeInfo['events'], $intEventId);
			$arrNoti['events'] = isset($arrEventsNoti['info'])? $arrEventsNoti['info'] : array();
			$arrNoti['message_time']['events'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['events'] = $this->arrTimeInfo['events'];
		}

		//客户端上传的各词库版本及通知版本数据
		$arrWordsInfo = $this->getWordsInfo($arrUserPostInfo);

		//分类词库及地理词库推荐消息
		if ( ($intCurrentTime - $this->arrTimeInfo['cells'] >= $this->intCallInterval) ) {
			$intRecomwid = isset($arrUserPostInfo['recomwid'])? intval($arrUserPostInfo['recomwid']):0;
			$arrCellsNoti = NotiCells::getNoti($this, $this->cache, $this->strCellsCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $strPlatform, $strVersionName, $this->arrTimeInfo['cells'], $intRecomwid);
			$arrNoti['cells'] = !empty($arrCellsNoti['info'])? $arrCellsNoti['info'] : array();

			//本地化词库推荐
			//只有上传基站信息的才推送本地化词库
			$arrLocationInfo = $this->getLocationInfo($arrUserPostInfo);

			//edit by zhoubin05 20200506 某些系统会记录app获取权限的记录，用户会看到。为了避免这种情况，客户端不会上传location的内容
            //此时要用ip获取地理信息， 判断isset的目的是为了尽可能减少 getPosition方法的调用
            if (isset ( $arrLocationInfo['location'] ) && ! empty ( $arrLocationInfo['location'] ) && $this->intVersion < 7040000) {  //7.4以上就不要这数据了，有自动静默推送词库在cell_loc字段中
                $arrCityInfo = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo);
				$arrLocalWords = NotiCells::fetchLocalCellWordsMessage($this, $this->cache, $this->strCellsCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $arrCityInfo, $arrWordsInfo);
				if (!empty($arrLocalWords)) {
					$arrNoti['cells'][] = $arrLocalWords;
				}
			}

			//获取个性化词库推荐消息(deprecated on 20161212 by lipengcheng02)
			/* 
			if( isset($strCuid) && !empty($strCuid) ){

				$arrPwords = NotiCells::fetchPersonalCellWordsMessage($this, $this->cache, $this->strCellsCachePre, $this->intCacheExpired, $strCuid, $arrWordsInfo);
				if (isset($arrPwords['dlink'])) {
					$arrPwords['dlink'] = $this->strV4HttpRoot.'?c=ud&cuid='.urlencode($strCuid).'&version='.$arrPwords['version'].'&url='.urlencode($arrPwords['dlink']);
					$arrNoti['cells'][] = $arrPwords;
				}
			}
            */
			$arrNoti['message_time']['cells'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['cells'] = $this->arrTimeInfo['cells'];
		}

		//剪贴板功能黑名单
		if ( isset($arrUserPostInfo['clipboard_blacklist']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['clipboard_blacklist'] >= $this->intCallInterval) ) {
			$arrClBlNoti = NotiClipboardBlackList::getNoti($this, $this->cache, $this->strClipboardBlacklistCachePre, $this->intCacheExpired, $this->strClipboardBlacklistRoot, $arrUserPostInfo['clipboard_blacklist']['msgver']);
			$arrNoti['clipboard_blacklist'] = $arrClBlNoti;
			$arrNoti['message_time']['clipboard_blacklist'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['clipboard_blacklist'] = $this->arrTimeInfo['clipboard_blacklist'];
		}

		//广告热词消息
		if ( isset($arrUserPostInfo['ad_hot_word']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['ad_hot_word'] >= $this->intCallInterval) ) {
			$arrNotiInfo = $this->getNotiInfoFromRes('ad_hot_word', $this->arrTimeInfo['ad_hot_word'], null, $arrUserPostInfo['ad_hot_word']['msgver']);
			$arrNoti['ad_hot_word'] = $arrNotiInfo['info'];
			$arrNoti['message_time']['ad_hot_word'] = $intCurrentTime;
		}else{
			$arrNoti['message_time']['ad_hot_word'] = $this->arrTimeInfo['ad_hot_word'];
		}

		//插件消息
		if ( isset($arrUserPostInfo['ad_plugin']['memory'])  && ($intCurrentTime - $this->arrTimeInfo['ad_plugin'] >= $this->intCallInterval) ) {
		    $arrPluginNoti = NotiPlugin::getNoti($this, $this->cache, $this->strPluginCachePre, $this->intCacheExpired, $this->getPhoneOS($strPlatform), $strVersionName, $this->arrTimeInfo['ad_plugin'], $arrUserPostInfo['ad_plugin']['msgver'], $arrUserPostInfo['ad_plugin']['memory'], $arrUserPostInfo['ad_plugin']['cpu']);
		    $arrNoti['ad_plugin'] = isset($arrPluginNoti['info'])? $arrPluginNoti['info'] : array();
		    $arrNoti['message_time']['ad_plugin'] = $intCurrentTime;
		}else{
		    $arrNoti['message_time']['ad_plugin'] = $this->arrTimeInfo['ad_plugin'];
		}

		//三维词库、流行词、分类词库、系统词库

		foreach ($arrWordsInfo as $key => $value){
		    if ($key === 'wdu') {
		        if ($intCurrentTime - $this->arrTimeInfo[$key] >= $this->intCallInterval) {
		            $arrWduNoti = NotiWdu::getNoti($this, $this->cache, $this->strWduCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo[$key], $value, $this->getPhoneOS($strPlatform), $strNetWork);
		            $arrNoti['words'][$key] = isset($arrWduNoti['info'])? $arrWduNoti['info'] : array();
		            $arrNoti['message_time'][$key] = $intCurrentTime;
		        } else {
		            $arrNoti['words'][$key] = array();
		            $arrNoti['message_time'][$key] = $this->arrTimeInfo[$key];
		        }
		    }else{
		        $intWordslibId = str_replace('w', '', $key);
		        $intWordslibId = intval($intWordslibId);
		        //================流行词消息================
		        if ($intWordslibId === self::HOT_WORDS_ID) {
		            if ($intCurrentTime - $this->arrTimeInfo[$key] >= $this->intCallInterval) {
		                $arrHotWordsNoti = NotiHotwords::getNoti($this, $this->cache, $this->strHotwordsCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo[$key], $intWordslibId, $value, $this->getPhoneOS($strPlatform), $strNetWork, intval($arrUnifyConf['hotword_showlevel']));
		                $arrNoti['words'][$key] = $arrHotWordsNoti;
		                $arrNoti['message_time'][$key] = $intCurrentTime;
		            } else {
		                $arrNoti['words'][$key] = array();
		                $arrNoti['message_time'][$key] = $this->arrTimeInfo[$key];
		            }
		        }
		        //================分类词库消息================
		        elseif ($intWordslibId > self::HOT_WORDS_ID && $intWordslibId < self::SYS_WORDS_MIN_ID) {
		            if ($intCurrentTime - $this->arrTimeInfo['wcell'] >= $this->intCallInterval) {
		                $arrAdCellNoti = NotiAdCell::getNoti($this, $this->cache, $this->strAdCellCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->arrTimeInfo[$key], $intWordslibId, $value, $this->getPhoneOS($strPlatform), $strNetWork);
		                $arrNoti['words'][$key] = isset($arrAdCellNoti['info'])? $arrAdCellNoti['info'] : array();
		                $arrNoti['message_time']['wcell'] = $intCurrentTime;
		            } else {
		                $arrNoti['words'][$key] = array();
		                $arrNoti['message_time']['wcell'] = $this->arrTimeInfo['wcell'];
		            }
		        }
		        //================系统词库消息================
		        elseif ($intWordslibId > self::SYS_WORDS_MIN_ID) {
		            if ($intCurrentTime - $this->arrTimeInfo['wsys'] >= $this->intCallInterval) {
		                $arrWsysNoti = NotiWsys::getNoti($this, $this->cache, $this->strWsysCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $this->strMicHttpRoot, $this->arrTimeInfo[$key], $intWordslibId, $value, $this->getPhoneOS($strPlatform), $strNetWork);
		                $arrNoti['words'][$key] = $arrWsysNoti;
		                $arrNoti['message_time']['wsys'] = $intCurrentTime;
		            } else {
		                $arrNoti['words'][$key] = array();
		                $arrNoti['message_time']['wsys'] = $this->arrTimeInfo['wsys'];
		            }
		        }
		    }

		}

		//================插件手写模板更新消息================
		if ($intCurrentTime - $this->arrTimeInfo['handwrite'] >= $this->intCallInterval) {
		    $intHandwriteVersion = isset($arrUserPostInfo['handwrite_version'])? intval($arrUserPostInfo['handwrite_version']):-1;
		    $arrMsg = NotiHandwrite::getNoti($this, $this->cache, $this->strHandwriteCachePre, $this->intCacheExpired, $this->getPhoneOS($strPlatform), $strVersion, $intHandwriteVersion);
		    $arrNoti['handwrite'] = $arrMsg;
		    $arrNoti['message_time']['handwrite'] = $intCurrentTime;
		} else {
		    $arrMessage = array();
		    $arrMessage['have_update'] = '0';
		    $arrNoti['handwrite'] =$arrMessage;
		    $arrNoti['message_time']['handwrite'] = $this->arrTimeInfo['handwrite'];
		}

		//================直达号更新消息================
		if ($intCurrentTime - $this->arrTimeInfo['zhida'] >= $this->intCallInterval) {
		    $intZhidaVersion = isset($arrUserPostInfo['zhidaver'])? intval($arrUserPostInfo['zhidaver']):-1;
		    $arrZhidaInfo = NotiZhida::getNoti($this, $this->cache, $this->strZhidahaoCachePre, $this->intCacheExpired, $intZhidaVersion);
		    if ( !empty($arrZhidaInfo) ){
		        $arrNoti['zhidahao'] = $arrZhidaInfo;
		    }

		    $arrNoti['message_time']['zhida'] = $intCurrentTime;
		}else{
		    $arrNoti['message_time']['zhida'] = $this->arrTimeInfo['zhida'];
		}

		//================启动屏更新消息================
		if ( (isset($arrUserPostInfo['splash']['msgver'])) && ($intCurrentTime - $this->arrTimeInfo['splash'] >= $this->intCallInterval) ) {
		    $arrSplashInfo = NotiSplash::getNoti($this, $this->cache, $this->strSplashCachePre, $this->intCacheExpired, intval($arrUserPostInfo['splash']['msgver']));

		    $arrNoti['splash'] = $arrSplashInfo;
		    $arrNoti['message_time']['splash'] = $intCurrentTime;
		}else{
		    $arrNoti['message_time']['splash'] = $this->arrTimeInfo['splash'];
		}

		//================场景化更新消息================
		if ( (isset($arrUserPostInfo['scene']['msgver'])) && ($intCurrentTime - $this->arrTimeInfo['scene'] >= $this->intCallInterval) ) {
		    $arrSceneInfo = NotiScene::getNoti($this, $this->cache, $this->strSceneCachePre, $this->intCacheExpired, intval($arrUserPostInfo['splash']['msgver']));

		    $arrNoti['scene'] = $arrSceneInfo;
		    $arrNoti['message_time']['scene'] = $intCurrentTime;
		}else{
		    $arrNoti['message_time']['scene'] = $this->arrTimeInfo['scene'];
		}

		//================统计控制客户端上传消息================
		if ($intCurrentTime - $this->arrTimeInfo['report'] >= $this->intCallInterval) {
		    $arrNoti['report'] = NotiReport::getNoti($this, $this->cache, $this->strReportCachePre, $this->intCacheExpired);
		    $arrNoti['message_time']['report'] = $intCurrentTime;
		} else {
		    $arrMessage = array();
		    $arrMessage['report'] = array();
		    $arrNoti['report'] = $arrMessage;
		    $arrNoti['message_time']['report'] = $this->arrTimeInfo['report'];
		}

		//===============云输入链接打开类型===============
		//if ( $this->intVersion >= 6000000 && $intCurrentTime - $this->arrTimeInfo['cloud_input_link'] > $this->intCallInterval){
		if ( Util::boolVersionPlatform(6000000) && $intCurrentTime - $this->arrTimeInfo['cloud_input_link'] > $this->intCallInterval){
		    $arrNoti['cloud_input_link'] = NotiCloudInputLink::getNoti($this, $this->cache, $this->strCloudInputTypeCachePre, $this->intCacheExpired);
		    $arrNoti['message_time']['cloud_input_link'] = $intCurrentTime;
		} else {
		    $arrNoti['cloud_input_link'] = array();
		    $arrNoti['message_time']['cloud_input_link'] = $this->arrTimeInfo['cloud_input_link'];
		}

		//===============地理位置===============
		//if ( $this->intVersion >= 5050000 && $intCurrentTime - $this->arrTimeInfo['location'] > $this->intCallInterval){
		if ( Util::boolVersionPlatform(5050000) && $intCurrentTime - $this->arrTimeInfo['location'] > $this->intCallInterval){
		    $arrCityInfo = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo);
		    $arrNoti['location'] = isset($arrCityInfo['city'])? $arrCityInfo['city'] : '';
		    $arrNoti['message_time']['location'] = $intCurrentTime;
		} else {
		    $arrNoti['location'] = array();
		    $arrNoti['message_time']['location'] = $this->arrTimeInfo['location'];
		}

		//===============应用内侧滑配置消息===============
		//if ( isset($arrUserPostInfo['slide']['ver']) && $this->intVersion >= 5030000 && $intCurrentTime - $this->arrTimeInfo['slide'] > $this->intCallInterval){
		if ( isset($arrUserPostInfo['slide']['ver']) && Util::boolVersionPlatform(5030000) && $intCurrentTime - $this->arrTimeInfo['slide'] > $this->intCallInterval){
		    $intSlideconfver = abs(intval($arrUserPostInfo['slide']['ver']));
		    $arrNoti['slide'] = NotiSlide::getNoti($this, $this->cache, $this->strSlideCachePre, $this->intCacheExpired, $intSlideconfver);
		    $arrNoti['message_time']['slide'] = $intCurrentTime;
		} else {
		    $arrNoti['slide'] = array();
		    $arrNoti['message_time']['slide'] = $this->arrTimeInfo['slide'];
		}

		//===============场景更新===============
		if (isset($arrUserPostInfo['new_scene']['msgver']) && $intCurrentTime - $this->arrTimeInfo['new_scene'] > $this->intCacheExpired){
		    $arrNoti['new_scene'] = NotiNewScene::getNoti($this, $this->cache, $this->strNewSceneCachePre, $this->intCacheExpired, intval($arrUserPostInfo['new_scene']['msgver']), $this->getClientIP());
		    $arrNoti['message_time']['new_scene'] = $intCurrentTime;
		} else {
		    $arrNoti['new_scene'] = array();
		    $arrNoti['message_time']['new_scene'] = $this->arrTimeInfo['new_scene'];
		}

		//===============6.6版本以上活动开启时间 Edit by fanwenli, 2015-12-31===============
        //if ($this->intVersion >= 6060000 && $intCurrentTime - $this->arrTimeInfo['activity_time'] >= $this->intCallInterval){
        if (Util::boolVersionPlatform(6060000) && $intCurrentTime - $this->arrTimeInfo['activity_time'] >= $this->intCallInterval){
            $arrNoti['activity_time'] = NotiActivityTime::getNoti($this, $this->cache, $this->strActivityTimeCachePre, $this->intCacheExpired, $this->arrTimeInfo['activity_time']);
            $arrNoti['message_time']['activity_time'] = $intCurrentTime;
        } else {
            $arrNoti['activity_time'] = array();
            $arrNoti['message_time']['activity_time'] = $this->arrTimeInfo['activity_time'];
        }

		//===============云开关控制器，根据网络、版本、平台、用户标识向客户端提供云端开关数据===============
        $arrNoti['cc'] = NotiCloudSwitch::getCloudSwitchData($intSp, $strUid, $strVersion, $strPlatform, $this->arrTestUid, $arrUnifyConf, $this->cache, $strCuid);

		//==============通知中心最多保留消息条数================
		$arrNoti['maxnum'] = self::NOTI_NESSAGE_MAX_KEEP_NUM;

		//=========通知中心获取手机ppi =============
		$arrNoti['ppi'] = NotiPhonePpi::getPhonePpi($this->arrPhoneInfo['model'], $this->arrPhoneInfo['resolution']);

		//==========获取表情最新版本===========
		$arrNoti['emojiver'] = NotiEmojiVer::getLatestAddTime($this, $this->cache, $this->strEmojiVerCachePre, $this->intCacheExpired);

		//===============获取仓颉最新版本================
		$arrNoti['cangjiever'] = NotiCangjieVer::getLatestVer($this, $this->cache, $this->strCangjieVerCachePre, $this->intCacheExpired);

		//获取注音最新版本
		$arrNoti['zhuyinver'] = NotiZhuyinVer::getLatestVer($this, $this->cache, $this->strZhuyinVerCachePre, $this->intCacheExpired);

		//动态表情模板更新提醒
		$arrNoti['demojiver'] = NotiDemojiLatestVer::getNoti($this, $this->cache, $this->strDemojiLatestVerCachePre, $this->intCacheExpired);

		//本地地理词库最大虚条目保存数目
		$arrNoti['local_words_max'] = self::LOCAL_CELL_WORDS_MAX_KEEP_NUM;

		//微信附件面板高度
		$arrNoti['wxpanelheight'] = 0;

		//android客户端调用本地框所适用的框信息,android5.5.5及以上才有,10%用户收到
		NotiSearchWithApp::getNoti($this, $this->cache, $this->strSearchWithAppCachePre, $this->intCacheExpired, $this->arrTimeInfo['search_with_app'], $this->intVersion, $strPlatform, $strCuid, $arrNoti);

		//广告更新标记
		//if( $this->intVersion >= 5030000 ){
		if (Util::boolVersionPlatform(5030000)) {
		    $apcrediscache = new ApcRedisCache($this->cache);
		    NotiAds::getNoti($this, $apcrediscache, $this->strAdsCachePre, $this->intCacheExpired, $this->arrTimeInfo['ads'], $arrNoti);
		}

		//通知中心颜文字“最热”通知
		//if( $this->intVersion >= 6000000 && isset($arrUserPostInfo['emoticon_hot']['ver']) ){
		if( Util::boolVersionPlatform(6000000) && isset($arrUserPostInfo['emoticon_hot']['ver']) ){
		    NotiEmotionHot::getNoti($this, $this->cache, $this->strEmoticonHotCachePre, $this->intCacheExpired, $arrUserPostInfo['emoticon_hot']['ver'], $intCurrentTime, $this->arrTimeInfo['emoticon_hot'], $this->intCallInterval, $arrNoti);
		}

	    //================IOS内测====================
		/*if ($this->arrPhoneInfo['phone_os'] == 'ios' && $intCurrentTime - $this->arrTimeInfo['ios_beta'] >= $this->intCallInterval){
		    $arrNoti['ios_beta'] = NotiIosBeta::getNoti($this, $this->cache, $this->strIosBetaCachePre, $this->intCacheExpired, $intCurrentTime, $this->intVersion, $strCuid);
		    $arrNoti['message_time']['ios_beta'] = $intCurrentTime;
		} else{
		    $arrNoti['ios_beta'] = array();
		    $arrNoti['message_time']['ios_beta'] = $this->arrTimeInfo['ios_beta'];
		}*/

		//通知懒人语料
		//if( $this->intVersion >= 6080000 ){
		if(Util::boolVersionPlatform(6080000)){
		    NotiLazyCorpora::getNoti($arrNoti);
		}

		//表情转换关系
		//if( $this->intVersion >= 7000000 ){
		if(Util::boolVersionPlatform(7000000)){
		    NotiEmojiInvert::getNoti($arrNoti);
		}

		//彩蛋下发
		//if( $this->intVersion >= 6050000 ){
		if(Util::boolVersionPlatform(6050000)){
		    NotiScreenEggs::getNoti($arrNoti);
		}

		$arrNoti['acs_switch'] = $arrUnifyConf['acs_switch'];

		//if ($this->intVersion >= 7020000) {
		if(Util::boolVersionPlatform(7020000)){
			//push delay 推送延迟
			//$pdModel = IoCload('models\\PushDelayModel');
			//$arrNoti['push_delay'] = $pdModel->getDelayTime();

			if(isset($arrUserPostInfo['emoticon_hot']['ver'])){
				!isset($arrNoti['content_ver']) && $arrNoti['content_ver'] = array();
				//webview_sdk
				$wvModel = IoCload('models\\WebviewsdkModel');
				$arrNoti['content_ver']['webview_sdk_ver'] = $wvModel->cache_getSdkVer();

				//android_hot_patch
				$hpModel = IoCload('models\\HotpatchModel');
				$arrNoti['content_ver']['android_hotpatch_ver'] = $hpModel->cache_getVer();

				//颜文字最热tab 通知
				$emoticonModel = IoCload('models\\EmoticonModel');
				$arrNoti['content_ver']['buildhot'] = $emoticonModel->cache_getBuildhotVer();

				$emojitabModel = IoCload('models\\EmojitabModel');
        		$emojitab = $emojitabModel->getEmojitab();
        		$arrNoti['content_ver']['emojitab_version_code'] = !empty($emojitab['version_code']) ? $emojitab['version_code'] : 0;
			}
			
			//acs
            if (isset($arrUserPostInfo['acs']['cw_version_code']) && isset($arrUserPostInfo['acs']['pl_version_code']) && isset($arrUserPostInfo['acs']['wl_version_code'])) {
				$apnModel = IoCload('models\\AcsPackageNameModel');
				$awlModel = IoCload('models\\AcsWhiteListModel');
				$twfModel = IoCload('models\\TopWordsFileModel');
				$twf = $twfModel->cache_getNewFile();
				$apnVer = $apnModel->cache_getVer();
				$awlVer = $awlModel->cache_getVer();
				$arrNoti['acs'] = array(
					'pl_version_code' => $apnVer,
					'wl_version_code' => $awlVer,
					'cw_version_code' => empty($twf['version_code']) ? 0 : intval($twf['version_code']),
					'dlink' => empty($twf['url']) ? '' : $twf['url'],
					'cw_update' => $arrUserPostInfo['acs']['cw_version_code'] < $twf['version_code']
						? true : false,
					'pl_update' => $arrUserPostInfo['acs']['pl_version_code'] < $apnVer
						? true : false,
					'wl_update' => $arrUserPostInfo['acs']['wl_version_code'] < $awlVer
						? true : false,
				);
				
				
				//智能回复白名单版本号下发， 数据更新接口在/v5/acs/airwl/? 
				if(isset($arrUserPostInfo['acs']['airwl_version_code'])) {
				    $airwlModel = IoCload('models\\AirModel');
				    $airwlVer = $airwlModel->cache_getVer();
				    $arrNoti['acs']['airwl_version_code'] = $airwlVer;
				}
				
				//智能回复下载包版本号下发, 客户端暂时只是上传和本地保存，不用在具体业务，保留是为了服务端扩展业务用
				if(isset($arrUserPostInfo['acs']['airpack_version_code'])) {
				    $airpackModel = IoCload('models\\AirPackModel');
				    $airpackVer = $airpackModel->cache_getVer();
				    $arrNoti['acs']['airpack_version_code'] = $airpackVer;
                }
                
                 //智能回复下载包内核版本号下发, 数据更新接口在/v5/acs/airwl/airpack? 
                if(isset($arrUserPostInfo['acs']['core_version_code'])) {
                    $airpackModel = IoCload('models\\AirPackModel');
                    $airpackCoreVer = $airpackModel->cache_getCoreVer();
                    $arrNoti['acs']['core_version_code'] = $airpackCoreVer;
                }
                
                //智能回复策略下发, 数据更新接口在/v5/acs/airwl/airstra? 
                if(isset($arrUserPostInfo['acs']['airstra_version_code'])) {
                    $airstraModel = IoCload('models\\AirStraModel');
                    $airstraVer = $airstraModel->cache_getVer();
                    $arrNoti['acs']['airstra_version_code'] = $airstraVer;
                }
			}
			

			$tplVerModel = IoCload('models\\SearchTplVersionModel');
			$arrNoti['message_time']['search_tpl_version'] = intval($tplVerModel->cache_getMaxVersion());

			$talModel = IoCload('models\\TipsAppListModel');
			$arrNoti['tal_version'] = intval($talModel->cache_getVer());
		}

		//颜文字通知
		if ( isset($arrUserPostInfo['ad_emoticon']['msgver']) && ($intCurrentTime - $this->arrTimeInfo['ad_emoticon'] >= $this->intCallInterval) ) {
		    $arrEmoticonNoti = NotiEmoticon::getNoti($this, $this->cache, $this->strEmoticonCachePre, $this->intCacheExpired, $this->arrTimeInfo['ad_emoticon'], $arrUserPostInfo['ad_emoticon']['msgver'], $this->getPhoneOS($strPlatform));
		    $arrNoti['ad_emoticon'] = isset($arrEmoticonNoti['info'])? $arrEmoticonNoti['info'] : array();
		    $arrNoti['message_time']['ad_emoticon'] = $intCurrentTime;
		}else{
		    $arrNoti['message_time']['ad_emoticon'] = $this->arrTimeInfo['ad_emoticon'];
		}

		//================版本升级消息====================

		if ($intCurrentTime - $this->arrTimeInfo['soft'] >= $this->intCallInterval ){
		     //版本升级需要获取userinfo softinfo
		     $this->getUserInfo($arrUserPostInfo, $strUid, $strCuid, $strImei, $strPlatform, $strFrom, $strCfrom, $intSp, $strNetWork, self::getClientIP());
		     $this->getSoftInfo($arrUserPostInfo, $strVersion);

		     $arrNoti['soft'] = NotiSoft::fetchVersionUpgradeMessage($this, $this->cache, $this->strSoftCachePre, $this->intCacheExpired, $this->getPhoneOS($strPlatform), $strNetWork, $arrNoti['oem_lc_switch'],
                     $strVersion, $strPlatform, $this->arrPhoneInfo, $this->arrUserInfo, $this->arrSoftInfo, $this->strLcIncUpgradeAddress, $this->strLcUpgradeAddress, $this->intDebugFlag, $this->getClientIP(), $bolDebugLog);

		     $arrNoti['message_time']['soft'] = $intCurrentTime;
		} else{
		     $arrNoti['soft'] = array();
		     $arrNoti['message_time']['soft'] = $this->arrTimeInfo['soft'];
		}

		//===============TabOem下发 Edit by fanwenli, 2016-03-08===============
        if (isset($arrUserPostInfo['tab_oem']['tab_oem_ver']) && $intCurrentTime - $this->arrTimeInfo['tab_oem'] >= $this->intCallInterval){
            //获取客户端传送的版本号
        	if(isset($arrUserPostInfo['tab_oem']['tab_oem_ver']) && $arrUserPostInfo['tab_oem']['tab_oem_ver'] != ''){
				$tab_version = (int)$arrUserPostInfo['tab_oem']['tab_oem_ver'];
			}else{
				$tab_version = 0;
			}

            $arrNoti['tab_oem'] = NotiTabOem::getNoti($this, $this->cache, $this->strTabOemCachePre, $this->intCacheExpired, $this->arrTimeInfo['tab_oem'], $this->strMicHttpRoot, $tab_version);
            $arrNoti['message_time']['tab_oem'] = $intCurrentTime;
        } else {
            $arrNoti['tab_oem'] = array();
            $arrNoti['message_time']['tab_oem'] = $this->arrTimeInfo['tab_oem'];
        }

        //===============IconOem下发 Edit by fanwenli, 2016-03-11===============
        if (isset($arrUserPostInfo['icon_oem']['icon_oem_ver']) && $intCurrentTime - $this->arrTimeInfo['icon_oem'] >= $this->intCallInterval){
            //获取客户端传送的版本号
        	if(isset($arrUserPostInfo['icon_oem']['icon_oem_ver']) && $arrUserPostInfo['tab_oem']['icon_oem_ver'] != ''){
				$icon_version = (int)$arrUserPostInfo['icon_oem']['icon_oem_ver'];
			}else{
				$icon_version = 0;
			}

            $arrNoti['icon_oem'] = NotiIconOem::getNoti($this, $this->cache, $this->strIconOemCachePre, $this->intCacheExpired, $this->arrTimeInfo['icon_oem'], $icon_version);
            $arrNoti['message_time']['icon_oem'] = $intCurrentTime;
        } else {
            $arrNoti['icon_oem'] = array();
            $arrNoti['message_time']['icon_oem'] = $this->arrTimeInfo['icon_oem'];
        }

        //===============皮肤熊头提醒下发 Edit by fanwenli, 2016-03-11===============
        if (isset($arrUserPostInfo['skin_bear_remind_oem']['skin_bear_oem_ver']) && $intCurrentTime - $this->arrTimeInfo['skin_bear_remind_oem'] >= $this->intCallInterval){
            //获取客户端传送的版本号
        	if(isset($arrUserPostInfo['skin_bear_remind_oem']['skin_bear_oem_ver']) && $arrUserPostInfo['skin_bear_remind_oem']['skin_bear_oem_ver'] != ''){
				$skin_bear_remind_version = (int)$arrUserPostInfo['skin_bear_remind_oem']['skin_bear_oem_ver'];
			}else{
				$skin_bear_remind_version = 0;
			}

            $arrNoti['skin_bear_remind_oem'] = NotiSkinBearRemindOem::getNoti($this, $this->cache, $this->strSkinBearRemindOemCachePre, $this->intCacheExpired, $this->arrTimeInfo['skin_bear_remind_oem'], $skin_bear_remind_version);
            $arrNoti['message_time']['skin_bear_remind_oem'] = $intCurrentTime;
        } else {
            $arrNoti['skin_bear_remind_oem'] = array();
            $arrNoti['message_time']['skin_bear_remind_oem'] = $this->arrTimeInfo['skin_bear_remind_oem'];
        }

        //===============7.0版本以上通知中心负载均衡时间段下发 Edit by fanwenli, 2016-04-05===============
        /*if ($this->intVersion >= 7000000 && $intCurrentTime - $this->arrTimeInfo['down_balance_time_set'] >= $this->intCallInterval){
        	$arrNoti['down_balance_time_set'] = NotiDownBalanceTimeSet::getNoti($this, $this->cache, $this->strDownBalanceTimeSetCachePre, $this->intCacheExpired, $this->arrTimeInfo['down_balance_time_set']);
        	$arrNoti['message_time']['down_balance_time_set'] = $intCurrentTime;
        } else {
        	$arrNoti['down_balance_time_set'] = array();
        	$arrNoti['message_time']['down_balance_time_set'] = $this->arrTimeInfo['down_balance_time_set'];
        }*/

        //Edit by fanwenli on 2017-01-18, change limit version from 7000000 to 6050000
        //if($this->intVersion >= 6050000) {
        if(Util::boolVersionPlatform(6050000)) {
            $arrNoti['down_balance_time_set'] = is_array($arrUnifyConf['down_balance_time_set'])? $arrUnifyConf['down_balance_time_set']:json_decode($arrUnifyConf['down_balance_time_set'], true);

			if(isset($arrNoti['down_balance_time_set']['start_time_h']) && $arrNoti['down_balance_time_set']['start_time_h'] != '' && isset($arrNoti['down_balance_time_set']['end_time_h']) && $arrNoti['down_balance_time_set']['end_time_h'] != ''){
        		$start_time_h = (int)$arrNoti['down_balance_time_set']['start_time_h'];
        		$end_time_h = (int)$arrNoti['down_balance_time_set']['end_time_h'];

        		//如果开始小时数在终止小时数后，数据不下发
        		if($start_time_h > $end_time_h){
        			$arrNoti['down_balance_time_set'] = UnifyConf::$arrDefaultUnifyConf['down_balance_time_set'];
        		} else {
        			$arrNoti['down_balance_time_set']['start_time_m'] = 0;
        			$arrNoti['down_balance_time_set']['end_time_m'] = 0;

        			//后台设置的随即分钟数
        			if(isset($arrNoti['down_balance_time_set']['random']) && $arrNoti['down_balance_time_set']['random'] != ''){
        				$arrNoti['down_balance_time_set']['end_time_m'] = (int)$arrNoti['down_balance_time_set']['random'];
        				unset($arrNoti['down_balance_time_set']['random']);
        			}
        		}
        	}
		}
		else{
			$arrNoti['down_balance_time_set'] = UnifyConf::$arrDefaultUnifyConf['down_balance_time_set'];
		}


		//===============7.0.2版本以上下发是否更新用户个性化信息标志 Edit by fanwenli, 2016-07-19===============
        //if (isset($arrUserPostInfo['core_user_stat']['user_stat_version']) && $this->intVersion >= 7000200 && $strCuid != '' && $intCurrentTime - $this->arrTimeInfo['core_user_stat'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['core_user_stat']['user_stat_version']) && Util::boolVersionPlatform(7000200) && $strCuid != '' && $intCurrentTime - $this->arrTimeInfo['core_user_stat'] >= $this->intCallInterval){
            //返回个性化信息数组。update为0代表没找到内容，1代表有内容。version代表版本号
            $arrNoti['core_user_stat'] = NotiCoreUserStat::getNoti($this, $this->cache, $this->strCoreUserStatCachePre, $this->intCacheExpired, $this->arrTimeInfo['core_user_stat'], $strCuid, intval($arrUserPostInfo['core_user_stat']['user_stat_version']));
            $arrNoti['message_time']['core_user_stat'] = $intCurrentTime;
        } else {
            $arrNoti['core_user_stat'] = array();
            $arrNoti['message_time']['core_user_stat'] = $this->arrTimeInfo['core_user_stat'];
        }

        //===============7.2版本以上下发手助lite的icon Edit by fanwenli, 2016-07-21===============
        //if (isset($arrUserPostInfo['icon_lite']['icon_lite_ver']) && $this->intVersion >= 7020000 && $intCurrentTime - $this->arrTimeInfo['icon_lite'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['icon_lite']['icon_lite_ver']) && Util::boolVersionPlatform(7020000) && $intCurrentTime - $this->arrTimeInfo['icon_lite'] >= $this->intCallInterval){
            $arrNoti['icon_lite'] = NotiIconLite::getNoti($this, $this->cache, $this->strIconLiteCachePre, $this->intCacheExpired, $this->arrTimeInfo['icon_lite'], intval($arrUserPostInfo['icon_lite']['icon_lite_ver']));
            $arrNoti['message_time']['icon_lite'] = $intCurrentTime;
        } else {
            $arrNoti['icon_lite'] = array();
            $arrNoti['message_time']['icon_lite'] = $this->arrTimeInfo['icon_lite'];
        }

        //===============7.2版本以上下发手助lite插件下载标志 Edit by fanwenli, 2016-07-27===============
        //if (isset($arrUserPostInfo['plugin_download_lite']['plugin_download_lite_ver']) && $this->intVersion >= 7020000 && $intCurrentTime - $this->arrTimeInfo['plugin_download_lite'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['plugin_download_lite']['plugin_download_lite_ver']) && Util::boolVersionPlatform(7020000) && $intCurrentTime - $this->arrTimeInfo['plugin_download_lite'] >= $this->intCallInterval){
            $arrNoti['plugin_download_lite'] = NotiPluginDownloadLite::getNoti($this, $this->cache, $this->strPluginDownloadLiteCachePre, $this->intCacheExpired, $this->arrTimeInfo['plugin_download_lite'], $arrUserPostInfo['plugin_download_lite']['plugin_download_lite_ver']);
            $arrNoti['message_time']['plugin_download_lite'] = $intCurrentTime;
        } else {
            $arrNoti['plugin_download_lite'] = 0;
            $arrNoti['message_time']['plugin_download_lite'] = $this->arrTimeInfo['plugin_download_lite'];
        }

        //===============6.5版本以上下发场景化语音条 Edit by fanwenli, 2016-12-09===============
        //if (isset($arrUserPostInfo['scene_voice_cand']['scene_voice_cand_ver']) && $this->intVersion >= 6050000 && $intCurrentTime - $this->arrTimeInfo['scene_voice_cand'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['scene_voice_cand']['scene_voice_cand_ver']) && Util::boolVersionPlatform(6050000) && $intCurrentTime - $this->arrTimeInfo['scene_voice_cand'] >= $this->intCallInterval){
            $arrNoti['scene_voice_cand'] = NotiSceneVoiceCand::getNoti($this, $this->cache, $this->strSceneVoiceCandCachePre, $this->intCacheExpired, $this->arrTimeInfo['scene_voice_cand'], $arrUserPostInfo['scene_voice_cand']['scene_voice_cand_ver']);
            $arrNoti['message_time']['scene_voice_cand'] = $intCurrentTime;
        } else {
            $arrNoti['scene_voice_cand'] = array();
            $arrNoti['message_time']['scene_voice_cand'] = $this->arrTimeInfo['scene_voice_cand'];
        }

        //===============7.2版本以上下发乐视6.0智能回复数据文件服务端更新 Edit by fanwenli, 2016-10-14===============
        //if (isset($arrUserPostInfo['le_data_recover']['le_data_recover_ver']) && $this->intVersion >= 7020000 && $intCurrentTime - $this->arrTimeInfo['le_data_recover'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['le_data_recover']['le_data_recover_ver']) && Util::boolVersionPlatform(7020000) && $intCurrentTime - $this->arrTimeInfo['le_data_recover'] >= $this->intCallInterval){
        	$arrNoti['le_data_recover'] = NotiLeDataRecover::getNoti($this, $this->cache, $this->strLeDataRecoverCachePre, $this->intCacheExpired, $this->arrTimeInfo['le_data_recover'], $arrUserPostInfo['le_data_recover']['le_data_recover_ver']);
            $arrNoti['message_time']['le_data_recover'] = $intCurrentTime;
        } else {
            $arrNoti['le_data_recover'] = array();
            $arrNoti['message_time']['le_data_recover'] = $this->arrTimeInfo['le_data_recover'];
        }

        //===============7.2版本以上或者小米6.6以上下发场景化地图和搜索语音条 Edit by fanwenli, 2017-04-27===============
        //$judge_version = (($this->intVersion >= 7020000 && $strPlatform != 'p-a1-3-66') || ($this->intVersion >= 6050000 && $strPlatform == 'p-a1-3-66')) ? 1 : 0;
        $judge_version = ((Util::boolVersionPlatform(7020000) && $strPlatform != 'p-a1-3-66') || (Util::boolVersionPlatform(6050000) && $strPlatform == 'p-a1-3-66')) ? 1 : 0;
        if (isset($arrUserPostInfo['scene_map_search_voice_cand']['scene_map_search_voice_cand_ver']) && $judge_version && $intCurrentTime - $this->arrTimeInfo['scene_map_search_voice_cand'] >= $this->intCallInterval){
            $arrNoti['scene_map_search_voice_cand'] = NotiSceneMapSearchVoiceCand::getNoti($this, $this->cache, $this->strSceneMapSearchVoiceCandCachePre, $this->intCacheExpired, $this->arrTimeInfo['scene_map_search_voice_cand'], $arrUserPostInfo['scene_map_search_voice_cand']['scene_map_search_voice_cand_ver']);
            $arrNoti['message_time']['scene_map_search_voice_cand'] = $intCurrentTime;
        } else {
            $arrNoti['scene_map_search_voice_cand'] = array();
            $arrNoti['message_time']['scene_map_search_voice_cand'] = $this->arrTimeInfo['scene_map_search_voice_cand'];
        }

        //===============7.2版本以上下发语音标点黑名单 Edit by fanwenli, 2016-10-24===============
        //if (isset($arrUserPostInfo['punctuation_blacklist']['punctuation_blacklist_ver']) && $this->intVersion >= 7020000 && $intCurrentTime - $this->arrTimeInfo['punctuation_blacklist'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['punctuation_blacklist']['punctuation_blacklist_ver']) && Util::boolVersionPlatform(7020000) && $intCurrentTime - $this->arrTimeInfo['punctuation_blacklist'] >= $this->intCallInterval){
            $arrNoti['punctuation_blacklist'] = NotiPunctuationBlacklist::getNoti($this, $this->cache, $this->strPunctuationBlacklistCachePre, $this->intCacheExpired, $this->arrTimeInfo['punctuation_blacklist'], $arrUserPostInfo['punctuation_blacklist']['punctuation_blacklist_ver']);
            $arrNoti['message_time']['punctuation_blacklist'] = $intCurrentTime;
        } else {
            $arrNoti['punctuation_blacklist'] = array();
            $arrNoti['message_time']['punctuation_blacklist'] = $this->arrTimeInfo['punctuation_blacklist'];
        }

        //===============6.5版本以上下发场景化语音通讯录 Edit by fanwenli, 2017-03-01===============
        //if (isset($arrUserPostInfo['scene_address_book_voice']['scene_address_book_voice_ver']) && $this->intVersion >= 6050000 && $intCurrentTime - $this->arrTimeInfo['scene_address_book_voice'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['scene_address_book_voice']['scene_address_book_voice_ver']) && Util::boolVersionPlatform(6050000) && $intCurrentTime - $this->arrTimeInfo['scene_address_book_voice'] >= $this->intCallInterval){
            $arrNoti['scene_address_book_voice'] = NotiSceneAddressBookVoice::getNoti($this, $this->cache, $this->strSceneAddressBookVoiceCachePre, $this->intCacheExpired, $this->arrTimeInfo['scene_address_book_voice'], $arrUserPostInfo['scene_address_book_voice']['scene_address_book_voice_ver']);
            $arrNoti['message_time']['scene_address_book_voice'] = $intCurrentTime;
        } else {
            $arrNoti['scene_address_book_voice'] = array();
            $arrNoti['message_time']['scene_address_book_voice'] = $this->arrTimeInfo['scene_address_book_voice'];
        }

        //===============7.3版本以上下发红点/熊头开关  新功能红点开关 Edit by zhoubin05, 2016-12-12===============
        //if(isset($arrUserPostInfo['tips_icon_version']) && $this->intVersion >= 7030000 && $intCurrentTime - $this->arrTimeInfo['tips_icon_version'] >= $this->intCallInterval ) {
        if(isset($arrUserPostInfo['tips_icon_version']) && Util::boolVersionPlatform(7030000) && $intCurrentTime - $this->arrTimeInfo['tips_icon_version'] >= $this->intCallInterval ) {
            $arrNoti['tips_switch'] = array();
            $arrNoti['tips_switch']['emojis'] = $arrUnifyConf['tips_switch_emojis']; //表情icon 红点/熊头提示开关 0：不显示(默认) 1：红点 2：熊头

            $red_point_list = UnifyConf::getUnifyConf($this->cache, 'noti_red_point_new_conf_pre', $this->intCacheExpired, '/res/json/input/r/online/icon_red_point/', self::getClientIP(), $strFrom, false);
            $tips_icon = array(0 => array(), 1 => array(), 2 => array());

            $rpl_tmp =  array();
            if(is_array($red_point_list)) {
                foreach($red_point_list as $k => $v) {
                    if($v['version'] > $rpl_tmp['version'] && $v['version'] > 0) {
                        $rpl_tmp = $v;
                   }
                }

                if(!empty($rpl_tmp)) {
                    $tips_icon[0] = $rpl_tmp['no_point'];
                    $tips_icon[1] = $rpl_tmp['point'];
                    $tips_icon[2] = $rpl_tmp['bear'];
                    sort($tips_icon[0]);
                    sort($tips_icon[1]);
                    sort($tips_icon[2]);
                    $arrNoti['tips_icon'] = $tips_icon;
                    $arrNoti['tips_icon_version'] = $rpl_tmp['version'];
                } else {
                    $arrNoti['tips_icon_version'] = 0;
                }
                $arrNoti['message_time']['tips_icon_version'] = $intCurrentTime;

            } else {
                $arrNoti['tips_icon'] = $tips_icon;
                $arrNoti['tips_icon_version'] = 0;
                $arrNoti['message_time']['tips_icon_version'] = $this->arrTimeInfo['tips_icon_version'];
            }

        }

        //===============6.5版本以上下发多模语音搜索 Edit by fanwenli, 2017-03-01===============
        //if ((isset($arrUserPostInfo['minimalist_voice_cand']['android_ver']) || isset($arrUserPostInfo['minimalist_voice_cand']['ios_version'])) && $this->intVersion >= 6050000 && $intCurrentTime - $this->arrTimeInfo['minimalist_voice_cand'] >= $this->intCallInterval){
        if ((isset($arrUserPostInfo['minimalist_voice_cand']['android_ver']) || isset($arrUserPostInfo['minimalist_voice_cand']['ios_version'])) && Util::boolVersionPlatform(6050000) && $intCurrentTime - $this->arrTimeInfo['minimalist_voice_cand'] >= $this->intCallInterval){
            $arrNoti['minimalist_voice_cand'] = NotiMinimalistVoiceCand::getNoti($this, $this->cache, $this->strMinimalistVoiceCandCachePre, $this->intCacheExpired, $this->arrTimeInfo['minimalist_voice_cand'], $arrUserPostInfo['minimalist_voice_cand']);
            $arrNoti['message_time']['minimalist_voice_cand'] = $intCurrentTime;
        } else {
            $arrNoti['minimalist_voice_cand'] = array();
            $arrNoti['message_time']['minimalist_voice_cand'] = $this->arrTimeInfo['minimalist_voice_cand'];
        }

        //===============7.3版本以上下发IOS场景化语音 Edit by fanwenli, 2016-12-14===============
        //if (isset($arrUserPostInfo['ios_scene_voice_cand']['ver']) && $this->intVersion >= 7030000 && $intCurrentTime - $this->arrTimeInfo['ios_scene_voice_cand'] >= $this->intCallInterval){
        if (isset($arrUserPostInfo['ios_scene_voice_cand']['ver']) && Util::boolVersionPlatform(7030000) && $intCurrentTime - $this->arrTimeInfo['ios_scene_voice_cand'] >= $this->intCallInterval){
            $arrNoti['ios_scene_voice_cand'] = NotiIosSceneVoiceCand::getNoti($this, $this->cache, $this->strIosSceneVoiceCandCachePre, $this->intCacheExpired, $this->arrTimeInfo['ios_scene_voice_cand'], $arrUserPostInfo['ios_scene_voice_cand']['ver']);
            $arrNoti['message_time']['ios_scene_voice_cand'] = $intCurrentTime;
        } else {
            $arrNoti['ios_scene_voice_cand'] = array();
            $arrNoti['message_time']['ios_scene_voice_cand'] = $this->arrTimeInfo['ios_scene_voice_cand'];
        }

        //===============7.3版本以上下发长语音包名白名单版本号 Edit by zhoubin05, 2016-12-14===============
        //if(isset($arrUserPostInfo['voice_pkg_white_list']['version']) && $this->intVersion >= 7030000 && $intCurrentTime - $this->arrTimeInfo['voice_pkg_white_list'] >= $this->intCallInterval) {
        if(isset($arrUserPostInfo['voice_pkg_white_list']['version']) && Util::boolVersionPlatform(7030000) && $intCurrentTime - $this->arrTimeInfo['voice_pkg_white_list'] >= $this->intCallInterval) {
            $voiceModel = IoCload('models\\VoiceModel');
            $arrNoti['voice_pkg_white_list'] = $voiceModel->getMessageVersion();
            $arrNoti['message_time']['voice_pkg_white_list'] = $intCurrentTime;
        } else {
            $arrNoti['voice_pkg_white_list'] = array();
            $arrNoti['message_time']['voice_pkg_white_list'] = $this->arrTimeInfo['voice_pkg_white_list'];
        }

        //===============6.5版本以上下发小米数据分享开关 Edit by fanwenli, 2017-02-10===============
        //if($this->intVersion >= 6050000 && isset($arrUnifyConf['mi_data_share_switch']) && $arrUnifyConf['mi_data_share_switch'] !== '') {
        if(Util::boolVersionPlatform(6050000) && isset($arrUnifyConf['mi_data_share_switch']) && $arrUnifyConf['mi_data_share_switch'] !== '') {
			$arrNoti['mi_data_share_switch'] = $arrUnifyConf['mi_data_share_switch'];
		}


        //===============7.4版本以上下发地理词库信息 Edit by zhoubin05, 2017-02-14===============
        //if ($this->intVersion >= 7040000 ){
        if (Util::boolVersionPlatform(7040000)){

            //edit by zhoubin05 20200506 某些系统会记录app获取权限的记录，用户会看到。为了避免这种情况，客户端不会上传location的内容
            //此时要用ip获取地理信息， 判断isset的目的是为了尽可能减少 getPosition方法的调用
            if (Util::getVersionIntValue($_GET['version']) > 8020000 ) {

                if(!isset($arrCityInfo) || empty($arrCityInfo)) {
                    $arrCityInfo = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo);
                }

                $arrLocalWords = NotiCellLoc::getNoti($this, $this->cache, $this->intCacheExpired, $this->strV4HttpRoot, $arrUserPostInfo, $arrCityInfo, $strCuid);
                if (!empty($arrLocalWords)) {
                    $arrNoti['cell_loc'] = $arrLocalWords;
                }

            } else if(isset ( $arrLocationInfo['location'] ) && ! empty ( $arrLocationInfo['location'])) {
                if(!isset($arrCityInfo) || empty($arrCityInfo)) {
                    $arrCityInfo = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo);
                }

                $arrLocalWords = NotiCellLoc::getNoti($this, $this->cache, $this->intCacheExpired, $this->strV4HttpRoot, $arrUserPostInfo, $arrCityInfo, $strCuid);
                if (!empty($arrLocalWords)) {
                    $arrNoti['cell_loc'] = $arrLocalWords;
                }
            }


            //语音提示
            $vtipsModel = IoCload('models\\VoiceTipsModel');
            $arrNoti['voice_tips_ver'] =  $vtipsModel->getVer();
        }
        
        
        //内核so文件下发
//         $objKernelModel = IoCload('models\\KernelModel');
//         $arrNoti['kernel_so_version'] = $objKernelModel->cache_getVer();
        
        //===============7.4版本以上下发智能强引导开关和智能强引导黑名单 Edit by fanwenli, 2017-04-26===============
        //if($this->intVersion >= 7040000 ){
        if(Util::boolVersionPlatform(7040000)){
		    $arrNoti['int_guidance_switch'] = $arrUnifyConf['int_guidance_switch'];
		    
		    if (isset($arrUserPostInfo['blacklist_int_guidance']['version']) && $intCurrentTime - $this->arrTimeInfo['blacklist_int_guidance'] >= $this->intCallInterval){
                //1代表的是智能强制黑名单
                $arrNoti['blacklist_int_guidance'] = NotiBlacklist::getNoti($this, $this->cache, $this->strBlacklistIntGuidanceCachePre, $this->intCacheExpired, $this->arrTimeInfo['blacklist_int_guidance'], $arrUserPostInfo['blacklist_int_guidance']['version'],1);
                $arrNoti['message_time']['blacklist_int_guidance'] = $intCurrentTime;
            } else {
                $arrNoti['message_time']['blacklist_int_guidance'] = $this->arrTimeInfo['blacklist_int_guidance'];
            }
            
            /*if (isset($arrUserPostInfo['blacklist_int_guidance']['version']) && $intCurrentTime - $this->arrTimeInfo['blacklist_int_guidance'] >= $this->intCallInterval){
                $arrNoti['int_guidance_switch'] = $arrUnifyConf['int_guidance_switch'];
            }*/
            
            //7.5版本以上下发IOS语音麦克风模式开关 Edit by fanwenli, 2017-06-26，如没设置或过滤条件通不过则不下发
            if($arrUnifyConf['ios_microphone_mode_switch'] !== '') {
                $arrNoti['ios_microphone_mode_switch'] = $arrUnifyConf['ios_microphone_mode_switch'];
            }
            
            //===============7.4版本以上下发语料和谐及数据源管理 Edit by fanwenli, 2017-09-07===============
            if (isset($arrUserPostInfo['string_safty_replace']['version']) && $intCurrentTime - $this->arrTimeInfo['string_safty_replace'] >= $this->intCallInterval){
                //Edit by fanwenli on 2018-02-06, use new function to get cache and delete api data
                $api_key_conf = GFunc::getConf('Stringsaftyreplace');
                $api_key = $api_key_conf['properties']['list_data_cache_key'];
                
                $arrNoti['string_safty_replace'] = NotiComm::getNoti('string_safty_replace', $this, $this->cache, $this->strStringSaftyReplaceCachePre, $this->intCacheExpired, $this->arrTimeInfo['string_safty_replace'], $arrUserPostInfo['string_safty_replace']['version'],$api_key);
                $arrNoti['message_time']['string_safty_replace'] = $intCurrentTime;
            } else {
                $arrNoti['message_time']['string_safty_replace'] = $this->arrTimeInfo['string_safty_replace'];
            }
		}
		
		//===============语音识别及翻译列表 Add by zhoubin, 2017-05-09===============
        if (isset($arrUserPostInfo['voice']['vdt_version']) ){
            $vdtModel = IoCload('models\\VoiceDistinguishTranslateModel');
            $arrNoti['voice']['vdt_version'] = $vdtModel->cache_getVer();
        }
        
        //===============6.5版本以上下发彩蛋下发策略 Edit by fanwenli, 2017-05-10===============
        //if($this->intVersion >= 6050000) {
        if(Util::boolVersionPlatform(6050000)){
			if(isset($arrUserPostInfo['screen_eggs_strategy']['version']) && $intCurrentTime - $this->arrTimeInfo['screen_eggs_strategy'] >= $this->intCallInterval) {
			    $arrNoti['screen_eggs_strategy'] = NotiScreenEggsStrategy::getNoti($this, $this->cache, $this->strScreenEggsStrategyCachePre, $this->intCacheExpired, $this->arrTimeInfo['screen_eggs_strategy'], $arrUserPostInfo['screen_eggs_strategy']['version']);
                $arrNoti['message_time']['screen_eggs_strategy'] = $intCurrentTime;
            } else {
                $arrNoti['screen_eggs_strategy'] = array();
                $arrNoti['message_time']['screen_eggs_strategy'] = $this->arrTimeInfo['screen_eggs_strategy'];
            }
        }
        
        //===============运营活动最新修改时间===============
        $objNotiActivityTime = new NotiActivityTime();
        $arrNoti['last_update_activity_time'] = $objNotiActivityTime->getLastUpdateTime($this, $this->cache, $this->strActivityTimeCachePre);
		
		//===============7.6版本以上下发安卓离线语音开关 Edit by fanwenli, 2017-07-14===============
        //if($this->intVersion >= 7060000 ){
        if(Util::boolVersionPlatform(7060000)){
            //7.6版本以上下发安卓离线语音开关, 如没设置或过滤条件通不过则不下发
            if($arrUnifyConf['offline_voice_switch'] !== '') {
                $arrNoti['offline_voice_switch'] = $arrUnifyConf['offline_voice_switch'];
            }
            
            //===============7.6版本以上下发分BundleID支持语音Option&Mode Edit by fanwenli, 2017-08-08===============
            if (isset($arrUserPostInfo['bundle_id_voice']['version']) && $intCurrentTime - $this->arrTimeInfo['bundle_id_voice'] >= $this->intCallInterval){
                $arrNoti['bundle_id_voice'] = NotiBundleIdVoice::getNoti($this, $this->cache, $this->strBundleIdVoiceCachePre, $this->intCacheExpired, $this->arrTimeInfo['bundle_id_voice'], $arrUserPostInfo['bundle_id_voice']['version']);
                $arrNoti['message_time']['bundle_id_voice'] = $intCurrentTime;
            } else {
                $arrNoti['message_time']['bundle_id_voice'] = $this->arrTimeInfo['bundle_id_voice'];
            }
        }
        
        //logo插件
        $objPlugin = IoCload("NotiPlugin");
        $arrNoti['logo_ads_time'] = $objPlugin->getLastUpdateTime($this, $this->cache, $this->strActivityTimeCachePre, $this->intCacheExpired);
		//语音气泡提醒开关
        isset($arrUnifyConf['voice_tips']) && ($arrNoti['voice_tips'] = $arrUnifyConf['voice_tips']);
        //===============AR表情最新修改时间===============
        $objNotiArEmoji = new NotiArEmoji();
        $arrNoti['last_update_aremoji_time'] = $objNotiArEmoji->getLastUpdateTime($this, $this->cache, $this->strEmojiCachePre, $this->intCacheExpired);
        
        //词库载推送
        //1.客户端没有服务端会推，2.客户端版本低服务端会推
        if(isset($arrUserPostInfo['pushword'])){
            $arrPushWordNoti = NotiPushWord::getNoti($this, $this->cache, $this->strPushWordCachePre, $this->intCacheExpired, $this->strV4HttpRoot, $arrUserPostInfo['pushword'], $this->strPushWordConfResRoot);
            $arrNoti['pushword'] = $arrPushWordNoti;
        }
        //ar表情so文件最新修改时间
        $arrNoti['last_update_aremoji_so_time'] = $objNotiArEmoji->getAremojiModifyTime($this, $this->cache, $this->strEmojiCachePre, $this->intCacheExpired);
        //个性化动态热区最新修改时间
        $objNotiHotspots = new NotiHotspots();
        $arrNoti['last_update_hotspots_time'] = $objNotiHotspots->getHotspotsModifyTime($this->cache, $this->strHotspotsCachePre, $this->intCacheExpired);

        //高低端机型判断标准最新修改时间
        $objNotiPerformanceStd = new NotiPerformanceStd();
        $arrNoti['performance_version'] = $objNotiPerformanceStd->getPerformanceStdModifyTime($this->cache, $this->strPerformanceStdCachePre, $this->intCacheExpired);
        $arrNoti['ios_performance_version'] = $objNotiPerformanceStd->getIosPerformanceStdModifyTime($this->cache, $this->strPerformanceStdCachePre, $this->intCacheExpired);

        //app场景词库版本号下发  add by zhoubin05 20180309
        //if( $this->intVersion >= 8000000 ){
        if(Util::boolVersionPlatform(8000000)){
            $ascModel = IoCload('models\\AppSceneWordslibModel');
            $ascResutl = $ascModel->cachez_getResData();
            $app_scene_wordslib = array('version' => isset($ascResutl['version']) ? intval($ascResutl['version']) : 0);
            $arrNoti['app_scene_wordslib'] = $app_scene_wordslib;
        }
        
        //===============IOS语音助手黑名单下发 Edit by fanwenli, 2018-04-25===============
        if (isset($arrUserPostInfo['ios_voice_blacklist']['version']) && $intCurrentTime - $this->arrTimeInfo['ios_voice_blacklist'] >= $this->intCallInterval){
            $arrNoti['ios_voice_blacklist'] = NotiComm::getNoti('ios_voice_blacklist', $this, $this->cache, $this->strIosVoiceBlacklistCachePre, $this->intCacheExpired, $this->arrTimeInfo['ios_voice_blacklist'], $arrUserPostInfo['ios_voice_blacklist']['version']);
            $arrNoti['message_time']['ios_voice_blacklist'] = $intCurrentTime;
        } else {
            $arrNoti['message_time']['ios_voice_blacklist'] = $this->arrTimeInfo['ios_voice_blacklist'];
        }
        
        //===============控制字段下发 Edit by fanwenli, 2018-12-28===============
        NotiNotifyFieldControl::getNotifyFieldInfo($arrNoti);
        
        //Edit by fanwenli on 2019-10-18, 华为误触开关, 0: 关闭 1: 打开
        $arrNoti['huawei_touch_mistake_switch'] = isset($arrUnifyConf['huawei_touch_mistake_switch']) ? $arrUnifyConf['huawei_touch_mistake_switch'] : 0;
        
        //===============接入通知中心新接口NotiV2 Edit by fanwenli, 2018-05-10===============
        $notiV2 = IoCload('NotiV2');
        $notiDataV2 = $notiV2->getNoti($arrUserPostInfo, $strPlatform, $strVersion, $intSp);
        if(is_array($notiDataV2['data']) && !empty($notiDataV2['data'])) {
            //cover old data with new one if there have any return data
            foreach($notiDataV2['data'] as $strV2Key => $strV2Value) {
                $arrNoti[$strV2Key] = $strV2Value;
            }
        }

        $arrNoti['data_switch'] = NotiDataSwitch::getNoti(NotiDataSwitch::RESOURCE, $this, $this->cache, $this->strNotiDataSwitchCachePre, $this->intCacheExpired);


        //qm sdk开关
        $arrNoti['qm_sdk_switch'] = isset($arrUnifyConf['qm_sdk_switch'])?$arrUnifyConf['qm_sdk_switch'] : 1;

        //mtj上传数据开关
        $arrNoti['mtj_switch'] = isset($arrUnifyConf['mtj_switch'])?$arrUnifyConf['mtj_switch'] : 1;
        
        return $arrNoti;
	}
	
	private function phasterThreadsJoin(){
	    foreach ($this->phasterThreadPool as $phasterthread){
	        $phasterthread->join();
	    }
	}



    
}

/**
 * Class ApcRedisCache 实现一个二级缓存类在通知中心临时使用
 * todo 之后可以优化为对接binder服务
 */
class ApcRedisCache
{
    /**
     * @var apc缓存
     */
    private $apccache;

    /**
     * @var apc 缓存时间
     */
    private $apcttl;


    /**
     * ApcRedisCache constructor 构造函数
     * @param $apccache
     * @param $rediscache
     */
    function __construct($apccache)
    {
       $this->apccache = $apccache;
       $this->apcttl = GFunc::getCacheTime("10mins");
    }

    /**
     * @param $name
     * @param bool $succeededd
     */
    public function get($name, &$succeeded = true,$add_cache_version_prefix = false)
    {
        $name = $name."_ads";
        $apcdata = $this->apccache->get($name, $succeeded);
        if(null !== $apcdata)
        {
            return $apcdata;
        }
        else
        {
            $redisdata = GFunc::cacheZget($name,$add_cache_version_prefix);
            if(false !== $redisdata)
            {
                $this->apccache->set($name, $redisdata, $this->apcttl);
                return $redisdata;
            }
            else
            {
                return null;
            }
        }
    }

    /**
     * @param $name
     * @param $var
     * @param int $ttl
     * @param bool $add_cache_version_prefix
     */
    public function set($name, $var, $ttl = 0,$add_cache_version_prefix = false)
    {
        $name = $name."_ads";

        $this->apccache->set($name, $var, $this->apcttl);

        GFunc::cacheZset($name, $var, $ttl ,$add_cache_version_prefix );
    }


}
