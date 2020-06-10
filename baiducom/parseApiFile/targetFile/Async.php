<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
use asyncserv;

use utils\Util;
use utils\GFunc;

/**
 * 异步服务 发送，订阅，检索使用demo
 *
 * @author zhoubin05
 * @path("/async/")
 */
class Async
{   
      
    
    /**
     *
     * @route({"GET", "/publish"})
     * @return array
     */
    function publish(){
            
         $async = IoCload("asyncserv\\BaseAsync");
         //第一参数为类名，第二参数为类中的函数名，第三参数为数据，可以是数字，字符,布尔,或者array
         return $async->publish('Demo', 'dosome',array($_GET['data']));
    }
    
    /**
     *
     * @route({"GET", "/show"})
     * @return array
     */
    function showRedis(){
            
        $key = 'demo_for_asyncserv';
        $redis = GFunc::getCacheInstance();
        return $redis->get($key);
    }
    
    /**
     *
     * @route({"GET", "/get"})
     * @return array
     */
    function search(){
           
       $async = IoCload("asyncserv\\BaseAsync");
       return $async->info();
    }
    
    
    /**
     *
     * @route({"GET", "/fetch"})
     * @return array
     */
    public  function subscription(){
       $id = isset($_GET['id']) ? intval($_GET['id']) : -1;
       $batch = isset($_GET['batch']) ? intval($_GET['batch']) : 1;
       $async = IoCload("asyncserv\\BaseAsync");
       return $async->subscription($id, $batch);
    }
    
    
    /**
     *
     * @route({"GET", "/shell"})
     */
    public  function shellx(){
       /*
       $async = IoCload("asyncserv\\BaseAsync");
       $data = $async->subscription(200,10);
       return $async->run_fetch($data);
       */
    }
    
    
   
    
}
