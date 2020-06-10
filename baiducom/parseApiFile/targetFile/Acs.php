<?php
/**
 *
 * @desc acs 搜索
 * @path("/acs/")
 */
use utils\Util;

class Acs
{

    /**
     * @desc acs搜索匹配
     * @route({"POST", "/wr"})
     * @param({"cuid", "$._GET.cuid"})
     * @param ({"query", "$._POST.query"}) {'cuid':,'cursor':,'word':}
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        data: ['共产党宣言','共产党党章']
     }
     */
    public function wr($cuid = '', $query = '')
    {
        $res = array('data' => array());
        $twModel = IoCload("models\\TopWordModel");
        $query = trim($query);
        $query = bd_B64_Decode($query, 0);
        $query = json_decode($query, true);
        $cuidw = $query['cuid'];
        $word  = $query['word'];
        $cursor = $query['cursor'];
        if ($cuidw == urlencode($cuid) && $word)
        {
            $wrs = $twModel->cache_getWordRank($word);
            $wrs_pn = $wrs['nlpc_trunks_pn'];

            $klen = 0;
            $bef = array();
            $aft = array();
            foreach ($wrs_pn as $k => $v)
            {
                $klen += mb_strlen($v['buffer']);
                //!Util::isPunct(trim($v['buffer'])) && $bef[] = $v['buffer'];
                $bef[] = $v['buffer'];
                if ($klen >= $cursor + 1)
                {
                    if (trim($wrs_pn[$k + $i]) === '' || Util::isPunct(trim($wrs_pn[$k + $i]['buffer'])))
                    {
                        continue;
                    }

                    //寻找落点以后数据
                    $i = 1;
                    $j = 0;
                    $len = count($wrs_pn);
                    while($j < 2 && (($i + $k) < $len))
                    {
                        if (trim($wrs_pn[$k + $i]) !== '' && !Util::isPunct(trim($wrs_pn[$k + $i]['buffer'])))
                        {
                            $j++;
                        }
                        $aft[] = $wrs_pn[$k + $i]['buffer'];
                        $i++;
                    }

                    // 寻找落点以前数据
                    $i = count($bef);
                    $tbef = array();
                    $j = 0;
                    while($j < 3 && $i > 0)
                    {
                        if (trim($bef[$i-1]) !== '' && !Util::isPunct(trim($bef[$i-1])))
                        {
                            $j++;
                        }
                        $tbef[] = $bef[$i-1];
                        $i--;
                    }
                    $tbef = array_reverse($tbef);

                    $res['data'] = array(implode('', $tbef) . '' . implode('', $aft));//  array_merge($bef, $aft);

                    break;
                }
            }
        }

        return $res;
    }

    /**
     * @desc 下发包列表
     * @route({"GET", "/pl"})
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        ecode: 0,
        emsg: success,
        data: ['com.tencent.mobileqq','com.tencent.wechat')],
        version: 1231312
     }
     */
    public function pl($plt = 'a1', $notiVersion = 0) {
        $apnModel = IoCload("models\\AcsPackageNameModel");
        //$res = array('data' => $apnModel->getPackages());
        
        //Edit by fanwenli on 2018-05-04, set construction with new style about error code & error msg
        $out = Util::initialClass(false);
        
        $out['data'] = $apnModel->getPackages();
        $out['ecode'] = $apnModel->getStatusCode();
        $out['emsg'] = $apnModel->getErrorMsg();
        $out['version'] = intval($apnModel->intMsgVer);

        return Util::returnValue($out,false,true);
    }

    /**
     * @desc 下发白名单
     * @route({"GET", "/wl"})
     * @param({"sdkVer", "$._GET.sdk_version"})
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        ecode: 0,
        emsg: success,
        data: ['com.tencent.mobileqq','com.tencent.wechat')],
        sdk_version: 23123,
        version: 1231312
     }
     */
    public function wl($sdkVer = '', $notiVersion = 0) {
        $awlModel = IoCload("models\\AcsWhiteListModel");
        //$res = array('data' => $awlModel->getWl($sdkVer), 'sdk_version' => $sdkVer);
        
        //Edit by fanwenli on 2018-05-04, set construction with new style about error code & error msg
        $out = Util::initialClass(false);
        
        $out['data'] = $awlModel->getWl($sdkVer);
        $out['sdk_version'] = $sdkVer;
        $out['ecode'] = $awlModel->getStatusCode();
        $out['emsg'] = $awlModel->getErrorMsg();
        $out['version'] = intval($awlModel->intMsgVer);

        return Util::returnValue($out,false,true);
    }
    
    
    /**
     * @desc 下发智能回复白名单
     * @route({"GET", "/airwl"})
     * @param({"sdkVer", "$._GET.sdk_version"})
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        data: [
            {
                package_name: "xxxxxxx",
                version_range: [
                "1.0.0.1-5.6.0.1"
                ]
            },
            {
                package_name: "yyyyy",
                version_range: [
                "1.0.0.1-5.6.0.1"
                ]
            },
            {
                package_name: "qq.com",
                version_range: [
                "1.0.0.1-5.6.0.1",
                "1.0.0.1-5.6.0.1"
                ]
            },
            {
                package_name: "tent.com",
                version_range: [
                "1.0.0.1-3.6.0.1",
                "1.0.0.1-2.6.0.1"
                ]
            }
        ],
        sdk_version: "100"
     }
     */
    public function airwl($sdkVer = '', $notiVersion = 0) {
        $awlModel = IoCload("models\\AirModel");
        
        //Edit by fanwenli on 2018-07-04, set construction with new style about error code & error msg
        $out = Util::initialClass(false);
        
        //$res = array('data' => $awlModel->getAirwl($sdkVer), 'sdk_version' => $sdkVer);
        $out['data'] = $awlModel->getAirwl($sdkVer);
        $out['sdk_version'] = $sdkVer;
        $out['ecode'] = $awlModel->getStatusCode();
        $out['emsg'] = $awlModel->getErrorMsg();
        $out['version'] = intval($awlModel->intMsgVer);

        return Util::returnValue($out,false,true);
    }
    
    
    /**
     * @desc 下发智能回复包
     * @route({"GET", "/airpack"})
     * @param({"intCoreSign", "$._GET.core_sign"}) 内核更新标识，智能回复下载包内核部分为1，默认为0
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        data: {
            id: 2,
            title: "22222b",
            res_url: "http://mco.ime.shahe.baidu.com:8891/res/file/ai_resp_package/files/149309914462026.bin",
            airpack_version_code: 1493099146
        }
     }
     */
    public function airpack($intCoreSign = 0, $notiVersion = 0) {
        $awlModel = IoCload("models\\AirPackModel");
        
        //Edit by fanwenli on 2018-07-05, set construction with new style about error code & error msg
        $out = Util::initialClass(false);
        
        //$res = array('data' => $awlModel->getAirPack());
        
        $data = $awlModel->getAirPack();
        //core must be checked whether it has core_version
        if(intval($intCoreSign) == 1) {
            if(!isset($data['core_version']) || empty($data['core_version'])) {
                $data = array();
            }
        }
        
        $out['data'] = $data;
        $out['ecode'] = $awlModel->getStatusCode();
        $out['emsg'] = $awlModel->getErrorMsg();
        $out['version'] = intval($awlModel->intMsgVer);

        return Util::returnValue($out,false,true);
    }
    
    
    /**
     * @desc 下发智能回复白名单
     * @route({"GET", "/airstra"})
     * @param({"sdkVer", "$._GET.sdk_version"})
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        data: [
            {
                package_name: "youku.cn",
                version_range: "1.0.0.1-5.6.0.1",
                stragtegy: {
                    id_list: [
                    "434",
                    "545",
                    "54"
                    ]
                }
            },
            {
                package_name: "weix.omc",
                version_range: "1.0.0.1-5.6.0.1",
                stragtegy: {
                    id_list: [
                    "6",
                    "9"
                    ]
                }
            },
            {
                package_name: "baidu.adsf.omc",
                version_range: "1.0.0.1-5.6.0.1",
                stragtegy: {
                    id_list: [
                    "989",
                    "89",
                    "6"
                    ]
                }
            },
            {
                package_name: "qq.com",
                version_range: "1.0.0.1-5.6.0.12",
                stragtegy: {
                    id_list: [
                    "1",
                    "3",
                    "4"
                    ]
                }
            }
        ],
        sdk_version: "100"
    }
     */
    public function airstra($sdkVer = '', $notiVersion = 0) {
        $airstraModel = IoCload("models\\AirStraModel");
        //$res = array('data' => $airstraModel->getList($sdkVer), 'sdk_version' => $sdkVer);
        
        //Edit by fanwenli on 2018-07-13, set construction with new style about error code & error msg
        $out = Util::initialClass(false);
        
        $out['data'] = $airstraModel->getList($sdkVer);
        $out['sdk_version'] = $sdkVer;
        $out['ecode'] = $airstraModel->getStatusCode();
        $out['emsg'] = $airstraModel->getErrorMsg();
        $out['version'] = intval($airstraModel->intMsgVer);

        return Util::returnValue($out,false,true);
    }
    
    /**
     * @desc 打包文件
     * @route({"GET", "/top_words_file"})
     * @param({"notiVersion", "$._GET.message_version"}) $notiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        ecode: 0,
        emsg: success,
        data: "http://res.mi.baidu.com/imeres/dev-env/acs_to_20160811.gz",
        version: 1231312
     }
     */
    public function twf($notiVersion = 0) {
        $topWordsFileModel = IoCload("models\\TopWordsFileModel");
        
        $out = Util::initialClass(false);
        
        $data = $topWordsFileModel->cache_getNewFile();
        
        //download url
        $out['data'] = empty($data['url']) ? '' : $data['url'];
        $out['ecode'] = $topWordsFileModel->getStatusCode();
        $out['emsg'] = $topWordsFileModel->getErrorMsg();
        $out['version'] = empty($data['version_code']) ? 0 : intval($data['version_code']);

        return Util::returnValue($out,false);
    }
}