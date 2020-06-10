<?php

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\CustLog;
use utils\KsarchRedis;
use utils\Bos;
use utils\Util;


/**
 *
 * Backup
 * 说明：第三方oem版本文件上传备份
 *
 * @author zhoubin
 * @path("/backup/") 
 */
class Backup
{
    
    private $bos;
    
    private $platform;
    
    private $version;
    
    private $savePath;
    
    private $uniqueId;
    
    private $thirdId; //第三方标示
    
    private $keepNum = 4; //保留老备份条数,至少为1。 如：当老备份数=4时，实际用户文件夹中有5条数据，其中一条是当前数据，不算在老备份中
    
    private $objPathPrefix = 'app-conf-backup';//
    
    private $objSubPrefix = 'conf';
    
    private $realSavePath;
    
    //Edit by fanwenli on 2018-07-16, get all file in bos
    private $arrBackupFiles = array();
    
     
    //error info map
    static $ERROR_INFO = array (
        10 => "param ukey empty",
        11 => "param ukey wrong",
        13 => "params error",
        15 => "params verify fail",
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
        58 => "create file durl failed",
        59 => "open id verify fail",//Edit by fanwenli on 2017-07-24, if open_id is null, then return error
        60 => "the user had been logout",
    );
    
    /**
     * 类只会在无缓存时才会被实例化, 所以可以在构造方法中连接数据库
     * @return void
     */
    function __construct()
    {
      
        $this->bos = new Bos('imepri', $this->objPathPrefix);
       
        $this->platform = $this->_getPhoneOS($_GET['platform']);
        
        $this->version = !empty($_GET['version']) ? $_GET['version'] : "";
       
       
        $this->_verifyAccess();
           
    }
    
    
    /**
     * 删除非当前备份(保留最近10个)
     * @param unknown $guid
     * @return 
     */
    private function _cleanNotCurrentBackup($current_tid) {
        
        $result = $this->bos->realListObjects(array('prefix'=>$this->savePath , 'delimiter'=>'/')); //获取savePath目录详细信息和其下文件夹对象路径信息
        
        if($result['status'] === 0) {
            $this->_retrunError(503, 22);
        }
        
        $tid_tmp = $result['data']['commonPrefixes']; //事务"文件夹"列表
  
        $tid_list = array();
        foreach ($tid_tmp as $v) {
            $tid_list[$v['prefix']] = array();
        }
        
        $result = $this->bos->realListObjects(array('prefix'=>$this->savePath )); //获取savePath目录下所有保存文件的信息
        if($result['status'] === 0) {
            $this->_retrunError(503, 46);
        }
        
        $obj_list = $result['data']['contents']; //全对象列表, 用来获取精确的objectkey及对比事务“文件夹”最后更新时间
        
        foreach ($obj_list as $ov) {
            if( isset($tid_list[$ov['key']]) ) {
                $tid_list[$ov['key']]['lastModified'] = $ov['lastModified'];
                $tid_list[$ov['key']]['lastModifiedTimeStamp'] = strtotime($ov['lastModified']); //时间标记，以便排序
                continue;
            }
            
            $match_tid = $this->_getTidFormObjectKey($ov['key']);
            
            if($match_tid !== false) {
                $obj_path =  $this->realSavePath  .$match_tid .'/';
                if(isset($tid_list[$obj_path])) {     
                    $tid_list[$obj_path]['list'][] =  $ov; //内容填充，将一个“文件夹”下的key归集在以tid为下标的数组list中
                }
            }
        }
       
      
        return $this->_patchDelete($tid_list, $current_tid);
    }
    
    
    
    /**
     * 抽取objectkey路径中的tid
     * @param unknown $realObjectKey
     * @return 
     */
    private function _getTidFormObjectKey($realObjectKey) {
        $pattern = "/(?:{$this->objSubPrefix}\/{$this->thirdId}\/[A-Za-z0-9-]+\/)+([A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12})/";
        preg_match($pattern, $realObjectKey, $match);
        return !empty($match[1]) ? $match[1] : false;
    }
    
   
    
    /**
     * 删除object"文件夹"及内容
     * @param array $tid_list
     * {
        app-conf-backup/conf/mi/12133435353535/3DE82846-22DF-F8DC-C414-6880BD116898/: {
                lastModified: "2016-03-03T08:21:11Z",
                lastModifiedTimeStamp: 1456993271,
                list: [
                        {
                        key: "app-conf-backup/conf/mi/12133435353535/3DE82846-22DF-F8DC-C414-6880BD116898/gg/x",
                        lastModified: "2016-03-03T08:21:11Z",
                        eTag: "6512bd43d9caa6e02c990b0a82652dca",
                        size: 2,
                        owner: {
                            id: "ddb6d93b58cb4fe5a5e809202adbd7a2",
                            displayName: "PASSPORT:2263141684"
                            }
                        }
                      ]
        },
        app-conf-backup/conf/mi/12133435353535/0D550FBB-3C95-3E59-8188-0A933425BF2C/: {
                lastModified: "2016-03-03T08:21:10Z",
                lastModifiedTimeStamp: 1456993270,
                list: [
                        {
                        key: "app-conf-backup/conf/mi/12133435353535/0D550FBB-3C95-3E59-8188-0A933425BF2C/gg/x",
                        lastModified: "2016-03-03T08:21:10Z",
                        eTag: "6512bd43d9caa6e02c990b0a82652dca",
                        size: 2,
                        owner: {
                            id: "ddb6d93b58cb4fe5a5e809202adbd7a2",
                            displayName: "PASSPORT:2263141684"
                            }
                        }
                    ]
        },
       }
     * @return array
     */
    private function _patchDelete($tid_list, $tid) {
        
        uasort($tid_list, 'self::myListSort'); //按时间戳lastModifiedTimeStamp排序（大到小)
        
        $keepNum = $tid === 0 ? 0 : $this->keepNum; //保留老备份条数 当前tid是0时删除全部备份,否则指定条数保留 

        //尝试获取要指定删除的文件
        $dfkey_info = $this->_getKeyInfo('dfkey');
        if(is_array($dfkey_info) && !empty($dfkey_info)) {
            $delresult = $this->_specifyDelete($tid_list, $dfkey_info);
            if($delresult) {
                return array();
            }else {
                return array('error_code' => 100001, 'error_msg'=> '删除失败');
            }
        }
        
        $now_num = 0; //循环当前条数 
        foreach ($tid_list as $tk => $tv) {
            
            if($now_num >= $keepNum) {
                
            }else{
                $now_num++;
                continue;
            }
           
            $tmp_tid = $this->_getTidFormObjectKey($tk);
            if($tmp_tid === $tid && $tid !==0) {
                //如果tid不为零，则当前事务也不要删
                $now_num++;
                continue;
            }
            
            $list_num = count($tv['list']);
            $list_counter = 0;
            foreach ($tv['list'] as $ok => $ov) {
                $result = $this->bos->deleteObjectByRealKey($ov['key']);
                if($result['status'] === 1) {
                    unset($tid_list[$tk]['list'][$ok]);
                    $list_counter += 1;
                }
            }
            if($list_num === $list_counter) {
                //一个文件夹里的都删除了才删文件夹，避免遗留垃圾数据
                $result = $this->bos->deleteObjectByRealKey($tk);
                if($result['status'] === 1) {
                    unset($tid_list[$tk]);
                }
            }
            
            $now_num++;
            
        }
        
        if($tid === 0) {
            $this->_setCurrent(0, 0);//设置当前事务为0
        }
        
        return $tid_list;
    }
    
    /**
     * 删除指定文件
     * @param $tid_list 事务及文件列表，已按新到旧排序
     * @param $dfkey_info 
     * @return array
     */
    private function _specifyDelete($tid_list, $dfkey_info) {
        
        if(is_array($tid_list) && is_array($dfkey_info) && !empty($tid_list) && !empty($dfkey_info)) {
            //处理tid_list第一个元素（最新的一次备份内容）
            foreach ($tid_list as $tk => $tv) {
                $tmp_tid = $this->_getTidFormObjectKey($tk); //事务id
                foreach ($tv['list'] as $ok => $ov) {
                    $is_deleted = false;
                    foreach($dfkey_info as $dk => $dv) {
                        //用事务id+文件路径的方式来匹配实际文件，保证唯一性    
                        if(strrpos($ov['key'] , $tmp_tid. $dv['name']) != false) {
                            $result = $this->bos->deleteObjectByRealKey($ov['key']);
                            if($result['status'] === 1) {
                                
                            }else {
                                return false; //只要有一个匹配到存在的文件删除不成功就认为删除失败
                            }
                            
                        }    
                           
                    }
                    
                }
                
                return true; //只处理$tid_list第一个元素（最新的一次备份内容，其他备份不动)
                
            }
        
            
        }
        return true;
        
    }
    
    
  
    
    
    /**
     * 请求验证
     * @return null
     */
    private function _verifyAccess() {
        
        
        if(empty($_GET['params'])) {
            $this->_retrunError(503, 13);
        }
       
     
        //1. ras解密信息 获取 第三方标示，access_token, open_id
        $params_str = bd_RSA_DecryptByDK(B64Decoder::decode($_GET['params'], 0));
        

        //for test
        //$params_str = 'third_id=gi&access_token=aaabbccc&open_id=12345678911';       //8295a99e8de0c379afe9e00bf71bc5a33df1
        //$params_str =  'third_id=gi&access_token= &open_id=DC2AEC1BFC574CD8B52CE04FE152CAE1'; 
            
        parse_str($params_str,$params);
  
       
        if(!$params['third_id'] || !$params['open_id']) {
            $this->_retrunError(503, 15);
        }
        
        //Edit by fanwenli on 2017-07-24, if open_id is null, then return error
        if($params['open_id'] && $params['open_id'] == 'null') {
            $this->_retrunError(503, 59);
        }
        
        
        //百度用户备份时，需要检测用户是否被注销或者封禁，是则提示客户端登录失效
        if('baidu' === $params['third_id']) {
            $uid = intval($params['open_id']);
            $userInfo = Util::getUserInfoByUid($uid);
            if (empty($userInfo) || ( isset($userInfo[$uid]['userstate']) && (1 == $userInfo[$uid]['userstate'] || 2 == $userInfo[$uid]['userstate']))) {
                //只有当验证到用户被封禁（1）或被注销（2）时，才提示403，其他情况不准用403返回。 403向客户端表示登录失效。    
                $this->_retrunError(403, 60);    
            }
        }
       
        //2.参数验证   第三方应用标示（mi/huawei/..）,第三方唯一id, 目录标示(如配置项备份 => 'conf' ), 平台号，版本号
        
        $this->thirdId = $params['third_id'];

        $this->uniqueId = $this->uniqueSlat($params['third_id'], $params['open_id']);
        
        //根据文件类型分不同的文件夹保存
        if(isset($_GET['ftype'])) {
            //Edit by fanwenli on 2017-07-10, judge file dir type for uploading these file to the right place
            $file_dir_type = trim($_GET['ftype']); //文件目录名
            switch ($file_dir_type) {
                case 'uwords':
                    $this->objSubPrefix = 'uwords'; //用户自造词
                    break;
                case 'lazycorpora':
                    $this->objSubPrefix = 'lazycorpora'; //懒人短语
                    break;
                default:
                    break;
            }
                
        }
         
        //3. 支持多文件上传 文件将会以  /conf/mi/uniqueid/guid/自定义路径/android.txt  方式保存
        $this->savePath = $this->objSubPrefix .'/'. $this->thirdId .'/'. $this->uniqueId .'/' ;
        
        $this->realSavePath = $this->objPathPrefix . '/' . $this->savePath;
        
    }
    
    /**
     * verify upload file
     *
     * @param
     * 		参数名称：$upload_file
     *      是否必须：是
     *      参数说明：upload_file
     *
     * @param
     * 		参数名称：$key
     *      是否必须：是
     *      参数说明：校验key
     * 
     * @return null
     */
    private function _verifyUploadFile($upload_file, $key){
 
        if ( !(isset ( $upload_file ) && $upload_file['error'] == 0
            && $upload_file['size'] > 16 && $upload_file['size'] < 51200000 ) ){
            $this->_retrunError(400, 42);
        }
        $key_decode = bd_AESB64_Decrypt($key); //InputEncoderV2::AESB64Decrypt ( $key );
 
        if ( (false === $key_decode) || (strlen ($key_decode) !== 24) ){
            $this->_retrunError(400, 43);
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
            $this->_retrunError(400, 42);
        }
    }
    
    /**
     * 上传文件
     *
     * @param
     * 		参数名称：$tid
     *      是否必须：是
     *      参数说明：tid
     *
     * @return array 上传文件(名称为PCS存储名称)列表
     */
    private function _uploadFile($current_id, $tid){
        
        $upload_file_list = array();
        
        //事务文件夹写入
        $result =  $this->bos->putObjectFromString($this->savePath . $tid .'/', '');
        if($result['status'] !== 1) {
            $this->_retrunError(503, 31);
        }
        
        $this->_copyTrans($current_id, $tid);
        
        //get key info
        $key_info = $this->_getKeyInfo();
        
        foreach ($_FILES as $key => $value){
            //排除指定上传文件
            if ( ('ukey' === $key) || ('key' === $key) ){
                continue;
            }
         
            //verify file
            if ( !isset($key_info[$key]) ){
                $this->_retrunError(400, 42);
            }
        	
            $this->_verifyUploadFile($value, $key_info[$key]['key']);
            
            //Edit by fanwenli on 2018-07-16, merge usrinfo and vkwords
            $this->_mergeCustomeFile($current_id, realpath($value['tmp_name']), $key_info[$key]['name']);
            
            //upload file to bos 
            $result = $this->_upload( $tid, realpath($value['tmp_name']), $key_info[$key]['name']);
            if (false === $result){
                $this->_retrunError(503, 44);
            }
            
            array_push($upload_file_list, $result);
        }


        $result = $this->_setCurrent($tid, time());
        if(false === $result) {
            $this->_retrunError(503, 53);
        }
        
        
        return $upload_file_list;
    }
    
    /**
     * 复制事务
     * @param string $current_id
     * @param string $tid
     * @return null
     */
    private function _copyTrans($current_id, $tid) {
        
        $file_list = array();
        
        if($current_id ) {
            $src_path = $this->savePath . $current_id .'/';
            $target_path = $this->savePath . $tid .'/';
            
            $result = $this->bos->listObjects(array('prefix'=> $src_path));
         
            if($result['status'] != 1) {
                $this->_retrunError(503, 45);
            }
            
            $src_list = $result['data']['contents'];
            foreach ($src_list as $k => $v) {
                $src_obj_key = $v['key'];
                $target_obj_key = str_replace($src_path, $target_path, $src_obj_key);
                if($src_obj_key != $target_obj_key && $src_obj_key != $src_path) {
                        
                    $meta_result = $this->bos->getObjectMetadata($src_obj_key);
                    if($meta_result['status'] != 1) {
                        $this->_retrunError(503, 46);
                    }
                    
                    $info = $meta_result['data']['userMetadata'];
            
                    $url_result = $this->bos->getUrl($target_obj_key, 'http://pri.mi.baidu.com/');
                    
                    if($url_result['status'] !== 1) {
                        $this->_retrunError(503, 58);
                    }
                    $info['durl'] = $url_result['data'] ;
                    
                    //Edit by fanwenli on 2018-07-16, get all file in bos
                    $this->arrBackupFiles[] = $info['durl'];
                    
                    $opt = array('userMetadata' => $info);
                    
                    $reuslt = $this->bos->copyObject($src_obj_key, $target_obj_key, $opt);
                    if($result['status'] != 1) {
                        $this->_retrunError(503, 51);
                    }
                }
            }      
        }
    }
    
    /**
     * 获取列表 
     * @param boolean $withDurl
     * @return multitype:unknown Ambigous <number, NULL>
     */
    private function _getBackupList($withDurl = true)
    {
        
        $current_info = $this->_getCurrent();
        if($current_info === false) {
            $this->_retrunError(503, 20);
        }
        $current_trans_path = $this->savePath . $current_info['tid'] .'/';
        
        $result = $this->bos->listObjects(array('prefix'=>$current_trans_path ));
        
        if($result['status'] === 0) {
            $this->_retrunError(503, 45);
        }
        
        $backup_list = array();
        $backup_list['tid'] = $current_info['tid'];
        $backup_list['ctime'] = $current_info['ctime'];
        
        foreach ($result['data']['contents'] as $k => $v) {
            if($v['key'] == $current_trans_path) {
                continue;
            }
            
            $meta_result = $this->bos->getObjectMetadata($v['key']);
            if($meta_result['status'] != 1) {
                $this->_retrunError(503, 46);
            }
            
            $node_current = &$backup_list;
            
            $meta_info = $meta_result['data']['userMetadata'];
            
            $router = explode("/", $meta_info['path']);
            
            unset($meta_info['name']);
            unset($meta_info['path']);
            
            if(!$withDurl) {
                unset($meta_info['durl']);
            }
            
            $rc = count($router);
            
            for($i = 0 ; $i < $rc ; $i++) {
                //空串""
                if (0 === $i){
                    continue;
                }
                
                if($i == ($rc - 1)) {
                    $name = $router[$i];
                    $node_current[$name] = $meta_info;
                } else {
                    if(isset($node_current[$router[$i]])) {
                        $node_current = &$node_current[$router[$i]];
                    }else {
                        $node_current[$router[$i]] = array();
                        $node_current = &$node_current[$router[$i]];
                    }
                }
            }
            
        }
        
        return $backup_list;
        
    }
    
    /**
     * 上传文件
     * @param unknown $tid
     * @param unknown $file_path
     * @param unknown $file_name
     * @return boolean|multitype:string unknown NULL Ambigous <number, number, string, Ambigous <mixed, string>>
     */
    private function _upload($tid, $file_path, $file_name) {
        $obj_path =  $this->savePath . $tid .'/' .$file_name;
        
        $info = array(
            'name' => basename($file_name),
            'path' => $file_name,
            'mtime' => (String)(time()),
            'size' => (String)filesize($file_path),
            'md5' =>  md5($file_path),
        );
        
        
        $result = $this->bos->getUrl($obj_path, 'http://pri.mi.baidu.com/');
        if($result['status'] !== 1) {
            $this->_retrunError(503, 58);
        }
        $info['durl'] = $result['data'] ;
        
        
        $opt = array('userMetadata' => $info);
        $result = $this->bos->putObjectFromFile($obj_path , $file_path, $opt);
        if($result['status'] !== 1) {
            return false;
        }
        
        return  $info;
        
    }
    
    /**
     * 获取上传文件校验信息
     * @param $key_name 上传文件名 
     *
     * @return array
     */
    private function _getKeyInfo($key_name = 'key'){
        
        if ( !(isset ( $_FILES [$key_name] ) && $_FILES [$key_name] ['error'] === 0 && $_FILES [$key_name]['size'] < 10240) ) {
            if($key_name == 'key') {
                $this->_retrunError(400, 42);    
            }
            
        }
    
        $key_buffer = file_get_contents( $_FILES [$key_name]['tmp_name'] );
        if(empty($key_buffer)) {
            return array();
        }
        
        $decode_key_info = json_decode($key_buffer, true);
  
        $key_info = array();
        foreach ($decode_key_info as $key => $value){
            $value_info = array();
            	
            if ( !isset($value['name'])
                || ('/' !== substr($value['name'], 0, 1)) || ('/' === substr($value['name'], -1, 1)) ){
                $this->_retrunError(400, 42);
            }
            	
            $value_info['name'] = $value['name'];
            $value_info['key'] = urldecode($value['key']);
            $key_info[$key] = $value_info;
        }
    
        return $key_info;
    }
    
    /**
     * 获取当前备份信息 
     * @return array
     */
    private function _getCurrent() {
        
        $arrReturn =  array(
            'tid' => 0,
            'ctime' => 0,
        );
  
        $result = $this->bos->getObjectMetadata($this->savePath);

        if($result['status'] == 0  && strpos(strtolower($result['message']), 'not found') === false ) {
            return false;
        }
        $result = $result['data'];
        if(!empty($result['userMetadata']['tid'])) {
            $arrReturn['tid'] = $result['userMetadata']['tid'];
        }
        if(!empty($result['userMetadata']['ctime'])) {
            $arrReturn['ctime'] = $result['userMetadata']['ctime'];
        }
        
        return $arrReturn;
    }
    
    /**
     * 设置当前备份信息    将当前备份的list返回结果作为json字符串作为object的值存入（避免获取list要遍历object）
     * ， 将事务guid和创建时间等信息作为object的meta信息存入，
     * @param string $guid
     * @param int $time
     * @param array $fileList
     * @return array
     */
    private function _setCurrent($guid, $time, $fileList = array()) {
        
        $userMeteadata = array( 
            'tid' => (String)$guid, 
            'ctime' => (String)$time,
            'platform' => !empty($this->platform) ? $this->platform : "",
            'version' =>  !empty($this->version) ? $this->version : "",
        );
        
        $result =  $this->bos->putObjectFromString($this->savePath, json_encode($fileList) , array('userMetadata' => $userMeteadata,));
        
        return $result['status'] !== 1 ? false : $result['data'];
    }
    
    
    
    
    /**
     * 根据平台号获取手机操作系统类型
     * @param $platform string 手机平台号
     *
     * @return string (ios, symbian, mac, android)
     */
    private function _getPhoneOS($platform) {
        $platform = strtolower($platform);
        if (substr($platform, 0, 1) === 'i') {
            return 'ios';
        } elseif (substr($platform, 0, 1) === 's') {
            return 'symbian';
        } elseif (substr($platform, 0, 1) === 'm') {
            return 'mac';
        } else {
            return 'android';
        }
    }
    
    /**
     * 生成guid(就是tid)
     * @return string
     */
    private function _getGuid(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid =
             substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
           
            return $uuid;
        }
    }
    
    
    /**
     * get error info
     *
     * @param
     * 		参数名称：$error_code
     *      是否必须：是
     *      参数说明：error_code
     *
     * @return string
     */
    private function _getErrorInfo($error_code){
        $error_info = array();
        $error_info['error_code'] = $error_code;
        $error_info['error_msg'] = self::$ERROR_INFO[$error_code];
        try{
            $arrLog = $error_info;
            $arrLog['response_header'] = headers_list();
            $arrLog['log_time'] = date('Y-m-d H:i:s');

            $arrLog['req_uri'] =  $_SERVER["REQUEST_URI"];
            $arrLog['req_query'] = $_SERVER["QUERY_STRING"];

            CustLog::write('v5_backup_error_log',
                $error_code .':'.self::$ERROR_INFO[$error_code],
                array('trace' => 1, 'trace_limit' => 6));

        } catch (Exception $e) {

        }
        return $error_info;
    }
    
    /**
     * 输出错误码到浏览器 
     * @param unknown $http_code
     * @param unknown $error_code
     * @return null
     */
    private function _retrunError($http_code, $error_code) {

        $arrErrorInfo = $this->_getErrorInfo($error_code);

        if(1 === intval($_GET['stdrt'])) {
            $arrResult = Util::initialClass();
            $arrResult['ecode'] = $arrErrorInfo['error_code'];
            $arrResult['emsg'] = $arrErrorInfo['error_msg'];
            echo json_encode($arrResult);
        } else {
            header('X-PHP-Response-Code: '. $http_code, true, $http_code);
            print json_encode($arrErrorInfo);

        }
        exit;

    }
    
    /**
     * 返回数据
     * @param unknown $data
     * @return string encrypt json data
     */
    private function _returnResult($data) {
        if(is_array($data)) { 
            $data =  json_encode($data);
        }
        
        //Edit by fanwenli on 2017-07-14, if ftype is lazycorpora, then result will be trim
        //if(isset($_GET['ftype']) && trim($_GET['ftype']) == 'lazycorpora') {
            $data = trim(bd_AESB64_Encrypt($data));
        //} else {
        //    $data = bd_AESB64_Encrypt($data);
        //}


        if(1 === intval($_GET['stdrt'])) {
            $arrResult = Util::initialClass();
            $arrResult['data'] = array('value' => $data );
            echo json_encode($arrResult);
        } else {
            print $data;
        }

        exit;
        

    } 
    
    /**
     * 生成存储用id (防止open_id中出现的非法字符对程序造成的影响）
     * @param unknown $third_id
     * @param unknown $open_id
     * @return string
     */
    private function uniqueSlat($third_id, $open_id) {
        return md5(trim($third_id).trim($open_id)) .(substr( sha1(trim($open_id)), 0 , 4 ));
    }
    
   
    
    /**
     * @route({"POST","/upload"})
     * 获取精品应用列表(整合管理后台推荐应用和海纳应用数据)
     * @return({"body"})
     */
    public function upload()
    {
        
        //无法修改header问题处理
        ob_end_flush();
        
        $current_info = $this->_getCurrent();
       
        if($current_info === false) {
            $this->_retrunError(503, 20);
        }
        
        $this->_cleanNotCurrentBackup($current_info['tid']);
    
        $new_tid = $this->_getGuid();
    
        $this->_uploadFile($current_info['tid'], $new_tid);
      
        $this->_returnResult($this->_getBackupList(false));
    }
    
    /**
     * @route({"POST","/list"})
     * 获取精品应用列表(整合管理后台推荐应用和海纳应用数据)
     * @return({"body"})
     */
    public function getList() {
        $this->_returnResult($this->_getBackupList());
    }
    
    
    /**
     * @route({"POST","/delete"})
     * 删除备份
     */
    function deleteList() {
        $this->_returnResult($this->_cleanNotCurrentBackup(0));
    }
    
    
    
    /**
     * 按时间戳排序 (从大到小)
     * @param array $a
     * @param array $b
     * @return number
     */
    public static function myListSort($a, $b) {
        if($a['lastModifiedTimeStamp'] == $b['lastModifiedTimeStamp']) {
            return 0;
        }
        return $a['lastModifiedTimeStamp'] > $b['lastModifiedTimeStamp'] ? -1 : 1;
    }
    
    /**
     * 处理上传文件内容合并与否
     *
     *
     * @param
     *      参数名称：$current_id
     *      是否必须：是
     *      参数说明：之前上传的文件所在次级目录
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
    */
    public function _mergeCustomeFile($current_id, $file_path, $file_name){
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
            
            //find backup before
            if(is_array($this->arrBackupFiles) && !empty($this->arrBackupFiles)){
                foreach($this->arrBackupFiles as $val){
                    //get usrinfo_stat path
                    if($file_real_name == 'usrinfo_stat.ini' && preg_match('/usrinfo_stat\.ini/i',$val,$arr)){
                        $usrinfo_stat_path = $val;
                        break;
                    }
                    
                    //get kv path
                    if($file_real_name == 'vkword.txt' && preg_match('/vkword\.txt/i',$val,$arr)){
                        $vk_path = $val;
                        break;
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
                
                $rs = Util::request($backup_file_path, 'GET', null, 5);
                
                //file exists
                if($rs['http_code'] == '200'){
                    //get file content from current file
                    $file_save_tmp_name = str_replace('/', '_', $this->savePath).'_'.$current_id.'_'.time().'.'.$tail;
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
}
