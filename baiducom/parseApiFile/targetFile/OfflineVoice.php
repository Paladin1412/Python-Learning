<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/offlinevoice/")
 * OfflineVoice.php UTF-8 2018-4-19 10:36:08
 */
use utils\Util;
use utils\GFunc;
use models\FilterModel;
use utils\ErrorCode;

class OfflineVoice {

    /**
     * AR表情列表
     * @route({"GET", "/downloadinfo"})
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"verName", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"fileType", "$._GET.filetype"}) 语音包类型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function downloadInfo($verName = '', $plt = '', $fileType = 0) {
        $result = Util::initialClass();
        if (empty($verName) && empty($plt)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR);
        }
        $fileType = intval($fileType);
        if ($fileType < 0 || $fileType > 1) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR);
        }
        $pathinfo    = "/res/json/input/r/online/offline_voice/";
        $strCachekey = md5($pathinfo);
        $arrRs       = GFunc::cacheZget($strCachekey);
        if (false === $arrRs || null === $arrRs) {
            $header = array(
                'pathinfo'    => $pathinfo,
                'querystring' => 'onlycontent=1'
            );
            $arrRs  = ral("res_service", "get", null, null, $header);
            GFunc::cacheZset($strCachekey, $arrRs, GFunc::getCacheTime('2hours'));
        }

        $filterModel = new FilterModel();
        $arrRs     = $filterModel->getFilterByArray($arrRs);
        $arrInfo = current($arrRs);//若取到多条配置以最新配置的为准
        if (empty($arrInfo)) {
            return ErrorCode::returnError(ErrorCode::DATA_FORMAT_ERROR,'无配置');
        }
        if ($arrInfo['checkType']==0||$fileType === 0) {
            $arrData = $arrInfo['all'];
        }
        else {
            $arrData = $arrInfo['lite'];
        }
        $paramsList=['name','versionCode','description','isSoInstallDelay','v7','v8'];
        foreach ($paramsList as $param){
            if(isset($arrInfo[$param])){
                $arrData[$param]=$arrInfo[$param];
            }
        }
        if(!isset($arrData['isSoInstallDelay'])){
            $arrData['isSoInstallDelay']=0;
        }
        $arrData['fileType']    = $fileType;
        $result['data']         = $arrData;

        return $result;
    }

    /**
     * AR表情列表
     * @route({"GET", "/turbonetDownload"})
     *
     * @param({"ver", "$._GET.ver"}) string $ver_name sdk版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function turbonetDownload($ver) {
        $result = Util::initialClass();
        $pathinfo    = "/res/json/input/r/online/turbonet/";
        $strCachekey = md5($pathinfo);
        //Edit by fanwenli on 2019-01-11, add version in cache
        $strCachekey .= '_new';
        $arrRs       = GFunc::cacheZget($strCachekey);
        if (false === $arrRs || null === $arrRs) {
            $header = array(
                'pathinfo'    => $pathinfo,
                'querystring' => 'onlycontent=1'
            );
            
            //Edit by fanwenli on 2019-01-11, add version in cache
            //$arrRs  = ral("res_service", "get", null, null, $header);
            $arrRs['data'] = GFunc::getRalContent('turbonet', 0);
            $arrRs['version'] = GFunc::getResVersion();
            
            GFunc::cacheZset($strCachekey, $arrRs, GFunc::getCacheTime('2hours'));
        }
        $filterModel = IoCload('models\FilterModel');
        /*$arrRs = $filterModel->getFilterByArray($arrRs);
        foreach ($arrRs as $arrRsK => $arrRsV) {
            if ($arrRsV['versionCode'] > $ver) {
                $result['data'] = $arrRsV;
                break;
            }
        }*/
        
        $arrRs['data'] = $filterModel->getFilterByArray($arrRs['data']);
        foreach ($arrRs['data'] as $arrRsK => $arrRsV) {
            if ($arrRsV['versionCode'] > $ver) {
                $result['data'] = $arrRsV;
                break;
            }
        }
        
        //Edit by fanwenli on 2019-01-11, set version in return json
        $result['version'] = $arrRs['version'];

        return Util::returnValue($result,true,true);
    }
}
