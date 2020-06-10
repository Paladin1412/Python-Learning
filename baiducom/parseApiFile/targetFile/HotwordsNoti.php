<?php
/**
 *
 * @desc 通知中心业务接口--热词
 * @path("/hotwords_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorMsg;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class HotwordsNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strHotwordNotiCachePre;
    
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
    * @desc 热词
    * @route({"POST", "/info"})
    * @param({"strWordlibInfo", "$._POST.wordlib_info"}) $wordLibInfo wordlib_info客户端POST词库JSON数据
    * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
    * @param({"strNotiswitchVersion", "$._GET.notiswitch_version"}) $notiswitch_version notiswitch_version,本地开关版本号
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
    public function getHotword($strWordlibInfo, $strVersion, $strPlatform, $strNotiswitchVersion = 0, $intSp = 12, $strFrom = '', $strRom = '')
    {
        //decode post data
        $decodedWordlibInfo = json_decode($strWordlibInfo,true);
        $formatedWordlibInfo = $this->objBase->formatWordlibInfo($decodedWordlibInfo);
        
        //set hotword id with static param
        $hotwordsId = NotiBase::HOT_WORDS_ID;
        $wordlibKey = 'w' . $hotwordsId;

        //could not get resource from json
        if(empty($formatedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'The post data of json format cannot be decoded!',true);
        }

        //could not find result with hotword
        if(!isset($formatedWordlibInfo[$wordlibKey])) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'No post data or post data name is wrong!',true);
        }
        
        $arrHotwordInfo = $formatedWordlibInfo[$wordlibKey];
        $strNotiVersion = intval($arrHotwordInfo['msgver']);

        //get redis obj
        $redis = GFunc::getCacheInstance();

        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];

        $strVersionName = Util::formatVer($strVersion);

        $strPhoneOs = Util::getPhoneOS($strPlatform);

        $strNetWork = (intval($intSp) === NotiBase::WIFI_FLAG_OF_SP)?'wifi':'gprs';

        //noti switch data
        $arrNotiswitch = $this->objBase->getNotiSwitch($strVersionName, $strPlatform, $strNotiswitchVersion, $strFrom, $strRom);
        $intHotwordShowLevel = intval($arrNotiswitch['hotword_showlevel']);

        $data = NotiHotwords::getNoti($this->objBase, $redis, $this->strHotwordNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $strNotiVersion, $hotwordsId, $arrHotwordInfo, $strPhoneOs, $strNetWork, $intHotwordShowLevel);

        $this->out['data'] = $this->objBase->checkArray($data);

        $this->out['version'] = NotiHotwords::getVersion();

        //code
        $this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        $this->out['emsg'] = $this->objBase->getErrorMsg();
        
        //server time
        $this->out['server_time'] = Util::getCurrentTime();

        return Util::returnValue($this->out,false);
    }
    
    /**
    * @desc 热词检测升级接口
    * @route({"GET", "/check_update"})
    * @param({"ver", "$._GET.ver"}) int 词库版本号
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version, 通知中心版本号
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
    public function checkHotwordUpdate($ver = 0, $notiVersion = 0)
    {
        $intClientVer = intval($ver);
        
        $strCacheKey = $this->strHotwordNotiCachePre . 'check_update';
        $data = GFunc::cacheGet($strCacheKey);
        if($data === false) {
            $data = array();
            
            //set hotword id with static param
            $hotwordsId = NotiBase::HOT_WORDS_ID;
            $wordlibKey = 'w' . $hotwordsId;
            
            $wordlibModel = IoCload("models\\WordlibModel");
            $arrWordlibVersion = $wordlibModel->getWordlibVersion(array($wordlibKey => 0));
            $intServerVer = intval($arrWordlibVersion[$wordlibKey]['wordlib_ver']);
            
            $data['version'] = intval($arrWordlibVersion['version']);
            
            $hotWordsModel = IoCload('models\\HotWordsModel');
            //always display summary with max version
            $arrWordsSummary = $hotWordsModel->getSummary(0, $intServerVer);
            
            if(isset($arrWordsSummary['text'])){
                $data['lastversion'] = $intServerVer;
                $data['summary'] = $arrWordsSummary['text'] . '...';
            }
            
            if(GFunc::cacheSet($strCacheKey, $data, $this->intCacheExpired) === false) {
                //ecode
                $this->out['ecode'] = ErrorCode::REDIS_ERROR;
                //msg
                $this->out['emsg'] = ErrorMsg::getMsg($this->out['ecode']);
            }
        }
        
        $this->out['data'] = array(
            'lastversion' => $data['lastversion'],
            'summary' => $data['summary'],
        );
        
        $this->out['version'] = intval($data['version']);

        return Util::returnValue($this->out,false);
    }
}
