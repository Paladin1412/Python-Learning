<?php

/***************************************************************************
 *
 * Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
require __DIR__.'/utils/BdinputUserBehavior.php';

/**
 * @author wangyixiang(wangyixiang@baidu.com)
 * @desc 日志收集类
 * @path("/fb/")
 */
class LogCollector{

    const ADSLOG = 0;
    const BBMLOG = 1;
    const SEVERLOG = 2;
    const CLIENTLOG = 3;
    const VPNLOG = 4;
    const CHANGEIMELOG = 5; //输入法变更,access_type获取切换输入法包名,同时获取bbm数据
    const CORPUS = 6; //语料相关数据

    private $data;
    private $user_behavior;
    private $k8408;

    private $clientlog_arr = array(
        'CRASHLOG' => 1 ,
        'KERNELOG' => 2 ,
        'UZ___LOG' => 3 ,
        'UZ___TMP' => 4 ,
        'UE___LOG' => 5 ,
        'UE___TMP' => 6 ,
        'CELL_LOG' => 7 ,
        'CELL_TMP' => 8 ,
        'LXR__LOG' => 9 ,
        'LXR__TMP' => 10 ,
        'EMOJILOG' => 11 ,
        'EMOJITMP' => 12 ,
        'PHRASLOG' => 13 ,
        'PHRASTMP' => 14 ,
        'SYM__LOG' => 15 ,
        'SYM__TMP' => 16 ,
        'SYML_LOG' => 17 ,
        'SYML_TMP' => 18 ,
        'CORE_LOG' => 19 ,
        'CORE_RSV' => 20 ,
        'VOC_INFO' => 21 ,
        'EXCEPLOG' => 22 ,
        'UWORDLOG' => 23 ,  //用户自造词，  actionid:23，key：用户uid，value：json格式自造词数据
        'DEBUGLOG' => 24 ,  //客户端追查问题自定义数据内容
        'PERFMLOG' => 25 ,  //客户端性能日志
        'ANR__LOG' => 26 ,  //客户端响应延迟等日志ANR__LOG
        'ARERRLOG' => 27 ,  //AR使用信息,RSA加密后分片,每片2次AES加密,接口层完成2次AES解密,去除cpr_splitblock_header,并BASE64编码后pb化
        'IPERRZIP' => 28 ,  //iOS崩溃日志zip数据
        'IO___LOG' => 29 ,  //Android IO性能日志
        'IOSTRANS' => 30 ,  //iOS转化率数据日志,通过接口/v5/fb/custom?logtype=3&actionid=30上传
        'IPTTOUCH' => 31 ,  //内核误触率统计数据

    );

    private $corpus_arr = array(
        'an11' => 1 ,  //手写SDK上传的未加密的手写轨迹，an11
        'an10' => 2 ,  //长log、轨迹数据，an10
        'an13' => 3 ,  //带坐标的轨迹，an13
        'an14' => 4 ,  //纠错轨迹，an14
        'an15' => 5 ,  //皮肤轨迹，an15
        'an16' => 6 ,  //误触工具轨迹，an16
    );


    function __construct(){
        require_once __DIR__.'/utils/BdinputStatistic.php';
        $this->data = '';
        $this->user_behavior = null;
        $this->k8408 = array();
    }


    /**
     * @desc 数据收集接口。
     * @route({"POST", "/an"})    【注】需要POST文件an3，$_FILES['an3']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"mac", "$._GET.mac"}) mac地址
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param({"acmode", "$._GET.acmode"}) 激活方式
     * @param({"logtype", "$._GET.logtype"}) 日志类型
     * @param({"screen_w", "$._GET.screen_w"}) 屏幕宽度
     * @param({"screen_h", "$._GET.screen_h"}) 屏幕高度
     * @param ({"an3", "$._FILES.an3.tmp_name"}) 广告数据文件
     * @param ({"an10", "$._FILES.an10.tmp_name"}) 长log、轨迹数据，an10
     * @param ({"an11", "$._FILES.an11.tmp_name"}) 手写SDK上传的未加密的手写轨迹，an11
     * @param ({"an13", "$._FILES.an13.tmp_name"}) 带坐标的轨迹，an13
     * @param ({"an14", "$._FILES.an14.tmp_name"}) 纠错轨迹，an14
     * @param ({"an15", "$._FILES.an15.tmp_name"}) 皮肤轨迹，an15
     * @param ({"an16", "$._FILES.an16.tmp_name"}) 误触工具轨迹，an16
     * @param ({"cime", "$._POST.cime"}) 输入法变更数据 CHANGEIMELOG
     * @param ({"model", "$._GET.model"}) 客户端机型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }
     */
    function an(
        $cuid = null,
        $uid = null,
        $mac = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $acmode = null,
        $logtype = null,
        $screen_w = null,
        $screen_h = null,
        $an3 = null,
        $an10 = null,
        $an11 = null,
        $an13 = null,
        $an14 = null,
        $an15 = null,
        $an16 = null,
        $cime = null,
        $model = null
    ){


        $res = array();

        //判断是否为欧盟用户，是则直接返回不存储
        if (true === Util::isEu($cuid)) {
            $res['info'] = 'Success';
            $res['status'] = 0;
            return $res;
        }

        if($cime != null){
            $cime = bd_RSA_DecryptByDK($cime);
            if($cime == ''){
                $cime = null;
            }
        }

        if(isset($an3) || $cime != null){
            try{
                $clientip = Util::getClientIP();
                $file_data = file_get_contents($an3);
                $file_size = strlen($file_data);
                $zipdata = bd_RSA_DecryptByDK($file_data);
                if($zipdata ==='') {
                    $zipdata = bd_B64_Decode($file_data, 0);
                }
                $this->data = gzuncompress(substr($zipdata,4,strlen($zipdata)-4));
                $isformat = $this->isBBMFormated();
                if($isformat === true || $cime != null) {
                    $this->user_behavior = new BdinputUserBehav();
                    //先确定日志类型
                    if($logtype != null) {
                        $this->user_behavior->setLogType(LogCollector::BBMLOG);
                    } else {
                        $this->user_behavior->setLogType(LogCollector::ADSLOG);
                    }

                    $publicinfo = $this->user_behavior->getPublicInfo();
                    $common = $this->user_behavior->getCommon();
                    $common->setLogid(''.time());

                    if($cime != null){
                        $this->user_behavior->setLogType(LogCollector::CHANGEIMELOG);
                        $publicinfo->setAccessType($cime);
                    }


                    $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                    if($uid != null) {
                        $publicinfo->setBdinputUid(urldecode($uid));
                    }
                    if($cuid != null) {
                        $common->getDeviceId()->setCuid($cuid);

                        $parts = explode('|',$cuid, 2);
                        if(count($parts)==2){
                            $common->getDeviceId()->setImei(strrev($parts[1]));
                            $publicinfo->setBdinputUid('bd_'.$parts[1]);
                        }
                    }
                    if((!$publicinfo->hasBdinputUid() || $publicinfo->getBdinputUid()==='bd_0') && $mac != null) {
                        $publicinfo->setBdinputUid('bd_'.$mac);
                    }
                    if($idfa != null) {
                        $common->getDeviceId()->setIdfa($idfa);
                    }
                    if($opuid != null) {
                        $common->getDeviceId()->setOpenUdid($opuid);
                    }

                    $publicinfo->setDataSize($file_size);
                    if($platform != null) {
                        if($acmode != null && $acmode == 'setting'){
                            $platform = $platform.'setting';
                        }
                        $publicinfo->setPlatform($platform);
                        if(strpos($platform, 'a') !== false) {
                            $publicinfo->setOs(OSType::ANDROID);
                        } elseif(strpos($platform, 'i') !== false) {
                            $publicinfo->setOs(OSType::IOS);
                        } else {
                            $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                        }
                    }
                    if($version != null) {
                        $publicinfo->setSoftwareVersion($version);
                    }
                    if($channel != null) {
                        $publicinfo->setChannel($channel);
                    }
                    if($current_channel != null) {
                        $publicinfo->setCurrentChannel($current_channel);
                    }
                    if($os_version != null) {
                        $publicinfo->setOsVersion($os_version);
                    }
                    if($clientip != null) {
                        $publicinfo->setIp($clientip);
                    }
                    if($screen_w != null) {
                        $publicinfo->setResolutionV(intval($screen_w));
                    }
                    if($screen_h != null) {
                        $publicinfo->setResolutionH(intval($screen_h));
                    }
                    if($model != null) {
                        $publicinfo->setTerminalType($model);
                    }

                    $dataLen = strlen($this->data);
                    while($dataLen > 0){
                        $type = 0;
                        $childDataUnits = '';
                        if($this->parseTypeLenUnits($this->data,$childDataUnits,$type)){
                            $this->parseChildUnits($type, $childDataUnits);
                            if(strlen($this->data) > (strlen($childDataUnits)+6)){
                                $this->data = substr($this->data,-(strlen($this->data)-strlen($childDataUnits)-6));
                            }else{
                                $this->data = '';
                            }
                        }else{
                            $this->data = '';
                        }
                        $dataLen = strlen($this->data);
                    }
                    $str = $this->user_behavior->serialize();
                    if($str == null) {
                        $res['info'] = 'Pb Serial Failed';
                        $res['status'] = 1;
                        return $res;
                    }
                    // 使用b2log库来打印日志
                    $ret = b2log_write('bdinput_user_behav', $str);
                    if($ret == false) {
                        $res['info'] = 'Pb Write Failed';
                        $res['status'] = 1;
                        return $res;
                    }
                    if(count($this->k8408) > 0 && $this->user_behavior->getPublicInfo()->getPlatform() == 'p-a1-3-72'){
                        $this->realtime_K8408($this->k8408);
                    }
                    $res['info'] = 'Success';
                    $res['status'] = 0;
                    return $res;
                } else {
                    $res['info'] = 'Format Error';
                    $res['status'] = 1;
                    return $res;
                }
            }catch (Exception $e){
                $res['info'] = 'Unknown Error';
                $res['status'] = 1;
                return $res;
            }
        } elseif($an10 != null || $an11 != null || $an13 != null || $an14 != null || $an15 != null || $an16 != null) {
            $this->user_behavior = new BdinputCustom();
            $anfile = null;
            $action = '';
            if($an10 != null) {
                $anfile = $an10;
                $action = 'an10';
            } elseif($an11 != null) {
                $anfile = $an11;
                $action = 'an11';
            } elseif($an13 != null) {
                $anfile = $an13;
                $action = 'an13';
            } elseif($an14 != null) {
                $anfile = $an14;
                $action = 'an14';
            } elseif($an15 != null) {
                $anfile = $an15;
                $action = 'an15';
            } elseif($an16 != null) {
                $anfile = $an16;
                $action = 'an16';
            }
            $file_data = file_get_contents($anfile);
            $file_size = strlen($file_data);
            //先确定日志类型
            $this->user_behavior->setLogType(LogCollector::CORPUS);
            $publicinfo = $this->user_behavior->getPublicInfo();
            $common = $this->user_behavior->getCommon();

            $common->setLogid(''.time());
            $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);

            $publicinfo->setDataSize($file_size);
            $publicinfo->setIp(Util::getClientIP());
            if($platform != null) {
                $publicinfo->setPlatform($platform);
                if(strpos($platform, 'a') !== false) {
                    $publicinfo->setOs(OSType::ANDROID);
                } elseif(strpos($platform, 'i') !== false) {
                    $publicinfo->setOs(OSType::IOS);
                } else {
                    $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                }
            }
            if($version != null) {
                $publicinfo->setSoftwareVersion($version);
            }
            if($channel != null) {
                $publicinfo->setChannel($channel);
            }
            if($current_channel != null) {
                $publicinfo->setCurrentChannel($current_channel);
            }
            if($os_version != null) {
                $publicinfo->setOsVersion($os_version);
            }
            if($screen_w != null) {
                $publicinfo->setResolutionV(intval($screen_w));
            }
            if($screen_h != null) {
                $publicinfo->setResolutionH(intval($screen_h));
            }
            if($model != null) {
                $publicinfo->setTerminalType($model);
            }
            if($uid != null) {
                $publicinfo->setBdinputUid(urldecode($uid));
            }
            if($cuid != null) {
                $common->getDeviceId()->setCuid($cuid);

                $parts = explode('|',$cuid, 2);
                if(count($parts)==2){
                    $common->getDeviceId()->setImei(strrev($parts[1]));
                    $publicinfo->setBdinputUid('bd_'.$parts[1]);
                }
            }
            if($idfa != null) {
                $common->getDeviceId()->setIdfa($idfa);
            }
            if($opuid != null) {
                $common->getDeviceId()->setOpenUdid($opuid);
            }

            $onelog = new CustomLog();
            if($action != '') {
                $onelog->setActionId($this->corpus_arr[$action]);
                $onelog->setStrKey($action);
                $onelog->setStrVal(trim(bd_B64_Encode($file_data, 0)));
            }

            $this->user_behavior->addCustomLogs($onelog);
            $str = $this->user_behavior->serialize();
            if($str == null) {
                $res['info'] = 'Pb Serial Failed: '.$action;
                $res['status'] = 1;
                return $res;
            }
            // 使用b2log库来打印日志
            $ret = b2log_write('bdinput_custom', $str);
            if($ret == false) {
                $res['info'] = 'Pb Write Failed: '.$action;
                $res['status'] = 1;
                return $res;
            }
            $res['info'] = 'Success: '.$action;
            $res['status'] = 0;
            return $res;
        } else {
            $res['info'] = 'File Not Found';
            $res['status'] = 1;
            return $res;
        }

        return $res;
    }



    /**
     * @desc 十进制数字转十六进制字符串
     * @param int $number 数值
     * @param int $length 半字节长度
     * @return string 带‘k’十六进制字符串
     */
    function intToHex($number,$length=4){
        $hexStr = dechex($number);
        for($i = strlen($hexStr); $i < $length; $i++){
            $hexStr = '0'.$hexStr;
        }
        return 'k'.strtoupper($hexStr);

    }


    /**
     * @desc 根据长类型解析短类型
     * @param int $type 长类型，详见  http://wiki.baidu.com/pages/viewpage.action?pageId=36888523
     * @param string $childDataUnits 短类型待解析内容
     */
    private function parseChildUnits($type, $childDataUnits){
        //echo $this->intToHex($type)."<br>";
        switch ($type)
        {
            case 0x8000:
                $this->parseK8000($childDataUnits);
                break;
            case 0x8001:
                $this->parseK8001($childDataUnits);
                break;
            case 0x8400:
                $this->parseK8400($childDataUnits);
                break;
            case 0x8401:
                $this->parseK8401($childDataUnits);
                break;
            case 0x8402:
                $this->parseK8402($childDataUnits);
                break;
            case 0x8403:
                $this->parseK8403($childDataUnits);
                break;
            case 0x8405:
                $this->parseK8405($childDataUnits);
                break;
            case 0x8406:
                $this->parseK8406($childDataUnits);
                break;
            case 0x8407:
                $this->parseK8407($childDataUnits);
                break;
            case 0x8408:
                $this->parseK8408($childDataUnits);
                break;
            case 0x8409:
                $this->parseK8409($childDataUnits);
                break;
            case 0x8410:
                $this->parseK8410($childDataUnits);
                break;
            default:
                ;
        }
    }

    /**
     * @desc 析短长类型K8000，已pb化
     * @param string $dataUnits K8000待解析内容
     */
    private function parseK8000($dataUnits){
        $k8000 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $content = unpack("a".strlen($dataUnit),$dataUnit);
                $k8000[$this->intToHex($subtype)] = $content[1];
                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits=substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits='';
                }
            }else{
                $dataUnits='';
            }
            $dataLen = strlen($dataUnits);
        }

        $publicinfo = $this->user_behavior->getPublicInfo();
        if($k8000['k0000'] != null && $k8000['k0000']!='') {
            $this->user_behavior->getCommon()->getDeviceId()->setImei($k8000['k0000']);
        }
        if($k8000['k0002'] != null && $k8000['k0002']!='') {
            $publicinfo->setBsinfo($k8000['k00002']);
        }
        if($k8000['k0003'] != null && $k8000['k0003']!='') {
            $publicinfo->setResolutionV(intval($k8000['k0003']));
        }
        if($k8000['k0004'] != null && $k8000['k0004']!='') {
            $publicinfo->setResolutionH(intval($k8000['k0004']));
        }
        if($k8000['k0005'] != null && $k8000['k0005']!='') {
            $publicinfo->setTerminalType($k8000['k0005']);
        }
        if($k8000['k0006'] != null && $k8000['k0006']!='') {
            $publicinfo->setOsInfo($k8000['k0006']);
        }
        if($k8000['k0007'] != null && $k8000['k0007']!='') {
            $publicinfo->setMonthFlow(intval($k8000['k0007']));
        }

        if($k8000['k0008'] != null && $k8000['k0008']!='') {
            if(strstr($publicinfo->getPlatform(), 'i') === false) {
                $publicinfo->setNetType($k8000['k0008']);
            } else {
                $publicinfo->setOperatorType($k8000['k0008']);
            }

        }
        if($k8000['k0009'] != null && $k8000['k0009']!='') {
            $publicinfo->setPhoneNumber($k8000['k0009']);
        }
        if($k8000['k000A'] != null && $k8000['k000A']!='') {
            $publicinfo->setIsroot(intval($k8000['k000A']));
        }
        if(($k8000['k000D'] != null && $k8000['k000D']!='') || ($k8000['k000E'] != null && $k8000['k000E']!='')) {
            $trace_time = new TraceTime();
            $trace_time->setStartTime(intval($k8000['k000D']));
            $trace_time->setEndTime(intval($k8000['k000E']));
            $publicinfo->setTraceTime($trace_time);
        }
        if($k8000['k000F'] != null && $k8000['k000F']!='') {
            $publicinfo->setTerminalBrand($k8000['k000F']);
        }
        if($k8000['k0010'] != null && $k8000['k0010']!='') {
            $publicinfo->setManufacturer($k8000['k0010']);
        }
        if($k8000['k0013'] != null && $k8000['k0013']!='') {
            $publicinfo->setCpuInfo($k8000['k0013']);
        }
        if($k8000['k0014'] != null && $k8000['k0014']!='') {
            $publicinfo->setMemInfo($k8000['k0014']);
        }
        if($k8000['k0015'] != null && $k8000['k0015']!='') {
            $publicinfo->setScreenSize($k8000['k0015']);
        }
        if($k8000['k0016'] != null && $k8000['k0016']!='') {
            $publicinfo->setNetType($k8000['k0016']);
        }
        if(isset($k8000['k0017']) && $k8000['k0017'] != null && $k8000['k0017']!='') {
            $publicinfo->setCollectPeriod($k8000['k0017']);
        }
        if($k8000['k0018'] != null && $k8000['k0018']!='') {
            $publicinfo->setUserAccount($k8000['k0018']);
        }
        //var_dump($k8000);
    }


    /**
     * @desc 解析长类型K8001，已pb化
     * @param string $dataUnits K8001待解析内容
     */
    private function parseK8001($dataUnits){
        $k8001 = array();
        $index = -1;
        $dataLen = strlen($dataUnits);
        $one_trace = null;
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $content = unpack("a".strlen($dataUnit),$dataUnit);

                if($subtype === 0x0000){
                    $index++;
                    $k8001[$index] = array();
                    if($one_trace != null) {
                        $this->user_behavior->addAppTraces($one_trace);
                    }

                    $one_trace = new AppTrace();
                }

                if($subtype === 0x0002 || $subtype === 0x0006 || ($subtype >= 0x0009 && $subtype <= 0x000E)){
                    if(strlen($dataUnit) === 4){
                        $content=unpack("V",$dataUnit);
                        if($content[1] < 2147483647 && $content[1] > -2147483647) {
                            $key = $this->intToHex($subtype);
                            $k8001[$index][$key] = $content[1];
                            if($key === 'k0009') {
                                $one_trace->setUseTimes($content[1]);
                            }elseif($key === 'k000A') {
                                $one_trace->setPanelTimes(intval($content[1]));
                            }elseif($key === 'k000B') {
                                $one_trace->setUrlBoxTimes(intval($content[1]));
                            }elseif($key === 'k000C') {
                                $one_trace->setUrlBoxEnterTimes(intval($content[1]));
                            }elseif($key === 'k000D') {
                                $one_trace->setSearchBoxTimes(intval($content[1]));
                            }elseif($key === 'k000E') {
                                $one_trace->setSearchBoxEnterTimes(intval($content[1]));
                            }
                        }
                    }
                }else if($subtype === 0x0007){
                    if(strlen($dataUnit) >= 12){
                        $content1 = unpack("V",substr($dataUnit, 0,4));
                        $content2 = unpack("V",substr($dataUnit, 4,4));
                        $content3 = unpack("V",substr($dataUnit, 8,4));
                        $k8001[$index]['begintime'] = $content1[1];
                        $k8001[$index]['endtime'] = $content2[1];
                        $k8001[$index]['seconds'] = $content3[1];
                        $one_trace->setUseSeconds($content3[1]);
                    }
                }else if($subtype === 0x0008){
                    $app_usetime_log_list = array();
                    $logCnt = strlen($dataUnit)/8;
                    for($logi = 0; $logi < $logCnt; $logi++){
                        $content1 = unpack("V",substr($dataUnit, $logi*8,4));
                        $k8001[$index][$this->intToHex($subtype)][$logi]['begintime'] = $content1[1];
                        $content2 = unpack("V",substr($dataUnit, $logi*8+4,4));
                        $k8001[$index][$this->intToHex($subtype)][$logi]['endtime'] = $content2[1];
                        $app_usetime_log = new TraceTime();
                        $app_usetime_log->setStartTime($content1[1]);
                        $app_usetime_log->setEndTime($content2[1]);
                        array_push($app_usetime_log_list, $app_usetime_log);
                    }
                    $one_trace->setTraceLogsList($app_usetime_log_list);
                }else{
                    $content = unpack("a".strlen($dataUnit),$dataUnit);
                    $key = $this->intToHex($subtype);
                    $k8001[$index][$key] = $content[1];
                    if($key === 'k0003') {
                        $one_trace->setPackageName($content[1]);
                    }elseif($key === 'k0001') {
                        $one_trace->setVersionName($content[1]);
                    }elseif($key === 'k0000') {
                        $one_trace->setAppName($content[1]);
                    }elseif($key === 'k0004') {
                        $one_trace->setAppId($content[1]);
                    }elseif($key === 'k0005') {
                        $one_trace->setAppStatus(intval($content[1]));
                    }elseif($key === 'k0010') {
                        $one_trace->setInstallTs(intval($content[1])/1000);
                    }elseif($key === 'k0011') {
                        $one_trace->setAppChange($content[1]);
                    }
                }

                if(strlen($dataUnits) > strlen($dataUnit)+6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        //var_dump($k8001);
        if($one_trace != null) {
            $this->user_behavior->addAppTraces($one_trace);
        }
    }


    /**
     * @desc 解析长类型K8400，已pb
     * @param string $dataUnits K8400待解析内容
     */
    private function parseK8400($dataUnits){
        $k8400 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $content = unpack("a".strlen($dataUnit),$dataUnit);
                $key = $this->intToHex($subtype);
                $k8400[$key] = $content[1];
                $one_setting = new UseSetting();
                $one_setting->setSettingId($subtype);
                $one_setting->setSettingValue($content[1]);
                $this->user_behavior->addUseSettings($one_setting);

                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        //var_dump($k8400);
    }

    /**
     * @desc 解析长类型K8401，已pb
     * @param string $dataUnits K8401待解析内容
     */
    private function parseK8401($dataUnits){
        $k8401 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype) || $dataLen>0){
                $dataLen = strlen($dataUnit);
                while($dataLen>0){
                    $funKey = unpack("v",substr($dataUnit, 0,1)."\x00");
                    $dataUnit = substr($dataUnit, 1,(strlen($dataUnit)-1));
                    if(strlen($dataUnit)>0){
                        $content = unpack("v",substr($dataUnit, 0,2));
                        $dataUnit = substr($dataUnit, 2,(strlen($dataUnit)-2));
                        $k8401[$this->intToHex($subtype)][$this->intToHex($funKey[1],2)]=intval($content[1]);
                    }
                    $dataLen = strlen($dataUnit);
                }

                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        if(!empty($k8401)) {
            foreach ($k8401 as $key => $value) {
                $onelog = new CustomLog();
                $onelog->setStrKey($key);
                $onelog->setStrVal(json_encode($value));
                $this->user_behavior->addK8401($onelog);
            }
        }

        //var_dump($k8401);

    }

    /**
     * @desc 解析长类型K8402，已pb
     * @param string $dataUnits K8402待解析内容
     */
    private function parseK8402($dataUnits){
        $k8402 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $content = unpack("V",$dataUnit);
                if(intval($content[1]) < 2147483647 && intval($content[1]) > -2147483647) {
                    $k8402[$this->intToHex($subtype)] = intval($content[1]);
                }
                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        if(!empty($k8402)) {
            foreach ($k8402 as $key => $value) {
                $onelog = new CustomLog();
                $onelog->setStrKey($key);
                $onelog->setUseTimes($value);
                $this->user_behavior->addK8402($onelog);
            }
        }
        //var_dump($k8402);
    }

    /**
     * @desc 解析长类型K8403，已pb
     * @param string $dataUnits K8403待解析内容
     */
    private function parseK8403($dataUnits){
        $k8403 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $content = unpack("V",$dataUnit);
                if(intval($content[1]) < 2147483647 && intval($content[1]) > -2147483647) {
                    $k8403[$this->intToHex($subtype)] = intval($content[1]);
                }
                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        if(!empty($k8403)) {
            foreach ($k8403 as $key => $value) {
                $onelog = new CustomLog();
                $onelog->setStrKey($key);
                $onelog->setUseTimes($value);
                $this->user_behavior->addK8403($onelog);
            }
        }
        //var_dump($k8403);
    }

    /**
     * @desc 解析长类型K8405，已pb
     * @param string $dataUnits K8405待解析内容
     */
    private function parseK8405($dataUnits){
        $k8405 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen > 0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $ksubtype = $this->intToHex($subtype);
                $k8405[$ksubtype] = array();
                $content = unpack("a".strlen($dataUnit),$dataUnit);
                $allData = $content[1];
                $allData = str_replace("\r\n", "\n", $allData);
                $allData = explode("\n", $allData);
                $allCnt = count($allData);
                for($i = 0; $i < $allCnt; $i++){
                    $oneDataArr = explode("\t", $allData[$i]);

                    $size = count($oneDataArr);
                    if ($size >= 2){
                        $times = 0;
                        $times = intval($oneDataArr[$size-1]);
                        if($times > 200000000 || $times < -200000000 ) {
                            $times = 0;
                        }

                        //兼容iPhone数字转字符问题BUG
                        if($times === 0 && strlen($oneDataArr[$size-1]) === 4){
                            try {
                                $timesarr = unpack("V",substr($oneDataArr[$size-1], 0,4));
                                $times = $timesarr[1];
                                if($times > 200000000 || $times < -200000000 ) {
                                    $times = 0;
                                }
                            } catch (Exception $e) {
                            }
                        }
                        unset($oneDataArr[$size-1]);
                        $ut = urlencode(implode("\t", $oneDataArr));
                        if($ut != ''){
                            $ut='s'.$ut;
                            if(isset($k8405[$ksubtype][$ut])){
                                $k8405[$ksubtype][$ut] = $k8405[$ksubtype][$ut] + $times;
                            }else{
                                $k8405[$ksubtype][$ut] = $times;
                            }
                        }

                    }
                }


                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        if(!empty($k8405)) {
            //$k8407_list = $this->user_behavior->getK8407List();
            foreach ($k8405 as $key => $value) {
                $onelog = new CustomLog();
                $onelog->setStrKey($key);
                $onelog->setStrVal(json_encode($value));
                $this->user_behavior->addK8405($onelog);
            }
        }
        //var_dump($k8405);
    }



    /**
     * @desc 解析长类型K8406，已pb
     * @param string $dataUnits K8406待解析内容
     */
    private function parseK8406($dataUnits){
        $k8406 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen>0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $content = unpack("V",$dataUnit);
                if(intval($content[1]) < 2147483647 && intval($content[1]) > -2147483647) {
                    $k8406[$this->intToHex($subtype)] = intval($content[1]);
                }
                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        if(!empty($k8406)) {
            foreach ($k8406 as $key => $value) {
                $onelog = new CustomLog();
                $onelog->setStrKey($key);
                $onelog->setUseTimes($value);
                $this->user_behavior->addK8406($onelog);
            }
        }
        //var_dump($k8406);
    }

    /**
     * @desc 解析长类型K8407，已pb
     * @param string $dataUnits K8407待解析内容
     */
    private function parseK8407($dataUnits){
        $k8407 = array();
        $dataLen = strlen($dataUnits);
        while($dataLen > 0){
            $dataUnit = '';
            $subtype = 0;
            if($this->parseTypeLenUnits($dataUnits, $dataUnit, $subtype)){
                $ksubtype = $this->intToHex($subtype);
                $k8407[$ksubtype] = array();
                $content = unpack("a".strlen($dataUnit),$dataUnit);
                $allData = $content[1];
                $allData = str_replace("\r\n", "\n", $allData);
                $allData = explode("\n", $allData);
                $allCnt = count($allData);
                for($i = 0; $i < $allCnt; $i++){
                    $oneDataArr = explode("\t", $allData[$i]);

                    $size = count($oneDataArr);
                    if ($size >= 2){
                        $times = 0;
                        $times = intval($oneDataArr[$size-1]);
                        if($times > 200000000 || $times < -200000000 ) {
                            $times = 0;
                        }

                        //兼容iPhone数字转字符问题BUG
                        if($times === 0 && strlen($oneDataArr[$size-1]) === 4){
                            try {
                                $timesarr = unpack("V",substr($oneDataArr[$size-1], 0,4));
                                $times = $timesarr[1];
                                if($times > 200000000 || $times < -200000000 ) {
                                    $times = 0;
                                }
                            } catch (Exception $e) {
                            }
                        }
                        unset($oneDataArr[$size-1]);
                        $ut = urldecode(implode("\t", $oneDataArr));
                        if($ut != ''){
                            if(isset($k8407[$ut])){
                                $k8407[$ksubtype][$ut] = $k8407[$ksubtype][$ut] + $times;
                            }else{
                                $k8407[$ksubtype][$ut] = $times;
                            }
                        }

                    }
                }


                if(strlen($dataUnits) > strlen($dataUnit) + 6) {
                    $dataUnits = substr($dataUnits,-(strlen($dataUnits)-strlen($dataUnit)-6));
                } else {
                    $dataUnits = '';
                }
            }else{
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        if(!empty($k8407)) {
            //$k8407_list = $this->user_behavior->getK8407List();
            foreach ($k8407 as $key => $value) {
                $onelog = new CustomLog();
                $onelog->setStrKey($key);
                $onelog->setStrVal(json_encode($value));
                $this->user_behavior->addK8407($onelog);
            }
        }
        //var_dump($k8407);
    }

    /**
     * @desc 解析广告数据长类型K8408，已pb化
     * @param string $dataUnits K8408待解析内容
     */
    private function parseK8408($dataUnits){
        $k8408 = array();
        $time = 0;
        $dataLen = strlen($dataUnits);
        while($dataLen >= 7){

            $actionid = 0;
            $adpid = 0;
            $adid = 0;
            $count = 0;
            $strlen = 0;
            $strval = '';
            $actionid = intval(unpack('C',substr($dataUnits,0,1))[1]);
            while($actionid == 0xFF) {
                $time = unpack('V',substr($dataUnits,1,4))[1];
                $dataUnits = substr($dataUnits,5);
                $actionid = intval(unpack('C',substr($dataUnits,0,1))[1]);
            }
            if(strlen($dataUnits)>=8) {
                //$actionid = intval(unpack('C',substr($dataUnits,0,1))[1]);
                $areaid = intval(unpack('v',substr($dataUnits,1,2))[1]);
                $positionid = intval(unpack('C',substr($dataUnits,3,1))[1]);
                $adid = intval(unpack('v',substr($dataUnits,4,2))[1]);
                $count = intval(unpack('v',substr($dataUnits,6,2))[1]);
                $dataUnits = substr($dataUnits,8);
                if ($count % 2 ==1) {
                    $strlen = intval(unpack('v',substr($dataUnits,0,2))[1]);
                    $strval = substr($dataUnits,2,$strlen);
                    $dataUnits = substr($dataUnits,2+$strlen);
                }
                $count = $count >> 1;

                $k8408[] = array(
                    'time' => $time,
                    'actionid' => $actionid,
                    'areaid' => $areaid,
                    'positionid' => $positionid,
                    'adid' => $adid,
                    'count' => $count,
                    'strlen' => $strlen,
                    'strval' => $strval,
                );
                $one_trace = new AdvertiseTrace();
                $one_trace->setActionTime($time);
                $one_trace->setActionId($actionid);
                $one_trace->setAreaId($areaid);
                $one_trace->setPositionId($positionid);
                $one_trace->setAdvertiseId($adid);
                $one_trace->setUseTimes($count);
                if($strval != '') {
                    $one_trace->setStrVal($strval);
                }
                $this->user_behavior->addAdvertiseTraces($one_trace);
            } else {
                $dataUnits='';
            }
            $dataLen = strlen($dataUnits);
        }
        if(count($k8408) > 0){
            $this->k8408 = $k8408;
        }
        //var_dump($k8408);



    }



    /**
     * @desc K8408写入实时化日志
     * @param string $dataUnits K8408待解析内容
     */
    private function realtime_K8408($k8408, $logtype = 3){
        $publicinfo = $this->user_behavior->getPublicInfo();
        $common = $this->user_behavior->getCommon();

        $bdinput_realtime = new BdinputRealtime();
        $bdinput_realtime->setLogType(intval($logtype));
        $my_publicinfo = $bdinput_realtime->getPublicInfo();
        $my_common = $bdinput_realtime->getCommon();
        $my_common->setLogid($common->getLogid());

        $my_common->setTimestamp($common->getTimestamp());
        $my_publicinfo->setBdinputUid($publicinfo->getBdinputUid());
        $my_common->getDeviceId()->setCuid($common->getDeviceId()->getCuid());
        $my_common->getDeviceId()->setImei($common->getDeviceId()->getImei());


        $my_publicinfo->setPlatform($publicinfo->getPlatform());
        $my_publicinfo->setOs($publicinfo->getOs());
        $my_publicinfo->setSoftwareVersion($publicinfo->getSoftwareVersion());
        $my_publicinfo->setChannel($publicinfo->getChannel());
        $my_publicinfo->setCurrentChannel($publicinfo->getCurrentChannel());
        $my_publicinfo->setOsVersion($publicinfo->getOsVersion());
        $my_publicinfo->setIp($publicinfo->getIp());

        foreach ($k8408 as $item) {
            //{eid:xxx,aid:xxx,ts:xxx,str:xxx}
            $onelog = new RtLog();
            $onelog->setEventId(''.$item['areaid']);
            $onelog->setActionId(intval($item['actionid']));
            $strdata = array();
            if(strlen($item['strval']) > 0){
                $strdata = json_decode($item['strval'], true);
                if(is_null($strdata) || !is_array($strdata)){
                    $strdata = array(
                        'var' => $item['strval'],
                    );
                }
            }

            if(isset($strdata['ts'])){
                $onelog->setActionTs((intval($strdata['ts'])));
            }



            $strdata['pid'] = $item['positionid'];
            $strdata['adid'] = $item['adid'];
            $strdata['count'] = $item['count'];
            $onelog->setStrVal(json_encode($strdata));
            $bdinput_realtime->addRtlogs($onelog);


        }

        $str = $bdinput_realtime->serialize();
        //if($str == null) {
        //    print 'Pb Serial Failed';
        //}
        // 使用b2log库来打印日志
        $ret = b2log_write('bdinput_realtime', $str);





    }


    /**
     * @desc 解析广告数据长类型K8409，已pb化
     * @param string $dataUnits K8409待解析内容
     */
    private function parseK8409($dataUnits){
        $k8409 = array();
        $time = 0;
        $dataLen = strlen($dataUnits);
        while($dataLen >= 4){

            $actionid = 0;
            $count = 0;
            $strlen = 0;
            $strval = '';
            $actionid = intval(unpack('v',substr($dataUnits,0,2))[1]);
            while($actionid == 0xFFFF) {
                $time = unpack('V',substr($dataUnits,2,4))[1];
                $dataUnits = substr($dataUnits,6);
                $actionid = intval(unpack('v',substr($dataUnits,0,2))[1]);
            }
            if(strlen($dataUnits) >= 4) {
                //$actionid = intval(unpack('v',substr($dataUnits,0,2))[1]);
                $count = intval(unpack('v',substr($dataUnits,2,2))[1]);
                $dataUnits = substr($dataUnits,4);
                if ($actionid >= 50000) {
                    $strlen = intval(unpack('v',substr($dataUnits,0,2))[1]);
                    $strval = substr($dataUnits,2,$strlen);
                    $dataUnits = substr($dataUnits,2+$strlen);
                }
                $k8409[] = array(
                    'time' => $time,
                    'actionid' => $actionid,
                    'count' => $count,
                    'strlen' => $strlen,
                    'strval' => $strval,
                );
                $one_trace = new UseTrace();
                $one_trace->setActionTime($time);
                $one_trace->setActionId($actionid);
                $one_trace->setUseTimes($count);
                if($strval != '') {
                    $one_trace->setStrVal($strval);
                }
                $this->user_behavior->addUseTraces($one_trace);
            } else {
                $dataUnits = '';
            }
            $dataLen = strlen($dataUnits);
        }
        //var_dump($k8409);
    }


    /**
     * @desc 解析客户端debug数据长类型K8410，已pb化
     * @param string $dataUnits K8410待解析内容
     */
    private function parseK8410($dataUnits){
        $dataLen = strlen($dataUnits);
        while($dataLen >= 8){

            $actionid = 0;
            $count = 0;
            $strlen = 0;
            $strval = '';
            $ts = unpack('V',substr($dataUnits,0,4))[1];
            $actionid = intval(unpack('v',substr($dataUnits,4,2))[1]);
            $strlen = intval(unpack('v',substr($dataUnits,6,2))[1]);
            $strval = substr($dataUnits,8,$strlen);
            $dataUnits = substr($dataUnits,8+$strlen);
            $dataLen = strlen($dataUnits);

            $one_clog = new CustomLog();
            $one_clog->setActionTs($ts);
            $one_clog->setActionId($actionid);
            $one_clog->setStrVal($strval);
            $this->user_behavior->addK8410($one_clog);

        }
        //var_dump($k8409);
    }


    /**
     * @desc 分离6字节（2字节类型+4字节数据长度）数据结构类型
     * @param string $data 待解析原始数据
     * @param string $childDataUnits 分离出来的子目录数据
     * @param int $type 分离出来的子目录数据对应数据类型
     * @return boolean
     */
    private function parseTypeLenUnits($data,&$childDataUnits,&$type=0){
        if(strlen($data) >= 6){
            $tmpArrType    = unpack('v',substr($data,0,2));
            $type = intval($tmpArrType[1]);
            $tmpArrLength = unpack('V',substr($data,2,4));
            $dataLength = intval($tmpArrLength[1]);
            if($dataLength+6 <= strlen($data)){
                $childDataUnits = substr($data,6,$dataLength);
                return true;
            }
        }

        return false;
    }

    /**
     * @desc 判断数据格式是否符合规范
     * @return boolean
     */
    private function  isBBMFormated(){
        if(strlen($this->data) >= 12){
            $flag = substr($this->data,0,4);
            $length = unpack('V',substr($this->data,4,4));
            $version = unpack('V',substr($this->data,8,4));
            if($flag === 'bdmi' && (strlen($this->data)-12) === intval($length[1])){
                if(strlen($this->data)-12 > 6){
                    $this->data = substr($this->data,12,intval($length[1]));
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * @desc 自定义数据收集接口。
     * @route({"POST", "/bn"})    【注】需要POST文件bn3，$_FILES['bn3']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"mac", "$._GET.mac"}) mac地址
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param({"logtype", "$._GET.$logtype"}) 日志类型
     * @param({"bn3", "$._FILES.bn3.tmp_name"}) 数据文件
     * @param({"bn2", "$._FILES.bn2.tmp_name"}) iOS数据文件
     * @param({"bn4", "$._FILES.bn4.tmp_name"}) Mac数据文件
     * @param({"zipct", "$._GET.zipct"}) iOS崩溃日志zip包统计崩溃次数
     * @param({"screen_w", "$._GET.screen_w"}) 屏幕宽度
     * @param({"screen_h", "$._GET.screen_h"}) 屏幕高度
     * @param({"model", "$._GET.model"}) 客户端机型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }
     */
    function bn(
        $cuid = null,
        $uid = null,
        $mac = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $logtype = null,
        $bn3 = null,
        $bn2 = null,
        $bn4 = null,
        $zipct = null,
        $screen_w = null,
        $screen_h = null,
        $model = null
    ){
        $res = array();

        //判断是否为欧盟用户，是则直接返回不存储
        if (true === Util::isEu($cuid)) {
            $res['info'] = 'Success';
            $res['status'] = 0;
            return $res;
        }

        if($bn3 != null || $bn2 != null || $bn4 != null){
            try{
                $clientip = Util::getClientIP();
                $file_data = '';
                if($bn3 != null){
                    $file_data = file_get_contents($bn3);
                } elseif($bn2 != null){
                    $file_data = file_get_contents($bn2);
                } elseif($bn4 != null){
                    $file_data = file_get_contents($bn4);
                }

                $common = new Common();
                $common->setLogid(''.time());
                $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                $publicinfo = new PublicInfoV2();
                $file_size = strlen($file_data);
                $publicinfo->setDataSize($file_size);
                if($platform != null) {
                    $publicinfo->setPlatform($platform);
                    if(strpos($platform, 'a') !== false) {
                        $publicinfo->setOs(OSType::ANDROID);
                    } elseif(strpos($platform, 'i') !== false) {
                        $publicinfo->setOs(OSType::IOS);
                    } else {
                        $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                    }
                }
                if($version != null) {
                    $publicinfo->setSoftwareVersion($version);
                }
                if($channel != null) {
                    $publicinfo->setChannel($channel);
                }
                if($current_channel != null) {
                    $publicinfo->setCurrentChannel($current_channel);
                }
                if($os_version != null) {
                    $publicinfo->setOsVersion($os_version);
                }
                if($clientip != null) {
                    $publicinfo->setIp($clientip);
                }
                if($screen_w != null) {
                    $publicinfo->setResolutionV(intval($screen_w));
                }
                if($screen_h != null) {
                    $publicinfo->setResolutionH(intval($screen_h));
                }
                if($model != null) {
                    $publicinfo->setTerminalType($model);
                }
                if($uid != null) {
                    $publicinfo->setBdinputUid(urldecode($uid));
                }
                if($cuid != null) {
                    $common->getDeviceId()->setCuid($cuid);

                    $parts = explode('|',$cuid, 2);
                    if(count($parts)==2){
                        $common->getDeviceId()->setImei(strrev($parts[1]));
                        $publicinfo->setBdinputUid('bd_'.$parts[1]);
                    }
                }
                if((!$publicinfo->hasBdinputUid() || $publicinfo->getBdinputUid()==='bd_0') && $mac != null) {
                    $publicinfo->setBdinputUid('bd_'.$mac);
                }
                if($idfa != null) {
                    $common->getDeviceId()->setIdfa($idfa);
                }
                if($opuid != null) {
                    $common->getDeviceId()->setOpenUdid($opuid);
                }
                if($zipct == null) {

                    while (strlen($file_data)>12){
                        $oneloglen_arr=unpack('V', substr($file_data,8,4));
                        $oneloglen=$oneloglen_arr[1];
                        $suffix=unpack('a8', substr($file_data,0,8));
                        $logtype=trim($suffix[1]);
                        if(preg_match('/^[a-zA-Z0-9\_-]{1,8}$/', $logtype)){
                            if($logtype === 'CRASHLOG' || $logtype === 'EXCEPLOG') {
                                $content = substr($file_data,12,$oneloglen);
                                if($logtype === 'EXCEPLOG' && strcmp($version,"7-6") < 0) { //去除低于7-6版本的异常EXCEPLOG
                                    $content = '';
                                }
                                while(strlen($content)>9) {
                                    $desc_len = 0;
                                    $desc = '';
                                    if($publicinfo->getOs()==OSType::IOS || $platform == 'm1') {
                                        $desc_len_arr = unpack("V",substr($content,0,4));
                                        $desc_len = $desc_len_arr[1];
                                        $desc = str_replace(".", "_", substr($content,4,$desc_len));
                                    } else {
                                        $desc_len_arr = unpack("V",substr($content,0,4));  //4字节hashcode
                                        $desc = $desc.$desc_len_arr[1];
                                    }

                                    $count_arr = unpack("C*", substr($content,4+$desc_len,2)); //2字节count
                                    $count = intval($count_arr[1]);
                                    $len_arr = unpack('V', substr($content,6+$desc_len,4)); //4+2, 4字节长度
                                    $len = $len_arr[1];
                                    $crash_content = gzuncompress(substr($content,14+$desc_len, $len-4)); // 10+4
                                    $this->user_behavior = new BdinputCustom();
                                    $this->user_behavior->setLogType(LogCollector::CLIENTLOG);
                                    $this->user_behavior->setCommon($common);
                                    $this->user_behavior->setPublicInfo($publicinfo);
                                    $onelog = new CustomLog();
                                    $onelog->setActionId($this->clientlog_arr[$logtype]);
                                    $onelog->setStrKey($desc.".JSON");
                                    $onelog->setStrVal(json_encode($crash_content));
                                    $onelog->setUseTimes($count);
                                    $this->user_behavior->addCustomLogs($onelog);
                                    $str = $this->user_behavior->serialize();
                                    if($str == null) {
                                        $res['info'] = 'Pb Serial Failed';
                                        $res['status'] = 1;
                                        return $res;
                                    }
                                    // 使用b2log库来打印日志
                                    $ret = b2log_write('bdinput_custom', $str);
                                    if($ret == false) {
                                        $res['info'] = 'Pb Write Failed';
                                        $res['status'] = 1;
                                        return $res;
                                    }
                                    $content=substr($content, 10+$len+$desc_len);
                                }

                            } elseif($logtype === 'ARERRLOG') {
                                $content = substr($file_data,12,$oneloglen);
                                //两次AES解密
                                $aes_decode = bd_AES_Decrypt(bd_AES_Decrypt($content));
                                if(strlen($aes_decode) >= 20){
                                    //解析20字节头信息
                                    $data_crc32 = unpack("V", substr($aes_decode, 0 , 4))[1];
                                    $ctime = unpack("V", substr($aes_decode, 4 , 4))[1];
                                    $block_data_size = unpack("V", substr($aes_decode, 8 , 4))[1];
                                    $total_size = unpack("V", substr($aes_decode, 12 , 4))[1];
                                    $block_id = unpack("v", substr($aes_decode, 16 , 2))[1];
                                    $block_cnt = unpack("v", substr($aes_decode, 18 , 2))[1];

                                    //舍去头信息,做BASE64编码
                                    $b64_encode = trim(bd_B64_Encode(substr($aes_decode, 20), 0));

                                    $this->user_behavior = new BdinputCustom();
                                    $this->user_behavior->setLogType(LogCollector::CLIENTLOG);
                                    $this->user_behavior->setCommon($common);
                                    $this->user_behavior->setPublicInfo($publicinfo);
                                    $onelog = new CustomLog();
                                    $onelog->setActionId($this->clientlog_arr[$logtype]);
                                    $onelog->setActionTs($ctime); //创建时间
                                    $onelog->setActionTime($block_data_size); //当前块大小
                                    $onelog->setStrKey($data_crc32 . '_' . $block_cnt. '_'. $total_size.".BASE64"); //crc32,总块数,总大小作为key
                                    $onelog->setStrVal($b64_encode); //BASE64后的实际RSA片内容
                                    $onelog->setUseTimes($block_id); //当前数据块id
                                    $this->user_behavior->addCustomLogs($onelog);
                                    $str = $this->user_behavior->serialize();
                                    if($str == null) {
                                        $res['info'] = 'Pb Serial Failed';
                                        $res['status'] = 1;
                                        return $res;
                                    }
                                    // 使用b2log库来打印日志
                                    $ret = b2log_write('bdinput_custom', $str);
                                    if($ret == false) {
                                        $res['info'] = 'Pb Write Failed';
                                        $res['status'] = 1;
                                        return $res;
                                    }
                                }



                            } else {
                                $content = gzuncompress(substr($file_data,16,$oneloglen-4));
                                $actionid = 0 ;
                                if(isset($this->clientlog_arr[$logtype])) {
                                    $actionid = $this->clientlog_arr[$logtype];
                                }
                                if(!strpos($logtype,"LOG")) {
                                    $content = trim(bd_B64_Encode($content,0));  //bd_B64_Encode会带结束符"\0"返回，需要trim
                                    $logtype = $logtype.".BASE64";
                                } else {
                                    $content = json_encode($content);
                                    $logtype = $logtype.".JSON";
                                }

                                $this->user_behavior = new BdinputCustom();
                                $this->user_behavior->setLogType(LogCollector::CLIENTLOG);
                                $this->user_behavior->setCommon($common);
                                $this->user_behavior->setPublicInfo($publicinfo);
                                $onelog = new CustomLog();
                                $onelog->setActionId($actionid);
                                $onelog->setStrKey($logtype);
                                $onelog->setStrVal($content);
                                $this->user_behavior->addCustomLogs($onelog);
                                $str = $this->user_behavior->serialize();
                                if($str == null) {
                                    $res['info'] = 'Pb Serial Failed';
                                    $res['status'] = 1;
                                    return $res;
                                }
                                // 使用b2log库来打印日志
                                $ret = b2log_write('bdinput_custom', $str);
                                if($ret == false) {
                                    $res['info'] = 'Pb Write Failed';
                                    $res['status'] = 1;
                                    return $res;
                                }


                                //$handle=fopen($storedir.'/'.$fileName.'.'.$logtype, 'w');
                                //fwrite($handle, $content);
                                //fclose($handle);
                            }

                        }

                        if(strlen($file_data) < $oneloglen){
                            break;
                        }
                        $file_data=substr($file_data, 12+$oneloglen);
                    }
                } else {//$zipct not null
                    $content = bd_AES_Decrypt($file_data);

                    if($content !== ''){
                        $content = trim(bd_B64_Encode($content,0));  //bd_B64_Encode会带结束符"\0"返回，需要trim
                        $logtype = 'IPERRZIP';
                        $this->user_behavior = new BdinputCustom();
                        $this->user_behavior->setLogType(LogCollector::CLIENTLOG);
                        $this->user_behavior->setCommon($common);
                        $this->user_behavior->setPublicInfo($publicinfo);
                        $onelog = new CustomLog();
                        $onelog->setActionId($this->clientlog_arr[$logtype]);
                        $onelog->setStrKey($logtype.".BASE64");
                        $onelog->setUseTimes(intval($zipct));
                        $onelog->setStrVal($content);
                        $this->user_behavior->addCustomLogs($onelog);
                        $str = $this->user_behavior->serialize();
                        if($str == null) {
                            $res['info'] = 'Pb Serial Failed';
                            $res['status'] = 1;
                            return $res;
                        }
                        // 使用b2log库来打印日志
                        $ret = b2log_write('bdinput_custom', $str);
                        if($ret == false) {
                            $res['info'] = 'Pb Write Failed';
                            $res['status'] = 1;
                            return $res;
                        }
                    } else {
                        $res['info'] = 'Decode Failed';
                        $res['status'] = 1;
                        return $res;
                    }
                }



                if(!isset($res['status']) || $res['status'] === 1) {
                    $res['info'] = 'Success';
                    $res['status'] = 0;
                }

            }catch (Exception $e){
                $res['info'] = 'Unknown Error';
                $res['status'] = 1;
                return $res;
            }
        } else {
            $res['info'] = 'File Not Found';
            $res['status'] = 1;
            return $res;
        }

        return $res;
    }


    /**
     * @desc 自定义数据收集接口。
     * @route({"POST", "/custom"})    【注】需要POST文件cfile，$_FILES['cfile']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"mac", "$._GET.mac"}) mac地址
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param({"logtype", "$._GET.logtype"}) 日志类型
     * @param({"actionid", "$._GET.actionid"}) 日志细分类型
     * @param({"logkey", "$._GET.logkey"}) 日志关键词
     * @param ({"cfile", "$._FILES.cfile.tmp_name"}) 自定义数据内容，先压缩再加密
     * @param({"screen_w", "$._GET.screen_w"}) 屏幕宽度
     * @param({"screen_h", "$._GET.screen_h"}) 屏幕高度
     * @param({"model", "$._GET.model"}) 客户端机型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }
     */
    function custom(
        $cuid = null,
        $uid = null,
        $mac = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $logtype = null,
        $actionid = null,
        $logkey = null,
        $cfile = null,
        $screen_w = null,
        $screen_h = null,
        $model = null
    ){
        $res = array();

        //判断是否为欧盟用户，是则直接返回不存储
        if (true === Util::isEu($cuid)) {
            $res['info'] = 'Success';
            $res['status'] = 1;
            return $res;
        }

        if(isset($cfile)){
            $clientip = Util::getClientIP();
            $data = null;
            $file_data = file_get_contents($cfile);
            $file_size = strlen($file_data);
            $zipdata = bd_RSA_DecryptByDK($file_data);
            if($zipdata ==='') {
                $zipdata = bd_B64_Decode($file_data, 0);
            }
            $data = gzuncompress($zipdata);
            if(($data == null || $data ==='') && $platform == 'i5'){
                $data = $zipdata;
            }
            if($data !== null && strlen($data)>0) {
                $this->user_behavior = new BdinputCustom();
                //先确定日志类型
                $this->user_behavior->setLogType(intval($logtype));
                $publicinfo = $this->user_behavior->getPublicInfo();
                $common = $this->user_behavior->getCommon();
                $common->setLogid(''.time());
                $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                if($uid != null) {
                    $publicinfo->setBdinputUid(urldecode($uid));
                }
                if($cuid != null) {
                    $common->getDeviceId()->setCuid($cuid);
                    $parts = explode('|',$cuid, 2);
                    if(count($parts)==2){
                        $common->getDeviceId()->setImei(strrev($parts[1]));
                        $publicinfo->setBdinputUid('bd_'.$parts[1]);
                    }
                }
                if((!$publicinfo->hasBdinputUid() || $publicinfo->getBdinputUid()==='bd_0') && $mac != null) {
                    $publicinfo->setBdinputUid('bd_'.$mac);
                }
                if($idfa != null) {
                    $common->getDeviceId()->setIdfa($idfa);
                }
                if($opuid != null) {
                    $common->getDeviceId()->setOpenUdid($opuid);
                }
                $publicinfo->setDataSize($file_size);
                if($platform != null) {
                    $publicinfo->setPlatform($platform);
                    if(strpos($platform, 'a') !== false) {
                        $publicinfo->setOs(OSType::ANDROID);
                    } elseif(strpos($platform, 'i') !== false) {
                        $publicinfo->setOs(OSType::IOS);
                    } else {
                        $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                    }
                }
                if($version != null) {
                    $publicinfo->setSoftwareVersion($version);
                }
                if($channel != null) {
                    $publicinfo->setChannel($channel);
                }
                if($current_channel != null) {
                    $publicinfo->setCurrentChannel($current_channel);
                }
                if($os_version != null) {
                    $publicinfo->setOsVersion($os_version);
                }
                if($clientip != null) {
                    $publicinfo->setIp($clientip);
                }
                if($screen_w != null) {
                    $publicinfo->setResolutionV(intval($screen_w));
                }
                if($screen_h != null) {
                    $publicinfo->setResolutionH(intval($screen_h));
                }
                if($model != null) {
                    $publicinfo->setTerminalType($model);
                }
                $onelog = new CustomLog();
                if($actionid != null) {
                    $onelog->setActionId(intval($actionid));
                }
                if($actionid != null) {
                    $onelog->setStrKey($logkey);
                }
                $onelog->setStrVal($data);
                $this->user_behavior->addCustomLogs($onelog);
                $str = $this->user_behavior->serialize();
                if($str == null) {
                    $res['info'] = 'Pb Serial Failed';
                    $res['status'] = 0;
                    return $res;
                }
                // 使用b2log库来打印日志
                $ret = b2log_write('bdinput_custom', $str);
                if($ret == false) {
                    $res['info'] = 'Pb Write Failed';
                    $res['status'] = 0;
                    return $res;
                }
                $res['info'] = 'Success';
                $res['status'] = 1;
                return $res;
            } else {
                $res['info'] = 'File Invalid';
                $res['status'] = 0;
                return $res;
            }
        } else {
            $res['info'] = 'File Not Found';
            $res['status'] = 0;
            return $res;
        }
    }


    /**
     * @desc 语音数据收集接口。
     * @route({"POST", "/vt"})    【注】需要POST文件cfile，$_FILES['an']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"mac", "$._GET.mac"}) mac地址
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param ({"an", "$._FILES.an.tmp_name"}) 自定义数据内容，先压缩再加密
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }
     */
    function voice(
        $cuid = null,
        $uid = null,
        $mac = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $an = null
    ){
        $res = array();

        //判断是否为欧盟用户，是则直接返回不存储
        if (true === Util::isEu($cuid)) {
            $res['info'] = 'Success';
            $res['status'] = 1;
            return $res;
        }

        if(isset($an)){
            $clientip = Util::getClientIP();
            $data = null;
            $file_data = file_get_contents($an);
            $file_size = strlen($file_data);
            $data = bd_RSA_DecryptByDK($file_data);

            //$data = gzuncompress($zipdata);
            if($data !== null && strlen($data)>0 && $data != 'null') {
                $this->user_behavior = new BdinputVoice();
                $publicinfo = $this->user_behavior->getPublicInfo();
                $common = $this->user_behavior->getCommon();
                $common->setLogid(''.time());
                $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                if($uid != null) {
                    $publicinfo->setBdinputUid(urldecode($uid));
                }
                if($cuid != null) {
                    $common->getDeviceId()->setCuid($cuid);
                    $parts = explode('|',$cuid, 2);
                    if(count($parts)==2){
                        $common->getDeviceId()->setImei(strrev($parts[1]));
                        $publicinfo->setBdinputUid('bd_'.$parts[1]);
                    }
                }
                if((!$publicinfo->hasBdinputUid() || $publicinfo->getBdinputUid()==='bd_0') && $mac != null) {
                    $publicinfo->setBdinputUid('bd_'.$mac);
                }
                if($idfa != null) {
                    $common->getDeviceId()->setIdfa($idfa);
                }
                if($opuid != null) {
                    $common->getDeviceId()->setOpenUdid($opuid);
                }
                $publicinfo->setDataSize($file_size);
                if($platform != null) {
                    $publicinfo->setPlatform($platform);
                    if(strpos($platform, 'a') !== false) {
                        $publicinfo->setOs(OSType::ANDROID);
                    } elseif(strpos($platform, 'i') !== false) {
                        $publicinfo->setOs(OSType::IOS);
                    } else {
                        $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                    }
                }
                if($version != null) {
                    $publicinfo->setSoftwareVersion($version);
                }
                if($channel != null) {
                    $publicinfo->setChannel($channel);
                }
                if($current_channel != null) {
                    $publicinfo->setCurrentChannel($current_channel);
                }
                if($os_version != null) {
                    $publicinfo->setOsVersion($os_version);
                }
                if($clientip != null) {
                    $publicinfo->setIp($clientip);
                }
                $this->user_behavior->setVoiceTrace($data);
                $str = $this->user_behavior->serialize();
                if($str == null) {
                    $res['info'] = 'Pb Serial Failed';
                    $res['status'] = 0;
                    return $res;
                }
                // 使用b2log库来打印日志
                $ret = b2log_write('bdinput_voice', $str);
                if($ret == false) {
                    $res['info'] = 'Pb Write Failed';
                    $res['status'] = 0;
                    return $res;
                } else {
                    $res['info'] = 'Success';
                    $res['status'] = 1;
                    return $res;
                }
            } else {
                $res['info'] = 'Invalid data';
                $res['status'] = 0;
                return $res;
            }
        } else {
            $res['info'] = 'No data';
            $res['status'] = 0;
            return $res;
        }
    }

    /**
     * @desc 实时数据收集接口。
     * @route({"POST", "/rt"})    【注】需要POST文件cfile，$_FILES['cfile']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"mac", "$._GET.mac"}) mac地址
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param({"log_type", "$._GET.log_type"}) 日志类型
     * @param ({"cfile", "$._FILES.cfile.tmp_name"}) 自定义数据内容，先压缩再加密
     * @param({"screen_w", "$._GET.screen_w"}) 屏幕宽度
     * @param({"screen_h", "$._GET.screen_h"}) 屏幕高度
     * @param({"model", "$._GET.model"}) 客户端机型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }
     */
    function realtime(
        $cuid = null,
        $uid = null,
        $mac = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $log_type = 0,
        $cfile = null,
        $screen_w = null,
        $screen_h = null,
        $model = null
    ){
        $res = array();


        if(isset($cfile)){
            $clientip = Util::getClientIP();
            $data = null;
            $file_data = file_get_contents($cfile);
            $file_size = strlen($file_data);

            $data = bd_RSA_DecryptByDK($file_data);
            if($data ==='') {
                //$data = $file_data;
                $data = bd_B64_Decode($file_data, 0);
            }

            if($data !== null && strlen($data)>0) {
                $this->user_behavior = new BdinputRealtime();
                //先确定日志类型
                $this->user_behavior->setLogType(intval($log_type));
                $publicinfo = $this->user_behavior->getPublicInfo();
                $common = $this->user_behavior->getCommon();
                $common->setLogid(''.time());
                $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                if($uid != null) {
                    $publicinfo->setBdinputUid(urldecode($uid));
                }
                if($cuid != null) {
                    $common->getDeviceId()->setCuid($cuid);
                    $parts = explode('|',$cuid, 2);
                    if(count($parts)==2){
                        $common->getDeviceId()->setImei(strrev($parts[1]));
                        $publicinfo->setBdinputUid('bd_'.$parts[1]);
                    }
                }
                if((!$publicinfo->hasBdinputUid() || $publicinfo->getBdinputUid()==='bd_0') && $mac != null) {
                    $publicinfo->setBdinputUid('bd_'.$mac);
                }
                if($idfa != null) {
                    $common->getDeviceId()->setIdfa($idfa);
                }
                if($opuid != null) {
                    $common->getDeviceId()->setOpenUdid($opuid);
                }
                $publicinfo->setDataSize($file_size);
                if($platform != null) {
                    $publicinfo->setPlatform($platform);
                    if(strpos($platform, 'a') !== false) {
                        $publicinfo->setOs(OSType::ANDROID);
                    } elseif(strpos($platform, 'i') !== false) {
                        $publicinfo->setOs(OSType::IOS);
                    } else {
                        $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                    }
                }
                if($version != null) {
                    $publicinfo->setSoftwareVersion($version);
                }
                if($channel != null) {
                    $publicinfo->setChannel($channel);
                }
                if($current_channel != null) {
                    $publicinfo->setCurrentChannel($current_channel);
                }
                if($os_version != null) {
                    $publicinfo->setOsVersion($os_version);
                }
                if($clientip != null) {
                    $publicinfo->setIp($clientip);
                }
                if($screen_w != null) {
                    $publicinfo->setResolutionV(intval($screen_w));
                }
                if($screen_h != null) {
                    $publicinfo->setResolutionH(intval($screen_h));
                }
                if($model != null) {
                    $publicinfo->setTerminalType($model);
                }

                $data_json = json_decode($data, true);
                foreach ($data_json as $item) {
                    //{eid:xxx,aid:xxx,ts:xxx,str:xxx}
                    if($log_type == '4') {
                        $onelog = new RtLog();
                        $onelog->setStrVal(json_encode($item));
                        $this->user_behavior->addRtlogs($onelog);
                    }
                    elseif(isset($item['eid']) && isset($item['aid'])) {
                        $onelog = new RtLog();
                        $onelog->setEventId($item['eid']);
                        $onelog->setActionId(intval($item['aid']));
                        if(isset($item['ts'])) {
                            $onelog->setActionTs((intval($item['ts'])));
                        }
                        if(isset($item['str'])) {
                            $onelog->setStrVal($item['str']);
                        }
                        $this->user_behavior->addRtlogs($onelog);
                    }

                }

                $str = $this->user_behavior->serialize();
                if($str == null) {
                    $res['info'] = 'Pb Serial Failed';
                    $res['status'] = 0;
                    return $res;
                }
                // 使用b2log库来打印日志
                $ret = b2log_write('bdinput_realtime', $str);
                if($ret == false) {
                    $res['info'] = 'Pb Write Failed';
                    $res['status'] = 0;
                    return $res;
                } else {
                    $res['info'] = 'Success';
                    $res['status'] = 1;
                    return $res;
                }
            } else {
                $res['info'] = 'File Unknown';
                $res['status'] = 0;
                return $res;
            }
        } else {
            $res['info'] = 'File Not Found';
            $res['status'] = 0;
            return $res;
        }
    }



    /**
     * @desc 自定义数据收集接口。
     * @route({"POST", "/rtq"})    【注】需要POST文件cfile，$_FILES['cfile']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"mac", "$._GET.mac"}) mac地址
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param({"log_type", "$._GET.log_type"}) 日志类型
     * @param ({"cfile", "$._FILES.cfile.tmp_name"}) 自定义数据内容，先压缩再加密
     * @param({"screen_w", "$._GET.screen_w"}) 屏幕宽度
     * @param({"screen_h", "$._GET.screen_h"}) 屏幕高度
     * @param({"model", "$._GET.model"}) 客户端机型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }
     */
    function realtimequery(
        $cuid = null,
        $uid = null,
        $mac = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $log_type = 2,
        $cfile = null,
        $screen_w = null,
        $screen_h = null,
        $model = null
    ){
        $res = array();

        if ($platform !== 'a1' && $platform !== 'p-a1-3-68' && $platform !== 'p-a1-3-69'){
            $res['info'] = 'Success';
            $res['status'] = 1;
            return $res;
        }


        if(isset($cfile)){
            $clientip = Util::getClientIP();
            $data = null;
            $file_data = file_get_contents($cfile);
            $file_size = strlen($file_data);
            $is_valid = false;


            if($file_data !== null && strlen($file_data)>0) {

                $this->user_behavior = new BdinputRealtime();
                //先确定日志类型
                $this->user_behavior->setLogType(intval($log_type));
                $publicinfo = $this->user_behavior->getPublicInfo();
                $common = $this->user_behavior->getCommon();
                $common->setLogid(''.time());
                $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                if($uid != null) {
                    $publicinfo->setBdinputUid(urldecode($uid));
                }
                if($cuid != null) {
                    $common->getDeviceId()->setCuid($cuid);
                    $parts = explode('|',$cuid, 2);
                    if(count($parts)==2){
                        $common->getDeviceId()->setImei(strrev($parts[1]));
                        $publicinfo->setBdinputUid('bd_'.$parts[1]);
                    }
                }
                if((!$publicinfo->hasBdinputUid() || $publicinfo->getBdinputUid()==='bd_0') && $mac != null) {
                    $publicinfo->setBdinputUid('bd_'.$mac);
                }
                if($idfa != null) {
                    $common->getDeviceId()->setIdfa($idfa);
                }
                if($opuid != null) {
                    $common->getDeviceId()->setOpenUdid($opuid);
                }
                $publicinfo->setDataSize($file_size);
                if($platform != null) {
                    $publicinfo->setPlatform($platform);
                    if(strpos($platform, 'a') !== false) {
                        $publicinfo->setOs(OSType::ANDROID);
                    } elseif(strpos($platform, 'i') !== false) {
                        $publicinfo->setOs(OSType::IOS);
                    } else {
                        $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                    }
                }
                if($version != null) {
                    $publicinfo->setSoftwareVersion($version);
                }
                if($channel != null) {
                    $publicinfo->setChannel($channel);
                }
                if($current_channel != null) {
                    $publicinfo->setCurrentChannel($current_channel);
                }
                if($os_version != null) {
                    $publicinfo->setOsVersion($os_version);
                }
                if($clientip != null) {
                    $publicinfo->setIp($clientip);
                }
                if($screen_w != null) {
                    $publicinfo->setResolutionV(intval($screen_w));
                }
                if($screen_h != null) {
                    $publicinfo->setResolutionH(intval($screen_h));
                }
                if($model != null) {
                    $publicinfo->setTerminalType($model);
                }

                while($file_data !== null && strlen($file_data)>0){
                    $length = unpack('V',substr($file_data,0,4));
                    $content = substr($file_data,4,$length[1]);
                    $data = bd_RSA_DecryptByDK($content);
                    if($data !== null && strlen($data)>0){
                        $is_valid = true;
                        $onelog = new RtLog();
                        $onelog->setStrVal(trim(bd_B64_Encode($data,11))); //输入法内部敏感数据seed
                        $this->user_behavior->addRtlogs($onelog);
                    }

                    $file_data=substr($file_data, 4+$length[1]);
                }

                if($is_valid){
                    $str = $this->user_behavior->serialize();
                    if($str == null) {
                        $res['info'] = 'Pb Serial Failed';
                        $res['status'] = 0;
                        return $res;
                    }
                    // 使用b2log库来打印日志
                    $ret = b2log_write('bdinput_realtime', $str);
                    if($ret == false) {
                        $res['info'] = 'Pb Write Failed';
                        $res['status'] = 0;
                        return $res;
                    } else {
                        $res['info'] = 'Success';
                        $res['status'] = 1;
                        return $res;
                    }

                }else{
                    $res['info'] = 'File Unknown';
                    $res['status'] = 0;
                    return $res;
                }

            } else {
                $res['info'] = 'File Not Found';
                $res['status'] = 0;
                return $res;
            }
        } else {
            $res['info'] = 'File Not Found';
            $res['status'] = 0;
            return $res;
        }
    }


    /**
     * @desc 全流式数据收集接口。
     * @route({"POST", "/st"}) 【注】需要POST文件bdst，$_FILES['bdst']
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @param({"uid", "$._GET.uid"}) 用户自动提交的百度输入法uid：bd_逆序imei
     * @param({"idfa", "$._GET.idfa"}) ios设备idfa
     * @param({"opuid", "$._GET.opuid"}) ios设备opuid
     * @param({"platform", "$._GET.platform"}) 应用平台号
     * @param({"version", "$._GET.version"}) 应用版本号
     * @param({"channel", "$._GET.from"}) 应用原始渠道号
     * @param({"current_channel", "$._GET.cfrom"}) 应用当前渠道号
     * @param({"os_version", "$._GET.rom"}) 设备rom版本
     * @param({"acmode", "$._GET.acmode"}) 激活方式
     * @param({"logtype", "$._GET.logtype"}) 日志类型
     * @param({"screen_w", "$._GET.screen_w"}) 屏幕宽度
     * @param({"screen_h", "$._GET.screen_h"}) 屏幕高度
     * @param ({"model", "$._GET.model"}) 客户端机型
     * @param ({"bdst", "$._FILES.bdst.tmp_name"}) 广告数据文件
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
    "status": 1|0,
    "info": "Format Error",

    }*/
    function st(
        $cuid = null,
        $uid = null,
        $idfa = null,
        $opuid = null,
        $platform = null,
        $version = null,
        $channel = null,
        $current_channel = null,
        $os_version = null,
        $acmode = null,
        $logtype = 0,
        $screen_w = null,
        $screen_h = null,
        $model = null,
        $bdst = null
    ){
        $res = array();
        if(isset($bdst)){
            $file_data = file_get_contents($bdst);
            $file_size = strlen($file_data);
            $decode_data = bd_RSA_DecryptByDK($file_data);
            //$decode_data = $file_data;
            //$this->data = gzuncompress(substr($zipdata,4,strlen($zipdata)-4));

            if(strlen($decode_data) >0 ) {
                try {
                    $bis = new Baidu_Mobileinputmethod_BdinputStatistic();
                    $bis->parseFromString($decode_data);  #解包
                    $common = new Baidu_Mobileinputmethod_Common();
                    $publicinfo = new Baidu_Mobileinputmethod_PublicInfo();
                    $clientip = Util::getClientIP();
                    $bis->setLogType($logtype);
                    $common->setLogid('' . time());
                    $common->setTimestamp($_SERVER['REQUEST_TIME'] * 1000);
                    if ($uid != null) {
                        $publicinfo->setBdinputUid(urldecode($uid));
                    }
                    $deviceid = new Baidu_Mobileinputmethod_DeviceID();
                    if ($cuid != null) {
                        $deviceid->setCuid($cuid);

                        $parts = explode('|', $cuid, 2);
                        if (count($parts) == 2) {
                            $deviceid->setImei(strrev($parts[1]));
                            $publicinfo->setBdinputUid('bd_' . $parts[1]);
                        }
                    }

                    if ($idfa != null) {
                        $deviceid->setIdfa($idfa);
                    }
                    if ($opuid != null) {
                        $deviceid->setOpenUdid($opuid);
                    }
                    $common->setDeviceId($deviceid);

                    $publicinfo->setDataSize($file_size);
                    if ($platform != null) {
                        if ($acmode != null && $acmode == 'setting') {
                            $platform = $platform . 'setting';
                        }
                        $publicinfo->setPlatform($platform);
                        if (strpos($platform, 'a') !== false) {
                            $publicinfo->setOs(OSType::ANDROID);
                        } elseif (strpos($platform, 'i') !== false) {
                            $publicinfo->setOs(OSType::IOS);
                        } else {
                            $publicinfo->setOs(OSType::OS_TYPE_UNKNWON);
                        }
                    }
                    if ($version != null) {
                        $publicinfo->setSoftwareVersion($version);
                    }
                    if ($channel != null) {
                        $publicinfo->setChannel($channel);
                    }
                    if ($current_channel != null) {
                        $publicinfo->setCurrentChannel($current_channel);
                    }
                    if ($os_version != null) {
                        $publicinfo->setOsVersion($os_version);
                    }
                    if ($clientip != null) {
                        $publicinfo->setIp($clientip);
                    }
                    if ($screen_w != null) {
                        $publicinfo->setResolutionV(intval($screen_w));
                    }
                    if ($screen_h != null) {
                        $publicinfo->setResolutionH(intval($screen_h));
                    }
                    if ($model != null) {
                        $publicinfo->setTerminalType($model);
                    }
                    $bis->setCommon($common);
                    $bis->setPublicInfo($publicinfo);

                    $str = $bis->serializeToString();;
                    if ($str == null) {
                        $res['info'] = 'Pb Serial Failed';
                        $res['status'] = 1;
                        return $res;
                    }
                    // 使用b2log库来打印日志
                    $ret = b2log_write('bdinput_statistic', $str);
                    if ($ret == false) {
                        $res['info'] = 'Pb Write Failed';
                        $res['status'] = 1;
                        return $res;
                    }
                    $res['info'] = 'Success';
                    $res['status'] = 0;
                    return $res;
                } catch (Exception $e) {
                    $res['info'] = 'Unknown Error';

                    $res['status'] = 1;
                    return $res;
                }
            } else {
                $res['info'] = 'File Format Error';
                $res['status'] = 1;
                return $res;
            }

        } else {
            $res['info'] = 'File Not Found';
            $res['status'] = 1;
            return $res;
        }


        /*



        $bis_appevent = new Baidu_Mobileinputmethod_AppEvent();
        $bis_appevent->setEventID(100);
        $bis->appendAppEvent($bis_appevent);


        $packed = $bis->serializeToString();  #打包

        $bis->clear();

        try {
            $packed = file_get_contents('/home/work/wangyixiang/247527685.342710');
            $bis->parseFromString($packed);  #解包
        } catch (Exception $ex) {
            die('Upss.. there is a bug in this example');
        }

        $events = $bis->getAppEvent();
        foreach($events as $event){
            $event->dump();
        }
        */

    }


}



