<?php

use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use models\FilterModel;
use utils\Util;
use utils\GFunc;

/**
 *
 * 完全访问
 * @path("/access/")
 */

class Access
{
    /** @property 黑名单内部缓存key */
    private $video_data_cache_key;
    
    /** @property 内部缓存时长 */
    public $intCacheExpired;
    
    
    /**
     *
     * ios 完全访问是否开启
     * @route({"GET","/isopen"})
     */
    public function isopen()
    {

    }

    /**
     *
     * 统计hotpatch 是否下发成功
     * @route({"GET","/hotpatch"})
     */
    public function hotpatch()
    {

    }


    /**
     *
     * 和图片数据对接增加数据统计接口 只是统计数据 没有逻辑
     * @route({"GET","/image"})
     */
    public function imagelog()
    {
    }

    /**
     *
     * ios 7.6临时开关控制 搜索视频tab是否关闭 ios 在7.6采用轮训方式
     * @route({"GET","/video"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) 
     */
    public function videoisopen()
    {
        $out = array('videoisopen' => 0);

        /*
        $cache_key = $this->video_data_cache_key;

        $CommModel = IoCload('models\\CommModel');

        $switch = $CommModel->getCacheContent($cache_key);
        if($switch == '' || is_null($switch)){
            $switch = $CommModel->getContent('video_is_open');
            $CommModel->setCacheContent($cache_key, $switch, $this->intCacheExpired);
        }

        //过滤数据
        if(!empty($switch)){
            $filterModel = new FilterModel();
            $switch = $filterModel->getFilterByArray($switch);
        }


        if(!empty($switch)){
            foreach($switch as $val) {
                //only get the newest switch
                if(isset($val['switch'])) {
                    $out['videoisopen'] = $val['switch'];
                    break;
                }
            }
        }
        */

        return $out;
    }

    /**
     *
     * 提供给客户端做云输入运行速度测试
     * @route({"GET","/getip"})
     */
    public function getip()
    {
        $data  =array();
        $ip  = self::getClientIP();
        $data["ip"] = $ip;
        return $data;
    }

    /**
     *
     * 提供给客户端做云输入运行速度测试
     * @route({"GET","/ip_fetch"})
     * @return mixed
     */
    public function ipFetch()
    {
        $data  =array();
        $ip  = self::getClientIP();
        $data["ip"] = $ip;
        $ipInformation = Util::getGlbCity();
        $data["service_from_ip"] = \models\IpFetch::fetch($ipInformation);
        $data["service_from_location"] = \models\IpFetch::fetch(array(
            "province"=>$_GET['province'],
            "isp"=>$_GET['operator']
        ));
        return array(
            'code'=>0,
            "msg"=>"",
            "data"=>$data
        );
    }


    /**
     * 获取客户端ip
     * @return string
     */
    public static function getClientIP()
    {
        if(isset($_SERVER ['HTTP_X_FORWARDED_FOR'])
            &&  $_SERVER ['HTTP_X_FORWARDED_FOR'] !== ''
            &&  ($_SERVER ['REMOTE_ADDR'] == '127.0.0.1' || substr($_SERVER ['REMOTE_ADDR'], 0,3) == '10.'))
        {
            $agents=explode(',', $_SERVER ['HTTP_X_FORWARDED_FOR']);
            $onlineip=$agents[0];
        }
        else
        {
            $onlineip=$_SERVER ['REMOTE_ADDR'];
        }

        preg_match ( "/[\d\.]{7,15}/", $onlineip, $match );
        $onlineip = $match [0] ? $match [0] : 'unknown';
        if($onlineip==='unknown'){
            $onlineip='';
        }
        return $onlineip;
    }
}