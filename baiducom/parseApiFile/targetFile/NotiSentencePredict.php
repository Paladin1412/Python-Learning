<?php


use utils\Util;
use utils\ErrorCode;
use utils\GFunc;

/**
 * 整句预测通知逻辑
 * @path("/sentence/")
 */
class NotiSentencePredict {
    


    /**
     *
     * @desc 通知中心信息获取 http://agroup.baidu.com/inputserver/md/article/2584381
     *
     * @route({"GET","/predict"})
     *
     * @param({"notiVersion", "$._GET.message_version"}) 通知中心版本号
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function predict($notiVersion) {
        // 确保传递的参数不为空
        if('' === trim($notiVersion)) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, "通知中心版本号不可为空");
        }
        $response = Util::initialClass(false);
        $conditionFilter = IoCload("utils\\ConditionFilter");
        $sentencePredict = IoCload("models\\SentencePredictModel");
        $result = $sentencePredict->getMatchPackagesWithVersion($notiVersion);
        $datas = $result['packages'];
        $intVersion = $result['version'];
        $datas = $conditionFilter->getFilterConditionFromDB($datas);
        // $intVersion = Util::getDataVersion($sentencePredict->_tbl);
        $response['version'] = strval($intVersion);
        $data = array_shift($datas);
        // 无数据时不下发更新数据
        if(empty($data)) {
            return Util::returnValue($response, true, true);
        }
        // 配置独特连接， 做nginx跳转方便统计下载次数
        $dlink = rtrim(GFunc::getGlobalConf('res_https'), '/') . '/' . ltrim($data['download_link'], '/');
        $response['data'] = array(
            'dlink' => $dlink,
            'fmd5' => $data['fmd5'],
            'download_env' => 0, // 下载所需网络条件 0: ALL, 适配客户端, pm未给到策略， 所以暂时写个固定值
        );
        return Util::returnValue($response, true, true);
    }
}