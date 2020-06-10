<?php

use utils\Util;
/**
 * @desc 和客户端设备相关的接口
 * @path("/device/")
 */
class Device {

    /** @property 高低端机型filter_id */
    private $highLowDeviceFilterId;

    /**
     * 检测设备的一些信息
     * @route({"GET","/check"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function check() {
        $ret = Util::initialClass();
        // 如果是非低端机型或者出错, data保持为空
        if(empty($this->highLowDeviceFilterId)) {
            return Util::returnValue($ret);
        }
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $filters = $conditionFilter->getFilters();
        if(!isset($filters[$this->highLowDeviceFilterId])) {
            return Util::returnValue($ret);
        }
        $dataStd = $ret['data'];
        $islowDevice = $conditionFilter->filter($filters[$this->highLowDeviceFilterId]);
        $dataStd->is_low_device = $islowDevice;
        return Util::returnValue($ret);
    }
}