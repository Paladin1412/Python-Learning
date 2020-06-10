<?php
/**
 *
 * @desc 表情转换
 * @path("/emojiinvert/")
 */
use models\EmojiInvertModel;
use utils\Util;
class EmojiInvert
{
    /**
     * @desc emojiinvert 升级
     * @route({"POST", "/upgrade"})
     * @param({"cks", "$._POST['cks']"}) 需要检测的客户端已安装ids json [{id: "id", version_code: "0"}]
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
       {
            "data": [
                {
                    "id": 53,
                    "version_code": 0,
                    "res_net": 0,// 0/1/2/3/4/5
                    "file": "http://a.zip"
                }
            ]
        }
     */
    public function upgrade($cks = '')
    {
        //$res = array('data' => array());
        $res = Util::initialClass(false);
        $cks && $cks = json_decode($cks, true);

        $emojiInvert = IoCload("models\\EmojiInvertModel");
        $inverts = $emojiInvert->getUpgradeEmojiInvert($cks);
        $inverts && $res['data'] = $inverts;

        $res['ecode'] = $emojiInvert->getStatusCode();
        $res['emsg'] = $emojiInvert->getErrorMsg();
        $res['version'] = $emojiInvert->intMsgVer;
        return $res;
    }
}