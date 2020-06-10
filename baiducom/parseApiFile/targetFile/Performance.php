<?php

use utils\Util;
use utils\ErrorCode;
use utils\ErrorMsg;
/**
 * @desc 
 * 
 * @author lipengcheng02
 * @path("/performance/")
 * Performance.php UTF-8 2017-12-26 17:33:20
 *
 */
class Performance {

    /**
     * @desc 高低端机型判断
     * @route({"POST", "/info"})
     * @param({"strData","$._POST.performance"}) $performance 客户端上传的json格式的手机性能信息
     * @param({"model","$._GET.model"}) $model 手机机型
     * @param({"strPerformanceVersion", "$._GET.performance_version"}) $performance_version 请求获取的信息版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function getPerformanceInfo($strData, $model = '', $strPerformanceVersion = 0) {
        $result = array('ecode' => 0, 'emsg' => 'success','code' => 0, 'msg' => 'success', 'data' => array("is_high_performance_phone" => false), 'version' => 0);
        $result['md5'] = md5(json_encode($result['data']));
        
        if (null === $strData) {
            $result['code'] = 1;
            $result['msg'] = 'post data is null';
            return $result;
        }
        //decode上传参数
        $performaceInfo = json_decode($strData, true);
        if (null === $performaceInfo) {
            $result['code'] = 2;
            $result['msg'] = 'post data decode error';
        }
        //查询资源服务，判断是否为高端机
        $objPerformanceModel = IoCload("models\PerformanceModel");
        $objPerformanceModel->setPerformanceInfo($performaceInfo, $strPerformanceVersion, $model, $result);
        
        //Edit by fanwenli on 2019-03-25, add new column
        return $result;
    }
    
    /**
     * @desc IOS高低端机型判断
     * @route({"GET", "/iosinfo"})
     * @param({"timestamp","$._GET.timestamp"}) $model 手机机型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getIosPerformanceInfo($timestamp=0) {
        $result = Util::initialClass();
        
        $objPerformanceModel = IoCload('models\PerformanceModel');
        list($performanceInfo, $version) = $objPerformanceModel->getIosPerformanceInfo($timestamp);
        if (false === $performanceInfo) {
            $result['ecode'] = ErrorCode::DATA_RETURN_NULL;
            $result['emsg'] = ErrorMsg::getMsg(ErrorCode::DATA_RETURN_NULL);
            $result['data']->list = array();
        } else {
            $result['data']->list = $performanceInfo;
        }
        $result['version'] = $version;
        return Util::returnValue($result, false, true);
    }
}
