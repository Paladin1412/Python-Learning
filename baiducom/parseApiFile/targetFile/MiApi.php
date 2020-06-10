<?php

/**
 * @desc 
 * 
 * @author jiangyang05
 * @path("/mi_api/")
 * MiApi.php UTF-8 2018-1-26 14:45:55
 */
use tinyESB\util\Logger;
use utils\Bos;
use utils\GFunc;
use utils\Util;

class MiApi {

    private $intQps; //限制QPS
    private $cache; //缓存实例
    private $bucket; //bos bucket
    private $objPath; // bos objpath
    private $path; // bos objpath 下路径
    private $imgResurl; //取图片url

    /**
     * @desc 小米云输入api
     * @route({"POST", "/add"})
     * @param({"strDesc", "$._POST.title"})  配置标题
     * @param({"intShowTimes", "$._POST.show_times"})  展示次数
     * @param({"intFilterType", "$._POST.filter_type"})  过滤类型
     * @param({"strKeyword", "$._POST.keyword"})  关键词
     * @param({"intType", "$._POST.type"})  展示类型
     * @param({"intResType", "$._POST.res_type"})  资源类型
     * @param({"strText", "$._POST.text"})  
     * @param({"strLink", "$._POST.link"})
     * @param({"intTimestamp", "$._POST.timestamp"})
     * @param({"strSign", "$._POST.sign"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * 
     */

    public function add($strDesc = '', $intShowTimes = -1, $intFilterType = 0, $strKeyword = '', $intType = 0, $intResType = 1, $strText = '', $strLink = '', $intTimestamp = 0, $strSign = '') {
        $rs     = array('status' => 0, 'msg' => 'success', 'data' => new stdClass());
        $strApi = '/mi_api/add';
        if (false === $this->checkQps($strApi)) {
            Logger::warning("QPS limit");
            $rs['status'] = 1;
            $rs['msg']    = 'QPS';
            return $rs;
        }
        if ('' === $strDesc || '' === $strKeyword || '' === $strText) {
            Logger::warning("params error POST :" . json_encode($_POST));
            $rs['status'] = 2;
            $rs['msg']    = 'params error';
            return $rs;
        }
        $intShowTimes  = intval($intShowTimes);
        $intFilterType = intval($intFilterType);
        $intType       = intval($intType);
        $intResType    = intval($intResType);
        $intTimestamp  = intval($intTimestamp);
        $imgName       = '';
        if (abs(time() - $intTimestamp) > 60) {
            Logger::warning("timeout timestamp:" . $intTimestamp);
            $rs['status'] = 2;
            $rs['msg']    = 'timeout';
            return $rs;
        }
        if (1 === $intResType && !isset($_FILES['img'])) {
            Logger::warning("img not exists");
            $rs['status'] = 2;
            $rs['msg']    = 'img not exists';
            return $rs;
        }
        if (0 === $intShowTimes || -1 > $intShowTimes) {
            $rs['status'] = 2;
            $rs['msg']    = 'show_times error';
            return $rs;
        }
        if ($intFilterType !== 0 && $intFilterType !== 1) {
            $rs['status'] = 2;
            $rs['msg']    = 'filter_type error';
            return $rs;
        }
        if ($intType !== 0 && $intType !== 1) {
            $rs['status'] = 2;
            $rs['msg']    = 'type error';
            return $rs;
        }
        if ($intResType !== 0 && $intResType !== 1) {
            $rs['status'] = 2;
            $rs['msg']    = 'res_type error';
            return $rs;
        }
        if (1 === $intResType) {
            $img     = $_FILES['img'];
            $imgName = $img['name'];
            if (500 * 1024 < $img['size']) {
                $rs['status'] = 3;
                $rs['msg']    = 'img too large';
                return $rs;
            }
            $arrImgsize = getimagesize($img['tmp_name']);
            if ($arrImgsize[0] > 1800 || 280 !== $arrImgsize[1]) {
                $rs['status'] = 3;
                $rs['msg']    = 'img size error';
                return $rs;
            }
            if ('image/png' !== $arrImgsize['mime']) {
                $rs['status'] = 3;
                $rs['msg']    = 'img type error';
                return $rs;
            }
        }
        $sign = $this->getSign($strDesc, $intShowTimes, $intFilterType, $strKeyword, $intType, $intResType, $strText, $strLink, $imgName, $intTimestamp);
        if (false === $sign) {
            Logger::warning("secretkey error");
            $rs['status'] = 4;
            $rs['msg']    = 'secretkey error';
            return $rs;
        }
        $strSign = strtolower($strSign);
        if ($strSign !== $sign) {
            Logger::warning("sign error" . json_encode($_POST));
            $rs['status'] = 5;
            $rs['msg']    = 'sign error';
            return $rs;
        }
        $signUsed = $this->cache->get($sign);
        if ($signUsed === null || $signUsed === false || $signUsed === '0') {
            $signIncr = $this->cache->incr($sign);
        }
        if ($signUsed > 0 || $signIncr > 1 ||$signIncr===null) {
            Logger::warning("sign used");
            $rs['status'] = 6;
            $rs['msg']    = 'sign used';
            return $rs;
        }
        $this->cache->expire($sign, 3600);
        $res = $this->addRes($strDesc, $intShowTimes, $intFilterType, $strKeyword, $intType, $intResType, $strText, $strLink);
        if (is_array($res)) {
            $rs['data'] = $res;
        }
        else {
            $rs['status'] = 7;
            $rs['msg']    = $res;
        }
        return $rs;
    }

    /**
     * @desc 检验是否超过qps
     * @param $api  api路径
     * @return bool
     * 
     */
    public function checkQps($api = '') {
        if ('' === $api) {
            return false;
        }
        $strKey     = md5($api);
        $iSuccessed = false;
        $eSuccessed = false;
        $objRedis   = $this->cache;
        $res        = $objRedis->get($strKey);
        if (null === $res || $this->intQps > $res) {
            $rs = $objRedis->incr($strKey, $iSuccessed);
            if (false === $iSuccessed) {
                return false;
            }
            if (1 === $rs) {
                $objRedis->expire($strKey, 1, $eSuccessed);
                if (false === $eSuccessed) {
                    return false;
                }
            }
            if ($this->intQps < $rs) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * 
     * @param type $strDesc
     * @param type $intShowTimes
     * @param type $intFilterType
     * @param type $strKeyword
     * @param type $intType
     * @param type $intResType
     * @param type $strText
     * @param type $strLink
     * @return string
     */
    public function addRes($strDesc, $intShowTimes, $intFilterType, $strKeyword, $intType, $intResType, $strText, $strLink) {
        $extra     = null;
        $getHeader = array(
            'pathinfo'    => '/res/json/input/autoIncrement/cloud_res',
            'querystring' => 'timestamp=' . time(),
        );
        $rs        = ral("res_service", "get", null, $extra, $getHeader);
        if (!isset($rs['auto_id']) || !is_numeric($rs['auto_id'])) {
            return 'id_error';
        }
        $putHeader = array(
            'pathinfo'     => '/res/json/input/autoIncrement/cloud_res',
            'Content-Type' => 'application/json',
        );
        $rsi       = ral("res_service", "put", array('auto_id' => $rs['auto_id'] + 1), $extra, $putHeader);
        if (!isset($rsi['keyword']) || $rsi['keyword'] != 'cloud_res') {
            return 'id_error';
        }
        $strPost               = '{"id":1,"owner":1,"desc":"","show_times":-1,"filter_id":56,"keys":[],"cand":{"pos_1":{"type":0,"text":"","contents":[""],"res_url":"","link":"","icon":{"icon_240":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_320":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_480":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_720":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}}}},"pos_2":{"type":0,"text":"","contents":[""],"res_url":"","link":"","icon":{"icon_240":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_320":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_480":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_720":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}}}},"pos_3":{"type":0,"text":"","contents":[""],"res_url":"","link":"","icon":{"icon_240":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_320":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_480":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_720":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}}}},"pos_4":{"type":0,"text":"","contents":[""],"res_url":"","link":"","icon":{"icon_240":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_320":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_480":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_720":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}}}}},"lenovo":[{"type":0,"text":"","contents":[""],"res_url":"","link":"","icon":{"icon_240":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_320":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_480":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}},"icon_720":{"url":"","grid":{"l":0,"r":0,"t":0,"b":0}}}}]}';
        $arrPost               = json_decode($strPost, 1);
        $arrPost['id']         = $rs['auto_id'] + 1;
        $arrPost['desc']       = '[小米配置]' . $strDesc;
        $arrPost['show_times'] = $intShowTimes;
        if ($intFilterType === 1) {
            $arrPost['filter_id'] = 58;
        }
        $arrKeyword         = explode("\n", $strKeyword);
        $arrPost['keys'][0] = count($arrKeyword) > 1 ? implode(' 50000' . "\n", $arrKeyword).' 50000' : $arrKeyword[0] . ' 50000';
        foreach ($arrKeyword as $v) {
            $arrTempContent = explode('(', $v);
            $arrContent[]   = $arrTempContent[0];
        }
        $resType = 4;
        $imgUrl  = '';
        if ($intResType === 0) {
            $imgUrl  = 'http://res.mi.baidu.com/imeres/ime-res/cloud_res/files/76FC957AEA98E7B632C76D043C14426B-20180126235208-rSGFlt.png';
            $imgGrid = json_decode('{"l": 22,"r": 31,"t": 21,"b": 51}', true);
        }
        if ($intResType === 1) {
            $resType = 7;
            $imgUrl  = $this->uploadToBos($_FILES['img']['tmp_name']);
            if (false === $imgUrl) {
                return 'upload_img_error';
            }
        }
        if ($intType === 0) {
            $arrPost['cand']['pos_1']['type']                    = $resType;
            $arrPost['cand']['pos_1']['text']                    = $strText;
            $arrPost['cand']['pos_1']['link']                    = $strLink;
            $arrPost['cand']['pos_1']['contents']                = $arrContent;
            $arrPost['cand']['pos_1']['icon']['icon_720']['url'] = $imgUrl;
            if (isset($imgGrid)) {
                $arrPost['cand']['pos_1']['icon']['icon_720']['grid'] = $imgGrid;
            }
        }
        if ($intType === 1) {
            $arrPost['lenovo']['0']['type']                    = $resType;
            $arrPost['lenovo']['0']['text']                    = $strText;
            $arrPost['lenovo']['0']['link']                    = $strLink;
            $arrPost['lenovo']['0']['contents']                = $arrContent;
            $arrPost['lenovo']['0']['icon']['icon_720']['url'] = $imgUrl;
            if (isset($imgGrid)) {
                $arrPost['lenovo']['0']['icon']['icon_720']['grid'] = $imgGrid;
            }
        }
        $postHeader = array(
            'pathinfo'     => '/res/json/input/r/offline/cloud_res/',
            'Content-Type' => 'application/json; charset=UTF-8',
        );
        $rsp        = ral("res_service", "post", $arrPost, $extra, $postHeader);
        if (isset($rsp['keyword'])) {
            $filter_type = array('小米全量', '小米白名单');
            $type        = array('候选资源', '联想资源');
            $res_type    = array('电影类', '图片类');
            return array('title'       => $strDesc,
                'show_times'  => $intShowTimes,
                'filter_type' => $filter_type[$intFilterType],
                'keyword'     => $strKeyword,
                'type'        => $type[$intType],
                'res_type'    => $res_type[$intResType],
                'text'        => $strText,
                'link'        => $strLink,
                'img'         => $imgUrl
            );
        }
        else {
            return 'add res failed';
        }
    }

    /**
     * @desc 获取 secretkey
     * @return string|bool
     */
    public function getSecretKey() {
        $pathinfo     = "/res/json/input/r/online/mi_api_secretkey/";
        $strCachekey  = md5($pathinfo);
        $strSecretKey = '';
        $rs           = \utils\GFunc::cacheGet($strCachekey);
        if (false === $rs || null === $rs) {
            $header = array(
                'pathinfo'    => $pathinfo,
                'querystring' => 'onlycontent=1'
            );
            $rs     = ral("res_service", "get", null, null, $header);
            \utils\GFunc::cacheSet($strCachekey, $rs);
        }
        if (count($rs) !== 1) {
            return false;
        }
        $res = current($rs);
        if (!isset($res['switch']) || 0 === $res['switch'] || !isset($res['secretkey']) || '' === $res['secretkey']) {
            return false;
        }
        $strSecretKey = $res['secretkey'];
        return $strSecretKey;
    }

    /**
     * 
     * @param type $strDesc
     * @param type $intShowTimes
     * @param type $intFilterType
     * @param type $strKeyword
     * @param type $intType
     * @param type $intResType
     * @param type $strText
     * @param type $strLink
     * @param type $strImg
     * @param type $intTimestamp
     * @return string
     */
    public function getSign($strDesc, $intShowTimes, $intFilterType, $strKeyword, $intType, $intResType, $strText, $strLink, $strImg, $intTimestamp) {
        $params = func_get_args();
        $str    = '';
        foreach ($params as $value) {
            $str .= $value;
        }
        $strSecretkey = $this->getSecretKey();
        if (false === $strSecretkey) {
            return false;
        }
        $str .= $strSecretkey;
        return hash('sha256', $str);
    }

    /**
     * 从文件上传到bos
     * 
     * @param  $file 文件
     * @return string|bool
     */
    public function uploadToBos($file) {
        if (!isset($file)) {
            return false;
        }
        $objBosClient = new Bos($this->bucket, $this->objPath);
        $filename     = $this->path . 'mi_api_' . md5(time() . rand(10000, 99999)) . '.png';
        $uploadRes    = $objBosClient->putObjectFromFile($filename, $file, array('contentType' => 'image/png'));
        if (1 != $uploadRes['status']) {
            return false;
        }
        return $this->imgResurl . $filename;
    }

    /**
     * @desc 获取小米垫高机型列表
     * http://agroup.baidu.com/inputserver/md/article/1898644
     * @route({"GET", "/getModeList"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     *
     */
    public function getModeList() {
        $cacheKey = md5(__Class__ . __FUNCTION__ . '_mi_white_high_cachekey');
        $result = GFunc::cacheGet($cacheKey);
        if (false === $result || null === $result) {
            $result = Util::initialClass();
            $list = GFunc::getRalContent('mi_white_high');
            if (empty($list)) {
                $result['data']->list = array();
            } else {
                $model = array();
                foreach ($list as $listK => $listV) {
                    if (!empty($listV['Model'])) {
                        $model = array_merge($model, $listV['Model']);
                    }
                }

                $result['data']->list = $model;
            }
            $result['version'] = GFunc::getResVersion();

            GFunc::cacheSet($cacheKey, $result);
        }

        return Util::returnValue($result, true, true);
    }

}
