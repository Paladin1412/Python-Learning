<?php
/**
 *
 * @desc 云输入短地址
 * @path("/s/")
 */

use utils\ErrorCode;
use utils\Util;
use utils\GFunc;
use utils\CustLog;


class S
{
    /**
     * @desc xxx
     * @route({"GET", "/"})
     * @param({"address", "$.path[1]"}) string $address
     * @param({"cuid", "$._GET.cuid"}) cuid
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
       {
     * }
     */
    public function shortaddr($address = '', $cuid = '')
    {
        $address = trim($address);
        
        $path = 'short';
        $query = "id=" . $address;
        
        //set header
        $arrHeader = array(
            'pathinfo' => $path,
            'querystring' => $query,
        );

        $strUrl = ral("cloudshort", "get", null, null, $arrHeader);
        if($strUrl === false) {
            return ErrorCode::returnError('PARAM_ERROR', 'Not found');
        }
        
        $strUrl = trim($strUrl);
        $arrLog = array(
            'url' => $strUrl,
            'address_id' => $address,
            'cuid' => trim($cuid),
            'time' => time(),
        );
        
        //add b2log when 302
        CustLog::write('cloud_short_address', $arrLog);
        
        header('Location:' . $strUrl, true, 302);
        exit;
    }
}