<?php
/**
 *
 * @desc 通知中心剪贴板功能黑名单业务接口
 * @path("/clipboard_blacklist_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class ClipboardBlacklistNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strClipboardBlacklistNotiCachePre;
    
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
    * @desc 剪贴板功能黑名单通知
    * @route({"GET", "/info"})
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
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
    public function getClipboardBlacklist($notiVersion = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $notiVersion = intval($notiVersion);
        
        $strClipboardBlacklistRoot = '/res/json/input/r/online/clipboard_blacklist/';
        
        $data = NotiClipboardBlackList::getNoti($this->objBase, $redis, $this->strClipboardBlacklistCachePre, $this->intCacheExpired, $strClipboardBlacklistRoot, $notiVersion);
        
        if(isset($data['black_list'])) {
            $this->out['data'] = $this->objBase->checkArray($data['black_list']);
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
