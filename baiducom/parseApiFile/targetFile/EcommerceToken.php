<?php

use utils\ErrorCode;
use utils\GFunc;
use utils\Util;

/**
 * Class EcommerceToken
 * @desc 电商场景下口令
 * @path("/e_commerce_token/")
 */
class EcommerceToken
{
    /** @property 默认缓存时间 */
    private $intCacheExpired;

    /**
     * @Requirement_doc
     * http://newicafe.baidu.com/issue/inputserver-2595/show?from=page
     * http://newicafe.baidu.com/issue/A85-1397/show?from=page
     *
     * @api_doc
     * http://agroup.baidu.com/inputserver/md/article/2332025
     *
     * @technology_program
     * http://wiki.baidu.com/pages/viewpage.action?pageId=992121158
     *
     * @desc 获取下发token
     * @route({"GET", "/get_token"})
     * @param({"skin_token", "$._GET.skin_token"}) 皮肤token
     * @param({"package_name", "$._GET.package_name"}) 包名
     * @param({"strCuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function getToken($skin_token = "", $package_name = "", $strCuid = '')
    {
//        $result = Util::initialClass(true);
        if (empty($strCuid) || empty($package_name)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        /** @var \models\EcommerceTokenModel $model */
        $model = new \models\EcommerceTokenModel();
        $result = $model->getContent($skin_token, $package_name, $strCuid);
        return Util::returnValue($result, true);
    }

    /**
     * @desc 清除单个用户的展示状态
     * @route({"GET", "/del_shown"})
     * @param({"strCuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function delShown($strCuid)
    {
        $result = Util::initialClass(true);
        if (empty($strCuid)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        /** @var \models\EcommerceTokenModel $model */
        $model = new \models\EcommerceTokenModel();
        $data = $model->delShown($strCuid);
        $result['data'] = $data;
        return Util::returnValue($result, true);
    }

    /**
     * @desc 获取已展示的token 列表
     * @route({"GET", "/get_shown"})
     * @param({"strCuid", "$._GET.cuid"}) string $cuid cuid明文。不需要客户端传，从加密参数中获取
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function getShown($strCuid)
    {
        $result = Util::initialClass(true);
        if (empty($strCuid)) {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }
        /** @var \models\EcommerceTokenModel $model */
        $model = new \models\EcommerceTokenModel();
        $data = $model->getShownIds($strCuid);
        $result['data'] = $data;
        return Util::returnValue($result, true);
    }

    /**
     * @desc 获取下发token
     * @route({"GET", "/get_online_tokens"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function getOnlineTokens()
    {
        $result = Util::initialClass(true);

        /** @var \models\EcommerceTokenModel $model */
        $model = new \models\EcommerceTokenModel();
        $result['data'] = $model->getOnlineTokens();
        return Util::returnValue($result, true);
    }

    /**
     * http://agroup.baidu.com/inputserver/md/article/2332025
     * @desc 获取包名白名单
     * @route({"GET", "/get_all_package"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     **/
    public function getPackages()
    {
        $result = Util::initialClass(true);

        /** @var \models\EcommerceTokenPackageModel $model */
        $model = new \models\EcommerceTokenPackageModel();
        list($list, $version) = $model->getList();
        $result['data'] = $list;
        $result['version'] = $version;
        return Util::returnValue($result, true, true);
    }
}