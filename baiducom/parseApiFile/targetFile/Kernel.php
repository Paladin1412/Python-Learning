<?php
/**
 * 内核so文件更新
 * @desc
 * @path("/kernel/")
 */
class Kernel {

    /**
     * @route({"POST", "/upgrade"})
     * 
     * @param ({"version", "$._POST['version']"})
     * @return ({"header", "Content-Type: application/json; charset=UTF-8"})
     * @return ({"body"}) {
     *         "data": [
     *         {
     *         "id": 53,
     *         "version_code": 1,
     *         "file": 'sdsdds.zip'
     *         }
     *         ]
     *         }
     */
    public function upgrade($version = -1) {
        
        $objData = new stdClass();
        $res = array(
            'code' => 0, 
            'msg' => '', 
            'data' => $objData
        );
        
        $objKernelModel = IoCload("models\\KernelModel");
        $LastkernelInfo = $objKernelModel->getUpdate($version);
        $LastkernelInfo && $res['data'] = $LastkernelInfo;
        
        return $res;
    }
}

