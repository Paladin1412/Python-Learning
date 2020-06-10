<?php

use utils\ErrorCode;
use utils\GFunc;
use utils\Util;

/**
 * 风控
 * User: chendaoyan
 * Date: 2019/4/10
 * Time: 16:44
 * @path("/risk/")
 */
class Risk {
    /**
     * 风险查询
     * http://agroup.baidu.com/inputserver/md/article/1779646
     * @route({"POST", "/validate"})
     * @param({"commonParams", "$._POST.common"}) string $zid 客户端获取的zid透传过来
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function validate($commonParams='') {
        header("Access-Control-Allow-Origin: *");
        $result = Util::initialClass();
        //参数验证
        $commonParams = bd_AESB64_Decrypt($commonParams);
        $arrCommonParams = json_decode($commonParams, true);
        if (empty($arrCommonParams)) {
            return ErrorCode::returnError('PARAM_ERROR', '通用参数错误');
        } else if (empty($arrCommonParams['zid'])) {
            return ErrorCode::returnError('PARAM_ERROR', 'zid错误');
        }

        $conf = GFunc::getConf("risk_conf");
        $cuid = $arrCommonParams['cuid'];
        $passuid = $arrCommonParams['passUID'];
        $app = Util::getOS($arrCommonParams['platform']);
        $appKey = $conf['earch']['appkey'];
        $seckey = $conf['earch']['seckey'];
        switch ($app) {
            case 'ios':
                $dev = '1';
                $caller = $conf['earch']['caller_ios'];
                $aid = $conf['earch']['aid_ios'];
                break;
            case 'android':
                $dev = '2';
                $caller = $conf['earch']['caller_android'];
                $aid = $conf['earch']['aid_android'];
                break;
            default:
                $dev = '1';
                $caller = $conf['earch']['caller_ios'];
                $aid = $conf['earch']['aid_ios'];
        }
        $imei = $arrCommonParams['imei'];
        $idfa = $arrCommonParams['idfa'];
        $idfv = $arrCommonParams['idfv'];
        $ev = '2';
        $ver = $arrCommonParams['appVersion'];
        $zid = $arrCommonParams['zid'];

        $objRiskModel = IoCload('models\\RiskModel');
        $riskInfo = $objRiskModel->getRiskInfo($zid, $aid, $caller, $cuid, $passuid, $dev, $app, $appKey, $seckey, $imei, $idfa, $idfv, $ev, $ver);
        $result['data']->res = $riskInfo;

        return Util::returnValue($result);
    }
}