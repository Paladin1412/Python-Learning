<?php
/**
 *
 * @desc 分享
 * @path("/share/")
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use utils\Util as Util;
use utils\DbConn;
use utils\GFunc;
use models\ShareModel;
use models\ActivityModel;
use models\AdModel;

class Share {
    /**
     * @desc 分享数据
     * @route({"GET", "/info/*\/data/*"})
     * @param({"cat", "$.path[2]"}) int $cat 某自然分类 (activity, ad)
     * @param({"id", "$.path[4]"}) int $id
     * @return({"status", "$status"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
        {
            "share": {
                "title": "",
                "description": "",
                "url": "",
                "image": "http://10.58.19.57:8001/upload/imags/2015/07/10/907kvv8a87.jpg",
                "thumb": "http://10.58.19.57:8001/upload/imags/2015/07/10/907kvv8a87.jpg",
                "video_url": "",
                "platform": [{
                    "name": "weixin",
                    "content": {
                        "title": "",
                        "description": "",
                        "url": "",
                        "image": " ",
                        "thumb": "",
                        "video_url": "", //分享的视频地址
                    }
                },
                {
                    "name": "weixincircle",
                    "content": {
                       "title": "",
                        "description": "",
                        "url": "",
                        "image": "",
                        "thumb": "",
                        "video_url": "", //分享的视频地址
                    }
                },
                {
                    "name": "weibo",
                    "content": {
                        "title": "",
                        "description": "",
                        "url": "",
                        "image": "",
                        "thumb": "",
                        "video_url": "", //分享的视频地址
                    }
                },
                {
                    "name": "qq",
                    "content": {
                        "title": "",
                        "description": "",
                        "url": "",
                        "image": "",
                        "thumb": "",
                        "video_url": "", //分享的视频地址
                    }
                },
                {
                    "name": "qzone",
                    "content": {
                        "title": "",
                        "description": "",
                        "url": "",
                        "image": "",
                        "thumb": "",
                        "video_url": "", //分享的视频地址
                    }
                },
                {
                    "name": "system",
                    "content": {
                        "title": "",
                        "description": "",
                        "url": "",
                        "image": "",
                        "thumb": "",
                        "video_url": "", //分享的视频地址
                    }
                }]
            }
        }
     */
    public function shareData($cat = '', $id = 0, &$status = 0)
    {
        $shareModel = IoCload('models\\ShareModel');
        $cat_id = $this->getCatId($cat);

        $shareData = $shareModel->cache_getShareData($cat_id, $id);

        if ($cat_id && $shareData)
        {
            $defaultShareData = $this->getDefaultShareData($cat_id, $id);

            $image = !empty($shareData['image']) ? $shareData['image'] : $defaultShareData['image'];
            $thumb = !empty($shareData['thumb']) ? $shareData['thumb'] : $defaultShareData['thumb'];
            $weibo_text = !empty($shareData['weibo_text']) ?
                $shareData['weibo_text'] : $defaultShareData['weibo_text'];
            $weixin_title = !empty($shareData['weixin_title']) ?
                $shareData['weixin_title'] : $defaultShareData['weixin_title'];
            $weixin_text = !empty($shareData['weixin_text']) ?
                $shareData['weixin_text'] : $defaultShareData['weixin_text'];

            $res = $this->getShareData($image, $thumb,
                $weibo_text, $weixin_title, $weixin_text);

            return $res;
        }

        $status = "404 Not Found";
        return ;
    }

    /**
     *
     * @param   $cat
     * @return
     */
    private function getCatId($cat)
    {
        $ids = array('activity' => 1, 'ad' => 2);

        return isset($ids[$cat]) ? $ids[$cat] : 0;
    }

    /**
     *
     * @param   $cate_id
     * @param   $id
     * @return
     */
    private function getDefaultShareData($cate_id, $id)
    {
        $data = array(
            "weixin_title" => "来自百度输入法的分享",
            "weixin_text" => "我在百度输入法发现一个好东西，分享给你，你也来看看！",
            "weibo_text" => "我在百度输入法发现一个好东西，分享给你，你也来看看！",
            "image" => "",
            "thumb" => "",
        );

        return $data;
    }

    /**
     * @desc 获取颜文字分享数据
     * @param  array $model      颜文字数据
     * @return array
     */
    public function getShareData($image, $thumb, $weiboTitle, $weixinTitle, $weixinDesc, $shareUrl = '')
    {
        $shareUrl = '';
        $dWeiboTitle  = "";
        $dWeixinTitle = "";
        $dWeixinDesc  = "";
        !$image && $image = '';
        !$thumb && $thumb = '';

        !empty($weiboTitle)   && $weiboTitle .= $dWeiboTitle;
        !empty($weixinTitle) && $weixinTitle .= $dWeixinTitle;
        !empty($weixinDesc)  && $weixinDesc .= $dWeixinDesc;

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
                        "title" => $weixinDesc,
                        "description" => '',
                        "url" => $shareUrl ? $shareUrl . "&platform=weixincircle" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'weibo',
                    'content' => array(
                        "title" => $weiboTitle,
                        "description" => '',
                        "url" => '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'qq',
                    'content' => array(
                        "title" => $weixinDesc,
                        "description" => '',
                        "url" => $shareUrl ? $shareUrl . "&platform=qq" : '',
                        "image" => $image,
                        "thumb" => $thumb,
                    ),
                ),
                array(
                    'name' => 'qzone',
                    'content' => array(
                        "title" => $weixinDesc,
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

}