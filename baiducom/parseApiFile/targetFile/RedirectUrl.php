<?php
/**
 * 跳转到指定的url
 *
 * @author yangxugang
 * @date 2016年3月1日
 * @path("/redirectUrl/")
 */
class RedirectUrl {
    /** @property 内部缓存实例 */
    private $cache;
    
    /** @property 请求资源内容缓存key pre*/
    private $strResContentCachePre;
    
    /** @property 缓存过期时长*/
    private $intCacheExpired;
    
    /**
     * @route({"GET","/redirect"})
     * @return({"body"}){
                            "code": 0,
                            "info": "multidel OK: deleting sorted set {Skintheme::market::plt=i5:ver_name=5-6-0-19} from 0 to -1 and 2 has been deleted",
                            "ret": ""
                        }
     */
    public function redirect() {
        $strPath = '/res/json/input/r/online/redirectUrl/?onlycontent=1';
        $res = $this->getResourceString($strPath);
        if (!empty(current($res))) {
            $url = current($res)['redirect'];
        } else {
            $url = 'http://top.baidu.com/m?csrc=fyb_inputfyb_auto%EF%BC%9B';
        }
        
        header("Location: " . $url);
        
        exit();
    }
    
    /**
     * 通过path获取资源内容为string类型
     * 对资源内容缓存
     *
     * @param $strPath 请求path
     * @param $strQuery query string
     * @return array
     */
    public function getResourceString($strPath, $strQuery = null){
        $strCacheKey = $this->strResContentCachePre . $strPath;
        $bolStatus = false;
        $strCache = $this->cache->get($strCacheKey, $bolStatus);
        if (null !== $strCache){
            return $strCache;
        }
        //获取content信息
        if(null === $strQuery){
            $arrHeader = array(
                'pathinfo' => $strPath,
            );
        }else{
            $arrHeader = array(
                'pathinfo' => $strPath,
                'querystring'=> $strQuery,
            );
        }
    
        $strResult = ral("resJsonService", "get", null, rand(), $arrHeader);
        if(false === $strResult){
            return false;
        } else {
            //set cache
            $this->cache->set($strCacheKey, $strResult, $this->intCacheExpired);
            return json_decode($strResult, true);
        }
    }
}