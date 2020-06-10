<?php
/**
 *
 * @desc 通知中心颜文字业务接口
 * @path("/emoticon_noti/")
 */

use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;


class EmoticonNoti
{
    //默认每页显示条数
    const GENERAL_PAGE_SIZE = 12;
    
    /** 输出数组格式 */
    private $out = array();
    
    /***
    * 构造函数
    * @return void
    */
    public function  __construct() {
        //输出格式初始化
        $this->out = Util::initialClass(false);
    }
    
    /**
     * @desc emoticon 颜文字通知(包括最热tab)
     * @route({"GET", "/info"})
     * @param({"cate", "$._GET.cate"}) int $cate 某自然分类 (newest,hottest,buildin(内置),virtab,buildhot(最热tab))
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "ecode": 0,
            "emsg": 'success',
            "data":{
                "title": "最新",
                "list": [
                    {
                        "id": "21",
                        "name": "熊咚咚2",
                        "type": "normal",
                        "os": "all",
                        "short_name": "",
                        "version_code": "0",
                        "author": "",
                        "url": "",
                        "ios_file_size": "",
                        "android_file_size": "",
                        "zip_size": "",
                        "pub_item_type": "",
                        "desc": "平时呆萌，偶尔贱萌，最爱戴墨镜装大哥范儿，每天都会握拳打鸡血的励志熊。",
                        "downloads": "100",
                        "thumb": "",
                        "pic_1": "",
                        "pic_2": "",
                        "pic_3": ""
                    }
                ]
            }
            "version": 1441700356
        }
    */
    public function marketList($cate = '', $plt = 'a1', $ver_name = '6.0.0.0', $sf = 0, $num = self::GENERAL_PAGE_SIZE) {
        $this->out['version'] = 0;
        
        $obj = IoCload('Emoticon');
        
        $data = $obj->catlist($cate, $plt, $ver_name, $sf, $num);
        
        if(is_array($data['list']) && !empty($data['list'])) {
            $this->out['data'] = $data;
        }
        
        if(isset($data['ver'])) {
            $this->out['version'] = intval($data['ver']);
            
            //delete version in old list
            unset($this->out['data']['ver']);
        }
        
        return Util::returnValue($this->out,false);
    }
}
