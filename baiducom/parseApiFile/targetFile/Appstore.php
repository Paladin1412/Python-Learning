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
use utils\KsarchRedis;
use utils\Util;
use utils\GFunc;
use utils\DbConn;

require_once __DIR__.'/utils/CurlRequest.php';
require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';

/**
 *
 * appstore
 * 说明：精品相关接口
 *
 * @author zhoubin
 * @path("/appstore/")
 */
class Appstore
{

    /**  @property */
    private $storage;
    
    
    /** @property v5 */
    private $domain_v5;
    
    
    /**
     * 精品列表处于的广告区域
     * @var int
     */
    const ADZONE = 15;
    
    /**
     * 海纳缓存key前缀  后缀只有ios和android两种
     * @var string
     */
    const HAINA_CACHE_KEY_PREFIX = 'ime_v5_hainai_list_skip_first_';
    
    /**
     * 海纳缓存时间标记key前缀  后缀只有ios和android两种 (对应海纳缓存）
     * @var unknown
     */
	const HAINA_CACHE_TIMESTAMP_KEY_PREFIX = 'ime_v5_haina_cache_expire_time_';
	
	
	/** @property 缓存默认过期时间(单位: 秒) */
	private $intCacheExpired;
	
	
	/**
	 *
	 * per page count
	 * @var int
	 */
	const PER_PAGE_COUNT = 8;
	
	/**
	 *
	 * description word count
	 * @var int
	 */
	const DESC_WORD_COUNT = 30;
	
	/**
	 *
	 * subhead word count
	 * @var int
	 */
	const SUBHEAD_WORD_COUNT = 18;
	
	/**
	 *
	 * name word count
	 * @var int
	 */
	const NAME_WORD_COUNT = 15;
	
	/**
	 *
	 * not recommend flag
	 * @var int
	 */
	const NOT_RECOMMEND_FLAG = 2000;
	
	/**
	 *
	 * memcache 精品应用page prefix
	 * @var string
	 */
	const CACHE_APP_STORE_PAGE_PREFIX = 'ime_api_v5_app_store_page_i108_';
	
	/**
	 *
	 * memcache 精品应用page prefix
	 * @var string
	 */
	const CACHE_HAINA_MERGER_KEY = 'ime_api_v5_app_store_haina_i108_';
    
	/**
	 *
	 * 海纳密钥
	 * @var string
	 */
	const HAINA_KEY = 'baidur2o0s1e3qaq123465';
	
	/**
	 *
	 * 海纳api key
	 * @var string
	 */
	const HAINA_API_KEY = '1006423c';
	
	/**
	 *
	 * 海纳 url
	 * @var string
	 */
	const HAINA_URL = 'http://cp.mcp.baidu.com/?r=InterfaceHLAction';
	    
	/**
	 *
	 * os android ios
	 *
	 */
	private $strOs;
	
	/**
	 * 输入法版本号
	 * @var string
	 */
	private $version;
	
	/**
	 * 平台 ios / android
	 * @var string
	 */
	private $plt;
	
	/**
	 * @var string
	 * 系统ROM版本
	 */
	public $rom;
	
	/**
	 * @var string
	 * android平台正序imei
	 *
	 */
	public  $imei;
	
	/**
	 * 强制更新缓存
	 * @var unknown
	 */
	private $cc;
	

	/**  @property */
	private $strResDomain;
	
	/**
	 *
	 * 百通密钥
	 * @var string
	 */
	const BAITONG_KEY = 'baidur2o0s1e3qaq123465';
	
	/**
	 *
	 * 百通api key
	 * @var string
	 */
	/**  @property */
	private $baitong_api_key ;
	
	
	/**
	 * 自有app列表缓存前缀
	 * @var unknown
	 */
	const CACHE_IME_APP_LIST = 'ime_v5_ime_list_cache_prefix_';
	
	/**
	 * app源及排序列表缓存前缀
	 * @var unknown
	 */
	const CACHE_APP_SORT_LIST = 'ime_v5_app_sort_list_';
	
	/**
	 * app源开关缓存
	 * @var unknown
	 */
	const CACHE_APPSTORE_SRC_SWITCH = 'ime_v5_appstore_src_switch_';
	
	/**
	 * 总列表最大条数
	 * @var unknown
	 */
	private $max_list_num = 80;
	
	/**
	 * 手助cps app列表缓存前缀
	 * @var unknown
	 */
	const CACHE_CPS_APP_LIST = 'ime_v5_cps_list_cache_prefix_';
	
	
	/** @property cpd数据地址 */
	private $cpd_data_url;
	
	//空页填充，默认2， 背景：百通和cpd数据都需要用户维度数据来获取列表，故只能以用户为单位做缓存，  
	//原因：由于/v5/boutique_list_ads/list?  向/v5/appstore/list/? 请求会有超时时间(1500ms)，而appstore要向多个源请求数据，1500ms一般都会超时
	//(nginx已经由boutique  rewrite到了appstore, 1.5秒ral超时不存在了，但接入过多实时源依然可能造成appstore接口超时)
	//所以当appstore在没有建立完缓存前，客户端boutique_list_ads的前1，2页数据时由于ral超时会拿到空数据（容错），    客户端的现象就是如果1，2页可能会不显示。插入空页可以保证程序执行的时间
	private $empty_page_fill = 0;
	
	/** @property({"deafult":"@IpToCity"})*/
	private $ip_to_city;

	/**
	 * 手助自然源
	 * @var string
	 */
	const SZ_NATURAL_BOARD_URL = 'http://m.baidu.com/api?action=board';
	
	//const SZ_NATURAL_BOARD_URL =  'http://qa19.app.baidu.com:8080/api?action=board';  //联调环境   使用此地址获取到的列表数据中的download_url其实是线上的，也要把域名替换为http://qa19.app.baidu.com:8080
	
	/**
	 * 手助自然源渠道号
	 * @var string
	 */
	private $sn_from = '1014091e';
	
	/**
	 * 手助自然源token  
	 * @var string
	 */
	private $sn_token = 'bdsjshurufa';
	
	/**
	 * 手助自然源AES密钥
	 * @var string
	 */
	private $sn_aes_secret = '7d27663b021f114f7a359249';
	
	/**
	 * 手助自然源AES加密iv
	 * @var string
	 */
	private $sn_aes_iv = '55bc9ad09a5fd040';
	
	/**
	 * 自然源结果数组
	 * @var array(1 = > array() , 2 = > array())
	 */
	private $sn_data = array();
	
	
	/**
	 * 源数据集合
	 * @var array
	 */
    private $src_data = array();
    
	/**
	 * 资源映射表 实时源靠后
	 * @var array
	 */
	private $src_map = array(
	    'ime' => array('func' => 'getAppListWithRmdSort' , 'args' =>  30), //
	    'hn' => array('func' => 'getHaiNaList' ), //海纳数据无分页参数
	    'szcps' => array('func' => 'getCpsList' ,'args' =>  30) ,
	    'bt' => array('func' => 'getBaiTongList', 'args' => 30 ),
	    'szcpd' => array('func' => 'getCpdList', 'args' => 30), //cpd等同于baitong数据，暂由后台屏蔽
	    'sn_1' => array('func' => 'getSznaturlList' ,'args' => array('num' => 30 , 'board_id' => 1)), //num一次最多60 , board_id(榜单号 1~8)
	);
	
	/**
	 * 源去重数组
	 * @var unknown
	 */
	private $src_unique  = array('apk_name' => array(), 'name' => array());
	
	/**
	 *
	 * memcache 精品应用bannerkey
	 * @var string
	 */
	const CACHE_APP_STORE_BANNER_KEY = 'ime_api_v5_app_store_banner_i108_';
	
	/**
	 *
	 * as get app detail
	 * @var strring
	 */
	const AS_DETAIL_URL = 'http://m.baidu.com/api?action=search&from=1010184n&token=shurufa&type=app&docid=${id}&rn=10&pn=0&apilevel=4&format=json';
	//const AS_DETAIL_URL = 'http://m.baidu.com/api?action=search&from=testiD&token=test&type=app&docid=${id}&rn=10&pn=0&apilevel=4&format=json';
	
	/**
	 *
	 * id replace
	 * @var string
	 */
	const ID_REPLACE = '${id}';
	
	
	/**
	 * 类只会在无缓存时才会被实例化, 所以可以在构造方法中连接数据库
	 * @return void
	 */
	function __construct() {
	    $this->strOs = isset($_GET['platform']) ? $this->getPhoneOS($_GET['platform']) : '';
	    $this->plt = $this->strOs === 'android' ? 'android' : 'ios' ;
	    $this->version = isset($_GET['version']) ? $_GET['version'] : '';
	    $this->imei = isset($_GET['cuid']) ? $_GET['cuid'] : '';
	    $this->rom = isset($_GET['rom']) ? $_GET['rom'] : '';
	    $this->cc = false ; //isset($_GET['cc']) && !empty($_GET['cc']) ? true : false;
	}
	
	/**
     * @route({"GET","/list"})
     * @param({"intPage", "$._GET.page"}) 页数 默认1 一页默认8条
     * 获取精品应用列表(整合管理后台推荐应用和海纳应用数据)
     * @return({"body"})
      {
            apps: [
                {
                    id: "77983cf4f308d34fe7865a10a25ae39d",
                    apk_name: "com.achievo.vipshop",
                    name: "唯品会",
                    subhead: "一折秒大牌，正品全特卖",
                    icon: "http://bcscdn.baidu.com/mc-online//exchange/image/1446798465/1446798464511512-512_128_128.png",
                    desc: "唯品会，一家专门做特卖的网站，正品大牌一折起。数千名专业买手...",
                    thum1: "http://bcscdn.baidu.com/mc-online//exchange/image/1446798469/1446798469245480-800应用商店配图01_240_400.jpg",
                    thum2: "http://bcscdn.baidu.com/mc-online//exchange/image/1446798527/1446798526155480-800应用商店配图02_240_400.jpg",
                    version_name: "5.10.2",
                    type: "0",
                    durl: "http://cq02-mic-iptest.cq02.baidu.com:8890/v5/trace?url=http%3A%2F%2Fcp.mcp.baidu.com%2F%3Fr%3DInterfaceHLAction%26m%3Ddownload%26app_key%3D77983cf4f308d34fe7865a10a25ae39d%26secret%3D4a14bb5e2b3f6e071dbb7f02e08c9405%26api_key%3D1006423c%26params%3DNyxKq9pp-okyrwqUkyAiCkpWFYqI4EYdkJm-Cf0qxzY9aVj1kyFiz95hmfIyJEjPkJEikNp-F6kUpEzf4yFqjypVAqNJOPBtQBfPscE789fn4xjkIpAFoYayeqkf4mffqpxbhfy7xYkBJmfc65m_6NrFxo9N5Bi__u2y8_a0-iLcA%250026678878673468578789972537448090317370986934842573567573&sign=4a637409030d06ba3081c53e8f2a9140&rsc_from=hnapp&apk_name=唯品会",
                    size: "22941349",
                    mtime: "0",
                    pub_item_type: null,
                    from: "haina"
                },
                {
                    id: "eccd9f7dc92858b741132fda313130cf",
                    apk_name: "com.ss.android.article.news",
                    name: "今日头条",
                    subhead: "你关心的，才是头条",
                    icon: "http://bcscdn.baidu.com/mc-online//exchange/image/1447150773/1447150773706512_128_128.png",
                    desc: "用户量过3亿的新闻阅读客户端占领AppStore新闻类榜首2...",
                    thum1: "http://bcscdn.baidu.com/mc-online//exchange/image/1447150778/14471507787991_240_400.jpg",
                    thum2: "http://bcscdn.baidu.com/mc-online//exchange/image/1447150790/14471507907422_240_400.jpg",
                    version_name: "5.0.2",
                    type: "0",
                    durl: "http://cq02-mic-iptest.cq02.baidu.com:8890/v5/trace?url=http%3A%2F%2Fcp.mcp.baidu.com%2F%3Fr%3DInterfaceHLAction%26m%3Ddownload%26app_key%3Deccd9f7dc92858b741132fda313130cf%26secret%3D22f413a10fb456f0500f4defaa5a7897%26api_key%3D1006423c%26params%3DNyxKq9pp-okyrwqUkyAiCkpWFYqI4EYdkJm-Cf0qxzY9aVj1kyFiz95hmfIyJEjPkJEikNp-F6kUpEzf4yFqjypVAqNJPOThwBf27cbTJQGcbejFN5xfok4hAt_yRmqiNp-ikk5MDjIX5xq39p-Ukfp1mztt5AqkfO2_8ga4v8_IPv8-A%2500018188378155112896776454249851866525272875110909826825157&sign=abbb0da262dfd07e96e00b43958353cc&rsc_from=hnapp&apk_name=今日头条",
                    size: "15971080",
                    mtime: "0",
                    pub_item_type: null,
                    from: "haina"
                }
            ],
            lastpage: "1",
            count: 2
     }
     */
	public function getList($intPage = 1 ) {
	    
	    $intPage =  intval($intPage);
        $intPage = $intPage <= 0 ? 1 : $intPage;
        
        $strPageCacheKey = self::CACHE_APP_STORE_PAGE_PREFIX . $this->imei . '_';
        //根据每个用户缓存
	    $strCacheKey = $strPageCacheKey . $intPage;
	 
	    $arrPageList = GFunc::cacheGet($strCacheKey);
	
	    if(false !== $arrPageList) {
	        return $arrPageList;
	    }  
  
	    $arrEmptyRt = array('apps' => array(), 'lastpage' => '1' , 'count' => 0);
	    //get page
	    $arrPageList = array();
	    $arrPageList['apps'] = array();
	    $arrMergeList = $this->getMergeList();
	    
	    if(false === $arrMergeList ) {
	        return $arrEmptyRt; //返回空（只影响某个用户的一次请求） 
	    }
	
	    $arrData =  array();
	    $intCachePage = 1;
	    $intPageItemCount = 1;
	    
	    $adi = 0;
	    //数据分页，一次重建所有分页缓存, 防止由于缓存时间不同造成的数据重复, 由于百通数据是竞价产生，分页缓存时间过长可能造成百通下载不计费的问题，故分页缓存过期时间要短
	    foreach ($arrMergeList as $k => $v){
    	    
    	    if(!isset($arrData[$intCachePage]['apps'])) {
    	        $arrData[$intCachePage]['apps'] = array();
    	    }
    	    $arrTmp =  $v;
    	    
    	    if($arrTmp['pub_item_type'] === 'prod_ad') { 
    	        $arrTmp['ad_id'] = 0;
    	        $arrTmp['ad_zone'] = 15;
    	        $ad_pos = $intCachePage - 1 + $adi;
    	        $arrTmp['ad_pos'] = $ad_pos;
    	        $arrTmp['durl'] = $arrTmp['durl'] . "&ad_type=prod_ad&ad_id=0&ad_zone=15&ad_pos={$ad_pos}" ;
    	    }
    	    
    	    array_push($arrData[$intCachePage]['apps'], $arrTmp);
	        
            if(($intPageItemCount % self::PER_PAGE_COUNT) === 0){
                $intCachePage += 1;
                $adi = 0;
            }	        
            
            $intPageItemCount++;
            $adi++;
	    }
	    
	    foreach ($arrData as $k => $v){
	        $arrData[$k]['lastpage'] = "0";
	        $arrData[$k]['count'] = count($v['apps']);
	    }
	    $arrData[count($arrData)]['lastpage'] = "1"; //尾页标记
	    
	    
	    $rtData =  array();
	    if($this->empty_page_fill != 0 ) {
	        for($i = 1 ; $i <= $this->empty_page_fill ; $i++) {
	            $rtData[$i] =  array('apps' => array(), 'lastpage' => '0' , 'count' => 0);
	        }
	    }
	    
	    $x = count($rtData);
	    foreach ($arrData as $v) {
	        $x++;
	        $rtData[$x] = $v;
	    }
	    
    
	    foreach ($rtData as $k => $v){
	        //5分钟缓存  客户端在获取列表时可能会在很短时间内快速请求多页记录，一次重建所有分页数据缓存保证一定程度的数据一致性
	        //同时此缓存是针对每个用户imei做key，长时间缓存可能会占据大量缓存空间，顾做短时缓存
	        GFunc::cacheSet($strPageCacheKey . $k , $v, Gfunc::getCacheTime('5mins'));  
	    }
	    
	    if(!isset($rtData[$intPage])) {
	        //空页缓存
	        GFunc::cacheSet($strPageCacheKey . $intPage , $arrEmptyRt, Gfunc::getCacheTime('5mins')); //5分钟缓存
	        return $arrEmptyRt;
	    }
	   
	    return $rtData[$intPage];

	}
	
	
	/**
	 * @route({"GET","/getHaiNaFirst"})
	 * 提供热词接口用的取海纳随机1个应用数据
	 * @return({"body"})
	 *
	{
        app_key: "d56da061d55e2175bd67901d5f0948be",
        app_name: "凤凰新闻",
        package_name: "com.ifeng.news2",
        version_code: "99",
        version: "4.4.8",
        app_file_size: "11212399",
        category: "软件|资讯阅读",
        true_durl: "/exchange/apk/144654931367579/2_IfengNewsV448_V4.4.8_2558.apk",
        download_url: "http://cp.mcp.baidu.com/?r=InterfaceHLAction&m=download&app_key=d56da061d55e2175bd67901d5f0948be",
        starlevel: "4.30",
        downnum: "400000",
        comment: "",
        app_icon: "http://bcscdn.baidu.com/mc-online//exchange/image/1446549552/1446549552310icon.png",
        sequence: 3
    }
	 */
	public function getHaiNaRandOne() {
	    $hainaList = $this->getHainaData();
	    
	    if( !is_array($hainaList) ||  empty($hainaList)) {
	        return array();
	    }
	    
	    $randNum = rand(0, count($hainaList) - 1);
	    return $hainaList[$randNum];
	    
	}
	
	
	
	
	/**
	 * get haina detail
	 *
	 * @param
	 * 		参数名称：$strDetailUrl
	 *      是否必须：是
	 *      参数说明：detail url
	 *
	 * @return array
	 */
	public function getHainaDetail($detail_url) {
	    
	    $strAppKey = $this->getAppKey($detail_url);
		$strSecret = md5(self::HAINA_KEY . $strAppKey);
		
	    $strListParam = $this->encodeParam('action=show_detail&action_type=1&platform=' . $this->plt);
	   
	    $strListUrl = 'r=InterfaceHLAction&m=get_app_detail&secret=' . $strSecret . '&app_key=' . $strAppKey .  '&api_key=' . self::HAINA_API_KEY . '&params=' . $strListParam;
	    
	    ral_set_pathinfo('');
	    ral_set_querystring($strListUrl);
	    
	    $arrList = ral("haina", 'get', null, rand());
	    
	    if( isset($arrList['flag']) && $arrList['flag'] === 1  && !empty($arrList['data']) ) {
	        return $arrList;
	    } else {
	        return array();
	    }
	    
	}
	
	
	
	/**
	 * 获取指定规格图片地址
	 *
	 *
	 * @param
	 * 		参数名称：$strSrcImageUrl
	 *      是否必须：是
	 *      参数说明：初始URL
	 *
	 * @param
	 * 		参数名称：$strPix
	 *      是否必须：是
	 *      参数说明：像素 如:128_128
	 *
	 * @param
	 * 		参数名称：$str
	 *      是否必须：否
	 *      参数说明：分辨率前置标示，不同的接口可能不同，如海纳：u512_512,  百通：b512_512
	 *
	 *
	 * @param
	 * 		参数名称：$cloud_trans
	 *      是否必须：否
	 *      参数说明：强制云转码
	 *
	 * @return string
	 */
	public function getSpecImageUrl($strSrcImageUrl, $strPix, $str = 'u', $cloud_trans = false) {
	    
	    if(empty($strSrcImageUrl)) {
	        return "";
	    }
	     
	    $strUrl  = str_replace('}', '%7d', str_replace('{', '%7b', $strSrcImageUrl ));
	     
	    //以上都匹配不到说明url可能不支持云转码，对url做云转码处理
	    //图片里含有中文地址urlencode后老的客户端请求云转码时会异常导致不发起下载图片请求,先检测是否有中文
	    if (!preg_match("/[\x7f-\xff]/", $strSrcImageUrl)) {
	        //不含中文则云转码
	        $strTime = time();
	        $strDi = md5('wisetimgkey_noexpire_3f60e7362b8c23871c7564327a31d9d7' . $strTime . $strUrl);
	        $strTransUrl = 'http://timg01.baidu-1img.cn/timg?pa&er&quality=100&size='.$str . $strPix . '&sec=' . $strTime . '&di=' . $strDi . '&src=' . urlencode($strUrl);
	        return $strTransUrl;
	         
	    }
	    
	    return $strUrl;
	    
	}
	
	
	
	/**
	 * 获取自有应用，匹配推荐信息，按照推荐位排序  有缓存
	 * @param number $num 
	 * @return number|multitype:
	 */
	public function getAppListWithRmdSort($num = 35) {
	    
	    //get from cache
	    $strCacheKey = self::CACHE_IME_APP_LIST . $this->plt;
	    
	    $data = GFunc::cacheZget($strCacheKey);
	    
	    if (false !== $data ){
	        
	        $this->src_data['ime'] = $data;
	        return $data;
	    }
	    
	    /*$arrAppList = $this->getAppList($num);
 
	    $arrRmdInfo = $this->getAppRecommendInfo();*/
	    
	    
	    //Edit by fanwenli on 2017-07-28, get info by phaster
	    $arrInfo = $this->getDataFromAppListANDAppRecommendInfo($num);
	    $arrAppList = $arrInfo['arrAppList'];
	    $arrRmdInfo = $arrInfo['arrRmdInfo'];
   
	    $intAppListCount = count($arrAppList);
	    $arrAppFirstList = array();
	    $arrAppSecondList = array();
	    for ($i = 0; $i < $intAppListCount; $i++){
	        $arrAppInfo = $arrAppList[$i];
	        $arrAppInfo['desc'] = $arrAppList[$i]['description'];
	        $arrAppInfo['rmdpos'] = self::NOT_RECOMMEND_FLAG;
	        $arrAppInfo['type'] = '0';
	        if ( in_array(intval($arrAppList[$i]['id']), $arrRmdInfo['recommend']) ){
	            $arrAppInfo['type'] = '1';
	            $arrAppInfo['rmdpos'] = array_search(intval($arrAppList[$i]['id']), $arrRmdInfo['recommend']);
	            array_push($arrAppFirstList, $arrAppInfo);
	        }else{
	            array_push($arrAppSecondList, $arrAppInfo);
	        }
	    }
	    
	    //sort
	    //按照推荐位排序
	    usort($arrAppFirstList, function($a, $b){
	        if ($a['rmdpos'] == $b['rmdpos']){
	            return 0;
	        }
	        return ($a['rmdpos'] > $b['rmdpos']) ? 1 : -1;
	    });
	    
        $arrAppList = array_merge($arrAppFirstList, $arrAppSecondList);
	    
        //去掉排序字段等
	    $c = count($arrAppList);
        for($i = 0; $i < $c; $i++){
            unset($arrAppList[$i]['rmdpos']);
            unset($arrAppList[$i]['os']);
            unset($arrAppList[$i]['ctime']);
            unset($arrAppList[$i]['status']);
            unset($arrAppList[$i]['description']);
            unset($arrAppList[$i]['show_type']);
            unset($arrAppList[$i]['banner_icon']);
            unset($arrAppList[$i]['web_url']);
        }
    
        GFunc::cacheZset($strCacheKey, $arrAppList, $this->intCacheExpired);
        $this->src_data['ime'] = $arrAppList;
        
        
        return $arrAppList;
	    
	}
	
	
	/**
	 * 获取APP推荐信息
	 * @return array
	 */
	public function getAppRecommendInfo() {
	    
	    $strSql = 'select * from app_store_recommend where uid = 1';
	    
	    $conn_getXDB = $this->getXDB();
	    
	    $arrQueryResult = $conn_getXDB->queryf ( $strSql );
	    
	    DbConn::returnPhasterXdb($conn_getXDB);
	    
	    $arrRmdInfo =  is_array($arrQueryResult) && isset($arrQueryResult[0]) ? $arrQueryResult[0] : array();
	    
	    if(0 === count($arrRmdInfo)){
	        $arrRmdInfo['app_rcmd'] = '0';
	        $arrRmdInfo['banner'] = array();
	        $arrRmdInfo['recommend'] = array();
	    }else{
	        $arrRmdInfo['banner'] = json_decode($arrRmdInfo['banner'], true);
	        $arrRmdInfo['recommend'] = json_decode($arrRmdInfo['recommend'], true);
	    }
	
	    return $arrRmdInfo;
	}
	
	
	
	
	/**
	 * 获取自有应用列表信息
	 * 按上传时间降序排列
	 *
	 * @param number $num 
	 * @return array
	 */
	public function getAppList($num = 30) {
	
	    $strSql = "select * from app_store where status = 100  and os ='{$this->plt}' order by id desc limit {$num}";
	 
	    $conn_getXDB = $this->getXDB();
	    
	    $arrAppList = $conn_getXDB->queryf( $strSql );
	    
	    DbConn::returnPhasterXdb($conn_getXDB);
	    
	    $arrAppList = is_array($arrAppList) ? $arrAppList : array();
	    
	    $arrRt = array();
    
	    foreach ($arrAppList as &$arrApp){
	        
	        //广告CPD下载计数判断，超过计数则不下发
	        if(intval($arrApp['cpd_num']) > 0){
	            $cpd_key = "CPD_".strval(self::ADZONE)."_".strval($arrApp['id']);
	            $get_status = null;
	            $dowload_cnt = $this->storage->get($cpd_key, $get_status);
	            
	            if(!$get_status || intval($dowload_cnt) >= intval($arrApp['cpd_num'])){
	                continue;
	            }
	        }
	        
	        
	        $arrApp['app_data_type'] = "ime";
	        array_push($arrRt, $arrApp);
	        
	      
	    }
	    return $arrRt;
	}
	
	
	/**
	 * 获取trace跳转下载地址
	 *
	 * @param
	 * 		参数名称：$strUrl
	 *      是否必须：是
	 *      参数说明：下载地址
	 *
	 * @param
	 * 		参数名称：$strId
	 *      是否必须：是
	 *      参数说明：插件id
	 *
	 *
	 * @return string
	 *
	 */
	public function getTraceUrl($strUrl, $strId, $strName = '', $src_id = "", $cpd_num = 0, $rsc_from = '', $sorder = '') {
	    $strTraceUrl = $this->domain_v5 . 'v5/trace/cpd?url=' . urlencode($strUrl) . '&sign=' . md5($strUrl . 'iudfu(lkc#xv345y82$dsfjksa') . '&rsc_from=' . $rsc_from . '&apk_name=' . urlencode($strId) . '&src_id=' . $src_id . '&cpd_num=' . $cpd_num 
	    . (!empty($sorder) ? '&sorder=' . $sorder : ''  ) 
	    . (!empty($strName) ?  '&rsc_name=' . urlencode($strName) : '') ;
	    return $strTraceUrl;
	}
	
	
	/**
	 * 获取icon的url
	 *
	 *
	 * @param
	 * 		参数名称：$arrAppList
	 *      是否必须：否
	 *      参数说明：app list
	 *
	 * @param
	 * 		参数名称：$strApkName
	 *      是否必须：否
	 *      参数说明：apk name
	 *
	 * @return bool
	 */
	public function getIsHave($arrAppList, $strApkName) {
	    $bolIsHave = false;
	    foreach ($arrAppList as $arrApp){
	        if ($strApkName === $arrApp['apk_name']){
	            $bolIsHave = true;
	            break;
	        }
	    }
	
	    return $bolIsHave;
	}
	
	
	/**
	 * 从指定源获取一条数据
	 * @param string $src_type
	 * @return boolean|array <multitype:, array>
	 */
	public function getOneFromSrc($src_type = 'rand') {
	    
	    if($src_type === 'rand') {
	        
	        foreach ($this->src_map as $k => $v) {
	            $data = $this->getOneFromSrc($k);
	            if(false != $data) {
	                return $data;
	            }
	        }
	        
	        return false;
	        
	    }else {
	        if(!isset($this->src_data[$src_type])) {
	            return false;
	        }
	         
	        $tmp = array();
	         
	        foreach ($this->src_data[$src_type] as &$v) {
	            if(!in_array($v['apk_name'], $this->src_unique['apk_name']) && !in_array($v['name'], $this->src_unique['name'])) {
	                array_push($this->src_unique['apk_name'], $v['apk_name']);
	                array_push($this->src_unique['name'], $v['name']);
	                $tmp = $v;
	                unset($v);
	                return $tmp;
	            }
	        }
	         
	        return false;
	    }
	    
	}
	
	
	
	/**
	 * 合并数据源并排序处理成列表
	 *
	 *	@return array
	 */
	public function getMergeList() {
	    
	    $slist = $this->getAppSortRes();

	    $this->setSrdData($slist);
	     
	    $counter = $this->max_list_num;
	    $arrMergeList = array();
	    
	    $pos = 1; 
	    
	    for($i = 1 ; $i <= $counter ; $i++) {
	        
	        if($slist != false ) {
	            
	            foreach ($slist['sort_list'] as $v) {
	                //获取一条占位数据
	                $data = $this->getOneFromSrc($v['type']);
	                if($data == false) {
	                    //获取不到再从rest_type(剩余占位源)获取一条数据
	                    $data = $this->getOneFromSrc($slist['rest_type']);
	                    if($data == false) {
	                        //都获取不到此条忽略
	                        continue;
	                    } else {
	                        $i++;
	                    }
	                } else {
	                    $i++;
	                }
	                //地址转换
	                $data = $this->transTrace($data, $pos);
	                array_push($arrMergeList, $data);
	                $pos++;
	            }
	            
	            
	            $slist = false;
	        }
	        
	        //走到这里说明后台配置的排序都已经完成,下面开始使用已经获取的到的源数据填充剩余位置
	        $data = $this->getOneFromSrc($slist['rest_type']);
	        if($data != false) { //先使用剩余占用位配置获取
	            $data = $this->transTrace($data, $pos);
	            array_push($arrMergeList, $data);
	            $pos++;
	        } else { //剩余占用位没有则按照src_map中的顺序获取
	            $data = $this->getOneFromSrc();
	            if($data != false) {
	                $data = $this->transTrace($data, $pos);
	                array_push($arrMergeList, $data);
	                $pos++;
	            }
	        }
	        
	        
	    }
	    
	    return $arrMergeList;
	}
	
	/**
	 * 计算当前源数据合计数
	 * @return number
	 */
	public function countSrcData() {
	    $count = 0 ;
	    
	    foreach($this->src_data as $k => $v) {
	        $count += count($v);
	    }
	    return $count;
	}
	
	/**
	 *  填充源数据超过指定(max_list_num)条数, 
	 *  先获取后台排序列表中选择的源，如果这个源不够max_list_num指定的条数则从src_map中的其他源继续获取，已防止用不到的源也去获取，占用处理时间
	 * @param string $sort_list  排序列表
	 */
	public function setSrdData($sort_list =  null) {
	    if($sort_list === null) {
	        $sort_list = $this->getAppSortRes();
	    }
	    
	    $list = array();
	    
	    if($sort_list != false) {
	        $list[$sort_list['rest_type']] = 1;
	        foreach ($sort_list['sort_list'] as $v ) {
	            $list[$v['type']] = 1;
	        }
	    }

	    //根据开关控制源
	    $switch = $this->getSrcSwitchRes();
	    if($switch) {
	        foreach ($switch as $key => $v) {
	            if(isset($this->src_map[$key]) && $v === 0 ) {
	                unset($this->src_map[$key]);
	            }
	        }
	    }
	    
	    
	    $map =  $this->src_map;
	    
	    //Edit by fanwenli on 2017-07-28, set phaster function array
	    $phaster_fuc_arr = array();
	    foreach($map as $k => &$v) {
	        if(isset($list[$k])) {
	            if(isset($v['args'])) {
	                //$this->$v['func']($v['args']);
	                
	                $phaster_fuc_arr[$v['func']] = $v['args'];
	            }else {
	                //$this->$v['func']();
	                
	                $phaster_fuc_arr[$v['func']] = array();
	            }
	            unset($v);
	        }
	    }
	 
	    //不够数就继续获取其他数据源填充
	    foreach ($map as $k => $v) {
	        if($this->countSrcData() < $this->max_list_num) {
	            if(isset($v['args'])) {
	                //$this->$v['func']($v['args']);
	                
	                $phaster_fuc_arr[$v['func']] = $v['args'];
	            }else {
	                //$this->$v['func']();
	                
	                $phaster_fuc_arr[$v['func']] = array();
	            }

	        }
	    }
	    
	    //Edit by fanwenli on 2017-07-28, get these source by phaster
	    if(!empty($phaster_fuc_arr)) {
	        foreach($phaster_fuc_arr as $k => $v) {
	            $$k = new \PhasterThread(array($this,$k),array($v));
	        }
	        
	        foreach($phaster_fuc_arr as $k => $v) {
	            $$k->join();
	        }
	    }
	    
	}
	
	
	
	/**
	 * 转化下载链接为trace地址,同时去除多余的信息
	 * @param unknown $arrApp
	 * @param unknown $pos
	 * @return array()
	 */
	public function transTrace($arrApp, $pos) {
	    
	    switch ($arrApp['app_data_type']) {
	        case 'ime':
	            $arrApp['durl'] = $this->getTraceUrl($arrApp['durl'], $arrApp['apk_name'], $arrApp['name'], $arrApp['id'], $arrApp['cpd_num'], 'imeapp', $pos);
	            break;
	        case 'hn':
	            $strUrl = $arrApp['durl'];
	            $strAppKey = $this->getAppKey($strUrl);
	            $strSecret = md5(self::HAINA_KEY . $strAppKey);
	            $strParams = 'action=download_list&action_type=2&platform=' . $this->strOs . '&download_app_name=' . $arrApp['app_name'] . '&sequence=' . $arrApp['sequence'] . '&imei=' . $this->imei . '&os_version=' . $this->rom . '&timestamp=' . time();
	            $strEncodeParams = urlencode($this->encodeParam($strParams));
	             
	            $strDownUrl = $strUrl . '&secret=' . $strSecret . '&api_key=' . self::HAINA_API_KEY . '&params=' . $strEncodeParams;
	        
	            $arrApp['durl'] = $this->getTraceUrl($strDownUrl, $arrApp['apk_name'], $arrApp['name'], '', '', 'hnapp' ,$pos);
	            break;
	        case 'bt':
	            $arrApp['durl'] = $this->getTraceUrl($arrApp['durl'], $arrApp['apk_name'], $arrApp['name'], '', '', 'btapp', $pos);
	         
	            break;
            case 'szcpd':
                $arrApp['durl'] = $this->getTraceUrl($arrApp['durl'], $arrApp['apk_name'], $arrApp['name'], '', '', 'szcpd', $pos);
            
                break;
            case 'szcps':
                $arrApp['durl'] = $this->getTraceUrl($arrApp['durl'], $arrApp['apk_name'], $arrApp['name'], '', '', 'szcps', $pos);    
                break;
            case 'sn_1':
            case 'sn_2':
            case 'sn_3':
            case 'sn_4':
            case 'sn_5':
            case 'sn_6':
            case 'sn_7':
            case 'sn_8':
                $arrApp['durl'] = $this->getTraceUrl($arrApp['durl'], $arrApp['apk_name'], $arrApp['name'], '', '', 'snapp', $pos);
                break;
	    }
	    
	    unset($arrApp['app_data_type']);
	    unset($arrApp['app_name']);
	    unset($arrApp['sequence']);
	    
	    return $arrApp;
	}
	
	
	
	/**
	 * 获取海纳APP列表，并过滤第一条（用以显示在热词列表首位）, 然后保存在karshredis（如果成功获取且有数据) , 有缓存
	 *
	 * @return mix array|false
	 */
	public function getHaiNaList() {
	   
	    $strTmCacheKey = self::HAINA_CACHE_TIMESTAMP_KEY_PREFIX . $this->plt;
	    
	    $strTimestamp = GFunc::cacheGet($strTmCacheKey); 
	   
	    $strCacheKey = self::HAINA_CACHE_KEY_PREFIX . $this->plt;
	    
	    $strNow = date("YmdH");
	 
	    if(false === $strTimestamp ){ 
	
	        $data = $this->getHainaData();
	        
	        if(is_array($data) && !empty($data) ) { //存到缓存一定有数据
	            GFunc::cacheZset($strCacheKey, $data, Gfunc::getCacheTime('hours'));  
	            GFunc::cacheSet($strTmCacheKey, $strNow, Gfunc::getCacheTime('2hours'));
	            $this->src_data['hn'] = $data;
	            return $data;
	        } else {
	            $this->src_data['hn'] = array();
	            return false; 
	        }
	        
	    }
	    
	
	    if( (!empty($strTimestamp) && $strTimestamp <  $strNow) ) { //被动永久缓存超过一小时就强制更新缓存
	        
	        $data = $this->getHainaData();
	        GFunc::cacheSet($strTmCacheKey, $strNow, Gfunc::getCacheTime('2hours')); //始终更新过期时间，保证1小时内只有1次远端请求
	        
	        if(is_array($data) && !empty($data) ) {
	            GFunc::cacheZset($strCacheKey, $data, Gfunc::getCacheTime('hours'));
	            $this->src_data['hn'] = $data;
	            return $data;
	        } 
	    }
	    
	    $data = GFunc::cacheZget($strCacheKey);
	    $data = false !== $data ? $data : array();
	    $this->src_data['hn'] = $data;
	    
	    return $data;
	    
	     
	}
	
	
	/**
	 * ral海纳接口获取应用列表
	 * @return multitype:|boolean
	 */
	public function getHainaData() {
	    
	    $arrList = array ();
	    //列表是 Md5(密钥+api_key)
	    $strListSecret = md5(self::HAINA_KEY . self::HAINA_API_KEY);
	    $strListParam = $this->encodeParam('action=show_list&action_type=1&platform=' . $this->strOs);
	    $strListUrl = 'r=InterfaceHLAction&m=get_api&secret=' . $strListSecret . '&api_key=' . self::HAINA_API_KEY . '&params=' . $strListParam;
    
	    ral_set_pathinfo('');
	    ral_set_querystring($strListUrl);
	     
	    $arrList = ral("haina", 'get', null, rand());
	    
	    if( isset($arrList['flag']) && $arrList['flag'] === 1  && !empty($arrList['list']) ) {
	   
	        //unique
	        $arrUniqueList = array();
	        $existPakNameList = array();
	        
	        foreach ($arrList['list'] as $arrApp) {
	            if(in_array($arrApp['package_name'], $existPakNameList)) {
	                continue;
	            }
	            
	            $arrAppInfo = array();
	            $arrDetail = $this->getHainaDetail($this->genHainaDetailUrl($arrApp['download_url'], $arrApp['app_name']));
	            
	            $arrAppInfo['id'] = $arrApp['app_key'];
	            $arrAppInfo['apk_name'] = $arrApp['package_name'];
	            $arrAppInfo['name'] = $this->cutStr($arrApp['app_name'], self::NAME_WORD_COUNT);
	            $arrAppInfo['subhead'] = $this->cutStr($arrApp['comment'], self::SUBHEAD_WORD_COUNT);
	            $arrAppInfo['version_name'] = $arrDetail['data']['version'];
	            $arrAppInfo['icon'] = $this->getSpecImageUrl($arrApp['app_icon'] , '128_128');
	            
	            
	            $arrAppInfo['desc'] = $this->cutStr($arrDetail['data']['app_detail']);
	            //海纳默认240 400
	            $arrAppInfo['thum1'] = $arrDetail['data']['show_imgs'][0]['img'];
	            $arrAppInfo['thum2'] = $arrDetail['data']['show_imgs'][1]['img'];
	  
	            $arrAppInfo['type'] = '0';
	            $arrAppInfo['durl'] = $arrApp['download_url'];
	            $arrAppInfo['size'] = $arrApp['app_file_size'];
	            $arrAppInfo['mtime'] = '0';
	            $arrAppInfo['pub_item_type'] = $arrDetail['data']['pub_item_type'];
	            $arrAppInfo['from'] = 'haina';
	            
	            $arrAppInfo['app_data_type'] = 'hn';
	            
	            //海纳下载链接生产用
	            $arrAppInfo['app_name'] = $arrApp['app_name'];
	            $arrAppInfo['sequence'] = $arrApp['sequence'];
	          
	            array_push($arrUniqueList, $arrAppInfo);
	            array_push($existPakNameList,$arrApp['package_name']);
	        }
	        
	        return $arrUniqueList;
	        
	         
	    }
	    
	    return false;
	}
	
	
	
	
	/**
	 * 生成详情地址
	 *
	 *
	 * @param
	 * 		参数名称：$strUrl
	 *      是否必须：是
	 *      参数说明：url
	 *
	 *
	 * @param
	 * 		参数名称：$strAppName
	 *      是否必须：是
	 *      参数说明：app name
	 *
	 * @return string
	 */
	public function genHainaDetailUrl($strUrl, $strAppName) {
		$strAppKey = $this->getAppKey($strUrl);
		$strSecret = md5(self::HAINA_KEY . $strAppKey);
		$strDetailParam = $this->encodeParam('action=show_detail&action_type=1&platform=' . $this->strOs . '&download_app_name=' . $strAppName);
		$strDetailUrl = self::HAINA_URL . '&m=get_app_detail&secret=' . $strSecret . '&app_key=' . $strAppKey . '&api_key=' . self::HAINA_API_KEY . '&params=' . $strDetailParam;
	
		return $strDetailUrl;
	}
	
	/**
	 * 获取随机数
	 *
	 *
	 * @param
	 * 		参数名称：$strUrl
	 *      是否必须：是
	 *      参数说明：url
	 *
	 *
	 * @return string
	 */
	public function getAppKey($strUrl) {
	    $arrPara = array();
	    $arrSplitParameter = explode('&',end(explode('?',$strUrl)));
	    foreach($arrSplitParameter as $strVal){
	        $arrPair = explode('=',$strVal);
	        $arrPara[$arrPair[0]] = $arrPair[1];
	    }
	
	    return $arrPara['app_key'];
	}
	
	/**
	 * encode 参数
	 *
	 *
	 * @param
	 * 		参数名称：$strInput
	 *      是否必须：是
	 *      参数说明：encode string
	 *
	 *
	 * @return string
	 */
	public function encodeParam($strInput) {
	    $strInput = bd_B64_encode($strInput, 0);
	    return $strInput;
	}
	
	/**
	 * 根据平台号获取手机操作系统类型
	 * @param $platform string 手机平台号
	 *
	 * @return string (ios, symbian, mac, android)
	 */
	public function getPhoneOS($platform) {
	    $platform = strtolower($platform);
	    if (substr($platform, 0, 1) === 'i') {
	        return 'ios';
	    } elseif (substr($platform, 0, 1) === 's') {
	        return 'symbian';
	    } elseif (substr($platform, 0, 1) === 'm') {
	        return 'mac';
	    } else {
	        return 'android';
	    }
	}
	
	/**
	 * 截取字符串
	 *
	 * @param
	 * 		参数名称：$strCut
	 *      是否必须：否
	 *      参数说明：被截取string
	 *
	 * @param
	 * 		参数名称：$intSublen
	 *      是否必须：否
	 *      参数说明：被截取长度
	 *
	 * @param
	 * 		参数名称：$intStart
	 *      是否必须：否
	 *      参数说明：截取开始位置
	 *
	 * @param
	 * 		参数名称：$strCode
	 *      是否必须：否
	 *      参数说明：编码
	 *
	 * @return string
	 */
	public function cutStr($strCut, $intSublen = self::DESC_WORD_COUNT, $intStart = 0, $strCode = 'utf-8') {
	    //replace html element
	    $strCut = strip_tags($strCut);
	    if ($strCode == 'utf-8'){
	        $arrMatch= array();
	        $strPatten = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
	        preg_match_all($strPatten, $strCut, $arrMatch);
	        if (count($arrMatch[0]) - $intStart > $intSublen){
	            return join('', array_slice($arrMatch[0], $intStart, $intSublen))."...";
	        }
	        return join('', array_slice($arrMatch[0], $intStart, $intSublen));
	    }else{
	        $intStart = $intStart*2;
	        $intSublen = $intSublen*2;
	        $strlen = strlen($strCut);
	        $strTmp = '';
	        for($i=0; $i< $strlen; $i++){
	            if($i>=$intStart && $i< ($intStart+$intSublen)){
	                if(ord(substr($strCut, $i, 1))>129){
	                    $strTmp.= substr($strCut, $i, 2);
	                }else{
	                    $strTmp.= substr($strCut, $i, 1);
	                }
	            }
	
	            if(ord(substr($strCut, $i, 1))>129){
	                $i++;
	            }
	        }
	
	        //超出多余的字段就显示...
	        if(strlen($strTmp)< $strlen ){
	            $strTmp.= "...";
	        }
	        return $strTmp;
	    }
	}

	
	/**
	 *新库
	 * @return db
	 */
	public function getXDB(){
	    //Edit by fanwenli on 2017-07-28, create conn one by one
	    return DBConn::getPhasterXdb();
	}
	
	/**
	 * 获取百通应用， 无缓存（因为百通数据由竞价产生，如长时间缓存可能造成超时下载不计费的问题，同时百通生成的下载链接会带有请求时候的cuid信息，即相同app每个用户下载链接都不同。）,
	 *
	 * @param
	 * 		参数名称：$num
	 *      是否必须：否
	 *      参数说明：一页获取条数
	 *
	 * @return array
	 */
	public function getBaiTongList($num = 30){
	
	    //百度内部用
	    $log_id =  md5($_GET['cuid'] . $this->getMtime());

	    $strListParam = "timestamp=" . time() . "&host_app_name=ime_server&action_type=1&action=show_list&product_version={$_GET['version']}&imei={$_GET['imei']}"
	        . "&imsi={$_GET['imei']}&brand={$_GET['model']}&model={$_GET['model']}&resolution={$_GET['screen_h']}x{$_GET['screen_w']}&mac={$_GET['mc']}" ;
	    
	    if(isset($_GET['sp'])) {
	        $sp = $this->btSpTrans($_GET['sp']);
	        if($sp) {
	            $strListParam .=  "&network=$sp";
	        }
	        
	    }
    
	    
	    $strSecret = md5(self::BAITONG_KEY . $this->baitong_api_key);
	     
	    $strApiKey = $this->baitong_api_key;
	
	    $strListUrl = "r=InterfaceBTAction&m=get_api&params=" .base64_encode($strListParam) . "&secret={$strSecret}&api_key={$strApiKey}&clienttype=api&ad_type=1&platform={$this->plt}&api_version=20&baiduid={$_GET['uid']}&log_id={$log_id}&page=1&page_size={$num}&user_ip=".$this->getIp();
	   
	    ral_set_pathinfo('/baitong/index.php');

        ral_set_querystring($strListUrl);
	  
	    $arrList = ral("baitong", 'get', null, rand());

	    if($arrList && !empty($arrList) && !is_array($arrList)) {
	        $arrList = json_decode($arrList, true);
	    }
	   
	    if( isset($arrList['flag']) && $arrList['flag'] === 1  && !empty($arrList['list']) ) {
	        
	        $new = array();
	        //格式化
	        foreach ($arrList['list'] as $v) {
	            $tmp = array();
	            $tmp['id'] = md5('bt' . $v['app_key']);
	            $tmp['apk_name'] = $v['app_package'];
	            $tmp['name'] = $this->cutStr($v['app_name'], self::NAME_WORD_COUNT);
	            $tmp['subhead'] = $this->cutStr($v['comment'], self::SUBHEAD_WORD_COUNT);
	            $tmp['icon'] = $this->getSpecImageUrl( $v['app_icon'] ,'128_128','b') ;
	            $tmp['desc'] = $v['comment'];
	            $tmp['thum1'] = $this->getSpecImageUrl($v['screen1'],'240_400','u', true);
	            $tmp['thum2'] = $this->getSpecImageUrl($v['screen2'],'240_400','u', true);
	            $tmp['version_name'] = $v['version_code'];
	            $tmp['type'] = "0";
	            $tmp['durl'] = $this->btDownloadUrlFormat($v,$arrList['log_id']);
	            $tmp['size'] = $v['app_file_size'];
	            $tmp['mtime'] = "0";
	            $tmp['pub_item_type'] = null;
	            $tmp['from'] = 'bt';
	            $tmp['app_data_type'] = 'bt';
	            
	            array_push($new, $tmp);
	        }
            $this->src_data['bt'] = $new;
	        return $new;
	    } else {
	        $this->src_data['bt'] = array();
	        return false;
	    }
	
	}
	
	/**
	 * 获取客户端ip
	 * @return Ambigous <string, unknown>
	 */
	public function getIp()
	{
	    $cilent_ip = "";
	    if(isset($_GET['ip'])){
	        $cilent_ip = $_GET['ip'];
	    }else{
	        $cilent_ip = Util::getClientIP();
	    }
	    return $cilent_ip;
	}
	
	/**
	 * 获取app排序配置
	 * @return multitype:multitype: number string
	 */
	public function getAppSortRes() {
	
	    $strCacheKey = self::CACHE_APP_SORT_LIST . $this->plt ;
	    $arrRes = GFunc::cacheGet($strCacheKey);
	    if(false !== $arrRes) {
	        return $arrRes;
	    }
	    
	    $arrRes = array();
	    $arrHeader = array(
	        'pathinfo' => '/res/json/input/r/online/appstore_sort/',
	        'querystring'=> 'onlycontent=1&limit=1',
	    );
	     
	    $strResult = ral("resJsonService", "get", null, rand(), $arrHeader);
	   
	    if(false === $strResult){
	        Logger::warning('cloud input check white update request res get words failed');
	        return false;
	    }
	    $arrRes = json_decode($strResult, true);
	    if(!empty($arrRes)){
	        $key = key($arrRes);
	        $arrRes = $arrRes[$key];
	        
	        if(!empty($arrRes)) {
	            GFunc::cacheSet($strCacheKey, $arrRes, $this->intCacheExpired);
	            return $arrRes;
	        }
	    }

	    return false;
	}
	
	/**
	 * 获取13位时间戳
	 * @return string
	 */
	public function getMtime()
	{
	    $m = microtime();
	    return substr($m, 11, 10) . substr($m, 2, 3)  ;
	}
	
	/**
	 * 百通下载链接格式化
	 * @param unknown $bt_data  百通数据 
	 * @param unknown $lod_id
	 * @param unknown $is_down_complete    0:点击下载时   1:下载完成时 2:下载完成打开时
	 * @return string  url
	 */
	public function btDownloadUrlFormat($bt_data, $log_id, $is_down_complete = 0){
	    
	    $strListParam = "timestamp=" . time() . "&download_app_name={$bt_data['app_name']}&host_app_name=ime_server&action_type=2&action=download_list&product_version={$_GET['version']}&imei={$_GET['imei']}"
	        . "&imsi={$_GET['imei']}&os_version={$_GET['rom']}&brand={$_GET['model']}&model={$_GET['model']}&resolution={$_GET['screen_h']}x{$_GET['screen_w']}&mac={$_GET['mc']}" ;
	     
	    $strSecret = md5(self::BAITONG_KEY . $bt_data['app_key']);
	    
	    $strApiKey = $this->baitong_api_key;
	    
	    $strListUrl = "&params=" .base64_encode($strListParam) . "&app_key={$bt_data['app_key']}&secret={$strSecret}&down_complete={$is_down_complete}&api_version=20&api_key={$strApiKey}&log_id={$log_id}&from=lc";
	    
	    return $bt_data['download_url'] . $strListUrl;
	}
	
	
	/**
	 * 获取手助cps数据, 2小时缓存
	 * @param number $num 
	 * @return array()
	 */
	public function getCpsList($num = 35) {
	    
	    $strCacheKey = self::CACHE_CPS_APP_LIST . $this->plt  ;

	    $arrRes = GFunc::cacheZget($strCacheKey);
	    if(false !== $arrRes) {
	        $this->src_data['szcps'] = $arrRes;
	        return $arrRes;
	    }
	  
	    ral_set_pathinfo("/res/json/input/r/online/szcpsdata/");
	    ral_set_querystring("&onlycontent=1&searchbyori=1&limit={$num}&sort=%7b%22update_time%22%3a-1%7d" );
	   // ral_set_querystring("&onlycontent=1&searchbyori=1&limit={$num}&sort=".urlencode('{"update_time": -1}') );
	    $data = ral("res_service", 'get', null, rand());
	    $cps_data = array();
        if(is_array($data) && !empty($data)) {
            $p =  $this->getSnCommonParams();
            foreach($data as $k => $v) {

                //没有id就是非法数据
                if(empty($v['apk_id'])) {
                    continue;
                }
                
                if(strtolower($this->plt) == 'android' && strtolower($v['platform']) != 'andriod') { //cps数据源有拼写错误，
                    continue;
                }
                
                if(strtolower($this->plt) == 'ios' && strtolower($v['platform']) != 'ios') {
                    continue;
                }
           
                $tmp = array();
                $tmp['id'] = md5('cps_' . $v['apk_id']);
                $tmp['apk_name'] = $v['package_name'];
                $tmp['name'] = $this->cutStr($v['title'], self::NAME_WORD_COUNT);
                $tmp['subhead'] = $this->cutStr($v['summary'], self::SUBHEAD_WORD_COUNT);
    
                $tmp['icon'] = $this->getSpecImageUrl( $v['smallmaplink'] ,'128_128','b') ;
        
                $tmp['desc'] = $v['description'];
                $screen = explode(';', $v['bigmaplink']);
                $tmp['thum1'] = $this->getSpecImageUrl($screen[0],'240_400','u', true);
                $tmp['thum2'] = $this->getSpecImageUrl(isset($screen[1]) ? $screen[1] :'','240_400','u', true);
                $tmp['version_name'] = $v['version'];
                $tmp['type'] = "0";
                $tmp['durl'] = (strtolower($v['platform']) == 'ios' ? $v['ios_sourcelink'] : $v['packagelink'] ) . '&' .http_build_query($p) . '&sign=' . $this->getSnSign($p);
                $tmp['size'] = $v['packagesize'];
                $tmp['mtime'] = "0";
                $tmp['pub_item_type'] = null;
                $tmp['from'] = 'szcps';
                $tmp['app_data_type'] = 'szcps';
                array_push($cps_data, $tmp);
            
            }
        }
	    
	    if(!empty($cps_data)) {
	        GFunc::cacheZset($strCacheKey, $cps_data, Gfunc::getCacheTime('2hours'));
	    }
	    $this->src_data['szcps'] = $cps_data;
	    return $cps_data;
	}
	
	/**
	 * 获取手助cpd数据，无缓存(为了精准投放，获取列表时需要用户参数)
	 * @param number $num
	 * @return multitype:
	 */
	public function getCpdList($num = 35) {
	  
	    $params =  array(
	        'channel' => '1043',
	        'apitype' => 1,
	        'imei' => isset($_GET['imei']) ? $_GET['imei'] : '',
	        'page_size' => $num,
	        'product_version' => $this->version, //合作方的产品版本名称,如 V1.0、V2.0
	        'imsi' => isset($_GET['imei']) ? strrev($_GET['imei']) : '',
	        'os_version' => $_GET['rom'], //￼android 系统版本号
	        'brand' => $_GET['model'],
	        'model' => $_GET['model'],
	        'resolution' => $_GET['screen_h'] .'*' .$_GET['screen_w'],
	        'mac' => $_GET['mc'],
	    );
	    
	    $params['sign'] = $this->generate_sign($params);
	     
	    
	    $Orp_FetchUrl = new \Orp_FetchUrl();
	    $httpproxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));

	    $result = $httpproxy->get($this->cpd_data_url .'?' . http_build_query($params));
	    $err = $httpproxy->errmsg();
	    //$result = $this->getHttpRequestResponse($this->cpd_data_url .'?' . http_build_query($params), 'get' );
	   
	    $cpd_data = array();
	    
	    if(!$err && $httpproxy->http_code() == 200) {
	        $data = json_decode($result, true);
	        if(is_array($data)) {
	            foreach ($data['list'] as $k => $v) {
	                $tmp = array();
	                $tmp['id'] = md5('cpd_' . $v['app_key']);
	                $tmp['apk_name'] = $v['app_package'];
	                $tmp['name'] = $this->cutStr($v['app_name'], self::NAME_WORD_COUNT);
	                $tmp['subhead'] = $this->cutStr($v['comment'], self::SUBHEAD_WORD_COUNT);
	                $tmp['icon'] = $this->getSpecImageUrl( $v['app_icon'] ,'128_128','b') ;
	                $tmp['desc'] = $v['comment'];
	                $tmp['thum1'] = $this->getSpecImageUrl($v['screen1'],'240_400','u', true);
	                $tmp['thum2'] = $this->getSpecImageUrl($v['screen2'],'240_400','u', true);
	                $tmp['version_name'] = $v['version_name'];
	                $tmp['type'] = "0";
	                $tmp['durl'] = $v['download_url'];
	                $tmp['size'] = $v['app_file_size'];
	                $tmp['mtime'] = "0";
	                $tmp['pub_item_type'] = null;
	                $tmp['from'] = 'szcpd';
	                $tmp['app_data_type'] = 'szcpd';
	                array_push($cpd_data, $tmp);
	            }
	            
	            
	        }
	    }
	    $this->src_data['szcpd'] = $cpd_data;
	    return $cpd_data;
	}
	
	
	
	/**
	 * 对要提交的参数进行签名,计算 md5
	 * @param array $params
	 * @param string $secret
	 * @return string
	 */
	public function generate_sign($params) {
	    
	    $secret = '1014091e'; 
    	$str = ''; 
    	//先将参数以其参数名的字母升序进行排序 
    	ksort($params); 
    	//遍历排序后的参数数组中的每一个 key/value 对 
    	foreach ($params as $k => $v) {
    	   //为 key/value 对生成一个 key=value 格式的字符串,并拼接到签名字符串后面
    	   $str .= "$k=$v"; 
    	}
    	//将签名密钥拼接到签名字符串最后面 
    	$str .= $secret;
    	return md5($str); // 返回小写 md5
	}
	
	/**
	 * 获取http请求结果
	 *
	 * @param $strUrl 请求url
	 * @param $strMethod http method
	 * @param $arrPostField http post field
	 * @param $intTimeout 超时 单位秒
	 * @param $arrHeader header
	 *
	 * @return array
	 */
	public function getHttpRequestResponse($strUrl, $strMethod, $arrPostField = null, $intTimeout = 1, $arrHeader = null){
	    $arrParams = array (
	        'url' => $strUrl,
	        'method' => $strMethod,
	        'post_fields' => $arrPostField,
	        'timeout' => $intTimeout,
	    );
	
	    if( (null !== $arrHeader) && (0 !== count($arrHeader)) ){
	        $arrParams['header'] = $arrHeader;
	    }
	
	    $objCurlRequest = new CurlRequest ();
	    $objCurlRequest->init( $arrParams );
	    $arrResult = $objCurlRequest->exec();
	    curl_close ($objCurlRequest->ch);
	
	    return $arrResult;
	}
	
	/**
	 * 获取手助自然源榜单
	 * @param number $board_id  榜单号
	 * @return multitype: array
	 */
	public function getSznaturlList($args = array('num' => 30, 'board_id' => 1) ) {
	    
	    $k = 'sn_'.$args['board_id'];
	    $params =  $this->getSnBoardParams($args['board_id'], $args['num']);
        $params['id'] = $args['board_id']; //设置要获取的榜单
        //$url = self::SZ_NATURAL_BOARD_URL . '&' . http_build_query($params) .'&sign=' .$this->getSnSign($params);
        $url =  http_build_query($params) .'&sign=' .$this->getSnSign($params);
        
        ral_set_pathinfo('/api');
        
        ral_set_querystring('action=board&'.$url);
         
        $arrList = ral('mbaidu', 'get', null, rand());
    
        if($arrList && !empty($arrList) && !is_array($arrList)) {
            $arrList = json_decode($arrList, true);
        }
       
        $data = array();
        $r = $arrList;
        
        if($r['statuscode'] == 0) {
          
           $common_params = $this->deepUrlencode($this->getSnCommonParams());
           //格式化
            foreach ($r['result']['apps'] as $v) {
               
                $tmp['id'] = 'sn_' . $v['docid'] ;
                $tmp['apk_name'] = $v['package'];
                $tmp['name'] = $this->cutStr($v['sname'], self::NAME_WORD_COUNT);
                $tmp['subhead'] = $this->cutStr($v['manual_brief'], self::SUBHEAD_WORD_COUNT);
    
                $tmp['icon'] = $this->getSpecImageUrl( isset($v['icon_source']) ? $v['icon_source'] : $v['icon'] ,'128_128','b') ;
        
                $tmp['desc'] =  $this->cutStr($v['brief'], 60);
                $screen = explode(';', $v['screenshot']);
                $tmp['thum1'] = $this->getSpecImageUrl($screen[0],'240_400','u', true);
                $tmp['thum2'] = $this->getSpecImageUrl(isset($screen[1]) ? $screen[1] :'','240_400','u', true);
                $tmp['version_name'] = $v['versionname'];
                $tmp['type'] = "0";
                $tmp['durl'] = $v['download_url'] . '&' . http_build_query($common_params) .'&sign=' . $this->getSnSign($common_params);
                $tmp['size'] = $v['size'];
                $tmp['mtime'] = "0";
                $tmp['pub_item_type'] = null;
                $tmp['from'] = 'sn';
                $tmp['app_data_type'] = $k;
                
                array_push($data, $tmp);
            }
           
        }
       
       
       $this->src_data[$k] = $data;
       return $data;
	   
	}
	
	
	/**
	 * 获取榜单请求参数
	 * @param number $boardid  1~8  1 最热 2 最新 3 软件最热 4 游戏最热 5 软件最新 6 游戏最新 7 软件新锐 8 游戏新锐
	 * @return Ambigous <multitype:, string, array>
	 */
	public function getSnBoardParams($boardid = 1, $num = 30) {
	    $params = $this->getSnCommonParams();
        $params['from'] = $this->sn_from;
        $params['token'] = $this->sn_token;
        $params['type'] = 'app';
        $params['format'] = 'json';
        $params['id'] = $boardid >=1 && $boardid <= 8 ? $boardid : 1;
        $params['dpi'] = 320;
        $params['uid'] = urlencode($_GET['uid']);
        $params['rn'] = $num; //每页最大60
        $params['pn'] = 0 ; //只取第一页
        $params = $this->deepUrlencode($params);
        ksort($params);
        return $params;
        
	}
	
	/**
	 * 获取通用请求参数
	 * @return array
	 */
	public function getSnCommonParams() {
	    $ip =  Util::getClientIP();
	    $ip_info = $this->ip_to_city->getCity($ip);
	    $model = explode('-', $_GET['model']);
	    $params = array(
	        'bdi_imei' => $this->encrptSnByAES($_GET['imei']),
	        'bdi_loc'  => base64_encode($ip_info['city']),
	        'bdi_uip'  => $ip,
	        'bdi_bear' => isset($_GET['sp']) ? $_GET['sp'] : 'WF',
	        'resolution' => $_GET['screen_w'] .'_' .$_GET['screen_h'],
	        'apilevel' => $_GET['sdk'],
	        'os_version' => $_GET['rom'],
	        'brand' => isset($model[0]) ? $model[0] : 'unknown',
	        'model' => $_GET['model'],
	        'pver' => 3,
	    );
	    
	    return $params;
	}
	
	/**
	 * 数组值urlencode
	 * @param array $params
	 * @return array|string
	 */
	public function deepUrlencode($params) {
	    if(is_array($params)) {
	        foreach ($params as $k => &$v) {
	            $v = urlencode($v);
	        }
	        return $params;
	    }
	    
	    return urlencode($params);
	}
	
	/**
	 * 生成手助自然源访问签名
	 * @param unknown $params
	 * @return string
	 */
	public function getSnSign($params) {
	    
	    if(empty($params)) {
	        return '';
	    }
	    
	    ksort($params);
	    
	    return strtoupper(md5(http_build_query($params)));
	}
	
	/**
	 * 手助自然源AES加密算法
	 * @param string $plaintext
	 * @return string
	 */
	public function encrptSnByAES($plaintext) {
	    
	    $serect = $this->sn_aes_secret;	// 密钥-需申请
	    $iv = $this->sn_aes_iv;		// 偏移量-需申请
	    $from = $this->sn_from;	// 所属的API渠道号-需申请
	    
	    // 获取加密key
	    $key = strtoupper(substr(md5($from . $serect), -16));
	    
	    // 如果iv长度不足16字节，进行补足
	    $iv = str_pad(substr($iv, 0, 16), 16, chr(0));
	    
	    $plaintext = $this->aesPad($plaintext);
	    
	    // 初始化
	    $enmcrypt = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
	    mcrypt_generic_init($enmcrypt, $key, $iv);
	    
	    // 加密
	    $data = mcrypt_generic($enmcrypt, $plaintext);
	   // $data = urlencode(base64_encode($data));
	    $data = base64_encode($data); //urlencode在外部统一处理
	    mcrypt_generic_deinit($enmcrypt);
	    mcrypt_module_close($enmcrypt);
	    
	    return $data;
	}
	
	/**
	 * PKCS填充实现
	 * @param string
	 * @return string
	 */
	public function aesPad($text) {
	    $length = strlen($text);
	
	    if ($length % 16 == 0) {
	        return $text;
	    }
	
	    $pad = 16 - ($length % 16);
	
	    return str_pad($text, $length + $pad, chr($pad));
	}
	
	/**
	 * mac地址转换
	 * @param string $mac
	 * @return string
	 */
	public function transMac($mac) {
	    if(strlen($mac) == 17) {
	        return $mac;
	    }
	    
	    return chunk_split($mac, 2, ':');
	}
	
	
	
	/**
	 * 点击banner icon 跳转
	 * @route({"GET","/icon"})
	 * @return({"body"})
	 {
	 }
	 */
	public function iconAction() {
	    $strSrc = isset($_GET['src']) ? $_GET['src'] : '';
	    $strDst = $this->getOriUrl($strSrc);
	    header("Location: " . $strDst);
	    
	    exit;
	}
	
	/**
	 * 点击banner web 跳转
	 * 目的：统计
	 * @route({"GET","/web"})
	 * @return({"body"})
	 */
	public function webAction(){
	    $strSrc = isset($_GET['src']) ? $_GET['src'] : '';
	    $strDst = $this->getOriUrl($strSrc);
	    header("Location: " . $strDst);
	
	    exit;
	}
	
	/**
	 * haina down 跳转
	 * 目的：统计
	 * @route({"GET","/down"})
	 * @return({"body"})
	 */
	public function downAction(){
	    //经查已无使用
// 	    $strDst = isset($_GET['dst']) ? $_GET['dst'] : '';
// 	    header("HTTP/1.1 302 Found");
// 	    header("status: 302 Found");
// 	    header("Location: " . $strDst);
	
	    exit;
	}
	
	
	
	
	/**
	 * 获取banner信息
	 * @route({"GET","/banner"})
	 * @return({"body"})
	 */
	public function bannerAction(){
	    $intPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
	
	    if ($intPage <= 0){
	        $intPage = 1;
	    }
	
	    //get from cache
	    $strCacheKey = self::CACHE_APP_STORE_BANNER_KEY . $this->strOs . '_' . $intPage;
	    $arrBannerList = GFunc::cacheGet($strCacheKey);
	    
	    if (false !== $arrBannerList){
	        return $arrBannerList;
	    }
	
	    //get page
	    $arrBannerInfo = array();
	    $arrBannerInfo['banner'] = array();
	    $arrBanner = $this->getBanner();
	    $intTotalCount = count($arrBanner);
	    $intBegin = ($intPage - 1) * self::PER_PAGE_COUNT;
	    $intEnd = ($intPage * self::PER_PAGE_COUNT <= $intTotalCount)? $intPage * self::PER_PAGE_COUNT : $intTotalCount;
	
	    for($i = $intBegin; $i < $intEnd; $i++){
	        array_push($arrBannerInfo['banner'], $arrBanner[$i]);
	    }
	
	    $arrBannerInfo['lastpage'] = ($intEnd >= $intTotalCount)? '1' : '0';
	    $arrBannerInfo['count'] = count($arrBannerInfo['banner']);
	
	    GFunc::cacheSet($strCacheKey, $arrBannerInfo, Gfunc::getCacheTime('5mins'));
	
	    return $arrBannerInfo;
	}
	
	/**
	 * 获取banner列表
	 *@return array
	 */
	public function getBanner(){
	    $arrBanner = array();
	    /*$arrAppList = $this->getAppList();
	
	    foreach ($arrAppList as $k => $v ){
	        $arrAppList[$k]['durl'] = $this->getTraceUrl($v['durl'], $v['apk_name']);
	    }
   
	    $arrRmdInfo = $this->getAppRecommendInfo();*/
	    
	    //Edit by fanwenli on 2017-07-28, get info by phaster
	    $arrInfo = $this->getDataFromAppListANDAppRecommendInfo();
	    $arrAppList = $arrInfo['arrAppList'];
	    $arrRmdInfo = $arrInfo['arrRmdInfo'];
	    
	    foreach ($arrAppList as $k => $v ){
	        $arrAppList[$k]['durl'] = $this->getTraceUrl($v['durl'], $v['apk_name']);
	    }
	    
	    foreach($arrAppList as $arrApp){
	        $arrAppInfo = $arrApp;
	        $arrAppInfo['desc'] = $arrApp['description'];
	        $arrAppInfo['rmdpos'] = self::NOT_RECOMMEND_FLAG;
	        	
	        $arrAppInfo['banner_icon'] = $arrApp['banner_icon'];
	        $arrAppInfo['web_url'] = $arrApp['web_url'];
	        	
	        if ( in_array(intval($arrApp['id']), $arrRmdInfo['banner']) ){
	            $arrAppInfo['rmdpos'] = array_search(intval($arrApp['id']), $arrRmdInfo['banner']);
	            array_push($arrBanner, $arrAppInfo);
	        }
	    }
	
	    //sort
	    //按照推荐位排序
	    usort($arrBanner, function($a, $b){
	        if ($a['rmdpos'] == $b['rmdpos']){
	            return 0;
	        }
	        return ($a['rmdpos'] > $b['rmdpos']) ? 1 : -1;
	    });
	
	        //去掉排序字段等
	        $c = count($arrBanner);
        for($i = 0; $i < $c; $i++){
            unset($arrBanner[$i]['rmdpos']);
            unset($arrBanner[$i]['os']);
            unset($arrBanner[$i]['ctime']);
            unset($arrBanner[$i]['status']);
        }
	
        return $arrBanner;
	}
	
	
	/**
	 * 获取缩略图及描述
	 * @route({"GET","/thum"})
	 * @return({"body"})
	 */
	public function thumAction(){
	   $strId = isset($_REQUEST['id']) ? $_REQUEST['id'] : '' ; 
		
		if('' === $strId){
			header('X-PHP-Response-Code: '. 400, true, 400);
			exit();
		}
		
		$arrAsInfo = array ();
		
		$strUrl = self::AS_DETAIL_URL;
		$strUrl = str_replace(self::ID_REPLACE, $strId, $strUrl);
		
		
		$Orp_FetchUrl = new \Orp_FetchUrl();
		$httpproxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
		
		$arrResult = $httpproxy->get($strUrl);
		$err = $httpproxy->errmsg();
		if(!$err && $httpproxy->http_code() == 200) {
		    $arrAsInfo = json_decode($arrResult, true);
		}else{
			header('X-PHP-Response-Code: '. 500, true, 500);
			exit();
		}
		
		$arrAsDetail = array();
		$arrAsDetail['desc'] = $this->cutStr($arrAsInfo['result']['app']['brief']);
		$arrAsDetail['thum1'] = $this->getSpecImageUrl($arrAsInfo['result']['app']['screenshot 1'], '288_480');
		$arrAsDetail['thum2'] = $this->getSpecImageUrl($arrAsInfo['result']['app']['screenshot2'], '288_480');
		
		return $arrAsDetail;
	}
	
	
	/**
	 * 获取请求v4的url
	 *
	 *
	 * @param
	 * 		参数名称：$strV4Url
	 *      是否必须：否
	 *      参数说明：请求v4 url
	 *
	 * @return string
	 */
	public function getOriUrl($strV4Url){
	    if ('' === $strV4Url){
	        return '';
	    }
	
	    //$strV4Url = urldecode($strV4Url);
	    $strReplaceAndUrl = str_replace('${and}', '&', $strV4Url);
	
	    return $strReplaceAndUrl;
	}
	
	
	/**
	 * 获取icon的url
	 *
	 *
	 * @param
	 * 		参数名称：$strOriUrl
	 *      是否必须：否
	 *      参数说明：icon url
	 *
	 * @param
	 * 		参数名称：$strDpi
	 *      是否必须：否
	 *      参数说明：dbi
	 *
	 * @return string
	 */
	public function getIconUrl($strOriUrl, $strDpi){
	    $strUrl = $strOriUrl;
	    $arrMatch = array();
	    preg_match("#" . '&size=(\w+)&' . "#", $strOriUrl, $arrMatch);
	    if (isset($arrMatch[1])){
	        $strReplace = $arrMatch[1];
	        $strUrl = str_replace($strReplace, $strDpi, $strOriUrl);
	    }
	
	    return $strUrl;
	}
	
	/**
	 * 获取百通格式的网络状态
	 * @param string $sp
	 * @return boolean|string
	 */
	public function btSpTrans($sp = '') {
	    if(empty($sp)) {
	        return false;
	    }
	     
	    switch ($sp) {
	        case 1:
	        case '1':
	            return '2g';
	        case 2:
	        case '2':
	            return '3g';
	        case 3:
	        case '3':
	            return '4g';
	        case 4:
	        case '4':
	            return 'wf';
	        default:
	            return false;
	    }
	     
	}
	
	
	/**
	 * 获取源开关
	 * @return multitype:multitype: number string
	 */
	public function getSrcSwitchRes() {
	
	    $strCacheKey = self::CACHE_APPSTORE_SRC_SWITCH  ;
	    $arrRes = GFunc::cacheGet($strCacheKey);
	    if(false !== $arrRes) {
	        return $arrRes;
	    }
	     
	    $arrRes = array();
	    $arrHeader = array(
	        'pathinfo' => '/res/json/input/r/online/appstore_src_switch/',
	        'querystring'=> 'onlycontent=1&limit=1',
	    );
	
	    $strResult = ral("resJsonService", "get", null, rand(), $arrHeader);
	
	    if(false === $strResult){
	        Logger::warning('appstore getSrcSwitchRes failed');
	        return false;
	    }
	    $arrRes = json_decode($strResult, true);
	    if(!empty($arrRes)){
	        $key = key($arrRes);
	        $arrRes = $arrRes[$key];
	         
	        if(!empty($arrRes)) {
	            GFunc::cacheSet($strCacheKey, $arrRes, GFunc::getCacheTime('5mins'));
	            return $arrRes;
	        }
	    }
	
	    return false;
	}
	
	/**
	 * phaster获取获取自有应用列表信息和APP推荐信息
	 * @param int $appListNum  默认取30条自有应用列表信息
	 * @return array
	 */
	private function getDataFromAppListANDAppRecommendInfo($appListNum = 30) {
	    $list_arr = array(
	        'arrAppList' => array(),
	        'arrRmdInfo' => array(),
	    );
	    
	    $appListNum = intval($appListNum);
	    
	    $arrAppList_phaster = new PhasterThread(array($this,"getAppList"), array($appListNum));
	    $arrRmdInfo_phaster = new PhasterThread(array($this,"getAppRecommendInfo"), array());
	    
	    $list_arr['arrAppList'] = $arrAppList_phaster->join();
	    $list_arr['arrRmdInfo'] = $arrRmdInfo_phaster->join();
	    
	    return $list_arr;
	}
	
}
