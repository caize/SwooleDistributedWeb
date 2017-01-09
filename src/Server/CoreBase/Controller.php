<?php
namespace Server\CoreBase;

use Server\SwooleMarco;
use Server\SwooleServer;
use Server\Cache\ICache;

/**
 * Controller 控制器
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 上午11:59
 */
class Controller extends CoreBase
{
    /**
     * @var \Server\DataBase\RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var \Server\DataBase\MysqlAsynPool
     */
    public $mysql_pool;
    /**
     * @var HttpInPut
     */
    public $http_input;
    /**
     * @var HttpOutPut
     */
    public $http_output;
    /**
     * 是否来自http的请求不是就是来自tcp
     * @var string
     */
    public $request_type;
    /**
     * @var \Server\Client\Client
     */
    public $client;
    /**
     * fd
     * @var int
     */
    protected $fd;
    /**
     * uid
     * @var int
     */
    protected $uid;
    /**
     * 用户数据
     * @var
     */
    protected $client_data;
    /**
     * http response
     * @var \swoole_http_request
     */
    protected $request;
    /**
     * http response
     * @var \swoole_http_response
     */
    protected $response;

    /**
     * 用于单元测试模拟捕获服务器发出的消息
     * @var array
     */
    protected $testUnitSendStack = [];
    /**
     * 缓存
     * @var ICache
     */
    protected $cache;
    /**
     * session handler
     * @var ICache
     */
    public $session_handler;
    /**
     * 控制器名
     * @var string
     */
    public $controller_name;
    /**
     * 方法名
     * @var string
     */
    public $method_name;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->http_input = new HttpInput();
        $this->http_output = new HttpOutput($this);
        $this->redis_pool = get_instance()->redis_pool;
        $this->mysql_pool = get_instance()->mysql_pool;
        $this->client = get_instance()->client;
        $this->cache = get_instance()->cache;
        $this->session_handler = get_instance()->session_handler;
    }

    /**
     * 设置客户端协议数据
     * @param $uid
     * @param $fd
     * @param $client_data
     */
    public function setClientData($uid, $fd, $client_data)
    {
        $this->uid = $uid;
        $this->fd = $fd;
        $this->client_data = $client_data;
        $this->request_type = SwooleMarco::TCP_REQUEST;
        $this->initialization();
    }

    /**
     * 初始化每次执行方法之前都会执行initialization
     * 初始化需要返回true/false，返回true表示代码继续往下执行，返回false表示中断执行，可能需要页面跳转
     */
    public function initialization()
    {
        return true;
    }

    /**
     * set http Request Response
     * @param $request
     * @param $response
     * @return bool 是否继续执行
     */
    public function setRequestResponse($request, $response, $controller_name, $method_name)
    {
        $this->request = $request;
        $this->response = $response;
        $this->http_input->set($request);
        $this->http_output->set($response);
        $this->request_type = SwooleMarco::HTTP_REQUEST;
        $this->controller_name = $controller_name;
        $this->method_name = $method_name;
        return $this->initialization();
    }

    /**
     * 异常的回调
     * @param \Exception $e
     */
    public function onExceptionHandle(\Exception $e)
    {
        switch ($this->request_type) {
            case SwooleMarco::HTTP_REQUEST:
                $this->http_output->end($e->getMessage());
                break;
            case SwooleMarco::TCP_REQUEST:
                $this->send($e->getMessage());
                break;
        }
    }

    /**
     * 向当前客户端发送消息
     * @param $data
     * @param $destory
     * @throws SwooleException
     */
    protected function send($data, $destory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destory can not send data');
        }
        $data = get_instance()->encode($this->pack->pack($data));
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'send', 'fd' => $this->fd, 'data' => $data];
        } else {
            get_instance()->send($this->fd, $data);
        }
        if ($destory) {
            $this->destroy();
        }
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
        unset($this->fd);
        unset($this->uid);
        unset($this->client_data);
        unset($this->request);
        unset($this->response);
        $this->http_input->reset();
        $this->http_output->reset();
        ControllerFactory::getInstance()->revertController($this);
    }

    /**
     * 获取单元测试捕获的数据
     * @return array
     */
    public function getTestUnitResult()
    {
        $stack = $this->testUnitSendStack;
        $this->testUnitSendStack = [];
        return $stack;
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     * @throws SwooleException
     */
    protected function sendToUid($uid, $data, $destory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destory can not send data');
        }
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToUid', 'uid' => $this->uid, 'data' => $data];
        } else {
            get_instance()->sendToUid($uid, $data);
        }
        if ($destory) {
            $this->destroy();
        }
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     * @param $destory
     * @throws SwooleException
     */
    protected function sendToUids($uids, $data, $destory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destory can not send data');
        }
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToUids', 'uids' => $uids, 'data' => $data];
        } else {
            get_instance()->sendToUids($uids, $data);
        }
        if ($destory) {
            $this->destroy();
        }
    }

    /**
     * sendToAll
     * @param $data
     * @param $destory
     * @throws SwooleException
     */
    protected function sendToAll($data, $destory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destory can not send data');
        }
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToAll', 'data' => $data];
        } else {
            get_instance()->sendToAll($data);
        }
        if ($destory) {
            $this->destroy();
        }
    }

    /**
     * 发送给群
     * @param $groupId
     * @param $data
     * @param bool $destory
     * @throws SwooleException
     */
    protected function sendToGroup($groupId, $data, $destory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destory can not send data');
        }
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToGroup', 'groupId' => $groupId, 'data' => $data];
        } else {
            get_instance()->sendToGroup($groupId, $data);
        }
        if ($destory) {
            $this->destroy();
        }
    }

    /**
     * 踢用户
     * @param $uid
     */
    protected function kickUid($uid)
    {
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'kickUid', 'uid' => $uid];
        } else {
            get_instance()->kickUid($uid);
        }
    }

    /**
     * bindUid
     * @param $fd
     * @param $uid
     * @param bool $isKick
     */
    protected function bindUid($fd, $uid, $isKick = true)
    {
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'bindUid', 'fd' => $fd, 'uid' => $uid];
        } else {
            get_instance()->bindUid($fd, $uid, $isKick);
        }
    }

    /**
     * unBindUid
     * @param $uid
     */
    protected function unBindUid($uid)
    {
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'unBindUid', 'uid' => $uid];
        } else {
            get_instance()->unBindUid($uid);
        }
    }

    /**
     * 断开链接
     * @param $fd
     * @param bool $autoDestory
     */
    protected function close($fd, $autoDestory = true)
    {
        if (SwooleServer::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'close', 'fd' => $fd];
        } else {
            get_instance()->close($fd);
        }
        if ($autoDestory) {
            $this->destroy();
        }
    }
}