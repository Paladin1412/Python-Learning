<?php
/**
 *
 * @desc 内测用户
 * @path("/cbt/")
 */
use models\CbtModel;
use utils\Util;

class Cbt
{
    /**
     * @desc
     * @route({"GET", "/auth"})
     * @param({"ver_name", "$._GET.version"}) string $ver_name 版本号(5-2-0-11或5.2.0.11格式)
     * @param({"imei", "$._GET.imei"}) 用户imei
     * @return({"header", "Content-Type: application/text; charset=UTF-8"})
     * @return({"body"})
        {
        }
     */
    public function auth($ver_name = '5.4.0.0', $imei = '')
    {
        $arrResult = array(
            'status' => 1,
            'imei'   => $this->imei,
            'time'   => strval(time()),
        );

        if ('6.0.0.18' != Util::formatVer($ver_name)) {
            $cbtModel = IoCload('models\\CbtModel');
            $idStr = $cbtModel->cache_getIdStr();
            $idList = json_decode($idStr, true);

            if (in_array($imei, $idList)) {
                $arrResult["imei"] = $imei;
            } else {
                header('X-PHP-Response-Code: '. 404, true, 404);
                exit();
            }
        }

        print bd_AESB64_Encrypt(json_encode($arrResult));
        exit;
    }
    
    /**
     * save
     * @route({"POST", "/save"})
     * @param({"phoneModel", "$._POST.phone_model"}) string 机型
     * @param({"rom", "$._POST.rom"}) string rom
     * @param({"cd", "$._POST.cd"}) string cd
     * @param({"reason", "$._POST.reason"}) string 申请理由
     * @param({"email", "$._POST.email"}) string email
     * @param({"qq", "$._POST.qq"}) string qq
     * @param({"phone", "$._POST.phone"}) string 手机号
     * @param({"baiduAccount", "$._POST.baidu_account"}) string baidu帐号
     * @return 
     */
    public function save($phoneModel='', $rom='', $cd='', $reason='', $email='', $qq='', $phone='', $baiduAccount='') {
        header("Access-Control-Allow-Origin: *");
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => array(),
        );
        
        if (empty($phoneModel) || empty($rom) || empty($cd) || empty($qq)) {
            $result['code'] = 1;
            $result['msg'] = '参数错误';
            return $result;
        }
        //判断用户是否添加过
        $cbtUserModel = IoCload('models\\CbtUserModel');
        $isExist = $cbtUserModel->queryByCd($cd);
        if (!empty($isExist)) {
            $result['code'] = 2;
            $result['msg'] = '您已经提交过内测，请勿重复提交。';
            return $result;
        }
        
        $data = array();
        $data['phone_model'] = htmlspecialchars($phoneModel);
        $data['rom'] = htmlspecialchars($rom);
        $data['cd'] = htmlspecialchars($cd);
        $data['reason'] = htmlspecialchars($reason);
        $data['email'] = htmlspecialchars($email);
        $data['qq'] = intval($qq);
        $data['phone'] = intval($phone);
        $data['baidu_account'] = htmlspecialchars($baiduAccount);
        $data['status'] = 0;
        $data['create_time'] = date('Y-m-d H:i:s');
        $inserRes = $cbtUserModel->save($data);
        if (!$inserRes) {
            $result['code'] = 3;
            $result['msg'] = '添加失败';
        }
        return $result;
    }
    
    /**
     * @route({"GET", "/check"})
     * @param({"cd", "$._GET.cd"}) string cd
     * @return
     */
    public function check($cd='') {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => array(),
        );
        
        if (empty($cd)) {
            $result['code'] = 1;
            $result['msg'] = '参数错误';
            return $result;
        }
        
        $cbtUserModel = IoCload('models\\CbtUserModel');
        $user = $cbtUserModel->queryByCd($cd, 'status');
        if (isset($user[0]['status']) && 1 == $user[0]['status']) {
            $result['data']['result'] = 1;
        } else {
            $result['data']['result'] = 0;
        }
        
        return $result;
    }
    /**
     * get switch
     * @route({"GET", "/switch"})
     * @return
     */
    public function getSwitch() {
        $result = array(
            'code' => 0,
            'msg' => '',
            'data' => array(),
        );
        
        $cbtUserModel = IoCload('models\\CbtSwitchModel');
        $switch = $cbtUserModel->getSwitch();
        if (isset($switch[0]['switch'])) {
            $result['data']['switch'] = $switch[0]['switch'];
        } else {
            $result['code'] = 1;
        }
        
        return $result;
    }
}