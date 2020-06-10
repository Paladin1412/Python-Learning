<?php
/**
 *
 * @desc 懒人语料
 * @path("/corpora/")
 */
use models\LazyCorporaNoti;
//use models\LazyCorpora;
use utils\GFunc;
use utils\Util;
use tinyESB\util\ClassLoader;
ClassLoader::addInclude(__DIR__.'/noti');

class LazyCorpora
{
    /**
     * @desc corpora 懒人语料 增量升级
     * @route({"POST", "/incre"})
     * @param({"cks", "$._POST['cks']"}) 需要检测的客户端已安装ids json [{id: "id", version_code: "0"}]
     * @param({"from_ver", "$._GET.from_ver"}) int 升级起始版本
     * @param({"to_ver", "$._GET.to_ver"}) int 升级到到版本，由noti下发（重构后此字段考虑不传，直接下发起始版本到最新的版本的增量部分）
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
       {
            "data": [
                {
                    "id": 53,
                    "name": "懒人语料3",
                    "version_code": 0,
                    "file": "http://a.zip"
                }
            ]
        }
     */
    public function incre($to_ver, $from_ver = 0, $cks = '')
    {
        $cks && $cks = json_decode($cks, true);

        //$res = array('data' => array());
        $res = Util::initialClass(false);
        $lazyCoporaNotiModel = IoCload("models\\LazyCorporaNoti");
        $lazyCoporaModel = Iocload("models\\LazyCorpora");
        if(isset($to_ver)) {
            //老版本通知中心，客户端需要上传升级到的版本
            $corporaIds = $lazyCoporaNotiModel->getCorporaIdsByNoti($from_ver, $to_ver);
        }else {
            //新版本通知中心，客户端不需要上传升级到的版本，下发全部增量
            $corporaIds = $lazyCoporaNotiModel->getCorporaIdsByNotiNew($from_ver);
        }
        $res['data'] = $lazyCoporaModel->getUpgradeCorpora($corporaIds, $cks);

        $noti_conf = GFunc::getConf('Noti');
        
        $unify_conf = UnifyConf::getUnifyConf(GFunc::getCacheInstance(), $noti_conf['properties']['strUnifyConfCachePre'], intval($noti_conf['properties']['intCacheExpired']), $noti_conf['properties']['strUnifyConfResRoot'], Util::getClientIP(), isset($_GET['from']) ? $_GET['from']: '');
        
        $res['switch'] = $unify_conf['tips_switch_lazy'];

        $res['ecode'] = $lazyCoporaNotiModel->getStatusCode() !== 0 ? $lazyCoporaNotiModel->getStatusCode() : $lazyCoporaModel->getStatusCode();
        $res['emsg'] = $lazyCoporaNotiModel->getStatusCode() !== 0 ? $lazyCoporaNotiModel->getErrorMsg() : $lazyCoporaModel->getErrorMsg();
        $res['version'] = intval($lazyCoporaNotiModel->intMsgVer);
        
        return Util::returnValue($res,false,true);
    }
}