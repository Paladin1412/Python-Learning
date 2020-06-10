<?php

/**
 *
 * @desc vivo业务接口
 * @path("/vivo_noti/")
 */
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\GFunc;

ClassLoader::addInclude(__DIR__ . '/noti');

class VivoNoti {

    /** @property 通知中心请求资源缓存key pre */
    private $strVivoNotiCachePre;

    /**
     * @desc vivo数据收集白名单列表数据
     * @route({"GET", "/info"})
     * @param({"intNotiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) {
         "ecode": 1,
         "emsg": "",
         "data": [
             {
                 "name": "test"
             }
         ],
         "version": 123,
         "md5": "sdfsdfsdfsdf",
      }
     */
    public function getVivoDataCollectlist($intNotiVersion = 0) {
        $strProtoName = 'vivo_data_collect';
        $out = GFunc::getNotiReturn($strProtoName, $this->strVivoNotiCachePre, Gfunc::getCacheTime('2hours'));

        $arr = array();
        //整理数据
        if (!empty($out['data'])) {
            foreach ($out['data'] as $val) {
                if(isset($val['info']) && is_array($val['info']) && !empty($val['info'])) {
                    foreach($val['info'] as $info) {
                        $arrCtrid = array();
                        
                        if(isset($info['ctrid']) && !empty($info['ctrid'])) {
                            foreach($info['ctrid'] as $ctrid) {
                                if(isset($ctrid['ctrid'])) {
                                    $arrCtrid[] = $ctrid['ctrid'];
                                }
                            }
                        }

                        //未初始化该包名
                        if(!isset($arr[$info['package_name']])) {
                            $arr[$info['package_name']] = $arrCtrid;
                        } else {
                            $arr[$info['package_name']] = array_merge($arr[$info['package_name']],$arrCtrid);
                        }
                    }
                }
            }
        }
        
        $out['data'] = array();
        //去重
        if (!empty($arr)) {
            foreach ($arr as $key => $val) {
                $arrData = array_unique($val); //数组去重
                $out['data'][] = array(
                    'package_name' => $key,
                    'ctrid' => array_values($arrData), //php 数组索引值重新从0开始递增
                );
            }
        }

        return Util::returnValue($out, false, true);
    }

}
