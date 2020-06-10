<?php
/**
 *
 * @desc 通知中心业务接口--统计控制客户端上传消息
 * @path("/report_noti/")
 */
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class ReportNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strReportNotiCachePre;
    
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
    * @desc 统计控制客户端上传消息
    * @route({"GET", "/info"})
    * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
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
    public function getReport($notiVersion = 0)
    {
        //get redis obj
        $redis = GFunc::getCacheInstance();
        
        $data =  NotiReport::getNoti($this->objBase, $redis, $this->strReportNotiCachePre, $this->intCacheExpired);
        
        $this->out['data'] = $this->objBase->checkArray($data);
        
        $this->out['version'] = NotiReport::getDataVersion();
        
        //code
        //$this->out['ecode'] = $this->objBase->getStatusCode();
        //msg
        //$this->out['emsg'] = $this->objBase->getErrorMsg();
        
        return Util::returnValue($this->out,false);
    }
}
