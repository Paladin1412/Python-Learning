<?php
/**
 *
 * @desc 通知中心业务接口--APP推荐消息
 * @path("/ad_recommends_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class AdRecommendsNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdRecommendsNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /** wifi网络对应的sp代号 */
    const WIFI_FLAG_OF_SP = 2;
    
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
    * @desc APP推荐消息
    * @route({"GET", "/info"})
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
    * @param({"strCfrom", "$._GET.cfrom"}) $strCfrom cfrom当前渠道号,不需要客户端传
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"strScreenW", "$._GET.screen_w"}) $strScreenW 屏幕宽,不需要客户端传
    * @param({"strScreenH", "$._GET.screen_h"}) $strScreenH 屏幕高,不需要客户端传
    * @param({"intSp", "$._GET.sp"}) $intSp sp 联网类型
    * @param({"activeTime", "$._GET.active_time"}) $activeTime active_time 安卓时间参数(?)
    * @param({"channel", "$._GET.channel"}) $channel channel 安卓覆盖渠道号(?)
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
    public function getAdRecommends($strVersion, $strCfrom, $notiVersion = 0, $intMsgVer = 0, $strPlatform = '', $strScreenW = 640, $strScreenH = 320, $intSp = 12, $activeTime = 0, $channel = '')
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $strVersionName = Util::formatVer($strVersion);
        
        //获取渠道号
        if ($channel !== '') {
            $strCfrom = $channel;
        }
		
		$intActiveTime = 0;
		//如果是毫秒, 先转化为秒
		if (strlen($activeTime) >= 13) {
		    $intActiveTime = intval($activeTime) / 1000;
		} else {
		    $intActiveTime = intval($activeTime);
		}
		
		//network
		$strNetWork = (intval($intSp) === self::WIFI_FLAG_OF_SP)?'wifi':'gprs';
        
        $data = NotiAppRecommend::getNoti($this->objBase, $redis, $this->strAdRecommendsNotiCachePre, $this->intCacheExpired, $strPlatform, $strVersionName, $strCfrom, $strNetWork, $strScreenW, $strScreenH, $notiVersion, $intMsgVer, $intActiveTime);
        
        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
