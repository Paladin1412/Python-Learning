<?php
/**
 *
 * @desc 通知中心ad_plugin业务接口
 * @path("/ad_plugin_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class AdPluginNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdPluginNotiCachePre;
    
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
    * @desc ad_plugin通知
    * @route({"GET", "/info"})
    * @param({"intMemory", "$._GET.memory"}) $intMemory memory，手机内存信息
    * @param({"strCpuInfo", "$._GET.cpuinfo"}) $strCpuInfo cpuinfo，手机cpu信息
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"intMsgVer", "$._GET.noti_id"}) $intMsgVer noti_id, 上一次请求intMsgVer
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": [],
            "version": 123
        }
    */
    public function getAdPlugin($intMemory, $strCpuInfo, $notiVersion = 0, $intMsgVer = 0, $strPlatform = '', $strVersion = '')
    {
        //处理memory或者cpu信息未上传的异常情况
        if(!isset($intMemory) || !isset($strCpuInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'memory info and cpu info is required!',true);
        }

        //get redis obj
        $redis = GFunc::getCacheInstance();

        $data = NotiPlugin::getNoti($this->objBase, $redis, $this->strAdPluginNotiCachePre, $this->intCacheExpired, Util::getPhoneOS($strPlatform), $strVersion, $notiVersion, $intMsgVer, $intMemory, $strCpuInfo);
        
        $this->out['data'] = $this->objBase->checkArray($data['info']);
        
        $this->out['version'] = $this->objBase->getNewVersion();
        
        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
