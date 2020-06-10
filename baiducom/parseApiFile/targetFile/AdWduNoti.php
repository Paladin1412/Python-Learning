<?php
/**
 *
 * @desc 通知中心三维词库广告ad_wdu业务接口
 * @path("/ad_wdu_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class AdWduNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdWduNotiCachePre;
    
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
    * @desc 三维词库ad_wdu通知
    * @route({"POST", "/info"})
    * @param({"strWordlibInfo", "$._POST.wordlib_info"}) $wordLibInfo wordlib_info客户端POST词库JSON数据
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer（此接口中用不到）
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"intSp", "$._GET.sp"}) $intSp sp 联网类型
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
    public function getAdWdu($strWordlibInfo, $notiVersion = 0, $intMsgVer = 0, $strPlatform = '', $intSp = 12)
    {
        //decode post data
        $decodedWordlibInfo = json_decode($strWordlibInfo,true);
        $formatedWordlibInfo = $this->objBase->formatWordlibInfo($decodedWordlibInfo);
        
        //could not get resource from json
        if(empty($formatedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'The post data of json format cannot be decoded!',true);
        }

        if(!isset($formatedWordlibInfo['wdu'])) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'wdu not set',true);
        }

        $strWduInfo = $formatedWordlibInfo['wdu'];

        //get redis obj
        $redis = GFunc::getCacheInstance();

        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];

        $data = NotiWdu::getNoti($this->objBase , $redis, $this->strAdWduNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $notiVersion, $strWduInfo, Util::getPhoneOS($strPlatform), $intSp);

        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        //server time
        $this->out['server_time'] = Util::getCurrentTime();
        
        return Util::returnValue($this->out,false);
    }
}
