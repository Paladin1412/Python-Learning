<?php
/**
 *
 * @desc 通知中心ad_emoji业务接口
 * @path("/ad_emoji_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class AdEmojiNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdEmojiNotiCachePre;
    
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
    * @desc emoji通知
    * @route({"GET", "/info"})
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
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
    public function getAdEmoji($notiVersion = 0, $intMsgVer = 0, $strPlatform = '', $strVersion = '')
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];
        
        $data = NotiEmoji::getNoti($this->objBase, $redis, $this->strAdEmojiNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $notiVersion, $intMsgVer, Util::getPhoneOS($strPlatform), $strVersion);
        
        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
