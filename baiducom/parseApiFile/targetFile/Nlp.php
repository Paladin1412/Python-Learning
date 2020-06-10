<?php

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\ErrorCode;
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

/**
 *
 *
 *
 * @author daixi
 * @path("/nlp/")
 */

class Nlp
{
    /**
     * nlp nerplus 功能
     * @route({"POST","/nerplus"})
     * @param({"query", "$._POST.query"})
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function  nerlplus($query)
    {
        $ret = Util::nerlplus($query);
        if($ret)
        {
            return $ret;
        }
        else
        {
            $data = array();
            $data["status"] = -1;
            return $data;
        }
    }

    /**
     * nlp nerplus 功能
     * @route({"POST","/nerlplusdemo"})
     * @param ({"query", "$._POST.query"})
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function nerlplusdemo($query) {
        $data['results'] = array();
        $arrQuery = json_decode($query, true);
        if (!empty($arrQuery)) {
            $postData = array(
                'handler' => 'NERL',
                'query' => implode("\t", $arrQuery['texts']) 
            );
            $tagRes = Util::request('http://cp01-qa-bu-qa39.cp01.baidu.com:8000/run', 'POST', http_build_query($postData));
            if (!empty($tagRes['body'])) {
                $arrTagRes = json_decode($tagRes['body'], true);
                foreach ($arrTagRes as $arrTagResK => $arrTagResV) {
                    if (!empty($arrTagResV[0])) {
                        $data['results'][] = $arrTagResV[0];
                    }
                }
            }
        }
        
        return $data;

    }

    /**
     * 分词词性标注专名识别
     * @route({"POST","/lexer"})
     * @param ({"query", "$._POST.query"})
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function lexer($query='') {
        if (empty($query)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        $result = Util::initialClass();
        $header = array(
            'pathinfo'   => 'nlpc_lexer_112',
            'querystring'=> 'username=changminqiang&app=nlpc_2018091119340610616&access_token=6717e4bff8505a98b52732acec7225d2&encoding=utf8',
        );
        $text = array(
            'texts' => json_decode($query, true),
        );
        $ret = ral("ral_nlpc", "post", json_encode($text), rand(), $header);

        if ($ret === false) {
            return ErrorCode::returnError('NET_ERROR', '接口请求失败');
        } else {
           $ret = json_decode($ret, true);
           if (is_array($ret) && 0 == $ret['status']) {
                $result['data'] = $ret['results'];
           } else {
               return ErrorCode::returnError('DATA_FORMAT_ERROR', '接口返回错误');
           }
        }

        return Util::returnValue($result);
    }
}