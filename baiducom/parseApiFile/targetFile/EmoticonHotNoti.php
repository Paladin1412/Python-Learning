<?php
/**
 *
 * @desc 通知中心最热颜文字业务接口
 * @path("/emoticon_hot_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class EmoticonHotNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strEmoticonHotNotiCachePre;
    
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
    * @desc 最热颜文字列表数据
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
    public function getEmoticonHotlist($intNotiVersion = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $intNotiVersion = intval($intNotiVersion);
        
        $intCurrentTime = Util::getCurrentTime();
        
        $data = array();
        NotiEmotionHot::getNoti($this->objBase, $redis, $this->strEmoticonHotNotiCachePre, $this->intCacheExpired, $intNotiVersion, $intCurrentTime, $intNotiVersion, 0, $data);
        
        if(isset($data['emoticon_hot']['url'])) {
            $this->out['data'] = $data['emoticon_hot']['url'];
        } else {
            $this->out['data'] = '';
        }
        
        //version
        $this->out['version'] = intval($data['emoticon_hot']['ver']);
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
