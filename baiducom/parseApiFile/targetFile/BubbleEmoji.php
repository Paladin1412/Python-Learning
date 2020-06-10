<?php
use utils\Bos;

require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';
/**
 * 气泡表情
 * @author lipengcheng02
 * @path("/bubble_emoji/")
 */
class BubbleEmoji {

    /**
     * 根据用户input生成气泡表情
     * @route({"GET", "/generate"})
     * @param({"annotation_info", "$._GET.annotation_info"}) 标注信息
     * @param({"avatar_id", "$._GET.avatar_id"}) 头像编号
     * @param({"chatbox_id", "$._GET.chatbox_id"}) 聊天框编号
     * @param({"client", "$._GET.client"}) 聊天所在app
     *
     */
    public function generateBubbleEmoji($annotation_info = "", $avatar_id = 1, $chatbox_id = 1, $client = 0) {

        //提取标注信息
        $annotation_info_json = B64Decoder::decode($annotation_info, 0);
        if(!$annotation_info){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }
        $annotation_info = json_decode($annotation_info_json, true);
        //to-do检验json
        if(is_null($annotation_info)){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }
        list($image_width, $image_height, $chatbox_width, $chatbox_height, $input_arr, $chatbox_rows, $font_id) = $annotation_info;

        $client = intval($client);
        $background_color = "none";
        //默认，ios默认无底色
        if(0 === $client){
            $background_color = "none";
        }
        //微信和QQ的第三方应用
        if(1 === $client){
            $background_color = "white";
        }
        //安卓微信
        if(2 === $client){
            $background_color = "#ededed";
        }
        //安卓QQ
        if(3 === $client){
            $background_color = "#f1f2f7";
        }
        $output = new Imagick();
        $output->newimage( $image_width, $image_height, $background_color);
        $this->generateEmoji($output, $avatar_id, $chatbox_id, $chatbox_width, $chatbox_height, $input_arr, $chatbox_rows, $font_id);

        $output->setImageFormat("png");
        //$output->writeImage(ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/out.png');
        Header("Content-type: image/png");
        echo $output->getImageBlob();
        exit();
    }

    private function getAvatarObj($avatar_id = 1){
        $local_avatar = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/avatar/' . $avatar_id . '.png';
        $remote_avatar = 'https://imeres.baidu.com' . '/bubble_emoji/avatar/' . $avatar_id . '.png';
        if(!file_exists($local_avatar)){
            //$avatar_image = file_get_contents($remote_avatar);
            $Orp_FetchUrl = new \Orp_FetchUrl();
            $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
            $avatar_image = $http_proxy->get($remote_avatar);
            if($avatar_image === false){
                header("HTTP/1.1 404 Not Found");
                header("status: 404 Not Found");
                exit();
            }
            file_put_contents($local_avatar, $avatar_image);
        }
        $avatar_obj = new Imagick($local_avatar);
        return $avatar_obj;
    }

    private function getChatboxObj($chatbox_id = 1, $chatbox_width = 0, $chatbox_height = 0,  $chatbox_rows = 1){
        //$chatbox = new Imagick();
        //$chatbox->readImage(ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/chatbox/' . 'one_row' . '.png');

        if($chatbox_rows > 1){
            $chatbox_name = $chatbox_id . '_' . $chatbox_rows;
            $local_chatbox = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/chatbox/' . $chatbox_id . '/' . $chatbox_name . '.png';
            $remote_chatbox = 'https://imeres.baidu.com' . '/bubble_emoji/chatbox/'  . $chatbox_id . '/' . $chatbox_name . '.png';
            if(!file_exists($local_chatbox)){
                //$chatbox_image = file_get_contents($remote_chatbox);
                $Orp_FetchUrl = new \Orp_FetchUrl();
                $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
                $chatbox_image = $http_proxy->get($remote_chatbox);
                if($chatbox_image === false){
                    header("HTTP/1.1 404 Not Found");
                    header("status: 404 Not Found");
                    exit();
                }
                file_put_contents($local_chatbox, $chatbox_image);
            }
            $chatbox_obj = new Imagick($local_chatbox);
            return $chatbox_obj;
        }elseif($chatbox_rows == 1){
            $chatbox_obj = new Imagick();
            $chatbox_obj->newimage($chatbox_width, $chatbox_height, "none");

            //左边元素
            $local_component_l = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/chatbox/' . $chatbox_id . '/components/'. 'left.png';
            $remote_component_l = 'https://imeres.baidu.com' . '/bubble_emoji/chatbox/'. $chatbox_id . '/components/'. 'left.png';
            if(!file_exists($local_component_l)){
                //$component_l_image = file_get_contents($remote_component_l);
                $Orp_FetchUrl = new \Orp_FetchUrl();
                $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
                $component_l_image = $http_proxy->get($remote_component_l);
                if($component_l_image === false){
                    header("HTTP/1.1 404 Not Found");
                    header("status: 404 Not Found");
                    exit();
                }
                file_put_contents($local_component_l, $remote_component_l);
            }
            $component_l = new Imagick($local_component_l);
            $component_l_width = $component_l->getImageWidth();

            //右边元素
            $local_component_r = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/chatbox/' . $chatbox_id . '/components/'. 'right.png';
            $remote_component_r = 'https://imeres.baidu.com' . '/bubble_emoji/chatbox/'. $chatbox_id . '/components/'. 'right.png';
            if(!file_exists($local_component_r)){
                //$component_r_image = file_get_contents($local_component_r);
                $Orp_FetchUrl = new \Orp_FetchUrl();
                $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
                $component_r_image = $http_proxy->get($remote_component_r);
                if($component_r_image === false){
                    header("HTTP/1.1 404 Not Found");
                    header("status: 404 Not Found");
                    exit();
                }
                file_put_contents($local_component_r, $remote_component_r);
            }
            $component_r = new Imagick($local_component_r);
            $component_r_width = $component_r->getImageWidth();

            //中间元素
            $local_component_m = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/chatbox/' . $chatbox_id . '/components/'. 'mid.png';
            $remote_component_m = 'https://imeres.baidu.com' . '/bubble_emoji/chatbox/'. $chatbox_id . '/components/'. 'mid.png';
            if(!file_exists($local_component_m)){
                //$component_m_image = file_get_contents($local_component_m);
                $Orp_FetchUrl = new \Orp_FetchUrl();
                $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
                $component_m_image = $http_proxy->get($remote_component_m);
                if($component_m_image === false){
                    header("HTTP/1.1 404 Not Found");
                    header("status: 404 Not Found");
                    exit();
                }
                file_put_contents($local_component_m, $remote_component_m);
            }
            $component_m = new Imagick($local_component_m);
            $component_m_width = $chatbox_width - $component_l_width - $component_r_width;
            $component_m->scaleImage($component_m_width, $chatbox_height);

            //合成
            $chatbox_obj->compositeImage($component_l, Imagick::COMPOSITE_DEFAULT, 0, 0);
            $chatbox_obj->compositeImage($component_m, Imagick::COMPOSITE_DEFAULT, $component_l_width, 0);
            $chatbox_obj->compositeImage($component_r, Imagick::COMPOSITE_DEFAULT, $component_l_width + $component_m_width, 0);

            return $chatbox_obj;
        }else{
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }
    }

    private function getFontDrawObj($font_id = 0){
        $local_font = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/font/' . $font_id . '.ttf';
        $remote_font = 'https://imeres.baidu.com' . '/bubble_emoji/font/'  . $font_id . 'ttf';

        if(!file_exists($local_font)){
            //$font_ttf = file_get_contents($remote_font);
            $Orp_FetchUrl = new \Orp_FetchUrl();
            $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
            $font_ttf = $http_proxy->get($remote_font);
            if($font_ttf === false){
                header("HTTP/1.1 404 Not Found");
                header("status: 404 Not Found");
                exit();
            }
            file_put_contents($local_font, $font_ttf);
        }
        $font_draw = new ImagickDraw();
        $font_draw->setFont($local_font);
        $font_draw->setFontSize(32);
        //$font_draw->setStrokeColor("rgb(214, 116, 214)");
        //$font_draw->setStrokeWidth(2);
        $font_draw->setGravity(Imagick::GRAVITY_NORTHWEST);
        //$font_draw->setTextAlignment(\Imagick::ALIGN_CENTER);
        return $font_draw;
    }

    private function generateEmoji(&$output, $avatar_id = 1, $chatbox_id = 1, $chatbox_width = 0, $chatbox_height = 0, $input_arr = array(), $chatbox_rows = 1, $font_id = 0){
        $avatar_obj = $this->getAvatarObj($avatar_id);
        $chatbox_obj = $this->getChatboxObj($chatbox_id, $chatbox_width, $chatbox_height, $chatbox_rows);

        $output->compositeImage($chatbox_obj, Imagick::COMPOSITE_DEFAULT, 13, 61);
        $output->compositeImage($avatar_obj, Imagick::COMPOSITE_DEFAULT, 26, 0);

        $font_draw_obj = $this->getFontDrawObj($font_id);
        for ($row_id = 0 ; $row_id < count($input_arr); $row_id++){
            switch ($row_id){
                case 0:
                    $output->annotateImage($font_draw_obj, 42, 82, 0,  $input_arr[$row_id]);
                    break;
                case 1:
                    $output->annotateImage($font_draw_obj, 42, 122, 0,  $input_arr[$row_id]);
                    break;
                case 2:
                    $output->annotateImage($font_draw_obj, 42, 162, 0,  $input_arr[$row_id]);
                    break;
                case 3:
                    $output->annotateImage($font_draw_obj, 42, 202, 0,  $input_arr[$row_id]);
                    break;
                default:
                    break;
            }
        }
    }
}
