<?php
/**
 *
 * @desc oem 相关接口
 * @path("/oem/")
 * @author zhoubin05
 */
use utils\Util;
use utils\GFunc;


class Oem
{

    //cuid和皮肤token对应关系数据缓存key前缀
    private $_cuid_skintoken_map_cache_prefix_key  = 'cuid_st_map_pkey_';

    private $_type_maps = array(
        'huawei_skin_div' => array('cache_time' => 3600), //华为皮肤导流
        'sym26ad' => array('cache_time' => 3600), //26键符号键盘开关
    );

    /**
     * @route({"GET","/dswitch"})
     * 获取oem引流开关配置
     * @return
     */
    public function getDiversionSwitch() {

        $arrData = $this->_getProtoDataWithCache('oem_diversion_switch','oem_diversion_switch_v1', GFunc::getCacheTime('30mins'));

        $arrVal = array();
        if(!empty($arrData['data']) && is_array($arrData['data'])) {
            $arrFirstData = current($arrData['data']);

            foreach($arrFirstData['switchlist'] as $k => $v) {
                if(!isset($arrVal[$v['switch_name']])) {
                    $arrVal[$v['switch_name']] = 1 === intval($v['val']) ? 1 : 0;
                }
            }


        }

        $arrData['data'] = $arrVal ;


        return Util::returnValue($arrData, true, true);



    }

    /**
     * @route({"GET","/dbmt"})
     * oem熊头菜单顶部导流配置
     * @return
     */
    public function getDiversionBearHeadMenuTop() {
        $arrData = $this->_getProtoDataWithCache('oem_diversion_bearhead_menu_top','oem_diversion_bearhead_menu_top_v1', GFunc::getCacheTime('30mins'));
        $arrFirstData = array();
        if(!empty($arrData['data']) && is_array($arrData['data'])) {
            $arrFirstData = current($arrData['data']);
            unset($arrFirstData['title']);
            unset($arrFirstData['description']);
        }
        $arrData['data'] = $arrFirstData;

        return Util::returnValue($arrData, true, true);

    }



    /**
     * @route({"GET","/dlist"})
     * oem导流主线缺失功能菜单列表
     * @return
     */
    public function getDiversionConfList() {

        $arrData = $this->_getProtoDataWithCache('oem_diversion_conf_list','oem_diversion_conf_list_v1', GFunc::getCacheTime('30mins'));
        $arrTmp =  array();
        if(!empty($arrData['data']) && is_array($arrData['data'])) {
            foreach($arrData['data'] as $k => $v) {
                $arrTmp = array_merge($arrTmp, $v['contab']);
            }

            array_multisort(array_column($arrTmp,'sort'), SORT_DESC, $arrTmp);

        }
        $arrData['data'] = array('list' => $arrTmp);

        return Util::returnValue($arrData, false, true);

    }

    /**
     * @route({"GET","/dtab"})
     * oem导流主线扩展面板菜单
     * @return
     */
    public function getDiversionConfTab() {

        $arrData = $this->_getProtoDataWithCache('oem_diversion_conf_tab','oem_diversion_conf_tab_v1', GFunc::getCacheTime('30mins'));
        $arrTmp =  array();

        if(!empty($arrData['data']) && is_array($arrData['data'])) {

            foreach($arrData['data'] as $k => $v) {
                $arrTmp = array_merge($arrTmp, $v['contab']);
            }

            array_multisort(array_column($arrTmp,'sort'), SORT_DESC, $arrTmp);

        }

        $arrData['data'] =  array('list' => $arrTmp);

        return Util::returnValue($arrData, false, true);

    }

    /**
     * @param $strProtoName proto名称
     * @param $strCacheKey 缓存key
     * @param $intCacheTime 缓存时间
     * @return array
     */
    private function _getProtoDataWithCache($strProtoName, $strCacheKey, $intCacheTime) {


        GFunc::getNotiReturn();

        $rtData = Util::initialClass();

        $result = array();

        $cache_key = $strCacheKey;


        $cacheData = GFunc::cacheZget($cache_key);
        if (false !== $cacheData && null !== $cacheData){
            $arrResult = $cacheData['data'];
            $strVer = $cacheData['version'];
        } else {
            $bolRalSuccessed = true;
            $arrResult = GFunc::getRalContent($strProtoName,0,'', $bolRalSuccessed);
            $strVer = GFunc::$intResMsgVer;

            $cacheTime = true === $bolRalSuccessed ? $intCacheTime : GFunc::getCacheTime('5mins');
            GFunc::cacheZset($cache_key, array('data' => $arrResult, 'version' => $strVer), $cacheTime);

        }


        $rtData['data'] = array();


        if(is_array($arrResult)) {

            $conditionFilter = IoCload("utils\\ConditionFilter");

            foreach ($arrResult as $k => $item)
            {
                if($conditionFilter->filter($item['filter_conditions']))
                {
                    unset($item['filter_conditions']);

                    $rtData['data'][] = $item;

                }
            }
        }


        $rtData['version'] = $strVer;


        return $rtData;
    }


    /**
     * @route({"GET","/gst"})
     * 通过cuid获取skin_token, 广告id等映射关系
     *
     * {
            "ecode": 0,
            "emsg": "success",
            "data": {
                "sym26ad": {
                    "time": 1556453015,
                    "id": "8848"
                },
                "skin": {
                    "time": 1556452957,
                    "id": "1e9e823ebb546d9a11ee235e6d8f127d"
                }
            }
        }
     *
     * @return
     *
     */
    public function getSkinTokenByCuid() {

        $rtData = Util::initialClass(false);

        $strCuid = trim($_GET['cuid']);

        $rtData['data']['list'] = array();
        if(!empty($strCuid) ) {

            $arrTmp = array();
            foreach ($this->_type_maps as $key => $value) {
                $strCacheKey = $this->_cuid_skintoken_map_cache_prefix_key .'_'.$key .'_' . $strCuid;
                $result = GFunc::cacheGet($strCacheKey);
                if(false !== $result && null !== $result) {
                    $result['name'] = $key;
                    $arrTmp[] =  $result;
                }
            }

            //根据保存时间排序，最新的排最前
            array_multisort(array_column($arrTmp,'time'), SORT_DESC, $arrTmp);

            $rtData['data']['list'] = $arrTmp;



        }

        return Util::returnValue($rtData);

    }

    /**
     * @route({"GET","/sst"})
     * 记录cuid和skin_token的关系，同时跳转指定url
     * @return
     */
    public function setSkinTokenByCuid() {
        $strCuid = trim($_GET['cuid']);
        //广告id，皮肤token等都用'id'表示
        $strId = trim($_GET["id"]);
        //类型，标记是皮肤token还是广告
        $strMayType = trim($_GET['map_type']);
        inner_test
        if(!empty($strCuid) && !empty($_GET['id']) && isset($this->_type_maps[$strMayType])) {
            $strCacheKey = $this->_cuid_skintoken_map_cache_prefix_key .'_'.$strMayType .'_' . $strCuid;
            $intCacheTime = $this->_type_maps[$strMayType]['cache_time'];
            $arrData =  array('time'=> time(), 'id' =>  $strId);
            GFunc::cacheSet($strCacheKey, $arrData, $intCacheTime);
        }

        if(!empty($_GET['url'])) {
            header("Location: " . $_GET['url']);
        }

        return;

    }

}