<?php
use utils\Bos;

require_once dirname(__DIR__).'/esb/v5/components/utils/FetchUrl.php';
/**
 * 文字表情
 * @author lipengcheng02
 * @path("/text_emoji/")
 */
class TextEmoji {

    /** 背景配置 */
    private $background_color_settings = array(
        //默认方案
        "1-1" => "#FFFFFF", "2-1" => "#000000", "4-9" => "#FF3B30",
        //备用默认方案
        "3-1" => "#000000",
        //第一排配色
        "1-2" => "#FFFFFF", "1-3" => "#FFFFFF", "1-4" => "#FFFFFF", "1-5" => "#FFFFFF", "1-6" => "#FFFFFF", "1-7" => "#FFFFFF",
        //第二排配色
        "2-2" => "#FF5098", "2-3" => "#FF3B30", "2-4" => "#FF9500", "2-5" => "#FFCC00", "2-6" => "#5AC8FA", "2-7" => "#007AFF", "2-8" => "#5856D6",
        //镂空
        "4-1" => "#FFFFFF", "4-2" => "#FFFFFF", "4-3" => "#007AFF",
        //渐变
        "3-10" => "#FFFFFF", "3-11" => "#FFFFFF", "3-12" => "#FFFFFF", "3-13" => "#FFFFFF",
        //布局二，画圆
        "3-2" => "#FF5098", "3-3" => "#FF3B30", "3-4" => "#FF9500", "3-5" => "#FFCC00", "3-6" => "#4CD964", "3-7" => "#5AC8FA", "3-8" => "#007AFF", "3-9" => "#5856D6",
        //田字格布局（每个字颜色都不一样）
        "4-5" => "#FFFFFF", "4-6" => "#FFFFFF", "4-7" => "#000000", "4-8" => "#000000",
        //强化第一种（字体颜色变化）
        "5-1" => "#FF3B30", "5-2" => "#007AFF", "5-3" => "#5856D6", "5-4" => "#000000", "5-5" => "#000000",
        //强化第二种（字加背景）
        "5-8" => "#FFFFFF", "5-9" => "#FFFFFF", "5-10" => "#FFFFFF",
        //强化第三种（字体颜色变化）
        "6-1" => "#FFFFFF", "6-2" => "#FFFFFF", "6-3" => "#FFFFFF", "6-4" => "#FFFFFF",
        //强化第三种（渐变）
        "6-5" => "#FFFFFF",
    );

    /** 字体颜色配置 */
    private $font_color_settings = array(
        //默认方案
        "1-1" => "#000000", "2-1" => "#FFFFFF", "4-9" => "#FFF200",
        //备用默认方案
        "3-1" => "#FFF200",
        //第一排配色
        "1-2" => "#FF5098", "1-3" => "#FF3B30", "1-4" => "#FF9500", "1-5" => "#FFCC00", "1-6" => "#007AFF", "1-7" => "#5856D6",
        //第二排配色
        "2-2" => "#FFFFFF", "2-3" => "#FFFFFF", "2-4" => "#FFFFFF", "2-5" => "#FFFFFF", "2-6" => "#FFFFFF", "2-7" => "#FFFFFF", "2-8" => "#FFFFFF",
        //镂空
        "4-1" => "#FFFFFF", "4-2" => "#FFFFFF", "4-3" => "#FFFFFF",
        //渐变
        "3-10" => "#000000", "3-11" => "#000000", "3-12" => "#000000", "3-13" => "#000000",
        //布局二，画圆
        "3-2" => "#FF5098", "3-3" => "#FF3B30", "3-4" => "#FF9500", "3-5" => "#FFCC00", "3-6" => "#4CD964", "3-7" => "#5AC8FA", "3-8" => "#007AFF", "3-9" => "#5856D6",
        //强化第一种（字体颜色变化）
        "5-1" => "#FFFFFF", "5-2" => "#FFFFFF", "5-3" => "#FFFFFF", "5-4" => "#FFFFFF", "5-5" => "#FFFFFF",
        //强化第二种（字加背景）
        "5-8" => "#FFFFFF", "5-9" => "#FFFFFF", "5-10" => "#FFFFFF",
        //强化第三种（字体颜色变化）
        "6-1" => "#000000", "6-2" => "#000000", "6-3" => "#000000", "6-4" => "#000000",
    );

    //加强字体颜色
    private $enhance_font_color_settings = array(
        //强化第一种（字体颜色变化）
        "5-1" => "#FFF200", "5-2" => "#FFF200", "5-3" => "#FFF200", "5-4" => "#FFFFFF", "5-5" => "#FFFFFF",
        //强化第二种（字加背景）
        "5-8" => "#FFFFFF", "5-9" => "#FFFFFF", "5-10" => "#000000",
        //强化第三种（字体颜色变化）
        "6-1" => "#FF5098", "6-2" => "#FF3B30", "6-3" => "#007AFF", "6-4" => "#5856D6",
        //渐变
        "6-5" => "#000000",
    );

    //加强字体背景
    private $enhance_font_background_color_settings = array(
        //强化第一种（字体颜色变化）
        "5-4" => "#FF5098", "5-5" => "#FF3B30",
        //强化第二种（字加背景）
        "5-8" => "#FF5098", "5-9" => "#FF3B30", "5-10" => "#FFCC00",
    );

    //加强字体整体背景
    private $enhance_all_font_background_color_settings = array(
        //强化第二种（字加背景）
        "5-8" => "#000000", "5-9" => "#000000", "5-10" => "#000000",
    );

    //加强字体整体背景
    private $enhance_gradient_settings = array(
        //分段渐变
        "6-5" => array("gradient:#FF1414-#FDE853", "gradient:#B4EC51-#4A90E2")
    );

    //渐变配色
    private $gradient_settings = array(
        "3-10" => "gradient:#3023AE-#B4EC51", "3-11" => "gradient:#3023AE-#C86DD7", "3-12" => "gradient:#FAD961-#F76B1C", "3-13" => "gradient:#F5515F-#9F041B",
    );

    //镂空描边配色
    private $stroke_color_settings = array(
        "4-1" => "#FF5098", "4-2" => "#FF3B30", "4-3" => "#5856D6",
    );

    /**
     * 根据用户input生成文字表情
     * @route({"GET", "/generate"})
     * @param({"annotation_info", "$._GET.annotation_info"}) 标注信息
     * @param({"color_plan_id", "$._GET.color_plan_id"}) 配色编号
     * @param({"font_id", "$._GET.font_id"}) 字体文件id
     * @param({"font_size", "$._GET.font_size"}) 字体大小
     * @param({"text_direction", "$._GET.text_direction"}) 字体方向
     * @param({"input", "$._GET.input"}) 用户input
     *
     */
    public function generateTextEmoji($annotation_info = "", $color_plan_id = "1-1", $font_id = 0, $font_size = 250, $text_direction = 0, $input = "") {

        $keyword = B64Decoder::decode($input, 0);
        if(!$keyword){
            $keyword = " ";
        }

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

        $output = new Imagick();
        $output->newimage( 300, 300, "none");

        $annotation_type = isset($annotation_info['annotation_type']) ? $annotation_info['annotation_type'] : "1_0";
        if(in_array($annotation_type, array("1_0", "1_1", "2_0", "2_1", "2_2", "3_0", "3_1", "4_1"))){
            if($annotation_type == "3_1" && $color_plan_id == "4-4"){
                $this->doVerticalTextWithCircle($output, $keyword, $font_id, $font_size);
            }else{
                $this->doNormalTextLessThanFive($output, $keyword, $color_plan_id, $font_id, $font_size, $text_direction);
            }
        }elseif($annotation_type == "4_0"){
            $this->doFieldLikeText($output, $keyword, $color_plan_id , $font_id, $font_size);
        }elseif($annotation_type == "5_0"){
            $this->doNormalTextMoreThanFourWithoutEnhance($output, $annotation_info['annotation_refs'], $color_plan_id);
        }elseif($annotation_type == "5_1"){
            //加强配色
            $this->doNormalTextMoreThanFourWithEnhance($output, $annotation_info['annotation_refs'], $color_plan_id);
        }else{
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }

        $output->setImageFormat("png");
        //$output->writeImage(ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/out.png');
        Header("Content-type: image/png");
        echo $output->getImageBlob();
        exit();
    }

    private function getFontPath($font_id = 0){
        $local_font = ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/font/' . $font_id . '.ttf';
        $remote_font = 'https://imeres.baidu.com' . '/bubble_emoji/font/'  . $font_id . 'ttf';

        if(!file_exists($local_font)){
            //$font_ttf = file_get_contents($remote_font);
            $Orp_FetchUrl = new \Orp_FetchUrl();
            $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
            $font_ttf = $http_proxy->get($remote_font);
            if(false === $font_ttf){
                return false;
            }
            file_put_contents($local_font, $font_ttf);
        }
        return $local_font;
    }

    private function getBackgroundPath($background_id = 1){
        //$backgroud = new Imagick();
        //$backgroud->readImage(ROOT_PATH . '/webroot/v5/static/img/bubble_emoji/backgroud/' . '01' . '.png');

        $local_backgroud = ROOT_PATH . '/webroot/v5/static/img/text_emoji/background/' . $background_id . '.png';
        $remote_backgroud = 'https://imeres.baidu.com' . '/text_emoji/background/' . $background_id . '.png';
        if(!file_exists($local_backgroud)){
            //$backgroud_image = file_get_contents($remote_backgroud);
            $Orp_FetchUrl = new \Orp_FetchUrl();
            $http_proxy = $Orp_FetchUrl->getInstance(array('timeout' =>1500));
            $backgroud_image = $http_proxy->get($remote_backgroud);
            if(false === $backgroud_image){
                return false;
            }
            file_put_contents($local_backgroud, $backgroud_image);
        }
        return $local_backgroud;
    }

    /*
     * 字符串转数组
     */
    private function mbStringToArray ($string) {
        $strlen = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string,0,1,"UTF-8");
            $string = mb_substr($string,1,$strlen,"UTF-8");
            $strlen = mb_strlen($string);
        }
        return $array;
    }

    /*
     * 获取圆圈对象
     */
    private function getCircleObj($word = "", $font_id = 0, $font_size = 250, $font_color = "#FF3B30"){
        $word = mb_substr($word, 0, 1, 'utf-8');
        //制作底图
        $circle = new Imagick();
        $circle->newimage(108, 108, "none");
        $draw = new ImagickDraw();
        $circle_color = "#000000";
        $draw->setFillColor($circle_color);
        $draw->circle(54, 54, 2, 54);
        $draw->setFontSize($font_size);
        $font_path = $this->getFontPath($font_id);
        if(!$font_path){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }
        $draw->setFont($font_path);
        $draw->setFillColor($font_color);
        $draw->setGravity(Imagick::GRAVITY_CENTER);
        $draw->annotation(0, 0, $word);
        $circle->drawImage($draw);
        return $circle;
    }

    /*
     * 三字垂直摆放（带圆）
     */
    private function doVerticalTextWithCircle(&$image, $keyword = "", $font_id = 0, $font_size = 250){
        $keyword = mb_substr($keyword, 0, 3, 'utf-8');
        $keyword_arr = $this->mbStringToArray($keyword);
        $keyword_complete_arr = array();
        for($index = 0; $index < 3; $index++){
            if(isset($keyword_arr[$index])){
                $keyword_complete_arr[$index] = $keyword_arr[$index];
            }else{
                $keyword_complete_arr[$index] = "";
            }
        }
        $image->newimage(300, 300, "#FFFFFF");
        //圆圈图
        $up_circle = $this->getCircleObj($keyword_complete_arr[0], $font_id, $font_size, "#FF3B30");
        $mid_circle = $this->getCircleObj($keyword_complete_arr[1], $font_id, $font_size, "#FFCC00");
        $down_circle = $this->getCircleObj($keyword_complete_arr[2], $font_id, $font_size, "#5AC8FA");
        //把三个圆贴到大图上
        $image->compositeImage($up_circle, Imagick::COMPOSITE_DEFAULT, 96, 15);
        $image->compositeImage($mid_circle, Imagick::COMPOSITE_DEFAULT, 96, 15+81);
        $image->compositeImage($down_circle, Imagick::COMPOSITE_DEFAULT, 96, 15+81+81);
    }

    /*
     * 获取田字格1/4图片
     */
    private function getQuarterObj($word = "", $color_plan_id = "1-1", $font_id = 0, $font_size = 250, $font_color = "#FF3B30"){
        $word = mb_substr($word, 0, 1, 'utf-8');
        //制作底图
        $quarter = new Imagick();
        $quarter->newimage(150, 150, "none");
        $draw = new ImagickDraw();
        $draw->setFontSize($font_size);
        $font_path = $this->getFontPath($font_id);
        if(!$font_path){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }
        $draw->setFont($font_path);
        $draw->setFillColor($font_color);
        //镂空
        if(in_array($color_plan_id, array('4-1', '4-2', '4-3'))){
            $stroke_color = "#FF5098";
            if(isset($this->stroke_color_settings[$color_plan_id])){
                $stroke_color = $this->stroke_color_settings[$color_plan_id];
            }
            $draw->setStrokeColor($stroke_color);
            $draw->setStrokeWidth(2);
        }

        //写字
        $draw->setGravity(Imagick::GRAVITY_CENTER);
        $draw->annotation(0, 0, $word);
        $quarter->drawImage($draw);
        return $quarter;
    }

    /*
     * 四字田字格摆放
     */
    private function doFieldLikeText(&$image, $keyword = "", $color_plan_id = "1-1", $font_id = 0, $font_size = 250){
        $keyword = mb_substr($keyword, 0, 4, 'utf-8');
        $keyword_arr = $this->mbStringToArray($keyword);
        $keyword_complete_arr = array();
        for($index = 0; $index < 4; $index++){
            if(isset($keyword_arr[$index])){
                $keyword_complete_arr[$index] = $keyword_arr[$index];
            }else{
                $keyword_complete_arr[$index] = "";
            }
        }
        //背景颜色
        $background_color = "#FFFFFF";
        if(isset($this->background_color_settings[$color_plan_id])){
            $background_color = $this->background_color_settings[$color_plan_id];
        }

        //字体颜色
        $font_color = "#000000";
        if(isset($this->font_color_settings[$color_plan_id])){
            $font_color = $this->font_color_settings[$color_plan_id];
        }

        //区分带不带背景图片
        if(in_array($color_plan_id, array('1-8', '1-9', '1-10', '1-11', '2-9', '2-10', '2-11', '2-12'))){
            $background_path = $this->getBackgroundPath($color_plan_id);
            if(!$background_path){
                header("HTTP/1.1 404 Not Found");
                header("status: 404 Not Found");
                exit();
            }
            $image->readImage($background_path);
            //有背景的时候，字体固定白色
            $font_color = "#FFFFFF";
        }else{
            $image->newimage(300, 300, $background_color);
        }

        //画田字格
        switch ($color_plan_id) {
            case '4-5':
                $top_left_field = $this->getQuarterObj($keyword_complete_arr[0], $color_plan_id, $font_id, $font_size, "#FF3B30");
                $top_right_field = $this->getQuarterObj($keyword_complete_arr[1], $color_plan_id,  $font_id, $font_size, "#FFCC00");
                $bottom_left_field = $this->getQuarterObj($keyword_complete_arr[2], $color_plan_id,  $font_id, $font_size, "#4CD964");
                $bottom_right_field = $this->getQuarterObj($keyword_complete_arr[3], $color_plan_id,  $font_id, $font_size, "#007AFF");
                break;
            case '4-6':
                $top_left_field = $this->getQuarterObj($keyword_complete_arr[0], $color_plan_id,  $font_id, $font_size, "#FF5098");
                $top_right_field = $this->getQuarterObj($keyword_complete_arr[1], $color_plan_id,  $font_id, $font_size, "#5856D6");
                $bottom_left_field = $this->getQuarterObj($keyword_complete_arr[2], $color_plan_id,  $font_id, $font_size, "#FF9500");
                $bottom_right_field = $this->getQuarterObj($keyword_complete_arr[3], $color_plan_id,  $font_id, $font_size, "#4CD964");
                break;
            case '4-7':
                $top_left_field = $this->getQuarterObj($keyword_complete_arr[0], $color_plan_id,  $font_id, $font_size, "#FF3B30");
                $top_right_field = $this->getQuarterObj($keyword_complete_arr[1], $color_plan_id,  $font_id, $font_size, "#FFCC00");
                $bottom_left_field = $this->getQuarterObj($keyword_complete_arr[2], $color_plan_id,  $font_id, $font_size, "#4CD964");
                $bottom_right_field = $this->getQuarterObj($keyword_complete_arr[3], $color_plan_id,  $font_id, $font_size, "#007AFF");
                break;
            case '4-8':
                $top_left_field = $this->getQuarterObj($keyword_complete_arr[0], $color_plan_id,  $font_id, $font_size, "#FF5098");
                $top_right_field = $this->getQuarterObj($keyword_complete_arr[1], $color_plan_id,  $font_id, $font_size, "#5856D6");
                $bottom_left_field = $this->getQuarterObj($keyword_complete_arr[2], $color_plan_id,  $font_id, $font_size, "#FF9500");
                $bottom_right_field = $this->getQuarterObj($keyword_complete_arr[3], $color_plan_id,  $font_id, $font_size, "#4CD964");
                break;
            default:
                $top_left_field = $this->getQuarterObj($keyword_complete_arr[0], $color_plan_id,  $font_id, $font_size, $font_color);
                $top_right_field = $this->getQuarterObj($keyword_complete_arr[1], $color_plan_id,  $font_id, $font_size, $font_color);
                $bottom_left_field = $this->getQuarterObj($keyword_complete_arr[2], $color_plan_id,  $font_id, $font_size, $font_color);
                $bottom_right_field = $this->getQuarterObj($keyword_complete_arr[3], $color_plan_id,  $font_id, $font_size, $font_color);
                break;
        }

        //合成田字格
        $image->compositeImage($top_left_field, Imagick::COMPOSITE_DEFAULT, 0, 0);
        $image->compositeImage($top_right_field, Imagick::COMPOSITE_DEFAULT, 150, 0);
        $image->compositeImage($bottom_left_field, Imagick::COMPOSITE_DEFAULT, 0, 150);
        $image->compositeImage($bottom_right_field, Imagick::COMPOSITE_DEFAULT, 150, 150);
    }

    /*
     * 四字以内的普通居中摆放（包含水平、垂直）
     */
    private function doNormalTextLessThanFive(&$image, $keyword = "", $color_plan_id = "1-1", $font_id = 0, $font_size = 250, $text_direction = 0){
        //$keyword_len = mb_strlen($keyword,'utf-8') > 4 ? 4 : mb_strlen($keyword,'utf-8');
        $keyword = mb_substr($keyword, 0, 4, 'utf-8');
        $vertical_keyword = "";
        if($text_direction == 1){
            $vertical_keyword = implode("\n", $this->mbStringToArray($keyword));
            $keyword = $vertical_keyword;
        }

        //背景颜色
        $background_color = "#FFFFFF";
        if(isset($this->background_color_settings[$color_plan_id])){
            $background_color = $this->background_color_settings[$color_plan_id];
        }

        //字体颜色
        $font_color = "#000000";
        if(isset($this->font_color_settings[$color_plan_id])){
            $font_color = $this->font_color_settings[$color_plan_id];
        }

        //字体路径
        $font_path = $this->getFontPath($font_id);
        if(!$font_path){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }

        //区分带不带背景图片
        if(in_array($color_plan_id, array('1-8', '1-9', '1-10', '1-11', '2-9', '2-10', '2-11', '2-12'))){
            $background_path = $this->getBackgroundPath($color_plan_id);
            if(!$background_path){
                header("HTTP/1.1 404 Not Found");
                header("status: 404 Not Found");
                exit();
            }
            $image->readImage($background_path);
            //有背景的时候，字体固定白色
            $font_color = "#FFFFFF";
        }else{
            $image->newimage(300, 300, $background_color);
        }

        //写字
        $draw = new ImagickDraw();
        if(in_array($color_plan_id, array('3-2', '3-3', '3-4', '3-5', '3-6', '3-7', '3-8', '3-9'))) {
            $circle_color = "#FFFFFF";
            $draw->setFillColor($circle_color);
            $draw->circle(150, 150, 275, 150);
        }
        $draw->setFontSize($font_size);
        $draw->setFont($font_path);
        $draw->setFillColor($font_color);

        //渐变
        if($text_direction == 0 && in_array($color_plan_id, array('3-10', '3-11', '3-12', '3-13'))){
            //计算本文的宽高
            $metrics = $image->queryFontMetrics($draw, $keyword, false);
            $input_width = $metrics['textWidth'];
            $input_height = $metrics['textHeight'];
            $offset_x = (300 - $input_width) / 2;
            $offset_y = (300 - $input_height) / 2;

            //创建PseudoImage
            $im = new Imagick();

            $pseudo_string = "gradient:#3023AE-#B4EC51";
            if(isset($this->gradient_settings[$color_plan_id])){
                $pseudo_string = $this->gradient_settings[$color_plan_id];
            }
            switch ($color_plan_id) {
                case '3-10':
                    $im->newPseudoImage($input_height, $input_width, $pseudo_string);
                    $im->rotateImage(new ImagickPixel(), 270);
                    break;
                case '3-11':
                    $im->newPseudoImage($input_height, $input_width, $pseudo_string);
                    $im->rotateImage(new ImagickPixel(), 270);
                    break;
                case '3-12':
                    $im->newPseudoImage($input_width, $input_height, $pseudo_string);
                    break;
                case '3-13':
                    $im->newPseudoImage($input_width, $input_height, $pseudo_string);
                    break;
                default:
                    $im->newPseudoImage($input_width, $input_height, $pseudo_string);
                    break;
            }
            //pushPattern的起点(x,y)对这个场景不起作用，只要保证宽、高即可。后面的宽、高表示【每组区域】截取的宽、高。该区域在整个图中可以repeat
            $draw->pushPattern('gradient', $offset_x, $offset_y, $input_width + $offset_x, $input_height + $offset_y);
            //composite的起点(x,y)表示渐变区域的起始位置，宽、高表示渐变区域真实的宽高，区域越宽，渐变越长，此宽高绘制好后，会被pushPatter给截断
            $draw->composite(Imagick::COMPOSITE_OVER, $offset_x, $offset_y, $input_width, $input_height, $im);
            $draw->popPattern();

            $draw->setFillPatternURL('#gradient');
        }

        //镂空
        if(in_array($color_plan_id, array('4-1', '4-2', '4-3'))){
            $stroke_color = "#FF5098";
            if(isset($this->stroke_color_settings[$color_plan_id])){
                $stroke_color = $this->stroke_color_settings[$color_plan_id];
            }
            $draw->setStrokeColor($stroke_color);
            $draw->setStrokeWidth(2);
        }

        $draw->setGravity(Imagick::GRAVITY_CENTER);
        $draw->annotation(0, 0, $keyword);

        $image->drawImage($draw);
    }

    /*
     * 四字以上的普通水平排列（无加强）
     */
    private function doNormalTextMoreThanFourWithoutEnhance(&$image, $annotation_refs = array(), $color_plan_id = "1-1"){
        if(empty($annotation_refs)){
            return;
        }

        //背景颜色
        $background_color = "#FFFFFF";
        if(isset($this->background_color_settings[$color_plan_id])){
            $background_color = $this->background_color_settings[$color_plan_id];
        }

        //字体颜色
        $font_color = "#000000";
        if(isset($this->font_color_settings[$color_plan_id])){
            $font_color = $this->font_color_settings[$color_plan_id];
        }

        //字体路径
        $font_id = isset($annotation_refs['font_id']) ? intval($annotation_refs['font_id']) : 0;
        $font_path = $this->getFontPath($font_id);
        if(!$font_path){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }

        //区分带不带背景图片
        if(in_array($color_plan_id, array('1-8', '1-9', '1-10', '1-11', '2-9', '2-10', '2-11', '2-12'))){
            $background_path = $this->getBackgroundPath($color_plan_id);
            if(!$background_path){
                header("HTTP/1.1 404 Not Found");
                header("status: 404 Not Found");
                exit();
            }
            $image->readImage($background_path);
            //有背景的时候，字体固定白色
            $font_color = "#FFFFFF";
        }else{
            $image->newimage(300, 300, $background_color);
        }

        //定制imagick draw
        $draw = new ImagickDraw();
        if(in_array($color_plan_id, array('3-2', '3-3', '3-4', '3-5', '3-6', '3-7', '3-8', '3-9'))) {
            $circle_color = "#FFFFFF";
            $draw->setFillColor($circle_color);
            $draw->circle(150, 150, 275, 150);
        }
        $font_size = isset($annotation_refs['font_size']) ? intval($annotation_refs['font_size']) : 36;

        $draw->setFontSize($font_size);
        $draw->setFont($font_path);
        $draw->setFillColor($font_color);

        //镂空
        if(in_array($color_plan_id, array('4-1', '4-2', '4-3'))){
            $stroke_color = "#FF5098";
            if(isset($this->stroke_color_settings[$color_plan_id])){
                $stroke_color = $this->stroke_color_settings[$color_plan_id];
            }
            $draw->setStrokeColor($stroke_color);
            $draw->setStrokeWidth(2);
        }

        //写字
        $draw->setGravity(Imagick::GRAVITY_NORTHWEST);
        $new_keyword = isset($annotation_refs['new_keyword']) ? $annotation_refs['new_keyword'] : "";

        //字体对应行数以及坐标的关系
        $font_coordinate_settings = array();
        //大号字体
        $font_coordinate_large_font_settings = array();
        //一行
        $font_coordinate_large_font_settings[0][0] = array(30, 123);
        //二行
        $font_coordinate_large_font_settings[1][0] = array(30, 95);
        $font_coordinate_large_font_settings[1][1] = array(30, 150);
        //三行
        $font_coordinate_large_font_settings[2][0] = array(30, 68);
        $font_coordinate_large_font_settings[2][1] = array(30, 123);
        $font_coordinate_large_font_settings[2][2] = array(30, 178);
        $font_coordinate_settings[0] = $font_coordinate_large_font_settings;
        //小号字体
        $font_coordinate_small_font_settings = array();
        //一行
        $font_coordinate_small_font_settings[0][0] = array(24, 130);
        //二行
        $font_coordinate_small_font_settings[1][0] = array(24, 109);
        $font_coordinate_small_font_settings[1][1] = array(24, 150);
        //三行
        $font_coordinate_small_font_settings[2][0] = array(24, 89);
        $font_coordinate_small_font_settings[2][1] = array(24, 130);
        $font_coordinate_small_font_settings[2][2] = array(24, 171);
        //四行
        $font_coordinate_small_font_settings[3][0] = array(24, 68);
        $font_coordinate_small_font_settings[3][1] = array(24, 109);
        $font_coordinate_small_font_settings[3][2] = array(24, 150);
        $font_coordinate_small_font_settings[3][3] = array(24, 191);
        $font_coordinate_settings[1] = $font_coordinate_small_font_settings;

        //行数设置
        $rows_cnt = isset($annotation_refs['rows']) ? intval($annotation_refs['rows']) : 1;
        $small_font = isset($annotation_refs['small_font']) ? intval($annotation_refs['small_font']) : 0;
        if($small_font == 0){
            if($rows_cnt > 3){
                $rows_cnt = 3;
            }
        }else{
            if($rows_cnt > 4){
                $rows_cnt = 4;
            }
        }

        //写字
        foreach($annotation_refs['input_arr'] as $row_index => $input_one_line_arr){
            //行数限制
            if($small_font == 0){
                if($row_index > 2){
                    break;
                }
            }else{
                if($row_index > 3){
                    break;
                }
            }
            //每行标注
            $row_index = intval($row_index);
            $offset_x = 0;
            foreach($input_one_line_arr as $slice_index => $input_slice){
                $slice_index = intval($slice_index);
                list($start_x, $start_y) = $font_coordinate_settings[$small_font][$rows_cnt-1][$row_index];
                if(isset($input_slice['type']) && $input_slice['type'] == 0){
                    $keyword_slice = mb_substr($new_keyword, intval($input_slice['start_index']), intval($input_slice['end_index']) - intval($input_slice['start_index']) + 1, "utf-8");
                    $draw->annotation($offset_x + $start_x, $start_y, $keyword_slice);
                    $metrics = $image->queryFontMetrics($draw, $keyword_slice, false);
                    $offset_x += $metrics['textWidth'];
                }
            }
        }
        $image->drawImage($draw);
    }

    /*
     * 四字以上的普通水平排列（加强）
     */
    private function doNormalTextMoreThanFourWithEnhance(&$image, $annotation_refs = array(), $color_plan_id = "1-1"){
        if(empty($annotation_refs)){
            return;
        }

        //背景颜色
        $background_color = "#FFFFFF";
        if(isset($this->background_color_settings[$color_plan_id])){
            $background_color = $this->background_color_settings[$color_plan_id];
        }

        //字体颜色
        $font_color = "#000000";
        if(isset($this->font_color_settings[$color_plan_id])){
            $font_color = $this->font_color_settings[$color_plan_id];
        }

        //加强字体颜色
        $enhance_font_color = "#FFFFFF";
        if(isset($this->enhance_font_color_settings[$color_plan_id])){
            $enhance_font_color = $this->enhance_font_color_settings[$color_plan_id];
        }

        //加强字体背景颜色
        $enhance_font_background_color = "#FFFFFF";
        if(isset($this->enhance_font_background_color_settings[$color_plan_id])){
            $enhance_font_background_color = $this->enhance_font_background_color_settings[$color_plan_id];
        }

        //加强方案字体背景颜色
        $enhance_all_font_background_color = "#FFFFFF";
        if(isset($this->enhance_all_font_background_color_settings[$color_plan_id])){
            $enhance_all_font_background_color = $this->enhance_all_font_background_color_settings[$color_plan_id];
        }

        //字体路径
        $font_id = isset($annotation_refs['font_id']) ? intval($annotation_refs['font_id']) : 0;
        $font_path = $this->getFontPath($font_id);
        if(!$font_path){
            header("HTTP/1.1 404 Not Found");
            header("status: 404 Not Found");
            exit();
        }

        $image->newimage(300, 300, $background_color);

        //定制imagick draw
        $draw = new ImagickDraw();
        $font_size = isset($annotation_refs['font_size']) ? intval($annotation_refs['font_size']) : 36;
        $draw->setFontSize($font_size);
        $draw->setFont($font_path);

        //写字
        $draw->setGravity(Imagick::GRAVITY_NORTHWEST);
        $new_keyword = isset($annotation_refs['new_keyword']) ? $annotation_refs['new_keyword'] : "";

        //字体对应行数以及坐标的关系
        $font_coordinate_settings = array();
        //大号字体
        $font_coordinate_large_font_settings = array();
        //一行
        $font_coordinate_large_font_settings[0][0] = array(30, 123);
        //二行
        if(in_array($color_plan_id, array('5-8', '5-9', '5-10'))){
            $font_coordinate_large_font_settings[1][0] = array(30, 90-5);
            $font_coordinate_large_font_settings[1][1] = array(30, 150+5);
        }else{
            $font_coordinate_large_font_settings[1][0] = array(30, 95);
            $font_coordinate_large_font_settings[1][1] = array(30, 150);
        }
        //三行
        if(in_array($color_plan_id, array('5-8', '5-9', '5-10'))){
            $font_coordinate_large_font_settings[2][0] = array(30, 68-10);
            $font_coordinate_large_font_settings[2][1] = array(30, 123);
            $font_coordinate_large_font_settings[2][2] = array(30, 178+10);
        }else{
            $font_coordinate_large_font_settings[2][0] = array(30, 68);
            $font_coordinate_large_font_settings[2][1] = array(30, 123);
            $font_coordinate_large_font_settings[2][2] = array(30, 178);
        }

        $font_coordinate_settings[0] = $font_coordinate_large_font_settings;
        //小号字体
        $font_coordinate_small_font_settings = array();
        //一行
        $font_coordinate_small_font_settings[0][0] = array(24, 130);
        //二行
        if(in_array($color_plan_id, array('5-8', '5-9', '5-10'))){
            $font_coordinate_small_font_settings[1][0] = array(24, 109-5);
            $font_coordinate_small_font_settings[1][1] = array(24, 150+5);
        }else{
            $font_coordinate_small_font_settings[1][0] = array(24, 109);
            $font_coordinate_small_font_settings[1][1] = array(24, 150);
        }
        //三行
        if(in_array($color_plan_id, array('5-8', '5-9', '5-10'))){
            $font_coordinate_small_font_settings[2][0] = array(24, 89-10);
            $font_coordinate_small_font_settings[2][1] = array(24, 130);
            $font_coordinate_small_font_settings[2][2] = array(24, 171+10);
        }else{
            $font_coordinate_small_font_settings[2][0] = array(24, 89);
            $font_coordinate_small_font_settings[2][1] = array(24, 130);
            $font_coordinate_small_font_settings[2][2] = array(24, 171);
        }
        //四行
        if(in_array($color_plan_id, array('5-8', '5-9', '5-10'))){
            $font_coordinate_small_font_settings[3][0] = array(24, 68-15);
            $font_coordinate_small_font_settings[3][1] = array(24, 109-5);
            $font_coordinate_small_font_settings[3][2] = array(24, 150+5);
            $font_coordinate_small_font_settings[3][3] = array(24, 191+15);
        }else{
            $font_coordinate_small_font_settings[3][0] = array(24, 68);
            $font_coordinate_small_font_settings[3][1] = array(24, 109);
            $font_coordinate_small_font_settings[3][2] = array(24, 150);
            $font_coordinate_small_font_settings[3][3] = array(24, 191);
        }
        $font_coordinate_settings[1] = $font_coordinate_small_font_settings;

        //行数设置
        $rows_cnt = isset($annotation_refs['rows']) ? intval($annotation_refs['rows']) : 1;
        $small_font = isset($annotation_refs['small_font']) ? intval($annotation_refs['small_font']) : 0;
        if($small_font == 0){
            if($rows_cnt > 3){
                $rows_cnt = 3;
            }
        }else{
            if($rows_cnt > 4){
                $rows_cnt = 4;
            }
        }

        //写字
        foreach($annotation_refs['input_arr'] as $row_index => $input_one_line_arr){
            //行数限制
            if($small_font == 0){
                if($row_index > 2){
                    break;
                }
            }else{
                if($row_index > 3){
                    break;
                }
            }
            //每行标注
            $row_index = intval($row_index);
            $offset_x = 0;
            foreach($input_one_line_arr as $slice_index => $input_slice){
                $slice_index = intval($slice_index);
                list($start_x, $start_y) = $font_coordinate_settings[$small_font][$rows_cnt-1][$row_index];

                if(isset($input_slice['type'])){
                    if(intval($input_slice['type']) === 0){
                        $keyword_slice = mb_substr($new_keyword, intval($input_slice['start_index']), intval($input_slice['end_index']) - intval($input_slice['start_index']) + 1, "utf-8");

                        $metrics = $image->queryFontMetrics($draw, $keyword_slice, false);
                        $text_width = $metrics['textWidth'];
                        $text_height = $metrics['textHeight'];
                        //字体背景加颜色
                        if(in_array($color_plan_id, array('5-8', '5-9', '5-10'))){
                            $draw->setFillColor($enhance_all_font_background_color);
                            $draw->rectangle($offset_x + $start_x, $start_y, $offset_x + $start_x + $text_width, $start_y + $text_height);
                        }
                        $draw->setFillColor($font_color);
                        $draw->annotation($offset_x + $start_x, $start_y, $keyword_slice);
                        $offset_x += $metrics['textWidth'];
                    }else{
                        $keyword_slice = mb_substr($new_keyword, intval($input_slice['start_index']), intval($input_slice['end_index']) - intval($input_slice['start_index']) + 1, "utf-8");
                        $metrics = $image->queryFontMetrics($draw, $keyword_slice, false);
                        $enhance_width = $metrics['textWidth'];
                        $enhance_height = $metrics['textHeight'];

                        if(in_array($color_plan_id, array('5-4', '5-5', '5-8', '5-9', '5-10'))){
                            //字体背景加颜色
                            $draw->setFillColor($enhance_font_background_color);
                            $draw->rectangle($offset_x + $start_x, $start_y, $offset_x + $start_x + $enhance_width, $start_y + $enhance_height);

                            $draw->setFillColor($enhance_font_color);
                            $draw->annotation($offset_x + $start_x, $start_y, $keyword_slice);
                        }elseif(in_array($color_plan_id, array('6-5'))) {
                            //渐变
                            $first_gradient = "gradient:#FF1414-#FDE853";
                            $second_gradient = "gradient:#B4EC51-#4A90E2";
                            if (isset($this->enhance_gradient_settings[$color_plan_id])) {
                                $first_gradient = $this->enhance_gradient_settings[$color_plan_id][0];
                                $second_gradient = $this->enhance_gradient_settings[$color_plan_id][1];
                            };
                            //计算渐变的起点
                            $gradient_offset_x = $offset_x + $start_x;
                            $gradient_offset_y = $start_y;

                            //创建PseudoImage（左半边）
                            $im_first = new Imagick();
                            $im_first->newPseudoImage($enhance_height, $enhance_width / 2, $first_gradient);
                            $im_first->rotateImage(new ImagickPixel(), 270);

                            //创建PseudoImage（右半边）
                            $im_second = new Imagick();
                            $im_second->newPseudoImage($enhance_height, $enhance_width / 2, $second_gradient);
                            $im_second->rotateImage(new ImagickPixel(), 270);

                            $draw_gradient = new ImagickDraw();
                            $draw_gradient->setFontSize($font_size);
                            $draw_gradient->setFont($font_path);
                            $draw_gradient->setGravity(Imagick::GRAVITY_NORTHWEST);

                            //pushPattern的起点(x,y)对这个场景不起作用，只要保证宽、高即可。后面的宽、高表示【每组区域】截取的宽、高。该区域在整个图中可以repeat
                            $draw_gradient->pushPattern('gradient', $gradient_offset_x, $gradient_offset_y, $enhance_width + $gradient_offset_x, $enhance_height + $gradient_offset_y);
                            //composite的起点(x,y)表示渐变区域的起始位置，宽、高表示渐变区域真实的宽高，区域越宽，渐变越长，此宽高绘制好后，会被pushPattern给截断
                            $draw_gradient->composite(Imagick::COMPOSITE_OVER, $gradient_offset_x, $gradient_offset_y, $enhance_width / 2, $enhance_height, $im_first);
                            $draw_gradient->composite(Imagick::COMPOSITE_OVER, $gradient_offset_x + $enhance_width / 2 - 1, $gradient_offset_y, $enhance_width / 2, $enhance_height, $im_second);
                            $draw_gradient->popPattern();

                            $draw_gradient->setFillPatternURL('#gradient');
                            $draw_gradient->annotation($offset_x + $start_x, $start_y, $keyword_slice);
                            $image->drawImage($draw_gradient);
                        }else{
                            $draw->setFillColor($enhance_font_color);
                            $draw->annotation($offset_x + $start_x, $start_y, $keyword_slice);
                        }
                        $offset_x += $metrics['textWidth'];
                    }
                }
            }
        }
        $image->drawImage($draw);
    }
}
