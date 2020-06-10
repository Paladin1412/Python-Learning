<?php
use utils\GFunc;
/**
 * 三维词库
 * @author chendaoyan
 * @date 2016年5月5日
 * @path("/tw/")
 */
class ThreeWord {
    /**
     * memcache的key字段前缀
     * `
     * @var string
     */
    const LATEST_VERSION_CACHE_KEY='ime_api_v4_tw_wdu_latestversion_vtype_123_';
    
    /** @property cache */
    private $cache;
    
    /** @property 内部缓存默认过期时间(单位: 秒) */
    private $intCacheExpired;
    
    /**
     * 客户端三维词库版本
     *
     * @var int
     */
    private $_client_ver;
    
    /**
     * 客户端三维词库对应内核版本
     *
     * @var int
     */
    private $_client_vtype;
    
    /**
     * 当前三维词库最新版本
     *
     * @var int
     */
    private $_latest_version;
    
    /**
     * 最新三维词库的信息
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
     * 三维词库更新检测接口，返回更新信息
     * @route({"GET", "/tde/"})
     * @param({"vtype", "$._GET.vtype"}) string $vtype
     * @param({"ver", "$._GET.ver"}) string $ver 版本号
     * @return ({"body"})
     */
    public function tde($vtype, $ver) {
        $this->getLatestVersion($vtype, $ver);
        return $this->_return_array;
    }
    
    /**
     * 获取最新版本，判断是否有更新，写入返回数组
     * @param unknown $vtype
     * @param unknown $ver
     */
    private function getLatestVersion($vtype, $ver){
    
        $this->_client_ver = ! empty($ver) ? intval($ver) : 0;
        if ($this->_client_ver === - 1) { // 客户端传-1的时候这里需要特殊处理
            $this->_client_ver = 0;
        }
        $this->_client_vtype = ! empty($vtype) ? $vtype : 1;
        $this->_latest_info = $this->cache->get(self::LATEST_VERSION_CACHE_KEY . $this->_client_vtype);
         
        /*
         *	缓存中没有最新版本的，数据库里读取数据，写入缓存
         */
        if ($this->_latest_info === false || is_null($this->_latest_info)) {
            
            $objThreedwordModel = IoCload('models\\ThreeDWordModel');
            $this->_latest_info = $objThreedwordModel->getLatestInfo($this->_client_vtype);

            
            if(isset($this->_latest_info['isdberror'])&&$this->_latest_info['isdberror'] == 1 )
            {
                $this->_latest_info = array();
                $this->intCacheExpired = intval(GFunc::getCacheTime('10mins'));
            }

            /*
             * 空值同样存入缓存，保存两小时
             */
            $this->cache->set(self::LATEST_VERSION_CACHE_KEY . $this->_client_vtype, $this->_latest_info, $this->intCacheExpired);
            if (!empty($this->_latest_info)) {
                $this->_latest_version = intval($this->_latest_info['ver']);
            } else {
                $this->_latest_version = 0;
            }
        } else {
            if (!empty($this->_latest_info)) {
                $this->_latest_version = intval($this->_latest_info['ver']);
            } else {
                $this->_latest_version = 0;
            }
        }
    
        /*
         *   根据版本号，判断是否有更新，并写入返回信息
         */
        if ($this->_latest_version <= $this->_client_ver) {
            $this->_return_array['status'] = 0;
        } else {
            $this->_return_array['status'] = 1;
            $this->_return_array['ver'] = $this->_latest_info['ver'];
            $this->_return_array['vtype'] = $this->_latest_info['vtype'];
            $this->_return_array['dlink'] = $this->_latest_info['dlink'];
            $this->_return_array['token'] = $this->_latest_info['token'];
            $this->_return_array['filesize'] = $this->_latest_info['filesize'];
        }
    }
    
    /**
     * 获取最新版本三维词库的二进制流文件
     */
    private function getLatestPkg() {
        /*
         * 最新版三维词库的本地下载地址
         */
        $latest_version_dlink_local = $this->_latest_info['dlink_local'];
    
        header("HTTP/1.1 302 Found");
        header("status: 302 Found");
        header("Location: " . $latest_version_dlink_local);
        exit();
    }
    
    /**
     * 三维词库更新检测接口，返回最新版本三维词库的二进制流文件
     * @route({"GET", "/tdo/"})
     * @param({"vtype", "$._GET.vtype"}) string $vtype
     * @param({"ver", "$._GET.ver"}) string $ver 版本号
     * @return ({"body"})
     */
    public function tdo($vtype, $ver) {
        $this->getLatestVersion($vtype, $ver);
        $this->getLatestPkg();
        exit();
    }
}