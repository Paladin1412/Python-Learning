<?php
/**
 *
 * @desc 上屏彩蛋接口
 * @path("/screeneggs/")
 */

use utils\CacheVersionSwitchScope;
use utils\Util;

class ScreenEggs
{
    /**
     * @desc 上屏彩蛋关系升级
     * @route({"POST", "/upgrade"})
     * @param({"cks", "$._POST['cks']"}) 需要检测的客户端已安装ids json [{id: "id", version_code: "0"}]
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
       {
            "data": [
                {
                    "id": 53,
                    "version_code": 0,
                    "res_net": 0,// 0/1/2/3/4/5,
                    "file": "http://a.kwd",  //关系文件
                    "res":[1,2,4,6],         //资源文件
                }
            ]
        }
     */
    public function upgrade($cks = '', $ver_name = '5.4.0.0', $intMsgVersion = 0)
    {
        //Edit by fanwenli on 2018-09-25, set return by format
        $res = Util::initialClass(false);
        
        $intVersion = Util::getVersionIntValue($ver_name);
        //$res = array('data' => array());
        $cks && $cks = json_decode($cks, true);

        $eggsModel = IoCload("models\\ScreenEggsModel");
        $eggs = $eggsModel->getUpgradeScreenEggs($cks, $intVersion);
        
        $eggs && $res['data'] = $eggs;
        
        //Edit by fanwenli on 2018-09-25, set version after system get resource
        $res['version'] = intval($eggsModel->intMsgVer);

        //return $res;
        //Edit by fanwenli on 2018-09-25, check return's format
        return Util::returnValue($res,false,true);
    }

    /**
     * @desc 彩蛋资源下载
     * @route({"GET", "/res/*\/file"})
     * @param({"id", "$.path[2]"}) string $id 资源id
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * * @param({"screen_w", "$._GET.screen_w"}) int $screen_w 屏幕宽，不需要客户端传，从加密参数中获取
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     * 验证通过则：
     * HTTP/1.1 302 Found
     * Location: 下载链接
     * 验证不通过则：
     * HTTP/1.1 404 Not Found
     */
    public function resDownload($id = 0, $plt = 'a1', $screen_w = '0', &$status = '', &$location = '')
    {
        $id = trim($id);

        if($id)
        {
            $eggResModel = IoCload('models\\ScreenEggsResModel');
            $obj = new CacheVersionSwitchScope($eggResModel->getCacheObj(), 'screen_eggs_res');
            $url = $eggResModel->cache_getResUrl($id, $plt, $screen_w);

            if($url)
            {
                $status = "302 Found";
                $location = "Location:" . $url;
                return;
            }
        }
        $status = "404 Not Found";
        return ;
    }
    
    /**
     * @desc 上屏彩蛋策略下发
     * @route({"GET", "/strategy"})
     * @param({"intMsgVersion", "$._GET.message_version"}) $intMsgVersion message_version 客户端上传版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
       {
            "data": "8RAGq9hsAo9f4FN09Jx1jtMuBh_KuvCNzRAG69hrAo9f4FN09Jxn"
        }
    */
    public function strategy($intMsgVersion = 0){
        //$out = array('data' => array());
        //Edit by fanwenli on 2019-03-22, set return by format
        $out = Util::initialClass(false);
        
        $eggsModel = IoCload("models\\ScreenEggsStrategyModel");
        $eggsStrategy = $eggsModel->getScreenEggsStrategy();
        
        $eggsStrategy_arr = array();
        //整理数据
        if(!empty($eggsStrategy)){
            foreach($eggsStrategy as $val){
                $eggsStrategy_arr[] = array(
                    'id' => $val['strategy_id'],
                    'description' => $val['description'],
                    'app_ids' => Util::packageToArray($val['package_name']),
                );
            }
        }
        
        $out['version'] = intval($eggsModel->intMsgVer);
        
        if(!empty($eggsStrategy_arr)){
            $out['data'] = bd_B64_encode(json_encode($eggsStrategy_arr),0);
        }
        
        //return $out;
        //Edit by fanwenli on 2019-03-22, check return's format
        return Util::returnValue($out,false,true);
    }
}