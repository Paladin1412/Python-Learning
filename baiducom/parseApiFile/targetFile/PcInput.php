<?php
/**
 *
 * @desc PC输入法服务端接口
 * @path("/pc_input/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\Bos;
use utils\ErrorCode;

class PcInput
{
    /** @property $bucket */
    private $bucket;
    
    /** @property 崩溃日志保存路径 */
    private $strCrashLogPath;
    
    /** @property 崩溃日志上传bos与否缓存前缀 */
    private $strCrashLogSignCachePre;
    
    /** 崩溃日志验签语料 */
    private $strSalt = 'iudfu(lkc#xv345y82$dsfjksafwl';
    
    /** @property 内测资格所在缓存key */
    private $strInnerTestCuidCache;
    
    /**
    * @desc 云开关控制器
    * @route({"GET", "/noti_cloud_switch"})
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 0,
            "emsg": "",
            "data": sdfsdfsdfsdfsdfsd
            ]
        }
    */
    public function getNotiCloudSwitch() {
        $out = Util::initialClass(false);
        
        //switch init
        $arrSwitch = array(
            'longlog_collect' => 1,
            'trace_collect' => 1,
            'bbm_collect' => 0,
            'crash_collect' => 0,
            'longlog_upload' => 1,
            'trace_upload' => 1,
            'bbm_upload' => 0,
            'crash_upload' => 0,
            'corelog_upload' => 0,
            'handwrite_collect' => 0,
            'handwrite_upload' => 0,
            'ad_stat_collect' => 0,
            'ad_stat_upload' => 0,
        );
        
        $out['data'] = bd_B64_encode(json_encode($arrSwitch),0);
        
        return Util::returnValue($out,false);
    }
    
    /**
    * @desc 崩溃开关接口
    * @route({"GET", "/crash_switch"})
    * @param({"strCen", "$._GET.cen"}) $strCen cen Cen客户端字段
    * @param({"strCrashLogSign", "$._GET.crashsign"}) $strCrashSign crashsign 崩溃日志签名
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 0,
            "emsg": "",
            "data": sdfsdfsdfsdfsdfsd
            ]
        }
    */
    public function getCrashSwitch($strCen = '', $strCrashLogSign = '') {
        $out = Util::initialClass(false);
        
        //switch init
        $arrSwitch = array(
            'switch' => 0,
        );
        
        $boolCen = false;
        $strCen = trim($strCen);
        if(strstr($strCen,'crashsign')) {
            $boolCen = true;
        }
        
        //Edit by fanwenli on 2019-01-23, set crash log switch with 1 when there has no cache with cache log key
        $strCrashLogSign = trim($strCrashLogSign);
        if($strCrashLogSign != '' && $boolCen == true) {
            $strCrashLogSignCacheKey = $this->strCrashLogSignCachePre . $strCrashLogSign;
            $objCache = IoCload('utils\\KsarchRedis');
            $strCrashLogSignCache = $objCache->hget($strCrashLogSignCacheKey, 'sign');
            
            //set crash log switch with 1 when there has no cache
            if($strCrashLogSignCache === null) {
                $arrSwitch['switch'] = 1;
                
                $now = time();
                $arrSwitch['time'] = $now;
                $arrSwitch['sign'] = md5($strCrashLogSign . $now . $this->strSalt);
            }
        }
        
        $out['data'] = bd_B64_encode(json_encode($arrSwitch),0);
        
        return Util::returnValue($out,false);
    }
    
    /**
    * @desc 崩溃日志收集
    * @route({"POST", "/logs_upload"})
    * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
    * @param({"strVersion", "$._GET.version"}) $strCuid version,不需要客户端传
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"strCrashLogSign", "$._GET.crashsign"}) $strCrashSign crashsign 崩溃日志签名
    * @param({"strSign", "$._GET.sign"}) $strSign sign 签名验证
    * @param({"intTime", "$._GET.time"}) $strSign time 获取上传资格时的时间戳
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 0,
            "emsg": "",
            "data": {}
            ]
        }
    */
    public function logsUpload($strCuid = '', $strVersion = '', $strPlatform = '', $strCrashLogSign = '', $strSign = '', $intTime = 0) {
        $out = Util::initialClass();
        
        $strErrorMsg = '';
        
        //return error when it could not get sign
        $strSign = trim($strSign);
        if($strSign == '') {
            $strErrorMsg = 'Please give your sign';
        }
        
        //return error when it could not get cuid
        $strCuid = trim($strCuid);
        if($strCuid == '') {
            $strErrorMsg = 'Please give your cuid';
        }
        
        //return error when it could not get version
        $strVersion = trim($strVersion);
        if($strVersion == '') {
            $strErrorMsg = 'Please give your version';
        }
        
        //return error when it could not get platform
        $strPlatform = trim($strPlatform);
        if($strPlatform == '') {
            $strErrorMsg = 'Please give your platform';
        }
        
        //return error when it could not get crash log sign
        $strCrashLogSign = trim($strCrashLogSign);
        if($strCrashLogSign == '') {
            $strErrorMsg = 'Please give your crash log sign';
        }
        
        //return error when it could not get file
        if(!isset($_FILES['crash_log_file'])) {
            $strErrorMsg = 'Please upload your crash log';
        }
        
        if($strErrorMsg != '') {
            return ErrorCode::returnError('PARAM_ERROR', $strErrorMsg);
        }
        
        //verify signature
        $strUploadSign = md5($strCrashLogSign . intval($intTime) . $this->strSalt);
        if($strSign != $strUploadSign) {
            return ErrorCode::returnError('PARAM_ERROR', 'Verifying signature failed');
        }
        
        //Edit by fanwenli on 2019-01-23, upload file to bos when there has no cache with cache log key
        $strCrashLogSignCacheKey = $this->strCrashLogSignCachePre . $strCrashLogSign;
        $objCache = IoCload('utils\\KsarchRedis');
        $strCrashLogSignCache = $objCache->hget($strCrashLogSignCacheKey, 'sign');
        
        //upload file to bos when there has no cache
        if($strCrashLogSignCache === null) {
            //version
            $strVersion = Util::getVersionIntValue(trim($strVersion));
            
            //$arrFileInfo = pathinfo($_FILES['crash_log_file']['name']);
            //upload file name
            //$strFileName = time() . '.' . $arrFileInfo['extension'];
            
            $strFileName = time();
            
            //file path: 20190118/platform/version/sign/cuid/
            $strPath = date('Ymd') . '/' . $this->strReplace($strPlatform) . '/' . $this->strReplace($strVersion) . '/' . $this->strReplace($strCrashLogSign) . '/' . $this->strReplace($strCuid) . '/';
            $objectName = $strPath . $strFileName;
            
            //bos object
            $objBos = new Bos($this->bucket, $this->strCrashLogPath);
            
            /*$result = $objBos->listObjects(array('prefix' => $strPath));
            var_dump($result);
            exit;*/
            
            
            $uploadBosRes = $objBos->putObjectFromFile($objectName, $_FILES['crash_log_file']['tmp_name']);
            //return error when the file could not be uploaded
            if (1 != $uploadBosRes['status']) {
                return ErrorCode::returnError('UPLOAD_ERROR', 'upload file error');
            }
            
            //set crash log sign cache
            $objCache->hset($strCrashLogSignCacheKey, 'sign', 1);
        } else {
            //set emsg that the file has been uploaded
            $out['emsg'] = 'The file has been uploaded';
        }
        
        return Util::returnValue($out);
    }
    
    /**
    * @desc 内测资格审核
    * @route({"GET", "/inner_test"})
    * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 0,
            "emsg": "",
            "data": 1
            ]
        }
    */
    public function innerTest($strCuid = '') {
        $out = Util::initialClass(false);
        
        //default
        $sign = 0;
        
        $strCuid = trim($strCuid);
        
        if($strCuid != '') {
            $objRedis = IoCload('utils\\KsarchRedis');
            //cache was be set in v4 backend /pcinput/innertest
            $intFindCache = intval($objRedis->sismember($this->strInnerTestCuidCache, $strCuid));
            if($intFindCache > 0){
                $sign = 1;
            }
        }
        
        $out['data'] = bd_B64_encode($sign,0);
        
        return Util::returnValue($out,false);
    }
    
    /**
    * @desc 内测词库接口域名下发
    * @route({"GET", "/inner_wordslibs_domain"})
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 0,
            "emsg": "",
            "data": 1
            ]
        }
    */
    public function innerWordslibsDomain() {
        $out = Util::initialClass(false);
        
        $domain = 'http://amisapi.ime-php7.sz-orp.int.baidu.com';
        
        $out['data'] = bd_B64_encode($domain,0);
        
        return Util::returnValue($out,false);
    }
    
    /**
    * @desc 转换文档目录/为无
    *
    *
    * @param $string 原字符串
    * @return string
    *
    */
    private function strReplace($string = '') {
        $string = str_replace('/', '', $string);
        
        return $string;
    }
}
