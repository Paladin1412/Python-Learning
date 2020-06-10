<?php
/**
 *
 * @desc 动态表情贴图
 * @path("/demoji/")
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use utils\Util;
use utils\Gfunc;

class Demoji
{
    /**
     * @desc 表情市场
     * @route({"GET", "/list"})
     * @param({"plt", "$._GET.platform"}) string $plt 平台号，不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return
     */
    public function alist($plt = 'a1')
    {
        $platform = Util::getPhoneOS($plt);
        $demojiModel = IoCload('models\\DemojiModel');

        $list = $demojiModel->cache_getListByPlatform($platform);

        return $this->packList($list);
    }

    /**
     * [packList]
     * @param   $list
     * @return
     */
    private function packList($list)
    {
        $ret = array();
        $demojis = array();
        $lmt = 0;

        foreach ($list as $v) {
            if ($v['update_time'] > $lmt) {
                $lmt = $v['update_time'];
            }

            $demojis[] = array(
                'packageid' => $v['demoji_id'],
                'id' => $v['packagekey'],
                'name' => $v['demoji_name'],
                'icon' => $v['demoji_ico'],
                'icon_selected' => $v['demoji_ico_selected'],
                'url' => 'v5/demoji/download?id=' . $v['demoji_id'],
                'mdate' => $v['update_time'],
            );
        }

        //demoji详细列表
        $ret['demojiinfo']['domain'] = Gfunc::getGlobalConf('domain_v5');
        $ret['demojiinfo']['updateTime'] = (int) $lmt;
        $ret['demojiinfo']['demojilist'] = $demojis;

        return $ret;
    }

     /**
     * @desc
     * @route({"GET", "/download"})
     * @param({"id", "$._GET.id"}) string $id 平台号，不需要客户端传，从加密参数中获取
     * @return({"status", "$status"})
     * @return({"header", "$location"})
     * @return({"body"})
     * 验证通过则：
     * HTTP/1.1 302 Found
     * Location: 下载链接
     * 验证不通过则：
     * HTTP/1.1 404 Not Found
     */
    public function download($id = '', &$status='', &$location='')
    {
        if (is_numeric($id)) {
            $demojiModel = IoCload('models\\DemojiModel');
            $demoji = $demojiModel->cache_getById($id);

            if( !empty($demoji)) {
                $url = $demoji['demoji_url'];
                $status = "302 Found";
                $location = "Location: " . $url;
                return;
            }
        }

        $status = "404 Not Found";
        return ;
    }

}
