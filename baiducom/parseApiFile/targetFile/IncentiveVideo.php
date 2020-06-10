<?php

use models\SspDefaultPaddingModel;
use models\StartScreenAdConfModel;
use tinyESB\util\Logger;
use utils\ErrorCode;
use utils\Util;

/**
 * 激励视频
 * @path("/incentive_video/")
 */
class IncentiveVideo
{
    const SOURCE_TYPE_SSP = 1;
    const TYPE_START_SCREEN = 'splash';
    const TYPE_GAME = 'game';
    const TYPE_SKIN = 'skin';

    /**
     * SSP 示例参数
     * //        $arrPostField = array(
     * //            "app" => array("appVersion" => "10.9.4", "bundle" => "com.yingliang.clicknews", "channelId" => 2020, "deepLink" => true, "secure" => 0),
     * //            "device" => array("accu" => "0", "androidId" => "a9d061021d52b42f", "brand" => "OPPO", "bssid" => "doudou", "connectionType" => 100, "coordinateType" => 0, "deviceType" => 1, "imei" => "869754034926677", "ip" => "171.221.40.7", "lat" => "30.679847", "lng" => "104.003693", "mac" => "F2:49:D2:A9:2D:B3", "model" => "OPPO A83", "make" => "OPPO A83", "operatorType" => 0, "osType" => 1, "osVersion" => "7.1.1", "screenDensity" => "2.0", "screenHeight" => 1360, "screenType" => 1, "screenWidth" => 720, "ua" => "Mozilla/5.0 (Linux; Android 7.1.1; OPPO A83 Build/N6F26Q; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/66.0.3359.126 MQQBrowser/6.2 TBS/044605 Mobile Safari/537.36",),
     * //            "id" => "15543083531081535602",
     * //            "imps" => array(array("height" => 1280, "impId" => "53695bd0879149cebe5d5c49ad497b18", "instl" => 0, "page" => 0, "pid" => "2258a59f", "slotId" => "9ef2b133", "splash" => 0, "type" => 2, "width" => 720))
     * //        );
     */

    /**
     * 获取激励视频
     *
     * @route({"POST","/get_video"})
     * @param({"clientInfo","$._POST.client_info"}) 客户端上传信息
     * @param({"video_channel", "$._GET.video_channel"})
     * @param({"cuid", "$._GET.cuid"})
     * @return({"header",
     * "Content-Type: application/json; charset=UTF-8"})
     */
    public function getVideo($clientInfo, $video_channel = 'game', $cuid = '')
    {
        $result = Util::initialClass(true);

        // validate
        $clientInfo = is_array($clientInfo) ? $clientInfo : json_decode($clientInfo, true);
        if (json_last_error() != 0) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "mal-formatted param");
        }

        list($ok, $msg) = $this->_validate($clientInfo);
        if (!$ok) {
            return ErrorCode::returnError('PARAM_ERROR', $msg);
        }
        $result['data']->id = $clientInfo['id'];
        $result['data']->ads = array();

        //  首屏走过滤逻辑
        if (in_array($video_channel, array(self::TYPE_START_SCREEN))) {
            // 访问控制
            /** @var StartScreenAdConfModel $model */
            $model = new StartScreenAdConfModel();
            $res = $model->filter($cuid);
            if (!$res) {
                Logger::debug(sprintf("filter failed for cuid:%s", $cuid));
                return Util::returnValue($result);
            }
        }

        $orpFetchUrl = new Orp_FetchUrl();

        /** @var Orp_FetchUrl $httpProxy */
        $httpProxy = $orpFetchUrl->getInstance(array('timeout' => 3000));
        $header = array(
            sprintf("%s:%s", "appClientIp", Util::getClientIP()),
            "Content-Type:application/json",
        );
        $sspRst = $httpProxy->post('http://control.ssp.lionmobo.com/api', json_encode($clientInfo), $header);
        Logger::debug(sprintf("getVideo.call | http code: %d | errno:%d | input %s | output %s",
            $httpProxy->http_code(), $httpProxy->errno(), json_encode($clientInfo), $sspRst));

        if ($httpProxy->http_code() === 200 && $httpProxy->errno() === 0) {
            $sspArrRst = json_decode($sspRst, true);
//            $sspArrRst = array();
            if (is_array($sspArrRst) && isset($sspArrRst['ads']) && !empty($sspArrRst['ads'])) { // 有数据则下发，否则走兜底逻辑
                $result['data']->ads = $sspArrRst['ads'];
                return Util::returnValue($result);
            } else {
                Logger::warning(sprintf(
                    "getVideo.decode error | http code:%d | errno:%d | input %s | output %s | error:%d",
                    $httpProxy->http_code(), $httpProxy->errno(), json_encode($clientInfo), $sspRst, json_last_error()));
                if (in_array($video_channel, array(self::TYPE_GAME, self::TYPE_SKIN))) {
                    $this->addCountPadding($video_channel);
                    $result['data']->ads = $this->getPaddingData();
                    return Util::returnValue($result);
                }
            }
        } else {
            Logger::warning(sprintf(
                "getVideo.call ssp error | http code:%d| errno:%d | input %s | output %s",
                $httpProxy->http_code(), $httpProxy->errno(), json_encode($clientInfo), $sspRst));
            if (in_array($video_channel, array(self::TYPE_GAME, self::TYPE_SKIN))) {
                $this->addCountPadding($video_channel);
                $result['data']->ads = $this->getPaddingData();
                return Util::returnValue($result);
            }
        }
        return ErrorCode::returnError(ErrorCode::EMPTY_RESULT, "第三方调用失败");
    }

    private function _validate($arrParams)
    {
        $rule = array(
            "id" => true,
            "app" => true,
            "app.appVersion" => true,
            "app.bundle" => true,
            "app.channelId" => true,
            "app.deepLink" => true,
            "device" => true,
            "device.brand" => true,
            "device.connectionType" => true,
            "device.deviceType" => true,
            "device.ip" => true,
            "device.mac" => true,
            "device.model" => true,
            "device.make" => true,
            "device.osType" => true,
            "device.osVersion" => true,
            "device.screenHeight" => true,
            "device.screenWidth" => true,
            "device.ua" => true,
            "imps" => true,
        );
        foreach ($rule as $k => $v) {
            $kArr = explode(".", $k);
            $primaryKey = $kArr[0];
            if (sizeof($kArr) == 1) {
                if (!isset($arrParams[$primaryKey]) || empty($arrParams[$primaryKey])) {
                    return array(false, "$primaryKey 字段为空");
                }
            } else {
                $subKey = $kArr[1];
//                echo "<pre>";var_dump(isset($arrParams[$primaryKey][$subKey]));exit();
                if (!isset($arrParams[$primaryKey][$subKey])) {
                    return array(false, "$primaryKey.$subKey 字段为空");
                }
            }
        }
        return array(true, "");
    }

    private function addCountPadding($type)
    {
        $domain = Util::getV5Domain();
        $url = $domain . "v5/incentive_video/statistics?type=" . $type;
        Logger::warning(sprintf("incentive_video send padding data count, type:%s", $type));
        $orpFetchUrl = new Orp_FetchUrl();
        $httpProxy = $orpFetchUrl->getInstance(array('timeout' => 1000));
        $httpProxy->get($url);
    }

    private function getPaddingData()
    {
        $model = new SspDefaultPaddingModel();
        return $model->getLastOnline();
    }

    /**
     * 统计兜底数据填充，这边只是给个空接口
     * http://newicafe.baidu.com/issue/inputserver-2919/show?from=page
     * @route({"GET", "/statistics"})
     * @param({"type", "$._GET.type"}) int 1 点赞
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function statistics($type = 'game')
    {
        $result = Util::initialClass();
        Logger::warning(sprintf("incentive_video receive padding data count, type:%s", $type));
        return Util::returnValue($result);
    }

    /**
     * 获取激励视频
     *
     * @route({"GET","/start_screen_conf"})
     * @return({"header",
     * "Content-Type: application/json; charset=UTF-8"})
     */
    public function getStartScreenConfig()
    {
        $result = Util::initialClass(true);
        /** @var StartScreenAdConfModel $model */
        $model = new StartScreenAdConfModel();
        $res = $model->getConfig();
        Logger::debug(sprintf("getStartScreenConfig.get config:%s", json_encode($res)));
        if (empty($res)) {
            Logger::warning("getStartScreenConfig.get config failed");
        }
        $result['data'] = $res;
        return Util::returnValue($result);
    }

    /**
     * 获取激励视频
     *
     * @route({"GET","/get_count"})
     * @param({"cuid", "$._GET.cuid"})
     * @return({"header",
     * "Content-Type: application/json; charset=UTF-8"})
     */
    public function getCount($cuid = '')
    {
        $result = Util::initialClass(true);
        /** @var StartScreenAdConfModel $model */
        $model = new StartScreenAdConfModel();
        $count = $model->getCount($cuid);
        $result['data']->cuid = $cuid;
        $result['data']->count = intval($count);
        return Util::returnValue($result);
    }
}

