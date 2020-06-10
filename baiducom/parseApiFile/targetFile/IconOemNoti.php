<?php
/**
 *
 * @desc 通知中心icon oem业务接口
 * @path("/icon_oem_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class IconOemNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strIconOemNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /***
    * 构造函数
    * @return void
    */
    public function  __construct() {
        $this->objBase = new NotiBase();
        
        //输出格式初始化
        $this->out = Util::initialClass(false);
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
    public function getIconOemlist($intNotiVersion = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $intNotiVersion = intval($intNotiVersion);
        
        $data = NotiIconOem::getNoti($this->objBase, $redis, $this->strIconOemNotiCachePre, $this->intCacheExpired, $intNotiVersion, $intNotiVersion);
        
        if(isset($data['icon'])) {
            $this->out['data'] = $this->objBase->checkArray($data['icon']);
        }
        
        //version in data array
        $this->out['version'] = intval($data['version']);
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
