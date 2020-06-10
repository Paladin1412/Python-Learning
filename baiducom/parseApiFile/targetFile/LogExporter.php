<?php
/**
 *
 * @desc 用户广告行为存储至redis
 * @path("/log/")
 */
use utils\LogDispatcher;
use tinyESB\util\ClassLoader;
ClassLoader::addInclude(__DIR__.'/utils');

class LogExporter
{

    public  function  _construct()
    {

    }

    /**
     * @desc
     * @route({"GET", "/export"})
     * @return({"body"}){
    "ret" => ""
    }
     */
    public function Export(){

        $arr =  array();
        $dispatcher = LogDispatcher::getInstance();
        $arr = $dispatcher->Fetch();
        $dispatcher->Delete();
        return  $arr;
    }



}