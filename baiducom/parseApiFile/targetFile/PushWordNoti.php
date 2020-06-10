<?php
/**
 *
 * @desc 通知中心业务接口--词库推送pushword
 * @path("/pushword_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class PushWordNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strPushwordNotiCachePre;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /**
     * 构造函数
     * @return void
     */
    public function  __construct() {
        $this->objBase = new NotiBase();
        
        //输出格式初始化
        $this->out = Util::initialClass();
    }
    
    /**
    * @desc pushword
    * @route({"POST", "/info"})
    * @param({"strWordlibInfo", "$._POST.wordlib_info"}) $wordLibInfo wordlib_info客户端POST词库JSON数据
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
    public function getPushword($strWordlibInfo)
    {
        //decode post data
        $decodedWordlibInfo = json_decode($strWordlibInfo,true);
        $formatedWordlibInfo = $this->objBase->formatWordlibInfo($decodedWordlibInfo);

        //could not get resource from json
        /*if(empty($formatedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'The post data of json format cannot be decoded!',true);
        }*/

        //get redis obj
        $redis = GFunc::getCacheInstance();

        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];

        $data = NotiPushWord::getNoti($this->objBase, $redis, $this->strPushwordNotiCachePre, $this->intCacheExpired, $strV4HttpRoot, $formatedWordlibInfo, $this->strPushWordConfResRoot);

        $this->out['data'] = $this->objBase->checkArray($data);

        $this->out['version'] = NotiPushWord::getVersion();

        //code
        $this->out['ecode'] = NotiPushWord::getStatusCode();
        //msg
        $this->out['emsg'] = NotiPushWord::getErrorMsg();

        return Util::returnValue($this->out);
    }
}
