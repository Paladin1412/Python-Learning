<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use \tinyESB\util\Verify;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\Util;
use utils\GFunc;
use utils\HpipLib;


define('COLOMBO_IPLIB_FILE', dirname(dirname(__FILE__)) .'/colombo_iplib.txt');

/**
 *
 * 高精度ip脚本监控工具
 *
 * @author zhoubin05
 * @path("/hpiptest/")
 */
class HpipTest
{
     private $cache_key = 'rate_of_progress_for_save_hpioinfo_to_redis'; //用来标记使用高精度定位API已经跑到了哥伦布库的哪条记录, 各机器都是以单行哥伦布IP段数据为最小单元运行逻辑
     private $info_cache_key = 'rate_of_progress_for_save_hpioinfo_to_redis_info'; //用来标记周期开始时间以及当前已经执行的ip段个数
    
     /**
     * @route({"GET","/init"})
     * 初始化高精度ip redis数据（） 
     *   
     * 不要重复执行！！！会影响脚本执行进度。  除非想重头开始刷新高精度 redis ip库，可以执行一次。
     * 
     * @return
     */
    public function hpipInit () {
        $pwd = $_GET['pwd'];
        
        if(md5($pwd) == md5('watcher@@_' . date('Ymd'))) {
            if(GFunc::cacheZset($this->cache_key, '0.0.0.0|0.255.255.255|ZZ|None|None|None|None|100|0|0|0|0', 0, false)) {
                if(GFunc::cacheZset($this->info_cache_key, array('date' => date('Y-m-d H:i:s'),'total' => 0 ), 0, false)) {
                    //不过期的缓存
                    echo   '[初始化成功,请立即退出，勿重复执行]'.PHP_EOL;
                } else {
                    echo   '[执行失败(info写入失败)，请再试]'.PHP_EOL;
                }
            } else {
                echo   '[执行失败(标记写入失败)，请再试]'.PHP_EOL;
            }
        } else {
            echo '密码错误';
        }
        
        
        
        
    }
    
    
     /**
     * @route({"GET","/look"})
     * 查看运行情况
     * @return
     */
    public function look () {
        //获取当前哥伦布ip的有效IP段总数
        $fp = fopen(COLOMBO_IPLIB_FILE, 'rb');
        $l = 1;
        if ($fp) {
            $bf =  false;
            while (!feof($fp)) {
                if( strpos(fgets($fp), 'None|None|None|None') == false) {
                    $l++;
                } 
            }
        
        }
        fclose($fp);
        
        echo "当前哥伦布库有效IP段总数为{$l}行" .PHP_EOL;
        
        //读取标记未
        $result = GFunc::cacheZget($this->cache_key, false);
        if($result) {
            echo "当前已处理至IP段[{$result}]" . PHP_EOL;
        }
        
        $result = GFunc::cacheZget($this->info_cache_key, false);
        if($result) {
            
            echo "当期处理开始于{$result['date']}" . PHP_EOL;
            echo "当期已处理ip段数量为{$result['total']}行" . PHP_EOL;
        }
    }
    
    /**
     * @route({"GET","/getinfo"})
     * 根据IP获取redis中信息
     * @return
     */
    public function get() {
        $hpip = new HpipLib();
        $data = $hpip->getFromRedis();
        if($data) {
            echo json_encode($data);
        } else {
            echo '获取失败';
        }
    }
   
    /**
     * @route({"GET","/getinfolib"})
     * 根据IP获取标准数据信息
     * @return
     */
    public function getFromLib() {
        $hpip = new HpipLib();
        $data = $hpip->getData();
        if($data) {
            echo json_encode($data);
        } else {
            echo '获取失败';
        }
    }
    
    /**
     * @route({"GET","/getinfoapi"})
     * 根据IP获取api数据信息
     * @return
     */
    public function getFromApi() {
        $hpip = new HpipLib();
        $data = $hpip->loadFromApi('', null, true);
        if($data) {
            echo json_encode($data);
        } else {
            echo '获取失败';
        }
    }
}
    