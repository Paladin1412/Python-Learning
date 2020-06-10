<?php
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
use utils\GFunc;
use utils\ErrorCode;

/**
 *
 * 帐号相关接口 
 * @path("/account/")
 * @author zhoubin05
 */

class Account
{
        
    
    /**
     * 检测bduss是否有效, 根据pass确认，status != 0就认为bduss无效，可以直接提示用户重登
     * @route({"POST","/bdusschk"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function validCheck()
    {
        //输出格式初始化
        $rs = Util::initialClass();
        
        if (!(isset($_FILES['ukey']) && (0 === $_FILES['ukey']['error'])) ) {
            return ErrorCode::returnError('PARAM_ERROR', 'ukey error');
        } 
         
        $bduss_encode = file_get_contents( $_FILES ['ukey']['tmp_name'] );
        $bduss_decode = bd_AESB64_Decrypt ( $bduss_encode ); //解码
        if (false === $bduss_decode) {
            return ErrorCode::returnError('BDUSS_ERR', 'ukey decode error');
        }
        
        $bduss = unpack ( 'a' . strlen ($bduss_decode), $bduss_decode );
        $userinfo = Util::getUserInfoByBduss($bduss[1]);
        
        if ( isset($userinfo['status']) && (0 === intval($userinfo['status']))
        && isset($userinfo['uid']) && (0 !== intval($userinfo['uid'])) ){

            $strUsername = mb_convert_encoding($userinfo['username'],'utf-8','gbk');
            $arrData = array('info' => array(
                'uid' => $userinfo['uid'],
                'username' => !empty($strUsername) ? $strUsername : '',
            ));
            $rs['data'] = $arrData;
            //保存uid
            return $rs;
        } 
        else{
            return ErrorCode::returnError('BDUSS_ERR', 'bduss unvalid');
        }
          
    }
    
    
    
    
    
}