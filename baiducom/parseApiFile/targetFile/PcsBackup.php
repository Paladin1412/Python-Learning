<?php

//require_once dirname(__DIR__).'/apis/utils/B64Decoder.php';

use utils\CustLog;
use utils\ErrorCode;
use utils\Util;
use utils\GFunc;
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Bos;


/**
 * Backup (原/v4/?c=backup)
 * @author zhobuin05 20180409
 * 说明：文件上传备份 (pcs)
 *
 * @path("/pcsbackup/") 
 */
class PcsBackup
{
    /**
     * max file size 50M
     */
    const MAX_FILE_SIZE = 51200000;
                          
    /**
     * max key size
     */
    const MAX_KEY_SIZE = 10240;
    
    /**
     * max retry count
     */
    const MAX_RETRY_COUNT = 3;
    
    /**
     * auth request url
     */
    const AUTH_REQUEST_URL = "http://10.26.7.174:8000/oauth/2.0/token?grant_type=bduss&client_id=WKMjd5GsCnd4IYZHAkfEtjGy&client_secret=FICLgCR7t8Ttm9xcqeKL17cxiouQV9Bj&scope=netdisk";
    
    
    /**
     * baidu uid
     *
     */
    private $_uid = null;
    
    /**
     * bduss
     *
     */
    private $_bduss = null;
    
    /**
     * os
     *
     */
    private $_os;
    
    /**
     * model
     *
     */
    private $_model;
    
    //error info map
    static $ERROR_INFO = array (
        10 => "param ukey empty",
        11 => "param ukey wrong",
        12 => "request pass failed",
        13 => "pass return wrong bduss invalid",
        14 => "request meta failed",
        20 => "query current failed",
        21 => "query current return wrong",
        22 => "query transaction failed",
        23 => "query transaction return wrong",
        30 => "delete transaction failed",
        31 => "insert transaction failed",
        32 => "delete except current failed",
        33 => "insert current failed",
        34 => "update current failed",
        40 => "param transaction empty",
        41 => "param transaction wrong",
        42 => "param file wrong",
        43 => "param file key wrong",
        44 => "upload file failed",
        45 => "copy current to new failed",
        46 => "get backup list failed",
        47 => "clean current info failed",
        48 => "clean backup failed",
        50 => "get token failed",
        51 => "copy old to new failed",
        52 => "delete old failed",
        53 => "update transaction info failed",
        54 => "gen transaction info failed",
        55 => "down load old conf failed",
        56 => "down load old conf not found",
        57 => "upload old conf failed",
        58 => "delete file could not be found",
        70 => "netdisk exceed quota", //用户网盘空间已满
    );
    
    /**
     * replace slash
     */
    const REPLACE_SLASH = '${slash}';
    
    
    /**
     * 初始化
     * 
     */
    function __construct() {

        
        //get os ios android mac
        $this->_os = Util::getPhoneOS($_GET['platform']);
    
        //model
        $this->_model =  IoCload("models\\PcsBackupModel");
        
        //pcsd下载接口不要验证bduss
        if(0 !== strpos(Util::getRequestUri(),'/v5/pcsbackup/pcsd/')) {
            //verify bduss 客户端每次请求都必须传bduss
            $this->_verifyBduss();    
        }
        
        
    }
    
    /**
     * get error info
     *
     * @param
     *      参数名称：$error_code
     *      是否必须：是
     *      参数说明：error_code
     * 
     * @return string
     */
    public function _getErrorInfo($error_code){
        $error_info = array();
        $error_info['error_code'] = $error_code;
        $error_info['error_msg'] = self::$ERROR_INFO[$error_code];

        try{
            $arrLog = $error_info;
            $arrLog['response_header'] = headers_list();
            $arrLog['log_time'] = date('Y-m-d H:i:s');
            $arrLog['req_uri'] =  $_SERVER["REQUEST_URI"];
            $arrLog['req_query'] = $_SERVER["QUERY_STRING"];
            $arrLog['bduss'] =  $this->_bduss;
            $arrLog['uid'] =  $this->_uid;
            CustLog::write('v5_pcsbackup_error_log', $arrLog, array('trace' => 1, 'trace_limit' => 6));

        } catch (Exception $e) {

        }


        return $error_info;
    }
    
    /**
     * verify bduss
     *
     */
    public function _verifyBduss(){
        if ( !(isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) ) {
            $this->returnError(400, 10);
        }
        
        $bduss_encode = file_get_contents( $_FILES ['ukey']['tmp_name'] );
        $bduss_decode = bd_AESB64_Decrypt ( $bduss_encode ); //解码
        if (false === $bduss_decode) {
            $this->returnError(400, 11);
        }
        
        $bduss = unpack ( 'a' . strlen ($bduss_decode), $bduss_decode );
        $userinfo = Util::getUserInfoByBduss($bduss[1], Util::getClientIP());
    
        if ( isset($userinfo['status']) && (0 === intval($userinfo['status']))
        && isset($userinfo['uid']) && (0 !== intval($userinfo['uid'])) ){
            //保存uid
            $this->_uid = $userinfo['uid'];
            $this->_bduss = $bduss[1];
        } 
        else{
            $this->returnError(403, 13);

        }
    }
    
    /**
     * get access token
     *
     * @param
     *      参数名称：$bduss
     *      是否必须：是
     *      参数说明：bduss
     *
     * @return string
     */
    public function _getAccessToken($bduss){
        if(!isset($bduss) || empty($bduss)) {
            return null;
        }
        
        $url = self::AUTH_REQUEST_URL . '&bduss=' . $bduss;
        
        $result = $this->_model->_getCurlRequestReslut($url, 'GET');
        if ( !(isset( $result['http_code'] ) && intval( $result['http_code'] ) === 200 && isset( $result['body'] )) ) {
            $this->returnError(503, 50);
        }
        
        $userinfo = json_decode($result ['body'], true);
        
        if( !(isset($userinfo['access_token']) && !empty($userinfo['access_token'])) ){
            $this->returnError(503, 50);
        }
        
        return $userinfo['access_token'];
    }
    
    /**
     * verify transaction info
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     * 
     * @return bool
     */
    public function _verifyTransactionInfo($uid){
        if ( !(isset($_POST['tid']) && isset($_POST['ctime'])) ){
            $this->returnError(400, 40);
        }
        
        $tid = intval($_POST['tid']);
        $ctime = intval($_POST['ctime']);
        
        $result = false;
        //先查询
        $result = $this->_model->_queryTransactionInfo($uid);
        
        if (false === $result){
            $this->returnError(503, 22);

        }
        //pss 查询返回检查
        if ( !isset($result['count']) || !isset($result['records']) ){
            $this->returnError(503, 23);

        }
        $record_count = intval($result['count']);
        //按创建时间降序排列只取最新的
        if ((0 === $record_count) || ($tid !== intval($result['records'][0]['tid'])) || ($ctime !== intval($result['records'][0]['ctime'])) ){
            $this->returnError(400, 41);
        }
    }
    
    /**
     * 删除超过一定时间未修改的非当前备份
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *          
     * @param
     *      参数名称：$current_tid
     *      是否必须：是
     *      参数说明：当前tid
     * 
     * @return bool
     */
    public function _cleanNotCurrentBackup($uid, $current_tid){
        //tid 为0说明当前没有备份要全部删除

        $ret = $this->_model->_deleteExceptCurrentTid($uid, $current_tid);
        if (false === $ret){
            $this->returnError(503, 32);
        }
        
        return true;
    }
    
    /**
     * 清空事务表
     * 
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     * 
     * @return bool
     */
    public function _cleanTransactionInfo($uid){
        $result = false;
        //先查询
        $result = $this->_model->_queryTransactionInfo($uid);
        
        if (false === $result){
            $this->returnError(503, 22);
        }
        //pss 查询返回检查
        if ( !isset($result['count']) || !isset($result['records']) ){
            $this->returnError(503, 23);
        }
        $record_count = intval($result['count']);
        
        if (0 === $record_count){
            return true;
        }
        //有可能插入多条
        $pss_key_list = array();
        for($i = 0; $i < $record_count; $i++){
            
            if ( !isset($result['records'][$i]['_key']) ){
                $this->returnError(503, 23);
            }
            $pss_key = $result['records'][$i]['_key'];
            array_push($pss_key_list, $pss_key);
        }
        
        $delete_result = $this->_model->_deleteBatchTransactionInfo($uid, $pss_key_list);
        if (false === $delete_result){
            $this->returnError(503, 30);
        }
        
        return true;
    }
    
    /**
     * 查询事务信息
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     *
     * @return int
     */
    public function _queryTransactionInfo($uid)
    {
        $result = false;
        //先查询
        $result = $this->_model->_queryTransactionInfo($uid);
    
        if (false === $result){
            $this->returnError(503, 22);
        }
        //pss 查询返回检查
        if ( !isset($result['count']) || !isset($result['records']) ){
            $this->returnError(503, 23);
        }
        
        return $result;
    }
    
    /**
     * 查询事务信息
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$condition_tid
     *      是否必须：是
     *      参数说明：condition tid
     *
     * @return int
     */
    public function _queryTransactionInfoWithCondition($uid, $condition_tid)
    {
        $result = false;
        //先查询
        $result = $this->_model->_queryTransactionInfoWithCondition($uid, $condition_tid);
    
        if (false === $result){
            $this->returnError(503, 22);

        }
        //pss 查询返回检查
        if ( !isset($result['count']) || !isset($result['records']) ){
            $this->returnError(503, 23);
        }
    
        return $result;
    }
    
    /**
     * 生成事务tid
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *     
     *
     * @return array
     */
    public function _genTransactionId($uid)
    {
        $isSuccess = false;
        $arrTranInfo = array();
        for ($i = 0; $i < self::MAX_RETRY_COUNT; $i++){
            $tid = 0;
            $ctime = time();
            //先查询
            $result = $this->_queryTransactionInfo($uid);
            $record_count = intval($result['count']);
            if (0 === $record_count){
                $tid = 1;
                $result = $this->_model->_insertTransactionInfo($uid, $tid, $ctime);
                
                if (!$result){
                    $this->returnError(503, 31);
                }
                
                $result = $this->_model->_queryTransactionInfoWithCondition($uid, $tid);
                $record_count = intval($result['count']);
                //相同账号多个客户端同时插入
                if ($record_count > 1){
                    continue;
                }else{
                    $isSuccess = true;
                    break;
                }
                
            }else{
                $tid = $result['records'][0]['tid'] + 1;
                $result = $this->_model->_updateTransactionInfo($uid, $result['records'][0]['tid'], $result['records'][0]['ctime'], $tid, $ctime);
                //插入失败有可能相同账号多个客户端同时更新
                if (!$result){
                    continue;
                }
                
                $isSuccess = true;
                break;
            }
        }
        
        if (!$isSuccess){
            $this->returnError(503, 54);
        }
        
        $arrTranInfo['tid'] = $tid;
        $arrTranInfo['ctime'] = $ctime;
        
        return $arrTranInfo;
    }
    
    /**
     * 获取当前tid
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @return array
     */
    public function _getCurrentInfo($uid){
        $info = array();
        $tid = 0;
        $pss_key = '';
        $backup = array();
        //查询当前信息
        $result = $this->_model->_queryCurrentInfo($uid);
        if (false === $result){
            $this->returnError(503, 20);
        }
        //pss 查询返回检查
        if ( !isset($result['count']) || !isset($result['records']) ){
            $this->returnError(503, 31);
        }
        
        $record_count = intval($result['count']);
        if (0 === $record_count){
            $tid = 0;
            $pss_key = '';
            $ctime = 0;
        }
        else{
            $tid = intval($result['records'][0]['tid']);
            $pss_key = $result['records'][0]['_key'];
            $ctime = intval($result['records'][0]['ctime']);
            //用version字段保存备份列表信息
            $backup = $result['records'][0]['version'];
        }
        
        $info['tid'] = $tid;
        $info['_key'] = $pss_key;
        $info['ctime'] = $ctime;
        $info['backup'] = $backup;
        
        return $info;
    }
    
    /**
     * 获取上传文件校验信息
     *
     *
     * @return array
     */
    public function _getKeyInfo(){
        if ( !(isset ( $_FILES ['key'] ) && $_FILES ['key'] ['error'] === 0 && $_FILES ['key']['size'] < self::MAX_KEY_SIZE) ) {
            $this->returnError(400, 42);
        }
        
        $key_buffer = file_get_contents( $_FILES ['key']['tmp_name'] );
        $decode_key_info = json_decode($key_buffer, true);
        
        $key_info = array();
        foreach ($decode_key_info as $key => $value){
            $value_info = array();
            
            if ( !isset($value['name'])
            || ('/' !== substr($value['name'], 0, 1)) || ('/' === substr($value['name'], -1, 1)) ){
                $this->returnError(400, 42);
            }
            
            $value_info['name'] = $value['name'];
            $value_info['key'] = urldecode($value['key']);
            $key_info[$key] = $value_info;
        }
        
        return $key_info;
    }
    
    /**
     * verify upload file
     *
     * @param
     *      参数名称：$upload_file
     *      是否必须：是
     *      参数说明：upload_file
     *
     * @param
     *      参数名称：$key
     *      是否必须：是
     *      参数说明：校验key
     *
     */
    public function _verifyUploadFile($upload_file, $key){
        if ( !(isset ( $upload_file ) && $upload_file['error'] == 0
                && $upload_file['size'] > 16 && $upload_file['size'] < self::MAX_FILE_SIZE ) ){

            $this->returnError(400, 42);
        }
        
        $key_decode = bd_AESB64_Decrypt ( $key );
        if ( (false === $key_decode) || (strlen ($key_decode) !== 24) ){
            $this->returnError(400, 43);

        }
        
        $upload_file_path = $upload_file['tmp_name'];
        $upload_file_size = $upload_file['size'];
        $file_len_arr = unpack ( 'V', substr ( $key_decode, 0, 4 ) );
        
        $start8_key = substr ( $key_decode, 4, 8 );
        $end8_key = substr ( $key_decode, 12, 8 );
        
        $upload_file_handle = fopen($upload_file_path, 'rb');
        $start8_file = fread($upload_file_handle, 8); //只读8字节
        fseek($upload_file_handle, -8, SEEK_END);
        $end8_file = fread($upload_file_handle, 8); //只读8字节
        fclose($upload_file_handle);
        
        if ( ($upload_file_size !== intval($file_len_arr[1])) || ($start8_key !== $start8_file) || ($end8_key !== $end8_file) ){

            $this->returnError(400, 43);

        }
    }
    
    /**
     * 上传文件
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *     
     *         
     * @param
     *      参数名称：$tid
     *      是否必须：是
     *      参数说明：tid
     * 
     *     
     * @param
     *      参数名称：$current
     *      是否必须：否
     *      参数说明：当前存在的文档数据，包括文档路径。如有数值则采用整合之前文档生成新文档的方式
     *      
     * @return array 上传文件(名称为PCS存储名称)列表     
     */
    public function _uploadFile($uid, $tid, $current = array()){
        $upload_file_list = array();
        //get key info
        $key_info = $this->_getKeyInfo();
        
        foreach ($_FILES as $key => $value){
            //排除指定上传文件
            if ( ('ukey' === $key) || ('key' === $key) ){
                continue;
            }
            
            //verify file
            if ( !isset($key_info[$key]) ){

                $this->returnError(400, 42);

            }
            
            $this->_verifyUploadFile($value, $key_info[$key]['key']);
            
            //Edit by fanwenli on 2016-07-11
            $this->_mergeCustomeFile($uid, $current, realpath($value['tmp_name']), $key_info[$key]['name']);
            
            //upload file to pcs 不使用真实文件名 而使用参数名
            $result = $this->_model->_uploadFile($uid, $tid, realpath($value['tmp_name']), $key_info[$key]['name']);
            if (false === $result){
                $this->returnError(503, 44);

            }
            
            if (-1 === $result){

                $this->returnError(200, 70);
            }
            
            $upload_file['name'] = $result['name'];
            $upload_file['path'] = $result['path'];
            $upload_file['mtime'] = $result['mtime'];
            $upload_file['size'] = $result['size'];
            $upload_file['md5'] = $result['md5'];
            array_push($upload_file_list, $upload_file);
        }
        
        return $upload_file_list;
    }
    
    /**
     * 拷贝当前备份有新备份没有的文件
     *
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$begin_info
     *      是否必须：是
     *      参数说明：begin info
     *
     * @param
     *      参数名称：$upload_file_list
     *      是否必须：是
     *      参数说明：新备份list
     *
     * @return array 新的备份列表
     */
    public function _copyCurrentToNew($uid, $begin_info, $upload_file_list){
        $result = $upload_file_list;
        
        if( 0 !== $begin_info['current']['tid'] ){
            $result = $this->_model->_copyCurrentToNewTransaction($uid, $begin_info, $upload_file_list);
            if (false === $result){
                $this->returnError(503, 45);

            }
            
            if (-1 === $result){
                $this->returnError(200, 70);

            }
        }
        
        return $result;
    }
    
    /**
     * 更新当前表信息
     *
     * @param
     *      参数名称：$current_tid
     *      是否必须：是
     *      参数说明：current_tid
     *      
     * @param
     *      参数名称：$new_tid
     *      是否必须：是
     *      参数说明：new_tid
     *      
     * @param
     *      参数名称：$ctime
     *      是否必须：是
     *      参数说明：ctime
     *      
     * @param
     *      参数名称：$platform
     *      是否必须：是
     *      参数说明：platform
     *      
     * @param
     *      参数名称：$new_backup_list
     *      是否必须：是
     *      参数说明：new backup list
     *      
     * @param
     *      参数名称：$pss_key
     *      是否必须：是
     *      参数说明：pss_key          
     *      
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid         
     *
     * @return bool
     */
    public function _updateCurrentInfo($current_tid, $new_tid, $ctime, $platform, $new_backup_list, $pss_key, $uid){
        //insert
        if(0 === $current_tid){
            $result = $this->_model->_insertCurrentInfo($new_tid, $ctime, $platform, $new_backup_list, $uid);
            if (!$result){
                $this->returnError(503, 33);

            }
        }
        else{
            $result = $this->_model->_updateCurrentInfo($new_tid, $ctime, $platform, $new_backup_list, $pss_key, $uid);
            if (!$result){
                $this->returnError(503, 34);
            }
        }
    }
    
    /**
     * 获取备份列表
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$os
     *      是否必须：是
     *      参数说明：os
     *
     * @param
     *      参数名称：$ctime
     *      是否必须：是
     *      参数说明：ctime
     *
     * @param
     *      参数名称：$access_token
     *      是否必须：是
     *      参数说明：access_token
     *
     * @return array
     */
    public function _getBackupList($uid, $os, $tid, $ctime, $access_token = null){
        $backup_list = array();
        if (0 === $tid){
            $backup_list = $this->_getOldApiBackup($uid, $os, $tid, $ctime, $access_token);
        }else{
            $backup_list = $this->_getNewBackup($uid, $tid, $ctime, $access_token);
        }
        
        return $backup_list;
    }
    
    /**
     * 拷贝旧接口备份到新接口目录
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$os
     *      是否必须：是
     *      参数说明：os
     *
     * @param
     *      参数名称：$new_tid
     *      是否必须：是
     *      参数说明：new_tid
     *
     *  @return string
     */
    public function _copyOldApiToNew($uid, $os, $new_tid){
        $result = $this->_model->_copyOldApiToNew($uid, $os, $new_tid);
        if (false === $result){
            $this->returnError(503, 51);

        }
        
        if (-1 === $result){
            $this->returnError(200, 70);

        }
        
        return $result;
    }
    
    /**
     * 获取旧接口备份列表
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$os
     *      是否必须：是
     *      参数说明：os
     *      
     * @param
     *      参数名称：$tid
     *      是否必须：是
     *      参数说明：tid
     *
     * @param
     *      参数名称：$ctime
     *      是否必须：是
     *      参数说明：ctime
     *
     * @param
     *      参数名称：$access_token
     *      是否必须：是
     *      参数说明：access_token
     *
     * @return array
     */
    public function _getOldApiBackup($uid, $os, $tid, $ctime, $access_token = null){
        $upload_file_list = array();
        $backup_list = array();
        //is have old api backup
        $meta_ios = $this->_getOldBackupMeta($uid, 'ios');
        $meta_mac = $this->_getOldBackupMeta($uid, 'mac');
        if (false === $meta_ios && false === $meta_mac){
            $backup_list['tid'] = 0;
            $backup_list['ctime'] = 0;
            
            return $backup_list;
        }
        
        //begin transaction
        $new_tid_info = $this->_beginTransaction();
        
        //copy old to new
        //ios
        if(false !== $meta_ios){
            //解决使用ios越狱备份设置项新版本输入法还原问题
            $result = $this->_model->downloadOldConf($uid, 'ios');
            if (false === $result){
                $this->returnError(503, 55);

            }
            
            if(intval( $result['http_code'] ) === 200){
                $strConf = ( $result['body'] );
            }else{
                $this->returnError(503, 56);

            }
            
            $arrChangeRet = $this->_model->changeIosConf($strConf);
            if ($arrChangeRet['change'] === true){
                $bolUpRet = $this->_model->_uploadOldConf($uid, $os, $arrChangeRet['conf']);
                if (false === $bolUpRet){
                    $this->returnError(503, 57);

                }
                
                if (-1 === $bolUpRet){
                    $this->returnError(200, 70);

                }
            }
            //
            
            $result = $this->_copyOldApiToNew($uid, 'ios', $new_tid_info['tid']);
            
            $upload_file['name'] = $result['name'];
            $upload_file['path'] = $result['path'];
            $upload_file['mtime'] = $meta_ios['list'][0]['mtime'];
            $upload_file['size'] = $meta_ios['list'][0]['size'];
            $upload_file['md5'] = $meta_ios['list'][0]['md5'];
            array_push($upload_file_list, $upload_file);
        }
        //mac
        if(false !== $meta_mac){
            $result = $this->_copyOldApiToNew($uid, 'mac', $new_tid_info['tid']);
            
            $upload_file['name'] = $result['name'];
            $upload_file['path'] = $result['path'];
            $upload_file['mtime'] = $meta_mac['list'][0]['mtime'];
            $upload_file['size'] = $meta_mac['list'][0]['size'];
            $upload_file['md5'] = $meta_mac['list'][0]['md5'];
            array_push($upload_file_list, $upload_file);
        }
        
        //update current info
        $this->_updateCurrentInfo(0, $new_tid_info['tid'], $new_tid_info['ctime'], $os, $upload_file_list, '', $this->_uid);
        
        //delete old api backup
        //ios
        if (false !== $meta_ios){
            $result = $this->_model->_deleteOldApiBackup($uid, 'ios');
            if (false === $result){
                $this->returnError(503, 52);

            }
        }
        //mac
        if (false !== $meta_mac){
            $result = $this->_model->_deleteOldApiBackup($uid, 'mac');
            if (false === $result){
                $this->returnError(503, 52);

            }
        }
        
        //$backup_list = $this->_model->_getBackupList($uid, $new_tid_info['tid'], $new_tid_info['ctime'], $access_token);
        //$backup_list = $this->_model->_genBackupList($tid, $ctime, $upload_file_list, $access_token);
        $backup_list = $this->_model->_genBackupList($new_tid_info['tid'], $new_tid_info['ctime'], $upload_file_list, $access_token, $this->_uid);
        if (false === $backup_list){
            $this->returnError(503, 46);

        }
        
        return $backup_list;
    }
    
    /**
     * 获取新接口备份列表
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$tid
     *      是否必须：是
     *      参数说明：tid
     *
     * @param
     *      参数名称：$ctime
     *      是否必须：是
     *      参数说明：ctime
     *
     * @param
     *      参数名称：$access_token
     *      是否必须：是
     *      参数说明：access_token
     *
     * @return array
     */
    public function _getNewBackup($uid, $tid, $ctime, $access_token = null){
        $backup_list = $this->_model->_getBackupList($uid, $tid, $ctime, $access_token);
        if (false === $backup_list){
            $this->returnError(503, 46);

        }
        
        return $backup_list;
    }
    
    /**
     * 获取meta
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     * @param
     *      参数名称：$os
     *      是否必须：是
     *      参数说明：os
     *
     * @return array
     */
    public function _getOldBackupMeta($uid, $os){
        $result = false;
        if ( ('ios' === $os) || ('mac' === $os) ){
            $meta = $this->_model->_getOldBackMeta($uid, $os);
            if ( !(isset( $meta['http_code'] ) && (intval( $meta['http_code'] ) === 200 || intval( $meta['http_code'] ) === 404) ) ){
                $this->returnError(503, 14);

            }
            
            if (intval( $meta['http_code'] ) === 200){
                $result = json_decode($meta['body'], true);
            }
        }
        
        return $result;
    }
    
    /**
     * 删除所有备份
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     *
     * @return bool
     */
    public function _cleanBackup($uid){
        //get current info
        $current_info = $this->_getCurrentInfo($uid);
        $current_tid = $current_info['tid'];
        $current_key = $current_info['_key'];
        
        //Edit by fanwenli on 2016-08-11, delete file which you upload, you must post file structure
        if(isset($_FILES ['key']) && $_FILES ['key'] ['error'] === 0 && !empty($current_info) && isset($current_info['backup'])){
            //Edit by fanwenli on 2016-11-18, create new backup list function, get new backup list and the file's array which will be deleted
            $new_backup_arr = $this->_getNewBackupList($current_info);
            $new_backup_list = $new_backup_arr['new_backup_list'];
            $path_list = $new_backup_arr['delete_list'];
            
            //如无任何文件删除，则报错
            if(empty($path_list)){
                $this->returnError(200, 58);

            }
            
            //clean current info
            $ctime = time();
            $result = $this->_model->_updateCurrentInfo($current_tid, $ctime, $this->_os, $new_backup_list, $current_key, $uid);
            if(false === $result){
                $this->returnError(503, 47);

            }
            
            //clean backup file
            //Edit by fanwenli on 2016-11-18, do not delete files
            /*$result = $this->_model->_batchDelete($uid, $path_list);
            if(false === $result){
                $this->returnError(503, 48);

            }*/
        } else {
            //clean current info 
            $result = true;
            if ('' !== $current_key){
                $result = $this->_model->_deleteCurrentInfo($uid, $current_key);
            }
            
            if(false === $result){
                $this->returnError(503, 47);

            }
            
            //clean backup file
            $result = $this->_model->_cleanBackup($uid);
            if(false === $result){
                $this->returnError(503, 48);

            }
        }
        
        return true;
    }
    
    /**
     * begin transaction
     *
     * @return array
     */
    public function _beginTransaction(){
        $ret = array();
        
        //get current tid
        $current_info = $this->_getCurrentInfo($this->_uid);
        
        //clean not current backup
        $this->_cleanNotCurrentBackup($this->_uid, $current_info['tid']);
        
        //generate tid
        $ret = $this->_genTransactionId($this->_uid);
        
        //current info
        $ret['current'] = $current_info;
        
        return $ret;
    }
    
    /**
     * end transaction
     *
     * @param
     *      参数名称：$begin_info
     *      是否必须：是
     *      参数说明：begin info 包含新的tid及当前备份信息
     *      
     * @param
     *      参数名称：$upload_file_list
     *      是否必须：是
     *      参数说明：upload file list      
     * 
     *
     */
    public function _endTransaction($begin_info, $upload_file_list){
        //get current info
        //$current_info = $this->_getCurrentInfo($this->_uid);
        $current_info = $begin_info['current'];
    
        //copy current to new
        $new_backup_list = $this->_copyCurrentToNew($this->_uid, $begin_info, $upload_file_list);
        
        //update current info
        $this->_updateCurrentInfo($current_info['tid'], $begin_info['tid'], $begin_info['ctime'], $this->_os, $new_backup_list, $current_info['_key'], $this->_uid);
    
        //get list
        //$backup_list = $this->_getBackupList($this->_uid, $this->_os, $begin_info['tid'], $begin_info['ctime']);
        $backup_list = $this->_model->_genBackupList($begin_info['tid'], $begin_info['ctime'], $new_backup_list, null , null);

        $strEncodeData = json_encode($backup_list);

        if(1 === intval($_GET['stdrt'])) {
            $arrResult = Util::initialClass();
            $arrResult['data'] = array('value'=> trim($strEncodeData) );
            echo json_encode($arrResult);
            exit;
        } else {
            print $strEncodeData;
            exit;
        }


    }
    
    
    /**
     * @desc 上传平台备份
     * @route({"POST", "/upload"})
     * @return
     */ 
    public function uploadAction(){
        //无法修改header问题处理
        ob_end_flush();
        
        //begin transaction
        $begin_info = $this->_beginTransaction();
        
        //upload file
        $upload_file_list = $this->_uploadFile($this->_uid, $begin_info['tid'], $begin_info['current']);
        
        //end transaction
        $this->_endTransaction($begin_info, $upload_file_list);
    }
    
   
    /**
     * @desc get backup list info
     * @route({"POST", "/list"})
     * @return
     */
    public function listAction(){
        //get current info
        $current_info = $this->_getCurrentInfo($this->_uid);
        
        $access_token = $this->_getAccessToken($this->_bduss);
        
        //$backup_list = $this->_getBackupList($this->_uid, $this->_os, $current_info['tid'], $current_info['ctime'], $access_token);
        
        if (0 === $current_info['tid']){
            $backup_list = $this->_getOldApiBackup($this->_uid, $this->_os, $current_info['tid'], $current_info['ctime'], $access_token);
        }else{
            //$backup_list = $this->_getNewBackup($uid, $tid, $ctime, $access_token);
            
            $backup_list = $this->_model->_genBackupList($current_info['tid'], $current_info['ctime'], $current_info['backup'], $access_token, $this->_uid);
        }
        
        //AES encode
        $encode_list = trim(bd_AESB64_Encrypt(json_encode($backup_list)));

        if(1 === intval($_GET['stdrt'])) {
            $arrResult = Util::initialClass();
            $arrResult['data'] = array('value'=> $encode_list );
            echo json_encode($arrResult);
        } else {
            print $encode_list;
        }

        exit;

    }
    
    /**
     * @desc delete backup
     * @route({"POST", "/delete"})
     * @return
     */
    public function deleteAction(){
        $this->_cleanBackup($this->_uid);

        if(1 === intval($_GET['stdrt'])) {
            $arrResult = Util::initialClass();
            echo json_encode($arrResult);
        }

        exit;
    }
    
    /**
     * 处理上传文件内容合并与否
     *
     * @param
     *      参数名称：$uid
     *      是否必须：是
     *      参数说明：uid
     *
     *
     * @param
     *      参数名称：$current
     *      是否必须：是
     *      参数说明：当前文件夹信息
     *
     *
     * @param
     *      参数名称：$file_path
     *      是否必须：是
     *      参数说明：上传的临时文件位置
     *
     *
     * @param
     *      参数名称：$file_name
     *      是否必须：是
     *      参数说明：上传的文件路径
     *
     *
     *
     */
    public function _mergeCustomeFile($uid, $current, $file_path, $file_name){
        //file's tail
        $file_path_info = pathinfo($file_name);
        $file_real_name = $file_path_info['basename'];
        $tail = $file_path_info['extension'];
        
        //usrinfo_stat or vkword
        if($file_real_name == 'usrinfo_stat.ini' || $file_real_name == 'vkword.txt'){
             //定义本地有可写权限目录位置
            $intPos = strpos(APP_PATH, '/app');
            $file_save_tmp_path = substr(APP_PATH, 0, $intPos) . "/data/app/v5/conf_backup_tmp/";
            
            //create file tmp path, if it could not find it
            Util::makeDir($file_save_tmp_path,true);
            
            //find usrinfo_stat path
            $usrinfo_stat_path = '';
            
            //find vk path
            $vk_path = '';
            
            //Edit by fanwenli on 2018-06-21, check file name in vkword whether it belongs ios or mac
            $pcs_vk_file_name = str_replace('/', self::REPLACE_SLASH, $file_name);
        
            //find backup before
            if(is_array($current) && isset($current['backup']) && is_array($current['backup'])){
                foreach($current['backup'] as $key => $val){
                    if(is_array($val) && isset($val['name']) && isset($val['path'])){
                        //get usrinfo_stat path
                        if($file_real_name == 'usrinfo_stat.ini' && preg_match('/usrinfo_stat\.ini/i',$val['name'],$arr)){
                            $usrinfo_stat_path = $val['path'];
                            break;
                        }
                        
                        //get kv path
                        //Edit by fanwenli on 2018-06-21, check file name in vkword whether it belongs ios or mac
                        if($file_real_name == 'vkword.txt' && preg_match('/vkword\.txt/i',$val['name'],$arr) && $pcs_vk_file_name == $val['name']){
                            $vk_path = $val['path'];
                            break;
                        }
                    }
                }
            }
            
            //current file has usrinfo
            if($usrinfo_stat_path != '' || $vk_path != ''){
                //find current file
                if($usrinfo_stat_path != '') {
                    $backup_file_path = $usrinfo_stat_path;
                } elseif($vk_path != '') {
                    $backup_file_path = $vk_path;
                }
                
                $rs = $this->_model->_downloadFile($uid, $backup_file_path);
                
                //file exists
                if($rs['http_code'] == '200'){
                    //get file content from current file
                    $file_save_tmp_name = $uid.'_'.$current['tid'].'_'.time().'.'.$tail;
                    $file_in_server = $file_save_tmp_path . $file_save_tmp_name;
                    $fh = fopen($file_in_server, "a+");
                    fwrite($fh, $rs['body']);
                    fclose($fh);
                    
                    $exe_file = dirname(__FILE__).'/../script/';
                    
                    $action = '';
                    
                    //Edit by fanwenli on 2018-03-14, set file in exec command
                    $exec_file_path = $file_in_server.' '.$file_path.' '.$file_path;
                    
                    //usrinfo_stat
                    if($usrinfo_stat_path != '') {
                        //merge program path
                        $exe_file .= 'usrinfo';
                        $action = ' -merge';
                    }
                    
                    //vk
                    if($vk_path != '') {
                        //Edit by fanwenli on 2018-03-14, judge whether cuid is in white list
                        if(Util::inNewStrgCuidList()) {
                            $exe_file .= 'makeVkWord';
                            
                            //client file, server file, object file
                            $exec_file_path = $file_path.' '.$file_in_server.' '.$file_path;
                        } else {
                            //merge program path
                            $exe_file .= 'vkmerge';
                        }
                    }
                    
                    //chmod 777
                    exec('chmod +x '.$exe_file);
                    
                    //merge file with current file and upload file. return_val is 1 means success, otherwise, means failure
                    exec($exe_file.$action.' '.$exec_file_path, $output, $return_val);
                    
                    //delete tmp file
                    exec('rm '.$file_in_server, $output, $return_val);
                }
            }
        }
    }
    
    /**
     * 返回删除指定文件后的剩余文件列表
     *
     *
     *
     * @param
     *      参数名称：$current_info
     *      是否必须：是
     *      参数说明：当前文件夹内容
     *
     *
     *
     * @return array
     */
    public function _getNewBackupList($current_info){
        $backup_list = array(
            'new_backup_list' => array(),//Back up list
            'delete_list' => array(),//The file array which will be deleted
        );
        
        //get key info
        $key_info = $this->_getKeyInfo();
        
        $key_info_name = array();
        if(!empty($key_info)){
            //replace file name
            foreach($key_info as $val){
                $file_name = str_replace('/', self::REPLACE_SLASH, $val['name']);
                $key_info_name[] = $file_name;
            }
        }
        
        if(!empty($current_info) && isset($current_info['backup']) && is_array($current_info['backup']) && !empty($current_info['backup'])){
            foreach($current_info['backup'] as $k_backup => $v_backup){
                if(in_array($v_backup['name'],$key_info_name)){
                    //delete file from current document
                    $backup_list['delete_list'][] = $v_backup['path'];
                } else {
                    //file is not deleted
                    $backup_list['new_backup_list'][] = $v_backup;
                }
            }
        }
        
        return $backup_list;
    }
    
    
    /**
     * @route({"GET","/pcsd"})
     * 下载pcs文件
     * 内网pcs下载地址，只能通过服务端中转下载
     * @return 
     */
    function pcsdDown() {
       
       $strSalt = "1*nv1_319#&8@";
       
       $intTm = isset($_GET['tm']) ? $_GET['tm'] : '';  
       
       $strDownUrl = isset($_GET['dl']) ? $_GET['dl'] : '';  
       
       $strSign = md5($intTm . $strSalt . $strDownUrl);
        
       if(strtoupper($strSign) === strtoupper($_GET['sign']) ) {
           
           $strDownUrl = \B64Decoder::decode($strDownUrl, 0);
          
           if(!empty($strDownUrl)) {
               $strFile = file_get_contents($strDownUrl); 
               
               $intSize = strlen($strFile);
               
               if(false !== $strFile && $intSize > 0) {
                   $rt = json_decode($strFile, true);        
                   if(isset($rt['error_code']) && intval($rt['error_code']) > 0 ) {
                       //log error here
                       //eg: {"error_code":31066,"error_msg":"file does not exist","request_id":2029682443621948725}
                   } else {
                       $arrParam = parse_url($strDownUrl);
                       $arrQuery = $this->_convertUrlQuery($arrParam['query']);
                       $strFileName = basename(urldecode($arrQuery['path']));
                       header("Content-type: application/octet-stream");
                       header("Accept-Ranges: bytes");
                       header("Accept-Length: ". $intSize);
                       header("Content-Disposition: attachment; filename=" . $strFileName);
                       echo $strFile;
                       exit;    
                   }
               }
               
           }
             
       }
       
       header("HTTP/1.1 404 Not Found");
       header("status: 404 Not Found");
       exit;
       
       
    }
    
    /**
     * 将字符串参数变为数组
     * @param $strQuery
     * @return array array (size=10)
              'm' => string 'content' (length=7)
              'c' => string 'index' (length=5)
              'a' => string 'lists' (length=5)
              'catid' => string '6' (length=1)
              'area' => string '0' (length=1)
              'author' => string '0' (length=1)
              'h' => string '0' (length=1)
              'region' => string '0' (length=1)
              's' => string '1' (length=1)
              'page' => string '1' (length=1)
     */
    private function _convertUrlQuery($strQuery)
    {
        $arrParts = explode('&', $strQuery);
        $arrParams = array();
        foreach ($arrParts as $strParam) {
            $arrItem = explode('=', $strParam);
            $arrParams[$arrItem[0]] = $arrItem[1];
        }
        return $arrParams;
    }


    /**
     * 根据客户端不同使用不同的返回方式
     * @param $intHttpCode
     * @param $intErrorCode
     * @return
     * @throws ReflectionException
     */
    private function returnError($intHttpCode, $intErrorCode) {

        $arrErrorInfo = $this->_getErrorInfo($intErrorCode);

        if(1 === intval($_GET['stdrt'])) {
            $arrResult = Util::initialClass();
            $arrResult['ecode'] = $arrErrorInfo['error_code'];
            $arrResult['emsg'] = $arrErrorInfo['error_msg'];
            echo json_encode($arrResult);
            exit;
        } else {
            header('X-PHP-Response-Code: '. $intHttpCode, true, $intHttpCode);
            print json_encode($arrErrorInfo);
            exit();
        }

    }
    
}
    