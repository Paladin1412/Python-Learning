<?php
/**
 *
* @desc 用户广告行为存储至redis
* @path("/ads_log/")
*/

class Adslog
{    
    /** @property*/
    private $cache;

    /**
     * @desc cuid文件预热
     * @route({"POST", "/import"})
     * @param ({"ads_log_file", "$._FILES.ads_log_file.tmp_name"}) 本地广告日志文件
     * @param ({"num", "$._GET.num"}) 一次加入缓存的cuid数量，默认为5w 
     * @return({"body"}){
                            "code": 0,
                            "info": "ads log import OK: $ads_log_file has been imported",
                            "ret" => ""
                        }   
     */
    public function adsLogImport($ads_log_file = null, $num = 50000){
        $res = array(
            "code" => 0,
            "info" => "",
            "ret" => "",
        );     
        if(!isset($ads_log_file)){
            $res['code'] = 1;
            $res['info'] = "ads log import failed: ads log file not found";
            return $res;
        }        
        $file = file($ads_log_file);
        $file_chunk = array_chunk($file, $num);
        foreach($file_chunk as $chunk){
            $inputs = array();
            foreach($chunk as $line){
                $line = trim($line);
                $eles = explode("\t", $line);
                if(count($eles) !== 4){
                    continue;
                }
                $key = "ads_log_".$eles[0];
                $ads_log = $eles[1].":".$eles[2];
                $member = array();
                $member['member'] = $ads_log;
                $member['score'] = 1;
                $members[] = $member;                
                $input = array(
                    'key' => $key,
                    'members' => $members,
                );
                $inputs[] = $input;
            }                  
            $ret_all = $this->cache->multizadd($inputs);
            $err_msg = $ret_all['err_msg'];
            if($err_msg !== "OK"){
                $res['code'] = 1;
                $res['info'] = "ads log import failed: $err_msg";
                return $res;            
            }
        }     
        $res['code'] = 0;
        $res['info'] = "ads log import OK: ads_log_file has been imported";
        return $res;
    }
    
    /**
     * @desc 查看cuid对于的广告行为记录
     * @route({"GET", "/query"})
     * @param({"cuid", "$._GET.cuid"}) 用户cuid
     * @return({"body"}){
                            "code": 0,
                            "info": "cuid query OK",
                            "ret" : ""
                         }
     */
    public function cuidQuery($cuid = null, $start = 0, $stop = -1){
        $res = array(
            "code" => 0,
            "info" => "",
            "ret" => "",
        );       
        $key = "ads_log_".$cuid;       
        $cache_get_status = null;
        $ads_log = $this->cache->zrange($key, $start, $stop, $cache_get_status);
        if ($cache_get_status === false || is_null($res)) {
            $res['code'] = 1;
            $res['info'] = "cuid query error";
            return $res;
        }        
        $res['code'] = 0;
        $res['info'] = "cuid query OK";
        $res['ret'] = $ads_log;
        return $res;
    }
}