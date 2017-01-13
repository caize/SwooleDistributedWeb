<?php

namespace Server\CoreBase;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-29
 * Time: 上午11:22
 */
class HttpOutput
{
    /**
     * http response
     * @var \swoole_http_response
     */
    public $response;

    /**
     * http request
     * @var \swoole_http_request
     */
    public $request;
    /**
     * @var Controller
     */
    protected $controller;

    /**
     * HttpOutput constructor.
     * @param $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * 设置
     * @param $request
     * @param $response
     */
    public function set($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * 重置
     */
    public function reset()
    {
        unset($this->response);
        unset($this->request);
    }

    /**
     * Set HTTP Status Header
     *
     * @param    int    the status code
     * @param    string
     * @return HttpOutPut
     */
    public function setStatusHeader($code = 200)
    {
        $this->response->status($code);
        return $this;
    }

    /**
     * Set Content-Type Header
     *
     * @param    string $mime_type Extension of the file we're outputting
     * @return    HttpOutPut
     */
    public function setContentType($mime_type)
    {
        $this->setHeader('Content-Type', $mime_type);
        return $this;
    }

    /**
     * set_header
     * @param $key
     * @param $value
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->response->header($key, $value);
        return $this;
    }

    /**
     * 发送
     * @param string $output
     * @param bool $gzip
     * @param bool $destory
     */
    public function end($output = '', $gzip = true, $destory = true)
    {
        $output ?? '';  //屏蔽null
        
        $_data = ob_get_clean();
        $output = $_data. $output;
        //低版本swoole的gzip方法存在效率问题
        if ($gzip) {
            $this->response->gzip(1);
        }
        //压缩备用方案
        /*if ($gzip) {
            $this->response->header('Content-Encoding', 'gzip');
            $this->response->header('Vary', 'Accept-Encoding');
            $output = gzencode($output . " \n", 9);
        }*/
        $this->response->header('Pragma', 'no-cache');
        $this->response->header('Cache-Control', 'no-cache');
        $this->response->header('Expires', '-1');
        $this->response->end($output);
        if ($destory) {
            $this->controller->destroy();
        }
        return;
    }

    /**
     * 输出文件（会自动销毁）
     * @param $root_file
     * @param $file_name
     * @param bool $destory
     * @return mixed
     */
    public function endFile($root_file, $file_name, $destory = true)
    {
        $result = httpEndFile($root_file . '/' . $file_name, $this->request, $this->response);
        if ($destory) {
            $this->controller->destroy();
        }
        return $result;
    }
}