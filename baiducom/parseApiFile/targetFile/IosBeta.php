<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 */
/**
 * @author zhangqinyang(zhangqinyang@baidu.com)
 * @desc IOS内测接口
 * @path("/iosbeta/")
 */

use utils\GFunc;
use utils\Util;

class IosBeta{
	
    //消息类型
    CONST IOS_BETA_UPGRADE_MSGTYPE = 'ad_ios_beta';
    
    /** @property 内部缓存实例 */
	private $cache;
    
    /** @property 资源服务URL */
    private $resource_url;
    
    /** @property 静态资源URL */
    private $resource_manage_url;
    
    /** @property BCS_BUCKET */
    private $bcs_bucket;
    
    /** @property BCS_AK */
    private $bcs_ak;
    
    /** @property BCS_SK */
    private $bcs_sk;
    
    /** @property BCS_HOST_NAME */
    private $bcs_host_name;
    
    //bcs 对象
    private static $bcs_obj;

    /**
	 * @desc 返回IOS内测当前版和最新版本信息
	 * @route({"GET", "/"})
	 * @param({"version", "$._GET.version"}) IOS内测版本号
	 * @return({"body"})返回IOS内测版本信息
     * 数据格式如下：
     * {
     *      data: {
     *          has_new: true,
     *          current: {
     *              title: "IOS内测抢先版",
     *              content: "IOS内测抢先版",
     *              version: "5.9.0.1",
     *              max_remind: 3,
     *              install_link: "http://www.baidu.com",
     *              expire_time: 1438300800,
     *              expire_title: "版本已失效，请升级版本",
     *              expire_content: "版本已失效，请升级版本"
     *          },
     *          new: {
     *              force_update: true,
     *              title: "IOS抢先体验版",
     *              content: "IOS抢先体验版",
     *              version: "5.9.0.2",
     *              max_remind: 1,
     *              install_link: "http://www.baidu.com",
     *              start_time: 1435708800,
     *              expire_time: 1438300800,
     *              expire_title: "该版本已过期，请升级到最新版本",
     *              expire_content: "该版本已过期，请升级到最新版本",
     *              buffer_day: 3,
     *              buffer_start_title: "还有3天缓冲时间，请升级到最新版本",
     *              buffer_start_content: "还有3天缓冲时间，请升级到最新版本",
     *              buffer_end_title: "该版本已经无法正常使用，请升级到最新版本",
     *              buffer_end_content: "该版本已经无法正常使用，请升级到最新版本"
     *          }
     *      }
     *  }
	 */
	public function index($version){
	    $key = __CLASS__ . __METHOD__ . $version;
        $result = GFunc::cacheGet($key);
        if (false === $result) {
            $version = addslashes(strip_tags($version));
            $version_value = $this->getVersionIntValue($version);
            $last_ios_beta = $this->getLastIosBeta();
            $ios_beta_info = $this->getIosBetaByVersion($version);

            $has_new = false; //是否有最新版本
            $current = array(); //当前版本信息
            $new = array(); //最新版本信息
            if ($ios_beta_info){
                $current = array(
                    'title' => $ios_beta_info['beta_title'],
                    'content' => $ios_beta_info['beta_content'],
                    'version' => $ios_beta_info['version'],
                    'max_remind' => $ios_beta_info['beta_max_remind'],
                    'install_link' => $ios_beta_info['install_link'] . urlencode($ios_beta_info['plist_url']),
                    'expire_time' => $ios_beta_info['expire_time'],
                    'expire_title' => $ios_beta_info['expire_title'],
                    'expire_content' => $ios_beta_info['expire_content'],
                );
            }

            if ($last_ios_beta && $this->getVersionIntValue($last_ios_beta['version']) > $version_value)
            {
                $has_new = true;
                $new = array(
                    'force_update' => $last_ios_beta['force_update'],
                    'title' => $last_ios_beta['beta_title'],
                    'content' => $last_ios_beta['beta_content'],
                    'version' => $last_ios_beta['version'],
                    'max_remind' => $last_ios_beta['beta_max_remind'],
                    'install_link' => $last_ios_beta['install_link'] . urlencode($last_ios_beta['plist_url']),
                    'start_time' => $last_ios_beta['start_time'],
                    'expire_time' => $last_ios_beta['expire_time'],
                    'expire_title' => $last_ios_beta['expire_title'],
                    'expire_content' => $last_ios_beta['expire_content'],
                );

                if ($last_ios_beta['buffer_day'])
                {
                    $new['buffer_day'] = $last_ios_beta['buffer_day'];
                    $new['buffer_start_title'] = $last_ios_beta['buffer_start_title'];
                    $new['buffer_start_content'] = $last_ios_beta['buffer_start_content'];
                    $new['buffer_end_title'] = $last_ios_beta['buffer_end_title'];
                    $new['buffer_end_content'] = $last_ios_beta['buffer_end_content'];
                }
            }

            $result = array(
                "data" => array(
                    'has_new' => $has_new,
                    'current' => $current,
                    'new' => $new,
                    'refresh_interval' => 3600 * 6,
                ),
            );

            GFunc::cacheSet($key, $result, Gfunc::getCacheTime('10mins'));
        }

        return $result;
	}
    
    /**
     * 返回最新IOS测试版本 add by zqy 2015-07-14
     * 
     * @return array
     */
    protected function getLastIosBeta(){

        $message_key = self::IOS_BETA_UPGRADE_MSGTYPE;
        $now = time();
        $search_conditions = sprintf('{"content.class":"%s", "content.%s.start_time":{"$lte":%d}, "content.%s.expire_time":{"$gt":%d}}', $message_key, $message_key, $now, $message_key, $now);
        $ios_beta = $this->getNotiInfo($message_key, $now, $search_conditions, 0, urlencode('{"content.'.$message_key.'.version": -1}'));
        $last_ios_beta = $this->getIosBetaLastVersion($ios_beta['info']);
        return $last_ios_beta;
    }
    
    /**
     * 按照版本值排序
     * @param type $ios_beta_list
     * @return array
     */
    public function getIosBetaLastVersion($ios_beta_list)
    {
        $last_ios_beta = array();
        if ($ios_beta_list && is_array($ios_beta_list))
        {
            foreach ($ios_beta_list as &$ios_beta)
            {
                $ios_beta['ver_value'] = $this->getVersionIntValue($ios_beta['version']);
            }
            
            usort($ios_beta_list, function($a, $b){
                if ($a['ver_value'] == $b['ver_value'])
                {
                    return 0;
                }
                return ($a['ver_value'] < $b['ver_value'] ? 1 : -1);
            });
            $last_ios_beta = $ios_beta_list[0];
        }
        return $last_ios_beta;
    }
    
    /**
     * 根据版本返回IOS内测详细信息 add by zqy 2015-07-14
     * 
     * @param type $version
     * @return array
     */
    protected function getIosBetaByVersion($version)
    {
        $message_key = self::IOS_BETA_UPGRADE_MSGTYPE;
        $now = time();
        $search_conditions = sprintf('{"content.class":"%s", "content.%s.version": "%s"}', $message_key, $message_key, $version);
        $ios_beta = $this->getNotiInfo($message_key, $now, $search_conditions);
        $ios_beta = $ios_beta['info'][0];
        return $ios_beta;
    }
    
    /**
	 * 返回输入法版本数值
	 * 如5.1.1.5 5010105
	 * 
	 * @param
	 * 		参数名称：$version_name
	 *      是否必须：是
	 *      参数说明：version name
	 *
	 * @param
	 * 		参数名称：$digit
	 *      是否必须：是
	 *      参数说明：位数
	 *
	 *
	 * @return string
	 */
	protected function getVersionIntValue($version_name, $digit = 4){
		$version_name = str_replace('-', '.', $version_name);
		
		$val = 0;
		$verson_digit = explode('.', $version_name);
		for ($i = 0; $i < $digit; $i++){
			$digit_val = 0;
			switch ($i){
				case 0:
					$digit_val = intval($verson_digit[$i]) * 1000000;
					break;
				case 1:
					$digit_val = intval($verson_digit[$i]) * 10000;
					break;
				case 2:
					$digit_val = intval($verson_digit[$i]) * 100;
					break;
				case 3:
					$digit_val = intval($verson_digit[$i]);
					break;
				default:
					break;
			}
			
			$val = $val + $digit_val;
		}
		
		return $val;
	}
    
    /**
     * 获取通知消息
     * @param $message_key 消息key
     * @param $last_message_time int 启动屏幕消息版本
     * @param $search 资源服务查询条件，如果没有则使用默认查询条件
     * @param $msg_ver 消息版本
     * @param $sort 排序
     * 
     * @return array
     *
     * 有消息, 返回对应的消息
     * 无消息, 返回空数组
     */
    public function getNotiInfo($message_key, $last_message_time, $search = null, $msg_ver = 0, $sort = '') {
    	//默认返回信息
    	$message = array();
    	$message['lastmodify'] = $last_message_time;
    	$message['info'] = array();
    	 
    	if(null === $search){
    		$search = sprintf('{"update_time":{"$gt":%d}, "content.class":"%s", "content.%s.expired_time":{"$gt":%d}, "content.%s.noti_id":{"$gt":%d}}', $last_message_time, $message_key, $message_key, time(), $message_key, $msg_ver);
    	}
    	$url = $this->resource_url . '/res/json/input/r/online/notify/?search=' . urlencode($search) . '&onlycontent=1&searchbyori=1';
    	if ($sort){
            $url .= '&sort=' . $sort;
        }
        
        $header = array(
            'pathinfo' => '/res/json/input/r/online/notify/',
            'querystring' => array(
                "search" => urlencode($search),
                'onlycontent' => 1,
                'searchbyori' => 1,
            ),
        );
        
        //如果排序不为空，则添加排序规则
        if ($sort){
            $header['querystring']['sort'] = $sort;
        }
        
        $header['querystring'] = http_build_query($header['querystring']);
        $result = ral('res_service', 'get', null, rand(), $header);
        if(false === $result){
            return false;
        }
        
        foreach($result as $val){
            $message['info'][] = $val[$message_key];
        }
    	
    	return $message;
    }
    
    /**
     * @desc 上传IOS内测ipa并返回Plist地址
	 * @route({"POST", "/plist/"})
	 * @param({"data", "$._POST.data"}) IOS内测版本信息
     * @return({"body"})内测版本信息
     * 数据格式如下
     * {
     *  "class": "ad_ios_beta",
     *  "ad_ios_beta": {
     *      "noti_id": 74,
     *      "title": "zzzz",
     *      "content": "zzzzzz",
     *      "version": "5.8.0.5",
     *      "max_remind": 1,
     *      "install_link": "itms-services://?action=download-manifest&url=",
     *      "ipa_url": "https://bs.baidu.com/emot-pack-test/notify/files/143746933445721.ipa",
     *      "is_orient": "1",
     *      "cuid": "",
     *      "start_time": 1435708800,
     *      "expire_time": 1438300800,
     *      "expire_title": "zzzzz",
     *      "expire_content": "zzzzzz",
     *      "plist_title": "zzzzzz",
     *      "plist_sub_title": "zzzzzzz",
     *      "plist_logo": "http://cq02-mic-iptest.cq02.baidu.com:8891/res/file/notify/files/14374693617134.png",
     *      "bundle_identifier": "zzzz",
     *      "force_update": false,
     *      "plist_url": "https://bs.baidu.com/emot-pack-test/ios_beta_1437469423.plist"
     *    }
     * }
     */
    public function plist($data){
        $data = json_decode($data, true);
        $class = $data['class'];
        ini_set('memory_limit', '256M');
        /*
        $data = array(
            "version" => "5.9.0.2",
            "plist_title" => "BAIDU_INPUT",
            "plist_sub_title" => "BAIDU_INPUT",
            "plist_logo" => "http://www.baidu.com",
            "install_link" => "http://www.baidu.com"
        );
         * 
         */
        $version = $data[$class]['version'];
        $plist_title = $data[$class]['plist_title'];
        $plist_sub_title = $data[$class]['plist_sub_title'];
        $bundle_identifier = $data[$class]['bundle_identifier'];
        $plist_logo = $data[$class]['plist_logo'];
        $ipa_url = $data[$class]['ipa_url'];
        $ipa_url = $this->setIpaURl($ipa_url);
        $plist_content = $this->getPlist($version, $plist_title, $plist_sub_title, $bundle_identifier, $plist_logo, $ipa_url);
        $plist_object_name = '/ios_beta_' . time() . ".plist";
        $bcs_obj = $this->getBcsObject();
        $bcs_obj->create_object_by_content($this->bcs_bucket, $plist_object_name, $plist_content);
        $data[$class]['plist_url'] = $this->getBcsUrl($plist_object_name);
        $data[$class]['ipa_url'] = $ipa_url;
        header("Access-Control-Allow-Origin: *"); //允许所有接口访问
        header("Content-Type: application/json");
        return $data;
    }
    
    /**
     * 静态资源地址替换为BCS资源地址
     * @param type $ipa_url
     * @return string 
     * bcs地址
     */
    protected function setIpaURl($ipa_url)
    {
        return str_replace($this->resource_manage_url . '/res/file', 'https://' . $this->bcs_host_name . '/' . $this->bcs_bucket, $ipa_url);
    }

    /**
     * 返回bcs地址
     * @param type $object_name
     * @return string 
     * url BCS地址
     */
    protected function getBcsUrl($object_name)
    {
        return 'https://' . $this->bcs_host_name . '/' . $this->bcs_bucket . $object_name;
    }

    /**
     * 返回bcs object
     * 
     * @return bcs object 
     */
    protected function getBcsObject()
    {
        if (self::$bcs_obj == null)
        {
            require_once __DIR__ . '/utils/Bcs.php';
            $bcs_obj = new BaiduBCS($this->bcs_ak, $this->bcs_sk, $this->bcs_host_name);
            self::$bcs_obj = $bcs_obj;
        }
        return self::$bcs_obj;
    }

    /**
     *
     * @desc ios_test_flight
     *
     * @route({"GET", "/test_flight"})
     * @param({"version", "$._GET.message_version"}) 通知中心下发的时间戳
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function testFlight($version = 0) {
        $model = IoCload(\models\TestFlight::class);
        //输出格式初始化
        $rs = Util::initialClass();
        $rs['data'] = $model->getResource($version);

        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs,true,true);
    }

    /**
     *
     * @desc ios_test_flight 内测update
     *
     * @route({"GET", "/test_flight_update"})
     * @param({"version", "$._GET.message_version"}) 通知中心下发的时间戳
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function testFlightUpdate($version = 0) {
        $model = IoCload(\models\TestFlightUpdate::class);
        //输出格式初始化
        $rs = Util::initialClass();
        $rs['data'] = $model->getResource($version);

        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs,true,true);
    }

    /**
     *
     * @desc test flight 内测流程Url
     *
     * @route({"GET", "/test_flight_url"})
     * @param({"version", "$._GET.message_version"}) 通知中心下发的时间戳
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function testFlightUrl($version = 0) {
        $model = IoCload(\models\TestFlightUrl::class);
        //输出格式初始化
        $rs = Util::initialClass();
        $data = $model->getResource($version);
        $rs['data'] = empty($data) ? new stdClass() : $data;

        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs);
    }

    /**
     *
     * @desc test flight 内测流程Url
     *
     * @route({"GET", "/redirect_test_flight_url"})
     * @param({"version", "$._GET.message_version"}) 通知中心下发的时间戳
     * @return({"header", "Content-Type: text/html; charset=UTF-8"})
     */
    public function redirectTestFlightUrl($version = 0) {
        $model = IoCload(\models\TestFlightUrl::class);
        //输出格式初始化
        $data = $model->getResource($version);
        if(!isset($data['url'])) {
            return \utils\ErrorCode::returnError(\utils\ErrorCode::DB_ERROR);
        }

        header(sprintf('Location:%s', $data['url']));
    }

    /**
     * 返回plist内容
     * @param type $version 版本
     * @param type $plist_title plist标题
     * @param type $plist_sub_title plist子标题
     * @param type $bundle_identifier 标识符
     * @param type $plist_logo
     * @param type $ipa_url 下载地址
     * @return string 
     * plist内容
     */
    protected function getPlist($version, $plist_title, $plist_sub_title, $bundle_identifier, $plist_logo, $ipa_url)
    {
        $plist_content = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>items</key>
    <array>
        <dict>
            <key>assets</key>
            <array>
                <dict>
                    <key>kind</key>
                    <string>software-package</string>
                    <key>url</key>
                    <string>$ipa_url</string>
                </dict>
                <dict>
                    <key>kind</key>
                    <string>display-image</string>
                    <key>needs-shine</key>
                    <true/>
                    <key>url</key>
                    <string>$plist_logo</string>
                </dict>
            </array>
            <key>metadata</key>
            <dict>
                <key>bundle-identifier</key>
                <string>$bundle_identifier</string>
                <key>bundle-version</key>
                <string>$version</string>
                <key>kind</key>
                <string>software</string>
                <key>subtitle</key>
                <string>$plist_sub_title</string>
                <key>title</key>
                <string>$plist_title</string>
            </dict>
        </dict>
    </array>
</dict>
</plist>
EOF;
        return $plist_content;
    }

}