<?php
use utils\GFunc;
use utils\Util;
use utils\ErrorMsg;
use utils\ErrorCode;

/**
 *
 * @desc
 * @path("/hotpatch/")
 */
class Hotpatch {
    /** @property 内部缓存实例 */
    private $cache;
    
    /** @property 缓存前缀 */
    private $cachePre;
    
    /**
     * @desc
     * @route({"POST", "/upgrade"})
     * @param({"id", "$._POST['id']"})
     * @param({"versionCode", "$._POST['version_code']"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
       {
            "data": [
                {
                    "id": 53,
                    "version_code": 1,
                    "file":  'sdsdds.zip'
                }
            ]
        }
     */
    public function upgrade($id = -1, $versionCode = -1)
    {
        //$cks && $cks = json_decode($cks, true);
        $cks = array(array('id' => $id, 'version_code' => $versionCode));

        $res = array('data' => array());

        $hpModel = IoCload("models\\HotpatchModel");
        $hp = $hpModel->getUpdate($cks);
        $hp && $res['data'] = $hp;

        return $res;
    }
    
    /**
     * @desc ios hotpatch列表
     * @route({"GET", "/list"})
     * @param({"search", "$._GET['search']"})
     * @param({"onlycontent", "$._GET['onlycontent']"})
     * @param({"withcontent", "$._GET['withcontent']"})
     * @param({"searchbyori", "$._GET['searchbyori']"})
     * @param({"limit", "$._GET['limit']"})
     * @param({"sort", "$._GET['sort']"})
     * @param({"hotpatch_version", "$._GET['hotpatch_version']"}) 客户端版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
             [
            {
                "name": "7.5_增加隐私声明全量",
                "id": 28,
                "version": 1700,
                "mininputcode": 7050106,
                "maxinputcode": 7050106,
                "durl": "http://r6.mo.baidu.com/res/file/ios_hotpatch/files/150328647200379.zip",
                "md5": "4354ab3607ea9cfbf844c79cc36a09cb",
                "interval_second": 43200,
                "model": [
                    "all"
                ],
                "os": [
                    "all"
                ]
            }
        ]
     */
    public function getList($search='', $onlycontent='', $withcontent='', $searchbyori='', $limit=1, $sort='', $hotpatch_version = 0) {
        $result = array();
        
        //Edit by fanwenli on 2019-08-21, get result when client send their hotpatch version
        if(intval($hotpatch_version) > 0) {
            $key = md5($this->cachePre . $search . '_' . $onlycontent . '_' . $withcontent . '_' . $searchbyori . '_' . $limit . '_' . $sort . "_v1");
            $success = false;
            //@TODO 使用getbig方法
            $arrResult = $this->cache->get($key, $success);
            if (!$success || is_null($arrResult)) {
                $arrQuery = array();
                !empty($search) && $arrQuery[] = 'search=' . $search;
                !empty($onlycontent) && $arrQuery[] = 'onlycontent=' . $onlycontent;
                !empty($withcontent) && $arrQuery[] = 'withcontent=' . $withcontent;
                !empty($searchbyori) && $arrQuery[] = 'searchbyori=' . $searchbyori;
                !empty($limit) && $arrQuery[] = 'limit=' . $limit;
                !empty($sort) && $arrQuery[] = 'sort=' . $sort;

                $arrHeader = array(
                    'pathinfo' => '/res/json/input/r/online/ios_hotpatch/',
                    'querystring' => !empty($arrQuery) ? implode('&', $arrQuery) : '',
                );
                //请求资源服务
                $arrResult = ral("res_service", "get", null, rand(), $arrHeader);

                if (false === $arrResult) {
                    $arrResult = array();
                }
                $this->cache->set($key, $arrResult, GFunc::getCacheTime("15mins"));
            }

            if (!empty($arrResult)) {
                $conditionFilter = IoCload("utils\\ConditionFilter");
                foreach ($arrResult as $arrResultK => $arrResultV) {
                    if ($conditionFilter->filter($arrResultV['filter_conditions'])) {
                        unset($arrResultV['filter_conditions']);
                        $result[] = $arrResultV;
                    }
                }
            }
        }

        return $result;
    }
    
    
    /**
     * @desc ios hotpatch检查有无更新
     * @route({"GET", "/check"})
     * @param({"hotpatch_version", "$._GET['hotpatch_version']"}) 客户端版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": [],
            "version": 123
        }
     */
    public function checkVersion($hotpatch_version = 0) {
        $result = Util::initialClass(false);
        $result['version'] = 0;
        
        $key = $this->cachePre . '_check';
        $success = false;
        $data = $this->cache->get($key, $success);
        if (!$success || is_null($data)) {
            $arrHeader = array(
                'pathinfo' => '/res/json/input/r/online/ios_hotpatch/',
                'querystring'=> 'limit=1',
            );
            //请求资源服务
            $arrResult = ral("res_service", "get", null, rand(), $arrHeader);
            if (!empty($arrResult) && isset($arrResult[0]['update_time'])) {
                $data = intval($arrResult[0]['update_time']);
            } else {
                $data = 0;
            }
            
            $this->cache->set($key, $data, GFunc::getCacheTime("15mins"));
        }
        
        //return version if server bigger than client
        if($data > intval($hotpatch_version)) {
            $result['version'] = $data;
        }
        
        return Util::returnValue($result,false);
    }
}

