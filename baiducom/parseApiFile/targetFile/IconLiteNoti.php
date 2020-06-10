<?php
/**
 *
 * @desc 通知中心手助lite的icon业务接口
 * @path("/icon_lite_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class IconLiteNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strIconLiteNotiCachePre;
    
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
    * @desc lite的icon列表数据
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
    public function getIconLitelist($intNotiVersion = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $intNotiVersion = intval($intNotiVersion);
        
        $data = NotiIconLite::getNoti($this->objBase, $redis, $this->strIconLiteNotiCachePre, $this->intCacheExpired, $intNotiVersion, $intNotiVersion);
        
        if(isset($data['icon'])) {
            $this->out['data'] = $this->objBase->checkArray($data['icon']);
        }
        
        //version
        $this->out['version'] = intval($data['version']);
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
