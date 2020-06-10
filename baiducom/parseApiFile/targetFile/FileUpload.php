<?php

use utils\Bos;
use utils\ErrorCode;
use utils\GFunc;
use utils\Util;

/**
 * ios 瘦身 文件上传
 * User: chendaoyan
 * Date: 2018/12/5
 * Time: 16:35
 * @path("/fileUpload/")
 *
 */
class FileUpload {
    /** @property $bucket */
    private $bucket;

    //bos保存路径
    private $strPath = 'fileupload';

    /**
     * 上传文件
     * http://agroup.baidu.com/inputserver/md/article/1408798
     * @route({"POST","/slimming"})
     * @param({"tagName", "$._POST.tagName"})
     * @param({"version", "$._POST.version"})
     * @param({"bundleId", "$._POST.bundleId"})
     * @param({"clientMd5", "$._POST.md5"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     * @throws Exception
     */
    public function slimming($tagName, $version, $bundleId='', $clientMd5='') {
        $result = Util::initialClass();
        if (empty($tagName) || empty($version)) {
            return ErrorCode::returnError('PARAM_ERROR', 'params error');
        }
        $md5 = md5_file($_FILES['data']['tmp_name']);
        if ($md5 != $clientMd5) {
            return ErrorCode::returnError('PARAM_ERROR', 'file content error');
        }
        $version = htmlspecialchars(trim($version));
        $tagName = htmlspecialchars(trim($tagName));
        $objBos = new Bos($this->bucket, $this->strPath);
        $objectName = date('Y-m-d') . '/' . md5(microtime() . rand(0, 10000)) . $_FILES['data']['name'];
        $uploadRes = $objBos->putObjectFromFile($objectName, $_FILES['data']['tmp_name']);
        if (1 != $uploadRes['status']) {
            return ErrorCode::returnError('UPLOAD_ERROR', 'upload file error');
        }
        $objInputFileUploadModel = IoCload('models\\FileUploadModel');
        $condition = array('version=' => $version , 'tag_name=' => $tagName);
        if (!empty($bundleId)) {
            $condition['bundle_id='] = htmlspecialchars(trim($bundleId));
        }
        $checkIsExist = $objInputFileUploadModel->select('*', $condition);
        $row = array();
        $row['md5'] = $md5;
        $row['size'] = $_FILES['data']['size'];
        $row['file_url'] = $this->strPath . '/' . $objectName;
        if (empty($checkIsExist)) {
            $row['version'] = $version;
            $row['tag_name'] = $tagName;
            $row['bundle_id'] = $bundleId;
            $row['create_time'] = date('Y-m-d H:i:s');
            $insertRes = $objInputFileUploadModel->insert($row);
        } else {
            $insertRes = $objInputFileUploadModel->update($row, $condition);
        }
        if (!$insertRes) {
            return ErrorCode::returnError('DB_ERROR', 'insert to db error');
        }

        return Util::returnValue($result);
    }

    /**
     * 资源文件检查
     * http://agroup.baidu.com/inputserver/md/article/1408812
     * @route({"POST","/check"})
     * @param({"version", "$._POST.version"})
     * @param({"bundleId", "$._POST.bundleId"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function check($version='', $bundleId='') {
        $result = Util::initialClass();
        $objInputFileUploadModel = IoCload("models\\FileUploadModel");
        $version = htmlspecialchars(trim($version));
        $condition = array();
        !empty($version) && $condition['version='] = $version;
        !empty($bundleId) && $condition['bundle_id='] = $bundleId;
        if (!empty($condition)) {
            $list = $objInputFileUploadModel->select('*', $condition);
        } else {
            $list = $objInputFileUploadModel->select('*');
        }
        $data = array();
        $bosHost = GFunc::getGlobalConf('bos_host_https');
        foreach ($list as $listV) {
            unset($listV['id']);
            $listV['file_url'] = sprintf('%s/%s', $bosHost, $listV['file_url']);
            $data[$listV['version']][] = $listV;
        }

        !empty($data) && $result['data'] = $data;

        return Util::returnValue($result);
    }

    /**
     * 获取资源信息
     * http://agroup.baidu.com/inputserver/md/article/1409278
     * @route({"POST","/getInfo"})
     * @param({"version", "$._POST.version"})
     * @param({"tagNames", "$._POST.tagNames"})
     * @param({"bundleId", "$._POST.bundleId"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function getInfo($version, $tagNames, $bundleId='') {
        $result = Util::initialClass(false);
        $arrTagNames = json_decode($tagNames, true);
        $version = htmlspecialchars(trim($version));
        $bundleId = htmlspecialchars(trim($bundleId));
        if (empty($version) || empty($tagNames) || empty($arrTagNames)) {
            return ErrorCode::returnError('PARAM_ERROR', 'params error');
        }
        $objInputFileUploadModel = IoCload("models\\FileUploadModel");
        $strTagName = '';
        foreach ($arrTagNames as $arrTagNameV) {
            $strTagName .= '"' . htmlspecialchars(trim($arrTagNameV)) . '",';
        }
        $strTagName = rtrim($strTagName, ',');
        $list = $objInputFileUploadModel->getListByTagNameAndVersion($version, $strTagName, $bundleId);
        if (!empty($list) && is_array($list)) {
            $bosHost = GFunc::getGlobalConf('bos_host_https');
            foreach ($list as $listK => $listV) {
                unset($listV['id']);
                $listV['file_url'] = sprintf('%s/%s', $bosHost, $listV['file_url']);
                $result['data'][] = $listV;
            }
        }

        return Util::returnValue($result, false);
    }

    /**
     * @route({"POST","/offlineVoiceLog"})
     * 离线语音日志上传
     *
     * @return
     */
    public function offlineVoiceLogUpload() {

        if(empty($_GET['tm'])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'tm is empty');
        }

        if(empty($_GET['sign'])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'sign is empty');
        }

        if(empty($_GET['cuid'])) {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'cuid is empty');
        }

        if (isset($_FILES['logfile'])) {
            if(0 === $_FILES['logfile']['error']) {

                $strFileContent = file_get_contents($_FILES['logfile']['tmp_name']);
                $strAESContent = bd_AESB64_Decrypt(trim($strFileContent));

                if(false === $strAESContent) {
                    return ErrorCode::returnError(ErrorCode::PARAM_ERROR, ' logfile AESB64_decode error');
                }

                $strMd5File = strtolower(md5_file($_FILES['logfile']['tmp_name']));

                if(false === $strMd5File) {
                    return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'get logfile md5 fail');
                }
            } else {
                return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'logfile upload error['
                    .$_FILES['logfile']['error'].']');
            }

        } else {
            return ErrorCode::returnError(ErrorCode::PARAM_ERROR, 'logfile is empty');
        }

        $strSalt = "*_Ji&NFAL#@Qhf!O";

        $strSign = md5($strMd5File . trim($_GET['tm']) . $strSalt);

        if(strtolower(trim($_GET['sign'])) !== strtolower($strSign)) {
            return ErrorCode::returnError(ErrorCode::SIGN_ERROR);
        }


        $objBos = new Bos('imeres', 'file-upload');

        $strUploadDate = date('YmdHis');

        $strKey = GFunc::getGlobalConf("env")."/offline-voice-log/".md5(trim($_GET['cuid']))."/".$strUploadDate;


        $arrResult = $objBos->putObjectFromFile($strKey, $_FILES['logfile']['tmp_name']);

        if (1 != $arrResult['status']) {
            return ErrorCode::returnError(ErrorCode::UPLOAD_ERROR,"bos ecode:"
                .$arrResult['errorCode'], " emsg:".$arrResult['message']);
        }

        $arrReturn = Util::initialClass();

        $arrReturn['data'] = array('time' => $strUploadDate);

        return Util::returnValue($arrReturn);

    }


}