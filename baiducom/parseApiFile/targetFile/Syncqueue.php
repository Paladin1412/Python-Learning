<?php
/***************************************************************************
 *
* Copyright (c) 2017 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

/**
 *
 * @desc 异步服务 接收端
 * @path("/syncqueue/")
 * @author zhoubin05 20171016
 */
use utils\Util;
use utils\GFunc;
use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use asyncserv;

class Syncqueue
{
    /**
     * 异步服务接受推送
     * @route({"POST", "/recv"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     */
    public function recv(){
        $async = IoCload("asyncserv\\BaseAsync");
        $async->run_queue();    
    }
}

?>