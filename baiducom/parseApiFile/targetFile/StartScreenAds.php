<?php

use models\StartScreenAdConfModel;
use models\UserShowLimiterModel;
use tinyESB\util\Logger;
use utils\ErrorCode;
use utils\GFunc;
use utils\Util;

/**
 *
 * 开屏广告
 *
 *
 * @author fanwenli
 * @path("/start_screen_ads/")
 */
class StartScreenAds
{
    const PRE_SSP = 1;
    const POST_SSP = 2; // ssp 之后

    /**
     * 获取开屏广告
     *
     * @route({"POST","/list"})
     * @param({"clientInfo","$._POST.client_info"}) 客户端上传信息
     * @param({"cuid", "$._GET.cuid"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getList($clientInfo, $cuid = '')
    {
        if ($cuid == "") {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "cuid为空");
        }
        $clientInfo = is_array($clientInfo) ? $clientInfo : json_decode($clientInfo, true);

        $result = Util::initialClass(true);
        $result['data'] = array(
            "res" => array(),
            "ssp" => array("ads" => array(), "id" => $clientInfo['id']),
        );

        // 切分配置
        $resAds = Util::filterAds(Util::filterExptimeAds($this->getAdsList()));
//        echo "<pre>";var_dump($resAds);exit();
        list($preSspResAds, $postSspResAds) = $this->splitResAds($resAds);
        // 一、ssp 前展示的人工配置
        $resPreForLimit = $this->preProcessingRes($preSspResAds);
        $ResLimiterPre = new UserShowLimiterModel($cuid, "pre_ssp_res",
            $this->cacheTime());

        list($okPre, $preToShowMeta) = $ResLimiterPre->limit($resPreForLimit);
        if ($okPre) {
            $result['data']['res'] = $this->fetchResAdFromMeta($resAds, $preToShowMeta);
            return Util::returnValue($result);
        }

        // 二、SSP
        /** @var StartScreenAdConfModel $model */
        $sspModel = new StartScreenAdConfModel();
        $shouldSsp = $sspModel->filter($cuid);
        if (!$shouldSsp) {
            logger::debug(sprintf("%s.%s | ssp filter failed | cuid:%s",
                __CLASS__, __FUNCTION__, $cuid));
        } else {
            list($okSsp, $sspData) = $this->fetchSsp($clientInfo);
            if ($okSsp) {
                logger::debug(sprintf("%s.%s | get ssp succ | cuid:%s",
                    __CLASS__, __FUNCTION__, $cuid));
                $result['data']['ssp'] = $sspData;
                return Util::returnValue($result);
            }
            logger::warning(sprintf("%s.%s | get ssp failed, continue | cuid:%s",
                __CLASS__, __FUNCTION__, $cuid));
        }

        // 三、ssp 后展示的人工配置
        $resPostForLimit = $this->preProcessingRes($postSspResAds);
        $ResLimiterPre = new UserShowLimiterModel($cuid, "post_ssp_res", $this->cacheTime());
        list($okPost, $postToShowMeta) = $ResLimiterPre->limit($resPostForLimit);
        if ($okPost) {
            $result['data']['res'] = $this->fetchResAdFromMeta($resAds, $postToShowMeta);
            return Util::returnValue($result);
        }

        return Util::returnValue($result);
    }

    /**
     * 获取res广告信息
     * @return array
     */
    public function getAdsList()
    {
        $ads_cache_key = __Class__ . '_start_screen_ads_list_cachekey';
        $uri = '/res/json/input/r/online/advertisement/?onlycontent=1&search={"ad_zone":{"$in":[19]}}';
        $resAds = Util::ralGetContent($uri, $ads_cache_key, GFunc::getCacheTime('ads_cache_time'));
        logger::debug(sprintf("%s.%s | data:%s", __CLASS__, __FUNCTION__, json_encode($resAds)));
        return $resAds;
    }

    /**
     * 将res广告分为两部分
     * @param $resAds
     * @return array[]
     */
    private function splitResAds($resAds)
    {
        $preSspResAds = array();
        $postSspResAds = array();
        foreach ($resAds as $aResAd) {
            if (isset($aResAd['show_priority']) && isset($aResAd['show_priority']['show_priority'])) {
                if ($aResAd['show_priority']['show_priority'] == self::POST_SSP) {
                    $postSspResAds[] = $aResAd;
                    continue;
                }
            }
            // 默认都是ssp之前展示
            $preSspResAds[] = $aResAd;
        }
        return array($preSspResAds, $postSspResAds);
    }

    /**
     * res 广告预处理
     * @param $resAds
     * @return array
     */
    private function preProcessingRes($resAds)
    {
        if (empty($resAds)) {
            return $resAds;
        }
        $processed = array();
        foreach ($resAds as $aResAd) {
            $one = array();
            $one[UserShowLimiterModel::FIELD_BUSID] = $aResAd['ad_id'];
            if ($aResAd['max_cnt'] == -1) {
                $one[UserShowLimiterModel::FIELD_MAX_NUM] = 99999999; // 便于统一处理, 不限次数默认给99999999次
            } else {
                $one[UserShowLimiterModel::FIELD_MAX_NUM] = $aResAd['max_cnt'];
            }
            $one[UserShowLimiterModel::FIELD_PRIORITY_PRIMARY] = $aResAd['priority'];
            $one[UserShowLimiterModel::FIELD_PRIORITY_SECONDARY] = $aResAd['ad_id'];

            $processed[] = $one;
        }
        return $processed;
    }

    private function cacheTime()
    {
        return Gfunc::getCacheTime('2hours') * 12 * 14;
//        return Gfunc::getCacheTime('1mins'); // FIXME
    }

    /**
     * @param $resAds
     * @param $preToShowMeta
     * @return array
     */
    private function fetchResAdFromMeta($resAds, $preToShowMeta)
    {
        $showAds = array();
        foreach ($resAds as $aResAd) {
            foreach ($preToShowMeta as $aMeta) {
                if ($aMeta[UserShowLimiterModel::FIELD_BUSID] == $aResAd['ad_id']) {
                    $showAds[] = $aResAd;
                }
            }
        }
        return $showAds;
    }

    /**
     * 获取ssp
     * @param $clientInfo
     * @return array
     */
    private function fetchSsp($clientInfo)
    {
        $ok = false;
        $sspResp = array(
            "ads" => array(),
            "id" => $clientInfo['id'],
        );
        $orpFetchUrl = new Orp_FetchUrl();

        /** @var Orp_FetchUrl $httpProxy */
        $httpProxy = $orpFetchUrl->getInstance(array('timeout' => 3000));
        $header = array(
            sprintf("%s:%s", "appClientIp", Util::getClientIP()),
            "Content-Type:application/json",
        );
        $sspRst = $httpProxy->post('http://control.ssp.lionmobo.com/api', json_encode($clientInfo), $header);
        Logger::debug(sprintf("%s.fetchSsp | http code: %d | errno:%d | input %s | output %s",
            __CLASS__, $httpProxy->http_code(), $httpProxy->errno(), json_encode($clientInfo), $sspRst));

        if ($httpProxy->http_code() === 200 && $httpProxy->errno() === 0) {
            $sspArrRst = json_decode($sspRst, true);
            if (is_array($sspArrRst) && isset($sspArrRst['ads'])) {
                if (!empty($sspArrRst['ads'])) {
                    $sspResp['ads'] = $sspArrRst['ads'];
                    $ok = true;
                } else {
                    Logger::warning(sprintf(
                        "%s.fetchSsp ads empty | http code:%d | errno:%d | err_msg:%s| input %s | output %s | error:%d",
                        __CLASS__, $httpProxy->http_code(), $httpProxy->errno(), $httpProxy->errmsg(),
                        json_encode($clientInfo), $sspRst, json_last_error()));
                }
            } else {
                Logger::warning(sprintf(
                    "%s.fetchSsp decode error | http code:%d | errno:%d | err_msg:%s| input %s | output %s | error:%d",
                    __CLASS__, $httpProxy->http_code(), $httpProxy->errno(), $httpProxy->errmsg(),
                    json_encode($clientInfo), $sspRst, json_last_error()));
            }
        } else {
            Logger::warning(sprintf(
                "%s.fetchSsp error | http code:%d| errno:%d | err_msg:%s| input %s | output %s",
                __CLASS__, $httpProxy->http_code(), $httpProxy->errno(), $httpProxy->errmsg(),
                json_encode($clientInfo), $sspRst));
        }
        return array($ok, $sspResp);
    }
}