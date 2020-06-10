<?php
/**
 * 剪切板
 * User: chendaoyan
 * Date: 2019/7/24
 * Time: 10:55
 */

use MongoDB\BSON\UTCDateTime;
use utils\ErrorCode;
use utils\GFunc;
use utils\Util;

/**
 * 完全访问
 * @path("/ClipBoard/")
 */
class ClipBoard {
    private static $cachePre = 'input_clipboard';
    /**
     * 获取剪切板token
     * http://agroup.baidu.com/inputserver/md/article/1954375
     * @route({"POST", "/getClipBoardInfo"})
     * @param({"content", "$._POST.content"}) 写到剪切板的内容
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getClipBoardInfo($content='') {
        $result = Util::initialClass();
        if (empty($content)) {
            $content = file_get_contents('php://input');
            //如果content-type是json，tinyesb底层做了限制(tinyESB/rsapi/Request:42)，传过来的body内容必须是json格式的，
            //我们这里需要传字符串，所以就多了两个引号，在此就必须decode才行
            if (false !== strpos($_SERVER['CONTENT_TYPE'], 'application/json')) {
                $content = json_decode($content, true);
            }
        }
        //判断内容是否正确，防止恶意写入，存入的数据
        $b64Content = bd_B64_Decode(trim($content), 0);
        if (empty($b64Content)) {
            return ErrorCode::returnError(100, '内容参数错误');
        }
        //用redis存储token与数据的对应关系
        $token = md5(microtime() . __CLASS__ . __METHOD__);
        $tokenKey = __CLASS__ . self::$cachePre . 'clipboard_data_token' . $token;
        $cacheSetResult = GFunc::cacheSet($tokenKey, $content, GFunc::getCacheTime('2hours') * 12 * 30);
        if (false === $cacheSetResult) {
            return ErrorCode::returnError(302, '写入剪切板内容失败');
        }
        $result['data']->token = bd_B64_Encode('baiduinputoperational:' . $token, 0);

        return Util::returnValue($result);
    }

    /**
     * 通过token获取具体数据
     * http://agroup.baidu.com/inputserver/md/article/1954430
     * @route({"POST", "/getDataByToken"})
     * @param({"token", "$._POST.token"}) 下发的token
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getDataByToken($token) {
        $result = Util::initialClass(false);
        $decodeToken = bd_B64_Decode(trim($token), 0);
        $arrToken = explode(':', $decodeToken);
        if (!isset($arrToken[1])) {
            return ErrorCode::returnError(100, '参数错误');
        }
        $tokenKey = __CLASS__ . self::$cachePre . 'clipboard_data_token' . $arrToken[1];
        $data = GFunc::cacheGet($tokenKey);
        if (false === $data) {
            return ErrorCode::returnError(300, '获取剪切板内容失败');
        }
        $result['data']['info'] = $data;

        return Util::returnValue($result, false);
    }

}