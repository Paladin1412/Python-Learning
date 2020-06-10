<?php
/**
 *
 * @desc 通知中心业务接口--分类词库
 * @path("/ad_cell_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class AdCellNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strAdCellNotiCachePre;
    
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
    * @desc 分类词库
    * @route({"POST", "/info"})
    * @param({"strWordlibInfo", "$._POST.wordlib_info"}) $wordLibInfo wordlib_info客户端POST词库JSON数据
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"intSp", "$._GET.sp"}) $intSp sp 联网类型
    * @param({"strFrom", "$._GET.from"}) $strFrom 初始渠道号,不需要客户端传
    * @param({"strRom", "$._GET.rom"}) $strRom rom,不需要客户端传
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
    public function getAdCell($strWordlibInfo, $strPlatform, $intSp = 12, $strFrom = '', $strRom = '')
    {
        //decode post data
        $decodedWordlibInfo = json_decode($strWordlibInfo,true);
        $formatedWordlibInfo = $this->objBase->formatWordlibInfo($decodedWordlibInfo);
        
        //set hotword id with static param
        $intHotwordsId = NotiBase::HOT_WORDS_ID;
        
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
            
            //the result is hotwords or syswords or etc.
            if($wordLibId <= $intHotwordsId || $wordLibId >= $intSyswordsId) {
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

        $strPhoneOs = Util::getPhoneOS($strPlatform);

        $strNetWork = (intval($intSp) === NotiBase::WIFI_FLAG_OF_SP)?'wifi':'gprs';
        
        $data = array();
        $version = 0;
        foreach($formatedWordlibInfo as $strWordLibKey => $strWordLibValue) {
            //set each message version
            $strNotiVersion = intval($strWordLibValue['msgver']);
            $intWordslibId = str_replace('w', '', $strWordLibKey);
            
            $getNoti = NotiAdCell::getNoti($this->objBase, $redis, $this->strAdCellNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $strNotiVersion, $intWordslibId, $strWordLibValue, $strPhoneOs, $strNetWork);
            
            if(isset($getNoti['info']) && !empty($getNoti['info'])) {
                $data[$strWordLibKey] = $getNoti['info'];
                
                //set max version
                $version = max($version, $data[$strWordLibKey]['version']);
            }
        }

        $this->out['data'] = $this->objBase->checkArray($data);

        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        //version
        $this->out['version'] = $version;
        
        //server time
        $this->out['server_time'] = Util::getCurrentTime();

        return Util::returnValue($this->out,false);
    }
}
