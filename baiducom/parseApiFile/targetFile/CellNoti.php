<?php
/**
 *
 * @desc 通知中心业务接口--分类词库
 * @path("/cell_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class CellNoti
{
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strCellNotiCachePre;
    
    /** @property 内部缓存实例(KsarchRedis缓存) */
    private $cache;
    
    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /***
    * 构造函数
    * @return void
    */
    public function  __construct() {
        //输出格式初始化
        $this->out = Util::initialClass(false);
    }
    
    /**
    * @desc 分类词库检测升级接口
    * @route({"POST", "/check_update"})
    * @param({"strWordlibInfo", "$._POST.wordlib_info"}) $wordLibInfo wordlib_info客户端POST词库JSON数据
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
    public function checkCellUpdate($strWordlibInfo, $notiVersion = 0)
    {
        //decode post data
        $decodedWordlibInfo = json_decode($strWordlibInfo,true);
        
        //post array sort
        ksort($decodedWordlibInfo);
        
        //set hotword id with static param
        $intHotwordsId = NotiBase::HOT_WORDS_ID;
        
        //set system id with static param
        $intSyswordsId = NotiBase::SYS_WORDS_MIN_ID;
        
        //could not get resource from json
        if(empty($decodedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'The post data of json format cannot be decoded!',true);
        }

        //find result in array or delete it
        foreach($decodedWordlibInfo as $strWordLibKey => $strWordLibValue) {
            //get word lib id
            $wordLibId = intval(trim(str_replace('w', '', $strWordLibKey)));
            
            //the result is hotwords or syswords or etc.
            if($wordLibId <= $intHotwordsId || $wordLibId >= $intSyswordsId) {
                unset($decodedWordlibInfo[$strWordLibKey]);
            }
        }
        
        //could not find result in array
        if(empty($decodedWordlibInfo)) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'No post data or post data name is wrong!',true);
        }
        
        //redis cache key
        $cacheKey = $this->strCellNotiCachePre . 'check_update_';
        //version
        $cacheVersionKey = $cacheKey . 'version';
        if(is_array($decodedWordlibInfo) && !empty($decodedWordlibInfo)) {
            //set all post key in array and sort
            $arrClientPostKey = array_keys($decodedWordlibInfo);
            
            foreach($arrClientPostKey as $k => $v) {
                $arrClientPostKey[$k] = $cacheKey . $arrClientPostKey[$k];
            }
            
            //set version
            $arrClientPostKey[] = $cacheVersionKey;
        }
        
        $arrDataCache = $this->cache->multiget($arrClientPostKey);
        
        //check for cache after set but cache time is up
        if($arrDataCache !== null && isset($arrDataCache['ret'])) {
            if(isset($arrDataCache['ret']) && !empty($arrDataCache['ret'])) {
                foreach($arrDataCache['ret'] as $key => $val) {
                    //there has not cache
                    if($val === null) {
                        $arrDataCache = null;
                        break;
                    }
                }
            }
        }
        
        if($arrDataCache === null || $arrDataCache === false) {
            $wordlibModel = IoCload("models\\WordlibModel");
            $data = $wordlibModel->getWordlibVersion($decodedWordlibInfo);
            
            //set each cache
            foreach($decodedWordlibInfo as $key => $val){
                //cache key
                $strCacheKey = $cacheKey . $key;
                
                //wordlib key's version
                $intWordlib = '';
                //find version in data array
                if(isset($data[$key]['wordlib_ver'])) {
                    $intWordlib = intval($data[$key]['wordlib_ver']);
                }
                
                if($this->cache->set($strCacheKey, $intWordlib, $this->intCacheExpired) === false) {
                    //code
                    $this->out['ecode'] = ErrorCode::REDIS_ERROR;
                    //set error code
                    $wordlibModel->setErrorMsg($this->out['ecode']);
                    //msg
                    $this->out['emsg'] = $wordlibModel->getErrorMsg();
                }
            }
            
            if($this->cache->set($cacheVersionKey, intval($data['version']), $this->intCacheExpired) === false) {
                //code
                $this->out['ecode'] = ErrorCode::REDIS_ERROR;
                //set error code
                $wordlibModel->setErrorMsg($this->out['ecode']);
                //msg
                $this->out['emsg'] = $wordlibModel->getErrorMsg();
            }
            
            //code
            $this->out['ecode'] = $wordlibModel->getStatusCode();
            //msg
            $this->out['emsg'] = $wordlibModel->getErrorMsg();
        } else {
            //get redis content
            $data = array(
                'version' => 0,
            );
            
            if(isset($arrDataCache['ret']) && !empty($arrDataCache['ret'])) {
                foreach($arrDataCache['ret'] as $key => $val) {
                    //replace cache key pre
                    $strDataKey = str_replace($cacheKey,'',$key);
                    
                    //set wordlib & all version
                    switch($strDataKey) {
                        case 'version':
                            //total version
                            $data[$strDataKey] = intval($val);
                            break;
                        default:
                            //single version
                            $data[$strDataKey]['wordlib_ver'] = intval($val);
                            break;
                    }
                }
            }
        }
        
        $version = 0;
        //unset useless data
        if(!empty($data)) {
            //set version
            if(isset($data['version'])) {
                $version = intval($data['version']);
                //unset version
                unset($data['version']);
            }
            
            foreach($data as $strWordLibId => $strWordLibVal) {
                //post data have not wordlib version or return data have not
                if(!isset($decodedWordlibInfo[$strWordLibId]['wordlib_ver'])) {
                    unset($data[$strWordLibId]);
                } elseif(!isset($data[$strWordLibId]['wordlib_ver'])) {
                    unset($data[$strWordLibId]);
                } else {
                    $intClientWordLibVersion = intval($decodedWordlibInfo[$strWordLibId]['wordlib_ver']);
                    $intServerWordLibVersion = intval($data[$strWordLibId]['wordlib_ver']);
                    
                    //unset return data when client version is bigger than server version
                    if($intClientWordLibVersion >= $intServerWordLibVersion) {
                        unset($data[$strWordLibId]);
                    }
                }
            }
        }

        $this->out['data'] = $data;
        $this->out['version'] = $version;

        return Util::returnValue($this->out,false);
    }
}
