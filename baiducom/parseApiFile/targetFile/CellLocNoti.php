<?php
/**
 *
 * @desc 通知中心业务接口--本地地理词库推送
 * @path("/cellloc_noti/")
 */

use tinyESB\util\ClassLoader;   
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

ClassLoader::addInclude(__DIR__.'/noti');

class CellLocNoti
{
    /** 基类对象 */
    private $objBase;
    
    /** 输出数组格式 */
    private $out = array();
    
    /** @property 通知中心请求资源缓存key pre*/
    private $strCellLocNotiCachePre;

    /** @property 默认缓存时间 */
    private $intCacheExpired;
    
    /**
     * 构造函数
     * @return void
     */
    public function  __construct() {
        $this->objBase = new NotiBase();
        
        //输出格式初始化
        $this->out = Util::initialClass(false);
    }
    
    /**
    * @desc 地理词库推送
    * @route({"POST", "/info"})
    * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform, 不需要客户端传
    * @param({"strVersion", "$._GET.version"}) $strVersion version, 输入法版本,不需要客户端传
    * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
    * @param({"intEnv", "$._GET.env"}) $intEnv env加密版本,不需要客户端传,当前最新版本值为2
    * @param({"strCellLocInfo", "$._POST.cell_loc"}) $strCellLocInfo cell_loc, 客户端POST地理词库JSON数据
    * @param({"strLocationInfo", "$._POST.location"}) $strLocationInfo location, 客户端POST基站信息JSON数据
    * @param({"strPostionInfo", "$._POST.position"}) $strPostionInfo position, 客户端POST位置JSON数据，包含sdk获取的Apinfo
    * @return({"header", "Content-Type: application/json; charset=UTF-8"})
    * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": 1,
            "version": 123
            ]
        }
    */
    public function getCellLoc($strPlatform, $strVersion, $strCuid, $intEnv = null, $strCellLocInfo = "", $strLocationInfo = "", $strPostionInfo = "")
    {
        //只有上传基站信息的才推送本地化词库
        $arrLocationInfoDecoded = $this->decodeLocationInfo($intEnv, $strLocationInfo);
        $arrLocationInfoFormated = $this->formatLocationInfo($arrLocationInfoDecoded);
        if (!isset ( $arrLocationInfoFormated['location'] ) || empty ( $arrLocationInfoFormated['location'])) {
            $this->out['ecode'] = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($this->out['ecode'],'基站信息不正确',true);
        }

        //通过position信息分析城市
        $strUsePostPositionInfo = json_decode($strPostionInfo,true);
        $strApinfo = $strUsePostPositionInfo['apinfo'];
        $arrCityInfo = Util::getPosition($strPlatform, $strVersion, $strCuid, $strApinfo);

        //get redis obj
        $redis = GFunc::getCacheInstance();

        $conf = GFunc::getConf('Noti');
        $strV4HttpRoot = $conf['properties']['strV4HttpRoot'];

        $arrCellLocInfo = array();
        $arrCellLocInfo['cell_loc'] = json_decode($strCellLocInfo,true);

        $data = NotiCellLoc::getNoti($this->objBase, $redis, $this->intCacheExpired, $strV4HttpRoot, $arrCellLocInfo, $arrCityInfo, $strCuid);

        $this->out['data'] = $this->objBase->checkArray($data);

        //version
        $this->out['version'] = Util::getCurrentTime();

        return Util::returnValue($this->out,false);
    }

    /**
     * 基站信息格式化
     * @param $arrLocationData array 用户解密后的基站信息
     *
     * @return array
     */
    private function formatLocationInfo($arrLocationData){
        $arrLocationInfo = array();
        if (null !== $arrLocationData) {
            $arrLocationInfo['location'] = array ();
            if (isset ( $arrLocationData['mcc'] )) {
                $arrLocationInfo['location'] ['mcc'] = intval ( $arrLocationData['mcc'] );
            }
            if (isset ( $arrLocationData['mnc'] )) {
                $arrLocationInfo['location'] ['mnc'] = intval ( $arrLocationData['mnc'] );
            }
            if (isset ( $arrLocationData['lac'] )) {
                $arrLocationInfo['location'] ['lac'] = intval ( $arrLocationData['lac'] );
            }
            if (isset ( $arrLocationData['cid'] )) {
                $arrLocationInfo['location'] ['cid'] = intval ( $arrLocationData['cid'] );
            }
        }
        return $arrLocationInfo;
    }

    /**
     * 用户基站信息解密
     * @param $env int 加密版本,当前最新版本值为2
     * @param $strLocationInfo string 用户基站信息
     *
     * @return array 解密之后的用户基站信息
     */
    public function decodeLocationInfo($env, $strLocationInfo){
        $LocationInfoDecoded = json_decode($strLocationInfo,true);
        if ($env == 2) {
            if (is_array($LocationInfoDecoded)) {
                foreach ($LocationInfoDecoded as $key => $value) {
                    $decode_result = \B64Decoder::decode($value, 0);
                    if ($decode_result !== false) {
                        $LocationInfoDecoded[$key] = $decode_result;
                    }
                }
            } else {
                $decode_result = \B64Decoder::decode($LocationInfoDecoded, 0);
                if ($decode_result !== false) {
                    $LocationInfoDecoded = $decode_result;
                }
            }
        }
        return $LocationInfoDecoded;
    }
}
