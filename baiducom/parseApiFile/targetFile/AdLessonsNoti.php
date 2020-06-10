<?php
/**
 *
 * @desc 通知中心业务接口--教程
 * @path("/ad_lessons_noti/")
 */
 
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class AdLessonsNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdLessonsNotiCachePre;
    
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
    * @desc 教程通知
    * @route({"GET", "/info"})
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
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
    public function getAdlessons($strVersion, $notiVersion = 0, $intMsgVer = 0, $strPlatform = '')
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $strVersionName = Util::formatVer($strVersion);
        
        $data = NotiLesson::getNoti($this->objBase, $redis, $this->strAdLessonsNotiCachePre, $this->intCacheExpired, $strPlatform, $strVersionName, $notiVersion, $intMsgVer);
        
        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
