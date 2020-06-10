<?php
/**
 *
 * @desc zhuyin
 * @path("/zhuyin/")
 */
use utils\GFunc;

class Zhuyin
{
    /**
     * @desc zde
     * @route({"GET", "/zde"})
     * @param({"zhuyin_ver", "$._GET.zhuyin_ver"}) int
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
        }
     */
    public function zde($zhuyin_ver = 0)
    {
        $info = $this->getLatestVersion($zhuyin_ver);

        if (!empty($info['ver']) && $info['ver'] <= $zhuyin_ver) {
            $info = array(
                'status' => 0,
            );
        }

        unset($info['refer_link']);
        return $info;
    }

     /**
     * @desc
     * @route({"GET", "/zdo"})
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
    public function zdo($ver = 0, &$status = '', &$location = '')
    {
        if ($ver && $ver > 0)
        {
            $zhuyinModel = IoCload('models\\ZhuyinModel');
            $zhuyinInfo = $this->getLatestVersion($ver);

            if(!empty($zhuyinInfo))
            {
                $url = $zhuyinInfo['refer_link'];
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

        $zhuyinModel = IoCload('models\\ZhuyinModel');
        $zhuyinInfo = $zhuyinModel->cache_getLatestZhuyin();

        if (!empty($zhuyinInfo['version']) && $zhuyinInfo['version']) {
            $ret['status']   = 1;
            $ret['ver']      = $zhuyinInfo['version'];
            $ret['dlink']    = $zhuyinInfo['dlink'];
            $ret['token']    = $zhuyinInfo['token'];
            $ret['filesize'] = $zhuyinInfo['dlink_size'];
            $ret['refer_link'] = $zhuyinInfo['refer_link'];
        }

        return $ret;
    }
}