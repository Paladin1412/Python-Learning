<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;
use models\CellWordsBaseModel;
use models\WordlibModel;
use utils\Bos;

/**
 *
 * cellwords
 * 说明：词库相关接口
 *
 * @author zhoubin
 * @path("/cw/")
 */
class CellWords
{
    /**
     *
     * android下发新版本热词、分类词库输入法版本(混输词)
     * @var string
     */
    const ANDROID_NEW_FORMAT_WORD_INPUT_VERSION_MIN = '5.5.0.0';
    
    /**
     *
     * ios下发新版本热词、分类词库输入法版本
     * @var string
     */
    const IOS_NEW_FORMAT_WORD_INPUT_VERSION_MIN = '5.6.0.0';
    
    /**
     * 
     * memcache 中词库最新版本的key的前缀
     * @var string
     */
    const LAST_VERSION_CACHE_KEY_PERFIX ='ime_api_v5_cw_ini_last_version_';
    
    
    /**
     * 无更新时返回显示给用户的信息
     * 
     * @var string
     */
    const NO_UPDATE_MSG='没有可用更新'; //
    
    /**
     * 
     * 客户端词库版本
     * @var int
     */
    private $_client_ver=0;
    
    /**
     * 当前词库最新版本
     *
     * @var int
     */
    private $_last_version=0;
    
    /**
     * 返回给客户端的数据
     * 
     * @var array
     */
    private $_return_array=array(); 
    
    /**
     * 缓存
     * 
     * @var mix
     */
    private $_ime_cache;
    
    /**
     * 最新版本在memcache缓存中的key
     * 
     * @var string
     */
    private $_last_vaersion_cache_key;
    
    /**
     * 是否为新格式词库
     *
     * @var string
     */
    private $bolIsNewFormat = false;
    
    /**
     * 新格式词库后缀
     *
     * @var string
     */
    private $strNewFormatTail = '';
    
    
    /**
     * 用户的系统类型
     *
     * @var string
     */
    private $_os = '';
    
    
   
    private $micweb_httproot = null;
    
    private $micweb_httproot_bos = null;
    
    private $micweb_webroot_bos = null;
    
    private $bos_domain_https = null;
    
    private $platform = '';
    
    private $version = '';
    
    /**
     * 客户端版本
     * @var int
     */
    private $clientVersion = 0;
    
    //客户端流行词最大保留天数
    const MAX_REMAIN_DAYS = 10;
    //客户端流行词最大保留条数 maxremainnumber
    const MAX_REMAIN_NUMBER = 30;
    
    
    /**  @property */
    private $wise_apprec_url;
    
    /**  @property */
    private $wise_apprec_from;
    
    /**  @property */
    private $wise_apprec_token;
    
    private $cuid, $ua, $uid, $hot_words;
    
    
    /**
     * 初始化，获取cache和最新版本，判断是否有更新
     */
    public function __construct(){
        
        $this->platform =  isset($_GET['platform']) ? $_GET['platform'] : '';
        $this->version = isset($_GET['version']) ? $_GET['version'] : '';
        $this->cuid = $_GET['cuid'];
        $this->ua = $_GET['ua'];
        $this->uid = $_GET['uid'];
        $this->clientVersion = isset($_GET['clientversion']) ? $_GET['clientversion'] : 0;
        
        $this->micweb_httproot = GFunc::getGlobalConf('micweb_httproot');
        $this->micweb_httproot_bos = GFunc::getGlobalConf('micweb_httproot_bos');
        $this->micweb_webroot_bos = GFunc::getGlobalConf('micweb_webroot_bos');
        $this->bos_domain_https = GFunc::getGlobalConf('bos_domain_https');
        
        
        $hot_words_model =  IoCload('models\\HotWordsModel');
        
        //下发词库格式判断（混输词）
        if ( 'android' === Util::getPhoneOS($this->platform) && $this->getVersionIntValue(str_replace('-', '.', $this->version)) >= $this->getVersionIntValue(self::ANDROID_NEW_FORMAT_WORD_INPUT_VERSION_MIN) ){
            $this->bolIsNewFormat = true;
            $this->strNewFormatTail = '_newformat';
        }
        //下发词库格式判断（混输词）
        if ( 'ios' === Util::getPhoneOS($this->platform) && $this->getVersionIntValue(str_replace('-', '.', $this->version)) >= $this->getVersionIntValue(self::IOS_NEW_FORMAT_WORD_INPUT_VERSION_MIN) ){
            $this->bolIsNewFormat = true;
            $this->strNewFormatTail = '_newformat';
        }
         
        $this->_ime_cache= GFunc::getCacheInstance();
        $this->_getLastVersion();
       
    }
    
    /**
     * 返回输入法版本数值
     * 如5.1.1.5 5010105
     * 
     * @param
     *      参数名称：strVersionName
     *      是否必须：是
     *      参数说明：version name
     *
     * @param
     *      参数名称：intDigit
     *      是否必须：是
     *      参数说明：位数
     *
     *
     * @return string
     */
    private function getVersionIntValue($strVersionName, $intDigit = 4){
        $strVersionName = str_replace('-', '.', $strVersionName);
        
        $intVal = 0;
        $arrVersonDigit = explode('.', $strVersionName);
        for ($i = 0; $i < $intDigit; $i++){
            $intDigitVal = 0;
            switch ($i){
                case 0:
                    $intDigitVal = intval($arrVersonDigit[$i]) * 1000000;
                    break;
                case 1:
                    $intDigitVal = intval($arrVersonDigit[$i]) * 10000;
                    break;
                case 2:
                    $intDigitVal = intval($arrVersonDigit[$i]) * 100;
                    break;
                case 3:
                    $intDigitVal = intval($arrVersonDigit[$i]);
                    break;
                default:
                    break;
            }
            
            $intVal = $intVal + $intDigitVal;
        }
        
        return $intVal;
    }
    
    /**
     * 获取最新版本，如已是最新版本直接返回无更新
     * 
     * 
     */
    private function _getLastVersion(){
        
        $this->_client_ver      = intval($_REQUEST["ver"]);
        $this->_client_wid      = intval($_REQUEST["wid"]);
        
        $this->_last_vaersion_cache_key= self::LAST_VERSION_CACHE_KEY_PERFIX. $this->_client_wid;
        
        $this->_last_version    =$this->_ime_cache->get($this->_last_vaersion_cache_key);
     
        if ($this->_last_version === false || $this->_last_version == null){ 
            /*
             *缓存中没有最新版本的，数据库里读取数据，写入缓存 
             */
            
            $cell_words= new CellWordsBaseModel($this->_client_wid);
            
            $this->_last_version= $cell_words->getLastVersion();
            
            if($this->_last_version===false){
                $this->_last_version=0;
            }
            
            $this->_ime_cache->set($this->_last_vaersion_cache_key, $this->_last_version, GFunc::getCacheTime('2hours'));
        }else{
            
            $this->_last_version = intval($this->_last_version);
        }
        
        /*
         * 没有更新直接输出
         */
        if($this->_last_version<=$this->_client_ver){
            $this->_return_array['status']=0;
            $this->_return_array['msg']=self::NO_UPDATE_MSG;
            Util::outputJsonResult($this->_return_array);
        }
        
        //ios客户端奔溃，可能原因是客户端载入静默词库时有问题。暂时将静默词库的更新屏蔽, 条件： 平台=ios && 词库61001 <= wid <= 61365  add by zhoubin 20170811
        if('ios' === Util::getPhoneOS($this->platform) && 61001 <= $this->_client_wid && $this->_client_wid <= 61365 && Util::getVersionIntValueNew($_GET['version']) < 7060000 ) {
            $this->_return_array['status']=0;
            $this->_return_array['msg']=self::NO_UPDATE_MSG;
            Util::outputJsonResult($this->_return_array);
        }
        
        if($this->_last_version>$this->_client_ver){
            $this->_return_array['status']=1;
            $this->_return_array['lastversion']=$this->_last_version;
        }
    }
    
    /**
     * @route({"GET","/cs"})
     * 客户端分类词库更新检测接口
     * @return
     */
    public function csAction() {
        Util::outputJsonResult($this->_return_array);
    }
    
    
    /**
     * @route({"GET","/ds"})
     * 分类词库下载接口
     * @return 
     */
    public function dsAction(){
        
        if($this->_client_wid >= 100000 ) {
            $this->bolIsNewFormat = false;
        } else {
            if(in_array($_GET['platform'], array('i9','i10','a11')) ) {
                $this->bolIsNewFormat = true;    
            }    
        }  
        
        if($this->_client_ver > 0 && ($this->_last_version - $this->_client_ver ) <= 60 ){
            Util::dlIncrWordslib($this->_client_wid, $this->_client_ver, $this->_last_version, $this->bolIsNewFormat );
        }else{
            Util::dlFullWordslib($this->_client_wid, $this->_last_version, $this->bolIsNewFormat );
        }
        
        //上面函数如果有相应的输出文件，函数会直接header到文件地址并exit,否则程序继续执行一下内容
        $this->_return_array['status']=0;
        $this->_return_array['msg']=self::NO_UPDATE_MSG;
        return $this->_return_array;
    }
    
    /**
     * @route({"POST","/dy"})
     * 云优化下载接口
     * 下载的同时客户端post用户自造词文件，通过dy01字段上传到服务器，
     * @return 
     */
    public function dyAction(){
        
        /*
         * 保存用户自造词文件
         */   
        $this->_saveUserWords($_FILES['dy01']);
        
        if($this->_client_wid >= 100000 ) {
            $this->bolIsNewFormat = false;
        } else {
            if(in_array($_GET['platform'], array('i9','i10','a11')) ) {
                $this->bolIsNewFormat = true;    
            }    
        }
        
        //仅输出旧版格式,
        Util::dlIncrWordslib($this->_client_wid, $this->_client_ver, $this->_last_version, $this->bolIsNewFormat );
        
        //上面函数如果有相应的输出文件，函数会直接header到文件地址并exit,否则程序继续执行一下内容
        $this->_return_array['status']=0;
        $this->_return_array['msg']=self::NO_UPDATE_MSG;
        return $this->_return_array;
    }
    
    /**
     * @route({"GET","/dy"})
     * 云优化下载接口
     * 下载的同时客户端post用户自造词文件，通过dy01字段上传到服务器，
     * @return 
     */
    public function dyGetAction(){
        return $this->dyAction();
    }
    
    /**
    * 上传用户文件
    * @param $upfile
    * @return boolean
    */
    private function _saveUserWords($upfile){
        $this->saveUploadFiles($upfile, 'userwords','bdimezip');
    }
    
    /**
     * 
     * 保存客户端上传的各类文件，统一文件命名规则，路径策略
     * 
     * 总命名规则是:
     * /路径/年/月/日/时/分/秒/时间戳__产品线__平台__uid_屏宽_屏高_机型_版本_平台__cuiddeviceid部分
     * __cuid逆序imei部分__当前渠道号__初始渠道号__android随机数__1字符随机串.后缀v加密版本
     * 大于10M和小于10字节的文件不保存
     * 
     * @param array $upload_file $_FILES['file']上传的单个文件
     * @param string $save_path 存储路径
     * @param string $suffix 文件名后缀
     * 
     * @return boolean 保存成功返回true，失败返回false
     */
    public function saveUploadFiles($upload_file, $save_path, $suffix='data'){
        
        //qa need shahe env can_repeat_upload
        $can_repeat_upload = intval(Gfunc::getGlobalConf('qa_can_repeat_upload'));
        
        if( 1 !== $can_repeat_upload )
        {
            $is_repeat_upload= Util::isRepeatRequest('ime_api_v5_upload_user_file_'.$suffix, 100);
            if($is_repeat_upload){
                return true;
            }
        }
        
        if(isset($upload_file) 
        && intval($upload_file['error'])===0 
        && isset($upload_file['tmp_name']) 
        && isset($upload_file['error']) 
        && isset($upload_file['size'])  
        && intval($upload_file['size'])<=1024*1024*10 
        && intval($upload_file['size'])>=10){
            
            $acmode = $_REQUEST['acmode'];
            
            $newname='';
            
            //定义本地有可写权限目录位置
            $intPos = strpos(APP_PATH, '/app');
            $strWordsFileDir = substr(APP_PATH, 0, $intPos) . "/data/app/v5/uploadinfo/";
               
            //拼装本地可写目录路径及文件名，
            $savedir= $strWordsFileDir .$save_path;
            
            if(substr($savedir,-1,1)!=='/'){
                $savedir.='/';
            }
            $saveurl = $savedir.date("Y")."/".date("m")."/".date("d")."/".date("H")."/".date("i")."/".date("s")."/";

            $saveurl.=time().'__';
            $saveurl.='ime__';
            if($acmode != null && $acmode == 'setting') {
                $saveurl .= $this->platform.'setting__';
            } else {
                $saveurl .= $this->platform.'__';
            }
            $saveurl .= $this->uid.'__';
            $saveurl .= $this->ua.'__';
            $saveurl .= $this->cuid_a.'__';
            $saveurl .= $this->cuid_b.'__';
            $saveurl .= $this->cfrom.'__';
            $saveurl .= $this->from.'__';
            $saveurl .= $this->rid.'__';
            $saveurl .= $this->client_ip.'__';
            
            $saveurl .= Util::getRandLowerString(1) . '__';

            
            $saveurl .= '.' . $suffix;
            $env = intval($_GET['env']);
            if($env > 0){
                $saveurl .= 'v'. $env;
            }
            
            Util::makeDir($saveurl,true);
            $save_result = move_uploaded_file($upload_file['tmp_name'],$saveurl);
            
            if($save_result) {
                
                $micweb_webroot_bos =  GFunc::getGlobalConf('micweb_webroot_bos');
                //上传bos
                $bos_url_ary = explode('/', $micweb_webroot_bos);
                $bucket =  $bos_url_ary[3];
                $perfix = $bos_url_ary[3] ;
    
                $localDir = str_replace(basename($saveurl) ,'',$saveurl);
                
                $objectKey =  str_replace($micweb_webroot_bos, '/', $localDir);
                $objectKey = '/' . $bos_url_ary[4] . $objectKey ;
                $bos = new Bos($bucket, $perfix);
                $result = $bos->putObjectFromFile($objectKey, $saveurl);
    
                if($result['status'] != 1) {
                    Logger::warning(' upload to bos failed ' . $result['message']);
                    $save_result = false;
                } else {
                    unlink($saveurl);
                }
            }
            
                
            return $save_result;
        }
        return false;
    }
    
    
    
    
}
    