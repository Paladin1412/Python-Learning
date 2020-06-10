<?php
/***************************************************************************
 *
* Copyright (c) 2016 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use utils\MolaDBClient;
use utils\Util;

/**
 *
 * apprecommend
 * 说明：app推荐接口
 *
 * @author lipengcheng02
 * @path("/AppRecommend/")
 */
class AppRecommend
{    
    /**  @property */
    private $storage;
    
    /** @property v5 */
    private $domain_v5;
    
    //推荐类型
    private $reco_app = 'reco_app';
    
    //cuid
    private $cuid = '';
    
    //cuid对应的docid
    private $docid_of_cuid = '';    
    
    //本地已安装应用
    private $installed_apps = array();
    
    //是否为新用户
    private $is_new_user = true;
    
    //mola实例
    protected static $mola = null;    
    
    //默认每页显示条数
    const GENERAL_PAGE_SIZE = 10;
    
    //最大APP个数
    const MAX_APP_NUM = 60;
    
    //随机选取APP的阈值
    const RANDOM_APP_THRESHOLD = 20;
    
    //app更新时间
    const UPDATE_TIME = 3600;    
    //const UPDATE_TIME = 1;

    /**
     * 构造函数
     * @param
     * @return  成功：  mola客户端
     *          失败：  返回false
     **/
    public function __construct()
    {
        if (!self::$mola || !is_object(self::$mola)) {
            self::$mola = IoCload("utils\\MolaDBClient");
        }
    }    
    
    /**
     * @route({"GET","/list"})
     * @param({"type", "$._GET.type"})
     * @param({"sf", "$._GET.sf"}) 
     * @param({"num", "$._GET.num"})
     * @param({"cuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * 获取个性化app推荐列表
     * @return({"body"})
     {
         apps: [
             {
                "sname": "微店",
                "icon": "http://timg01.baidu-1img.cn/timg?pa&er&quality=100&size=b128_128&sec=1474947147&di=9af9fae24efe13021492dcace3d5fad4&src=http%3A%2F%2Fcdn00.baidu-img.cn%2Ftimg%3Fvsapp%26size%3Db800_800%26quality%3D100%26imgtype%3D3%26er%26sec%3D0%26di%3Dbbbfeb872311fa0511b958af08c628b1%26ref%3Dhttp%253A%252F%252Fd.hiphotos.bdimg.com%26src%3Dhttp%253A%252F%252Fd.hiphotos.bdimg.com%252Fwisegame%252Fpic%252Fitem%252F354e9258d109b3de7ff288f8c4bf6c81810a4cd5.jpg",
                "manual_brief": "手机开店，支持多种付款方式",
                "download_url": "http://10.58.19.57:8890/v5/trace?url=http%3A%2F%2Fm.baidu.com%2Fapi%3Faction%3Dredirect%26token%3Dshurufa%26from%3D1010184n%26type%3Dapp%26dltype%3Dnew%26refid%3D1947176564%26tj%3Dsoft_9935912_653428_%25E5%25BE%25AE%25E5%25BA%2597%26refp%3Daction_cate%40id_8018%26blink%3D4be8687474703a2f2f612e67646f776e2e62616964752e636f6d2f646174612f7769736567616d652f646530353164396266653235363563322f7765696469616e5f373530302e61706b3f66726f6d3d6131313031e957%26crversion%3D1&sign=4c5b
             },
             {
                "sname": "多看阅读",
                "icon": "http://timg01.baidu-1img.cn/timg?pa&er&quality=100&size=b128_128&sec=1474947219&di=3bba3812ed091ea694f4698143603b10&src=http%3A%2F%2Fcdn00.baidu-img.cn%2Ftimg%3Fvsapp%26size%3Db800_800%26quality%3D100%26imgtype%3D3%26er%26sec%3D0%26di%3De1cac56d17a894ae7d2f6b49a02274c5%26ref%3Dhttp%253A%252F%252Fd.hiphotos.bdimg.com%26src%3Dhttp%253A%252F%252Fd.hiphotos.bdimg.com%252Fwisegame%252Fpic%252Fitem%252F6a23dd54564e9258ca38936c9b82d158ccbf4e27.jpg",
                "manual_brief": "精致排版，更有丰富的辅助工具",
                "download_url": "http://10.58.19.57:8890/v5/trace?url=http%3A%2F%2Fm.baidu.com%2Fapi%3Faction%3Dredirect%26token%3Dshurufa%26from%3D1010184n%26type%3Dapp%26dltype%3Dnew%26refid%3D2019372469%26tj%3Dsoft_9957779_1949931767_%25E5%25A4%259A%25E7%259C%258B%25E9%2598%2585%25E8%25AF%25BB%26refp%3Daction_board%40id_2%26blink%3D93e8687474703a2f2f612e67646f776e2e62616964752e636f6d2f646174612f7769736567616d652f323664623036336362656638626434352f64756f6b616e79756564755f3435343136303930392e61706b3f66726f6d3d6131313031e957%26crversion%3D1&sign=4c0f7379b07053632a78f0f15ab3cefe&package=com.duokan.reader"
             },             
         ],
         total: "60"
     }
     */
    public function getAppList($type = 'app', $sf = 0, $num = self::GENERAL_PAGE_SIZE, $cuid = '') {

        if($type == 'game'){
            $this->reco_type = 'reco_game_app';
        }elseif(($type == 'app')){
            $this->reco_type = 'reco_app';
        }else{
            $this->reco_type = 'reco_app';
        }
        if(preg_match('/^[a-zA-Z0-9|]+$/', $cuid, $matches)){
            $this->cuid = $cuid;
        }else{
            $this->cuid = '';
        }
        $this->docid_of_cuid = Util::get_docid($cuid);        
        $result = self::$mola->GetItem(array (
            "AttributesToGet" => array (
                "user_mup",
                "user_app",
                "user_other",
            ),
            "Key" => array (
                "key" => array ("N" => $this->docid_of_cuid),
            ),
            "TableName" => "mobile_ime_userprofile",
        ));
        //var_dump($result);
        $reco_app_list = array();
        $ret_data = array();       
        if($result !== false && !empty($result)){
            $this->is_new_user = false;
            $user_mup = json_decode($result['Item']['user_mup']['S'], true);
            $user_app = json_decode($result['Item']['user_app']['S'], true);
            if(isset($user_app['installed_apps']) && !empty($user_app['installed_apps'])){
                $this->installed_apps = $user_app['installed_apps'];
            }
            $user_other = json_decode($result['Item']['user_other']['S'], true);            
            if(isset($user_other["{$this->reco_type}_list"]) && isset($user_other["{$this->reco_type}_list_updatetime"])){
                $current_time = time();
                $last_updatetime = $user_other["{$this->reco_type}_list_updatetime"];
                if (intval($current_time - $last_updatetime) < self::UPDATE_TIME){
                    $reco_app_package_list = $user_other["{$this->reco_type}_list"];
                    return $this->genRetData($reco_app_package_list, $sf, $num, 'mup');                    
                }else{
                    if(isset($user_mup[$this->reco_type]) && !empty($user_mup[$this->reco_type])){                        
                        $reco_app_package_list = $this->genRecoAppList($user_mup, $user_other);
                        return $this->genRetData($reco_app_package_list, $sf, $num, 'mup');
                    }else{
                        $reco_app_package_list = $this->genGlobalAppList();
                        return $this->genRetData($reco_app_package_list, $sf, $num, 'global');
                    }                
                }
            }else{
                if(isset($user_mup[$this->reco_type]) && !empty($user_mup[$this->reco_type])){                    
                    $reco_app_package_list = $this->genRecoAppList( $user_mup, $user_other); 
                    return $this->genRetData($reco_app_package_list, $sf, $num, 'mup');
                   
                }else{
                    $reco_app_package_list = $this->genGlobalAppList(); 
                    return $this->genRetData($reco_app_package_list, $sf, $num, 'global');
                }            
            } 
        }else{
            $this->is_new_user = true;
            $reco_app_package_list = $this->genGlobalAppList();
            return $this->genRetData($reco_app_package_list, $sf, $num, 'global');
        }    
    } 
    
    /**
     * 根据分类id和打分生成推荐app的列表，同时更新mola表
     * @param $user_mup
     * @param $user_reco_info
     * @return array
     */
    public function genRecoAppList($user_mup = array(), $user_reco_info = array()){        
        $reco_app = $user_mup[$this->reco_type];
        //arsort($reco_app);
        $min = 0;
        $max = 1;
        $new_min = 1;
        $new_max = 100;        
        $reco_app_keys = array();
        foreach ($reco_app as $k => $v) {
            $reco_app_keys[] = "app_cate_{$k}";
        }
        $candidate_reco_app_ret = $this->storage->multiget($reco_app_keys);        
        $candidate_reco_app_ret = $candidate_reco_app_ret['ret'];
        $candidate_reco_app = array();
        foreach ($reco_app as $k => $v) {
            if($v == null){
                continue;
            }
            $reco_app[$k] = intval(((($new_max - $new_min) * ($v - $min)) / ($max - $min)) + $new_min);
            $candidate_reco_app[$k] = json_decode($candidate_reco_app_ret["app_cate_{$k}"], true);
            $candidate_reco_app[$k] = array_diff($candidate_reco_app[$k], $this->installed_apps);
        }
        //var_dump($candidate_reco_app);        
        $reco_app_package_list = array();
        for($index = 0; $index < self::MAX_APP_NUM; $index++){
            $class_id = $this->getRandomWeightedElement($reco_app);
            if(empty($candidate_reco_app[$class_id])){
                continue;
            }
            //random pick one from list
            $max_index = min(self::RANDOM_APP_THRESHOLD, count($candidate_reco_app[$class_id]));
            $random_index = rand(1, $max_index);
            $selected_app = array_splice($candidate_reco_app[$class_id], $random_index-1, 1);
            $selected_app_package = array_values($selected_app)[0];
            //$selected_app_package = array_shift($candidate_reco_app[$class_id]);
            $reco_app_package_list[] = "app_package_{$selected_app_package}";
        }
        $reco_app_package_list = array_unique($reco_app_package_list);        
        /*
         * 写入mola
         */
        $current_time = time();
        $user_reco_info["{$this->reco_type}_list"] = $reco_app_package_list;
        $user_reco_info["{$this->reco_type}_list_updatetime"] = $current_time;
        $result = self::$mola->UpdateItem(array (
            "AttributeUpdates" => array (
                "user_other" => array (
                    "Action" => "PUT",
                    "Value" => array ("S" => json_encode($user_reco_info)),
                ),
            ),
            "Key" => array (
                "key" => array ("N" => $this->docid_of_cuid),
            ),
            "TableName" => "mobile_ime_userprofile",
        ));        
        return $reco_app_package_list;        
    }
    
    /**
     * 获取全局推荐列表
     * @return array
     */
    public function genGlobalAppList(){
        $reco_app_key = 'app_cate_app_newest';        
        if($this->reco_type == 'reco_app'){
            $reco_app_key = 'app_cate_app_newest';
        }else{
            $reco_app_key = 'app_cate_game_newest';
        }
        $get_status = null;        
        $reco_app_package_list_ret = $this->storage->get($reco_app_key, $get_status);
        if($get_status){
            $reco_app_package_list_ret = json_decode($reco_app_package_list_ret, true);
            //和本地安装app去重
            $reco_app_package_list_ret = array_diff($reco_app_package_list_ret, $this->installed_apps);
            $reco_app_package_list = array();
            foreach($reco_app_package_list_ret as $package){
                $reco_app_package_list[] = "app_package_{$package}";
            }
            //全局热门打乱，防止重复
            shuffle($reco_app_package_list);
            $reco_app_package_list = array_slice($reco_app_package_list, 0, self::MAX_APP_NUM);            
            /*
             * 写入mola
             */
            $current_time = time();
            $user_reco_info["{$this->reco_type}_list"] = $reco_app_package_list;
            $user_reco_info["{$this->reco_type}_list_updatetime"] = $current_time;
            //新用户写入数据，老用户更新数据
            if($this->is_new_user){
                $result = self::$mola->PutItem(array (
                    "Item" => array (
                        "key" => array ("N" => $this->docid_of_cuid),
                        "user_mup" => array ("S" => ""),
                        "user_app" => array ("S" => ""),
                        "user_log" => array ("S" => ""),
                        "user_info" => array ("S" => ""),
                        "user_skin" => array ("S" => ""),
                        "user_score" => array ("S" => ""),
                        "user_other" => array ("S" => json_encode($user_reco_info)),
                    ),
                    "TableName" => "mobile_ime_userprofile",
                ));
            }else{
                $result = self::$mola->UpdateItem(array (
                    "AttributeUpdates" => array (
                        "user_other" => array (
                            "Action" => "PUT",
                            "Value" => array ("S" => json_encode($user_reco_info)),
                        ),
                    ),
                    "Key" => array (
                        "key" => array ("N" => $this->docid_of_cuid),
                    ),
                    "TableName" => "mobile_ime_userprofile",
                ));                
            }            
            return $reco_app_package_list;
        }else{
            return array();
        }
    }    
    
    /**
     * 根据推荐app的包名列表生成最终的推荐列表
     *
     * @param $reco_app_package_list 包名列表
     * @param $sf
     * @param $num
     * @param $reco_from
     * @return array
     *
     */
    public function genRetData($reco_app_package_list = array(), $sf  = 0, $num = self::GENERAL_PAGE_SIZE, $reco_from = 'mup'){
        $ret_data = array();
        $reco_app_list = $this->getRecoAppInfo($reco_app_package_list, $sf, $num, $reco_from);
        $ret_cnt = count($reco_app_package_list);
        $ret_data['total'] = $ret_cnt;
        $ret_data['apps'] = $reco_app_list;
        return $ret_data;
    }
    
    /**
     * 根据app的package去索引app的详细信息返回给客户端
     *
     * @param $reco_app_package_list app的package列表
     * @param $sf 
     * @param $num
     * @param $reco_from
     * @return array
     * 
     */
    public function getRecoAppInfo($reco_app_package_list = array(), $sf  = 0, $num = self::GENERAL_PAGE_SIZE, $reco_from = 'mup'){        
        $reco_app_package_list = array_slice($reco_app_package_list, $sf, $num);
        $reco_app_ret = $this->storage->multiget($reco_app_package_list);
        $reco_app_list = array();
        //保持返回的顺序
        $app_pos = $sf;
        foreach($reco_app_package_list as $package){
            if($reco_app_ret['ret'][$package] == null){                
                continue;
            }
            $app_info = json_decode($reco_app_ret['ret'][$package], true);
            $one_app = array();
            $one_app['sname'] = $app_info['sname'];
            $one_app['icon'] = $app_info['icon'];
            $one_app['manual_brief'] = $app_info['manual_brief'];
            $one_app['download_url'] = $this->domain_v5 . 'v5/trace?url=' . urlencode($app_info['download_url'])
            .'&sign=' . md5($app_info['download_url'] . 'iudfu(lkc#xv345y82$dsfjksa')
            .'&package=' . $app_info['package'] . '&pos=' . $app_pos . '&src=' . 'shouzhu' . '&reco_type=' . $this->reco_type
            .'&cuid=' . $this->cuid . '&reco_from=' . $reco_from;
            $reco_app_list[] = $one_app;
            $app_pos ++;
        }
        return $reco_app_list;        
    }
    
    /**
     * getRandomWeightedElement()
     * Utility function for getting random values with weighting.
     * Pass in an associative array, such as array('A'=>5, 'B'=>45, 'C'=>50)
     * An array like this means that "A" has a 5% chance of being selected, "B" 45%, and "C" 50%.
     * The return value is the array key, A, B, or C in this case.  Note that the values assigned
     * do not have to be percentages.  The values are simply relative to each other.  If one value
     * weight was 2, and the other weight of 1, the value with the weight of 2 has about a 66%
     * chance of being selected.  Also note that weights should be integers.
     *
     * @param array $weightedValues
     * @return string
     */
    function getRandomWeightedElement(array $weightedValues) {
        $rand = mt_rand(1, (int) array_sum($weightedValues));
        foreach ($weightedValues as $key => $value) {
            $rand -= $value;
            if ($rand <= 0) {
                return $key;
            }
        }
    }
}
