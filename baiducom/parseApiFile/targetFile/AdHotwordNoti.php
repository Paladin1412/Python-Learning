<?php
/**
 *
 * @desc 通知中心ad_hotword业务接口
 * @path("/ad_hotword_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;

ClassLoader::addInclude(__DIR__.'/noti');

class AdHotwordNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdHotwordNotiCachePre;
    
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
    * @desc ad_hotword通知
    * @route({"GET", "/info"})
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
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
    public function getAdHotword($notiVersion = 0, $intMsgVer = 0)
    {
        $data = $this->objBase->getNotiInfoFromRes('ad_hot_word', $notiVersion, null, $intMsgVer);
        
        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
