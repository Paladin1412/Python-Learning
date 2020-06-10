<?php
/**
 * ocp模式广告
 * User: chendaoyan
 * Date: 2019/3/19
 * Time: 12:06
 */

use utils\GFunc;
use utils\LogHelper;
use utils\Util;

/**
 * 完全访问
 * @path("/OcpAd/")
 */
class OcpAd {
    /**
     * @var cache object
     */
    protected $objRedis = null;
    /**
     *ocp cache pre
     */
    const CACHE_OCP_PREFIX = 'cache_ocp_info_';

    /**
     * @var null
     */
    private $_objFetchUrl = null;

    /**
     * OcpAd constructor.
     */
    public function __construct() {
        $this->objRedis = GFunc::getCacheInstance();
        $this->_objFetchUrl = \Orp_FetchUrl::getInstance(3000);
    }

    /**
     * @desc 监测接口
     * http://agroup.baidu.com/inputserver/md/article/1749157
     * @route({"GET", "/monitor/*"})
     * @param({"id", "$.path[2]"}) string 代理id，v5后台配置后生成的id
     * @return({"body"})
     */
    public function monitor($id='') {
        $imei = $_GET['imei'];
        $androidId = $_GET['androidid'];
        $mac = $_GET['mac'];
        $ip = $_GET['ip'];
        $ua = $_GET['ua'];
        if (empty($imei)) {
            LogHelper::getInstance()->Log('toutiao callback imei empty');
        } else if (empty($id)) {
            LogHelper::getInstance()->Log('toutiao callback id empty');
            echo 'params error';
            exit();
        } else if (empty($androidId)) {
            LogHelper::getInstance()->Log('toutiao callback androidid empty');
        } else if (empty($mac)) {
            LogHelper::getInstance()->Log('toutiao callback mac empty');
        } else if (empty($ip)) {
            LogHelper::getInstance()->Log('toutiao callback ip empty');
        } else if (empty($ua)) {
            LogHelper::getInstance()->Log('toutiao callback ua empty');
        }
        $callbackUrl = !empty($_GET['callback_url']) ? $_GET['callback_url'] : $_GET['callback'];
        //从资源服务获取渠道号
        $searchStr = urlencode('{"id":' . $id . '}');
        $resUrl = '/res/json/input/r/online/ocp_channel/?onlycontent=1&search=' . $searchStr;
        $resKey = md5(self::CACHE_OCP_PREFIX . $resUrl);
        $ocpChannelRes = Util::ralGetContent($resUrl, $resKey, Gfunc::getCacheTime('10mins'));
        if ($ocpChannelRes) {
            $ocpChannelResVal = current($ocpChannelRes);
        } else {
            echo 'error';
            exit();
        }

        //从数据库获取数据
        $key = md5(self::CACHE_OCP_PREFIX . $imei . $callbackUrl . $id);
        $selectRes = $this->objRedis->get($key);
        if (!$selectRes) {
            //到数据库查看
            $ocpModel = IoCload('models\\OcpModel');
            $time = date('Y-m-d H:i:s', strtotime(date('Y-m-d', strtotime("-7 days"))));
            $condition = "create_time>='$time' and (imei_md5='$imei' or android_id='$androidId' or ip_ua='$ip$ua' or mac='$mac')";
            $appends = array(
                'ORDER BY create_time ASC'
            );
            $selectRes = $ocpModel->select('*', $condition, null, $appends);
            if (!$selectRes) {
                echo 'error';
                exit();
            } else {
                $selectRes = $selectRes[0];
                $this->objRedis->set($key, $selectRes, GFunc::getCacheTime('30mins'));
            }
        }

        //渠道号在我们的列表里才发送callback请求
        $response = '';
        if (in_array($selectRes['from_channel'], $ocpChannelResVal['channel_name'])) {
            $callbackRes = $this->_objFetchUrl->get($callbackUrl);
            $key = self::CACHE_OCP_PREFIX . 'ocp_callback';
            $this->objRedis->hIncreby($key, $selectRes['from_channel'], 1); //回调记录日志
            LogHelper::getInstance()->Log($selectRes['from_channel'] . ' toutiao callback ' . $callbackUrl . ' response:' . $callbackRes);
            if (1 == $ocpChannelResVal['name'] || 2 == $ocpChannelResVal['name']) { //头条抖音返回值
                $response = 'success';
            } else if (3 == $ocpChannelResVal['name']) { //快手返回值
                $response = 'result=1';
            }
        } else {
            if (1 == $ocpChannelResVal['name'] || 2 == $ocpChannelResVal['name']) { //头条抖音返回值
                $response = 'error';
            } else if (3 == $ocpChannelResVal['name']) { //快手返回值
                $response = 'result=0';
            }
        }

        echo $response;
        exit();
    }

}