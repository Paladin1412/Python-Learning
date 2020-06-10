<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/ppdict/")
 * Ppdict.php UTF-8 2020-4-19 16:07:10
 */
use utils\Util;
use utils\ErrorCode;
use utils\GFunc;

class Ppdict {

    /**
     * 后置词库下发
     * 
     * @route({"GET","/get"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function get() {
        $result = Util::initialClass();

        $cacheKey = md5(sprintf("%s_%s_%s", __CLASS__, __METHOD__, "ppdict_data"));
        $data     = GFunc::cacheGet($cacheKey);
        if (false === $data || null === $data) {
            $data           = array();
            $objPpdictModel = IoCload('models\\PpdictModel');
            $condition      = array('status=100');
            $ppdictData     = $objPpdictModel->select('description,dict_url,filter_id,md5', $condition, null, array('ORDER BY update_time DESC'));
            if (false != $ppdictData) {
                $data['ppdict'] = $ppdictData;
                //获取最新更新时间戳
                $appends        = array(
                    'ORDER BY update_time DESC',
                    'LIMIT 1'
                );
                $lastTimestamp  = $objPpdictModel->select('update_time', null, null, $appends);
                if (false !== $lastTimestamp) {
                    $data['lastTimestamp'] = $lastTimestamp[0]['update_time'];
                    $time                  = GFunc::getCacheTime('2hours');
                }
                else {
                    $data['ppdict']        = array();
                    $data['lastTimestamp'] = 0;
                    $time                  = GFunc::getCacheTime('1mins') * 5;
                }
            }
            else {
                $data['ppdict']        = array();
                $data['lastTimestamp'] = 0;
                $time                  = GFunc::getCacheTime('1mins') * 5;
            }

            GFunc::cacheSet($cacheKey, $data, $time);
        }

        $ppdictData    = $data['ppdict'];
        $lastTimestamp = $data['lastTimestamp'];
        $result['version'] = $lastTimestamp;
        if (!empty($ppdictData) && 0 !== $lastTimestamp) {
            $conditionFilter   = IoCload('utils\\ConditionFilter');
            $ppdictData        = $conditionFilter->getFilterConditionFromDB($ppdictData);
            if (!empty($ppdictData)) {
                $result['data']= current($ppdictData);
            }
        }

        return Util::returnValue($result, true, true);
    }

}
