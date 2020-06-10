<?php
/**
 *
 * @desc acs 搜索
 * @path("/emojitab/")
 */
class Emojitab
{
    /**
     * @desc acs搜索匹配
     * @route({"GET", "/list"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function query()
    {
        $res = array(
            'data' => array(),
        );
        $emojitabModel = IoCload('models\\EmojitabModel');

        $data = $emojitabModel->cache_getEmojitab();
        $res['data'] = $data;

        return $res;
    }
}