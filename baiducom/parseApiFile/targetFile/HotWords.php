<?php

use tinyESB\util\Verify;
use utils\Util;
use utils\GFunc;
use utils\DbConn;
use utils\Bos;
use models\CellWordsBaseModel;
use models\WordlibModel;


require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';
/**
 * 
 * @author caoym
 * 热词接口, 将原v4的热词接口迁移到了v5
 * 目前只提供获取热词列表功能
 * 
 * @path("/hotwords/")
 */
class HotWords
{
	/**
	 * 手机输入法在移动搜索中的渠道号
	 *
	 * @var string
	 */
	const IME_CHANNEL_IN_WISE = '1001560r';

	/**
	 * 手机输入pu参数，目前包含osname和csrc
	 *
	 * @var string
	 */
	const IME_PU_IN_WISE  = ',osname@baiduinput,csrc@app_apphotword_auto';
	
	/** @property 运营分类lite intend缓存key */
    private $strHotWordsLiteIntendCombineCache;
    
    /** @property 缓存时间 */
    private $intCacheExpired;
    
    /**
     *
     * 下载热词接口热词最新版本与ios 7.8以前版本下载热词版本差异最大值
     * 超过则下发全量包
     * @var int
     */
    const DF_HW_IOS_LAST_VER_DIFF_MAX = 2;
    
    /**
     *
     * ios7.8以前版本更新热词存在BUG
     * 
     * @var string
     */
    const DF_HW_IOS_BUG_MAX_VER = '7.8.0.0';
	
    /**
     * @route({"GET","/"})
     * 获取热词列表
     * @param({"platform", "$._GET.platform"}) 请求者的平台号
     * @param({"sf", "$._GET.sf"}) 请求记录的开始条目, 默认是0
     * @param({"num", "$._GET.num"}) 请求的条目数量, 默认12
     * @return({"header", "Content-Type: application/json; charset=UTF-8"}) 返回格式是json
     * @return({"body"}) 返回热词列表
     * 数据结构如下:
     * [
     *        {
     *           id: "193",                          //热词的唯一id
     *           type: "1",                         //热词类型,1:普通图文热词; 2:banner图片热词; 3:图文app推荐; 4:banner式app推荐
     *           source_content: "百度葡语搜索|百度葡语搜索功能巴西正式上线",  //词条解释
     *           word: "百度葡语搜索",       //关键字
     *           word_desc: "百度葡语搜索功能巴西正式上线",   //关键字描述
     *           word_comment: "",          //关键字链接
     *           link: "",                            //app连接, 只有app推荐时有效
     *           pic: "http://r6.mo.baidu.com/upload/imags/2014/07/19/or68f8k8f7.jpg",//图片
     *         },
     *  ...
     * ]
     */
    public function getList($platform, $sf=0, $num=12){
        $key = __CLASS__ . __METHOD__ . $sf . $num;
        $res = GFunc::cacheGet($key);
        if (false === $res) {
            //先按是否置顶排序, 再按版本排序, 最后按优先级排序
            $sql  = 'select id, type, source_content, word, word_desc, word_comment, link, pic, action_type, tab_address from input_wordslib_hotwords_details where status = 100 and (platform = "all" or platform = "%s") '.
            'and ((stick = 1 and stick_start_time <= now() and stick_end_time >= now()) or stick = 0) and pic is not null order by stick desc, version desc, rank desc limit %d, %d';
            $res = $this->getDbX()->queryf($sql, $platform, $sf, $num);
            GFunc::cacheSet($key, $res);
        }
        Verify::isTrue($res, "db error");
        foreach ($res as &$v){
            $v['pic'] =  $this->httproot.$v['pic'];
            if ($v['link'] == ''){
                $v['link'] = 'http://m.baidu.com/s?pu='.urlencode(trim(self::IME_PU_IN_WISE)).'&from='.self::IME_CHANNEL_IN_WISE.'&word='.urlencode(trim($v['word']));
            }
        }

        //Edit by fanwenli on 2016-08-25, combine with lite intend
        $res = Util::liteIntendCombine('hotwords', $res, $this->strHotWordsLiteIntendCombineCache, $this->intCacheExpired);
        
        return $res;
    }

    /**
	 * @return db
	 */
	function getDB(){
	    return DBConn::getDb();
	}
	
	/**
     * 获取xdb实例
     *
     * @return db
     */
    public function getDbX() {
        return DBConn::getXdb();
    }
	
	
    /**
     * @property 指定图片下载url的前缀
     */
    public $httproot;
    
    
    /**
     *
     * 最新版本热词的memcache key
     * @var string
     */
    const LAST_VERSION_CACHE_KEY ='ime_api_v5_hw_ini_last_version';
    
    /**
     * 最新热词摘要的memcache key
     *
     * @var string
     */
    const CF_SUMMARY_CACHE_KEY	 = 'ime_api_v5_hw_cf_summary';
    
    /**
     * 最新热词搜索展现的memcache key
     * @var string
     */
    const CF_SHOW_WORDS_CACHE_KEY='ime_api_v5_hw_cf_showwords';
    
    /**
     * 新版本热词内容缓存key前缀
     * @var string
     */
    const CF_SHOW_WORDS_NEW_CACHE_KEY_PREFIX = 'ime_api_v5_hw_cf_showwords_new_';
    
    /**
     * 新版本热词展现内容html样式缓存key前缀
     */
    const CF_SHOW_CONTENT_HTML_STYLE_CACHE_KEY_PREFIX = 'ime_api_v5_hw_cf_showcontent_html_style_';
    
    
    /**
     * 新版本热词每页展示条数
     * @var int
     */
    const CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY = 8;
    
    /**
     * 新版本热词最多展示页数
     */
    const CF_SHOW_WORDS_PAGE_NUM_CACHE_KEY_PREFIX = 'ime_api_v5_hw_cf_showwords_page_num_';
    
    /**
     * 定向cuid推送app数据缓存key前缀
     *
     * @var string
     */
    const CF_CUID_PUSH_APP_RECOMMEND_DATA_CACHE_KEY_PREFIX = 'ime_api_v5_hw_cf_cuid_push_app_recommend_data_';
    
    /**
     * 定向cuid缓存key前缀
     * 需要与后台加载到memcache中使用的缓存key保持一直
     *
     * @var string
     */
    const CF_CUID_PUSH_APP_RECOMMEND_CUID_CACHE_KEY_PREFIX = 'notification_cuid_push_hotwordapp_';
    
    /**
     * 每次从wise的app推荐列表中获取的数目
     *
     * @var string
     */
    const CF_APP_RECOMMEND_NUM_FROM_WISE = 0;
    
    /**
     * SDK通过热词接口控制的上传BBM数据的比例，六分之一
     *
     * @var int
     */
    const CF4_SDK_UPLOAD_USERINFO_RATE	=6;
    
    
    /**
     * 全量热词包，最多包含的热词天数
     *
     * @var int
     */
    const HOT_WORDS_PKG_FULL_DAYS = 60;
    
    
    /**
     * cf7默认pagesize
     *
     * @var int
     */
    const CF7_IOS_DEFAULT_PAGESIZE = 14;
    
    /**
     * 无更新时返回显示给用户的信息
     *
     * @var string
     */
    const NO_UPDATE_MSG='没有可用更新';
    /**
     *
     * 图片云转码token
     * @var strring
     */
    const IMAGE_TRANSCODE_TOKEN = 'wisetimgkey_noexpire_3f60e7362b8c23871c7564327a31d9d7';
    
    /**
     *
     * 图片云转码url
     * @var strring
     */
    const IMAGE_TRANSCODE_URL = 'http://timg01.baidu-1img.cn/timg?pa&quality=100';
    /**
     *
     * 客户端词库版本
     * @var int
     */
    private $_client_ver=0;
    
    /**
     * 当前词库最新版本
     *
     * @var int
     */
    private $_last_version = 0;
    
    /**
     * 用户的系统类型
     *
     * @var string
     */
    private $_os = '';
    
    /**
     * 返回给客户端的数据
     *
     * @var array
     */
    private $_return_array = array();
    
    /**
     * 是否为新格式词库
     *
     * @var string
     */
    private $bolIsNewFormat = false;
    
    /**
     * 新格式词库后缀
     *
     * @var string
     */
    private $strNewFormatTail = '';
    
   
    private $micweb_httproot = null;
    
    private $micweb_httproot_bos = null;
    
    private $micweb_webroot_bos = null;
    
    private $bos_domain_https = null;
    
    private $platform = '';
    
    private $version = '';
    
    /**
     * 客户端版本
     * @var int
     */
    private $clientVersion = 0;
    
    //客户端流行词最大保留天数
    const MAX_REMAIN_DAYS = 10;
    //客户端流行词最大保留条数 maxremainnumber
    const MAX_REMAIN_NUMBER = 30;
    
    
    /**  @property */
    private $wise_apprec_url;
    
    /**  @property */
    private $wise_apprec_from;
    
    /**  @property */
    private $wise_apprec_token;
    
    private $cuid, $ua, $uid, $hot_words;
    
    /**
     *
     * android下发新版本热词、分类词库输入法版本(混输词)
     * @var string
     */
    const ANDROID_NEW_FORMAT_WORD_INPUT_VERSION_MIN = '5.5.0.0';
    
    /**
     *
     * ios下发新版本热词、分类词库输入法版本
     * @var string
     */
    const IOS_NEW_FORMAT_WORD_INPUT_VERSION_MIN = '5.6.0.0';
    
    /**
     *
     * 新格式词库打包工具
     * @var string
     */
    const NEW_FORMAT_WORD_BUILD_TOOL = '/home/work/php/bin/build_cell_tool';
    
    /**
     *
     * 新格式词库打包汉字配置文件
     * @var string
     */
    const NEW_FORMAT_WORD_BUILD_CONF = '/home/work/php/bin/hz_build_cell.bin';
    
    
    /**
     * 初始化，获取cache和最新版本，判断是否有更新
     * @see ApiBaseController::init()
     * @return 
     */
    public function __construct(){


        $this->platform =  isset($_GET['platform']) ? $_GET['platform'] : '';
        $this->version = isset($_GET['version']) ? $_GET['version'] : '';
        $this->cuid = $_GET['cuid'];
        $this->ua = $_GET['ua'];
        $this->uid = $_GET['uid'];
        $this->clientVersion = isset($_GET['clientversion']) ? $_GET['clientversion'] : 0;
        
        $this->micweb_httproot = GFunc::getGlobalConf('micweb_httproot');
        $this->micweb_httproot_bos = GFunc::getGlobalConf('micweb_httproot_bos');
        $this->micweb_webroot_bos = GFunc::getGlobalConf('micweb_webroot_bos');
        $this->bos_domain_https = GFunc::getGlobalConf('bos_domain_https');
        
        
        $hot_words_model =  IoCload('models\\HotWordsModel');
        $this->hot_words = $hot_words_model->getInstance();
      
        //下发词库格式判断（混输词）
        if ('android' === Util::getPhoneOS($this->platform) && Util::getVersionIntValue(str_replace('-', '.', $this->version)) >= Util::getVersionIntValue(self::ANDROID_NEW_FORMAT_WORD_INPUT_VERSION_MIN) ){
            $this->bolIsNewFormat = true;
            $this->strNewFormatTail = '_newformat';
        }
  
        if ('ios' === Util::getPhoneOS($this->platform) && Util::getVersionIntValue(str_replace('-', '.', $this->version)) >= Util::getVersionIntValue(self::IOS_NEW_FORMAT_WORD_INPUT_VERSION_MIN) ){
            $this->bolIsNewFormat = true;
            $this->strNewFormatTail = '_newformat';
        }
        
        //vivo适配，vivo下发新版格式热词,http://newicafe.baidu.com/issue/11094473/show?cid=5&spaceId=4478&from=email
        if($this->platform == 'p-a1-3-76') {
            $this->bolIsNewFormat = true;
            $this->strNewFormatTail = '_newformat';
        }
 

        $this->_os = Util::getPhoneOS($this->platform);
        $this->_getLastVersion();
    }
    
    
    /**
     * 获取最新版本，如已是最新版本直接返回无更新
     * @return array
     */
    private function _getLastVersion(){
    
        $this->_client_ver	= intval($_REQUEST["ver"]);
        $this->_last_version = GFunc::cacheGet(self::LAST_VERSION_CACHE_KEY);
         
        if ($this->_last_version === false){
    
            /*
             *缓存中没有最新版本的，数据库里读取数据，写入缓存
             */        	
            $this->_last_version = $this->hot_words->getLastVersion();
            	
            if($this->_last_version === false){
                $this->_last_version=0;
            }
            	
            GFunc::cacheSet(self::LAST_VERSION_CACHE_KEY, $this->_last_version, GFunc::getCacheTime('2hours'));
        }else{
            	
            $this->_last_version = intval($this->_last_version);
        }
    
        /*
         * 没有更新直接输出
         */
        if($this->_last_version <= $this->_client_ver){
            $this->_return_array['status'] = 0;
            $this->_return_array['msg'] = self::NO_UPDATE_MSG;
            Util::outputJsonResult($this->_return_array);
        }

    
        if($this->_last_version > $this->_client_ver){
            $this->_return_array['status'] = 1;
            $this->_return_array['lastversion'] = $this->_last_version;
        }
    }
    
    /**
     * 获取更新摘要词条
     * @return 
     */
    private function _getSummary(){
        
        $words_summary = GFunc::cacheGet(self::CF_SUMMARY_CACHE_KEY);
    
        if ($words_summary === false){
            
            $words_summary_array = $this->hot_words->getSummary($this->_client_ver, $this->_last_version);
           
            if(isset($words_summary_array['text'])){
                $words_summary=$words_summary_array['text'];
            }
            GFunc::cacheSet(self::CF_SUMMARY_CACHE_KEY, $words_summary, GFunc::getCacheTime('2hours'));
        }
    
        $this->_return_array['summary'] = $words_summary;
    }
    
    /**
     * 获取带搜索跳转的新词摘要
     *
     * @param num $max_words_num 最多显示词条数
     * @return 
     */
    private function _getShowWords($max_words_num){
    
        $show_words=GFunc::cacheGet(self::CF_SHOW_WORDS_CACHE_KEY);
  
        if ($show_words === false){
            $hot_words = IoCload('models\\HotWordsModel');
   
            $show_words = $this->hot_words->getShowWords($this->_client_ver, $this->_last_version, $max_words_num);
 
            if($show_words === false){
                $show_words=array();
            }
            GFunc::cacheSet(self::CF_SHOW_WORDS_CACHE_KEY, $show_words, GFunc::getCacheTime('2hours'));
        }
    
        $this->_return_array['words'] = $show_words;
    }
    

    
    /**
     * 获取热词展现样式
     *
     * @param string $os 系统类型:android/ios
     * (android与ios的展现方式完全不同)
     * @return 
     */
    private function _getShowContentHtmlStyle($os) {
        $cache_key = self::CF_SHOW_CONTENT_HTML_STYLE_CACHE_KEY_PREFIX.$os;
        $html_style = GFunc::cacheGet($cache_key);
        if ($html_style === false) {
            $hot_words =  IoCload('models\\HotWordsModel');
            $html_style = $this->hot_words->getHotWordsShowContentHtmlStyle($os);
            GFunc::cacheSet($cache_key, $html_style, GFunc::getCacheTime('2hours'));
        }
    
        return $html_style;
    }
    
    /**
     * 获取新版本热词内容(分页)
     *
     * @param $version int 最大取到热词的版本
     * @param $page int 页码
     * @param $type array 跳转到搜索的热词类型
     * @param $limit 分页显示pagesize
     * @return 
     */
    private function getShowWordsOfNewByPage($version, $page, $type, $limit = self::CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY, $clientVersion=0) {
        $platform = strtolower($this->platform);
        if (stripos($platform, 'a1') !== false){
            $platform = 'a1';
        }
        $cache_key = self::CF_SHOW_WORDS_NEW_CACHE_KEY_PREFIX . $platform . '_' . $version . '_' . $page . '_' . $limit ;
        $show_words_content = GFunc::cacheGet($cache_key);
        if ($show_words_content === false) {
            $show_words_content = $this->hot_words->getHotWordsContentDesc($platform, $version, $type, $page, $limit,'1000572f' === $this->cfrom, $clientVersion);
            if (is_array($show_words_content) && empty($show_words_content)) {
                GFunc::cacheSet($cache_key, $show_words_content, 3);
            } elseif (false !== $show_words_content) {
                GFunc::cacheSet($cache_key, $show_words_content);
            }
        }
    
        return $show_words_content;
    }
    
    /**
     * 获取新版本热词内容(总页数)
     *
     * @param $version int 最大取到热词的版本
     * @return
     */
    private function _getShwoWordsOfNewTotalPage($version) {
        $platform = strtolower($this->platform);
        $limit = self::CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY;
    
        $cache_key = self::CF_SHOW_WORDS_PAGE_NUM_CACHE_KEY_PREFIX.$platform.'_'.$version;
        $total_num = GFunc::cacheGet($cache_key);
        if ($total_num === false) {
            $total_num = $this->hot_words->getHotWordsContentDescTotalPage($platform, $version, $limit);
            GFunc::cacheSet($cache_key, $total_num);
        }
    
        return intval($total_num);
    }
    
    /**
     * 获取定向cuid推送app数据
     * @return
     */
    private function _getCuidPushAppRecommendData() {
        $platform = strtolower($this->platform);
        if (stripos($platform, 'a1') !== false){
            $platform = 'a1';
        }
        $version = str_replace('-', '.', $this->version);
        $cache_key = self::CF_CUID_PUSH_APP_RECOMMEND_DATA_CACHE_KEY_PREFIX.$platform.'_'.$version;
        $app_recommend_data = GFunc::cacheGet($cache_key);
        if ($app_recommend_data === false) {
            $app_recommend_data = $this->hot_words->getCuidPushAppRecommendData($platform, $version);
            GFunc::cacheSet($cache_key, $app_recommend_data);
        }
    
        $retdata = array();
        foreach ($app_recommend_data as $key => $value) {
            if ('1000572f' !== $this->cfrom && //屏蔽对googleplay的app推送
                ($value['channel'] === 'all' || $value['channel'] === $this->cfrom)) {
                // 看是否为cuid定向
                if (intval($value['cuidpush']) === 1) {
                    $cache_key = self::CF_CUID_PUSH_APP_RECOMMEND_CUID_CACHE_KEY_PREFIX . $value['id'] . '_' . $this->cuid;
                    // 看cuid是否在定向cuid列表中
                    $is_in_cuid_list = GFunc::cacheGet($cache_key);
                    if ($is_in_cuid_list !== false && intval($is_in_cuid_list) === 1) {
                        $retdata[] = $value;
                    }
                } else {
                    $retdata[] = $value;
                }
            }
        }
    
        // 去除不必要的字段
        foreach ($retdata as $key => $value) {
            unset($retdata[$key]['id']);
            unset($retdata[$key]['expired_time']);
            unset($retdata[$key]['platform']);
            unset($retdata[$key]['version']);
            unset($retdata[$key]['channel']);
            unset($retdata[$key]['cuidpush']);
            unset($retdata[$key]['cuidpush_file']);
            unset($retdata[$key]['create_time']);
            unset($retdata[$key]['status']);
        }
    
        return $retdata;
    }
    
    /**
     * 从wise获得app推荐数据
     *
     * @param $cuid string 用户cuid
     * @param $show_type int app推荐的展示类型, 与热词推荐展示类型相关
     * @return
     */
    private function _getAppRecommendDataFromWise($cuid, $show_type) {
        $wise_apprec_url = $this->wise_apprec_url . '&from=' . $this->wise_apprec_from . '&token=' . $this->wise_apprec_token;
    
        /**
         * returntype 返回类型
         * 0 : 默认字段
         * 1 : 全量字段
         * 2 : 页面
         */
        $returntype = 0;
    
        /**
         * rn 需要一次返回多少
         * 最小为1, 一次最多50, 默认15
         */
        $rn = 15;
    
        /**
         * rec_type 请求的推荐是什么类型, 只能为以下四种值
         * 001 : 相关推荐
         * 002 : 个性化推荐
         * 003 : 同时包含相关和个性化推荐，但两者分开存放
         * 004 : 同时包含相关和个性化推荐，但两者混合排序
         */
        $rec_type = '002';
    
        /**
         * pu 通用携带参数
         * 在页面链接中会传递, 多组数据以“,”分割, 每组数据内kv以@分割
         */
        $pu = 'cuid@' . $cuid;
    
        $wise_apprec_url .= '&pu=' . $pu . '&returntype=' . $returntype . '&rn=' . $rn . '&rec_type=' . $rec_type;

        
        $Orp_FetchUrl = new \Orp_FetchUrl();
        $httpproxy = $Orp_FetchUrl->getInstance(array('timeout' =>1000));
        
        $result = $httpproxy->get($wise_apprec_url);
        $err = $httpproxy->errmsg();
        
        $retdata = array(); 
          
        if(!$err && $httpproxy->http_code() == 200) {
            $result = json_decode($result, true);

            $app_score_array = array ();
            if (isset ( $result ['result'] ['apps'] ) && ! empty ( $result ['result'] ['apps'] )) {
                foreach ( $result ['result'] ['apps'] as $key => $value ) {
                    // 竞品相关的不加入推荐
                    if (strpos ( $value ['sname'], '输入法') === false) {
                        $app_score_array [$key] = $value ['score'];
                    }
                }
            }
            arsort ( $app_score_array );
            	
            $apprec_record = array ();
            $total_num_from_wise = count ( $app_score_array );
            if ($total_num_from_wise < self::CF_APP_RECOMMEND_NUM_FROM_WISE) {
                $max_count = $total_num_from_wise;
            } else {
                $max_count = self::CF_APP_RECOMMEND_NUM_FROM_WISE;
            }
            $count = 0;
            foreach ( $app_score_array as $key => $value ) {
                if ($count < $max_count) {
                    $apprec_record ['pic'] = $result ['result'] ['apps'] [$key] ['icon'];
                    $apprec_record ['word'] = $result ['result'] ['apps'] [$key] ['sname'];
                    $apprec_record ['word_desc'] = trim ( str_replace ( '<br>', '', $result ['result'] ['apps'] [$key] ['brief'] ) );
                    $apprec_record ['link'] = GFunc::getGlobalConf('domain_v5') . '/v5/j/s/?url=' . urlencode ( $result ['result'] ['apps'] [$key] ['download_url'] );
                    $apprec_record ['type'] = $show_type;
                    	
                    $retdata [] = $apprec_record;
                    $count ++;
                } else {
                    break;
                }
            }
        }
        
        
        return $retdata;
    }
    
    /**
     * @route({"GET","/cf2"})
     * 简单版流行词检测接口
     * 返回最新版本及三个更新词条
     * @return
     */
    public function cf2Action() { 
        $this->_getSummary();
        $this->_return_array['summary'].='...';
        Util::outputJsonResult($this->_return_array);
    }
    

    /**
     * @route({"GET","/cf3"})
     * 带词条搜索跳转的检测接口
     * @return
     */
    public function cf3Action() {

        $this->_return_array['wfmaxdays'] = self::MAX_REMAIN_DAYS;
        $this->_return_array['wfmaxnums'] = self::MAX_REMAIN_NUMBER;
    
        $this->_getShowWords(self::MAX_REMAIN_NUMBER);
        	
        Util::outputJsonResult($this->_return_array);
    }
    
   
    /**
     * @route({"GET","/cf4"})
     * 暗藏SDK上传BBM内容开关的简单版流行词检测接口
     * 如果us为客户端是否参与用户体验改善计划
     * 参与的用户打开采集，不参与的用户按照6分之一采集
     * 当客户端检测到摘要结尾的省略号为......时，上传数据，否则不传
     * @return
     */
    public function cf4Action() {
        $this->_getSummary();
        $us=intval($_REQUEST["us"]);
    
        if( $us===1
            || ($this->uid!=='' && (hexdec(substr(md5($this->uid), 0, 5)) % self::CF4_SDK_UPLOAD_USERINFO_RATE===(floor(time()/(3600*24)) % self::CF4_SDK_UPLOAD_USERINFO_RATE)))){
            $this->_return_array['summary'].='......';
        }else {
            $this->_return_array['summary'].='...';
        }
    
        Util::outputJsonResult($this->_return_array);
    }
    
    
    /**
     * @route({"GET","/sr"})
     * @return
     */
    public function srAction(){
        $words = array();
        $words['words'] = array();
        //获取html内容的头和尾
        $html_style = self::_getShowContentHtmlStyle($this->_os);
        	
        //如果热词样式没取到
        if (empty($html_style) || !isset($html_style['html_header']) || !isset($html_style['html_footer'])) {
            header('X-PHP-Response-Code: '. 500, true, 500);
            return;
        }
        	
        $jump_to_search_type = array();
        foreach ($html_style['type'] as $key => $value) {
            $jump_to_search_type[intval($value)] = true;
        }
    
        $hotwords_content = $this->getShowWordsOfNewByPage($this->_last_version, 1, $jump_to_search_type);
        $count = (count($hotwords_content) > 10)? 10 : count($hotwords_content);
        for($i = 0; $i < $count; $i++) {
            $word = array();
            if (null === $hotwords_content[$i]['word']){
                continue;
            }
            $word['word'] = $hotwords_content[$i]['word'];
            array_push($words['words'], $word);
        }
    
        Util::outputJsonResult($words);
    }
    
    /**
     * @route({"GET","/cf5"})
     * 新版本热词展现接口
     * 返回json格式数据
     * @return
     */
    public function cf5Action() {
        
        //增加参数pagesize
        $pagesize = isset($_REQUEST["pagesize"]) ? intval($_REQUEST["pagesize"]) : self::CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY;
        if ($pagesize <= 0){
            $pagesize = self::CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY;
        }
    
        //热词展现有更新
        if ($this->_last_version > $this->_client_ver) {
            //获取html内容的头和尾
            $html_style = self::_getShowContentHtmlStyle($this->_os);
            	
            //如果热词样式没取到
            if (empty($html_style) || !isset($html_style['html_header']) || !isset($html_style['html_footer'])) {
                $this->_return_array['status'] = 0;
                $this->_return_array['msg'] = self::NO_UPDATE_MSG;
            }
            	
            $jump_to_search_type = array();
            foreach ($html_style['type'] as $key => $value) {
                $jump_to_search_type[intval($value)] = true;
            }
            	
            //兼容通用ua, cuid字段加密失败的bug
            if (!mb_check_encoding($this->ua, 'UTF-8')) {
                $this->ua = 'bd_640_960_unknown_'.$this->version.'_'.$this->platform;
            }
            //cuid前半部分为baiduinput的md5
            if (!mb_check_encoding($this->cuid, 'UTF-8')) {
                $this->cuid = 'C4A3DFD1795F2667D0291900BF658761|000000000000000';
            }
           	
            $result_page = '';		//返回html内容
            $pic_array = array();	//返回html内容包含的图片(客户端自己下载)
            	
            $result_page .= $html_style['html_header'];
            
            //屏蔽googleplay
            if ($this->_os === 'android' && '1000572f' !== $this->cfrom ) {
                // 获取wise推荐的app数据
                // 这里3表示图文app推荐

                $apprec_data_from_wise = $this->_getAppRecommendDataFromWise ( $this->cuid, 3 );
                foreach ( $apprec_data_from_wise as $key => $value ) {
                    $temphtml = $html_style ['style' . $value ['type']] ['content'];
                    $temphtml = str_replace ( 'src="#"', 'src="' . $value ['pic'] . '"', $temphtml );
                    $temphtml = str_replace ( '<h2 class="title">#</h2>', '<h2 class="title">' . $value ['word'] . '</h2>', $temphtml );
                    // 显示字数处理
                    $max_show_words = intval ( $html_style ['style' . $value ['type']] ['max_show_words'] );
                    $temphtml = str_replace ( '<p>#</p>', '<p>' . $value ['word_desc'] . '</p>', $temphtml );
                    	
                    // app下载链接加上通用统计参数
                    $applink = $value ['link'];
                    if (strpos ( $applink, '?' ) !== false) {
                     $applink .= '&cuid=' . $this->cuid . '&ua=' . $this->ua;
                    } else {
                     $applink .= '?cuid=' . $this->cuid . '&ua=' . $this->ua;
                    }
                    	
                    $temphtml = str_replace ( 'href="#"', 'href="' . $applink . '"', $temphtml );
                    $temphtml = str_replace ( 'rel="#"', 'rel="' . $applink . '"', $temphtml );
                    	
                    $result_page .= $temphtml;
                }
            }
        	
            //获取cuid定向推送部分数据
            $app_recommend_data = $this->_getCuidPushAppRecommendData();
            //将cuid定向推送部分数据放到热词头部
            foreach ($app_recommend_data as $key => $value) {
                $pic_array[] = $this->micweb_httproot .$value['pic'];
                $piclink = $value['pic'];
                $piclink_array = explode('/', $piclink);
                $picname = $piclink_array[count($piclink_array) - 1];
                    	
    			$temphtml = $html_style['style'.$value['type']]['content'];
    			$temphtml = str_replace('src="#"', 'src="'.$picname.'"', $temphtml);
                $temphtml = str_replace('<h2 class="title">#</h2>', '<h2 class="title">'.$value['word'].'</h2>', $temphtml);
    		    //显示字数处理
    			$max_show_words = intval($html_style['style'.$value['type']]['max_show_words']);
       
    		    $temphtml = str_replace('<p>#</p>', '<p>'.$value['word_desc'].'</p>', $temphtml);
    		   
    		    //app下载链接加上通用统计参数
    			$applink = $value['link'];
    		    if (strpos($applink, '?') !== false) {
    		        $applink .= '&cuid='.$this->cuid.'&ua='.$this->ua;
                } else {
                    $applink .= '?cuid='.$this->cuid.'&ua='.$this->ua;
                }
        
                $temphtml = str_replace('href="#"', 'href="'.$applink.'"', $temphtml);
                $temphtml = str_replace('rel="#"', 'rel="'.$applink.'"', $temphtml);
                	
                $result_page .= $temphtml;
            }
        	
            $hotwords_content = $this->getShowWordsOfNewByPage($this->_last_version, 1, $jump_to_search_type, $pagesize);
            foreach ($hotwords_content as $key => $value) {
                $pic_array[] = $this->micweb_httproot . $value['pic'];
                $piclink = $value['pic'];
                $piclink_array = explode('/', $piclink);
                $picname = $piclink_array[count($piclink_array) - 1];
        
                $temphtml = $html_style['style'.$value['type']]['content'];
                $temphtml = str_replace('src="#"', 'src="'.$picname.'"', $temphtml);
                $temphtml = str_replace('<h2 class="title">#</h2>', '<h2 class="title">'.$value['word'].'</h2>', $temphtml);
                //显示字数处理
                $max_show_words = intval($html_style['style'.$value['type']]['max_show_words']);
                if (mb_strlen($value['word_desc'], 'utf8') <= $max_show_words) {
                    $temphtml = str_replace('<p>#</p>', '<p>'.$value['word_desc'].'</p>', $temphtml);
                } else {
                    $temphtml = str_replace('<p>#</p>', '<p>'.mb_substr($value['word_desc'], 0, $max_show_words, 'utf-8').'...</p>', $temphtml);
                }
        
                //链接加上通用统计参数
                $applink = $value['link'];
                if (strpos($applink, '?') !== false) {
                    $applink .= '&cuid='.$this->cuid.'&ua='.$this->ua;
                } else {
                    $applink .= '?cuid='.$this->cuid.'&ua='.$this->ua;
                }
            
                $temphtml = str_replace('href="#"', 'href="'.$applink.'"', $temphtml);
                $temphtml = str_replace('rel="#"', 'rel="'.$applink.'"', $temphtml);
                
                $result_page .= $temphtml;
            }
    	
            //html_footer内容需要进行替换, 包括版本号
            $html_footer = $html_style['html_footer'];
            	
            $html_footer = str_replace('rel="#"', 'rel="'.$this->micweb_httproot .'__'.$this->_last_version.'__'.$this->ua.'__'.$this->cuid.'__'.$pagesize.'"', $html_footer);
        
            $html_footer = str_replace('http://hw.request.url/', $this->micweb_httproot , $html_footer);
            $result_page .= $html_footer;
            $pic_array[] = 'https://srf.baidu.com/images/hw/more.png';
            $pic_array[] = 'https://srf.baidu.com/images/hw/more_active.png';
            if (trim($result_page) === '') {
                $this->_return_array['status']=0;
                $this->_return_array['msg']=self::NO_UPDATE_MSG;
            } else {
                $this->_return_array['content'] = $result_page;
                $this->_return_array['pic'] = $pic_array;
            }
            	
            Util::outputJsonResult( $this->_return_array);
        
        }
        
	}
    
        
    /**
    * @route({"GET","/cf6"})
    * 新版本热词展现分页数据下发接口
    * (内部调用, 不对外开放)
    * @return
    */
    public function cf6Action() {
        //增加参数pagesize
        $pagesize = isset($_REQUEST["pagesize"]) ? intval($_REQUEST["pagesize"]) : self::CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY;
        if ($pagesize <= 0){
            $pagesize = self::CF5_MAX_SHOWNUM_PER_PAGE_CACHE_KEY;
        }
        
        $page = isset($_REQUEST["page"]) ? intval($_REQUEST["page"]) : 1;
        $version =  intval($_REQUEST["version"]) ;
        
        //获取html内容的头和尾
        $html_style = self::_getShowContentHtmlStyle($this->_os);
        //如果热词样式没取到
        if (empty($html_style) || !isset($html_style['html_header']) || !isset($html_style['html_footer'])) {
            $retdata['total'] = 0;
            $retdata['current'] = $page;
            Util::outputJsonResult($retdata);
        }
        
        $jump_to_search_type = array();
        foreach ($html_style['type'] as $key => $value) {
            $jump_to_search_type[intval($value)] = true;
        }
        
        	//兼容通用ua, cuid字段加密失败的bug
    	if (!mb_check_encoding($this->ua, 'UTF-8')) {
    	   $this->ua = 'bd_640_960_unknown_'.$this->version.'_'.$this->platform;
        }
        //cuid前半部分为baiduinput的md5
        if (!mb_check_encoding($this->cuid, 'UTF-8')) {
            $this->cuid = 'C4A3DFD1795F2667D0291900BF658761|000000000000000';
        }
        
        $retdata = array();
        $retdata['total'] = $this->_getShwoWordsOfNewTotalPage($version);
        $retdata['current'] = $page;
        $retdata['urlprefix'] = $this->micweb_httproot;
        $content = $this->getShowWordsOfNewByPage($version, $page, $jump_to_search_type, $pagesize);
        foreach ($content as $key => $value) {
            $max_show_words = intval($html_style['style'.$value['type']]['max_show_words']);
            if (mb_strlen($value['word_desc'], 'utf8') > $max_show_words) {
                $content[$key]['word_desc'] = mb_substr($value['word_desc'], 0, $max_show_words, 'utf-8').'...';
            }
            	
            $applink = $content[$key]['link'];
            if (strpos($applink, '?') !== false) {
                $applink .= '&cuid='.$this->cuid.'&ua='.$this->ua;
            } else {
                $applink .= '?cuid='.$this->cuid.'&ua='.$this->ua;
            }
            	
            $content[$key]['link'] = $applink;
        }
        
        $retdata['content'] = $content;
        Util::outputJsonResult($retdata);
    }
        
        
        


    /**
     * @route({"GET","/cf7"})
     * 用以ios8 5.3以上热词web化
     * 返回json格式数据
     * @return
     */
    public function cf7Action() {
        
        if ($this->_last_version > $this->_client_ver) {
            //获取html内容的头和尾
            $html_style = self::_getShowContentHtmlStyle($this->_os);
            $jump_to_search_type = array();
            foreach ($html_style['type'] as $key => $value) {
                $jump_to_search_type[intval($value)] = true;
            }
            $hotwords_content = $this->getShowWordsOfNewByPage($this->_last_version, 1, $jump_to_search_type, self::CF7_IOS_DEFAULT_PAGESIZE, $this->clientVersion);
            foreach ($hotwords_content as $key => $value) {
                $hotwords_content[$key]['pic'] = !empty($value['pic']) ? $this->getImageTranscodeUrl($this->bos_domain_https . $value['pic'],'b100_100') : '';
                $hotwords_content[$key]['audio_path'] = !empty($value['audio_path']) ? $this->bos_domain_https . $value['audio_path'] : '';
            }
            $return = array(
                'status'=>1,
                'lastversion'=> $this->_last_version,
                'content'=>$hotwords_content,
            );
            Util::outputJsonResult( $return);
        }   
    }
            
            

    /**
     * 获取转换地址
     * @param unknown $strUrl
     * @param unknown $strPix
     * @return string
     */
    public function getImageTranscodeUrl($strUrl, $strPix){
        //Edit by fanwenli on 2018-08-08, set bos domain with conf
        $strBosDomain = Util::getGFuncGlobalConf();
        
        //云转码无法解析imeres.bj.bcebos.com域名的图片
        $strUrl =  str_replace('http://imeres.bj.bcebos.com',$strBosDomain,$strUrl);
        $strUrl =  str_replace('https://imeres.bj.bcebos.com',$strBosDomain,$strUrl);
        
        $strTime = 1431314958;
        $strUrl  = str_replace('}', '%7d', str_replace('{', '%7b', $strUrl ));
        $strDi = md5(self::IMAGE_TRANSCODE_TOKEN . $strTime . $strUrl);
        $strTransUrl = self::IMAGE_TRANSCODE_URL . '&size=' . $strPix . '&sec=' . $strTime . '&di=' . $strDi . '&src=' . urlencode($strUrl);
        return $strTransUrl;
    }
    
    


    /**
     * @route({"GET","/df"})
     * 下载流行词接口
     * @return
     */
    public function dfAction(){
        $strVersion = str_replace('-', '.', $this->version);
        
        if(1 === intval($_GET['newfmt']) || in_array($_GET['platform'], array('i9','i10','a11')) ) {
            $this->bolIsNewFormat = true;   
        }
          
        //客户端版本和当前服务端最新版本相差超过60则直接获取全量包
        if(!(('ios' === Util::getPhoneOS($this->platform)) 
                    && (version_compare($strVersion, self::DF_HW_IOS_BUG_MAX_VER, '<')) 
                    && (($this->_last_version - $this->_client_ver) > self::DF_HW_IOS_LAST_VER_DIFF_MAX)) 
                && ($this->_client_ver > 0) 
                && (($this->_last_version - $this->_client_ver) <= 60)){
            Util::dlIncrWordslib(1000, $this->_client_ver, $this->_last_version, $this->bolIsNewFormat );
        }else{
            Util::dlFullWordslib(1000, $this->_last_version, $this->bolIsNewFormat );
        }
        //上面函数如果有相应的输出文件，函数会直接header到文件地址并exit,否则程序继续执行一下内容
        
        /*
         * 其他异常，返回无更新
         */
        $this->_return_array['status'] = 0;
        $this->_return_array['msg'] = self::NO_UPDATE_MSG;
        Util::outputJsonResult($this->_return_array);
    
    
    }
    
    
    
}
