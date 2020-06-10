<?php
/**
 *
 * @desc 通知中心场景化更新消息（scene）业务接口
 * @path("/scene_noti/")
 *
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class SceneNoti
{
    /** 基类对象 */
    private $objBase;

    /** 输出数组格式 */
    private $out = array();

    /** @property 通知中心请求资源缓存key pre*/
    private $strSceneNotiCachePre;

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
     * @desc 场景化通知scene
     * @route({"GET", "/info"})
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function getScene($notiVersion = 0, $intMsgVer = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();

        $data = NotiScene::getNoti($this->objBase, $redis, $this->strAdSceneNotiCachePre, $this->intCacheExpired, $intMsgVer);

        $this->out['data'] = $this->objBase->checkArray($data);

        $this->out['version'] = $this->objBase->getNewVersion();

        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();

        return Util::returnValue($this->out,false,true);
    }
}
