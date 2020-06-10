<?php
/**
 *
 * @desc 通知中心三维词库广告wdu业务接口
 * @path("/wdu_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;


class WduNoti
{
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strWduNotiCachePre;
    
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
    * @desc 三维词库检测升级接口
    * @route({"GET", "/check_update"})
    * @param({"vtype", "$._GET.vtype"}) string $vtype
    * @param({"ver", "$._GET.ver"}) string $ver 版本号
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
    public function checkWduUpdate($vtype = 1, $ver = 0, $notiVersion = 0)
    {
        $baseDbxModel = IoCload("models\\BaseDbxModel");
        
        $notiVersion = intval($notiVersion);
        
        if($notiVersion === -1) {
            $notiVersion = 0;
        }
        
        $vtype = intval($vtype);
        
        //Edit by fanwenli on 2018-01-08, check ver whether user has been installed
        if(intval($ver) < 0) {
            $install = 0;
        } else {
            $install = 2;
        }
        
        $strCacheKey = $this->strWduNotiCachePre . 'check_update_' . $vtype . '_' . $install;
        $data = GFunc::cacheGet($strCacheKey);
        if($data === false) {
            $objThreedwordModel = IoCload('models\\ThreeDWordModel');
            //Edit by fanwenli on 2018-01-08, check ver whether user has been installed
            $data = $objThreedwordModel->getLatestInfo($vtype, $install);
            
            GFunc::cacheSet($strCacheKey, $data, $this->intCacheExpired);
        }
        
        //could not connect db
        if(isset($data['isdberror']) && $data['isdberror'] == 1) {
            $this->out['ecode'] = ErrorCode::DB_ERROR;
            
            //set error code
            $baseDbxModel->setErrorMsg($this->out['ecode']);
            //msg
            $this->out['emsg'] = $baseDbxModel->getErrorMsg();
            //set data
            $data = array();
        }
        
        $this->out['version'] = intval($data['version']);
        
        if(isset($data['ver']) && intval($data['ver']) > intval($ver)) {
            unset($data['version']);
            $this->out['data'] = $data;
        }

        return Util::returnValue($this->out,false);
    }
}
