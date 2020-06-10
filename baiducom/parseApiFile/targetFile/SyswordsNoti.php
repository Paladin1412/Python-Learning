<?php
/**
 *
 * @desc 通知中心业务接口--系统词库
 * @path("/syswords_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class SyswordsNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strSyswordsNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /**
    * 构造函数
    * @return void
    */
    public function  __construct() {
        $this->objBase = new NotiBase();

        //输出格式初始化
        $this->out = Util::initialClass(false);
    }
    
    /**
    * @desc 系统词库通知
    * @route({"POST", "/info"})
    *
    * @param({"strWordlibInfo","$._POST.wordlib_info"})
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"intSp", "$._GET.sp"}) $intSp 联网类型
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
    public function getSysWords($strWordlibInfo = "", $strPlatform = "", $intSp = 0)
    {
        //decode post data
        $decodedWordlibInfo = json_decode($strWordlibInfo,true);
        $formatedWordlibInfo = $this->objBase->formatWordlibInfo($decodedWordlibInfo);
        
        //set system id with static param
        $intSyswordsId = NotiBase::SYS_WORDS_MIN_ID;

        //could not get resource from json
        if(empty($formatedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'The post data of json format cannot be decoded!',true);
        }

        //find result in array or delete it
        foreach($formatedWordlibInfo as $strWordLibKey => $strWordLibValue) {
            //get word lib id
            $wordLibId = intval(trim(str_replace('w', '', $strWordLibKey)));
            
            //the result is syswords
            if($wordLibId <= $intSyswordsId) {
                unset($formatedWordlibInfo[$strWordLibKey]);
            }
        }
        
        //could not find result in array
        if(empty($formatedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'No post data or post data name is wrong!',true);
        }

        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];
        $strMicHttpRoot = $conf['properties']['strMicHttpRoot'];

        $strPhoneOs = Util::getPhoneOS($strPlatform);

        $strNetWork = (intval($intSp) === NotiBase::WIFI_FLAG_OF_SP)?'wifi':'gprs';
        
        $this->out['version'] = array();
        $data = array();
        foreach($formatedWordlibInfo as $strWordLibKey => $strWordLibValue) {
            //set each message version
            $strNotiVersion = intval($strWordLibValue['msgver']);
            $intWordslibId = str_replace('w', '', $strWordLibKey);
            
            $getNoti = NotiWsys::getNoti($this->objBase, $redis, $this->strSyswordsNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $strMicHttpRoot, $strNotiVersion, $intWordslibId, $strWordLibValue, $strPhoneOs, $strNetWork);
            
            if(!empty($getNoti)) {
                $data[$strWordLibKey] = $getNoti;
                
                $this->out['version'][$strWordLibKey] = NotiWsys::getVersion();
            }
        }
        
        $this->out['data'] = $this->objBase->checkArray($data);

        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        //server time
        $this->out['server_time'] = Util::getCurrentTime();
        
        return Util::returnValue($this->out,false);
    }
}
