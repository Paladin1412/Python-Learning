<?php
/**
 *
 * @desc ios spotlight
 * @path("/spotlight/")
 */
use tinyESB\util\Verify;
use utils\DbConn;
use utils\GFunc;

class Spotlight
{
    //数据库链接
    private $db;

    /**
     * @desc spotlight plist
     * @route({"GET", "/plist"})
     * @param({"version", "$._GET.plist_version"}) int
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * ，不需要客户端传，从加密参数中获取
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "data": {
                "plist_version": "1440379258",
                "url": "http://bs.baidu.com/emot-pack-test/%2Fspotlight_1440379258.plist?sign=MBO:iwImKL1Q98ibe5rybNr9CGRah0aZXyM:tZCeWOBwWpYhhyhi3V2aO0u%2B4yo%3D",
                "hash": "8d85aecbc342f8a3d1b8785646fc4508",
                "client_min_version": "5.7.0.0"
            }
        }
     */
    public function plist($version = 0, $ver_name = '5.7.0.0', $plt = 'i1') {
        $args = func_get_args();
        $key = __CLASS__ . __METHOD__ . implode('_', $args);
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            $res = array(
                'data' => (object) array(),
            );
            $client_min_version = $this->getVersionIntValue($ver_name);

            $sql = 'select version as plist_version,url,hash,client_min_version from input_spotlight where platform ="%s" and version>%n and client_min_version<=%n order by version desc limit 1';
            $sp = $this->getDb()->queryf($sql, $plt, $version, $client_min_version);
            if(!empty($sp['0']))
            {
                $d = $sp[0];

                $cmv = $d['client_min_version'];
                $d['client_min_version'] =
                intval($cmv/1000000) . '.' . intval((($cmv%1000000)/10000)) . '.' . intval(($cmv%10000)/100) . '.' . intval($cmv%100);
                $res = array(
                    'data' => $d,
                );
            }

            GFunc::cacheSet($key, $res, Gfunc::getCacheTime('10mins') * 2);
        }

        return $res;
    }

    /**
     * @return db
     */
    public function getDB()
    {
        return DbConn::getXdb();
    }

        /**
     * 返回输入法版本数值
     * 如5.1.1.5 5010105
     *
     * @param
     *      参数名称：$version_name
     *      是否必须：是
     *      参数说明：version name
     *
     * @param
     *      参数名称：$digit
     *      是否必须：是
     *      参数说明：位数
     *
     *
     * @return string
     */
    protected function getVersionIntValue($version_name, $digit = 4){
        $version_name = str_replace('-', '.', $version_name);

        $val = 0;
        $verson_digit = explode('.', $version_name);
        for ($i = 0; $i < $digit; $i++){
            $digit_val = 0;
            switch ($i){
                case 0:
                    $digit_val = intval($verson_digit[$i]) * 1000000;
                    break;
                case 1:
                    $digit_val = intval($verson_digit[$i]) * 10000;
                    break;
                case 2:
                    $digit_val = intval($verson_digit[$i]) * 100;
                    break;
                case 3:
                    $digit_val = intval($verson_digit[$i]);
                    break;
                default:
                    break;
            }

            $val = $val + $digit_val;
        }

        return $val;
    }
}