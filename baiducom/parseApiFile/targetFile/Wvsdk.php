<?php
/**
 *
 * @desc
 * @path("/wvsdk/")
 */
class Wvsdk {


    /**
     * @var 获取版本号
     */
    private $ver;

    /**
     * @var 获取平台号
     */
    private $plt;


    function __construct()
    {
        $this->ver = $_GET['version'];
        $this->plt = $_GET['platform'];
    }

    /**
     * @desc
     * @route({"POST", "/upgrade"})
     * @param({"cks", "$._POST['cks']"}) 需要检测的客户端已安装ids json [{id: "id", version_code: "0"}]
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
    public function upgrade($cks = '')
    {


        $version =  str_replace('-', '.', $this->ver);
        $result = version_compare($version,'8.2.6.211','>=');

        /* 华为8.2.6.211版本以上不下发浏览器sdk icafe地址：需求地址
        http://newicafe.baidu.com/issue/inputserver-2290/show?cid=5
        */
        if( $this->plt == 'p-a1-3-72'&& $result)
        {
            $res = array('data' => array());
            return $res;
        }

        $cks && $cks = json_decode($cks, true);

        $res = array('data' => array());

        $wvModel = IoCload("models\\WebviewsdkModel");
        $sdk = $wvModel->getUpdateSdk($cks);
        $wvModel && $res['data'] = $sdk;

        return $res;
    }

    /**
     * @param string $version
     * @return float|
     */
    private function getVersionValue($version = ''){
        $parts = explode('-', $version);
        $len = count($parts);
        $version_value = 0;
        for($i = 0; $i < $len; $i++) {
            $version_value =  $version_value * 100 + intval($parts[$i]);
        }
        return $version_value;
    }


}

