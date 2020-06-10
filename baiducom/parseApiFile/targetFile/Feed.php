<?php
/**
 * 之前的热词8.7版本更新为下发feedurl
 * @path("/feed/")
 */
use utils\Util;

class Feed
{
    /**
     *
     * 下发feedurl
     * @route({"GET","/url"})
     */
    public function getFeedUrl()
    {

        $rs = Util::initialClass();

        $rs['data']->feedurl = "https://srf.baidu.com/cache/hw/";

        return Util::returnValue($rs, true);
    }
}