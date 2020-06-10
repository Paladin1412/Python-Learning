<?php
/**
 *
* @desc cuid文件预热至redis缓存
* @path("/preheat/")
*/

class Preheat
{    
    /** @property*/
    private $storage;

    /**
     * @desc cuid文件预热
     * @route({"POST", "/set"}) 
     * @param({"cuid_url", "$._GET.cuid_url"}) cuid文件bcs地址 
     * @param ({"cuid_file", "$._FILES.cuid_file.tmp_name"}) 本地cuid文件 
     * @param ({"num", "$._GET.num"}) 一次加入缓存的cuid数量，默认为5w 
     * @return({"body"}){
                            "code": 0,
                            "info": "cuid file preheat OK: load url to cache success"
                        }   
     */
    public function cuidFilePreheat($cuid_url = null, $cuid_file = null, $num = 50000){
        $res = array(
            "code" => 0,
            "info" => "",
        );
        if(!isset($cuid_url)){
            $res['code'] = 1;
            $res['info'] = "cuid file preheat failed: cuid url not defined";
            return $res;
        }
        if(!isset($cuid_file)){
            $res['code'] = 1;
            $res['info'] = "cuid file preheat failed: cuid file not found";
            return $res;
        }
        //urlencode
        $fname_eles = explode("/", $cuid_url);
        $fname = urlencode($fname_eles[count($fname_eles)-1]);
        $fname_eles[count($fname_eles)-1] = $fname;
        $cuid_url = implode("/", $fname_eles);
        //cache key
        $key = 'cuidfile'.crc32($cuid_url);
        $file = file($cuid_file);
        $file_chunk = array_chunk($file, $num);
        foreach($file_chunk as $chunk){
            $member = array();
            foreach($chunk as $line){
                $line = trim($line);                
                $member[] = $line;                
            }
            $sadd_status = null;
            $this->storage->sadd($key, $member, $sadd_status);
            if($sadd_status){
                $res['code'] = 1;
                $res['info'] = "cuid file preheat failed: load url to cache failed";
                return $res;
            }
        }     
        $res['code'] = 0;
        $res['info'] = "cuid file preheat OK: load url to cache success";
        return $res;
    }
    
    /**
     * @desc 查看cuid缓存对应集合有多少元素
     * @route({"GET", "/cnt"})
     * @param({"cuid_url", "$._GET.cuid_url"}) cuid文件bcs地址
     * @return({"body"}){
                            "code": 0,
                            "info": "cuid file zcard OK: there are $zcard_res keys in set {cuidfile2286445522}"
                         }
     */
    public function cuidFileCnt($cuid_url = null){
        $res = array(
            "code" => 0,
            "info" => "",
        );
        if(!isset($cuid_url)){
            $res['code'] = 1;
            $res['info'] = "cuid file del failed: cuid url not defined";
            return $res;
        }
        //urlencode
        $fname_eles = explode("/", $cuid_url);
        $fname = urlencode($fname_eles[count($fname_eles)-1]);
        $fname_eles[count($fname_eles)-1] = $fname;
        $cuid_url = implode("/", $fname_eles);
        //cache key
        $key = 'cuidfile'.crc32($cuid_url);
        $scard_status = null;
        $scard_res = $this->storage->scard($key, $scard_status);
        if ($scard_status === false) {
            $res['code'] = 1;
            $res['info'] = "cuid file zcard OK: get count for sorted set {{$key}} failed";
            return $res;
        }
        $res['code'] = 0;
        $res['info'] = "cuid file zcard OK: there are $scard_res keys in set {{$key}}";
        return $res;
    }
    
    
    /**
     * @desc 删除cuid文件所在的缓存
     * @route({"GET", "/del"})
     * @param({"cuid_url", "$._GET.cuid_url"}) cuid文件bcs地址
     * @param({"username", "$._GET.username"}) string $username 用户名
     * @param({"password", "$._GET.password"}) string $password 密码 
     * @return({"body"}){
                            "code": 0,
                            "info": "cuid file del OK: set {cuidfile2286445522} has beed deleted"
                         }
     */
    public function cuidFileDel($cuid_url = null, $username = '', $password = ''){
        $res = array(
            "code" => 0,
            "info" => "",
        );    
        if(!isset($cuid_url)){
            $res['code'] = 1;
            $res['info'] = "cuid file del failed: cuid url not defined";
            return $res;
        }
        if($username !== 'inputserverrdqa' || $password !== 'inputserverrdqa'){
            $res['code'] = 1;
            $res['info'] = "multidel error: authentication failed";
            return $res;
        }
        //urlencode
        $fname_eles = explode("/", $cuid_url);
        $fname = urlencode($fname_eles[count($fname_eles)-1]);
        $fname_eles[count($fname_eles)-1] = $fname;
        $cuid_url = implode("/", $fname_eles);
        //cache key
        $key = 'cuidfile'.crc32($cuid_url);
        $del_status = null;
        $this->storage->del($key, $del_status);
        if( $del_status){
            $res['code'] = 0;
            $res['info'] = "cuid file del OK: set {{$key}} has beed deleted";
            return $res;
        }else{
            $res['code'] = 1;
            $res['info'] = "cuid file del failed: set {{$key}} delete failed";
            return $res;
        }
    }
}