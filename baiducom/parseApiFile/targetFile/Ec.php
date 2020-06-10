<?php
/**
 *
 * @desc ios表情包配置文件（plist, dylib）更新检测、下载接口      
 * move from /v4/?c=ec      add by 20160520
 * @author zhoubin05
 * @path("/ec/")
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use utils\Util;
use utils\Consts;
use utils\GFunc;
require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';

class Ec
{
    /**
     * plist和dylib的memcache的key字段前缀
     * 
     * @var string
     */
    const LATEST_VERSION_CACHE_KEY='ime_api_v5_emoji_plist_dylib';
    
    
    /**
     * plist客户端版本
     *
     * @var int
     */
    private $_plist_client_ver;

    /**
     * dylib客户端版本
     *
     * @var int
     */
    private $_dylib_client_ver;

    /**
     * 当前plist最新版本
     *
     * @var int
     */
    private $_plist_latest_version;
    
    /**
     * 当前dylib最新版本
     *
     * @var int
     */
    private $_dylib_latest_version;

    /**
     * 最新plist和dylib的信息
     *
     * @var array
     */
    private $_latest_info=array();

    /**
     * 返回给客户端的数据
     *
     * @var array
     */
    private $_return_array=array();
    
    
    
    
    /**
     * 构造函数
     * @param
     * @return  成功：  _db连接对象
     *          失败：  返回false
     **/
    public function __construct()
    {
        $this->_getLatestVersion();
    }
    
    /**
     * 获取plist和dylib最新版本，判断是否有更新，写入返回数组
     *
     */
    private function _getLatestVersion(){

        //plist客户端版本
        $this->_plist_client_ver = isset($_GET['plist_ver']) ? intval($_GET['plist_ver']) : 0 ;
        
        //dylib客户端版本
        $this->_dylib_client_ver = isset($_GET['dylib_ver']) ? intval($_GET['dylib_ver']) : 0 ;
        
        
        //从缓存中读取plist和dylib中的最新信息
        $this->_latest_info = GFunc::cacheGet(self::LATEST_VERSION_CACHE_KEY);

        /*
         *  缓存中没有信息，数据库里读取数据，写入缓存
         */
        if($this->_latest_info === false || is_null($this->_latest_info) || !is_array($this->_latest_info)){

            $emojiconf = IoCload("models\\EcModel");
            $this->_latest_info = $emojiconf->getLatestInfo();          

            /*
             *  空值同样存入缓存，保存两分钟
             */
            GFunc::cacheSet(self::LATEST_VERSION_CACHE_KEY, $this->_latest_info, 120);
            
            //从数据库中获取plist的最新版本
            if(isset($this->_latest_info['plist']) && !empty($this->_latest_info['plist'])){
                $this->_plist_latest_version = intval($this->_latest_info['plist']['ver']);
            }else{
                $this->_plist_latest_version = 0;
            }
            //从数据库中获取plist的最新版本
            if(isset($this->_latest_info['dylib']) && !empty($this->_latest_info['dylib'])){
                $this->_dylib_latest_version = intval($this->_latest_info['dylib']['ver']);
            }else{
                $this->_dylib_latest_version = 0;
            }
            
            
        }else{
            
            //从缓存中获取plist的最新版本
            if(isset($this->_latest_info['plist']) && !empty($this->_latest_info['plist'])){
                $this->_plist_latest_version = intval($this->_latest_info['plist']['ver']);
            }else{
                $this->_plist_latest_version = 0;
            }
            //从数据库中获取plist的最新版本
            if(isset($this->_latest_info['dylib']) && !empty($this->_latest_info['dylib'])){
                $this->_dylib_latest_version = intval($this->_latest_info['dylib']['ver']);
            }else{
                $this->_dylib_latest_version = 0;
            }
        }
        
        
        /*
         *   根据版本号，判断是否有更新，并写入返回信息
         */     
        //两个文件都没有更新
        if($this->_plist_latest_version <= $this->_plist_client_ver && $this->_dylib_latest_version <= $this->_dylib_client_ver){
            $this->_return_array['status'] = 0;           
        
        //plist有更新
        }elseif($this->_plist_latest_version > $this->_plist_client_ver && $this->_dylib_latest_version <= $this->_dylib_client_ver){
            $this->_return_array['status'] = 1;
            //plist信息
            $this->_return_array['plist']['ver'] = $this->_latest_info['plist']['ver'];       
            $this->_return_array['plist']['dlink'] = $this->_latest_info['plist']['dlink'];       
            $this->_return_array['plist']['filesize'] = $this->_latest_info['plist']['filesize'];     

        //dylib有更新
        }elseif($this->_plist_latest_version <= $this->_plist_client_ver && $this->_dylib_latest_version > $this->_dylib_client_ver){
            $this->_return_array['status'] = 2;
            //dylib信息
            $this->_return_array['dylib']['ver'] = $this->_latest_info['dylib']['ver'];
            $this->_return_array['dylib']['dlink'] = $this->_latest_info['dylib']['dlink'];
            $this->_return_array['dylib']['filesize'] = $this->_latest_info['dylib']['filesize'];
        
        //plist和dylib都有更新
        }else{
            $this->_return_array['status'] = 3;
            //plist信息
            $this->_return_array['plist']['ver'] = $this->_latest_info['plist']['ver'];       
            $this->_return_array['plist']['dlink'] = $this->_latest_info['plist']['dlink'];       
            $this->_return_array['plist']['filesize'] = $this->_latest_info['plist']['filesize'];                 
            //dylib信息
            $this->_return_array['dylib']['ver'] = $this->_latest_info['dylib']['ver'];
            $this->_return_array['dylib']['dlink'] = $this->_latest_info['dylib']['dlink'];
            $this->_return_array['dylib']['filesize'] = $this->_latest_info['dylib']['filesize'];         
        }
    }

    /**
     * 获取最新版本配置文件的二进制流文件
     * @param string $conf_file
     * @return null;
     */
    private function _getLatestPkg($conf_file){
        //客户端对所下载文件名不关心，下载直接跳转到V4的静态资源 
        @header('Location: ' . $this->_latest_info[$conf_file]['dlink_local']);
        return;
        
    }
    
    
    
    /**
     * @desc plist,dylib更新检测接口，返回更新信息
     * @route({"GET", "/ede"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function edeAction() {
        echo json_encode($this->_return_array);
        exit();
    }

    /**
     * @desc plist下载接口，返回最新版本plist的二进制流文件
     * @route({"GET", "/pdo"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function pdoAction() {
        $conf_file = 'plist';
        $this->_getLatestPkg($conf_file);
        exit();
    }
    
    
    /**
     * @desc dylib下载接口，返回最新版本dylib的二进制流文件
     * @route({"GET", "/ddo"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function ddoAction() {
        $conf_file = 'dylib';
        $this->_getLatestPkg($conf_file);
        exit();
    }
    
    
    
    /**
     * 获取远程文件尺寸
     * @param http_url $remoteUrl
     * @return int
     */
    private function getRemoteFileSize($remoteUrl) {
        $Orp_FetchUrl = new \Orp_FetchUrl();
        $httpproxy = $Orp_FetchUrl->getInstance(array('timeout' =>1000));
        
        $result = $httpproxy->get($remoteUrl);
        $err = $httpproxy->errmsg();
        
        
        if(!$err && $httpproxy->http_code() == 200) {
            $curl_info = $httpproxy->curl_info();
            if(isset($curl_info['size_download'])) {
                return intval($curl_info['size_download']);
            }
        }
        return 0;
    }
    
    
}
