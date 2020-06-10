<?php
/**
 *
 * @desc cangjie
 * @path("/cangjie/")
 */
use utils\GFunc;

class Cangjie
{
    /**
     * @desc zde
     * @route({"GET", "/cde"})
     * @param({"cangjie_ver", "$._GET.cangjie_ver"}) int
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
        }
     */
    public function cde($cangjie_ver = 0)
    {
        $info = $this->getLatestVersion($cangjie_ver);

        if (!empty($info['ver']) && $info['ver'] <= $cangjie_ver) {
            $info = array(
                'status' => 0,
            );
        }

        unset($info['refer_link']);
        return $info;
    }


     /**
     * @desc
     * @route({"GET", "/cdo"})
     * @param ({"ver", "$._GET.ver"}) 版本号
     *
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     * 验证通过则：
     * HTTP/1.1 302 Found
     * Location: 下载链接
     * 验证不通过则：
     * HTTP/1.1 404 Not Found
     */
    public function cdo($ver = 0, &$status = '', &$location = '')
    {
        if ($ver && $ver > 0)
        {
            $cangjieModel = IoCload('models\\CangjieModel');
            $cangjieInfo = $this->getLatestVersion($ver);

            if(!empty($cangjieInfo))
            {
                $url = $cangjieInfo['refer_link'];
                $status = "302 Found";
                $location = "Location: " . $url;
                return;
            }
        }

        $status = "404 Not Found";
        return ;
    }

    /**
     * 获取注音最新版本，判断是否有更新，写入返回数组
     * @return
     */
    private function getLatestVersion()
    {
        $ret = array(
            'status' => 0,
        );

        $cangjieModel = IoCload('models\\CangjieModel');
        $cangjieInfo = $cangjieModel->cache_getLatestCangjie();

        if (!empty($cangjieInfo['version']) && $cangjieInfo['version']) {
            $ret['status']   = 1;
            $ret['ver']      = $cangjieInfo['version'];
            $ret['dlink']    = $cangjieInfo['dlink'];
            $ret['token']    = $cangjieInfo['token'];
            $ret['filesize'] = $cangjieInfo['dlink_size'];
            $ret['refer_link'] = $cangjieInfo['refer_link'];
        }

        return $ret;
    }
}