<?php
/**
 *
 * @desc 表情贴图
 * @path("/emoji/")
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use utils\Util;
use utils\Phaster;
use utils\Consts;
use utils\GFunc;
use models\VoiceDistinguishTranslateModel;


require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';

class Emoji
{
    /** @property share_url_pre */
    protected $share_url_pre;

     /** @property $domain */
    protected $domain;

    protected $micweb_bos;
    
    /** @property 运营分类lite intend缓存key */
    private $strEmojiLiteIntendCombineCache;
    
    /** @property 缓存时间 */
    private $intCacheExpired;

    /**
     * 构造函数
     * @param
     * @return  成功：  _db连接对象
     *          失败：  返回false
     **/
    public function __construct()
    {

        $this->micweb_bos = GFunc::getGlobalConf('micweb_httproot_bos');
    }

    /**
     * @desc 表情市场
     * @route({"GET", "/market"})
     * @param({"cate", "$._GET.cate"}) string $cate 分类
     * @param({"is_wild", "$._GET.is_wild"}) int 是否野表情
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认10条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取     *
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
          "emojiinfo": {
            "domain": "http://r6.mo.baidu.com/",
            "updateTime": 1446190435,
            "ver": "0",
            "emojipre": "upload/emojis/",
            "emojilist": [
              {
                "id": "100146",
                "name": "JJ斗地主（Gif）",
                "down": "108209",
                "emoji_number": "24",
                "emoji_desc": "JJ斗地主是智能手机上最经典的斗地主游戏，JJ地主形象有着独一无二的大金牙，萌萌哒来袭，快来收了我吧！",
                "author": "JJ比赛",
                "pub_item_type": "non_ad",
                "author_link": [
                  {
                    "link": "http://www.jj.cn",
                    "icon": ""
                  }
                ],
                "recommend": "3",
                "img_demo": "2015/09/11/demo/demo_7l9y72gok2_android.png",
                "img_demo_size": 43498,
                "img_icon": "2015/09/11/icon/icon_x476ch2853.png",
                "img_icon_size": 16933,
                "imgs_detail_number": "3",
                "imgs_detail": [
                  "2015/10/29/100146/detail_pics/android_vg89696s37.png",
                  "2015/10/29/100146/detail_pics/android_4nt0q2016b.png",
                  "2015/10/29/100146/detail_pics/android_9to7ypz8yd.png"
                ],
                "url": "v5/emoji/d/?id=100146",
                "size": 1006969
              }
            ]
          }
        }
     */
    function market($cate = 'hot', $sf = 0, $num = Consts::GENERAL_PAGE_SIZE, $plt = 'a1', $ver_name = '5.4.0.0', $is_wild = 0)
    {
        $ver_name = Util::formatVer($ver_name);
        list($sf, $num) = Util::paging($sf, $num);
        $emojiModel = IoCload('models\\EmojiModel');

        $emojipage = $emojiModel->getMarketList($plt, $ver_name, $cate, $sf, $num, $is_wild);

        return $emojipage;
    }
    
    /**
     * @desc 通知中心重构－表情市场
     * @route({"GET", "/noti/market"})
     * @param({"cate", "$._GET.cate"}) string $cate 分类
     * @param({"is_wild", "$._GET.is_wild"}) int 是否野表情
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认10条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取     *
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
          "emojiinfo": {
            "domain": "http://r6.mo.baidu.com/",
            "updateTime": 1446190435,
            "ver": "0",
            "emojipre": "upload/emojis/",
            "emojilist": [
              {
                "id": "100146",
                "name": "JJ斗地主（Gif）",
                "down": "108209",
                "emoji_number": "24",
                "emoji_desc": "JJ斗地主是智能手机上最经典的斗地主游戏，JJ地主形象有着独一无二的大金牙，萌萌哒来袭，快来收了我吧！",
                "author": "JJ比赛",
                "pub_item_type": "non_ad",
                "author_link": [
                  {
                    "link": "http://www.jj.cn",
                    "icon": ""
                  }
                ],
                "recommend": "3",
                "img_demo": "2015/09/11/demo/demo_7l9y72gok2_android.png",
                "img_demo_size": 43498,
                "img_icon": "2015/09/11/icon/icon_x476ch2853.png",
                "img_icon_size": 16933,
                "imgs_detail_number": "3",
                "imgs_detail": [
                  "2015/10/29/100146/detail_pics/android_vg89696s37.png",
                  "2015/10/29/100146/detail_pics/android_4nt0q2016b.png",
                  "2015/10/29/100146/detail_pics/android_9to7ypz8yd.png"
                ],
                "url": "v5/emoji/d/?id=100146",
                "size": 1006969
              }
            ]
          }
        }
     */
    function notiMarket($cate = 'hot', $sf = 0, $num = Consts::GENERAL_PAGE_SIZE, $plt = 'a1', $ver_name = '5.4.0.0', $is_wild = 0)
    {
        $out = Util::initialClass();
        
        $out['version'] = 0;
        
        $out['data'] = $this->market($cate, $sf, $num, $plt, $ver_name, $is_wild);
        
        if(isset($out['data']['ver'])) {
            $out['version'] = intval($out['data']['ver']);
            
            //delete version in old list
            unset($out['data']['ver']);
        }

        return Util::returnValue($out);
    }


     /**
     * @desc 适配v4的表情市场
     * @route({"GET", "/m"})
     * @param({"cate", "$._GET.cate"}) string $cate 分类
     * @param({"sf", "$._GET.page"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.pagesize"}) int $num 分页显示每页的条数，最多200条，默认10条
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取     *
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取     *
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
          "emojiinfo": {
            "domain": "http://r6.mo.baidu.com/",
            "updateTime": 1446190435,
            "ver": "0",
            "emojipre": "upload/emojis/",
            "emojilist": [
              {
                "id": "100146",
                "name": "JJ斗地主（Gif）",
                "down": "108209",
                "emoji_number": "24",
                "emoji_desc": "JJ斗地主是智能手机上最经典的斗地主游戏，JJ地主形象有着独一无二的大金牙，萌萌哒来袭，快来收了我吧！",
                "author": "JJ比赛",
                "pub_item_type": "non_ad",
                "author_link": [
                  {
                    "link": "http://www.jj.cn",
                    "icon": ""
                  }
                ],
                "recommend": "3",
                "img_demo": "2015/09/11/demo/demo_7l9y72gok2_android.png",
                "img_demo_size": 43498,
                "img_icon": "2015/09/11/icon/icon_x476ch2853.png",
                "img_icon_size": 16933,
                "imgs_detail_number": "3",
                "imgs_detail": [
                  "2015/10/29/100146/detail_pics/android_vg89696s37.png",
                  "2015/10/29/100146/detail_pics/android_4nt0q2016b.png",
                  "2015/10/29/100146/detail_pics/android_9to7ypz8yd.png"
                ],
                "url": "v5/emoji/d/?id=100146",
                "size": 1006969
              }
            ]
          }
        }
     */
    function m($cate = 'hot', $sf = 0, $num = Consts::GENERAL_PAGE_SIZE, $plt = 'a1', $ver_name = '5.4.0.0')
    {
        list($sf, $num) = Util::paging($sf, $num);
        $emojiModel = IoCload('models\\EmojiModel');
        $ver_name = trim(str_ireplace('-', '.', substr($ver_name, 0, strripos($ver_name, '-', 1)))); //v4的版本格式
        if($sf < 1){
            $sf = 1;
        }
        $sf = ($sf - 1) * $num;
        $result = $emojiModel->getMarketList($plt, $ver_name, $cate, $sf, $num);

        if(isset($result['emojiinfo']['emojilist']) && is_array($result['emojiinfo']['emojilist']) ) {
            foreach ($result['emojiinfo']['emojilist'] as $k => $v) {
                $result['emojiinfo']['emojilist'][$k]['img_demo_size'] = false;
                $result['emojiinfo']['emojilist'][$k]['img_icon_size'] = false;
                $result['emojiinfo']['emojilist'][$k]['size'] = false;
            }
        }

        return $result;
    }

     /**
     * @desc 运营分类
     * @route({"GET", "/operate"})
     * @param({"is_wild", "$._GET.is_wild"}) int 是否野表情
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "list": [
                {
                    "type": "detail",
                    "tag": "1",
                    "image": "",
                    "pub_item_type": "non_ad",
                    "data": {
                        "id": "100022",
                        "name": "长颈鹿但丁(Gif)",
                        "down": "313218",
                        "emoji_number": "23",
                        "emoji_desc": "主人公是一只造型可爱的长颈鹿——但丁，但丁又名淡定，具有当下年轻人普遍拥有的特质。",
                        "author": "长颈鹿但丁",
                        "author_link": [],
                        "recommend": "0",
                        "emojipre": "upload/emojis/",
                        "domain": "http://220.181.111.219:81/",
                        "img_demo": "2013/11/14/demo/demo_xrbhcn82so_android.png",
                        "img_demo_size": false,
                        "img_icon": "2013/11/25/icon/icon_ocg27us5ut.png",
                        "img_icon_size": false,
                        "imgs_detail_number": "3",
                        "imgs_detail": [
                            "2014/01/21/100022/detail_pics/android_zc80375b8d.png",
                            "2014/01/21/100022/detail_pics/android_58x973vtqr.png",
                            "2014/01/21/100022/detail_pics/android_3io5annqks.png"
                        ],
                        "url": "v5/emoji/d/?id=100146",
                        "size": ''
                    }
                }
            ]
        }
     */
    public function operationCategory($plt = '', $ver_name = '5.4.0.0', $is_wild = '0')
    {
        $res = array(
            "list" => array(
            ),
        );
        $ver_name = Util::formatVer($ver_name);
        $os = Util::getOS($plt);
        $emojiModel = IoCload('models\\EmojiModel');
        $data = $emojiModel->getOpcates($plt, $ver_name, $is_wild);
        $listFlag = 'android' == $os && version_compare($ver_name, '6.8', '<');
        
        foreach ($data as $k => &$v)
        {
            //安卓6.8以下的type为list类型的不做下发
            if ($listFlag && 'list' == $v['type']) {
                unset($data[$k]);
                continue;
            }
            $v['data'] = $v['type'] == 'detail'
                ? $this->getEmojiDetailWithCache(intval($v['tag']), $plt) : (object) array();

            //获取不到详情的去除
            if(!$v['data'])
            {
                unset($data[$k]);
            }
        }
        $res['list'] = array_values($data);

        //Edit by fanwenli on 2016-08-25, combine with lite intend
        //Edit by fanwenli on 2017-08-21, add cache key and cache time
        $res['list'] = Util::liteIntendCombine('emoji', $res['list'], $this->strEmojiLiteIntendCombineCache, $this->intCacheExpired);

        return $res;
    }

    /**
     * @desc emoji 运营分类列表
     * @route({"GET", "/operate/*\/list"})
     * @param({"cat", "$.path[2]"}) int $cat 某自然分类
     * @param({"is_wild", "$._GET.is_wild"}) int 是否野表情
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"ver_name", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"sf", "$._GET.sf"}) int $sf start_from 分页起始记录
     * @param({"num", "$._GET.num"}) int $num 分页显示每页的条数，最多200条，默认12条
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "title": "xxx",
            "domain": "http://r6.mo.baidu.com/",
            "emojipre": "upload/emojis/",
            "list": [
                {
                    //@ref market
                }
            ]
        }
     */
    public function operateList($cat = '', $plt = 'a1', $ver_name = '6.0.0.0', $sf = 0, $num = Consts::GENERAL_PAGE_SIZE, $is_wild = '0')
    {
        $ver_name = Util::formatVer($ver_name);
        $emojiModel = IoCload("models\\EmojiModel");
        $data = $emojiModel->getOpcateList($plt, $ver_name, $cat, $sf, $num, $is_wild);

        return $data;
    }


    /**
     * @desc emoji 表情下载
     * @route({"GET", "/d"})
     * @param({"id", "$._GET.id"}) int 表情id
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function downloadEmoji($id = '')
    {

        $platform = Util::getOs($_GET['platform']);

        $dfrom = $_GET['dfrom'];

        $this_emoji_key = 'ime_api_v5_emoji_d_'.$id.'_'.$platform.'_'.$dfrom;

        $emoji = GFunc::cacheGet($this_emoji_key);

        if($emoji === false || is_null($emoji) || !is_array($emoji))
        {
            $emojiModel = IoCload("models\\EmojiModel");
            $emoji = $emojiModel->getOneemoji($id);
            if($emoji !==  false)
            {
                GFunc::cacheSet($this_emoji_key, $emoji, GFunc::getCacheTime('hours'));

            }else {
                header("HTTP/1.1 404 Not Found");
                header("status: 404 Not Found");
                exit();
            }
        }

        /*
         * 获取表情包文件路径，获取表情包文件大小，设置文件名
        */

        //如果是通过分享下载，则提供ios包
        if($dfrom === 'share'|| $dfrom === 'qrcode'){
            $filepath = $this->micweb_bos . urlencode($emoji['download_link_ios']);
            $newsuffix = ".bde"; //词库包文件后缀，触屏词库,默认
            $filename = 'bi_emoji_ios_' . $id . $newsuffix;
            $this->outputEmojiFile($filepath, $filename ,$emoji['file_size_ios']);
        }

        //如果通过商店下载，则分平台提供包
        if($platform === 'android'){
            $filepath = $this->micweb_bos . urlencode($emoji['download_link_android']);
            $newsuffix = ".bde"; //词库包文件后缀，触屏词库,默认
            $filename = 'bi_emoji_android_' . $id . $newsuffix;
            $this->outputEmojiFile($filepath, $filename, $emoji['file_size_android']);
        }
        if($platform === 'ios'){
            $filepath = $this->micweb_bos . urlencode( $emoji['download_link_ios']);
            $newsuffix = ".bde"; //词库包文件后缀，触屏词库,默认
            $filename = 'bi_emoji_ios_' . $id . $newsuffix;
            $this->outputEmojiFile($filepath, $filename, $emoji['file_size_ios']);
        }
        exit();

    }


    /**
     * 向客户端输出表情包二进制文件，(从远程（v4）获取文件并输出到客户端)
     *
     * @param string $fileurl 表情包文件路径
     * @param string $filename 输出文件名
     * @param int $filesize 输出文件名大小
     */
    private function outputEmojiFile($fileurl, $filename = null, $filesize = null){

        //客户端对所下载文件名不关心，下载直接跳转到V4的静态资源
        @header("Location: " . $fileurl);
        return;

    }

    /**
     * @desc 推荐表情
     * @route({"GET", "/recommend"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"version", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"body"})
     */
    public function recommend($version, $platform = 'a1')
    {
        try {
            $rs = Util::initialClass();
            //平台
            $os = Util::getOs($platform);
            $version = Util::formatVer($version);

            $model = IoCload(\models\EmojiModel::class);
            // 注意此处data 是单条数据， recommend 每次只返回一条数据, 且数据为空时， 是一个object
            $data = $model->getRecommendDataWithCache($os, $version);
            if(is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }
            $conditionFilter = IoCload("utils\\ConditionFilter");
            $datas = $conditionFilter->getFilterConditionFromDB(array( $data ));
            reset($datas);
            $data = current($datas);
            if(empty($data)) {
                // 如果datas 为空， 则current($datas) 返回为false
                $data = new \stdClass();
            }

            $rs = array_merge($rs, array(
                'data'=>$data,
            ));
            return Util::returnValue($rs);
        } catch (Exception $e) {
            return Util::returnValue(array_merge($rs, array(
                'ecode'=>0,
                'emsg'=>$e->getMessage(),
            )));
        }
    }


    /**
     * @desc emoji 表情详情
     * @route({"GET", "/detail"})
     * @param({"id", "$._GET.id"}) int 表情id
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function detailEmoji($id = '', $plt = 'a1')
    {
        //获取uid
        $strId = $id;
        if(null === $strId){
            header('X-PHP-Response-Code: '. 400, true, 400);
            exit();
        }

        //平台
        $platform = Util::getOs($plt);
        $arrEmojiDetail = $this->getEmojiDetailWithCache($id, $platform);

        return $arrEmojiDetail;
    }

    /**
     *
     * @param   $id
     * @param   $os
     * @return
     */
    private function getEmojiDetailWithCache($id, $os)
    {
        $strCacheKey = 'ime_api_v5_emoji_detail_' . $os . '_' . $id;

        $data = GFunc::cacheGet($strCacheKey);

        if($data == false || !is_array($data))
        {
            $emojiModel = IoCload("models\\EmojiModel");
            $emoji = $emojiModel->getOneemoji($id);

            if ($emoji) {
                $data = $this->formatEmojiDetail($emoji, $os);
                GFunc::cacheSet($strCacheKey, $data, $this->intCacheExpired);
            }
        }

        return $data;
    }

    /**
     *
     * @param  [type] $arrEmoji
     * @param  [type] $platform
     * @return [type]
     */
    private function formatEmojiDetail($arrEmoji, $platform)
    {
        $arrEmojiDetail = array();

        //表情包基本信息
        $arrEmojiDetail['id'] = $arrEmoji['uid'];
        $arrEmojiDetail['is_wild'] = $arrEmoji['is_wild'];
        $arrEmojiDetail['name'] = $arrEmoji['title'];
        $arrEmojiDetail['down'] = $arrEmoji['download_number'] + $arrEmoji['add_downloads'];
        $arrEmojiDetail['emoji_number'] = $arrEmoji['emoji_number'];
        $arrEmojiDetail['emoji_desc'] = $arrEmoji['emoji_desc'];
        $arrEmojiDetail['author'] = $arrEmoji['author'];
        $arrEmojiDetail['share_type'] = empty($arrEmoji['share_pic']) ? 0 : 2;
        $arrEmojiDetail['share_pic'] = $arrEmoji['share_pic'] ? $this->micweb_bos . urlencode($arrEmoji['share_pic']) : '';
        $arrEmojiDetail['share_qrcode'] = $arrEmoji['share_qrcode'] ? $this->micweb_bos . urlencode($arrEmoji['share_qrcode']) : '';
        $arrEmojiDetail['share'] = $this->getEmojiShareData($arrEmoji, $platform);

        //作者联系方式
        $arrEmojiDetail['author_link'] = array();
        if(isset($arrEmoji['author_link']) && !empty($arrEmoji['author_link'])){
            $contacts = explode("&&", $arrEmoji['author_link']);

            foreach($contacts as $contact){
                $onelink = array();
                $eles = explode("|", $contact);
                if(count($eles) === 2){
                    $onelink['link'] =  $eles[0];
                    $onelink['icon'] = str_replace('upload/emojis/', '',$eles[1]);
                    $arrEmojiDetail['author_link'][]  = $onelink;
                }
            }
        }

        //推荐类型(0.默认 1.推荐   2.hot 3.new)
        $arrEmojiDetail['recommend']=$arrEmoji['recommend'];
        $arrEmojiDetail['emojipre'] ='upload/emojis/';

        //表情商店列表中的预览图以及anroid单个表情包的详细页面中需要的小图
        $arrEmojiDetail['domain'] = $this->domain;

        //demo图，android和ios不同
        $demoimg = "";

        if($platform === 'android'){
            $demoimg = $arrEmoji['demo_android'];
        }
        if($platform === 'ios'){
            $demoimg = $arrEmoji['demo_ios'];
        }
        $arrEmojiDetail['img_demo'] = str_replace('upload/emojis/', '',$demoimg);

        $arrEmojiDetail['img_demo_size'] = $platform === 'android' ? $arrEmoji['demo_ios_size'] : $arrEmoji['demo_android_size'];

        //ipone单个表情包的详细页面中需要的小图（暂定为icon图）
        $iconimg = $arrEmoji['icon'];
        $arrEmojiDetail['img_icon'] = str_replace('upload/emojis/', '',$iconimg);
        $arrEmojiDetail['img_icon_size'] = $arrEmoji['icon_size'];

        //单个表情包的详细列表图，每个图有8个小图，上下两排，每个表情包的详细图数量不一，ios不需要此项
        if($platform === 'android'){ 
            $arrEmojiDetail['imgs_detail_number'] = $arrEmoji['detail_pic_number'];
            $oneemoji_detail_pic_str = $arrEmoji['detail_pic_android'];
            $oneemoji_detail_pics = explode('|',$oneemoji_detail_pic_str);
            $oneemoji_android_detail_pics = array();
            $i = 0;
            foreach($oneemoji_detail_pics as $pic){
                if ($i > 7) {//android最多显示8页，共8张大图
                    break;
                }
                $oneemoji_android_detail_pics[] = str_replace('upload/emojis/', '',$pic);
                $i++;
            }
            $arrEmojiDetail['imgs_detail'] = $oneemoji_android_detail_pics;
        }
        

        //表情文件包下载地址
        if($platform === 'android'){
            $arrEmojiDetail['url'] = 'v5/emoji/d/?id='.$arrEmoji['uid'];
            $arrEmojiDetail['size'] = intval($arrEmoji['file_size_android']);
        }
        if($platform === 'ios'){
            $arrEmojiDetail['url'] = 'v5/emoji/d/?id='.$arrEmoji['uid'];
            $arrEmojiDetail['size'] = intval($arrEmoji['file_size_ios']);
        }

        return $arrEmojiDetail;
    }


    /**
     * @desc 获取表情分享数据
     * @param  array $emoji 表情数据
     * @param $platform
     * @return array
     */
    private function getEmojiShareData(array $emoji, $platform) {

        $shareType = empty($emoji['share_pic']) ? 0 : 2;
        $shareUrl = $this->share_url_pre . "?id={$emoji['uid']}&ad_test={$shareType}";

        $demoimg = $emoji['demo_android'];
        if ($platform === 'ios')
        {
            $demoimg = $emoji['demo_ios'];
        }

        $thumb = $image = $demoimg ? $this->domain . $demoimg : '';

        if (!empty($emoji['weibo_text'])) {
            $weiboTitle = $emoji['weibo_text'] . $shareUrl;
        } else {
            $weiboTitle  = "「{$emoji['title']}」表情现已加入百度输入法豪华午餐，快来享用吧：{$shareUrl}";
        }
        if (!empty($emoji['weixin_title'])) {
            $weixinTitle = $emoji['weixin_title'];
        } else {
            $weixinTitle = "「{$emoji['title']}」表情好逗！";
        }
        if (!empty($emoji['weixin_desc'])) {
            $weixinDesc = $emoji['weixin_desc'];
        } else {
            $weixinDesc  = "「{$emoji['title']}」表情现已加入百度输入法豪华午餐，快来享用吧！";
        }
        $qqTitle = $weixinDesc;
        $qzoneTitle = $weixinDesc;
        $weixinCircleTitle = $weixinDesc;

        $res = array(
            "title" => '',
            "description" => '',
            "url" => $shareUrl,
            "image" => $image,
            "thumb" => $thumb,
            'platform' => array(
                array(
                    'name' => 'weixin',
                    'content' => array(
                        "title" => $weixinTitle,
                        "description" => $weixinDesc,
                        "url" => $shareUrl ? $shareUrl . "&platform=weixin" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'weixincircle',
                    'content' => array(
                        "title" => $weixinCircleTitle,
                        "description" => '',
                        "url" => $shareUrl ? $shareUrl . "&platform=weixincircle" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'weibo',
                    'content' => array(
                        "title" => $weiboTitle . "&platform=weibo",
                        "description" => '',
                        "url" => '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'qq',
                    'content' => array(
                        "title" => $qqTitle,
                        "description" => '',
                        "url" => $shareUrl ? $shareUrl . "&platform=qq" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'qzone',
                    'content' => array(
                        "title" => $qzoneTitle,
                        "description" => '',
                        "url" => $shareUrl ? $shareUrl . "&platform=qzone" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'system',
                    'content' => array(
                        "title" => $weixinTitle,
                        "description" => $weixinDesc,
                        "url" => $shareUrl ? $shareUrl . "&platform=system" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
            ),
        );

        return $res;
    }

    /**
     * 获取远程文件尺寸
     * @param http_url $remoteUrl
     * @return int
     */
    private function getRemoteFileSize($remoteUrl) {
        $Orp_FetchUrl = new \Orp_FetchUrl();
        $httpproxy = $Orp_FetchUrl->getInstance(array('timeout' =>1000));

        $result = $httpproxy->get($remoteUrl);
        $err = $httpproxy->errmsg();


        if(!$err && $httpproxy->http_code() == 200) {
            $curl_info = $httpproxy->curl_info();
            if(isset($curl_info['size_download'])) {
                return intval($curl_info['size_download']);
            }
        }
        return 0;
    }
    
    /**
     * @desc emoji 搜索sug
     * @route({"GET", "/suggest"})
     * @param({"k", "$._GET.k"}) int 表情id
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"clientVersion", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function suggest($k='', $plt='', $clientVersion='') {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => new stdClass(),
        );
        if (empty($k)) {
            $result['code'] = 1;
            $result['msg'] = '参数为空';
            return $result;
        }
        //emoji
        $objEmojiModel = IoCload('models\\EmojiModel');
        $platform = Util::getOS($plt);
        $clientVersion = Util::formatVer($clientVersion);
        $emojiList = $objEmojiModel->cache_getEmoji($k, $platform, $clientVersion, GFunc::getCacheTime('2hours'));
        if (!empty($emojiList)) {
            foreach ($emojiList as $emojiListK => $emojiListV) {
                if (!empty($emojiListV['title'])) {
                    $result['data']->suggest[] = $emojiListV['title'];
                }
            }
        }
        //emoticon 颜文字
        $objEmoticonModel = IoCload('models\\EmoticonModel');
        $emoticonList = $objEmoticonModel->cache_getEmoticon($k, $platform, $clientVersion, GFunc::getCacheTime('2hours'));
        if (!empty($emoticonList)) {
            foreach ($emoticonList as $emoticonListV) {
                if (!empty($emoticonListV['name'])) {
                    $result['data']->suggest[] = $emoticonListV['name'];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * @desc emoji 搜索
     * @route({"GET", "/search"})
     * @param({"k", "$._GET.k"}) string 搜索关键词
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @param({"clientVersion", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)，不需要客户端传，从加密参数中获取
     * @param({"sf", "$._GET.sf"}) int 起始数据
     * @param({"num", "$._GET.num"}) int 条数
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function search($k='', $plt='', $clientVersion='', $sf=0, $num=12) {
        $v4Domain = GFunc::getGlobalConf('domain_v4');
        $v5Domain = GFunc::getGlobalConf('domain_v5');
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => array(
                'emojilist' => array(),
                'domain' => $v4Domain,
                'emojipre' => 'upload/emojis/',
                'is_sug' => 0,
            ),
        );
        if (empty($k)) {
            $result['code'] = 1;
            $result['msg'] = '参数为空';
            return $result;
        }
        //emoji
        $objEmojiModel = IoCload('models\\EmojiModel');
        $platform = Util::getOS($plt);
        $clientVersion = Util::formatVer($clientVersion);
        //三个月
        $dateTime = date('Y-m-d H:i:s', time() - 86400 * 90);
        //三个月之内的按照上限时间倒叙排
        $emojiListBefore = $objEmojiModel->cache_getEmojiAndPicByOnlineTime($k, $platform, $clientVersion, $dateTime, '>=', 'online_time desc', GFunc::getCacheTime('2hours'));
        //三个月之前的按照下载量倒叙排序
        $emojiListAfter = $objEmojiModel->cache_getEmojiAndPicByOnlineTime($k, $platform, $clientVersion, $dateTime, '<', 'download_number desc', GFunc::getCacheTime('2hours'));
        $emojiList = array_merge($emojiListBefore, $emojiListAfter);
        $emojiList = array_unique($emojiList, SORT_REGULAR);

        //emoticon 颜文字
        $objEmoticonModel = IoCload('models\\EmoticonModel');
        $emoticonList = $objEmoticonModel->cache_getEmoticon($k, $platform, $clientVersion, GFunc::getCacheTime('2hours'));
        //如果搜索结果为空，则下发后台配置的推荐的
        if (empty($emojiList) && empty($emoticonList)) {
            $result['data']['is_sug'] = 1;
            $arrSuggestIds = $objEmojiModel->cache_getSuggest(GFunc::getCacheTime('2hours'));
            //根据ID获取表情
            if (!empty($arrSuggestIds['emoji_id'])) {
                $emojiList = $objEmojiModel->cache_getEmojiByIds($arrSuggestIds['emoji_id']);
            }
            if (!empty($arrSuggestIds['emoticon_id'])) {
                $emoticonList = $objEmoticonModel->cache_getEmoticonByIds($arrSuggestIds['emoticon_id']);
            }
            //如果后台配置数量不足10个则按下载量排序用表情补齐
            if (count($emojiList) + count($emoticonList) < 10) {
                $limit = 10 - (count($emojiList) + count($emoticonList));
                
                //Edit by fanwenli on 2019-08-27, just give result which type is 1
                $condition = 'type = 1';
                if (!empty($arrSuggestIds['emoji_id'])) {
                    $condition .= " and uid not in ({$arrSuggestIds['emoji_id']})";
                }
                
                $arrDownloadEmoji = $objEmojiModel->cache_getEmojiOrderByCondition($condition, 'download_number desc', $limit);
                if (!empty($arrDownloadEmoji)) {
                    $emojiList = array_merge($emojiList, $arrDownloadEmoji);
                }
            }
        }
        //分页
        $start = $sf - 1 > 0 ? $sf - 1 : 0;
        //表情数据格式化
        $result['data']['emojilist'] = array_slice(array_merge($emojiList, $emoticonList), $start, $num);
        //数据格式化
        if (!empty($result['data']['emojilist'])) {
            foreach ($result['data']['emojilist'] as $emojiListK => $emojiListV) {
                if (1 == $emojiListV['item_type']) {
                        // 作者联系方式
                    $result['data']['emojilist'][$emojiListK]['author_link'] = array();
                    if (! empty($emojiListV['author_link'])) {
                        $contacts = explode("&&", $emojiListV['author_link']);
                        foreach ($contacts as $contact) {
                            $onelink = array();
                            $eles = explode("|", $contact);
                            if (count($eles) === 2) {
                                $onelink['link'] = $eles[0];
                                $onelink['icon'] = str_replace('upload/emojis/', '', $eles[1]);
                                $result['data']['emojilist'][$emojiListK]['author_link'][]  = $onelink;
                            }
                        }
                    }
                    // demo图，android和ios不同
                    $result['data']['emojilist'][$emojiListK]['img_demo'] = str_replace('upload/emojis/', '', $result['data']['emojilist'][$emojiListK]["demo_{$platform}"]);
                    // ipone单个表情包的详细页面中需要的小图（暂定为icon图）
                    $result['data']['emojilist'][$emojiListK]['img_icon'] = str_replace('upload/emojis/', '', $result['data']['emojilist'][$emojiListK]['img_icon']);
                    // 单个表情包的详细列表图，每个图有8个小图，上下两排，每个表情包的详细图数量不一，ios不需要此项
                    if ($platform === 'android') {
                        $oneemojiDetailPicStr = $emojiListV['detail_pic_android'];
                        $oneemojiDetailPics = array_slice(explode('|', $oneemojiDetailPicStr), 0, 7);
                        foreach ($oneemojiDetailPics as &$pic) {
                            $pic = str_replace('upload/emojis/', '', $pic);
                        }
                        $result['data']['emojilist'][$emojiListK]['imgs_detail'] = $oneemojiDetailPics;
                    }
                    $result['data']['emojilist'][$emojiListK]['url'] = 'v5/emoji/d?id=' . $emojiListV['id'];
                } else if (2 == $emojiListV['item_type']) {
                    $result['data']['emojilist'][$emojiListK]['auther'] = $emojiListV['author'] ? $emojiListV['author'] : '百度输入法';
                    $result['data']['emojilist'][$emojiListK]['url'] = $v5Domain . 'v5/emoticon/items/' . $emojiListV['id'] . '/file';
                    $result['data']['emojilist'][$emojiListK]['thumb'] = $emojiListV['thumb'] ? $v4Domain . $emojiListV['thumb'] : '';
                    $result['data']['emojilist'][$emojiListK]['pic_1'] = $emojiListV['pic_1'] ? $v4Domain . $emojiListV['pic_1'] : '';
                    $result['data']['emojilist'][$emojiListK]['pic_2'] = $emojiListV['pic_2'] ? $v4Domain . $emojiListV['pic_2'] : '';
                    $result['data']['emojilist'][$emojiListK]['pic_3'] = $emojiListV['pic_3'] ? $v4Domain . $emojiListV['pic_3'] : '';
                }
            }
        }
        
        return $result;
    }

    /**
     *
     * @desc emoji resource package http://agroup.baidu.com/inputserver/md/article/1361622
     *
     * @route({"GET", "/emoji_resource_package"})
     * @param({"message_version", "$._GET.message_version"}) $message_version message_version 客户端上传版本
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){
     *      "ecode": "0",
     *      "emsg": "success",
     *      "data": array,
     *      "version": 1526352952
     * }
     */
    public function getEmojiResourcePackage($message_version = 0) {
        $model = IoCload("models\\EmojiResourcePackage");
        //输出格式初始化
        $rs = Util::initialClass();
        $rs['data'] = $model->getResource();
        $rs['version'] = intval($model->getVersion());
        return Util::returnValue($rs);
    }
    
    
    /**
     * @desc AI配图表情
     * @route({"GET", "/ai"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @param({"version", "$._GET.version"}) string 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"platform", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"body"})
     */
    public function ai($version, $platform = 'a1') {
        $out = Util::initialClass(false);

        //平台
        $os = Util::getOs($platform);
        $version = Util::formatVer($version);
        
        $model = IoCload("models\\EmojiModel");
        $data = $model->getAiData($os, $version);
        
        //get result from array randomly
        shuffle($data);
        
        //Edit by fanwenli on 2019-10-09, get all is 1 then list all item
        if(isset($_GET['all']) && intval($_GET['all']) == 1) {
            $arrImg = $data;
        } else {
            //only get 30 images from array
            $arrImg = array_slice($data, 0, 30);
        }
        
        $out['data'] = $arrImg;
        
        //Edit by fanwenli on 2019-12-10, add log collect
        $model = IoCload("models\\SearchEmojiModel");
        $model->inputCount('other');
        
        return Util::returnValue($out, false);
    }
}
