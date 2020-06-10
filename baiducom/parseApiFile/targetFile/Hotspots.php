<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/hotspots/")
 * Hotspots.php UTF-8 2017-12-26 17:33:20
 */
use utils\Util;
class Hotspots {

    /**
     * @desc 个性化动态热区开关
     * @route({"GET", "/switch"})
     * @param({"strCuid", "$._GET.cuid"})  cuid
     * @param({"strPlatform", "$._GET.platform"})  platform 平台号
     * @param({"strVersion", "$._GET.version"})  version 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function getSwitch($strCuid = '', $strPlatform = '', $strVersion = '') {
        $result = array('status' => 0, 'msg' => 'success', 'data' => false);
        if ('' === $strCuid || '' === $strPlatform || '' === $strVersion) {
            return $result;
        }
        $objHotspotsModel = IoCload("models\HotspotsModel");
        $result['data']   = $objHotspotsModel->getCuidSwitch($strCuid, $strPlatform, $strVersion);
        return $result;
    }

    /**
     * @desc 个性化动态热区文件上传
     * @route({"POST", "/upload"})
     * @param({"strCuid", "$._GET.cuid"})  cuid
     * @param({"strPlatform", "$._GET.platform"})  platform 平台号
     * @param({"strVersion", "$._GET.version"})  version 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function postFile($strCuid = '', $strPlatform = '', $strVersion = '') {
        $result = array('status' => 0, 'msg' => 'success', 'data' => new stdClass());
        if ('' === $strCuid || '' === $strPlatform || '' === $strVersion) {
            $result['status'] = 1;
            $result['msg']    = 'params error';
            return $result;
        }
        if (!isset($_FILES['hotspots'])) {
            $result['status'] = 2;
            $result['msg']    = 'file not exists';
            return $result;
        }
        if (1024 * 1024 < $_FILES['hotspots']['size']) {
            $result['status'] = 3;
            $result['msg']    = 'file is too large';
            return $result;
        }
        $objHotspotsModel = IoCload("models\HotspotsModel");
        $rs               = $objHotspotsModel->getCuidSwitch($strCuid, $strPlatform, $strVersion);
        if (false === $rs) {
            $result['status'] = 4;
            $result['msg']    = 'cuid error';
            return $result;
        }
        $uploadRes = $objHotspotsModel->uploadToBos($_FILES['hotspots']['tmp_name'], $strCuid, $_FILES['hotspots']['name']);
        if (false === $uploadRes) {
            $result['status'] = 5;
            $result['msg']    = 'upload to bos error';
        }
        return $result;
    }

    /**
     * @desc 个性化动态热区开关(新通知中心格式)
     * @route({"GET", "/switch_new"})
     * @param({"strCuid", "$._GET.cuid"})  cuid
     * @param({"strPlatform", "$._GET.platform"})  platform 平台号
     * @param({"strVersion", "$._GET.version"})  version 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function getSwitchNew($strCuid = '', $strPlatform = '', $strVersion = '') {
        $result=Util::initialClass();
        $result['data']->switch=false;
        $result['version']=0;
        if ('' === $strCuid || '' === $strPlatform || '' === $strVersion) {
            return Util::returnValue($result,true,true);
        }
        $objHotspotsModel = IoCload("models\HotspotsModel");
        $rs=$objHotspotsModel->getCuidSwitchNew($strCuid, $strPlatform, $strVersion,true);
        $result['data']->switch   = $rs['switch'];
        $result['version']=$rs['version'];
        return Util::returnValue($result,true,true);
    }

    /**
     * @desc 个性化动态热区文件上传(新通知中心格式)
     * @route({"POST", "/upload_new"})
     * @param({"strCuid", "$._GET.cuid"})  cuid
     * @param({"strPlatform", "$._GET.platform"})  platform 平台号
     * @param({"strVersion", "$._GET.version"})  version 版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function postFileNew($strCuid = '', $strPlatform = '', $strVersion = '') {
        $result=Util::initialClass();
        if ('' === $strCuid || '' === $strPlatform || '' === $strVersion) {
            $result['ecode'] = 1;
            $result['emsg']    = 'params error';
            return Util::returnValue($result,true,true);
        }
        if (!isset($_FILES['hotspots'])) {
            $result['ecode'] = 2;
            $result['emsg']    = 'file not exists';
            return Util::returnValue($result,true,true);
        }
        if (1024 * 1024 < $_FILES['hotspots']['size']) {
            $result['ecode'] = 3;
            $result['emsg']    = 'file is too large';
            return Util::returnValue($result,true,true);
        }
        $objHotspotsModel = IoCload("models\HotspotsModel");
        $rs               = $objHotspotsModel->getCuidSwitch($strCuid, $strPlatform, $strVersion);
        if (false === $rs) {
            $result['ecode'] = 4;
            $result['emsg']    = 'cuid error';
            return Util::returnValue($result,true,true);
        }
        $uploadRes = $objHotspotsModel->uploadToBos($_FILES['hotspots']['tmp_name'], $strCuid, $_FILES['hotspots']['name']);
        if (false === $uploadRes) {
            $result['ecode'] = 5;
            $result['emsg']    = 'upload to bos error';
        }
        return Util::returnValue($result,true,true);
    }

}
