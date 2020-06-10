<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/ime/")
 * Ime.php UTF-8 2019-4-2 17:39:34
 */
use utils\GFunc;

class Ime {

    /**
     * @desc pc输入法限量跳转
     * @route({"GET", "/rewrite"})
     * @param({"r", "$._GET.r"})  跳转id
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function rewrite($r = '') {
        $strPre = 'pc_ime_rewrite_';
        $strKey = base_convert($r, 36, 10);
        if ($strKey === false) {
            $this->_notFound();
        }
        $redis           = GFunc::getCacheInstance();
        $strConfKey      = $strPre . $strKey . '_conf';
        $strNumKey       = $strPre . $strKey . '_num';
        $successed       = false;
        $arrConf         = $redis->get($strConfKey,$successed);
        $retry           = 0;
        while($retry < 5  && $successed===false ){
            $arrConf     = $redis->get($strConfKey,$successed);
            $retry++;
        }
        if ($arrConf === false || $arrConf === null || !isset($arrConf['url']) || !isset($arrConf['num'])) {
            $this->_notFound();
        }
        $num = intval($arrConf['num']);
        if ($num === 0) {
            $this->_notFound();
        }
        else if ($num === -1) {
            $this->_rewrite($arrConf['url']);
        }
        else {
            $boolNumSuccess = false;
            $numCurrent     = $redis->incr($strNumKey, $boolNumSuccess);
            if ($boolNumSuccess === false || $numCurrent === null || $numCurrent > $num) {
                $this->_notFound();
            }
            else {
                $this->_rewrite($arrConf['url']);
            }
        }
    }

    /**
     * @desc wx安卓端跳出
     * @route({"GET", "/wx"})
     * @param({"r", "$._GET.r"})  跳转url
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */
    public function wxRewrite($r = '') {
        $url      = urldecode($r);
        $matchUrl = preg_match('/^(http|https):\/\/(.*)\.baidu\.com(:\d+)?\/(.*)/', $url);
        if ($matchUrl) {
            $match = preg_match("/MicroMessenger/", $_SERVER['HTTP_USER_AGENT']);
            if ($match) {
                header('HTTP/1.1 206 Partial Content');
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Length:0');
                header('Content-Range: bytes 0-1/1');
                header('Content-Disposition:attachment;filename=file.apk');
                die();
            }
            header('Location:' . $url, true, 302);
        }
        die();
    }

    /**
     * 
     */
    protected function _notFound() {
        header('HTTP/1.1 404 Not Found');
        echo "file not found";
        die();
    }

    /**
     * 
     * @param type $url
     */
    protected function _rewrite($url = '') {
        header('Location: ' . $url, true, 302);
        die();
    }

}
