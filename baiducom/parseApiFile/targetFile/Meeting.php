<?php
/***************************************************************************
 *
* Copyright (c) 2017 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use utils\Util;
use utils\GFunc;
use utils\Bos;
use asyncserv;

/**
 * 会议纪要
 *
 * @author zhoubin05
 * @path("/meeting/")
 */
class Meeting
{
    
    //错误码表    1000~3000 致命错误(不得使用原有数据重试)， 3001~4999  警告（可使用原有数据重试）
    //5000（含）以上是pc端的错误  5000~8000 致命错误(不得使用原有数据重试)， 8001~9999  警告（可使用原有数据重试）
    //原则上错误码只能新增，不许修改含义。 因为客户端会针对某些固定的错误码做业务处理。
    //创：创建会议接口   加：加入会议接口  退：退出会议接口 查：会议查询接口
    //PCID：PC端获取PCID     绑：客户端扫码绑定会议   测：PC端检测会议绑定接口   轮；客户端轮询     PC轮：PC端轮询    汇：会议汇总
    
    private $err_list = array(
        1001 => 'data参数不存在', 
        1002 => 'data解析出错', //无法解析或解析出不是array
        1003 => 'uid错误', //用户id不存在或者不合法(为空,无法解析等)
        1004 => 'mid错误', //会议id不存在或者不合法(为空等)
        1005 => 'pcid错误', //参数中没有pcid或pcid长度错误
        1006 => 'pcid无效', //缓存读取失败或缓存中未发现该pcid 
        1007 => '当前PC同屏已绑定其他会议', 
        1010 => '创建会议失败',//数据库插入失败
        1014 => '已结束的会议无法再次加入', //  [加]
        1015 => 'mname错误', // mname不存在或空
        1016 => 'mids错误', //mids空或格式错误
        1017 => 'member错误', //会议成员为空或数据错
        1020 => '会议不在进行中，无法保存用户数据' ,//会议不在进行中，无法保存用户数据  [轮]
        1021 => '会议已汇总，不可查询', //会议已汇总，无法从轮询接口获取数据
        1022 => 'mtype错误', //会议类型不存在或者不合法(为空等)
        1023 => '会议人数超过限制,无法加入', //会议人数超过限制
        1024 => '无法加入他人的单人会议', //无法加入他人的单人会议
        1025 => '会议已结束', //会议已结束
        1026 => '成员未参加过该会议', //成员未参加过该会议,无权限读取
        1027 => '会议不在进行中，无法更新会议数据', //会议不在进行中，无法更新会议数据    [轮]
        1028 => '清空语音数据错误', //用户不是单人会议创建者,无法清空语音数据
        1029 => '加入会议错误', //单人会议及采访模式不允许加入
        1030 => '会议未汇总完毕，无法获取结果', //会议未汇总完毕，无法获取结果
        1031 => '无法获取会议信息', //数据库和缓存均无法获取会议信息
        1032 => 'fuser格式错误', //fuser必须是字符串（如果有）
        2999 => '存储容量达到上限', //单个uid的语音数据存储数量达到上线
        3001 => '会议绑定错误', //绑定数据存入redis出错
        3002 => '创建会议失败', //生成二维码或二维码上传到存储失败
        3003 => '创建会议失败',//会议信息存入redis失败
        3004 => 'mid无效', //查询存储失败或存储中没有查到匹配的会议信息
        3005 => '加入会议失败', //会议信息存入redis失败
        3006 => '用户语音数据保存失败', //语音数据保存失败
        3007 => '用户心跳数据保存失败', //用户轮询心跳数据保存失败
        5004 => 'mid错误', //会议id不存在或者不合法(为空等),pc端用
        5102 => 'pcid错误', //参数中没有pcid或pcid长度错误
        5103 => 'pcid无效', //数据库中未发现该pcid (pc数次收到该返回后应该刷新当前页面重新获取PCID并提示用户重新扫码)
        5104 => '没有有效的绑定会议', //没有会议绑定信息或者绑定的会议id长度错误
        5030 => '会议未汇总完毕，无法获取结果', //会议未汇总完毕，无法获取结果 pc端用
        5031 => '无法获取会议信息', //数据库和缓存均无法获取会议信息 pc端用
        8001 => 'pcid创建失败', //创建pcid错误
        8004 => 'mid无效', //查询存储失败或存储中没有查到匹配的会议信息 pc端用
    );
    
    private $redis ;
    
    //会议人数上限
    private $member_limit;
     
    //pc_info_redis_perfix     redis保存pc端信息的前缀 ,用来尽可能避免key重复
    private $pirp;
    
    //pcid_redis_ttl
    private $prtl ; //redis中的过期时间 => 30天
    
    //minfo_redis_perfix
    private $mip; 
      
    function __construct() {
        $this->redis = GFunc::getCacheInstance();
        $mconfs = GFunc::getConf('Meeting');
        $this->member_limit = $mconfs['member_limit'];
        $this->prtl = $mconfs['pcid_redis_ttl'];
        $this->pirp = $mconfs['pc_info_redis_perfix'];
        $this->mip = $mconfs['minfo_redis_perfix'];
    }
    
    /**
     * 客户端用创建会议接口
     * @route({"GET", "/create"})
     * @return array
     */
    function createMeeting() {
        
        //验证客户端参数    
        $data = $this->checkClientParam();
         
        if (!isset($data['uid']) || empty($data['uid']) ) {
            $this->showErr(1003);     
        }
        //会议类型 0：单人 1:多人 2:采访（伪多人）
        $mtype = in_array(intval($data['mtype']), array(0,1,2)) ? intval($data['mtype']) : 0;
         
        $nname = isset($data['nname']) && !empty($data['nname']) ? $data['nname'] : mb_substr($data['uid'], 0 ,6 ,'utf-8');
        
        $meetingModel = IoCload("models\\MeetingModel");
        
        //单人会议和采访模式不要二维码
        $need_qrcode = ($mtype == 0 || $mtype == 2) ? false : true ;
        
        //初始化会议信息
        $mid_info = $meetingModel->createMid($need_qrcode);
         
        if ($mid_info != false) {
            $mname = isset($data['mname']) && !empty($data['mname']) ? trim($data['mname']) : 
            $nname .'的会议'; 
            
            $member = array(
                    $data['uid'] => array(
                        'uid' =>  $data['uid'], 
                        'nname' => $nname , 
                        'status' => 1, //1表示正在参会， 0表示已退出会议
                        'jtm' => Util::getMtime(), //加入时间
                    )
                );
            $db_data = array(
                'mid' => $mid_info['mid'],
                'mname' => $mname,
                'mtype' => $mtype,
                'mstatus' => 1,
                'qrcode' => $mid_info['qrcode'],
                'creator' => $data['uid'],
                'create_tm' => Util::getMtime(),
                'member' => json_encode($member),
            );   
            
            //先更新心跳，防止定时结束脚本把刚创建的会议结束掉
            $meetingModel->saveUhh($mid_info['mid'], $data['uid']);
           
            //会议数据入库(mysql)
            $result = $meetingModel->insertMinfoToDb($db_data);
            if (!$result) {
                $this->showErr(1010);    
            }
            
            //会议数据存入redis
            $redis_minfo =  array(
                'mid' => $mid_info['mid'],
                'mname' => $mname,
                'mtype' => $mtype,
                'qrcode' => $mid_info['qrcode'],
                'create_tm' => $db_data['create_tm'],
                'creator' => $data['uid'],
                'mstatus' => 1,
                'member' => $member
            );
            
            $minfo_key = $this->mip .$mid_info['mid'];
            if ($this->redis->set($minfo_key, $redis_minfo, $this->prtl) == false) {
                $this->showErr(3003);    
            }
            
            //创建会议接口多返回一个加入会议二维码明文地址字段，方便会议创建者再退出会议后从列表页再次加入会议
            $redis_minfo['qrcode_text'] = !empty($mid_info['qrcode_text']) ? bd_B64_decode($mid_info['qrcode_text'], 0) : '';
            
            $this->result($redis_minfo);
            
            
        } else {
            $this->showErr(3002);
        }
         
    }
    
    /**
     * 客户端用加入会议接口
     * @route({"GET", "/join"})
     * @return array
     */
    function joinMeeting() {
         //验证客户端参数    
        $data = $this->checkClientParam();
         
        if (!isset($_GET['mid']) || empty($_GET['mid'])) {
            $this->showErr(1004);    
        }
         
        if (!isset($data['uid']) || empty($data['uid']) ) {
            $this->showErr(1003);     
        }
         
        $nname = isset($data['nname']) && !empty($data['nname']) ? $data['nname'] : mb_substr($data['uid'], 0 ,6 ,'utf-8');
        
        $minfo_key = $this->mip .$_GET['mid'];
        
        $meetingModel = IoCload("models\\MeetingModel");
        $redis_minfo = $meetingModel->getMinfoRAD($_GET['mid']);
        if ($redis_minfo == false || $redis_minfo == null) {
            $this->showErr(3004);    
        }
        
        if (!isset($redis_minfo['mtype']) || $redis_minfo['mtype'] == 0 || $redis_minfo['mtype'] == 2) {
            $this->showErr(1029);    
        }
        
        //已经加入了就不要继续执行后面的业务,防止重复加入及提升效率
        if ($redis_minfo['member'][$data['uid']]['status'] == 1) {
            //更新心跳
            $meetingModel->saveUhh($_GET['mid'], $data['uid']);
            $this->result($redis_minfo);    
        }
        
        //结束和已汇总的多人会议不允许加入
        if ($redis_minfo['mstatus'] == 0  || $redis_minfo['mstatus'] == 2 ) {
            $this->showErr(1014);     
        }
        
        //检测正在参会人数是否超过上限   
        $member_couter = 0;        
        foreach($redis_minfo['member'] as $k => $v) {
            if ($v['status'] == 1) {
                $member_couter++;    
            }
        } 
        //参会人员超过限制
        if ( $member_couter >= $this->member_limit) {
            $this->showErr(1023);
        } 
        //加入时间
        $jtm = isset($redis_minfo['member'][$data['uid']]['jtm'])  ?  $redis_minfo['member'][$data['uid']]['jtm'] : Util::getMtime();      
        
        $redis_minfo['member'][$data['uid']] =  array(
                    'uid' =>  $data['uid'], 
                    'nname' => $nname , 
                    'status' => 1, //1表示正在参会， 0表示已退出会议
                    'jtm' => $jtm,
        ); 
        
        if ($this->redis->set($minfo_key, $redis_minfo, $this->prtl) == false) {
            $this->showErr(3005);    
        } else {
            //更新数据库
            $meetingModel->updateMinfoToDbFromReids($redis_minfo);
        }
        //更新心跳
        $meetingModel->saveUhh($_GET['mid'], $data['uid']);
        $this->result($redis_minfo);
         
    }
    
    
    /**
     * 客户端用退出会议接口
     * @route({"GET", "/quit"})
     * @return array
     */
    function quitMeeting() {
        
         //验证客户端参数    
        $data = $this->checkClientParam();
         
        if (!isset($data['mid']) || empty($data['mid'])) {
            $this->showErr(1004);    
        }
         
        if (!isset($data['uid']) || empty($data['uid']) ) {
            $this->showErr(1003);     
        }
        
        $minfo_key = $this->mip .$data['mid'];
        
        //如果所有人都已退出，则调用会议结束处理函数
        $meetingModel = IoCload("models\\MeetingModel");
         
        $nname = isset($data['nname']) && !empty($data['nname']) ? $data['nname'] : mb_substr($data['uid'], 0 ,6 ,'utf-8');
        
        $redis_minfo = $meetingModel->getMinfoRAD($data['mid']);
        if ($redis_minfo == false || $redis_minfo == null) {
            $this->showErr(3004);    
        }
        
        //单人会议或采访模式的退出仅仅用来清楚缓存中的语音数据，以便下次重录时的全量数据能全部上传（防止客户端本地数据的修改，造成的老数据无法更新） 
        if ($redis_minfo['mtype'] == 0 || $redis_minfo['mtype'] == 2) {
            //是否是单人会的创建者
            if (isset($redis_minfo['member'][$data['uid']])) {
                //删除缓存，语音数据和声纹数据是不过期的缓存，必须要有删除策略以节省空间
                $meetingModel->cleanMeetingCache($redis_minfo);
                $this->result($redis_minfo);        
            } else {
                $this->showErr(1028);    
            } 
            
        } else {
           //已经结束的多人会议，或者用户已经退出了就不要继续执行后面的业务,防止重复退出及提升效率
            if ($redis_minfo['mstatus'] == 0 || $redis_minfo['mstatus'] == 2 ) {
                $this->result($redis_minfo);    
            }
            
            $old_data = $redis_minfo;
            $is_changed = false; //标记修改
            if (isset($redis_minfo['member'][$data['uid']]) && $redis_minfo['member'][$data['uid']]['status'] != 0) {
                $jtm = isset($redis_minfo['member'][$data['uid']]['jtm'])  ?  $redis_minfo['member'][$data['uid']]['jtm'] :  9000000000000;
                $redis_minfo['member'][$data['uid']] =  array(
                        'uid' =>  $data['uid'], 
                        'nname' => $nname , 
                        'status' => 0, //1表示正在参会， 0表示已退出会议
                        'jtm' => $jtm,
                );    
                //清除心跳
                $meetingModel->delUhh($data['mid'], $data['uid']);
                $is_changed = true; //标记修改
            }
             
            //检查是否所有成员都已退出
            $all_quit = true;
            foreach($redis_minfo['member'] as $k => $v) {
                if ($v['status'] == 1) {
                    $all_quit = false;
                    break;   
                } 
            }
            
             
            if ($all_quit) {
                $em_result = $meetingModel->endMeeting($data['mid']);
                //如果结束会议成功, 则异步发送会议汇总任务
                if ($em_result) {
                    $async = IoCload("asyncserv\\BaseAsync");
                    //发送异步任务进行结束会议任务，以便接口可以快速返回 (不稳定就改用同步服务执行方式)
                    //$meetingModel->sumMeeting($data['mid']);
                    $async->publish('Meeting', 'asyncSumMeeting',array('mid' => $data['mid']));
                }
                $redis_minfo['mstatus'] = 0;
                
            } else {
                if ($is_changed) {
                    if ($this->redis->set($minfo_key, $redis_minfo, $this->prtl) == false) {
                        $this->showErr(3005);    
                    } else {
                        $meetingModel = IoCload("models\\MeetingModel");
                        //更新数据库
                        $meetingModel->updateMinfoToDbFromReids($redis_minfo);
                    }
                    
                }     
            }     
            
        }
         
        $this->result($redis_minfo);
         
    }
    
    
    /**
     * 客户端用查询会议状态接口
     * @route({"GET", "/status"})
     * @return array
     */
    function statusMeeting() {
                    
        //验证客户端参数    
        $data = $this->checkClientParam();
         
        if (!isset($data['mids']) || empty($data['mids'])) {
            $this->showErr(1016);    
        }
        
        if (!isset($data['uid']) || empty($data['uid'])) {
            $this->showErr(1003);    
        }
         
        $inputs = array();
        $mids_ary = explode(',',$data['mids']);
        foreach($mids_ary as $k => $mid) {
            if (!empty($mid)) {
                $inputs[] = array('key' =>$this->mip  .$mid);     
            }
        }
        
        $redis_result = $this->redis->multiget($inputs);
        $select_mids = array();
        $result = array();
        if (is_array($redis_result)) {
            foreach($redis_result as $k => $v) {
                $rk = substr($k,6);
                if ($v == false) {
                    //缓存里没有就标记一下去mysql查找
                    array_push($select_mids, $rk);
                } else {
                    $result[$rk] = $v; 
                    //unset($result[$rk]['member']); //减少数据传输
                }
            }    
        } else {
             //缓存里一个都查不到，直接返回false就会进入这里
            foreach($mids_ary as $k => $mid) {
                if (!empty($mid)) {
                    array_push($select_mids, $mid);
                }
            }
        }
        
      
        if (!empty($select_mids)) {
            $meetingModel = IoCload("models\\MeetingModel");
            $minfo = $meetingModel->getMinfosFromDb(join(',',$select_mids));
            
            if (is_array($minfo)) {
                foreach($minfo as $k => $v) {
                    $v['mtype'] = (int)$v['mtype'];
                    $v['mstatus'] = (int)$v['mstatus'];
                    $v['create_tm'] = (int)$v['create_tm'];
                    $result[$v['mid']] = $v;
                    
                }
                  
            }      
        }
        
        //异常会议数据结构
        $err_meeting =  array(
            "mid" => "",
            "mname" => "",
            "mtype" => 0,
            "qrcode" => "",
            "create_tm" => 0,
            "creator" => "",
            "mstatus" => 9
        ); 
        
        //轮询客户端请求的会议ID，做占位及权限检查
        foreach($mids_ary as $k => $mid) {
            $err_meeting['mid'] = $mid; 
            //如果没有查到会议，则标记为异常会议
            if (!isset($result[$mid])) {
                $result[$mid] = $err_meeting;
            } else {
                //如果找到会议，要判断用户是否曾经参加过该会议，没参加过为非法检查
                if (!isset($result[$mid]['member'][$data['uid']])) {
                    $result[$mid] = $err_meeting;
                }    
            }
            unset($result[$mid]['member']);
        }
        
        sort($result); 
        $final = array('minfos' => $result);
         
        $this->result($final);      
    }    
    
    /**
     * PC端用获取PCID和二维码
     * @route({"GET", "/osdcode"})
     * @return array
     */
    function getOsdcode() {
         
        $meetingModel = IoCload("models\\MeetingModel");
         //获取pc信息
        $mid_info = $meetingModel->createPCid();
         
        if ($mid_info != false && isset($mid_info['pcid'])) {
            $key = $this->pirp . $mid_info['pcid'];
            $this->redis->set($key, $mid_info, $this->prtl); 
            unset($mid_info['boskey']);
            $this->result($mid_info, false);  
        }
         
        $this->showErr(8001);
    }
    
    /**
     * 客户端扫码PC端二维码绑定会议接口
     * @route({"GET", "/pcbind"})
     * @return array
     */
    function pcBind() {
        $data = $this->checkClientParam();
         
        if (!isset($_GET['pcid']) || strlen($_GET['pcid']) != 32 ) {
            $this->showErr(1005);     
        }
         
        if (!isset($data['mid']) || empty($data['mid']) ) {
            $this->showErr(1004);     
        }
         
        if (!isset($data['uid']) || empty($data['uid']) ) {
            $this->showErr(1003);     
        }
         
        $key = $this->pirp . $_GET['pcid'];
        $pc_info = $this->redis->get($key);
         
        if ($pc_info === null || $pc_info === false) {
            $this->showErr(1006);      
        }
         
        if (isset($pc_info['mid'])) {
            $this->showErr(1007);    
        }    
        $pc_info['mid'] = $data['mid'];  
        $result = $this->redis->set($key, $pc_info, $this->prtl); 
        if ($result != null && $result != false) {
            $this->result();   
        }
         
        $this->showErr(3001);  
          
    }
    
    /**
     * PC端同屏绑定检测接口
     * @route({"GET", "/bindchk"})
     * @return array
     */
    function bindCheck() {
        //检测pcid
        if (!isset($_GET['pcid']) || strlen($_GET['pcid']) != 32) {
            $this->showErr(5102);    
        }
        $key = $this->pirp . $_GET['pcid'];
        $data = $this->redis->get($key);
        
        if ($data === null || $data === false) {
            $this->showErr(5103);      
        }
         
        if (!isset($data['mid']) || strlen($data['mid']) != 32 ) {
            $this->showErr(5104);    
        }         
         
        
        if (!empty($data['boskey'])) {
            //成功返回有删除pc端的pcID图片
            $meetingModel = IoCload("models\\MeetingModel");
            //删除pcid图片资源
            $meetingModel->DelBosFile($data['boskey']);        
        }
        
        unset($data['qrcode']);
        unset($data['boskey']);
        $this->result($data, false); 
    }
    
    /**
     * 客户端数据轮询接口
     * @route({"POST", "/sync"})
     * @return array
     */
    function sync() {
        //验证客户端参数    
        $data = $this->checkClientParam(true);
         
        if (!isset($data['mid']) || empty($data['mid'])) {
            $this->showErr(1004);    
        }
         
        if (!isset($data['uid']) || empty($data['uid']) ) {
            $this->showErr(1003);     
        }
        //可以是空字符串，但一定要传
        if (!isset($data['mname'])) {
            $this->showErr(1015);     
        }
        
        //获取其他返回数据
        $meetingModel = IoCload("models\\MeetingModel");
        $redis_minfo = $meetingModel->getMinfoRAD($data['mid']);
        if ($redis_minfo == false || $redis_minfo == null) {
            $this->showErr(3004);    
        }
        
        $minfo_key = $this->mip .$data['mid'];
        
        //是否需要更新缓存内容
        $is_changed = false;     
        //更新会议名称 只有当客户端有传会议名称，且名称与服务端保存不一致时，才去修改。    
        if (trim($data['mname']) != '' && $redis_minfo['mname'] !== $data['mname']) {
            if ($redis_minfo['mstatus'] == 1) {
                $redis_minfo['mname'] = $data['mname'];   
                $is_changed = true; 
            } else {
                //会议不在进行中，无法更新会议数据数据 
                $this->showErr(1027);  
            }
        }
         
        if (!isset($redis_minfo['member'][$data['uid']])) {
            $this->showErr(1026);    
        } 

        if ($redis_minfo['member'][$data['uid']]['nname'] !== $data['nname']) {
            if ($redis_minfo['mstatus'] == 1) {
                //更新成员昵称信息 
                $redis_minfo['member'][$data['uid']]['nname'] =  $data['nname'];
                $is_changed = true;
            } else {
                //会议不在进行中，无法更新会议数据数据 
                $this->showErr(1027);  
            }
        }
         //不一样的内容才去存，尽可能防止脏写
        if ($is_changed) {
            if ($this->redis->set($minfo_key, $redis_minfo, $this->prtl) == false) {
                $this->showErr(3005);    
            }
        }
        
        if ($redis_minfo['mstatus'] == 1 && $redis_minfo['mtype'] == 1) {
            //多人会议的语音数据同步才记录心跳
            $uhh_result = $meetingModel->saveUhh($data['mid'], $data['uid']);
            if ($uhh_result == false || $uhh_result == null) {
                //$this->showErr(3007);         
            }    
        }
       
        //保存用户数据
        if (isset($data['contents']) && !empty($data['contents'])) {
            //只有会议进行中才能存储数据
            if ($redis_minfo['mstatus'] == 1) {
            
                //保存数据
                $result = $meetingModel->saveUdata($data['mid'], $data['uid'], $data['contents']); 
                if ($result === -1) {
                    //数据量超过上限
                    $this->showErr(2999); 
                }
                    
                if ($result === false) {
                    $this->showErr(3006);     
                } 
                
                //保存声纹更新数据
                if (isset($data['vu_contents']) && is_array($data['vu_contents']) && !empty($data['vu_contents'])) {
                    $result = $meetingModel->saveVudata($data['mid'], $data['uid'], $data['vu_contents']);  
                    if ($result === -1) {
                        //数据量超过上限
                        $this->showErr(2999); 
                    }
                    
                    if ($result === false) {
                        //更新数据错误不报强制错误，可以接受一定的丢失
                        //$this->showErr(3006);     
                    }  
                }
                 
            } else {
               //会议不在进行中，无法保存用户数据 
               $this->showErr(1020);  
            }   
             
        }
        
        if ($redis_minfo['mstatus'] == 1 || $redis_minfo['mstatus'] == 0) {
            $detm = 0; //获取数据结束时间点
            
            $talk_data = array();
            
            if (!empty($data['fuser'])) {
                //获取其他返回数据
                $talk_data = $meetingModel->getUData($data['mid'], $data, $redis_minfo, $detm);
                if (!is_array($talk_data) && is_numeric($talk_data)) {
                    $this->showErr($talk_data);
                }    
            }
            
            
            $result_data =  array(
                'detm' => $detm,
                'minfo' => $redis_minfo,
                'contents' => $talk_data
            );
             
            $this->result($result_data);       
        } else {
            //会议已汇总，不可轮询
            $this->showErr(1021); 
        }
         
    }
    
    
    
    /**
     * PC端数据轮询接口
     * @route({"GET", "/pcsync"})
     * @return array
     */
    function pcsync() {
        
        $data = $_GET;
        if (!isset($data['mid']) || empty($data['mid'])) {
            $this->showErr(5004);    
        }
        //获取其他返回数据
        $meetingModel = IoCload("models\\MeetingModel");
        $redis_minfo = $meetingModel->getMinfoRAD($data['mid']);
        if ($redis_minfo == false || $redis_minfo == null) {
            $this->showErr(8004);    
        }
        
        //if ($redis_minfo['mstatus'] == 0 || $redis_minfo['mstatus'] == 2) {
            //这里不用中断错误
            //$this->showErr(1025); //会议已结束或已汇总    
        //}
        
        $detm = 0; //获取数据结束时间点
        
        $talk_data = $meetingModel->getUData($data['mid'], $data, $redis_minfo, $detm);
        if (!is_array($talk_data) && is_numeric($talk_data)) {
            $this->showErr($talk_data);
        }
        
        
        $result_data =  array(
            'detm' => $detm,
            'minfo' => $redis_minfo,
            'contents' => $talk_data,
        );
        
        //只有采访模式，或者多人会议 (多人会议创建时始终都是声纹模式, 但声纹更新数据会隔一段时间才返回) 才是声纹模式，才获取声纹更新数据
        if ($redis_minfo['mtype'] == 2 || $redis_minfo['mtype'] == 1  ) {
            $vu_data = $meetingModel->getVuData($data['mid'], $data, $redis_minfo, $detm);
            if (!is_array($vu_data) && is_numeric($vu_data)) {
                $this->showErr($vu_data);
            }
            $result_data['vu_contents'] = $vu_data;    
        }
        
         
         
        $this->result($result_data, false);    
    }
    
    
    
    /**
     * 会议汇总结果获取接口
     * @route({"GET", "/summary"})
     * @return array
     */
    function summary() {
        //验证客户端参数    
        $data = $this->checkClientParam();
         
        if (!isset($data['mid']) || empty($data['mid'])) {
            $this->showErr(1004);    
        }
        
        if (!isset($data['uid']) || empty($data['uid'])) {
            $this->showErr(1003);    
        }
        
        $key = 'sumMinfo_'. $data['mid'];
        $cache_data = $this->redis->zget($key);
        
        if (!$cache_data) {
            //从数据库获取数据
            $meetingModel = IoCload("models\\MeetingModel");
            $cache_data = $meetingModel->getMinfoByMid($data['mid']); 
            if (empty($cache_data) ||  $cache_data == null || $cache_data == false) {
                //设置空内容放入缓存，防止过多查询
                $cache_data = array();    
            }  
            unset($cache_data['id']);
            unset($cache_data['hlock']);
            
            //缓存5分钟
            $this->redis->zset($key, $cache_data, 5);
            
        } 
        
        if (empty($cache_data)) {
           $this->showErr(1031); 
        } elseif ( $cache_data['mstatus'] == 2) {
            $this->result($cache_data);
        } else {
            $this->showErr(1030);    
        }
        
    }
    
    /**
     * pc端用会议汇总结果获取接口
     * @route({"GET", "/pcsummary"})
     * @return array
     */
    function pcsummary() {
        
        //验证客户端参数    
        $data = $_GET;
         
        if (!isset($data['mid']) || empty($data['mid'])) {
            $this->showErr(5004);    
        }
        
        $key = 'sumMinfo_'. $data['mid'];
        $cache_data = $this->redis->zget($key);
        
        if (!$cache_data) {
            //从数据库获取数据
            $meetingModel = IoCload("models\\MeetingModel");
            $cache_data = $meetingModel->getMinfoByMid($data['mid']); 
            if (empty($cache_data) ||  $cache_data == null || $cache_data == false) {
                //设置空内容放入缓存，防止过多查询
                $cache_data = array();    
            }  
            unset($cache_data['id']);
            unset($cache_data['hlock']);
            
            //缓存1分钟
            $this->redis->zset($key, $cache_data, 60);
            
        } 
        if (empty($cache_data)) {
           $this->showErr(5031); 
        } elseif ( $cache_data['mstatus'] == 2) {
            $meetingModel = IoCload("models\\MeetingModel");
            $summary_data = $meetingModel->getSummaryContents($cache_data['meeting_file']);
            $result = array();
            if (isset($summary_data['member'])) {
                $result['member'] = $summary_data['member'];
            }
            
            if (isset($summary_data['contents'])) {
                $result['contents'] = $summary_data['contents'];
            }
            
            if (isset($summary_data['summary'])) {
                $result['summary'] = $summary_data['summary'];
            }
        
            $this->result($result, false);
        } else {
            $this->showErr(5030);    
        }
        
    }
    
    
    
    /**
    * 检查客户端参数
    * @param $is_post 提交的参数是get还是post
    * @return array 解析出的data内容 或者直接输出错误信息
    */
    private function checkClientParam($is_post = false) {
        $param = $is_post ? $_POST : $_GET;
        
        //data参数是否存在
        if (!isset($param['data'])) {
            $this->showErr(1001);
        }
        
        //解析
        $data = json_decode(bd_B64_decode($param['data'], 0), true);
        if (!is_array($data)) {
            $this->showErr(1002);       
        }
        
        return $data;
    }
    
    /**
    * 返回错误结果
    * @param $data 要返回的数据
    * @param $is_b64 是否要b64_encode加密
    * @return
    */
    private function showErr($err_code, $err_msg = '') {
        if ($err_msg === '') {
            $err_msg = isset($this->err_list[$err_code])  ? $this->err_list[$err_code] : '';     
        }
    
        $result = json_encode(array("status" => $err_code, "msg" => $err_msg ));
        $rt = $err_code < 5000 ? bd_B64_encode($result, 0) : (isset($_GET['callback']) ? $_GET['callback']. '('.$result.')' : $result);  
        echo $rt;
        exit;
    }
    
    /**
    * 返回结果
    * @param $data 要返回的数据
    * @param $is_b64 是否要b64_encode加密
    * @return
    */
    private function result($data = array(), $is_b64 = true) {
        $data['stm'] = Util::getMtime(); //服务端校准时间戳
        if (isset($data['member']) && is_array($data['member'])) {
            sort($data['member']);
        }
        
        if (isset($data['minfo']['member']) && is_array($data['minfo']['member'])) {
            sort($data['minfo']['member']);
        }
        
        $result = array(
            "status" => 0,
            "msg" => '',
            "data" => $data
        );
        $result = json_encode($result);
        //pc端使用jsonp
        echo $is_b64 ? bd_B64_encode($result, 0) : (isset($_GET['callback']) ? $_GET['callback']. '('.$result.')' : $result) ;
        exit;
    }
    
  
    
}
