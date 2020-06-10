<?php
/***************************************************************************
 *
* Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/

use tinyESB\util\exceptions\NotFound;
use tinyESB\util\exceptions\BadRequest;
use tinyESB\util\Logger;
use utils\KsarchRedis;
use models\TabBoutiqueLiteModel;

/**
 * lite版插件下载
 *
 * @author fanwenli
 * @path("/tabboutiquelite/")
 */
class TabBoutiqueLite
{	
        
    /**
	 *
	 * 详情获取
	 *
	 * @route({"GET", "/list"})
	 * @throws({"BadRequest", "status", "400 Bad request"})
	 * @throws({"tinyESB\\util\exceptions\\NotFound", "status", "404 Not Found"}) 没有找到节日，或者下线
	 * @return({"header", "Content-Type: application/json; charset=UTF-8"})
	 * @return({"body"}){
	 *      "file": "http://mco.ime.shahe.baidu.com:8891/res/file/lite-plugin-download/files/146960228415431.zip"
	 *      ]
	 * }
	 */
	public function getList(){
		$out = array();
		
		$TabBoutiqueLiteModel = IoCload('models\\TabBoutiqueLiteModel');
		
        $TabBoutiqueLite = $TabBoutiqueLiteModel->getTabBoutiqueLiteList();
        
        $FilterModel = Iocload("models\\TabBoutiqueLiteFilter");
        
        if(!empty($TabBoutiqueLite)){
        	foreach($TabBoutiqueLite as $key => $val){
        		//过滤不通过则进行下一条
				if(!$FilterModel->getFilter($val)) {
					continue;
				}
        		
        		//删除过滤条件
        		if(isset($val['filter_conditions'])){
        			unset($val['filter_conditions']);
        		}
        		
        		$out['tab_boutique_lite'][] = $val;
        	}
        }
        
        return $out;
	}
}
