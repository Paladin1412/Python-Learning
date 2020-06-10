<?php

use utils\ErrorCode;
use utils\Util;
use models\SolidWindowAdsModel;
use utils\ConditionFilter;

/**
 * @desc 固定窗口广告
 * @path("/solid-window-ads/")
 */
class SolidWindowAds {


    /**
     *
     * @desc 通知中心信息获取 http://agroup.baidu.com/inputserver/md/article/2637290
     *
     * @route({"GET","/content"})
     *
     * @param({"mark", "$._GET.mark"}) 通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function content($mark = '') {
        $mark = strtolower(trim($mark));
        if(empty($mark)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'ads mark can not be empty');
        }
        $solidAdsModel = IoCload(SolidWindowAdsModel::class);
        $conditionFilter = IoCload(ConditionFilter::class);
        $datas = $solidAdsModel->getAdsInfoByMark($mark);
        $response = Util::initialClass(false);
        $now = time();
        // 数据为空， 或者活动未开始或者已结束
        if(empty($datas) || $now < $datas['ads_start_time'] || $now > $datas['ads_end_time']) {
            return Util::returnValue($response);
        }
        unset($datas['ads_start_time']);
        unset($datas['ads_end_time']);
        $response['data'] = $datas;
        $resourceInfos = array();
        // 通过过滤条件过滤不适合当前客户端的资源
        $datas['items'] = $conditionFilter->getFilterConditionFromDB($datas['items']);
        //对资源的时间进行过滤
        foreach($datas['items'] as $val) {
            if($now < $val['resource_stime'] || $now > $val['resource_etime']) {
                continue;
            }
            // 对资源进行格式化
            $val = Util::resourceDbDataFormat($val);
            if(!empty($val['data'])) {
                $resourceInfo = $val['data'];
                // 删除不需要的global_id 和 arr_global_id
                unset($resourceInfo['global_id']);
                unset($resourceInfo['arr_global_id']);
                // 标记广告的global_id
                if('advert' == $resourceInfo['resource_type']) {
                    $resourceInfo['ad_global_id'] = $solidAdsModel->getAdGlobalId($datas['mark'], $resourceInfo['resource_id']);
                } else {
                    $resourceInfo['ad_global_id'] = '';
                }
                array_push($resourceInfos, $resourceInfo);
            }
        }
        // 将数据排序
        array_multisort(array_column($resourceInfos, 'resource_sequnce'), SORT_DESC, $resourceInfos);
        $response['data']['items'] = $resourceInfos;
        return Util::returnValue($response);
    }

}