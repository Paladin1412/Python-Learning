<?php
/**
 *
 * @desc 通知中心运营活动
 * @path("/activity_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

class ActivityNoti
{
    
    /***
    * 构造函数
    * @return void
    */
    public function  __construct() {
        //输出格式初始化
        $this->out = Util::initialClass();
    }
    
    /**
    * @desc 运营活动列表数据
    * @route({"GET", "/info"})
    * @param({"intNotiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
    * @param({"token", "$._GET.token"}) string $plt 平台号，不需要客户端传，从加密参数中获取
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
    public function getActivitylist($intNotiVersion = 0, $token = '')
    {
        $objActivity = IoCload("Activity");
        
        $this->out['data'] = $objActivity->getlist($token);
        
        $arrVersion = array(
            'activities' => 0,
            'skin_activities' => 0,
        );
        
        //get all content from dbx result
        if(is_array($objActivity->arrActivityFromDbx) && !empty($objActivity->arrActivityFromDbx)) {
            foreach($objActivity->arrActivityFromDbx as $key => $val) {
                //get lastest mdate
                if(isset($val['mdate']) && $arrVersion['activities'] < intval($val['mdate'])) {
                    $arrVersion['activities'] = intval($val['mdate']);
                }
            }
        }
        
        $this->out['version'] = max($arrVersion);
        
        return Util::returnValue($this->out,true,true);
    }
}
