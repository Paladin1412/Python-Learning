<?php

use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use utils\Util;

/**
 *
 * @desc 外部访问ksarch redis
 * @path("/callksarch/")
 */


class CallKsarch{
    /** @property */
    public $strSignSalt;
    
    /**  @property */
    private $storage;  
    
    /**
     * @desc
     * @route({"POST", "/"})
     * 
	 *  {
     *      "cmd": "set",//命令  根据需要自己添加
     *      "data": "value",//数据内容的base64_encode, 数据结构不做限制如set {"key": "key","value": "value"}
     *      "sign": "data sign"//签名
     *  }
	 * 
     * @return({"body"})
        {
            "result":"result"//和KsarchRedisV2保持一致
        }
     */
    public function call(){
        //限制内网使用
        $strClientIp = Util::getClientIP();
        Verify::isTrue( (substr($strClientIp, 0, 3) === '10.') || (substr($strClientIp, 0, 4) === '172.') || ($strClientIp === '127.0.0.1'), new BadRequest("forbid") );
        
        //避免传递header "Content-Type: text/plan"
        $strContent = file_get_contents ( 'php://input' );
        //上传文件
        if(isset($_FILES['file'])){
            if (isset ( $_FILES['file']['error'] ) && intval ( $_FILES['file']['error'] ) === 0 && isset ( $_FILES['file']['tmp_name'] )) {
                $strFileName = $_FILES['file']['tmp_name'];
                $strContent = file_get_contents($strFileName);
            }
        }
         
        Verify::isTrue( ($strContent !== null) , new BadRequest("param empty"));
        
        $arrContent = json_decode($strContent, true);
        Verify::isTrue( isset($arrContent['cmd']) && isset($arrContent['data']) && isset($arrContent['sign']) , new BadRequest("param empty"));
        //验证签名
        $strDecodeData = base64_decode($arrContent['data']);
        Verify::isTrue( ($arrContent['sign'] === $this->getSign($strDecodeData)) , new BadRequest("sign wrong"));
        
        $arrData = json_decode($strDecodeData, true);
        
        $arrResult = array('result' => null);
        switch ($arrContent['cmd']){
            case 'get':
                //{"key": "key"} 
                Verify::isTrue( isset($arrData['key']) , new BadRequest("param empty"));
                $arrResult['result'] = $this->storage->get($arrData['key']);
                break;
            case 'set':
                //{"key": "key","value": "value", "expire":7200} value需要base64_encode
                Verify::isTrue( isset($arrData['key']) && isset($arrData['value']) && isset($arrData['expire']), new BadRequest("param empty"));
                $strValue = base64_decode($arrData['value']);
                $arrResult['result'] = $this->storage->set($arrData['key'], $strValue, $arrData['expire']);
                break;
            case 'incr':
                //{"key": "key"}
                Verify::isTrue( isset($arrData['key']) , new BadRequest("param empty"));
                $arrResult['result'] = $this->storage->incr($arrData['key']);
                break;
            default:
                break;     
        }
        
        return $arrResult;
    }
    
    /**
     * @desc 获取签名
     * @param $strData 签名数据
     * @return string
     */
    public function getSign($strData){
        $strSign = sha1($strData . $this->strSignSalt);
        return $strSign;
    }
}