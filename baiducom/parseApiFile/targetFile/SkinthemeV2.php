<?php
/**
 * Created by PhpStorm.
 * User: chendaoyan
 * Date: 2019/9/6
 * Time: 17:18
 */

use BaiduBce\Services\Bos\BosOptions;
use models\SkinShareModel;
use models\SkinShareTemplateModel;
use utils\Bos;
use utils\CustLog;
use utils\ErrorCode;
use utils\Util;
use utils\GFunc;
use tinyESB\util\Logger;

/**
 * 客户端9.0版本以后皮肤商店相关接口
 * @author chendaoyan
 * @desc 皮肤主题类
 * @path("/skinV2/")
 */
class SkinthemeV2 {
    const GENERAL_PAGE_SIZE = 12;
    const SUPPORT_VERSION = '9.1.0.0';

    /** @property V4接口域名 */
    private $domain_v4;
    /** @property V5接口域名 */
    private $domain_v5;

    /** @property 资源路径前缀 */
    private $pre_path;

    //iOS为app store审核临时排除的部分分类（接口中还要屏蔽该相关分类下的所有皮肤主题）
    private $except_cats = array(
        'dm',//卡通动漫
        'mx',//明星名人
        'ys',//影视娱乐
        'yx',//游戏地带
        'ty',//体育赛事
        'ktdm',//卡通动漫mo
    );
    
    /** @property 皮肤主分类缓存 */
    private $skinMainCategoryCache;
    
    /** @property 皮肤tab缓存 */
    private $skinTabCache;

    private $strPath = 'skin';

    /**
     * 统计展示、点赞，这边只是给个空接口，统计在魏晓那边
     * http://agroup.baidu.com/inputserver/md/article/2078716
     * @route({"POST", "/statistics"})
     * @param({"id", "$._POST.id"}) int 皮肤id
     * @param({"action", "$._POST.action"}) int 1 点赞
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function statistics($id=0, $action=1) {
        $result = Util::initialClass();
        if (empty($id) || empty($action)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        switch ($action) {
            case 1:
                CustLog::write('skin_praise', $id);
            break;
        }

        return Util::returnValue($result);
    }

    /**
     *
     * @route({"GET","/rank-type-list"})
     *
     * 皮肤排行榜类型列表
     *
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * {
    "data": [{"id":1, "name":"总排行"}],
    }
     */
    public function rankTypeList() {
        $ret = Util::initialClass(false);
        $ret['data'] = array(
            array(
                "id" => 1,
                "name" => "总排行"
            ),
        );

        return Util::returnValue($ret, false);
    }

    /**
     *
     * 皮肤搜索接口
     * 数据组成部分
     * 1. 搜索词
     * 2. 点击词
     * 3. tags
     * 4. 图搜
     * 5. 相关推荐结果
     * @docuement http://agroup.baidu.com/inputserver/md/article/2107533
     * @icafe http://newicafe.baidu.com/issue/inputserver-2472/show?from=page
     * @route({"GET", "/search"})
     * @param({"keyword", "$._GET.keyword"}) string 搜素词
     * @param({"click_keyword", "$._GET.click_keyword"}) string 点击词
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return array
     */
    public function search($keyword = "", $click_keyword = '')
    {
        $keyword = trim($keyword);
        if (empty($keyword)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "keyword is required");
        }

        $click_keyword = trim($click_keyword);
        $searchStrategy = IoCload(\strategy\SkinSearchStrategy::class);
        // tags结果
        $coroutine1 = new PhasterThread(
            array(
                IoCload(SearchV2::class),
                "getAllTag"
            ),
            array()
        );
        // 相关推荐结果
        $coroutine2 = new PhasterThread(
            array(
                IoCload(\strategy\SkinSearchRecommendStrategy::class),
                "run"
            ),
            array(
                $keyword,
                Util::clientParameters()
            )
        );
        // 图搜结果
        $coroutine3 = new PhasterThread(
            array(
                IoCload(Diy::class),
                "getImage"
            ),
            array(
                $keyword,
                0,
                15
            )
        );

        // 搜索词结果
        list($coroutine4, $coroutine5, $coroutine6, $coroutine7, $coroutine8, $coroutine9)
            = $searchStrategy->run($keyword, Util::clientParameters());
        // 点击词结果
        // 当两个词不一样的时候
        if (!empty($click_keyword) && $keyword != $click_keyword) {
            $searchStrategy = IoCload(\strategy\SkinSearchStrategy::class);
            list($coroutine10) = $searchStrategy->run($click_keyword, Util::clientParameters(), array(
                    "searchFromTitle"
            ));
        }

        $recordList = array();
        if (!empty($coroutine4)) {
            // 点击词 + 搜索词
            if (!empty($click_keyword) && $keyword != $click_keyword) {
                $recordList = $coroutine10->join()
                    + $coroutine4->join()
                    + $coroutine5->join()
                    + $coroutine6->join()
                    + $coroutine7->join()
                    + $coroutine8->join()
                    + $coroutine9->join();
            } else {
                // 搜索词
                $recordList = $coroutine4->join()
                    + $coroutine5->join()
                    + $coroutine6->join()
                    + $coroutine7->join()
                    + $coroutine8->join()
                    + $coroutine9->join();
            }
        }
        // tags
        $tags = $coroutine1->join();
        // 相关推荐结果
        $recommends = $coroutine2->join();
        // 图搜结果
        $relate_image = $coroutine3->join();

        $recordList = array_values($recordList);
        $data = array(
            'highlight' => array(),
            'list' => array(),
            'tags' => array(),
            'recommend' => array(),
            "relate_image" => empty($relate_image) ? array() : $relate_image
        );
        $highlightPicked = false;
        $page_mark = "skinsearch2result";
        foreach ($recordList as $index => $item) {
            if ($item['first_recommend'] == \models\SkinthemeModel::FIRST_RECOMMEND && !$highlightPicked) {
                $highlightPicked = true;
                $_record = Util::skinDataFormatToResource(
                    $item,
                    $page_mark,
                    0,
                    false,
                    1);
                if (!empty($_record)) {
                    $data['highlight'][] = $_record;
                }
                unset($recordList[$index]);
                continue;
            }

            $_record = Util::skinDataFormatToResource(
                $item,
                $page_mark,
                1,
                false,
                $highlightPicked ? $index : $index + 1);
            if (!empty($_record)) {
                $data['list'][] = $_record;
            }
        }

        foreach ($recommends as $index => $recommend) {
            $_record = Util::skinDataFormatToResource(
                $recommend,
                $page_mark,
                2,
                false,
                $index + 1
            );
            if (!empty($_record)) {
                $data['recommend'][] = $_record;
            }
        }

        foreach ($tags as $tag) {
            if ($tag['type'] != \models\SearchModel::TYPE_HOT) {
                continue;
            }
            $data['tags'][] = array(
                'id' => $tag['id'],
                "name" => $tag['name'],
            );
        }

        $ret = Util::initialClass();
        $ret['data'] = $data;
        return Util::returnValue($ret);
    }

    /**
     *
     * 皮肤排行榜数据列表
     * @route({"GET", "/rank-list"})
     * @param({"rank_id", "$._GET.rank_id"}) string 排序id
     * @param({"page_num","$._GET.page_num"}) int 页码
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return array
     */
    public function rankList($rank_id = 1, $page_num = 1)
    {
        $model = IoCload(\models\SkinthemeModel::class);
        $recordList = $model->rankList($rank_id);

        $page_num = empty($page_num) ? 1 : intval($page_num);
        $count = count($recordList);
        $pageSize = 12;
        $page_count = $count % $pageSize == 0 ? floor($count / $pageSize) : floor($count / $pageSize) + 1;
        $data = array(
            "highlight"=>array(),
            "pagesize" => $pageSize,
            "page_num" => $page_num,
            "items_count" => $count,
            "page_count" => $page_count,
            "is_last_page" => $page_num >= $page_count ? 1 : 0,
            "items"=>array(),
        );

        $page_mark = sprintf("skinrank_%d", $rank_id);
        for($i = 0; $i < 3; $i++) {
            if (!isset($recordList[$i])) {
                break;
            }

            $record = array_shift($recordList);
            $_record = Util::skinDataFormatToResource(
                $record,
                $page_mark,
                0,
                false,
                $i + 1
            );
            if (!empty($_record)) {
                $data['highlight'][] = $_record;
            }
        }

        $start_from = ($page_num - 1) * $pageSize;
        for ($index = $start_from; $index < $start_from + $pageSize; $index++) {
            if (!isset($recordList[$index])) {
                break;
            }
            $_record = Util::skinDataFormatToResource(
                $recordList[$index],
                $page_mark,
                1,
                false,
                $index + 1
            );
            if (!empty($_record)) {
                $data['items'][] = $_record;
            }
        }

        $ret = Util::initialClass();
        $ret['data'] = $data;
        return Util::returnValue($ret);
    }

    
    /**
     * @route({"GET","/detail"})
     * @document http://agroup.baidu.com/inputserver/md/article/2084848
     * 皮肤排行详情数据, 不带相似推荐的数据
     * @param({"token", "$._GET.token"}) string 皮肤token
     * @param({"id", "$._GET.id"}) int 皮肤id
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function detail($token = "", $id = 0) {
        if(empty($token) && empty($id)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "token 不能为空");
        }

        $token = urldecode(trim($token));
        // token验证，防sql注入
        if (!empty($token) && !preg_match('/^\w{20,60}$/', $token)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "非法token");
        }
        $id = intval($id);

        $ret = Util::initialClass();
        // 运营活动handler
        $activeHandlers = array(
            array(
                "match" => function($token, $id) {
                    $tokens = GFunc::getGlobalConf("nishuihan_tokens");
                    if (in_array($token, $tokens)) {
                        return true;
                    }

                    return strpos($token, "nishuihan") === false ? false : true;
                },
                "before_handler" => function($token, $id) {
                    if (strpos($token, "nishuihan") === false) {
                        return array($token, $id);
                    }

                    $cache = GFunc::getCacheInstance();
                    $succeed = false;
                    $data = $cache->get($token, $succeed);
                    if (!$succeed) {
                        return null;
                    }

                    if (!isset($data["cuid"]) || empty($data["cuid"])) {
                        $data['cuid'] = $_GET['cuid'];
                    }

                    if ($data['cuid'] != $_GET['cuid']) {
                        return null;
                    }

                    $cache->set($token, $data, GFunc::getCacheTime("hours") * 24 * 30);

                    return isset($data['token']) ? array($data['token'], $id) : null;
                },
                "after_handler" => function($ret) {
                    if(
                        !isset($ret["data"])
                        || empty($ret['data'])
                        || !is_array($ret['data'])
                        || !isset($ret['data']['share_info'])
                        || empty($ret['data']['share_info'])
                        || !is_array($ret['data']['share_info'])
                    ) {
                        return $ret;
                    }

                    $client_parameters = Util::clientParameters();
                    $ret["data"]["status"] = "100";
                    $ret["data"]["share_type"] = "2";
                    $bos_host = GFunc::getGlobalConf("bos_host_https");
                    $qr_code = GFunc::getGlobalConf("nishuihan_qr_code");
                    foreach ($ret['data']['share_info'] as $index => $item) {
                        if (!isset($item['content'])) {
                            continue;
                        }

                        $ret['data']['share_info'][$index]["content"]["title"] = "我的恋爱盲盒";
                        $ret['data']['share_info'][$index]["content"]["description"] = "快来看看你的恋爱盲盒里都有谁";
                        $ret['data']['share_info'][$index]["content"]["url"] = GFunc::getGlobalConf("nishuihan_url");
                        $ret['data']['share_info'][$index]["content"]["thumb"] = sprintf("%s/activity/nishuihan/nishuihan_thumbnail.png", $bos_host);
                        if ($item["name"] == "weibo") {
                            $ret["data"]["share_info"][$index]["content"]["share_pic_paste_position"] = array(
                                "template_height"=> 1080,
                                "template_width"=> 1080,
                                "preview_width"=> 0,
                                "preview_height"=> 0,
                                "preview_relative_x"=> 0,
                                "preview_relative_y"=> 0,
                                "qrcode_height"=> 0,
                                "qrcode_width"=> 0,
                                "qrcode_relative_x"=> 0,
                                "qrcode_relative_y"=> 0
                            );
                            $ret['data']['share_info'][$index]["content"]["share_pic"] = $client_parameters["os"] == "ios" ?
                                sprintf("%s/activity/nishuihan/ios_weibo_share_pic.png", $bos_host) :
                                sprintf("%s/activity/nishuihan/android_weibo_share_pic.png", $bos_host);
                            continue;
                        }

                        $share_pic_paste_position = array(
                            "template_height"=> 1135,
                            "template_width"=> 1080,
                            "preview_width"=> 0,
                            "preview_height"=> 0,
                            "preview_relative_x"=> 0,
                            "preview_relative_y"=> 0,
                            "qrcode_height"=> 0,
                            "qrcode_width"=> 0,
                            "qrcode_relative_x"=> 0,
                            "qrcode_relative_y"=> 0
                        );
                        $ret["data"]["share_pic_paste_position"] = $share_pic_paste_position;
                        $ret["data"]["share_info"][$index]["content"]["share_pic_paste_position"] = $share_pic_paste_position;
                        $ret['data']['share_info'][$index]["content"]["share_pic"] = $client_parameters["os"] == "ios" ?
                            sprintf("%s/activity/nishuihan/ios_share_pic.png", $bos_host) :
                            sprintf("%s/activity/nishuihan/android_share_pic.png", $bos_host);
                        $ret['data']['share_info'][$index]["content"]["share_qrcode"] =
                            sprintf("%s/%s", $bos_host, $qr_code);
                    }
                    return $ret;
                }
            ),
        );
        $before_handler = null;
        $after_handler = null;
        foreach ($activeHandlers as $handler) {
            if(call_user_func($handler["match"], $token, $id)) {
                $before_handler = isset($handler["before_handler"])?$handler["before_handler"]:null;
                $after_handler = isset($handler["after_handler"])?$handler["after_handler"]:null;
                break;
            }
        }

        if (!empty($before_handler)) {
            $result = call_user_func($before_handler, $token, $id);
            if (empty($result)) {
                return Util::returnValue($ret);
            }
            list($token, $id) = $result;
        }

        $model = IoCload(\models\SkinthemeModel::class);
        $data = $model->detail($token, intval($id));
        if (empty($data)) {
            $ret['data'] = new stdClass();
        } else {
            $ret['data'] = $data;
        }

        if (!empty($after_handler)) {
            $ret = call_user_func($after_handler, $ret);
        }
        return Util::returnValue($ret);
    }

    /**
     * @route({"GET","/detailrecommend"})
     * @document http://agroup.baidu.com/inputserver/md/article/2138053
     * 皮肤排行详情相似推介的数据
     * @param({"token", "$._GET.token"}) string 皮肤token
     * @param({"id", "$._GET.id"}) int 皮肤id
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function detailRelatedRecommend($token = "", $id = 0) {
        if(empty($token) && empty($id)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "token 不能为空");
        }

        $token = urldecode(trim($token));
        // token验证，防sql注入
        if (!empty($token) && !preg_match('/^\w{20,60}$/', $token)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "非法token");
        }
        $id = intval($id);

        $handlers = array(
            array(
                "match" => function($token, $id) {
                    return strpos($token, "nishuihan") === false ? false : true;
                },
                "before_handler" => function($token, $id) {
                    $cache = GFunc::getCacheInstance();
                    $succeed = false;
                    $data = $cache->get($token, $succeed);
                    if (!$succeed) {
                        return null;
                    }

                    return isset($data['token']) ? array($data['token'], $id) : null;
                },
                "after_handler" => null,
            ),
        );
        $ret = Util::initialClass(false);
        $before_handler = null;
        $after_handler = null;
        foreach ($handlers as $handler) {
            if(call_user_func($handler["match"], $token, $id)) {
                $before_handler = isset($handler["before_handler"])?$handler["before_handler"]:null;
                $after_handler = isset($handler["after_handler"])?$handler["after_handler"]:null;
                break;
            }
        }

        if (!empty($before_handler)) {
            $result = call_user_func($before_handler, $token, $id);
            if (empty($result)) {
                return Util::returnValue($ret);
            }
            list($token, $id) = $result;
        }

        $model = IoCload(\models\SkinthemeModel::class);
        $data = $model->detailRecommend(array(
            $token,
            intval($id),
            Util::clientParameters()
        ));
        if (empty($data)) {
            $ret['data'] = array();
        } else {
            $ret['data'] = $data;
        }
        if (!empty($after_handler)) {
            $ret = call_user_func($after_handler, $ret);
        }
        return Util::returnValue($ret);
    }

    /**
     * @route({"GET","/skin-list-from-category"})
     * @param({"id", "$._GET.id"}) int 分类的id
     * @param({"page_num", "$._GET.page_num"}) int 页码
     * 分类下皮肤数据列表
     *
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function skinListFromCategory($id, $page_num = 1) {
        if (empty($id)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "category id is required");
        }

        $model = IoCload(\models\SkinthemeModel::class);
        $recordList = $model->getSkinListFromCategory($id);

        $page_mark = sprintf("skincateresult_%d", $id);
        // 取highlight
        $highlight = array();
        foreach ($recordList as $index => $record) {
            if ($record['first_recommend'] == 1) {
                unset($recordList[$index]);
                $_record = Util::skinDataFormatToResource(
                    $record,
                    $page_mark,
                    0,
                    false,
                    1
                );
                if (!empty($_record)) {
                    $highlight[] = $_record;
                }
                break;
            }
        }
        array_filter($recordList);

        $page_num = empty($page_num) ? 1 : intval($page_num);
        $count = count($recordList);
        $pageSize = 12;
        $page_count = $count % $pageSize == 0 ? floor($count / $pageSize) : floor($count / $pageSize) + 1;
        $data = array(
            "highlight"=>$highlight,
            "pagesize" => $pageSize,
            "page_num" => $page_num,
            "items_count" => $count,
            "page_count" => $page_count,
            "is_last_page" => $page_num >= $page_count ? 1 : 0,
            "items"=>array(),
        );

        $start_from = ($page_num - 1) * $pageSize;
        //数组unset 后不具有连续性
        $pageRecords = array_slice($recordList, $start_from, $pageSize);
        foreach($pageRecords as $index => $record) {
            $_record = Util::skinDataFormatToResource(
                $record,
                $page_mark,
                1,
                false,
                $index + 1
            );
            if (!empty($_record)) {
                $data['items'][] = $_record;
            }

        }

        $ret = Util::initialClass();
        $ret['data'] = $data;
        return Util::returnValue($ret);
    }
    
    /**
     * @route({"GET","/category"})
     *
     * 皮肤主分类数据列表
     * 
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function category() {
        $ret = Util::initialClass(false);
        
        $arrMainCate = GFunc::cacheZget($this->skinMainCategoryCache);
        if($arrMainCate === false) {
            $arrMainCate = $this->getMainCate();
            
            //set cache content and cache time is 15mins
            GFunc::cacheZset($this->skinMainCategoryCache, $arrMainCate, GFunc::getCacheTime('15mins'));
        }
        
        //过滤
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $arrMainCate = $conditionFilter->getFilterConditionFromDB($arrMainCate);
        
        //set array in theme & color
        $ret['data'] = array(
            'theme' => array(),
            'color' => array(),
        );
        
        if(!empty($arrMainCate)) {
            //get bos host
            $bosHost = GFunc::getGlobalConf('bos_host_https');
            
            foreach($arrMainCate as $val) {
                //add bos domain
                if(trim($val['image']) != '') {
                    $val['image'] = $bosHost . trim($val['image']);
                }
                
                switch($val['type']) {
                    //color
                    case 2:
                        unset($val['type']);
                        $ret['data']['color'][] = $val;
                        break;
                    default:
                        unset($val['type']);
                        $ret['data']['theme'][] = $val;
                        break;
                }
            }
        }
        
        
        return Util::returnValue($ret, false);
    }
    
    /**
     * @route({"GET","/tab"})
     *
     * 皮肤主分类下tab数据列表
     * 
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function tab() {
        //分类id
        $id = intval($_GET['cate_id']);
        
        if($id <= 0) {
            $ecode = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($ecode,'Please give us category id',true);
        }
        
        $ret = Util::initialClass();
        
        //cache key
        $strCacheKey = $this->skinTabCache . '_' . $id;
        $arrDate = GFunc::cacheZget($strCacheKey);
        if($arrDate === false) {
            $arrDate = $this->getCateTabInfo($id);
            
            //set cache content and cache time is 15mins
            GFunc::cacheZset($strCacheKey, $arrDate, GFunc::getCacheTime('15mins'));
        }
        
        //主分类过滤
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $arrDate = $conditionFilter->getFilterConditionFromDB($arrDate);
        
        if(isset($arrDate[0]['children']) && !empty($arrDate[0]['children'])) {
            //tab过滤
            $arrDate[0]['children'] = $conditionFilter->getFilterConditionFromDB($arrDate[0]['children']);
        }
        
        if(!empty($arrDate)) {
            $ret['data'] = $arrDate[0];
        }
        
        return Util::returnValue($ret);
    }
    
    /**
    * 获取主分类信息
    *
    * @return array
    *
    */
    private function getMainCate() {
        $out = array();
        
        $skinCateModel = IoCload('models\\SkinCateModel');
        $result = $skinCateModel->getCategory();
        
        $arrIds = array(
            'pid' => array(),
            'cid' => array(),
        );
        
        //main category
        $arrMainCate = array();
        
        if(!empty($result)) {
            //set all in ids
            foreach ($result as $val) {
                //parent ids
                $arrIds['pid'][] = $val['pid'];
                //children ids
                $arrIds['cid'][] = $val['cid'];
            }
            
            if (!empty($arrIds['pid']) && !empty($arrIds['cid'])) {
                foreach ($arrIds['pid'] as $val) {
                    //do not set in firstId & it could not be set in cid
                    if (!in_array($val, $arrMainCate) && !in_array($val, $arrIds['cid'])) {
                        $arrMainCate[] = $val;
                    }
                }
            }
        }

        $out = $skinCateModel->getMainCategoryInfo($arrMainCate, $arrIds);

        return $out;
    }
    
    /**
     * 获取主分类以及tab信息
     *
     * @param $id int 主分类id
     * 
     *  
     * @return array
     *
    */
    private function getCateTabInfo($id = 0) {
        $out = array();
        
        $skinCateModel = IoCload('models\\SkinCateModel');
        $result = $skinCateModel->getSingleMainCategoryInfo($id);
        
        if(!empty($result)) {
            $out[0] = $result[0];
            $out[0]['children'] = $this->getTabInfo($result[0]['id']);
        }
        
        return $out;
    }
    
    /**
     * 获取主分类对应tab信息
     *
     * @param $id int 主分类id
     * 
     *  
     * @return array
     *
    */
    private function getTabInfo($id = 0) {
        $out = array();
        
        $skinCateModel = IoCload('models\\SkinCateModel');
        $result = $skinCateModel->getTabRelation($id);
        
        if(!empty($result)) {
            $out = $skinCateModel->getTabInfo($result);
        }
        
        return $out;
    }

    /**
     * diy皮肤分享类型
     * http://agroup.baidu.com/inputserver/md/article/2104674
     * @route({"GET", "/getDiyShareType"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getDiyShareTypeAction() {
        $result = Util::initialClass();

        $objSkinShareType = IoCload('models\\SkinShareTypeModel');
        $appends = array(
            'order by id desc',
            'limit 1'
        );
        $cond = array(
            'status = 100'
        );
        $skinShareInfo = $objSkinShareType->select('*', $cond, null, $appends);
        $data = new stdClass();
        if (isset($skinShareInfo)) {
            $data->type = isset($skinShareInfo[0]['type']) ? $skinShareInfo[0]['type'] : 0;
        }
        $result['data'] = $data;

        return Util::returnValue($result);
    }

    /**
     * 上传分享图片接口
     * http://agroup.baidu.com/inputserver/md/article/2106360
     * @route({"POST", "/uploadShareImg"})
     * @param({"token", "$._POST.token"}) string 皮肤token
     * @param({"skinType", "$._POST.skinType"}) 皮肤类型 1diy皮肤 2普通皮肤
     * @param({"shareSource", "$._POST.shareSource"}) 皮肤分享来源 1商店分享 2面板分享，此字段暂时没用，预留
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function uploadShareImgAction($token='', $skinType, $shareSource) {
        $result = Util::initialClass();
        if (empty($_FILES['img']) || (2 == $skinType && empty($token)) || !in_array($skinType, array(1,2))) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        //上传图片到bos
        $bucket = GFunc::getGlobalConf('bos_bucket');
        $objBosClient = new Bos($bucket, $this->strPath);
        $objectKeyRes = date('Y-m-d') . '/' . md5(microtime() . rand(0, 10000)) . $_FILES['img']['name'];
        $fileName = $_FILES['img']['tmp_name'];
        try {
            $uploadRes = $objBosClient->putObjectFromFile($objectKeyRes, $fileName, array(BosOptions::CONTENT_TYPE => 'image/png'));
            if (1 != $uploadRes['status']) {
                ErrorCode::returnError('UPLOAD_ERROR', '图片上传失败');
            }
        } catch (Exception $e) {
            ErrorCode::returnError('UPLOAD_ERROR', '图片上传失败');
        }

        //save to db
        $requestId = md5(microtime() . rand(1, 10000));
        $imgUrl = sprintf('%s/%s', $this->strPath, $objectKeyRes);
        $dbData = array(
            'token' => $token,
            'skin_type' => $skinType,
            'request_id' => $requestId,
            'img_url' => $imgUrl,
            'create_time' => date('Y-m-d H:i:s'),
            'share_source' => $shareSource,
        );
        $objInputSkinShare = IoCload('models\\SkinShareModel');
        $saveRes = $objInputSkinShare->insert($dbData);
        if (false === $saveRes) {
            ErrorCode::returnError('DB_ERROR', '数据库保存失败');
        }
        $activityHost = GFunc::getGlobalConf('activityHost');
        if (2 == $skinType) {
            $activityUrl = $activityHost . '/static/activitysrc/skinshare/index.html?token=' . $token . '&requestId=' . $requestId;
        } else {
            $activityUrl = $activityHost . '/static/activitysrc/skinshare/index.html?token=&requestId=' . $requestId;
        }
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        $data = new stdClass();
        $data->img_url = sprintf('%s/%s', $bosHost, $imgUrl);
        $data->activity_url = $activityUrl;
        $data->thumb = sprintf('%s/%s', $bosHost, $imgUrl . '@w_100,h_80');
        $result['data'] = $data;

        return Util::returnValue($result);
    }

    /**
     * 皮肤分享的handler
     * @return array
     */
    protected function shareHandlers($token, $requestId, $os) {
        $handlers = array(
            array(
                "match" => function($token, $id) {
                    $tokens = GFunc::getGlobalConf("nishuihan_tokens");
                    if (in_array($token, $tokens)) {
                        return true;
                    }

                    return strpos($token, "nishuihan") === false ? false : true;
                },
                "before_handler" => function($token, $id) {
                    if (strpos($token, "nishuihan") === false) {
                        return array($token, $id, $token);
                    }

                    $cache = GFunc::getCacheInstance();
                    $succeed = false;
                    $data = $cache->get($token, $succeed);
                    if (!$succeed) {
                        return null;
                    }

                    return isset($data['token']) ? array($data['token'], $id, $token) : null;
                },
                "get_skin_info_handler"=>function($sql, $id, $token) {
                    if (empty($token)) {
                        return sprintf(
                            "SELECT t1.*,
                                t2.`diy_praise`, 
                                t2.`praise`, 
                                t2.`is_lock`, 
                                t2.`achievement`, 
                                t2.`detail_show_diy_type`, 
                                t2.share_template
                                FROM input_skinthemes t1
                                LEFT JOIN input_skinthemes_subsidiary t2 ON t1.`id`=t2.`skin_id`
                                WHERE t1.`id`=%d",
                            $id
                        );
                    }

                    return sprintf(
                        "SELECT t1.*,
                                t2.`diy_praise`, 
                                t2.`praise`, 
                                t2.`is_lock`, 
                                t2.`achievement`, 
                                t2.`detail_show_diy_type`, 
                                t2.share_template
                                FROM input_skinthemes t1
                                LEFT JOIN input_skinthemes_subsidiary t2 ON t1.`id`=t2.`skin_id`
                                WHERE t1.`token`=\"%s\"",
                        $token
                    );
                },
                "clipboard_token_handler" => function($clipData, $options) {
                    $os = Util::getOsByUa();
                    return array(
                        "title"=> "我的恋爱盲盒",
                        "activityId" => 20200429,
                        "createtime" => time(),
                        "pop_window" => 1,
                        "supportVersion" => "9.0.0.0",
                        "imageUrl" => 'ios' == $os ?
                            sprintf("%s/activity/nishuihan/nishuihan_clipboard_pop_ios.png", GFunc::getGlobalConf('bos_host_https')):
                            sprintf("%s/activity/nishuihan/nishuihan_clipboard_pop_android.png", GFunc::getGlobalConf('bos_host_https')),
                        "openUrl" => 'ios' == $os ?
                            urlencode(sprintf('opensuperskin?token=%s&tab=1&autoApply=true', $options['lottory_token'])) :
                            sprintf('opensuperskin?token=tab=1&atoken=%s', $options['lottory_token'])
                    );
                },
                "after_handler" => function($ret, $options) {
                    if(
                        !isset($ret["data"])
                        || empty($ret['data'])
                        || !is_array($ret['data'])
                    ) {
                        return $ret;
                    }

                    $bos_host = GFunc::getGlobalConf("bos_host_https");
                    $ret['data']["share_info"] = array(
                        "title" => '我的恋爱盲盒',
                        "desc" => "快来看看你的恋爱盲盒里都有谁",
                        "image_url" => sprintf("%s/activity/nishuihan/nishuihan_thumbnail.png", $bos_host),
                        "url" => GFunc::getGlobalConf("nishuihan_url"),
                    );

                    if (!empty($options["lottory_token"])) {
                        if ($options["os"] == "ios") {
                            $ret['data']['token'] = $options["lottory_token"];
                            return $ret;
                        }
                        $ret['data']['atoken'] = $options["lottory_token"];
                    }

                    return $ret;
                }
            ),
        );

        $before_handler = null;
        $after_handler = null;
        $get_skin_info_handler = null;
        $clipboard_token_handler = null;
        foreach ($handlers as $handler) {
            if(call_user_func($handler["match"], $token, $requestId)) {
                $before_handler = isset($handler["before_handler"])?$handler["before_handler"]:null;
                $after_handler = isset($handler["after_handler"])?$handler["after_handler"]:null;
                $clipboard_token_handler = isset($handler["clipboard_token_handler"])
                    ?$handler["clipboard_token_handler"]:null;
                $get_skin_info_handler = isset($handler["get_skin_info_handler"])
                    ?$handler["get_skin_info_handler"]:null;
                break;
            }
        }
        return array(
            $before_handler,
            $after_handler,
            $get_skin_info_handler,
            $clipboard_token_handler
        );
    }

    /**
     * H5分享接口
     * @document http://agroup.baidu.com/inputserver/md/article/2112368
     * @route({"GET","/share"})
     * @param({"token", "$._GET.token"}) string 皮肤token
     * @param({"requestId", "$._GET.requestId"}) int 当次请求id
     * @param({"os", "$._GET.os"}) int 当前系统 ios或者android
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function share($token, $requestId='', $os='') {
        header("Access-Control-Allow-Origin:*");
        $result = Util::initialClass();
        if (empty($token) && empty($requestId)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }

        // 运营活动handler
        list($before_handler,
            $after_handler,
            $get_skin_info_handler,
            $clipboard_token_handler) = $this->shareHandlers($token, $requestId, $os);

        $lottory_token = null;
        if (!empty($before_handler)) {
            list($token, $requestId, $lottory_token) = call_user_func($before_handler, $token, $requestId);
        }

        $data = array();
        $objSkinThemeModel = IoCload('models\\SkinthemeModel');
        //推荐数据
        $recommendInfo = $objSkinThemeModel->getShareRecommend();
        $data['recommend'] = $recommendInfo;
        $result['data'] = $data;
        //获取皮肤数据
        $skinInfo = $objSkinThemeModel->getSkinInfoByIdOrToken('', $token, $get_skin_info_handler);
        if (empty($skinInfo) && !empty($token)) {
            $result['ecode'] = ErrorCode::OFFLINE;
            $result['emsg'] = '皮肤已下线';
            return Util::returnValue($result);
        } else {
            $skinInfo = !empty($skinInfo[0]) ? $skinInfo[0] : array();
        }
        if (empty($os)) {
            $os = Util::getOsByUa();
        }
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        $preBosHost = GFunc::getGlobalConf('bos_domain_pre_http');
        if (!empty($skinInfo)) {
            //判断皮肤适用系统
            $skinOs = $objSkinThemeModel->getOsBySkinPlatform($skinInfo);
            if ($os != $skinOs && 'all' != $skinOs) {
                $arrShareUrl = explode('&', $skinInfo['share_url']);
                $id = 0;
                foreach ($arrShareUrl as $arrShareUrlK => $shareUrlV) {
                    $tmpShareUrl = explode('=', $shareUrlV);
                    if ('ios' == $os && 'ios_id' == $tmpShareUrl[0]) {
                        $id = $tmpShareUrl[1];
                        break;
                    } else if ('android' == $os && 'android_id' == $tmpShareUrl[0]) {
                        $id = $tmpShareUrl[1];
                        break;
                    }
                }

//                if (empty($id)) {
//                    /**
//                     * 通过皮肤组找匹配皮肤
//                     * 先通过group_id找到所有关联的皮肤
//                     * 然后通过回到过滤方法，找到需要的皮肤
//                     * 这里过滤os一致的皮肤
//                     */
//                    $brother = $objSkinThemeModel->getPointedBrother($skinInfo['group_id'],
//                        function ($brothers, $parameters) use($objSkinThemeModel) {
//                            foreach ($brothers as $brother) {
//                                $brotherOs = $objSkinThemeModel->getOsBySkinPlatform($brother);
//                                if ($brotherOs == "all" || $brotherOs == $parameters["os"]) {
//                                    return $brother;
//                                }
//                            }
//
//                            return null;
//                        }, array(
//                            "os"=>$os
//                        )
//                    );
//                    if (!empty($brother)) {
//                        $id = $brother["id"];
//                    }
//                }

                if (!empty($id)) {
                    $skinInfo = $objSkinThemeModel->getSkinInfoByIdOrToken($id, "", $get_skin_info_handler);
                    if (empty($skinInfo)) {
                        $result['ecode'] = ErrorCode::OFFLINE;
                        $result['emsg'] = '皮肤已下线';
                        return Util::returnValue($result);
                    } else {
                        $skinInfo = $skinInfo[0];
                        $skinOs = $objSkinThemeModel->getOsBySkinPlatform($skinInfo);
                    }
                } else {
                    switch ($os) {
                        case 'ios':
                            $result['ecode'] = ErrorCode::SKIN_IOS_FAILDED;
                            $result['emsg'] = '此皮肤不可以在IOS使用哦';
                            return Util::returnValue($result);
                        case 'android' :
                            $result['ecode'] = ErrorCode::SKIN_ANDROID_FAILDED;
                            $result['emsg'] = '此皮肤不可以在安卓使用哦';
                            return Util::returnValue($result);
                        default:
                            $result['ecode'] = ErrorCode::SKIN_OTHER_FAILDED;
                            $result['emsg'] = '此皮肤不可以在该系统使用哦';
                            return Util::returnValue($result);
                    }
                }
            }
            //通用处理字段
            $fields = array(
                'string' => array(
                    'title',
                    'author',
                    'content_text',//描述
                ),
                'int' => array(
                    'id',
                )
            );
            foreach ($fields['string'] as $k => $v) {
                if (is_string($v)) {
                    $data[$v] = !empty($skinInfo[$v]) ? $skinInfo[$v] : '';
                } else if (is_array($v)) {
                    $data[$v['show']] = !empty($skinInfo[$v['value']]) ? $skinInfo[$v['value']] : '';
                }
            }
            foreach ($fields['int'] as $k => $v) {
                $data[$v] = !empty($skinInfo[$v]) ? $skinInfo[$v] : 0;
            }
            //为了兼容前端代码，此处设置两个token，atoken为android皮肤token，token为ios皮肤token，填充其中一个另外一个默认为空即可
            $data['token'] = '';
            $data['atoken'] = '';
            if ($os != $skinOs) {
                $data['token'] = $data['atoken'] = $skinInfo['token'];
            } else if ('ios' == $os) {
                $data['token'] = $skinInfo['token'];
            } else if ('android' == $os) {
                $data['atoken'] = $skinInfo['token'];
            }
            //特殊处理字段
            $downloadNumber = $objSkinThemeModel->getDownloadTimes($skinInfo['id'], intval($skinInfo['download_number']) + intval($skinInfo['download_times_increase']));
            $data['download'] = $objSkinThemeModel->formatDownloadData($downloadNumber);
            $data['praise'] = $objSkinThemeModel->formatDownloadData(intval($skinInfo['praise']) + intval($skinInfo['diy_praise']));
            $resource = array();
            if (!empty($skinInfo['tj_video'])) {
                $resourceType = 1;
                $resource = array(
                    'thumb' => $preBosHost . $skinInfo['tj_video_thumb'],
                    'file' => substr($skinInfo['tj_video'], 0, strpos($skinInfo['tj_video'], '?')),//前端只能识别mp4等视频格式结果的url
                );
            } else if (!empty($skinInfo['tj_gif'])) {
                $resourceType = 2;
                $resource = array(
                    'thumb' => $preBosHost . $skinInfo['tj_gif_thumb'],
                    'file' => $preBosHost . $skinInfo['tj_gif'],
                );
            } else {
                $resourceType = 3;
                //服务端拉取皮肤预览图优先选择1080的预览图至少1张，若没有1080的图，则拉取640尺寸图，若拉取不到任何预览图数据，则告知H5 该皮肤已下线
                if (in_array($skinOs, array('ios', 'android'))) {
                    for ($i=1; $i < 4; $i++) {
                        if (!empty($skinInfo["pic_{$i}_1_" . $skinOs])) {
                            $resource[] = $preBosHost . $skinInfo["pic_{$i}_1_" . $skinOs];
                        }
                    }
                } else {//优先选取安卓的图片
                    foreach (array('android', 'ios') as $key => $val) {
                        for ($i=1; $i < 4; $i++) {
                            if (!empty($skinInfo["pic_{$i}_1_" . $val])) {
                                $resource[] = $preBosHost . $skinInfo["pic_{$i}_1_" . $val];
                            }
                        }
                        if (!empty($resource)) {
                            break;
                        }
                    }
                }

                if (empty($resource)) {
                    for ($i=1; $i < 4; $i++) {
                        if (!empty($skinInfo["pic_{$i}_0"])) {
                            $resource[] = $preBosHost . $skinInfo["pic_{$i}_0"];
                        }
                    }
                }
            }
            $data['resource_type'] = $resourceType;
            $data['resource'] = $resource;
            //分享数据
            $data['share_info'] = array(
                'title' => $skinInfo['title'],
                'desc' => $skinInfo['weixin_desc'],
                'image_url' => $preBosHost . $skinInfo['pic_2_100'],
            );
            $data['min_version'] = !empty($skinInfo['min_version_origin']) ? $skinInfo['min_version_origin'] : '';
            //查询分类信息
            $categoryInfo = $objSkinThemeModel->getSkinCategoryInfoByCateId($skinInfo['category']);
            $data['cate'] = !empty($categoryInfo[0]['category_name']) ? $categoryInfo[0]['category_name'] : '';
            $categoryType = '';
            if (1 == $categoryInfo[0]['type']) {
                $categoryType = 's';
            } else if (2 == $categoryInfo[0]['type']) {
                $categoryType = 't';
            }
            $data['cateId'] = !empty($skinInfo['category']) ? $categoryType . $skinInfo['category'] : '';
        }
        //皮肤实时预览图
        $data['real_time_pic'] = '';
        $requestInfo = array();
        if (!empty($requestId)) {
            $objSkinShareModel = IoCload(SkinShareModel::class);
            $requestInfo = $objSkinShareModel->getShareInfoByRequestId($requestId);
        }
        //如果skinInfo和requestInfo都为空，则返回错误
        if (empty($skinInfo) && empty($requestInfo)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        $data['real_time_pic'] = !empty($requestInfo[0]['img_url']) ? sprintf("%s/%s", $bosHost, $requestInfo[0]['img_url']) : '';
        //皮肤预览图和实时图片都没有，则提示皮肤下线
        if (empty($data['resource']) && empty($data['real_time_pic'])) {
            $result['ecode'] = ErrorCode::OFFLINE;
            $result['emsg'] = '皮肤已下线';
            return Util::returnValue($result);
        }
        if (!empty($requestInfo[0]) && 1 == $requestInfo[0]['skin_type']) {
            $skinType = 1;
        } else {
            $skinType = 0;
        }
        //模板数据
        //如果是diy分享直接取diy模板数据，否则根据皮肤选择的模板下发对应的数据
        $objInputSkinShareTemplate = IoCload(SkinShareTemplateModel::class);
        if (isset($requestInfo[0]['skin_type']) && 1 == $requestInfo[0]['skin_type']) {
            $templateInfo = $objInputSkinShareTemplate->getShareTemplateInfoByType(3);
        } else {
            $templateInfo = $objInputSkinShareTemplate->getShareTemplateInfoById($skinInfo['share_template']);
        }
        $data['template_bg_img'] = !empty($templateInfo[0]['template_bg']) ? $preBosHost . $templateInfo[0]['template_bg'] : '';
        $data['template_button_img'] = !empty($templateInfo[0]['template_button']) ? $preBosHost . $templateInfo[0]['template_button'] : '';
        //皮肤类型
        $data['skin_type'] = $skinType;
        $clipData['title'] = "";
        $clipData['activityId'] = 20200218;
        $clipData['createtime'] = time();
        $clipData['pop_window'] = 0;
        $clipData['supportVersion'] = self::SUPPORT_VERSION;
        $clipData['imageUrl'] = '';

        //剪切板
        if (1 == $skinType) { //diy分享剪切板内容
            $clipData['openUrl'] = 'opencommonskindiy';
            $data['title'] = '我的DIY皮肤';
            $data['content_text'] = '自己制作了一套独有的个性皮肤，感觉自己棒棒哒~ 你也快来做一套吧~';
            $data['share_info'] = array(
                'title' => 'DIY皮肤邀请函',
                'desc' => '邀请你一起制作DIY皮肤，快来Pick一下~',
                'image_url' => !empty($data['real_time_pic']) ? $data['real_time_pic'] . '@w_100,h_100' : '',
            );
        } else {
            if ('ios' == $os) {
                $clipData['openUrl'] = urlencode('opensuperskin?token=' . $skinInfo['token'] . '&tab=1');
            } else {
                $clipData['openUrl'] = 'opensuperskin?token=&tab=1&atoken=' . $skinInfo['token'];
            }
        }
        if(!empty($clipboard_token_handler)) {
            $clipData = call_user_func($clipboard_token_handler, $clipData, array(
                "lottory_token"=>$lottory_token,
            ));
        }
        $redis = GFunc::getCacheInstance();
        $redis->setCacheVersionKey("");
        $clipboardRes = Util::getClipBoardInfoLocal(bd_B64_Encode(json_encode($clipData), 0));
        $data['clipboard_token'] = $clipboardRes === false ? "" : $clipboardRes;
        $result['data'] = $data;

        if (!empty($after_handler)) {
            $result = call_user_func($after_handler, $result, array(
                "lottory_token" => $lottory_token,
                "os" => $os,
            ));
        }

        return Util::returnValue($result);
    }
}
