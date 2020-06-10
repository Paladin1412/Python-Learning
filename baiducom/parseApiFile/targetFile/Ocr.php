<?php
/**
 *
 * @desc ocr 图像识别
 * @path("/ocr/")
 * @author zhoubin05
 */
use utils\Util;
use utils\GFunc;
use utils\InnerToken;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\ErrorCode;
use utils\CustLog;

class Ocr
{

    /** @property 收集ocr badcase 开关 */
    private $boolSwitch;
    
    /**
     * @desc ocr 通用ocr
     * @route({"POST", "/com"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
        "log_id": 3309240423,
        "direction": 0,
        "language": 3,
        "words_result": [
            {
                "location": {
                    "left": 133,
                    "top": 0,
                    "width": 142,
                    "height": 27
                },
                "words": "d度输入法"
            },
            {
                "location": {
                    "left": 175,
                    "top": 19,
                    "width": 78,
                    "height": 17
                },
                "words": "交的表达"
            },
            {
                "location": {
                    "left": 25,
                    "top": 128,
                    "width": 98,
                    "height": 24
                },
                "words": "形、宁"
            },
            {
                "location": {
                    "left": 29,
                    "top": 154,
                    "width": 291,
                    "height": 30
                },
                "words": "在(第二届手机输入法创新大赛》中,提提案《字"
            },
            {
                "location": {
                    "left": 165,
                    "top": 181,
                    "width": 71,
                    "height": 23
                },
                "words": "第二名"
            },
            {
                "location": {
                    "left": 50,
                    "top": 209,
                    "width": 98,
                    "height": 16
                },
                "words": "发此状,以资鼓励"
            }
        ],
        "words_result_num": 6
    }
     */
    public function ocrCom()
    {
        //wiki : http://wiki.baidu.com/pages/viewpage.action?pageId=201087402#OCRAPI文档-通用OCR
        $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-ocr/v1/ocr/general';
        
       
        $salt = 'JSIv984@947!0x*49!++1';
        
        if(empty($_POST['image']) || empty($_POST['sign']) || empty($_POST['tm'])) {
            return array('error_code' => 999999, 'error_msg'=>'error req params','log_id'=>'10000000001');
        }
       
        $sign = md5($_POST['image'].$salt.$_POST['tm']);  
        
        if(strtoupper($_POST['sign']) !=  strtoupper($sign)) {
           return array('error_code' => 999991, 'error_msg'=>'error sign','log_id'=>'10000000001'); 
        }
        
        $_POST['access_token'] = $this->generateToken();
       
        $arrResult = Util::request($url,'POST',$_POST, 5);
        $result = array();
        if(isset( $arrResult['http_code'] ) && intval($arrResult['http_code'] ) === 200 ){
            $result = json_decode($arrResult['body'], true);
            if(intval($result['error_code']) == 4) {
                //限流错误则直接输出503， 方便日志统计。 （客户端收到503的表现是显示『网络错误』）
                header('HTTP/1.1 503 Service Unavailable');  
                exit;
            }
        } else {
            //ocr非200状态则直接输出502， 方便日志统计。 （客户端收到502的表现是显示『网络错误』）
            $this->logError($url, $arrResult);
            header('HTTP/1.1 502 Bad Gateway');
            exit;
        }
        return $result;
        
    }
     /**
     * @desc ocr 通用ocr
     * @route({"POST", "/common"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function common()
    {
        $result = Util::initialClass();
        //wiki : http://wiki.baidu.com/pages/viewpage.action?pageId=201087402#OCRAPI文档-通用OCR
        $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-ocr/v1/ocr/';
        $arrParams=['image','tm','sign'];
        $salt = 'JSIv984@947!0x*49!++1';
        $arrData=[];
        foreach($arrParams as $strValue){
            if(isset($_POST[$strValue])){
                $arrData[$strValue]=$_POST[$strValue];
            }
        }
        if(empty($arrData['image']) || empty($arrData['sign']) || empty($arrData['tm'])) {
            $result['ecode']=999999;
            $result['emsg']='error req params';
            return $result;
        }
       
        $sign = md5($arrData['image'].$salt.$arrData['tm']);  
        
        if(strtoupper($arrData['sign']) !=  strtoupper($sign)) {
            $result['ecode']=999991;
            $result['emsg']='error sign';
            return $result;
        }
        
        $arrData['access_token'] = $this->generateToken();
        $arrOcrlist=['general','card_detector'];
        $arrPhasterList=[];
        $arrRs=[];
        foreach ($arrOcrlist as $v){
                $arrPhasterList[$v]=new PhasterThread("utils\Util::request",array($url.$v,'POST',$arrData,5));
        }
        foreach ($arrPhasterList as $pk=>$pv){
            $arrRs[$pk]=$pv->join();
        }
        $rs=[];
        foreach($arrRs as $ak=>$av){
            if(isset( $av['http_code'] ) && intval($av['http_code'] ) === 200 ){
                $rs[$ak] = json_decode($av['body'],true);
                if(intval($rs[$ak]['error_code']) == 4) {
                    if($ak==='general'){
                        header('HTTP/1.1 503 Service Unavailable');  
                        exit;
                    }
                }
            }else{
                if($ak==='general'){
                    //ocr非200状态则直接输出502， 方便日志统计。 （客户端收到502的表现是显示『网络错误』）
                    $this->logError($url.$ak, $arrRs[$ak]);
                    header('HTTP/1.1 502 Bad Gateway');
                    exit;
                }else{
                    $rs[$ak]=array('error_code' => 999993, 'error_msg'=>'server error','log_id'=>'10000000001');
                }
            }
        }
        $result['data']=$rs;
        return Util::returnValue($result);
    }

    
    
     /**
     * @desc ocr 证件识别
     * @route({"POST", "/certificate"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function certificate()
    {
        $result = Util::initialClass();
        //wiki : http://wiki.baidu.com/pages/viewpage.action?pageId=201087402#OCRAPI文档-通用OCR
        
        $arrParams=['image','tm','sign','type'];
        $salt = 'JSIv984@947!0x*49!++1';
        $arrData=[];
        foreach($arrParams as $strValue){
            if(isset($_POST[$strValue])){
                $arrData[$strValue]=$_POST[$strValue];
            }
        }
        if(empty($arrData['image']) || empty($arrData['sign']) || empty($arrData['tm']) || $arrData['type']==null) {
            $result['ecode']=999999;
            $result['emsg']='error req params';
            return $result;
        }

        $sign = md5($arrData['image'].$salt.$arrData['tm']);

        if(strtoupper($arrData['sign']) !=  strtoupper($sign)) {
            $result['ecode']=999991;
            $result['emsg']='error sign';
            return $result;
        }
        $arrOcrlist=array(['idcard'=>['id_card_side'=>['front','back']]],'bankcard','driving_license','vehicle_license','passport','business_card','table');

        $type=intval($arrData['type']);
        unset($arrData['type']);
        if(!isset($arrOcrlist[$type])){
            $result['ecode']=999992;
            $result['emsg']='type not exits';
            return $result;
        }
        $arrData['access_token'] = $this->generateToken();
        $api=$arrOcrlist[$type];
        $arrRalList=[];
        $arrRs=[];
        $pathinfo = '/rest/2.0/vis-ocr/v1/ocr/';
        $strType='';
        if(is_array($api)){//身份证正反两面识别
            $u= key($api);
            $api= current($api);
                foreach($api as $k=>$kv){
                    foreach($kv as $kkv){
                        $arrRalList[$u.'_'.$kkv]=array('ocrservice', "post", array_merge($arrData,[$k=>$kkv]), null, array('pathinfo'    => $pathinfo.$u));
                    }
                }
            }else{//其他证件识别
                if($type===1){//银行卡调用ai开放平台接口
                    $strPathInfo='https://aip.baidubce.com/rest/2.0/ocr/v1/'.$api;
                    $strAccessToken=$this->generateAiToken();
                    if($strAccessToken===false){
                        $result['ecode']=999995;
                        $result['emsg']='get access_token error';
                        return $result;
                    }
                    $arrPostData   = array(
                        'image'    => $arrData['image'],
                    );
                    $arrData=array('access_token'=>$strAccessToken);
                    $orpFetchUrl = new \Orp_FetchUrl();
                    $httpproxy = $orpFetchUrl->getInstance(array('timeout' =>5000));
                    $arrRsOcr=$httpproxy->post($strPathInfo.'?'. http_build_query($arrData),$arrPostData);
                    $arrRs=false;
                    if($httpproxy->http_code()===200 && $httpproxy->errno()===0){
                        $arrRs=json_decode($arrRsOcr);
                    }
                    $result['data']=$arrRs;
                    if(false===$arrRs || null===$arrRs){
                        $result['data']=(object)array('error_code' => 999994,
                                          'error_msg'=>'bank card error',
                                          'log_id'=>10000000001
                        );
                    }
                    $result['data']->type= $api;
                    return Util::returnValue($result);
                }else{
                    $strType=$api;
                    $arrRalList[$api]=array('ocrservice', "post", $arrData, null, array('pathinfo'    => $pathinfo.$api));
                }
        }
        $arrRs=ral_multi($arrRalList);
        foreach ($arrRs as $ak=>&$av){
            if($av!==false && $av!==null){
                $av= json_decode($av);
                if(($type===0 && $av->image_status==='normal')||$type !==0){
                    if($ak==='idcard_front'){
                        if(isset($av->words_result)&&isset($av->words_result->出生)&&isset($av->words_result->出生->words)){
                            $formatedDate=date("Y年m月d日", strtotime($av->words_result->出生->words));
                            if($formatedDate!='1970年01月01日'){
                                $av->words_result->出生->words=$formatedDate;
                            }
                        }
                    }
                    $result['data']=$av;
                    $result['data']->type=$ak;
                    break;
                }
            }
        }
        if(!isset($result['data']->type)){

            $result['data']=(object)array('error_code' => 999994,
                                          'error_msg'=>'id card error',
                                          'log_id'=>isset($arrRs['idcard_front']->log_id)?$arrRs['idcard_front']->log_id:10000000001,
                                          'type'=>$strType===''?'idcard_front':$strType
            );
        }
        if($type===6){
            $objExcel = IoCload('models\\ExcelModel');
            $result['data']->form_content_status=false;
            $result['data']->form_content="";
            $form_content=$objExcel->outputAsBase64String($result['data'],1);
            $tempResult=$result['data']->forms_result;
            if($form_content!==false){
                $result['data']->form_content_status=true;
                $result['data']->form_content=$form_content;
                unset($result['data']->forms_result);
            }
            if($this->boolSwitch){
                $objOcrModel = IoCload('models\\OcrModel');
                $strFilename=md5($arrData['image']);
                $strFile= base64_decode($arrData['image']);
                $date=date("Y-m-d");
                if($result['data']->forms_result_num===null){
                    $objOcrModel->uploadToBos($strFilename.'.png',$strFile,'failure/'.$date,array('contentType'=>'image/png'));
                }else{
                    $objOcrModel->uploadToBos($strFilename.'.png',$strFile,'success/'.$date,array('contentType'=>'image/png'));
                    $objOcrModel->uploadToBos($strFilename.'.json',json_encode($tempResult),'success/'.$date);
                }
            }
            unset($tempResult);
        }
        return Util::returnValue($result);
    }

    
    
    
    /**
     * @desc ocr genroel 图像类型识别
     * @route({"POST", "/gentest"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
    "log_id":2309140521,
    "result_num":5,
    "result":[
            {
                "class_name":"非自然图像-文字图-证件翻拍",
                "probability":0.22500851750374
            },
            {
                "class_name":"非自然图像-文字图-单票",
                "probability":0.075736418366432
            },
            {
                "class_name":"人造物体-生活用品-个护化妆-药品保健品",
                "probability":0.063253596425056
            },
            {
                "class_name":"非自然图像-文字图-芯片卡",
                "probability":0.047403838485479
            },
            {
                "class_name":"人造物体-生活用品-文具-纸本便签",
                "probability":0.033877979964018
            }
        ]
    }
     */
    public function gentest()
    {
        //wiki : http://wiki.baidu.com/pages/viewpage.action?pageId=201087402#OCRAPI文档-通用OCR
        //$url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/classify/general';
        //$url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v1/object_detect';
        $url = 'http://openapi-test.jpaas-matrixoff00.baidu.com/rest/2.0/vis-classify/v1/classify/general?access_token=21.21cda41bd9739ce5a083f3326f64b610.2592000.1469180474.1686270206-11101624'; //demo only for test
       
        $salt = 'JSIv984@947!0x*49!++1';
        
        $_POST['access_token'] = '21.21cda41bd9739ce5a083f3326f64b610.2592000.1469180474.1686270206-11101624';
       
        if(!isset($_FILES['img'])) {
            return array('error_code' => 999992, 'error_msg'=>'img empty!','log_id'=>'10000000002');
        }
        
        $image = base64_encode(file_get_contents($_FILES['img']['tmp_name']));
        $_POST['image'] = $image;
       
        $arrResult = Util::request($url,'POST',$_POST, 2);
        $result = array();
        if(isset( $arrResult['http_code'] ) && intval($arrResult['http_code'] ) === 200 ){
            $result = json_decode($arrResult['body'], true);
        }
        return $result;
        
    }
    
    /**
     * token生成函数
     * @return string 
     */
    public function generateToken()
    {
        //5分钟缓存的token会出现token失效的问题，改为每次都用新的token
        //$cache_key = 'v5api_orc_access_token';
        //$token = GFunc::cacheGet($cache_key);
        //if(false === $token)
        //{
            //wiki: http://wiki.baidu.com/pages/viewpage.action?pageId=202011570
            $time = time();
            $appid = 9579958;
            $sk = 'Wq8Seh9NmrCMUwluFWclNQGyGd1rW9cx';
            $id = '0';
            $sign = md5($time . $id . $appid . $sk);
            $token =  "11.{$sign}.{$time}.{$id}-{$appid}";  
            //服务端访问河图的token有效时间默认是10分钟，所以这里设置5分钟缓存，保证token是永不过期的
        //    GFunc::cacheSet($cache_key, $token, GFunc::getCacheTime('5mins'));     
        //}
        
        return $token;
        
    }
    /**
     * ai开放平台token生成函数
     * @return string
     */
    public function generateAiToken()
    {
        $cache_key = 'v5api_orc_access_token';
        $token = GFunc::cacheGet($cache_key);
        if(false === $token|| null ===$token)
        {
            $ak='rLDmY9ikkVbmlLAWnGw71GhY';
            $sk = 'OvehtGZOEpRKWG6gSlUFLl8wis4xZZn5';
            $strPathInfo='https://aip.baidubce.com/oauth/2.0/token';

            $arrData   = array(
                'grant_type'    => 'client_credentials',
                'client_id'     => $ak,
                'client_secret' => $sk,
            );
            $orpFetchUrl = new \Orp_FetchUrl();
            $httpproxy = $orpFetchUrl->getInstance(array('timeout' =>5000));
            $arrRsToken=$httpproxy->post($strPathInfo,$arrData);
            $arrToken=false;
            if($httpproxy->http_code()===200 && $httpproxy->errno()===0){
                $arrToken= json_decode($arrRsToken,1);
            }
            if($arrToken===false||!(isset($arrToken['access_token'])&&isset($arrToken['expires_in']))){
                return false;
            }
            $token=$arrToken['access_token'];
            GFunc::cacheSet($cache_key, $token, $arrToken['expires_in']-10);//防止缓存还没失效access_token 已经失效
        }

        return $token;

    }

    /**
    * 记录ocr接口错误日志
    * 包括发起的请求和返回的结果
    * @param $url 请求到ocr的地址
    * @param $result ocr的返回结果
    * @return null
    */
    private function logError($url, $result) {
        $cache = GFunc::getCacheInstance();  
        $key = 'ocr_gengerl_api_limit_'.date("YmdH");  //以小时为单位
        if($cache->incr($key) > 5 ) { //每小时最多记录3个
            return; //大于规定次数不记录日志，日志太大不是适合都保存
        }
        
        $with_header_and_post = true; //是否记录头信息和post参数
        $content = 'ocr api error: url ['. $url .'] resp data [' . serialize($result) . '] ';
        if($with_header_and_post) {
            $content .= ' client req header ['. serialize($this->getHeaderInfo()) .'] ';
            $content .= ' POST ['. serialize($_POST) .'] ';
        }
        
        Logger::warning($content);
    }
    
    /**
    * 获取客户端请求头信息
    * @return array
    */
    private function getHeaderInfo() {
        
        if (!function_exists('getallheaders')) 
        { 
            $headers = array(); 
            foreach ($_SERVER as $name => $value) 
            { 
                if (substr($name, 0, 5) == 'HTTP_') 
                { 
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
                } 
            } 
            return $headers; 
        } else {
            return getallheaders();
        }
    }
    
    
    /**
     * @desc ocr 通用ocr
     * @route({"POST", "/general"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function general()
    {
        $arrCateMap = array(
            'baby' => array(
                '婴儿', '儿童',
            ),
            'cat' => array(
                '猫','豹','猫科-其他','狮','虎',
            ),
            'dog' => array(
                '豺','鬣狗','狼','犬','狐',
            ),
        );
        
       
        $result = Util::initialClass();
        //wiki : http://hetu.baidu.com/api/platform/api/show?apiId=821
        $url = 'http://inner.openapi.baidu.com/rest/2.0/vis-classify/v3/classify/general';
        //$url = 'http://openapi-test.jpaas-matrixoff00.baidu.com/rest/2.0/vis-classify/v3/classify/general';
       
        //for debug $_POST['image'] = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
        
        $salt = 'JSIv984@947!0x*49!++1';
        if(empty($_POST['image']) || empty($_POST['sign']) || empty($_POST['tm'])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR);
        }
       
        $sign = md5($_POST['image'].$salt.$_POST['tm']);  
        
        if(strtoupper($_POST['sign']) !=  strtoupper($sign)) {
            return ErrorCode::returnError(ErrorCode::SIGN_ERROR);
        }
        
        $_POST['access_token'] = $this->generateToken();
       
        $arrResult = Util::request($url,'POST',$_POST, 5);
        
        $arrRs = array();
        if(isset( $arrResult['http_code'] ) && intval($arrResult['http_code'] ) === 200 ){
            $arrRs = json_decode($arrResult['body'], true);
            if(intval($arrRs['error_code']) == 4) {
                //限流错误
                CustLog::write('ocr_general_access_limit_error', '');
                return ErrorCode::returnError(ErrorCode::NET_ERROR);
            }
        } else {
            //ocr非200状态
            CustLog::write('ocr_general_net_error', '');
            return ErrorCode::returnError(ErrorCode::NET_ERROR);
        }
        
        $strCate = 'default';
        
        //提取class_name的最后一级和最后两级，并和$arrCateMap比较，落在$arrCateMap中就返回对应的分类
        if(!empty($arrRs['result'][0])) {
            $strCateName = $arrRs['result'][0]['class_name'];  
            $arrCate = explode('-',$strCateName);
            $count = count($arrCate);
            $last = ($count - 1) < 0 ? 0 : ($count - 1);
            $strLast = $arrCate[$last];
            
            $strLastTwo = null;
            if($count >= 2) {
                $two = $count - 2;
                $strLastTwo = $arrCate[$two] . '-' . $arrCate[$last];
            }
            
            foreach($arrCateMap as $k => $v) {
                if(in_array($strLast, $v) || in_array($strLastTwo, $v)) {
                    $strCate = $k;
                    break;    
                }
            }
        }
        
        $data = array('cate' => $strCate);
        $result['data'] = $data;
        
        return Util::returnValue($result);
        
    }
    

}