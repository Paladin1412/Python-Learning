<?php
use models\NoteModel;
use utils\ErrorCode;
use utils\Util;
/**
 * 便签
 *
 * @author chendaoyan
 * @date 2017年2月23日
 * @path("/note/")
 */
class Note {
    public $objNoteModel = null;
    public $objRedis = null;
    public $preRedisKey = 'note_';
    /** @property 内部缓存默认过期时间(单位: 秒) */
    public $intCacheExpired = null;
    public $intTmpCacheExpired = null;

    /**
     * construct
     */
    public function __construct() {
        $this->objNoteModel = new NoteModel();
    }

    /**
     * 查看是否存在备份 8.3之后使用此接口
     * @route({"POST","/isexist"})
     *
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function isExists() {
        $result = array(
            'errno' => 0,
            'ecode' =>0,
            'error' => '',
            'data' => new stdClass()
        );
        //判断bduss信息
        $bdussModel = IoCload("models\\BdussModel");
        $userInfo = $bdussModel->getUserInfoByBduss();
        if (false !== $userInfo) {
            $uid = intval($userInfo['uid']);
        } else {
            $result['errno'] = ErrorCode::BDUSS_ERR;
            $result['ecode'] = ErrorCode::BDUSS_ERR;
            $result['error'] = 'passport error';
            return $result;
        }
        //先从redis里面读取最新的数据返回给用户，如果没有则从数据库读取
        $noteBack = $this->objRedis->hget($this->preRedisKey . 'tmp_save' ,$this->preRedisKey . md5($uid));
        if (!empty($noteBack) && 'null' != $noteBack) {
            $result['data']->is_exist = 1;
            return $result;
        }
        //从数据库中取数据
        $succeeded = false;
        $noteBack = $this->objRedis->get($this->preRedisKey . 'backup_isexist_' . md5($uid), $succeeded);
        if (false === $succeeded || empty($noteBack) || 'null' == $noteBack) {
            $noteBack = $this->objNoteModel->getNote($uid);
            $this->objRedis->set($this->preRedisKey . 'backup_isexist_' . md5($uid), json_encode($noteBack), $this->intCacheExpired);
        }

        if ('null' != $noteBack && $noteBack) {
            $result['data']->is_exist = 1;
        } else {
            $result['data']->is_exist = 0;
        }

        return $result;
    }

    /**
     * 查看是否存在备份 8.3之前使用此接口
     * @route({"GET","/isexist"})
     *
     * @param ({"bduss", "$._GET.bduss"}) bduss为用户uid加密字符串
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function isExist($bduss = '') {
        $result = array(
            'errno' => 0,
            'ecode' =>0,
            'error' => '',
            'data' => new stdClass()
        );

        $bduss = bd_AESB64_Decrypt($bduss);
        if (empty($bduss)) {
            $result['errno'] = 1;
            $result['ecode'] = 1;
            $result['error'] = 'error bduss';
            return $result;
        }
        //通过uid获取用户信息
        $userInfo = Util::getUserInfoByUid($bduss);
        if (false === $userInfo) {
            $result['errno'] = 1;
            $result['ecode'] = 1;
            $result['error'] = 'get userinfo error';
            return $result;
        } else if (isset($userInfo[$bduss]['userstate']) && (1 == $userInfo[$bduss]['userstate'] || 2 == $userInfo[$bduss]['userstate'])) {
            $result['errno'] = ErrorCode::BDUSS_ERR;
            $result['ecode'] = ErrorCode::BDUSS_ERR;
            $result['error'] = 'passport error';
            return $result;
        }

        //先从redis里面读取最新的数据返回给用户，如果没有则从数据库读取
        $noteBack = $this->objRedis->hget($this->preRedisKey . 'tmp_save' ,$this->preRedisKey . md5($bduss));
        if (!empty($noteBack) && 'null' != $noteBack) {
            $result['data']->is_exist = 1;
            return $result;
        }
        //从数据库中取数据
        $succeeded = false;
        $noteBack = $this->objRedis->get($this->preRedisKey . 'backup_isexist_' . md5($bduss), $succeeded);
        if (false === $succeeded || empty($noteBack) || 'null' == $noteBack) {
            $noteBack = $this->objNoteModel->getNote($bduss);
            $this->objRedis->set($this->preRedisKey . 'backup_isexist_' . md5($bduss), json_encode($noteBack), $this->intCacheExpired);
        }

        if ('null' != $noteBack && $noteBack) {
            $result['data']->is_exist = 1;
        } else {
            $result['data']->is_exist = 0;
        }

        return $result;
    }

    /**
     * 备份
     * @route({"POST","/backup"})
     *
     * @param ({"bduss", "$._GET.bduss"}) bduss为用户uid加密字符串
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function backup($bduss = '') {
        $result = array(
            'errno' => 0,
            'ecode' =>0,
            'error' => '',
            'data' => new stdClass()
        );

        //参数判断
        if (!empty($_FILES ['ukey']['tmp_name'])) {
            //判断bduss信息
            $bdussModel = IoCload("models\\BdussModel");
            $userInfo = $bdussModel->getUserInfoByBduss();
            if (false !== $userInfo) {
                $bduss = intval($userInfo['uid']);
            } else {
                $result['errno'] = ErrorCode::BDUSS_ERR;
                $result['ecode'] = ErrorCode::BDUSS_ERR;
                $result['error'] = 'passport error';
                return $result;
            }
        } else {
            $bduss = bd_AESB64_Decrypt($bduss);
            if (empty($bduss)) {
                $result['errno'] = 1;
                $result['ecode'] = ErrorCode::BDUSS_ERR;
                $result['error'] = 'error bduss';
                return $result;
            }
            //通过uid获取用户信息
            $userInfo = Util::getUserInfoByUid($bduss);
            if (false === $userInfo) {
                $result['errno'] = 1;
                $result['ecode'] = 1;
                $result['error'] = 'get userinfo error';
                return $result;
            } else if (isset($userInfo[$bduss]['userstate']) && (1 == $userInfo[$bduss]['userstate'] || 2 == $userInfo[$bduss]['userstate'])) {
                $result['errno'] = ErrorCode::BDUSS_ERR;
                $result['ecode'] = ErrorCode::BDUSS_ERR;
                $result['error'] = 'passport error';
                return $result;
            }
        }

        $time = time();
        $uploadRes = $this->objNoteModel->uploadToBos($_FILES['note']['tmp_name'], $_FILES['note']['name'], $bduss, $time);
        if (false === $uploadRes) {
            $result['errno'] = 1;
            $result['ecode'] = 1;
            $result['error'] = 'upload to bos error';
            return $result;
        } else {
            // 直接发送成功返回给客户端，并向redis写入一条数据
            $value = array();
            $value['url'] = $uploadRes;
            $value['uid'] = $bduss;
            $value['time'] = $time;
            $pushRes = $this->objRedis->lpush($this->preRedisKey . 'back', json_encode($value));
            //存到redis里面，防止用户刚备份完，脚本还没来得及跑就恢复
            $this->objRedis->hset($this->preRedisKey . 'tmp_save' ,$this->preRedisKey . md5($bduss), json_encode($value));
            if (empty($pushRes)) { // 如果因超时等原因写入失败，则再写入一次
                $this->objRedis->lpush($this->preRedisKey . 'back', json_encode($value));
            }
        }

        return $result;
    }

    /**
     * 恢复备份 8.3之前使用此接口
     * @route({"GET","/restore"})
     *
     * @param ({"bduss", "$._GET.bduss"}) bduss为用户uid加密字符串
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function restore($bduss = '') {
        $result = array(
            'errno' => 0,
            'ecode' => 0,
            'error' => '',
            'data' => new stdClass()
        );
        //参数判断
        $bduss = bd_AESB64_Decrypt($bduss);
        if (empty($bduss)) {
            $result['errno'] = 1;
            $result['ecode'] = ErrorCode::BDUSS_ERR;
            $result['error'] = 'error bduss';
            return $result;
        }
        //通过uid获取用户信息
        $userInfo = Util::getUserInfoByUid($bduss);
        if (false === $userInfo) {
            $result['errno'] = 1;
            $result['ecode'] = 1;
            $result['error'] = 'get userinfo error';
            return $result;
        } else if (isset($userInfo[$bduss]['userstate']) && (1 == $userInfo[$bduss]['userstate'] || 2 == $userInfo[$bduss]['userstate'])) {
            $result['errno'] = ErrorCode::BDUSS_ERR;
            $result['ecode'] = ErrorCode::BDUSS_ERR;
            $result['error'] = 'passport error';
            return $result;
        }

        $succeeded = false;
        //先从redis里面读取最新的数据返回给用户，如果没有则从数据库读取
        $noteBack = $this->objRedis->hget($this->preRedisKey . 'tmp_save' ,$this->preRedisKey . md5($bduss));
        if (!empty($noteBack) && 'null' != $noteBack) {
            // 从bos获取url
            $noteBack = json_decode($noteBack, true);
            if (!empty($noteBack['url'])) {
                $url = $this->objNoteModel->getUrlByObjectKey(md5($noteBack['uid']) . '/' . $noteBack['time'] . '/' . $noteBack['url']);
                if (false === $url) {
                    $result['errno'] = 1;
                    $result['ecode'] = 1;
                    $result['error'] = 'get from redis url error';
                } else {
                    $result['data']->url = $url;
                }
            } else {
                $result['errno'] = 1;
                $result['ecode'] = 1;
                $result['error'] = 'get from redis url error';
            }
            return $result;
        }
        //从数据库中取数据
        $noteBack = $this->objRedis->get($this->preRedisKey . 'backup_' . md5($bduss), $succeeded);
        if (false === $succeeded || empty($noteBack) || 'null' == $noteBack) {
            $noteBack = $this->objNoteModel->getLastNote($bduss);
            !empty($noteBack) && $this->objRedis->set($this->preRedisKey . 'backup_' . md5($bduss), json_encode($noteBack), $this->intCacheExpired);
        }

        if (is_string($noteBack)) {
            $noteBack = json_decode($noteBack, true);
        }
        if (! empty($noteBack['url'])) {
            // 从bos获取url
            $url = $this->objNoteModel->getUrlByObjectKey($noteBack['uid'] . '/' . $noteBack['time'] . '/' . $noteBack['url']);
            if (false === $url) {
                $result['errno'] = 1;
                $result['ecode'] = 1;
                $result['error'] = 'get url error';
            } else {
                $result['data']->url = $url;
            }
        } else {
            $result['errno'] = 1;
            $result['ecode'] = 1;
            $result['error'] = 'no noteback';
        }

        return $result;
    }

    /**
     * 恢复备份 8.3之后使用此接口
     * @route({"POST","/restore"})
     *
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     */
    public function restores() {
        $result = array(
            'errno' => 0,
            'ecode' => 0,
            'error' => '',
            'data' => new stdClass()
        );
        //判断bduss信息
        $bdussModel = IoCload("models\\BdussModel");
        $userInfo = $bdussModel->getUserInfoByBduss();
        if (false !== $userInfo) {
            $bduss = intval($userInfo['uid']);
        } else {
            $result['errno'] = ErrorCode::BDUSS_ERR;
            $result['ecode'] = ErrorCode::BDUSS_ERR;
            $result['error'] = 'passport error';
            return $result;
        }

        $succeeded = false;
        //先从redis里面读取最新的数据返回给用户，如果没有则从数据库读取
        $noteBack = $this->objRedis->hget($this->preRedisKey . 'tmp_save' ,$this->preRedisKey . md5($bduss));
        if (!empty($noteBack) && 'null' != $noteBack) {
            // 从bos获取url
            $noteBack = json_decode($noteBack, true);
            if (!empty($noteBack['url'])) {
                $url = $this->objNoteModel->getUrlByObjectKey(md5($noteBack['uid']) . '/' . $noteBack['time'] . '/' . $noteBack['url']);
                if (false === $url) {
                    $result['errno'] = 1;
                    $result['ecode'] = 1;
                    $result['error'] = 'get from redis url error';
                } else {
                    $result['data']->url = $url;
                }
            } else {
                $result['errno'] = 1;
                $result['ecode'] = 1;
                $result['error'] = 'get from redis url error';
            }
            return $result;
        }
        //从数据库中取数据
        $noteBack = $this->objRedis->get($this->preRedisKey . 'backup_' . md5($bduss), $succeeded);
        if (false === $succeeded || empty($noteBack) || 'null' == $noteBack) {
            $noteBack = $this->objNoteModel->getLastNote($bduss);
            !empty($noteBack) && $this->objRedis->set($this->preRedisKey . 'backup_' . md5($bduss), json_encode($noteBack), $this->intCacheExpired);
        }

        if (is_string($noteBack)) {
            $noteBack = json_decode($noteBack, true);
        }
        if (! empty($noteBack['url'])) {
            // 从bos获取url
            $url = $this->objNoteModel->getUrlByObjectKey($noteBack['uid'] . '/' . $noteBack['time'] . '/' . $noteBack['url']);
            if (false === $url) {
                $result['errno'] = 1;
                $result['ecode'] = 1;
                $result['error'] = 'get url error';
            } else {
                $result['data']->url = $url;
            }
        } else {
            $result['errno'] = 1;
            $result['ecode'] = 1;
            $result['error'] = 'no noteback';
        }

        return $result;
    }
}