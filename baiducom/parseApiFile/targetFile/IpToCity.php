<?php
/***************************************************************************
 *
* Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
/**
 * $Id$
 * @author caoyangmin(caoyangmin@baidu.com)
 * @brief
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
require __DIR__.'/utils/IpLib.php';


/**
 * 通过IP获取IP所在的城市信息
 * @author caoym
 * @path("/ip_to_city")
 */
class IpToCity
{
    /**
     * @param string $iplib_file
     *            ip库路径
     */
    function __construct($iplib_file)
    {
        $this->iplib = new utils\IpLib($iplib_file);
    }

    /**
     * 通过IP获取IP所在的城市信息
     * @route({"GET","city"})
     * @param({"ip", "$.path[2]"}) string $ip IP地址
     * @throws({"tinyESB\util\exceptions\NotFound", "status", "404 Not Found"}) 没有找到IP对应的城市
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) 返回包含城市信息的数组
     *      {
     *      "country":国家,
            "isp":运营商,
            "province":省,
            "city":市,
            "county":县,
            "country confidence":置信度
            "isp confidence":置信度
            "province confidence":置信度
            "city confidence":置信度
            "county confidence":置信度
            }
     */
    public function getCity($ip)
    {
        $found = $this->iplib->find($ip) or Verify::e(new NotFound());
        
        $keys = array(
            'country',
            'isp',
            'province',
            'city',
            'county',
            'country confidence',
            'isp confidence',
            'province confidence',
            'city confidence',
            'county confidence',
        );
        $values = array_slice($found, 2) + array_fill(0, count($keys), null);
        return array_combine($keys, $values);
    }

     /**
     * 获取请求方IP地址对应的城市信息
     * @route({"GET","mycity"})
     * @param ({"ip", "$._SERVER.REMOTE_ADDR"}) string $ip IP地址
     * @throws ({"tinyESB\util\exceptions\NotFound", "status", "404 Not Found"}) 没有找到IP对应的城市
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}) 返回包含城市信息的数组
     */
    public function getMyCity($ip){
        return $this->getCity($ip);
    }
    public $iplib;
}