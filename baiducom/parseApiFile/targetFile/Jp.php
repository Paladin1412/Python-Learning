<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
use utils\GFunc;


/**
 *
 * jp
 * 说明：连接跳转接口  迁自V4
 *
 * @author zhoubin
 * @path("/jp/")
 */
class Jp
{
    /**
     * memcache缓存默认过期时间(单位: 秒)
     * @var int
     */
    const CACHE_EXPIRED_TIME = 10800;

    /**
     * 签名加密salt
     * @var string
     */
    const SIGN_SALT = 'iudfu(lkc#xv345y82$dsfjksa';


    /**
     * 跳转域名白名单列表
     * @var array
     */
    static $WHITE_LIST = array(
        "baidu.com",
        "dwz.cn",
    );

    /**
     * @desc 获取域名
     * @param $strUrl url
     * @return string
     */
    public function getDoman($strUrl){
        //从URL中获取主机名称
        preg_match('@^(?:http://)?([^/]+)@i', $strUrl, $arrMatches);
        $strHost = $arrMatches[1];

        //获取主机名称的后面两部分
        preg_match('/[^.]+\.[^.]+$/', $strHost, $arrMatches);
        return $arrMatches[0];
    }

    /**
     * @desc 获取签名
     * @param $strUrl 跳转目的url
     * @return string
     */
    public function getSign($strUrl){
        $strSign = '';
        if('' === $strUrl){
            //如果url为空则根据时间生成个签名
            $strSign = sha1(time() . self::SIGN_SALT);
        }else{
            $strSign = sha1($strUrl . self::SIGN_SALT);
        }

        return $strSign;
    }

    /**
     * @desc 获取签名
     * @param $strUrl 跳转目的url
     * @return bool
     */
    public function checkSign(){
        $bolIsSignOk = false;
        //有url参数才会有跳转漏洞
        if( isset($_GET['url']) && '' !== $_GET['url'] ){
            $strUrl = urldecode($_GET['url']);
            if( isset($_GET['sign']) && '' !== $_GET['sign'] ){
                $bolIsSignOk = ($_GET['sign'] === $this->getSign($strUrl));
            }else{
                $strDomain = $this->getDoman($strUrl);
                $bolIsSignOk = in_array($strDomain, self::$WHITE_LIST);
            }
        }else{
            $bolIsSignOk = true;
        }

        return $bolIsSignOk;
    }

    /**
     * @desc 初始化
     * @return
     */
    function __construct(){

        if(!$this->checkSign()){
            header('X-PHP-Response-Code: '. 400, true, 400);
            exit();
        }
    }


     /**
     * @route({"GET",""})
     * @desc 百度框过来的点击处理，根据平台号跳转到不同的下载页面
     * @return
     */
    public function indexAction(){
        $location = 'http://dl.ops.baidu.com/baiduinput_AndroidPhone_1009905b.apk';
        $pl = trim($_REQUEST["pl"]);
        if (empty($pl)) {
            $pl = 'a';
            if (stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X') !== false) {
                $pl = 'i';
            }
        }
        if (substr($pl, 0, 1) == 'i') {
            $location = 'https://itunes.apple.com/cn/app/id916139408';
        }

        //c=jp&url=urlencode(jump_url)
        $strUrl = isset($_REQUEST["url"]) ? trim($_REQUEST["url"]) : '';
        //android and have param url
        if ((substr($pl, 0, 1) !== 'i') && ($strUrl !== '')){
            $location = urldecode($strUrl);
        }

        header("HTTP/1.1 302 Found");
        header("status: 302 Found");
        header("Location: {$location}");
        exit;
    }


     /**
     * @route({"GET","/url"})
     * @desc 百度框过来的点击处理，根据平台号跳转到不同的下载页面
     * @return
     */
    public function urlAction(){

        $location = 'http://dl.ops.baidu.com/baiduinput_AndroidPhone_1009905b.apk';
        $pl =trim($_REQUEST["pl"]);
        $strUrl = trim($_REQUEST["url"]);
        if (empty($pl)) {
            $pl = 'a';
            if (stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X') !== false) {
                $pl = 'i';
            }
        }
        if (substr($pl, 0, 1) == 'i') {

            //保存idfa 对应 下载 channel信息
            $sret = $this->saveIdfaChannelToRes();

            if(!empty($strUrl))
            {
                $location = urldecode($strUrl);
            }
            else
            {
                $location = 'https://itunes.apple.com/cn/app/id916139408';
            }

            //广点通
            $isGDT = !empty($_REQUEST['is_gdt']) ? trim($_REQUEST['is_gdt']) : 0;

            if ($isGDT)
            {
                $resp = array(
                    'ret' => $sret ? 0 : -1,
                    'msg' => '',
                );
                exit(json_encode($resp));
            }
        }

        header("HTTP/1.1 302 Found");
        header("status: 302 Found");
        header("Location: {$location}");
        exit;
    }

    /**
     * 保存idfa 对应 下载 channel信息
     * 统计渠道导入到appstore
     *
     */
    public function saveIdfaChannelToRes(){
        $strFrom = isset($_REQUEST['from']) ? trim($_REQUEST['from']) : '';
        $strIdfa = isset($_REQUEST['idfa']) ? trim($_REQUEST['idfa']) : '';
        $strCallback = isset($_REQUEST['callback']) ? urldecode(trim($_REQUEST['callback'])) : '';
        //$strIdfaResUrl = Util::getResDomain() . '/res/json/input/r/online/resource-idfaStatus/' . $strIdfa;
        //暂时写死访问idfa res

        $arrData = array(
            'status' => '0',
            'channel' => $strFrom,
            'jump_time' => time(),
            'callback' => $strCallback,
            'uid' => $strIdfa
        );

        //广点通参数
        $isGDT = !empty($_REQUEST['is_gdt']) ? trim($_REQUEST['is_gdt']) : 0;

        if($isGDT)
        {
            $arrData['muid'] = $strIdfa = isset($_REQUEST['muid']) ? trim($_REQUEST['muid']) : '';

            $arrData['click_time'] = isset($_REQUEST['click_time']) ? trim($_REQUEST['click_time']) : '';
            $arrData['click_id'] = isset($_REQUEST['click_id']) ? trim($_REQUEST['click_id']) : '';
            $arrData['appid'] = isset($_REQUEST['appid']) ? trim($_REQUEST['appid']) : '';
            $arrData['advertiser_id'] = isset($_REQUEST['advertiser_id']) ? trim($_REQUEST['advertiser_id']) : '';
            $arrData['app_type'] = isset($_REQUEST['app_type']) ? trim($_REQUEST['app_type']) : '';

            $arrData['is_gdt'] = 1;
            $arrData['uid'] = $arrData['muid'];
        }

        //如果没有渠道号及idfa信息则直接返回
        if ( '' === $strFrom || '' === $strIdfa ){
            return false;
        }

        return $this->saveToDb($arrData);
    }

    public function saveToDb($arrData)
    {

        $model = null;
        if(!empty($arrData['is_gdt']))
        {
            $model = IoCload("models\\MuidModel");
        }
        else
        {
            $model = IoCload("models\\IdfaModel");
        }

        return $model->save($arrData);
    }


}
