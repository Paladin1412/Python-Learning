<?php
/**
 *
 * @desc 国际化语言
 * @path("/gl/")
 */
use utils\Util;

class GlobalLanguage
{

    /**
     * @desc 国际化语言包列表，客户端进入语言选择设置时访问服务端
     * @route({"GET", "/list"})
     * @return({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return({"body"})
     {
    data: [
            {
                id: 2,
                name: "英文2",
                alias: "",
                language_code: "cn",
                version: "2",
                download_url: "http://mco.ime.shahe.baidu.com:8891/res/file/global_language/files/149319889364990.zip",
                package_size: "4.2"
            },
            {
                id: 5,
                name: "日文6",
                alias: "",
                language_code: "jp",
                version: "6",
                download_url: "http://mco.ime.shahe.baidu.com:8891/res/file/global_language/files/149319887445712.zip",
                package_size: "333"
            }
        ]
    }
     */
    public function glList()
    {
        $awlModel = IoCload("models\\GlobalLanguageModel");
        $res = array('data' => $awlModel->getList());

        return $res;
    }

    

}