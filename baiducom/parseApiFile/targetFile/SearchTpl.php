<?php
use utils\Util;

/**
 *
 * 搜索模版 daixi
 * @path("/search/")
 */

class SearchTpl
{
    /**
     * 平台 ios / android
     * @var string
     */
    private $plt;
    /**
     * 输入法版本号
     * @var string
     */
    private $version;
    /**
     * 平台 ios / android code
     * @var string
     */
    private $pltcode;
    /**
     * 渠道号
     * @var string
     */
    private $from;
    /**
     * @var string
     * 系统ROM版本
     */
    public $rom;
    /**
     * @var string
     * 搜索类型
     */
    private $type;

    /***
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->plt = isset($_GET['platform']) ? Util::getPhoneOS($_GET['platform']) : '';
        $this->version = !empty($_GET['version']) ? $_GET['version'] : '7.2.0.0';
        $this->pltcode = isset($_GET['platform']) ? $_GET['platform'] : '';
        $this->from = isset($_GET['from']) ? $_GET['from'] : '';
        $this->rom = isset($_GET['rom']) ? $_GET['rom'] : '';
        $this->imei = isset($_GET['cuid']) ? $_GET['cuid'] : '';
        $this->type = isset($_GET['qt']) ? $_GET['qt'] : '';
    }


    /**
     * 获取搜索查询内容
     * @route({"POST","/tpl"})
     * @param({"intNotiVersion", "$._GET.message_version"}) $intNotiVersion message_version,通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    {
        "search_tpl_version":1470818560,
        "frm_version":"1.0.0.0",
        "data":[
            {
                "tplid":"100",
                "tpl_version":"1"
            },
            {
                "tplid":"101",
                "tpl_version":"1"
            }
        ]
    }
     */
    public function getTemplates($intNotiVersion = 0)
    {
        /**
        {
            "ecode":"0",
            "emsg":"success",
            "data":
            [
                {
                    "tplid":"100",
                    "tpl_version":"1",
                    "tpl_url":"http://bos.baidu.com",
                    "op_status":"1" //1 代表更新  2 代表删除
                }
            ],
            "error":"0",
            "search_tpl_version":1470818560,
        }
         * @var array
         */
        
        //Edit by fanwenli on 2018-08-09, 输出格式初始化
        $ret = Util::initialClass(false);
        
        /*$ret = array(
            'error' => 0,
            'search_tpl_version' => 0,
            'data' => array(),
        );*/
        
        $ret['error'] = 0;
        $ret['search_tpl_version'] = 0;

        $query = isset($_POST["query"]) ? $_POST["query"] : '';
        $query = trim($query);
        //var_dump(bd_B64_Encode($query, 0));
        $query = bd_B64_Decode($query, 0);
        $query = $query ? json_decode($query, true) : array();

        if (!empty($query))
        {
            $tpl_version = !empty($query['search_tpl_version'])
                ? $query['search_tpl_version'] : 0;

            $tplVerModel = IoCload('models\\SearchTplVersionModel');
            $real_tpl_version = $tplVerModel->cache_getMaxVersion();
            $ret['search_tpl_version'] = $real_tpl_version;

            $tpl_ids = array();
            $tkv = array();
            foreach ($query['data'] as $k => $v)
            {
                $tpl_ids = $v['tplid'];
                $tkv[$v['tplid']] = $v['tpl_version'];
            }

            if ($tpl_version <= $real_tpl_version)
            {
                $tplDataModel = IoCload('models\\SearchTplDataModel');

                $frmVersion = !empty($query['frm_version']) ? Util::getVersionIntValue($query['frm_version']) : 0;
                
                //Edit by fanwenli on 2019-07-03, use cache zget & zset
                $tmp_tpls = $tplDataModel->cachez_getTplDataByFrmVersion($frmVersion);

                $tpls = array();

                $conditionFilter = IoCload('utils\\ConditionFilter');

                foreach ($tmp_tpls as $k => $v)
                {
                    $condition = $v['condition_filter'];
                    $condition && $condition = json_decode($condition, true);

                    if (
                        (!isset($tkv[$v['tplid']]) || $v['tpl_version'] > $tkv[$v['tplid']])
                        && (!$condition || $conditionFilter->filter($condition))
                       )
                    {
                        $tkv[$v['tplid']] = $v['tpl_version'];
                        $tpls[$v['tplid']] = array(
                            'tplid' => $v['tplid'],
                            'tpl_version' => $v['tpl_version'],
                            'tpl_url' => $v['tpl_url'],
                            'op_status' => $v['op_status'],
                        );
                    }
                }
                $ret['data'] = array_values($tpls);
            }
        }
        
        //Edit by fanwenli on 2019-01-04, add version
        $ret['version'] = $ret['search_tpl_version'];

        return Util::returnValue($ret,false);
    }

}