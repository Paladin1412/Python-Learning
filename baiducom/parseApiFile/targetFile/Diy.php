<?php
/**
 *
 * @desc Diy业务接口
 * @path("/diy/")
 *
 */

use utils\Util;
use utils\GFunc;
use utils\ErrorCode;
use strategy\DiyUploadStrategy;
use models\SkinUserDiy;
use utils\FjLib;
use utils\Strings;
class Diy
{
    /** @property 图搜缓存key pre*/
    private $strDiySearchCachePre;
    
    /***
     * 构造函数
     * @return void
     */
    public function  __construct() {
        
    }

    /**
     * @desc diy皮肤启用
     * @route({"POST", "/skin_active"})
     * @param({"type", "$._POST.type"}) string 皮肤token
     * @param({"groups", "$._POST.groups"}) string 皮肤token
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function skinActive($type, $groups) {
        if (!in_array($type, array(1, 2))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "invalid type");
        }

        $commonType = 1;
        $documentType = 2;
        $groups = json_decode($groups, false);
        $map = array(
            $commonType => function($groups) {
                $cache = Gfunc::getCacheInstance();
                $groups = implode(",", $groups);
                $cacheKey = md5(__CLASS__ . __FILE__ .
                    __FUNCTION__ . 1 . $groups . json_encode(Util::clientParameters()));
                $succeed = false;
                $recordList = $cache->getBig($cacheKey, $succeed);
                if ($succeed) {
                    return $recordList;
                }

                $model = new \models\CommonDiyModel();
                $sql = sprintf("SELECT * FROM skin_common_diy_template WHERE status=%d AND group_id IN (%s)",
                    \models\CommonDiyModel::STATUS_ONLINE,
                    $groups);
                $recordList = $model->query($sql);
                $filter = IoCload(\utils\ConditionFilter::class);
                $recordList = $filter->getFilterConditionFromDB($recordList, "filter_id");
                $cache->setBig($cacheKey, $recordList, GFunc::getCacheTime('2hours'));
                return $recordList;
            },
            $documentType => function($groups) {
                $cache = Gfunc::getCacheInstance();
                $groups = implode(",", $groups);
                $cacheKey = md5(__CLASS__ . __FILE__ . __FUNCTION__ . 2 .
                    $groups . json_encode(Util::clientParameters()));
                $succeed = false;
                $recordList = $cache->getBig($cacheKey, $succeed);
                if ($succeed) {
                    return $recordList;
                }

                $model = new \models\DocumentDiyModel();
                $sql = sprintf("SELECT * FROM skin_document_diy_template WHERE status=%d AND group_id IN (%s)",
                    \models\DocumentDiyModel::STATUS_ONLINE,
                    $groups);
                $recordList = $model->query($sql);
                $filter = IoCload(\utils\ConditionFilter::class);
                $recordList = $filter->getFilterConditionFromDB($recordList, "filter_id");
                $cache->setBig($cacheKey, $recordList, GFunc::getCacheTime('2hours'));
                return $recordList;
            }
        );
        $recordList = call_user_func($map[$type], $groups);
        $data = array(
            "type" => $type,
            "resources" => array(),
        );
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        foreach ($recordList as $record) {
            $data['resources'][] = array(
                "resource_id" => $record["id"],
                "group_id" => $record["group_id"],
                "type" => $record["type"],
                "url" => empty($record['zip']) ? "" : sprintf("%s/%s", $bosHost, $record['zip'])
            );
        }

        $ret = \utils\Util::initialClass();
        $ret["data"] = $data;
        return Util::returnValue($ret);
    }

    /**
     * @desc diy皮肤分享的形式下发
     * @route({"GET", "/share"})
     * @param({"id", "$._GET.diySkinId"}) string 皮肤token
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function share($id) {
        $model = new \models\SkinUserDiy();
        $record = $model->share($id);
        $ret = \utils\Util::initialClass();
        if (empty($record)) {
            return $ret;
        }

        $ret['data'] = $record;
        return Util::returnValue($ret);
    }

    /**
     * @desc diy皮肤分享的形式下发
     * @route({"GET", "/detail"})
     * @param({"id", "$._GET.diySkinId"}) string 皮肤token
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function detail($id) {
        $model = new \models\SkinUserDiy();
        $record = $model->detail($id);
        $ret = \utils\Util::initialClass();
        if (empty($record)) {
            return $ret;
        }

        $ret['data'] = $record;
        return Util::returnValue($ret);
    }

    /**
     * @desc 滤镜数据下发
     * @route({"GET", "/lens"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})[]
     */
    public function lens() {
        $model = new \models\LensModel();
        $recordList = $model->lensList();
        if ($recordList === false) {
            return ErrorCode::returnError(ErrorCode::EMPTY_RESULT, "结果为空");
        }

        $ret = \utils\Util::initialClass(false);
        $ret['data'] = $recordList;
        return Util::returnValue($ret,false);
    }

    /**
     * @desc 通用DIY模板数据下发
     * @route({"GET", "/common_diy_index"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function commonDiyIndex() {
        $model = new \models\CommonDiyModel();
        $recordList = $model->templateListGroupType();
        if ($recordList === false) {
            return ErrorCode::returnError(ErrorCode::EMPTY_RESULT, "结果为空");
        }

        $ret = \utils\Util::initialClass();
        $ret['data'] = $recordList;
        return Util::returnValue($ret);
    }

    /**
     * @desc 文案DIY模板数据下发
     * @route({"GET", "/document_diy_index"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function documentDiyIndex() {
        $model = new \models\DocumentDiyModel();
        $recordList = $model->templateListGroupType();
        if ($recordList === false) {
            return ErrorCode::returnError(ErrorCode::EMPTY_RESULT, "结果为空");
        }

        $ret = \utils\Util::initialClass();
        $ret['data'] = $recordList;
        return Util::returnValue($ret);
    }

    /**
     * @desc 百变DIY标题下发
     * @route({"GET", "/variety_diy_title"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function varietyDiyTitle() {
            $model = new \models\VarietyDiyTitle();
            $data = $model->getHeader();
            if ($data === false) {
                return ErrorCode::returnError(ErrorCode::EMPTY_RESULT, "结果为空");
            }

            $ret = Util::initialClass();
            $ret['data'] = $data;
            return Util::returnValue($ret);
    }

    /**
     * @desc 百变DIY热门推荐
     * @route({"GET", "/variety_diy_recommend"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})[]
     */
    public function varietyDiyRecommend() {
        $model = new \models\VarietyBackgroundDiyRecommendModel();
        $recordList = $model->getRecommendList();
        if ($recordList === false) {
            return ErrorCode::returnError(ErrorCode::EMPTY_RESULT, "结果为空");
        }

        $ret = Util::initialClass(false);
        $ret['data'] = $recordList;
        return Util::returnValue($ret,false);
    }

    /**
     * @desc 搜图接口内容下发
     * @route({"POST", "/imagesearch"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function getImageSearch() {
        //keyword
        $query = isset($_POST['query']) ? trim($_POST['query']) : '';
        
        //return error when there has not query
        if($query == '') {
            $ecode = ErrorCode::PARAM_ERROR;
            return ErrorCode::returnError($ecode,'The post data is empty!',true);
        }
        
        //return page
        $page = isset($_GET['pic_page']) ? intval($_GET['pic_page']) : 0;
        
        //return number
        $num = isset($_GET['pic_number']) ? intval($_GET['pic_number']) : 60;
        
        $out = Util::initialClass(false);
        
        //Edit by fanwenli on 2019-12-11, do not return result when page bigger than 2
        if($page <= 1) {
            $data = $this->getImage($query, $page, $num);
        } else {
            $data = array();
        }
        
        $out['data'] = $data;
        
        return Util::returnValue($out,false);
    }
    
    /**
     * get all image from api
     * 
     * @param $query 关键词
     * @param $page 页码
     * @param $num 每页数量
     * 
     * return array
     * 
     */
    public function getImage($query, $page, $num) {
        $arrConf = GFunc::getConf('Diy');
        $strCacheKey = $arrConf['properties']['strDiySearchCachePre'];
        
        //set cache key
        $strCacheKey = $strCacheKey . $query . '_' . $page . '_' . $num;
        
        $objRedis = IoCload('utils\\KsarchRedis');
        $data = GFunc::cacheZgetOrigin($objRedis, $strCacheKey);
        if($data === false){
            $data = array();
            
            //Edit by fanwenli on 2019-10-14, return empty when query is unsecurity
            if(Util::securityservice($query) === false) {
                $searchModel = IoCload("models\\SearchImageModel");

                //根据页数page以及每页大小num得到起始张数
                $begImgNum = $page * $num;
                // 此处逻辑如有改动， 请同步修改 amisapi 中关键词干扰策略
                //Edit by fanwenli on 2019-11-26, change query with dbx and use it when it was been set
                $strQueryInterpose = $searchModel->getSearchQueryInterpose($query);
                if($strQueryInterpose != '') {
                    $query = $strQueryInterpose;
                } else {
                    //Edit by fanwenli on 2019-11-11, add 高清壁纸
                    if (!preg_match('/高清壁纸$/i', $query, $arr)) {
                        $query .= '高清壁纸';
                    }
                }

                //Edit by fanwenli on 2019-10-28, return safe model
                $result = $searchModel->getRequestFromImageapi($query, $begImgNum, $num, true);
                
                if (!empty($result)) {
                    if (isset($result['data']['ResultArray']) && !empty($result['data']['ResultArray'])) {

                        //add by zhoubin05 on 2019-11-11 for 图片经过后台设定的进行过滤
                        //http://newicafe.baidu.com/issue/inputserver-2554/show?from=page
                        $brModel = IoCload("models\\BefilteredResourceModel");

                        $arrFilterImgs = $brModel->getDataByProvierWithCache(1, $query);

                        foreach ($result['data']['ResultArray'] as $v) {

                            if(in_array($v['Key'], $arrFilterImgs)) {
                                //如果图片在过滤条件中则过滤（跳过）
                                continue;
                            }

                            //Edit by fanwenli on 2019-11-04, get image extension from amis
                            $arrPathinfo = pathinfo($v['FromUrl']);
                            $extension = $arrPathinfo['extension'];
                            
                            //dismiss vcg.com and Pictype is gif
                            //Edit by fanwenli on 2019-11-01, get result if IsGif is 0
                            if (!stristr($v['FromUrl'], 'vcg.com') && $v['Pictype'] != 'gif' && ((isset($v['IsGif']) && intval($v['IsGif']) == 0) || (!isset($v['IsGif']) && $extension != 'gif'))) {
                                $data[] = array(
                                    'id' => $v['Key'],
                                    'image' => $v['ObjUrl'],
                                    'o_width' => $v['Width'],
                                    'o_height' => $v['Height'],
                                    'thumbnail' => $v['ThumbnailUrl'],
                                    'width' => $v['ThumWidth'],
                                    'height' => $v['ThumHeight'],
                                    'fromurl' => $v['FromUrl'],
                                );
                            }
                        }
                    }
                }
            }
            
            //set cache, 15min
            GFunc::cacheZsetOrigin($objRedis, $strCacheKey, $data, GFunc::getCacheTime('15mins'));
        }
        
        return $data;
    }

    
    /**
     * return 图片数据
     */
    public function transImage($oriImgBase64, $blackBgImgBase64, $maxGray = 128) {
        //对上传图片做处理，只获取b64信息给到切割接口
        if(stristr($oriImgBase64,'base64,')) {
            $arrSource = explode('base64,', $oriImgBase64);
            $oriImgBase64 = $arrSource[1];
        }
        if(stristr($blackBgImgBase64,'base64,')) {
            $arrSource = explode('base64,', $blackBgImgBase64);
            $blackBgImgBase64 = $arrSource[1];
        }
        // 从返回的数据中读取黑背景白色人像图
        $oriImg = imagecreatefromstring(base64_decode($oriImgBase64));
        $blackBgImg = imagecreatefromstring(base64_decode($blackBgImgBase64));
        unset($oriImgBase64);
        unset($blackBgImgBase64);

        // 宽高
        $oriImgWidth = imagesx($oriImg);
        $oriImgHeight = imagesy($oriImg);

        // 创建透明背景的真彩色图
        $destImg = imagecreatetruecolor($oriImgWidth, $oriImgHeight);
        $white = imagecolorallocate($destImg, 255, 255, 255);
        imagefill($destImg, 0, 0, $white);
        imagecolortransparent($destImg, $white);

        // 将非灰度区域的颜色填充至透明背景的图片
        for($w = 0; $w < $oriImgWidth; $w ++) {
            for($h = 0; $h < $oriImgHeight; $h ++) {
                // 获取黑白背景图片
                $index = imagecolorat($blackBgImg, $w, $h);
                $colors = imagecolorsforindex($blackBgImg, $index);
                // 计算像素点灰度值
                $gray = $colors['red'] * 0.3 + $colors['green'] * 0.59 + $colors['blue'] * 0.11;
                $isBlack = intval($gray) < $maxGray;
                if(!$isBlack) {
                    // 从原图位置取来正确的颜色
                    $index = imagecolorat($oriImg, $w, $h);
                    $colors = imagecolorsforindex($oriImg, $index);
                    $renderColor = imagecolorallocate($destImg, $colors['red'], $colors['green'], $colors['blue']);
                    imagesetpixel($destImg, $w, $h, $renderColor);
                }
            }
        }
        $content = '';
        // 因base64 不方便获取图片扩展， 所以以png格式输出
        ob_start();
        imagepng($destImg);
        $content = base64_encode(ob_get_contents());
        ob_end_clean();
        imagedestroy($oriImg);
        imagedestroy($destImg);
        imagedestroy($blackBgImg);
        return $content;
    }

    /**
     * pm 需要的人像分割接口， 提供给工具调用
     * @route({"POST", "/body_segregate_v2"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function bodySegregateV2() {
        $content = file_get_contents('php://input');
        $post = (array)json_decode($content, true);
        if (!isset($post["image"]) || empty($post['image'])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "image不能为空");
        }

        $model = IoCload(\models\BodyAnalysis::class);
        $data = $model->segregate($post["image"]);
        if ($data === false || empty($data)) {
            return ErrorCode::returnError(ErrorCode::SERVER_FATAL_ERR, "人像分割失败");
        }

        try {
            $transData = $this->transImage($post["image"], $data);
            if(!empty($transData)) {
                $data = $transData;
            }
        } catch (\Throwable $th) {
        }
        $ret = Util::initialClass();
        $ret['data'] = array(
            "scoremap" => $data,
        );
        return Util::returnValue($ret);
    }

    /**
     * @desc 人像分割
     * @route({"POST", "/body_segregate"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"}){}
     */
    public function bodySegregate() {
        $content = file_get_contents('php://input');
        $post = (array)json_decode($content, true);
        if (!isset($post["image"]) || empty($post['image'])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "image不能为空");
        }

        $model = IoCload(\models\BodyAnalysis::class);
        $data = $model->segregate($post["image"]);
        if ($data === false || empty($data)) {
            return ErrorCode::returnError(ErrorCode::SERVER_FATAL_ERR, "人像分割失败");
        }

        $ret = Util::initialClass();
        $ret['data'] = array(
            "scoremap" => $data,
        );
        return Util::returnValue($ret);
    }

    /**
     * @desc 检测用户是否已经上传自定义的diy皮肤包 http://agroup.baidu.com/inputserver/md/article/2607582
     * @param({"cuid", "$._GET.cuid"})
     * @param({"token", "$._GET.token"})
     * @route({"GET", "/check_exists"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function checkCustomSkinExists($cuid, $token) {
        if(empty(trim($token))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'token can not be empty');
        }
        if(empty(trim($cuid))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'cuid can not be empty');
        }
        $token = strtolower($token);
        $userDiyModel = IoCload(SkinUserDiy::class);
        // 如果用户不传local image 则token存在重复的可能， 所以必须和cuid 结合查询
        $datas = $userDiyModel->getUploadedDiy($cuid);
        $md5s = array_column($datas, 'md5');
        $key = array_search($token, $md5s);
        $ret = Util::initialClass();
        $dataStd = $ret['data'];
        $dataStd->is_exists = $key !== false ? 1 : 0;
        $dataStd->contribute_status = 0;
        $dataStd->is_exists = 0;
        // 能够查找到数据
        if(false !== $key) {
            $dataStd->is_exists = 1;
            $dataStd->contribute_status = $datas[$key]['contribute'] == SkinUserDiy::UN_CONTRIBUTE ? 0 : 1;
        }
        return Util::returnValue($ret);
    }

    /**
     * @desc 上传用户自定义的皮肤包数据 http://agroup.baidu.com/inputserver/md/article/2607614
     * @param({"cuid", "$._GET.cuid"})
     * @route({"POST", "/upload_custom"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function uploadCustomSkin($cuid) {
        // 校验必选参数
        $postRequiredParams = array(
            // 'author',
            'name',
            'sign',
            'have_local_img',
            'contribute',
            'type'
        );
        foreach($postRequiredParams as $val) {
            if(!isset($_POST[$val]) || trim($_POST[$val]) === '') {
                return ErrorCode::returnError(ErrorCode::PARAM_ERROR, $val . ' missed');
            }
        }
        // 对author 赋予默认值, 端上可能用户登录后任然取到空的用户名
        if(empty($_POST['author'])) {
            $_POST['author'] = '小秘密';
        }
        // cuid也校验一下
        if(empty(trim($cuid))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'cuid can not be empty');
        }
        $fjLib = new FjLib();
        $_POST['name'] = $fjLib->trans($_POST['name']);
        // 校验皮肤名称的长度, 目前允许输入最长汉字12个字, 如果纯英文则可输入24个
        $nameLen  = mb_strlen(trim($_POST['name']));
        $length = 0;
        for($i = 0; $i < $nameLen; $i++) {
            $chr = mb_substr(trim($_POST['name']), $i, 1);
            if(Strings::isChineseChr($chr)) {
                $length += 1;
            } else if(Strings::isEngilshChr($chr) || is_numeric($chr)) {
                $length += 0.5;
            } else {
                return ErrorCode::returnError(ErrorCode::PARAM_ERROR, '皮肤名称仅支持中文,英文和数字');
            }
        }
        if($length > 12) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, '皮肤名称太长啦!');
        }
        // 校验文案diy的长度
        if(!empty($_POST['document_diy_text'])) {
            if(strlen($_POST['document_diy_text']) > 1000) {
                return ErrorCode::returnError(ErrorCode::PARAM_ERROR, '皮肤名称太长啦!');
            }
            // 不能入库的数据过滤掉
            $_POST['document_diy_text'] = Strings::mysqlFilter(trim($_POST['document_diy_text']));
        }
        // 校验参数取值的正确性
        if(!in_array(intval(trim($_POST['type'])), [
            SkinUserDiy::DIY_COMMON_TYPE, SkinUserDiy::DIY_TEXT_TYPE, SkinUserDiy::DIY_VARIETY_TYPE,
        ])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'type value illegal');
        }
        if(!in_array(trim($_POST['have_local_img']), [1, 0]) || !in_array(trim($_POST['contribute']), [1, 0])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'have_local_img/contribute must be in [ 1, 0 ]');
        }
        $diyType = intval(trim($_POST['type']));
        $haveLocalImg = intval(trim($_POST['have_local_img'])) === 1;
        $contribute = intval(trim($_POST['contribute']));
        // 如果是文案diy 则document_diy_text 参数不能为空
        // if(SkinUserDiy::DIY_TEXT_TYPE === $diyType && empty(trim($_POST['document_diy_text']))) {
        //     return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'text diy must have document_diy_text');
        // }
        // 如果用户选择了投稿， 则用户必须登录 todo 校验uid 的正确性
        if(1 === $contribute  && (empty(trim($_POST['uid'])) || empty($_POST['ukey']))) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'contribute diy must login first: [uid/ukey] miss');
        }
        if(!empty($_POST['uid'])) {
            // 对uid进行解密
            $_POST['uid'] = bd_AESB64_Decrypt(trim($_POST['uid']));
        }
        //校验用户信息的有效性
        if(1 === $contribute) {
            $bdussModel = IoCload("models\\BdussModel");
            $userInfo = $bdussModel->getUserInfoByBdussStr(trim($_POST['ukey']));
            if(false === $userInfo) {
                return ErrorCode::returnError(ErrorCode::BDUSS_ERR, '用户登录信息失效');
            }
            if($userInfo['uid'] != trim($_POST['uid'])) {
                return ErrorCode::returnError(ErrorCode::BDUSS_ERR, 'uid和用户信息不符');
            }
        }
        // 查看文件是否已经有上传记录， 如果已经上传, 则不check config package 和 preview_image
        $_POST['sign'] = strtolower(trim($_POST['sign']));
        $userDiyModel = IoCload(SkinUserDiy::class);
        $diyStrategy = IoCload(DiyUploadStrategy::class);
        $uploadDatas = $userDiyModel->getUploadedDiy($cuid);
        $haveUploadedMd5s = array_column($uploadDatas, 'md5');
        $uploadKey = array_search($_POST['sign'], $haveUploadedMd5s);
        $cacheDiySkinId = false;
        if(false !== $uploadKey) {
            // 取出预览图继续走一遍审核流程
            $previewImg = $uploadDatas[$uploadKey]['preview_image'];
            // 取出数据， 防止数据被硬删除, 而缓存未过期的情况
            $configPackage = $uploadDatas[$uploadKey]['config_package'];
            // 取出缓存的id
            $cacheDiySkinId = $uploadDatas[$uploadKey]['id'];
        } else {
            // 校验文件
            if(empty($_FILES['config_package']) || empty($_FILES['preview_image'])) {
                return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'you must be upload config_package and preview_image');
            }
            $configPackageInfo = $_FILES['config_package'];
            $previewImgInfo = $_FILES['preview_image'];
            if($_POST['sign'] !== md5_file($configPackageInfo['tmp_name'])) {
                return ErrorCode::returnError(ErrorCode::SIGN_ERROR, 'diy配置包损坏, 请重新上传');
            }
            // preview_image 必须是图片文件
            if(!in_array(exif_imagetype($previewImgInfo['tmp_name']), [
                IMAGETYPE_PNG, IMAGETYPE_JPEG,
            ])) {
                return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'image must be png/jpg');
            }
            $previewImg = $diyStrategy->upload($previewImgInfo, 'img');
            if(false === $previewImg) {
                return ErrorCode::returnError(ErrorCode::UPLOAD_ERROR, '预览图上传失败, 请重试');
            }
            $configPackage = $diyStrategy->upload($configPackageInfo);
            if(false === $configPackage) {
                return ErrorCode::returnError(ErrorCode::UPLOAD_ERROR, '皮肤包上传失败, 请重试');
            }
            // 上传审核预览图
            // $reviewImg = $diyStrategy->getReviewImg($previewImgInfo['tmp_name'], pathinfo($previewImg, PATHINFO_EXTENSION));
        }
        // 名称转图片协程, 并传递文字颜色
        $nameImgTask = new \PhasterThread(array($diyStrategy, 'genTextImg'), array($_POST['name'], isset($_POST['name_color']) ? intval($_POST['name_color']) : 1));
        // 预览图审核
        $previewImgTask = new \PhasterThread(array($diyStrategy, 'imageReview'), array($previewImg, false));
        // 文案审核, 根据长度不同和qps的限制可能生成两个异步协程
        $textReviewTasks = $diyStrategy->genTextReviewTask(trim($_POST['name']), isset($_POST['document_diy_text']) ? trim($_POST['document_diy_text']) : '');
        $nameImg = $nameImgTask->join();
        list($isImgReviewPassed, $previewImgAssessInfo) = $previewImgTask->join();
        $isTextReviewPassed = true;
        $textAssessInfo = array();
        foreach($textReviewTasks as $textReviewTask) {
            list($isPassed, $assessInfo)= $textReviewTask->join();
            $isTextReviewPassed = $isTextReviewPassed && $isPassed;
            $textAssessInfo = array_merge($textAssessInfo, $assessInfo);
        }
        if(false === $nameImg) {
            return ErrorCode::returnError(ErrorCode::GENERATE_PIC_ERROR, '名称图片生成失败, 请重试');
        }
        // 最后将结果持久化
        $currStatus = $userDiyModel->getPackageStatus($haveLocalImg, $isTextReviewPassed, $isImgReviewPassed);
        // 得到正确的入库数据
        $dbParams = $userDiyModel->parseRequest($_POST, array(
            'cuid' => $cuid,
            'preview_image' => $previewImg,
            'name_image' => $nameImg,
            'contribute' => $userDiyModel->getContributeStatus($contribute),
            'status' => $currStatus,
            'ai_assess_information' => array_merge($previewImgAssessInfo, $textAssessInfo),
            'config_package' => $configPackage,
        ));
        if(false !== $cacheDiySkinId) {
            // 如果不是新记录， 则传递id参数
            $dbParams['id'] = $cacheDiySkinId;
        }
        // 传递是否是新增参数进去
        $userDiyId = $userDiyModel->save(Util::clientParameters(), $dbParams, false === $cacheDiySkinId);
        if(false === $userDiyId) {
            return ErrorCode::returnError(ErrorCode::DB_ERROR, '保存失败, 请重试');
        }
        // 如果需要的话， 开始异步审核流程
        $userDiyModel->asyncReviewLocalImg($userDiyId, $haveLocalImg, $currStatus);
        // diy上传成功， 此时更新用户的已上传列表缓存
        Util::asyncNative(SkinUserDiy::class, 'asyncGetUploadedDiy', array($cuid, true));
        // 第一上传成功后， 更新二维码图片
        if(false === $uploadKey) {
            // 生成二维码图片
            $qrCode = $diyStrategy->genQRCode($userDiyModel->getShareLink($userDiyId), empty($_POST['qr_size']) ? 200 : $_POST['qr_size']);
            if(false === $qrCode) {
                return ErrorCode::returnError(ErrorCode::GENERATE_PIC_ERROR, '二维码生成失败, 请重试');
            }
            $updateParams = $userDiyModel->parseRequest($_POST, array(
                'cuid' => $cuid,
                'qr_code' => $qrCode,
            ));
            $updateParams['id'] = $userDiyId;
            $userDiyModel->save(Util::clientParameters(), $updateParams, false);
        }
        // respons;
        $response = Util::initialClass();
        $response['data']->diySkinId = intval($userDiyId);
        return Util::returnValue($response);
    }
}
