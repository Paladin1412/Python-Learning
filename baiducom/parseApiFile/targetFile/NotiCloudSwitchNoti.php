<?php
/**
 *
 * @desc 通知中心业务接口--cc云开关
 * @path("/noti_cloudswitch_noti/")
 */
use tinyESB\util\ClassLoader;
use utils\Util;
use utils\ErrorMsg;
use utils\ErrorCode;
use utils\GFunc;

ClassLoader::addInclude(__DIR__.'/noti');

class NotiCloudSwitchNoti
{
    /** 输出数组格式 */
    private $out = array();

    /** @property apc内存缓存 */
    private $apc_cache;

    /** @property 通知中心请求资源缓存key pre*/
    private $strNotiSwitchNotiForCloudCachePre;

    /** @property 默认缓存时间 */
    private $intCacheExpired;

    /***
     * 构造函数
     * @return void
     */
    public function  __construct() {
        //输出格式初始化
        $this->out = Util::initialClass(false);
    }

    /**
     * @desc cc云开关信息
     * @route({"GET", "/info"})
     * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本,不需要客户端传
     * @param({"strPlatform", "$._GET.platform"}) $strPlatform platform,不需要客户端传
     * @param({"strFrom", "$._GET.from"}) $strFrom 初始渠道号,不需要客户端传
     * @param({"strUid", "$._GET.uid"}) $strUid uid,不需要客户端传
     * @param({"strCuid", "$._GET.cuid"}) $strCuid cuid,不需要客户端传
     * @param({"intSp", "$._GET.sp"}) $intSp 联网类型
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "ecode": 1,
            "emsg": "",
            "data": 1,
            "version": 123

        }
     */
    public function getNotiCloudSwitch($strVersion, $strPlatform, $strFrom = '', $strUid = null, $strCuid = null, $intSp = 12)
    {
        $conf = GFunc::getConf('Noti');
        $strUnifyConfResRoot = $conf['properties']['strUnifyConfResRoot'];
        $arrTestUid = $conf['properties']['arrTestUid'];
        //统一开关配置
        $arrUnifyConf = UnifyConf::getUnifyConf($this->apc_cache, $this->strNotiSwitchNotiForCloudCachePre, $this->intCacheExpired, $strUnifyConfResRoot, Util::getClientIP(), $strFrom);

        //cc信息
        $ccInfo = NotiCloudSwitch::getCloudSwitchData($intSp, $strUid, $strVersion, $strPlatform, $arrTestUid, $arrUnifyConf, $this->apc_cache, $strCuid);

        $this->out['data'] = $ccInfo;

        //code
        $this->out['ecode'] = UnifyConf::getCode();
        //msg
        $this->out['emsg'] = UnifyConf::getCodeMsg();

        return Util::returnValue($this->out,false);
    }

}
