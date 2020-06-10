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
use models\CellWordsBaseModel;
use models\WordlibModel;
use models\AppSceneWordslibModel;

/**
 *
 * wordlib
 * 说明：词库相关接口
 *
 * @author zhoubin
 * @path("/wordlib/")
 */
class Wordlib
{

    
	/**
	 *
	 * memcache 中词库最新版本的key的前缀
	 * @var string
	 */
	const LATEST_VERSION_CACHE_KEY_PERFIX ='ime_api_v5_wl_latest_version_';


	/**
	 *
	 * memcache 中词库分页的key的前缀
	 * @var string
	 */
	const WORDLIB_MARKET_CACHE_KEY_PRE ='ime_api_v5_wordlib_m_';

	/**
	 *
	 * memcache 中词库搜索联想的key的前缀
	 * @var string
	 */
	const WORDLIB_SEARCH_CACHE_KEY_PRE ='ime_api_v5_wordlib_s_';

	/**
	 *
	 * memcache 中词库搜索结果的key的前缀
	 * @var string
	 */
	const WORDLIB_SEARCH_RESULT_CACHE_KEY_PRE ='ime_api_v5_wordlib_r_';

	/**
	 *
	 * memcache 中词库分类的key的前缀
	 * @var string
	 */
	const WORDLIB_CATEGORY_CACHE_KEY_PRE ='ime_api_v5_wordlib_c_';

	/**
	 *
	 * memcache 中词库的key的前缀
	 * @var string
	 */
	const WORDLIB_DOWNLOAD_CACHE_KEY_PRE='ime_api_v5_wordlib_d_';


	/**
	 *
	 * memcache 搜索首页加热词前缀
	 * @var string
	 */
	const SEARCH_WORDLIB_CACHE_KEY_PRE='ime_api_v5_search_wordlib_';

	/**
	 *
	 * 搜索首页加热词个数
	 * @var string
	 */
	const MAX_SEARCH_HOT_WORD_COUNT = 10;

	/**
	 *
	 * 词库默认pagesize
	 * @var string
	 */
	const DEFAULT_PAGESIZE = 12;

	/**
	 * memcache缓存默认过期时间(单位: 秒)
	 * @var int
	 */
	const CACHE_EXPIRED_TIME = 600;
	
	/**
	 *
	 * 主线7.8词库打包工具增加
	 * 1.地理词库（0-普通，1-临时地理， 2-常住地）
	 * 2.用户可见（0不隐藏 1隐藏）
	 * 7.8以后版本下发新格式
	 * @var string
	 */
	const WORD_NEW_FORMAT_MIN_VER = '7.8.0.0';

	/**
	 * 当前词库最新版本
	 *
	 * @var int
	 */
	private $_latest_version = 0;

	 /**
     * 
     * @return
     */
	public function __construct(){
		
	}

	/**
	 * 获取最新版本
	 *
	 * @param int $id 相应词库的id
	 *
	 */
	private function __getLatestVersion($id){


		$this->_latest_vaersion_cache_key = self::LATEST_VERSION_CACHE_KEY_PERFIX . $id;

		$this->_latest_version	= Gfunc::cacheGet($this->_latest_vaersion_cache_key);

		if ($this->_latest_version === false){
			/*
			 *缓存中没有最新版本的，数据库里读取数据，写入缓存
			 */
			$cell_words = new CellWordsBaseModel($id);

			$this->_latest_version = $cell_words->getLastVersion();

			if($this->_latest_version === false){
				$this->_latest_version=0;
			}

			 Gfunc::cacheSet($this->_latest_vaersion_cache_key, $this->_latest_version, GFunc::getCacheTime('2hours'));
		}else{

			$this->_latest_version = intval($this->_latest_version);
		}
	}

	
	/**
	 * @route({"GET","/m"})
	 *  词库分页
	 * @return
	 */
	public function mAction(){
		//新增参数  默认12
		
		$pagesize = intval( Util::getQuery("pagesize", self::DEFAULT_PAGESIZE));

   		$orderby = 'id';

		//分类
		$cate = Util::getQuery("cate",'recommend');

	   	//关键字
	   	$keywords = Util::getQuery("keywords",'');
	   	$keywords = strip_tags($keywords);
	   	$replacechars = array('%','\'','"','#','\\');
	   	$keywords = str_replace($replacechars, "", $keywords);
	   	//当前页码
		$page = intval( Util::getQuery("page",0));

		$this_market_pagekey = self::WORDLIB_MARKET_CACHE_KEY_PRE . $cate . '_' . $page . '_' . $pagesize . '_' . urlencode($keywords);
		$cellpage= Gfunc::cacheGet($this_market_pagekey);
		/*
		 * cache中没有，从数据库中获取
		 */
		if($cellpage===false){

			//$pagesize = 12;

			//返回数据
			$cellpage = array();

			//分类别查询
			$real_cate=$cate;

			$condition="";
			$condition_args=array();

			//流行词$id判断条件
			$condition.='id > ? ';
			array_push($condition_args, '1000');
			$condition.=' and id < ? ';
			//edit by zhoubin 20180111 50000~60000 ID范围词库在客户端词库商店不可见 
			array_push($condition_args, '50000');
;
			if($cate === 'recommend'){
	   			$orderby = 'orders desc, download_number+add_downloads desc, id desc';
	   			$real_cate = null;
	   		}

	   		//全部
	   		if($cate === 'all'){
	   			$orderby = 'download_number+add_downloads desc, id desc';
	   			$real_cate = null;
	   		}

	   		//keywords字段
	   		if(trim($keywords) !== ''){
	   			$condition .= ' and (';
	   			$likefieldsarr = array('title', 'author', 'content_text', 'tags');
	   			foreach ($likefieldsarr as $likefield){
	   				$worldsarr = explode(' ', $keywords);
	   				if($keywords !== ''){
	   					foreach ($worldsarr as $likeword){
	   						$condition .= $likefield . ' like ? '.' or ';
	   						array_push($condition_args, '%'.$likeword.'%');
	   						//$condition.=$likefield." LIKE '%$likeword%'".' or ';
	   					}
	   				}
	   			}
	   			$condition = trim($condition,' or ');
	   			$condition.=')';
	   		}

	   		if($cate === 'hot'){

	   			if($page < 1){
	   				$page = 1;
	   			}
	   			if($page > 3){
	   				$page = 3;
	   			}
	   			$limit = ($page - 1) * $pagesize;

	   			$wordlibmodel = new WordlibModel();
	   			$wordlibdata_top36 = $wordlibmodel->getTop36Wordlib($limit, $pagesize);
				$wordlibdata['data'] = $wordlibdata_top36;
	   			$cellpage['pageinfo']['total']		=3;
	   	   		$cellpage['pageinfo']['current']		=$page;
	   	   		$cellpage['pageinfo']['count']		=36;

	   		}
	   		elseif ($cate === 'last'){//最新
	   			$real_cate = null;
	   			//最新不显示本地化词库、城市地理及其子分类下的词库 (50000~60000 ID范围词库在客户端词库商店不可见)
	   			$condition =' w.id > 1000 and w.id < 50000 and w.category not in (SELECT c1.category_id FROM input_wordslib_categories c1 WHERE c1.category_name = ? OR c1.parent_id = (SELECT c2.category_id FROM input_wordslib_categories c2 WHERE c2.category_name = ?)) ';
	   			$condition_args = array();
	   			array_push($condition_args, '城市地理');
	   			array_push($condition_args, '城市地理');
	   			$orderby = ' d.pub_time DESC ';
	   			$wordlibmodel = new WordlibModel();
	   		
	   			$wordlibdata = $wordlibmodel->getMarketPage($condition, $condition_args, $orderby, $pagesize, $page, $cate);
	   		
	   			$cellpage['pageinfo']['total'] = $wordlibdata['total_page'];
	   			$cellpage['pageinfo']['current'] = $wordlibdata['current_page'];
	   			$cellpage['pageinfo']['count'] = $wordlibdata['total_record'];
	   		}
	   		else{

	   			//cate字段
	   			if($real_cate !== null){
			   		//if(isset($all_cates[$real_cate])){
			   		    // edit by zhoubin05 20171211 (50001~60000 ID范围词库在客户端词库商店不可见)
						$condition .= ' and category=? and (id < 50000 OR id > 60000) ';
						array_push($condition_args, $real_cate);
			   		//}
				}

				$wordlibmodel = new WordlibModel();
				$wordlibdata = $wordlibmodel->getMarketPage($condition,$condition_args,$orderby,$pagesize,$page,$cate);
				//print_r($orderby);
				//print_r($condition_args);
				$cellpage['pageinfo']['total'] = $wordlibdata['total_page'];
	   	   		$cellpage['pageinfo']['current'] = $wordlibdata['current_page'];
	   	   		$cellpage['pageinfo']['count'] = $wordlibdata['total_record'];
	   		}

	   	   	$cellpage['cellinfo']['domain']	= GFunc::getGlobalConf('micweb_httproot');

	   	   	$cellpage['cellinfo']['celllist']=array();
			foreach ($wordlibdata['data'] as $wordlib){
		   		$onewordlib=array();

		   		$onewordlib['wordlib']['id'] = intval($wordlib['id']);
		   		$onewordlib['wordlib']['name'] = $wordlib['title'];
		   		$onewordlib['wordlib']['wordsnum'] = $wordlib['words_number'];
		   		$onewordlib['wordlib']['version'] = $wordlib['version'];
		   		$onewordlib['wordlib']['date'] = $wordlib['online_time'];
		   		$onewordlib['wordlib']['keywords'] = $wordlib['keywords'];
		   		$onewordlib['wordlib']['dlink'] ='v5/wordlib/d/?id=' . $wordlib['id'];

				array_push($cellpage['cellinfo']['celllist'], $onewordlib['wordlib']);
			}
			 Gfunc::cacheSet($this_market_pagekey,$cellpage);
			//print_r($cellpage);
		}
		//print_r($cellpage);
		return $cellpage;
	}

	/**
	 * @route({"GET","/s"})
	 * 词库搜索联想&结果.android没有联想，分页都是每页12个
	 * @return
	 */
	public function sAction(){
	    
		//类型，0搜索联想1搜索结果
		$type = intval( Util::getQuery("type",0));
		$page = intval( Util::getQuery("page",''));
		//关键字,urlencode后的
		$keywords	= Util::getQuery("keywords",'');
		$keywords = strip_tags($keywords);
		$replacechars = array('%','\'','"','#','\\');
		$keywords= trim(urldecode(str_replace($replacechars, "", $keywords)));
	
		//关键字为空时返回空集
		$wordsarr = explode(' ', $keywords);
		if ($keywords === '' || (!is_array($wordsarr) || count($wordsarr) < 1)) {
		   
			$cellpage = array();
			$cellpage['pageinfo']['total']		= 1;
			$cellpage['pageinfo']['current']	= 1;
			$cellpage['pageinfo']['count']		= 0;
			$cellpage['cellinfo']['domain']	= GFunc::getGlobalConf('micweb_httproot');
			$cellpage['cellinfo']['celllist'] = array();
		}
		else{
		  
			$this_search_pagekey = ($type ? self::WORDLIB_SEARCH_RESULT_CACHE_KEY_PRE : self::WORDLIB_SEARCH_CACHE_KEY_PRE) . '_' . $page . '_' . urlencode($keywords);
			$cellpage= Gfunc::cacheGet($this_search_pagekey);
			/*
			 * cache中没有，从数据库中获取
			*/
			if($cellpage === false){
			 
				$pagesize = 12;
				$page = empty($page) ? 1 : intval($page);

				//返回数据
				$cellpage = array();

				$feild_arr = array('w.title');
				if ($type !== 0) {
					array_push($feild_arr, 'w.keywords');
				}
				$wordlibmodel = new WordlibModel();
				$wordlibdata = $wordlibmodel->getSuggestResult($wordsarr, $pagesize, $page, $feild_arr);
	
				$cellpage['pageinfo']['total']		= $wordlibdata['total_page'];
				$cellpage['pageinfo']['current']	= $wordlibdata['current_page'];
				$cellpage['pageinfo']['count']		= $wordlibdata['total_record'];

				$cellpage['cellinfo']['domain']	= GFunc::getGlobalConf('micweb_httproot');

				$cellpage['cellinfo']['celllist']=array();
				foreach ($wordlibdata['data'] as $wordlib){
					$onewordlib=array();
					$onewordlib['wordlib']['id'] = intval($wordlib['id']);
					$onewordlib['wordlib']['name'] = $wordlib['title'];
					$onewordlib['wordlib']['wordsnum'] = $wordlib['words_number'];
					$onewordlib['wordlib']['version'] = $wordlib['version'];
					$onewordlib['wordlib']['date'] = $wordlib['online_time'];
					$onewordlib['wordlib']['keywords'] = $wordlib['keywords'];
					$onewordlib['wordlib']['dlink'] ='v5/wordlib/d/?id=' . $wordlib['id'];
					array_push($cellpage['cellinfo']['celllist'], $onewordlib['wordlib']);
				}
				 Gfunc::cacheSet($this_search_pagekey,$cellpage);
			}
		}
		if ($type && (strtolower(substr($_GET['platform'], 0, 1)) == 'i')) {
			//总返回热门词库top20
			$this_market_pagekey=self::WORDLIB_MARKET_CACHE_KEY_PRE.'hot_top20';
			$cellhot= Gfunc::cacheGet($this_market_pagekey);
			if ($cellhot === false) {
				$pagesize = 20;

				//返回数据
				$cellhot = array();

				$wordlibmodel=new WordlibModel();
				//sql opt
				$wordlibdata_topN = $wordlibmodel->getTopNWordlib(0, $pagesize);


				$cellhot['cellhot']['celllist']=array();
				if (is_array($wordlibdata_topN) && count($wordlibdata_topN) > 0) {
					foreach ($wordlibdata_topN as $wordlib){
						$onewordlib=array();
						$onewordlib['wordlib']['id'] = intval($wordlib['wordslib_id']);
						$onewordlib['wordlib']['name'] = $wordlib['title'];
						array_push($cellhot['cellhot']['celllist'], $onewordlib['wordlib']);
					}
					 Gfunc::cacheSet($this_market_pagekey,$cellhot);
				}
			}
			$cellpage['cellhot'] = $cellhot['cellhot'];
		}
		return $cellpage;
	}


	/**
	 * @route({"GET","/c"})
	 * 词库分类
	 * @return
	 */
	public function cAction(){
	    
	    
		//新增参数  默认12
		$pagesize = intval( Util::getQuery("pagesize", self::DEFAULT_PAGESIZE));

		//分类目录id
		$cateid =  Util::getQuery("cateid",'');
		$page =  Util::getQuery("page",'');

		$this_search_pagekey = self::WORDLIB_CATEGORY_CACHE_KEY_PRE . '_' . $cateid . '_' . $page . '_' . $pagesize;
		$cellpage =  Gfunc::cacheGet($this_search_pagekey);
		/*
		 * cache中没有，从数据库中获取
		*/
		if($cellpage === false){

			//$pagesize = 12;
			$page = empty($page) ? 0 : intval($page);

			//返回数据
			$cellpage = array();
			$orderby = '';
			$wordlibmodel = new WordlibModel();

			if ($cateid === '') {//取所有分类
				$condition = ' parent_id = -1 and category_id > 0';
				$condition_args = array();
				$wordlibdata = $wordlibmodel->getCategoryPage($condition, $condition_args, $orderby, empty($page) ? 0 : $pagesize, $page);
				$cellpage['cateinfo']['catelist']=array();
				if (is_array($wordlibdata['data']) && count($wordlibdata['data']) > 0) {
					foreach ($wordlibdata['data'] as $wordlib){
						$onewordlib=array();
//	 					$onewordlib['id'] = intval($wordlib['id']);
						$onewordlib['category_name'] = $wordlib['category_name'];
						$onewordlib['pri'] = $wordlib['pri'];
						$onewordlib['category_id'] = $wordlib['category_id'];
						$onewordlib['description'] = $wordlib['description'];
						array_push($cellpage['cateinfo']['catelist'], $onewordlib);
					}
				}
			}
			else{
				//取该分类下的所有子分类，没有子分类就取该分类下的所有词库
				$condition = ' parent_id = ? ';
				$condition_args = array($cateid);
		
				$wordlibdata = $wordlibmodel->getCategoryPage($condition, $condition_args, $orderby, empty($page) ? 0 : $pagesize, $page);
			
				if (isset($wordlibdata['data']) && is_array($wordlibdata['data']) && count($wordlibdata['data']) > 0){
					$cellpage['cateinfo']['catelist']=array();
					foreach ($wordlibdata['data'] as $wordlib){
						$onewordlib=array();
//	 					$onewordlib['id'] = intval($wordlib['id']);
						$onewordlib['category_name'] = $wordlib['category_name'];
						$onewordlib['pri'] = $wordlib['pri'];
						$onewordlib['category_id'] = $wordlib['category_id'];
						$onewordlib['description'] = $wordlib['description'];
						array_push($cellpage['cateinfo']['catelist'], $onewordlib);
					}
				}
				else{
				    //edit by zhoubin 20171211 50001~60000 ID范围词库在客户端词库商店不可见 
					$condition = ' category = ? AND status = 100 and (id < 50000 OR id > 60000) ';
					$condition_args = array($cateid);
					$wordlibdata = $wordlibmodel->getWordlibPage($condition, $condition_args, $orderby, empty($page) ? 0 : $pagesize, $page);
					$cellpage['cellinfo']['celllist']=array();
					$cellpage['cellinfo']['domain']	= GFunc::getGlobalConf('micweb_httproot');
					if (is_array($wordlibdata['data']) && count($wordlibdata['data']) > 0) {
						foreach ($wordlibdata['data'] as $wordlib){
							$onewordlib=array();
							$onewordlib['wordlib']['id'] = intval($wordlib['id']);
							$onewordlib['wordlib']['name'] = $wordlib['title'];
							$onewordlib['wordlib']['wordsnum'] = $wordlib['words_number'];
							$onewordlib['wordlib']['version'] = $wordlib['version'];
							$onewordlib['wordlib']['date'] = $wordlib['online_time'];
							$onewordlib['wordlib']['keywords'] = $wordlib['keywords'];
							$onewordlib['wordlib']['dlink'] ='v5/wordlib/d/?id=' . $wordlib['id'];
							array_push($cellpage['cellinfo']['celllist'], $onewordlib['wordlib']);
						}
					}
				}
			}
			$cellpage['pageinfo']['total']		= $wordlibdata['total_page'];
			$cellpage['pageinfo']['current']	= $wordlibdata['current_page'];
			$cellpage['pageinfo']['count']		= $wordlibdata['total_record'];

			Gfunc::cacheSet($this_search_pagekey,$cellpage);
		}

		return $cellpage;
	}


	/**
	 * @route({"GET","/d"})
	 * @param({"strVersion", "$._GET.version"}) $strVersion version输入法版本
	 * 词库下载
	 * @return
	 */
	function dAction($strVersion){
	    $strVersion = str_replace('-', '.', $strVersion);
	               
		$id	= intval( Util::getQuery("id"));
		if($id <= '1000' || $id > '100000'){
			return false;
		}

		$this_wordlib_key = self::WORDLIB_DOWNLOAD_CACHE_KEY_PRE . $id;
		$wordlib = Gfunc::cacheGet($this_wordlib_key);

		$wordlibmodel = new WordlibModel();
		if ($wordlib === false){
			$wordlib = $wordlibmodel->getOnewordlib($id);
			if(false === $wordlib) {
			    exit;
			}
			Gfunc::cacheSet($this_wordlib_key, $wordlib);
		}

		/*
		 * 恶意下载，只下载不计数
		 */
		$isbadrequest = false;

		$isbadrequest = Util::isRepeatRequest('wordlib_d_ips_cache_' . $wordlib['id'], 50);
 
		if(!$isbadrequest){
			/*
			 * 下载总数+1
			 */
			//sql opt
			//$wordlibmodel->addDownloadNum($id);

			/*
			 * 记录下载记录
			 */
			//$temp=array();
			//$temp['client_id']	=$this->uid;
			//$temp['wordslib_id']		=$wordlib['id'];
			//$temp['download_time']=date('Y-m-d H:i:s');
			//$temp['wordslib_name']	=$wordlib['title'];
			//$temp['category']		=$wordlib['category'];
			//sql opt
			//$downloadmodel=new WordlibDownloadModel();
			//$downloadmodel->saveDownload($temp);
		}

		$this->__getLatestVersion($id);
		
		$bolNewFormat = false;
		
		//如果指定了要下载新格式，则始终下载新格式。
		if(1 === intval($_GET['newfmt']) || in_array($_GET['platform'], array('i9','i10','a11')) ) {
		    $bolNewFormat = true;    
		} else {
		    if(version_compare($strVersion, self::WORD_NEW_FORMAT_MIN_VER, '>=')){
                $bolNewFormat = true;
            }    
		}
		
		
		Util::dlFullWordslib($id, $this->_latest_version, $bolNewFormat);
        //上面函数如果有相应的输出文件，函数会直接header到文件地址并exit,否则程序继续执行一下内容
        
		
	}

	
	/**
	 * @route({"GET","/sr"})
	 * 搜索首页加热词接口
	 * @return
	 */
	public function srAction(){
		$arrHotWords = array();
		$arrHotWords['words'] = array();

		$arrSrCache =  Gfunc::cacheGet(self::SEARCH_WORDLIB_CACHE_KEY_PRE);
		if (false !== $arrSrCache){
			return $arrSrCache;
		}

		$intCount = 0;
		$objModel = new WordlibModel();
		$arrContentList = $objModel->getSearchHotWords();
		foreach ($arrContentList as $arrContent){
			if ($intCount >= self::MAX_SEARCH_HOT_WORD_COUNT){
				break;
			}

			$arrLineList = explode("\n", $arrContent['content']);
			foreach ($arrLineList as $strLine){
				if ( '+' === substr($strLine, 0, 1) ){
					if ($intCount >= self::MAX_SEARCH_HOT_WORD_COUNT){
						break;
					}

					$strOneWord = str_replace('+', '', $strLine);
					$arrSplit = explode(" ", $strOneWord);
					if (isset($arrSplit[0]) && '' !== $arrSplit[0] && strlen($arrSplit[0]) <= 15)
					{
						$arrWord['word'] = $arrSplit[0];
						array_push($arrHotWords['words'], $arrWord);
						$intCount = $intCount + 1;
					}
				}
			}
		}

		 Gfunc::cacheSet(self::SEARCH_WORDLIB_CACHE_KEY_PRE, $arrHotWords, self::CACHE_EXPIRED_TIME);
        
		 return $arrHotWords;
	}
	
	
	
	/**
     * @route({"GET","/asd"})
     * app场景静默下载白名单
     * @return array
     */
    public function appSceneConfList(){
        $data = $this->getAppSceneConfData();
        $arrList = json_decode(json_encode($data['data']), true);
        $result = array();
        foreach($arrList as $k => $v) {
            array_push($result, $k);
        }
        return $result;

    }

    /**
     * @route({"GET","/asd_std"})
     * app场景静默下载白名单
     * @return array
     */
    public function appSceneConfListStandard(){

        $data = $this->getAppSceneConfData();
        $result = array();
        foreach($data['data'] as $k => $v) {
            array_push($result, $k);
        }
        $data['data'] = $result;
        return Util::returnValue($data, false, true);

    }
    
    
    /**
     * @route({"GET","/asdgwid"})
     * app场景静默下载白名单应用获取关联词库id
     *
     * @param({"app_name","$._GET.app_name"})
     * 白名单应用包名
     *
     * @return array
     */
    public function appSceneConfGetWid($app_name){
        $base_list = $this->getAppSceneConfData();

        foreach($base_list['data'] as $k => $v) {
            if($k == $app_name) {
                return $v;
            }
        }
        return array();
    }
    
    /**
     * @route({"GET","/aswl"})
     * app场景静默下载白名单全量数据 (以内核定义的格式输出,换行符分割'/n' )
     * 白名单应用包名列表
     *
        version:20180319,over_write:0
        app_name:com.jingdong.app.mall
        attribute:1,context_id:1,cell_id:51000,cell_id:51001
        attribute:2,context_id:2,cell_id:51002
        app_name:com.wochacha
        attribute:1,context_id:1,cell_id:51000,cell_id:51001
     *
     * @return array
     */
    public function appSceneWordslibSrc() {
           
        $result = Util::initialClass();
        
        $ascm = IoCload('models\\AppSceneWordslibModel');
        
        $resData = $ascm->cachez_getResData();
        
        $arrResult = is_array($resData['data']) ? $resData['data'] : array();
        
        $realResult = '';
        
        if(isset($resData['version'])) {
            //version是数据版本号，over_write是全量增量更新标记： 0:增量  1:全量 
            $realResult .= 'version:'.intval($resData['version']) . ',over_write:1/n';    
        
            $tmp = array();
             
            if(is_array($arrResult)) {
                //Edit by fanwenli on 2019-04-30, add context_flag
                $i = 0;
                $conditionFilter = IoCload("utils\\ConditionFilter");
                foreach ($arrResult as $k => $item) {
                    if($conditionFilter->filter($item['filter_conditions']))
                    {
                        //Edit by fanwenli on 2019-04-30, add context_flag
                        if($i == 0) {
                            $context_flag = 0;
                            if($item['context_flag'] != '') {
                                $arrFlag = explode(',',$item['context_flag']);
                                if(!empty($arrFlag)) {
                                    foreach($arrFlag as $v_flag) {
                                        $context_flag += intval($v_flag);
                                    }
                                }
                            }
                            
                            $realResult = str_replace('/n',',',$realResult) . 'context_flag:' . $context_flag . '/n';
                        }
                        $i++;
                        
                        foreach($item['app_conf'] as $ck => $cv) {
                            //报名，框属性,场景id,词库id都不为空才是有效数据, 缺任意一个都直接跳过
                            if(empty($cv['package_name']) || empty($cv['attribute']) || empty($cv['ctrid']) || empty($cv['wordslib_id'])) {
                                continue;
                            }      
                               
                            if(!isset($tmp[$cv['package_name']])) {
                                $tmp[$cv['package_name']] = array();    
                            } 
                            
                            $arrAtt = explode(',',$cv['attribute']); 
                            $arrCtr = explode(',',$cv['ctrid']);  
                            foreach($arrAtt as $ak => $av) {
                                //先将框属性放入对应包名内容中
                                if(!isset($tmp[$cv['package_name']][$av])) {
                                    $tmp[$cv['package_name']][$av] = array('context_id' => array(),'cell_id' => array());    
                                }
                                
                                //开始处理场景id
                                foreach($arrCtr as $ctk => $ctv) {
                                    if(!in_array($ctv,$tmp[$cv['package_name']][$av]['context_id'])) {
                                        array_push($tmp[$cv['package_name']][$av]['context_id'], $ctv);
                                    }
                                     
                                }
                                //处理词库id
                                foreach($cv['wordslib_id'] as $wdk => $wdv) {
                                    if(!in_array($wdv,$tmp[$cv['package_name']][$av]['cell_id'])) {
                                        array_push($tmp[$cv['package_name']][$av]['cell_id'], $wdv);
                                    }    
                                }
                                
                            }
                           
                        } 
                        
                    }
                }
                
                //开始整理最终结果
                foreach($tmp as $k => $v) {
                    foreach($v as $attribute => $vv) {
                        //包名赋值
                        $realResult .= 'app_name:' . $k .'/n'; 
                        //计算场景id,内核使用位运算，这里把全部场景id加起来即可
                        $context_id = 0;
                        foreach($vv['context_id'] as $ctk => $ctv) {
                            $context_id += intval($ctv);    
                        }
                        //拼词库id字符串
                        $cell_arr = array();
                        foreach($vv['cell_id'] as $clk => $cell_id) {
                            array_push($cell_arr,'cell_id:'. $cell_id);  
                        }    
                        $cell_str = join(',', $cell_arr);
                        
                        $realResult .= "attribute:${attribute},context_id:${context_id},${cell_str}/n";
                        
                    }       
                }
                
                //去掉最后一个'/n', 如果有
                if((strrpos($realResult, '/n') +2 ) === mb_strlen($realResult)) {
                    $realResult = substr($realResult, 0, -2);  
                }
            }
        
        }
        
        //替换换行符
        $realResult = str_replace('/n', chr(10), $realResult);
        $result['data'] = $realResult;
        
        return Util::returnValue($result);
    }
    
    
    /**
     * 获取app场景静默下载配置数据
     * @return
     */
    private function getAppSceneConfData() {

        $rtData = Util::initialClass();

        $result = array();
        
        $cache_key = 'app_scenc_conf_cache_data_v2';


        $cacheData = GFunc::cacheZget($cache_key);
        if (false !== $cacheData && null !== $cacheData){
            $arrResult = $cacheData['data'];
            $strVer = $cacheData['version'];
        } else {
            $bolRalSuccessed = true;
            $arrResult = GFunc::getRalContent('app_scene_conf',0,'', $bolRalSuccessed);
            $strVer = GFunc::$intResMsgVer;

            $cacheTime = true === $bolRalSuccessed ? GFunc::getCacheTime('2hours') : GFunc::getCacheTime('5mins');
            GFunc::cacheZset($cache_key, array('data' => $arrResult, 'version' => $strVer), $cacheTime);

        }
        
       
        if(is_array($arrResult)) {
            
            $conditionFilter = IoCload("utils\\ConditionFilter");

            foreach ($arrResult as $k => $item)
            {
                if($conditionFilter->filter($item['filter_conditions']))
                {
                    foreach($item['app_conf'] as $ck => $cv) {
                           
                        if(!isset($result[$cv['package_name']])) {
                           $result[$cv['package_name']] = $cv['wordslib_id'];
                        } else {
                           $result[$cv['package_name']] = array_merge($result[$cv['package_name']], $cv['wordslib_id']);
                        }
                       
                    } 
                }
            }
        }

        $rtData['data'] = $result;
        $rtData['version'] = $strVer;

        return $rtData;

    }
    
    
    /**
     * @route({"GET","/gkw"})
     * 游戏键盘语料下发
     *
     * @return array
     */
    function getGameKeyboardWordsData() {
        $data = $this->_getGkwData();
        return json_decode(json_encode($data['data']), true) ;
    }

    /**
     * @route({"GET","/gkw_std"})
     * 游戏键盘语料下发, 标准格式返回
     *
     * @return array
     */
    function getGameKeyboardWordsDataStandard() {
        $data = $this->_getGkwData();
        return Util::returnValue($data, false, true);
    }


    /**
     * 获取game_keyboard_words数据
     * @return array
     */
    function _getGkwData() {
        $rtData = Util::initialClass();

        $result = array();

        $cache_key = 'game_keyboard_words_cache_data_v2';

        $cacheData = GFunc::cacheZget($cache_key);


        if (false !== $cacheData && null !== $cacheData){
            $arrResult = $cacheData['data'];
            $arrVer = $cacheData['version'];
        } else {
            $bolRalSuccessed = true;
            $arrResult = GFunc::getRalContent('game_keyboard_words',0,'', $bolRalSuccessed);
            $arrVer = GFunc::$intResMsgVer;
            $cacheTime = true === $bolRalSuccessed ? GFunc::getCacheTime('2hours') : GFunc::getCacheTime('5mins');
            GFunc::cacheZset($cache_key, array('data' => $arrResult, 'version' => $arrVer), $cacheTime);
        }

        if(is_array($arrResult)) {

            $conditionFilter = IoCload("utils\\ConditionFilter");

            foreach ($arrResult as $k => $item)
            {
                if($conditionFilter->filter($item['filter_conditions']))
                {
                    foreach($item['app_conf'] as $ck => $cv) {

                        $words =  explode(PHP_EOL,$cv['words']);

                        if(!isset($result[$cv['package_name']])) {

                            $result[$cv['package_name']]['data'] = $words;
                            $result[$cv['package_name']]['package_name'] = $cv['package_name'];
                            $result[$cv['package_name']]['package_title'] = $cv['package_title'];
                            $result[$cv['package_name']]['switch'] = $cv['switch'] != 1 ? false : true;
                        } else {
                            $result[$cv['package_name']]['data'] = array_merge($result[$cv['package_name']]['data'], $words);
                        }

                    }
                }
            }
        }

        //语料去重
        if(is_array($result) && !empty($result)) {
            $tmp = array();
            foreach($result as $k => $v) {
                $words = array();
                foreach($v['data'] as $dk => $dv) {
                    if(!in_array($dv, $words)) {
                        array_push($words, $dv);
                    }
                }
                $tmp[] = array('data' => $words,'adapt_font'=> $v['switch'], 'package_name' => $v['package_name'], 'package_title' => $v['package_title']);
            }
            $result = $tmp;
        }

        $rtData['data'] = $result;
        $rtData['version'] = $arrVer;

        return $rtData;
    }
}
