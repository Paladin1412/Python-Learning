<?php
/**
 *
 * @desc 通知中心tab oem业务接口
 * @path("/tab_oem_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class TabOemNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strTabOemNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /***
    * 构造函数
    * @return void
    */
    public function  __construct() {
        $this->objBase = new NotiBase();
        
        //输出格式初始化
        $this->out = Util::initialClass();
    }
    
    /**
    * @desc tab oem列表数据
    * @route({"GET", "/info"})
    * @param({"intNotiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": 1,
            "version": 123
            ]
        }
    */
    public function getTabOemlist($intNotiVersion = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $intNotiVersion = intval($intNotiVersion);
        
        $conf = GFunc::getConf('Noti');
        $strMicHttpRoot = $conf['properties']['strMicHttpRoot'];
        
        $data = NotiTabOem::getNoti($this->objBase, $redis, $this->strTabOemNotiCachePre, $this->intCacheExpired, $intNotiVersion, $strMicHttpRoot, $intNotiVersion);
        
        if(isset($data['tab'])) {
            $this->out['data']->tab = $this->objBase->checkArray($data['tab']);
        }
        
        if(isset($data['global_ids'])) {
            $this->out['data']->global_ids = $this->objBase->checkArray($data['global_ids']);
        }
        
        //version in data array
        if(isset($data['version'])) {
            $this->out['version'] = intval($data['version']);
        }
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out);
    }
}
