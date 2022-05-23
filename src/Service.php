<?php
/*LICENSE
+-----------------------------------------------------------------------+
| SilangPHP Framework                                                   |
+-----------------------------------------------------------------------+
| This program is free software; you can redistribute it and/or modify  |
| it under the terms of the GNU General Public License as published by  |
| the Free Software Foundation. You should have received a copy of the  |
| GNU General Public License along with this program.  If not, see      |
| http://www.gnu.org/licenses/.                                         |
| Copyright (C) 2020. All Rights Reserved.                              |
+-----------------------------------------------------------------------+
| Supports: http://www.github.com/silangtech/SilangPHP                  |
+-----------------------------------------------------------------------+
*/
namespace SilangPHP;

class Service extends \Swoole\Server
{
    public $serv = null;
    public $worker_num = 2;
    public $user = 'www-data';
    public $group = 'www-data';
    // 测试的时候设为false
    public $daemonize = false;
    public $backlog = 128;
    // 异步耗时处理数
    public $task_worker_num = 4;
    public $tmp_path = '/tmp';
    public $pid_file;
    public $log_file;
    public $host = '0.0.0.0';
    public $port = 9501;
    public $processName = 'SilangPHP_server';

    public $service = [];

    public function __construct($serverName = '')
    {
        if(defined("PS_RUNTIME_PATH"))
        {
            $this->tmp_path = PS_RUNTIME_PATH;
        }else{
            $this->tmp_path = "/tmp/";
        }
        $this->pid_file = $this->tmp_path.'server'.$this->port.'.pid';
        $this->log_file = $this->tmp_path.'swoole'.$this->port.'.log';
        $this->processName .= $serverName;
    }

    /**
     * server配置
     */
    public function config()
    {
        parent::__construct($this->host, $this->port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->set([
            'worker_num' => $this->worker_num,
            'user' => $this->user,
            'group' => $this->group,
            'daemonize' => $this->daemonize,
            'backlog' => $this->backlog,
            'task_worker_num' => $this->task_worker_num,
            'pid_file' => $this->pid_file,
            'log_file' => $this->log_file
        ]);
    }

    /**
     * 设置好事件
     */
    public function event()
    {
        // 事件
        $this->on('Start', array($this, 'onStart'));
        if(method_exists($this, 'onShutdown')){
            $this->on('Shutdown', array($this, 'onShutdown'));
        }

        $this->on('WorkerStart', array($this, 'onWorkerStart'));
        if(method_exists($this, 'onWorkerError')){
            $this->on('WorkerError', array($this, 'onWorkerError'));
        }
        if(method_exists($this, 'onWorkerStop')){

            $this->on('WorkerStop', array($this, 'onWorkerStop'));
        }

        $this->on('ManagerStart', array($this, 'onManagerStart'));
        if(method_exists($this, 'onManagerStop')){
            $this->on('ManagerStop', array($this, 'onManagerStop'));
        }

        $this->on('Task', array($this, 'onTask'));
        if(method_exists($this, 'onFinish')){
            $this->on('Finish', array($this, 'onFinish'));
        }
        if(method_exists($this, 'onClose')){
            $this->on('Close', array($this, 'onClose'));
        }
        if(method_exists($this, 'onConnect')){
            $this->on('Connect', array($this, 'onConnect'));
        }
        $this->on('Receive', array($this, 'onReceive'));

    }

    /**
     * 开始运行
     */
    public function run()
    {
        $this->config();
        $this->event();
        $this->start();
        return true;
    }

    /**
     * 停止服务
     */
    public function stoppid()
    {
        // 获取pid,然后清空pid
        echo "[server_pid]".$this->pid_file.PHP_EOL;
        $pid = file_get_contents($this->pid_file);
        if($pid)
        {
            \Swoole\Process::kill((int)$pid, SIGTERM);
        }
        $pid = file_put_contents($this->pid_file, "");
        echo '停止成功'.PHP_EOL;
        return true;
    }

    /**
     * 重新启动服务
     */
    public function restartpid()
    {
        $this->stoppid();
        sleep(1);
        $this->run();
    }
    
    /**
     * 任务完成时
     * Warning: Swoole\Server::start(): require onFinish callback in /webwww/www/sdk/vendor/silangtech/silangphp/src/Service.php on line 115
     */
    public function onFinish(\Swoole\Server $serv, $task_id, $data){
        echo $task_id.'_task_finish'.PHP_EOL;
    } 

    /**
     * 开始进程
     * @param \Swoole\Server $server
     */
    public function onStart(\Swoole\Server $server)
    {
        swoole_set_process_name($this->processName);
    }

    public function onWorkerStart(\Swoole\Server $server, $worker_id)
    {
        if($worker_id >= $server->setting['worker_num']) {
            swoole_set_process_name($this->processName."_taskworker");
        } else {
            swoole_set_process_name($this->processName."_worker");
        }
    }

    public function onManagerStart(\Swoole\Server $serv)
    {
        swoole_set_process_name($this->processName."_manager");
    }

    public function inv($name, $service)
    {
        $this->service[$name] = $service;
    }

    /**
     * 接收客户端数据
     * @param \swoole_server $server
     * @param $fd
     * @param $reactor_id
     * @param $data
     */
    public function onReceive(\Swoole\Server $server, $fd, $reactor_id, $data)
    {
        // 根据信息，处理完之后返回
        // 获取到数据后的一个处理
        $data = json_decode($data,true);
        $service = $data['service'];
        $action = $data['action'];
        $param = $data['param'];
        if(isset($this->service[$service]))
        {
            $ser = $this->service[$service];
            $res = call_user_func_array([$ser,$action],$param);
            $server->send($fd, json_encode($res));
        }else{
            $server->send($fd, json_encode(['code'=>'10001', 'msg'=>'service error']));
        }
    }
}