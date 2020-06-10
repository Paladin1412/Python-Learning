<?php
/**
 * 输入法祝福插件
 * @author chendaoyan
 * @date 2016年5月5日
 * @path("/fest/")
 */
class Fest {
    /**
     * 获取祝福接口
     * @route({"GET", "/curfest/"})
     * @param({"moral", "$._GET.moral"}) string $moral
     * @param({"version", "$._GET.version"}) string $version 版本号
     * @return ({"body"})
     */
    public function curfest($moral, $version) {
        $objFestModel = IoCload('models\\FestModel');
        $arrActivity = $objFestModel->getCurActivity();
        
        if(!empty($arrActivity)){
            return $arrActivity;
        }
        
        $arrFest = $objFestModel->getCurFest($moral,$version);
        return $arrFest;
    }
}