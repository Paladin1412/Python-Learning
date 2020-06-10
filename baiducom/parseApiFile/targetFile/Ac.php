<?php

use utils\DbConn;
use utils\GFunc;
use utils\Util;
require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';
/**
 * 完全访问
 * @path("/ac/")
 */
class Ac
{

    /**
     * 激活过期时长(单位: 秒)
     * @var int
     */
    const IDFA_ACTIVE_EXPIRED_TIME = 10800;
    /**
     * 单个渠道推广上限(单位: 秒)
     * @var int
     */
    const MAX_AC_COUNT = 20000;
    /**
     * cache channel info key prefix
     */
    const CACHE_CHANNEL_INFO_PREFIX = 'idfa_status_channel_info_';
    /**
     * 缓存默认过期时间(单位: 秒)
     *
     */
    const CACHE_EXPIRED_TIME = 600;

    /**
     * @desc 下发包列表数据
     * @route({"GET", "/n"})
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"idfa", "$._GET.idfa"})
     * @param({"imei", "$._GET.imei"}) 用户imei
     * @param({"from", "$._GET.from"})
     * @param({"xd", "$._GET.xd"})
     * @param({"mc", "$._GET.mc"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function n($plt = 'i1', $idfa = '', $imei='', $from='', $xd='', $mc='')
    {
        //实时记录指定渠道号的激活数据
        $resUrl = '/res/json/input/r/online/ocp_channel/?onlycontent=1';
        $cacheKey = md5(self::CACHE_CHANNEL_INFO_PREFIX . __Class__ . __FUNCTION__ . '_cachekey' . $resUrl);
        $channelInfo = Util::ralGetContent($resUrl, $cacheKey);
        if ($channelInfo && is_array($channelInfo)) {
            $channel = array();
            foreach ($channelInfo as $channelInfoK => $channelInfoV) {
                $channel = array_merge($channel, $channelInfoV['channel_name']);
            }
            if (!empty($from) && in_array($from, $channel)) {
                $strXd = bd_B64_Decode($xd, 0);
                $arrXd = explode('_', $strXd);
                $androidId = !empty($arrXd[0]) ? $arrXd[0] : '';
                if (empty($imei) && !empty($arrXd[1])) {
                    $imei = $arrXd[1];
                }
                $ip = Util::getClientIP();
                $ua = $_SERVER['HTTP_USER_AGENT'];

                $objRedis = IoCload('utils\\KsarchRedis');
                //记录对应激活数
                $date = date('Y-m-d', time());
                $channelKey = self::CACHE_CHANNEL_INFO_PREFIX . '_ocp_channel_' . $date;
                $objRedis->hIncreby($channelKey, $from, 1);
                $objRedis->expire($channelKey, 86400);

                if (!empty($imei) || !empty($androidId) || !empty($mc) || (!empty($ip) && !empty($ua))) {
                    //写数据库
                    $data = array(
                        'imei' => $imei,
                        'imei_md5' => !empty($imei) ? md5($imei) : '',
                        'from_channel' => $from,
                        'create_time' => date('Y-m-d H:i:s'),
                        'android_id' => !empty($androidId) ? md5($androidId) : '',
                        'mac' => !empty($mc) ? md5($mc) : '',
                        'ip_ua' => $ip . $ua,
                    );
                    $ocpModel = IoCload('models\\OcpModel');
                    $insertRes = $ocpModel->insert($data);
                    if (!$insertRes) {
                        sleep(1);
                        $ocpModel->insert($data);
                    }
                }
            }
        }


        $result = array('status' => 0,'actime'=>time(), );

        if ($idfa == "00000000-0000-0000-0000-000000000000")
        {
            $result['channel'] = '1019838c';

            return $result;
        }


        list($channel, $idfaInfo) = $this->saveIdfaStatus($plt, $idfa);

        if($channel)
        {
            $this->saveChannelCallBack($idfa, $channel, $idfaInfo);
            $result['channel'] = $channel;
        }

        return $result;
    }

    /**
     * @desc 下发包列表
     * @route({"GET", "/mc"})
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"idfa", "$._GET.idfa"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function mc($plt = 'i1', $idfa = '')
    {
        $result = array('status' => 0,'actime'=>time());

        return $result;
    }


    /**
     * 保存idfa状态信息并返回激活渠道
     *
     * @param $arrIdfaInfo idfa info
     *
     * @return string 返回渠道信息
     *
     */
    public function saveIdfaStatus($plt, $idfa)
    {
        $channel = '';
        $info = array();
        if ( $idfa && (substr($plt, 0, 1) === 'i') )
        {
            $model = IoCload("models\\IdfaModel");
            $resData = $model->findByUid($idfa);
            $resData = !empty($resData) ? $resData : array();

            if ($resData && $resData['id'])
            {
                if($resData['status'] == '1')
                {
                    return array($resData['channel'], $resData);
                }

                $resData['status'] = '1';
                $resData['active_time'] = time();
                //如果没有渠道信息则设置为缺省渠道
                $resData['channel'] = isset($resData['channel'])? $resData['channel'] : '1009309e';

                //没有跳转时间或激活时间同跳转时间差超过3小时则返回默认渠道
                if( !isset($resData['jump_time'])
                    || ($resData['active_time'] - $resData['jump_time'] >= self::IDFA_ACTIVE_EXPIRED_TIME) ){
                    //超过3小时记录
                    $resData['active_timeout'] = 1;
                    $channel = '1019838a';
                }
                else
                {
                    $resData['active_timeout'] = 0;
                    $channel = $resData['channel'];
                }

                $model->updateTo($resData);

                $info = $resData;
            }
            else
            {
                //没有查询muid 广点通 渠道
                //没有则插入
                $resData = array('status' => '1', 'active_time' => time());

                $muid = md5(strtoupper($idfa));
                $muidModel = IoCload("models\\MuidModel");
                $muidData = $muidModel->findByUid($muid);

                $muidData = !empty($muidData) ? $muidData : array();

                if ($muidData)
                {
                    $resData = $muidData;
                    $resData['status'] = 1;
                    $resData['active_time'] = time();
                    $channel = $muidData['channel'];

                    $resData['is_gdt'] = 1;

                    $muidModel->updateTo($resData);
                }

                $resData['uid'] = $idfa;

                $model->insertTo($resData);
                $info = $resData;
            }
        }

        return array($channel, $info);
    }

    /**
     * @desc 生成不回调rand列表
     * @param $intFactor 回调因子
     * @return array
     */
    private function genRandNotCallback($intFactor){
        $arrNotCallback = array();
        $intPick = 100 - $intFactor;
        $intLoop = self::MAX_AC_COUNT / 100;
        for($i = 1; $i <= $intLoop; $i++){
            $arrRange = range(100*$i - 99, 100*$i);
            $arrTmpNotCallback = array_rand($arrRange, $intPick);
            foreach($arrTmpNotCallback as $pos){
               array_push($arrNotCallback, $arrRange[$pos]);
            }
        }

        return $arrNotCallback;
    }

    /**
     * @desc 获取不回调列表
     * @param $strFrom 渠道
     * @param $intFactor 回调因子
     * @return array
     */
    private function getNotCallback($strFrom, $intFactor)
    {
        $intFactor = intval($intFactor);
        $arrNotCallback = array();
        if($intFactor >= 100){
            return $arrNotCallback;
        }
        $strDate = date("Ymd");
        $strKey = self::CACHE_CHANNEL_INFO_PREFIX . $strFrom . $strDate;
            //迁移v5直接访问KsarchRedis
        $redis = GFunc::getCacheInstance();
        $strNotCallback = $redis->get($strKey, $success);
        if(null == $strNotCallback){
            //没有取到情况,认为是该渠道当天第一个用户需要生成非回调列表
            $arrNotCallback = $this->genRandNotCallback($intFactor);
            //迁移v5直接访问KsarchRedis
            $redis->set($strKey, json_encode($arrNotCallback), 86400);
        }else{
            $arrNotCallback = json_decode($strNotCallback, true);
        }

        return $arrNotCallback;
    }

        /**
     * 获取渠道信息
     *
     * @param $strFrom 渠道
     *
     * @return array
     */
    private function getChannelInfo($strFrom)
    {
        $redis = GFunc::getCacheInstance();
        //$adaptor = DbConn::getMongoResAdaptor();

        $strCacheKey = self::CACHE_CHANNEL_INFO_PREFIX . $strFrom;

        $arrResult = $redis->get($strCacheKey, $success);

        if(!$arrResult)
        {
            $arrSearch = array();
            $arrSearch['channel'] = $strFrom;
            $strSearch = 'search=' . urlencode(json_encode($arrSearch));
            $path = '/res/json/input/r/online/resource-channelInfo/?' . "onlycontent=1&" . $strSearch;;
            // $arrSearch = array();
            // $arrSearch['content.channel'] = $strFrom;
            // $res = $adaptor->get($path, $arrSearch);
            $res = $this->getFromRes($path);
            $res && $res = array_values($res);
            $res = !empty($res[0]) ? $res[0] : array();

            if ($res)
            {
                $arrResult = $res;
                $redis->set($strCacheKey, $arrResult, self::CACHE_EXPIRED_TIME);
            }
        }

        return $arrResult;
    }

    /**
     *
     * 请求渠道callback地址，获得结果并记录，优先使用跳转地址callback，然后使用渠道信息里的callback
     *
     * @param $arrIdfaInfo idfa info
     * @param $channel channel
     *
     */
    private function saveChannelCallBack($idfa, $channel, $arrIdfaInfo)
    {
        $redis = GFunc::getCacheInstance();
        $model = IoCload("models\\IdfaModel");
        $strJpCallback = isset($arrIdfaInfo['callback'])? $arrIdfaInfo['callback'] : '';
        $bolCallback = true;

        $arrgetChannelInfo = $this->getChannelInfo($channel);

        if (false !== $arrgetChannelInfo && isset($arrgetChannelInfo['call_back_factor'])) {
            $arrNotCallback = $this->getNotCallback($channel, $arrgetChannelInfo['call_back_factor']);

            $strKey = self::CACHE_CHANNEL_INFO_PREFIX . $channel . date("Ymd") . 'incr';
            $intCurrent = $redis->incr($strKey, $success);

            $bolCallback = !in_array($intCurrent, $arrNotCallback);
        }

        $arrIdfaInfo['is_callback'] = $bolCallback? 1:0;

        $orpFetchUrl = new \Orp_FetchUrl();
        $httpproxy = $orpFetchUrl->getInstance(array('timeout' =>2000));
        if ($arrIdfaInfo['is_gdt'] == 1)
        {
            if ($bolCallback) {
                $callBackUrl = $this->_getGdtUrl('916139408','BAAAAAAAAAAANVWb','7c22615f64d5729b',
                    '3495323','MOBILEAPP_ACTIVITE', 'IOS', $arrIdfaInfo['click_id'], $arrIdfaInfo['muid']);
                $strTraceUrl = GFunc::getGlobalConf('domain_v5') . '/v5/trace?url=' . urlencode($callBackUrl) . '&sign=' . md5($callBackUrl . 'iudfu(lkc#xv345y82$dsfjksa').'&inputgd=1' . "&from=$channel&idfa={$idfa}";
                $result = $httpproxy->get($strTraceUrl);
                $err = $httpproxy->errmsg();
                if (!$err && $httpproxy->http_code() == 200) {
                    $arrIdfaInfo['callback_result'] = 1;
                }else{
                    $arrIdfaInfo['callback_result'] = 0;
                }
            }

            $model->updateTo($arrIdfaInfo);
        }
        elseif ('' !== $strJpCallback)
        {
            if($bolCallback) {
                $callBackUrl = $strJpCallback . '&idfa=' . $idfa;
                $strTraceUrl = GFunc::getGlobalConf('domain_v5') . '/v5/trace?url=' . urlencode($callBackUrl) . '&sign=' . md5($callBackUrl . 'iudfu(lkc#xv345y82$dsfjksa').'&inputgd=1' . "&from=$channel&idfa=$idfa";
                $result = $httpproxy->get($strTraceUrl);
                $err = $httpproxy->errmsg();
                if (!$err && $httpproxy->http_code() == 200) {
                    $arrIdfaInfo['callback_result'] = 1;
                }else{
                    $arrIdfaInfo['callback_result'] = 0;
                }
            }

            $model->updateTo($arrIdfaInfo);
        }else{

            //channel 里的callback信息
            //"callback": "http://ios.api.i4.cn/appactivatecb.xhtml?aisicid=100001&aisi=1234&appid=appid& mac=mac&idfa=${idfa}&os=os",
            if(false !== $arrgetChannelInfo && isset($arrgetChannelInfo['callback']) && '' !== trim($arrgetChannelInfo['callback']) &&
                    (time() > intval($arrgetChannelInfo['begin_time'])) && (time() < intval($arrgetChannelInfo['end_time'])) ){
                if($bolCallback){
                    //替换idfa
                    $callBackUrl = str_replace('${idfa}', $idfa, $arrgetChannelInfo['callback']);
                    $strTraceUrl = GFunc::getGlobalConf('domain_v5') . '/v5/trace?url=' . urlencode($callBackUrl) . '&sign=' . md5($callBackUrl . 'iudfu(lkc#xv345y82$dsfjksa').'&inputgd=1' . "&from=$channel&idfa=$idfa";
                    $result = $httpproxy->get($strTraceUrl);
                    $err = $httpproxy->errmsg();
                    if (!$err && $httpproxy->http_code() == 200) {
                        $arrIdfaInfo['callback_result'] = 1;
                    }else{
                        $arrIdfaInfo['callback_result'] = 0;
                    }
                }

                $model->updateTo($arrIdfaInfo);
            }
        }

    }

    /**
     * 获取广点通上报URL
     * @date   2016-08-11
     * @param  [type]     $app_id      [description]  android 应用为开放平台移动应用的 id,或者 ios 应用在 Apple App Store 的 id
     * @param  [type]     $encrypt_key [description]  加密密钥 encrypt_key
     * @param  [type]     $sign_key    [description]  签名密钥 sign_key
     * @param  [type]     $uid         [description]  广告主 ID(必选)
     * @param  [type]     $conv_type   [description]  转化类型(必选)
     * @param  [type]     $app_type    [description]  转化应用类型(必选) ios 应用取值为 IOS,Android 应用取值为 ANDROID
     * @param  [type]     $click_id    [description]  广点通后台生成的点击 id,广点通系统中标识用户每次点击生成的唯一标识
     * @param  [type]     $muid        [description]  用户设备的 IMEI 或 idfa 进行 MD5SUM 以后得到的 32 位全小写 MD5 表现字符串
     * @return [type]                  [description]
     */
    private function _getGdtUrl($app_id, $encrypt_key, $sign_key, $uid, $conv_type, $app_type, $click_id, $muid){
        $conv_time    = time();
        $url          = 'http://t.gdt.qq.com/conv/app/' . $app_id . '/conv?';
        //参数拼接
        $query_string = "click_id=" . urlencode($click_id) . "&muid=" . urlencode($muid) . "&conv_time=" . urlencode($conv_time);
        //urlencode转化
        $encode_page  = urlencode($url . $query_string);
        //property
        $property     = $sign_key . '&GET&' . $encode_page;
        //md5加密
        $signature    = md5($property);
        //base_data
        $base_data    = $query_string . "&sign=" . urlencode($signature);
        //通过base_data和enctype_type进行异或
        $data         = urlencode($this->_SimpleXor($base_data, $encrypt_key));
        //组装
        $attachment   = "conv_type=" . urlencode($conv_type) . '&app_type=' . urlencode($app_type) . '&advertiser_id=' . urlencode($uid);
        //最终的拼接
        $lastUrl      = $url . "v=" . $data . "&" . $attachment;

        return $lastUrl;
    }


    /**
     * 简单异或
     * @date   2016-08-08
     * @param  [type]     $data [description]
     * @param  [type]     $key  [description]
     * @return [type]           [description]
     */
    private function _SimpleXor($data,$key){
        $str   = '';
        $len   = strlen($data);
        $len2  = strlen($key);
        for($i = 0; $i < $len; $i ++){
            $j = $i % $len2;
            $str .= ($data[$i]) ^ ($key[$j]);
        }
        return base64_encode($str);
    }

    /**
     * [getFromRes description]
     * @param  [type] $path [description]
     * @return [type]       [description]
     */
    private function getFromRes($path)
    {
        $arrData = null;
        $host = GFunc::getGlobalConf('domain_res');
        $path = $host . $path;

        $arrResult = Util::request($path, 'GET', null , 3);

        if (isset($arrResult['http_code']) && intval($arrResult['http_code']) === 200 && isset($arrResult['body']))
        {
            $arrBody = json_decode($arrResult['body'], true);
        }

        return $arrBody;
    }

    /**
     * [putToRes description]
     * @param  [type] $path    [description]
     * @param  [type] $arrData [description]
     * @return [type]          [description]
     */
    private function putToRes($path, $arrData)
    {
        $host = GFunc::getGlobalConf('domain_res');
        $path = $host . $path;
        $arrHeader = array("Content-Type: application/json");
        $arrResult = Util::request($path, 'PUT', $arrData, 3, $arrHeader);

        return true;
    }

    /**
     * [postToRes description]
     * @param  [type] $path    [description]
     * @param  [type] $arrData [description]
     * @return [type]          [description]
     */
    private function postToRes($path, $arrData)
    {
        $host = GFunc::getGlobalConf('domain_res');
        $path = $host . $path;
        $arrHeader = array("Content-Type: application/json");
        $arrResult = Util::request($path, 'POST', $arrData, 3, $arrHeader);

        return true;
    }


}