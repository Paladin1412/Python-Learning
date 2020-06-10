<?php
/**
 *
 * @desc 河图图片处理
 * @path("/image/")
 */
use tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use utils\Util;
use utils\Phaster;
use utils\Consts;
use utils\GFunc;
use models\StyleTransModel;
use utils\ErrorCode;


class ImageHandle
{
    /**
     * @route({"POST","/style"})
     * @param({"image", "$._POST.image"}) 客户端上传base64编码
     * @param({"option", "$._GET.option"}) 标示不同风格参数
     * 9.0版本风格化滤镜接口
     * @return object
     */
    public function StyleHandle($image,$option)
    {
        $data = Util::initialClass();
        $option = intval($option);

        $style = new StyleTransModel();

        $result = $style->getStylelTransImage($image,$option);

        if($result == false )
        {
            return ErrorCode::returnError('PARAM_ERROR', '参数错误');
        }

        $data['data']->imagestr = $result;

        return Util::returnValue($data);

    }
}
