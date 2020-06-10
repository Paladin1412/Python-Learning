<?php
/**
 * ios 启动屏
 * User: chendaoyan
 * Date: 2019/7/31
 * Time: 14:08
 */

use utils\GFunc;
use utils\Util;

/**
 * 完全访问
 * @path("/IosStart/")
 */
class IosStart {

    /**
     * 获取启动屏资源
     * http://agroup.baidu.com/inputserver/md/article/1979557
     * @route({"GET", "/getStartInfo"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function getStartInfo() {
        $result = Util::initialClass();
        //每小时缓存一次
        $time = strtotime(date('Y-m-d H', time()) . ':00:00');
        $search = urlencode('{"start_time" :{ "$lte": ' . $time . '},"end_time" :{ "$gte": ' . $time . '}}');
        $resUrl = '/res/json/input/r/online/ios_start/?onlycontent=1&search=' . $search;
        $cacheKey = md5(__Class__ . __FUNCTION__ . '_cachekey' . $resUrl);
        $list = Util::ralGetContent($resUrl, $cacheKey, GFunc::getCacheTime('hours'));
        //如果配置有多条，则合并随机下发一张图片和跳转地址
        if (!empty($list)) {
            $listKey = array_rand($list);
            $arrList = $list[$listKey];
            if (!empty($arrList)) {
                $data['start_time'] = date('Y-m-d', $arrList['start_time']);
                $data['end_time'] = date('Y-m-d', $arrList['end_time']);
                $resourceKey = array_rand($arrList['resource']);
                $data = array_merge($data, $arrList['resource'][$resourceKey]);
                $result['data'] = $data;
            }
        }

        return Util::returnValue($result);

    }
}
