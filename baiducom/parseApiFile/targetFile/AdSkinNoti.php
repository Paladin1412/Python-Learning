<?php
/**
 *
 * @desc 通知中心业务接口--皮肤主题
 * @path("/ad_skin_noti/")
 */
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class AdSkinNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdSkinNotiCachePre;
    
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
    * @desc 皮肤主题通知
    * @route({"GET", "/info"})
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"bolForeignAccess", "$._GET.foreign_access"}) 是否来自国外访问, 如果不指定, 自动根据ip判断
    * @param({"strScreenW", "$._GET.screen_w"}) $strScreenW 屏幕宽,不需要客户端传
    * @param({"strScreenH", "$._GET.screen_h"}) $strScreenH 屏幕高,不需要客户端传
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
    public function getAdSkin($strVersion, $notiVersion = 0, $intMsgVer = 0, $strPlatform = '', $bolForeignAccess = false, $strScreenW = 640, $strScreenH = 320)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];
        
        $data = NotiSkin::getNoti($this->objBase, $redis, $this->strAdSkinNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $notiVersion, $intMsgVer, $strPlatform, $strVersion, $strScreenW, $strScreenH, $bolForeignAccess);
        
        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
