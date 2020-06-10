<?php
/**
 *
 * @desc 通知中心启动屏更新消息（android_splash）业务接口
 * @path("/android_splash_noti/")
 *
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class AndroidSplashNoti
{
    /** 基类对象 */
    private $objBase;

    /** 输出数组格式 */
    private $out = array();

    /** @property 通知中心请求资源缓存key pre*/
    private $strAndroidSplashNotiCachePre;

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
     * @desc 启动屏更新消息（android_splash）
     * @route({"GET", "/info"})
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function getAndroidSplash($notiVersion = 0, $intMsgVer = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();

        $data = NotiSplash::getNoti($this->objBase, $redis, $this->strAndroidSplashNotiCachePre, $this->intCacheExpired, $intMsgVer);

        $this->out['data'] = $this->objBase->checkArray($data);

        $this->out['version'] = $this->objBase->getNewVersion();

        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        //md5
        $this->out['md5'] = md5($this->out['data']);

        return Util::returnValue($this->out,false);
    }
}
