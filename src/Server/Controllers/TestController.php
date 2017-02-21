<?php
namespace Server\Controllers;

use Server\CoreBase\Controller;
use Server\CoreBase\SelectCoroutine;
use Server\Models\TestModel;
use Server\Tasks\TestTask;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class TestController extends Controller
{
    /**
     * @var TestTask
     */
    public $testTask;

    /**
     * @var TestModel
     */
    public $testModel;

    /**
     * tcp的测试
     */
    public function tcp_testTcp()
    {
        $this->send($this->client_data->data);
    }

    /**
     * mysql 事务协程测试
     */
    public function mysql_begin_coroutine_test()
    {
        $id = yield $this->mysql_pool->coroutineBegin($this);
        $update_result = yield $this->mysql_pool->dbQueryBuilder->update('user_info')->set('sex', '0')->where('uid', 36)->coroutineSend($id);
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('user_info')->where('uid', 36)->coroutineSend($id);
        if ($result['result'][0]['channel'] == 888) {
            $this->http_output->end('commit');
            yield $this->mysql_pool->coroutineCommit($id);
        } else {
            $this->http_output->end('rollback');
            yield $this->mysql_pool->coroutineRollback($id);
        }
    }
    /**
     * 绑定uid
     */
    public function tcp_bind_uid()
    {
        $this->bindUid($this->fd, $this->client_data->data);
        $this->destroy();
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function tcp_efficiency_test()
    {
        $data = $this->client_data->data;
        $this->sendToUid(mt_rand(1, 100), $data);
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function tcp_efficiency_test2()
    {
        $data = $this->client_data->data;
        $this->send($data);
    }

    /**
     * mysql效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function tcp_mysql_efficiency()
    {
        yield $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', 10004)->coroutineSend();
        $this->send($this->client_data->data);
    }

    /**
     * 获取mysql语句
     */
    public function mysqlStatement()
    {
        $value = $this->mysql_pool->dbQueryBuilder->insertInto('account')->intoColumns(['uid', 'static'])->intoValues([[36, 0], [37, 0]])->getStatement(true);
        $this->http_output->end($value);
    }
    /**
     * http测试
     */
    public function test()
    {
        $this->http_output->end('helloworld', false);
    }

    /**
     * http redis 测试
     */
    public function redis()
    {
        $value = $this->redis_pool->getCoroutine()->get('test');
        yield $value;
        $value1 = $this->redis_pool->getCoroutine()->get('test1');
        yield $value1;
        $value2 = $this->redis_pool->getCoroutine()->get('test2');
        yield $value2;
        $value3 = $this->redis_pool->getCoroutine()->get('test3');
        yield $value3;
        $this->http_output->end(1, false);
    }

    /**
     * http 同步redis 测试
     */
    public function aredis()
    {
        $value = get_instance()->getRedis()->get('test');
        $value1 = get_instance()->getRedis()->get('test1');
        $value2 = get_instance()->getRedis()->get('test2');
        $value3 = get_instance()->getRedis()->get('test3');
        $this->http_output->end(1, false);
    }
    /**
     * html测试
     */
    public function html_test()
    {
        $template = $this->loader->view('server::error_404');
        $this->http_output->end($template->render(['controller' => 'TestController\html_test', 'message' => '页面不存在！']));
    }

    /**
     * html测试
     */
    public function html_file_test()
    {
        $this->http_output->endFile(SERVER_DIR, 'Views/test.html');
    }


    /**
     * 协程的httpclient测试
     */
    public function test_httpClient()
    {
        $httpClient = yield $this->client->coroutineGetHttpClient('http://localhost:8081');
        $result = yield $httpClient->coroutineGet("/TestController/test_request", ['id' => 123]);
        $this->http_output->end($result);
    }

    /**
     * select方法测试
     * @return \Generator
     */
    public function test_select()
    {
        yield $this->redis_pool->getCoroutine()->set('test', 1);
        $c1 = $this->redis_pool->getCoroutine()->get('test');
        $c2 = $this->redis_pool->getCoroutine()->get('test1');
        $result = yield SelectCoroutine::Select(function ($result) {
            if ($result != null) {
                return true;
            }
            return false;
        }, $c2, $c1);
        $this->http_output->end($result);
    }

    public function startInterruptedTask()
    {
        $testTask = $this->loader->task('TestTask');
        $task_id = $testTask->testInterrupted();
        $testTask->startTask(null);
        $this->http_output->end("task_id = $task_id");
    }

    public function interruptedTask()
    {
        $task_id = $this->http_input->getPost('task_id');
        get_instance()->interruptedTask($task_id);
        $this->http_output->end("ok");
    }

    public function getAllTask()
    {
        $messages = get_instance()->getServerAllTaskMessage();
        $this->http_output->end(json_encode($messages));
    }
    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->is_destroy;
    }

}