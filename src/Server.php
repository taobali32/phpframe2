<?php

namespace Jtar;

use Exception;
use Jtar\Event\Epoll;
use Jtar\Event\Event;
use Jtar\Event\Select;
use Jtar\Protocol\Stream;
use Jtar\Protocol\Text;

class Server
{
    const STATUS_SHUTDOWN = 3;
    const STATUS_RUNNING = 2;

    const STATUS_STARTING = 1;
    /**
     * @var mixed|string
     */
    public static $_startFile;
    public static string $_pidFile;
    public static string $_logFile;
    public static string $_os;
    public static $_status = 0;
    public $_mainSocket;
    public $_local_socket;

    public static $_connections = [];

    public $_events = [];

    public $_protocol = null;

    public $_protocol_layout;

    public $_usingProtocol;

    // 客户端连接数量
    static public $_clientNum = 0;
    //  执行recv/fread调用次数
    static public $_recvNum = 0;
    // 1秒钟接收了多少条消息
    static public $_msgNum = 0;

    static public $_eventLoop;

    public $_protocols = [
        'stream' => Stream::class,
        "text" => Text::class,
        "ws" => "",
        "http" => "",
        "mqtt" => ""
    ];

    public int $_startTime = 0;

    public $_setting = 0;
    public array $_pidMap = [];

    public function __construct($_local_socket = "tcp://0.0.0.0:12345")
    {
        list($protocol, $ip, $port) = explode(":", $_local_socket);

        if (isset($this->_protocols[$protocol])) {
            $this->_usingProtocol = $protocol;

            $this->_protocol = new $this->_protocols[$protocol]();
        } else {
            $this->_usingProtocol = "tcp";
        }

        $this->_startTime = time();

        $this->_local_socket = "tcp:" . $ip . ":" . $port;

//        static::$_eventLoop = new Select();

        if (DIRECTORY_SEPARATOR == "/") {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }
    }

    public function statistics()
    {
        $nowTime = time();
        $this->_startTime = $nowTime;

        $diffTime = $nowTime - $this->_startTime;

        if ($diffTime >= 1) {
            fprintf(STDOUT, "clientNum:%d, recvNum:%d, msgNum:%d\r\n", static::$_clientNum, static::$_recvNum, static::$_msgNum);

            static::$_recvNum = 0;
            static::$_msgNum = 0;
        }
    }

    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function Listen()
    {
        $flag = STREAM_SERVER_LISTEN | STREAM_SERVER_BIND;

        // socket端口复用
        $option['socket']['so_reuseport'] = 1;

        // TCP_NODELAY 禁用nigal算法
        $option['tcp']['tcp_nodelay'] = 1;

        $option['socket']['backlog'] = 102400;

        $context = stream_context_create($option);

        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errstr, $flag, $context);

        fprintf(STDOUT, "listen on:%s\r\n", $this->_local_socket);

        if (!is_resource($this->_mainSocket)) {
            fprintf(STDOUT, "server create fail:%s\n", $errstr);
            exit(0);
        }

        // 设置为非阻塞方式
        stream_set_blocking($this->_mainSocket, 0);
    }

    public function eventLoop()
    {
        static::$_eventLoop->loop();
    }


    public function checkHeartTime()
    {
        foreach (static::$_connections as $idx => $connection) {

            if ($connection->checkHeartTime()) {
                $connection->Close();
            }
        }
    }

    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function Accept()
    {
        $connfd = stream_socket_accept($this->_mainSocket, -1, $peername);

        if (is_resource($connfd)) {

            $connection = new TcpConnection($connfd, $peername, $this);

            $this->onClientJoin();

            static::$_connections[(int)$connfd] = $connection;

            $this->runEventCallBack('connect', [$connection]);

//            print_r("接受到客户端连接了");
        }
    }

    public function onClientJoin()
    {
        ++static::$_clientNum;
    }

    public function removeClient($sockfd)
    {
        if (isset(static::$_connections[(int)$sockfd])) {

            if (is_resource($sockfd)) {
                fclose($sockfd);
            }

            unset(static::$_connections[(int)$sockfd]);

            --static::$_clientNum;
        }
    }


    public function onRecv()
    {
        ++static::$_recvNum;
    }

    public function onMsg()
    {
        ++static::$_msgNum;
    }


    public function worker()
    {
        //  判断方式
        //  当子进程启动后status==start的,,cow复制技术, 复制的时候状态是start,
        //  到这里 肯定不想等于的
        if (self::STATUS_RUNNING == self::$_status) {
            //  异常启动
            $this->runEventCallBack("workerReload", [$this]);
        } else {
            //  正常启动
            static::$_status == self::STATUS_RUNNING;
        }

        cli_set_process_title("JT/worker");

        $this->Listen();

        static::$_status = self::STATUS_RUNNING;

        if (DIRECTORY_SEPARATOR == "/") {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }
//        static::$_eventLoop = new Select();

        // 子进程安装信号!
        // 用事件循环的信号,所以先忽略下
        pcntl_signal(SIGINT, SIG_IGN, false);  //  忽略 ctrl+c
        pcntl_signal(SIGTERM, SIG_IGN, false); //  忽略 请求进程终止
        pcntl_signal(SIGQUIT, SIG_IGN, false); //  忽略 请求进程终止并生成核心转储（core dump）
        //  该信号在与一个已关闭的写入端点的管道通信时发生。当您尝试向一个已关闭的管道写入数据时
//        pcntl_signal(SIGPIPE, SIG_IGN,false);

        static::$_eventLoop->add(SIGINT, Event::EV_SIGNAL, [$this, "sigHandler"]);
        static::$_eventLoop->add(SIGTERM, Event::EV_SIGNAL, [$this, "sigHandler"]);
        static::$_eventLoop->add(SIGQUIT, Event::EV_SIGNAL, [$this, "sigHandler"]);

        static::$_eventLoop->add($this->_mainSocket, Event::EV_READ, [$this, "accept"]);
//        static::$_eventLoop->add(2,Event::EVENT_TIMER,[$this,"checkHeartTime"]);
//        static::$_eventLoop->add(1,Event::EVENT_TIMER,[$this,"statistics"]);

//        static::$_eventLoop->add(2,Event::EVENT_TIMER,function ($timerId,$arg){
//            echo posix_getpid() . "定时\r\n";
//        });

        $this->runEventCallBack("workerStart", [$this]);

        $this->eventLoop();
        $this->runEventCallBack("workerStop", [$this]);

        exit(0);
    }

//    public function worker()
//    {
//        static::$_eventLoop->add($this->_mainSocket, Event::EV_READ, [$this, 'Accept']);
//
////        static::$_eventLoop->add(2, Event::EV_TIMER, [$this, 'checkHeartTime']);
//
////        static::$_eventLoop->add(1, Event::EV_TIMER, [$this, 'statistics']);
//
////        $timerId = static::$_eventLoop->add(1, Event::EV_TIMER, function ($timerId, $arg) {
////            var_dump($arg);
////            static::$_eventLoop->del($timerId, Event::EV_TIMER);
////        }, ['name' => 'xxx']);
//
////        $this->Accept();
//
//        $this->eventLoop();
//
//        exit(0);
//    }

//    public function masterWork()
//    {
//        while (1) {
//            $pid = pcntl_wait($status);
//
//            if ($pid > 0) {
//                unset($this->_pidMap[$pid]);
//            }
//
//            if (empty($this->_pidMap)) {
//                break;
//            }
//        }
//
//        exit(0);
//    }


    public function forkWorker()
    {
        $workerNum = $this->_setting['workerNum'] ?? 1;

        for ($i = 0; $i < $workerNum; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                $this->worker();
            } else {
                $this->_pidMap[$pid] = $pid;
            }
        }
    }

    public function init()
    {
        date_default_timezone_set("Asia/Shanghai");

        // 创建日志文件和pid保存文件
        $trace = debug_backtrace();
        $startFile = array_pop($trace)['file'];

        static::$_startFile = $startFile;

        static::$_pidFile = pathinfo($startFile)['filename'] . ".pid";

        static::$_logFile = pathinfo($startFile)['filename'] . ".log";

        if (!file_exists(static::$_logFile)) {
//            file_put_contents(static::$_logFile, "");

            touch(static::$_logFile);
//            chown(static::$_logFile, posix_getuid());
        }

        if (DIRECTORY_SEPARATOR == "/") {
            static::$_os = "LINUX";
            chown(static::$_logFile, posix_getuid());
        } else {
            static::$_os = "WIN";
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $this->echoLog("<file:%s>---<line:%s>---<info:%s>\r\n", $errfile, $errline, $errstr);
        });
    }

    public function echoLog($format, ...$data)
    {
        if ($this->checkSetting("daemon") && static::$_os != "WIN") {

            $info = sprintf($format, ...$data);
            $msg = "[pid:" . posix_getpid() . "]-[" . date("Y-m-d H:i:s") . "]-[info:" . $info . "]\r\n";
            file_put_contents(static::$_logFile, $msg, FILE_APPEND);
        } else {

            fprintf(STDOUT, $format, ...$data);
        }
    }

    public function checkSetting($item)
    {
        if (isset($this->_setting[$item]) && $this->_setting[$item] == true) {

            return true;
        }
        return false;
    }


    public function masterWork()
    {
        while (1) {
            //在给定的代码片段中，pcntl_signal_dispatch()函数被调用两次。
            //第一次调用用于处理可能在之前被挂起的信号，
            //第二次调用用于处理pcntl_wait()函数返回的子进程状态。这样可以确保在等待子进程结束期间，其他挂起的信号能够得到及时处理。
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status);
            pcntl_signal_dispatch();

            // 这3行回收子进程

            if ($pid > 0) {
                unset($this->_pidMap[$pid]);

                if (self::STATUS_SHUTDOWN != static::$_status) {
                    $this->reloadWorker();
                }
            }

            if (empty($this->_pidMap)) {
                break;
            }
        }

        $this->runEventCallBack("masterShutdown", [$this]);

        exit(0);
    }

    public function reloadWorker()
    {
        $pid = pcntl_fork();

        if ($pid == 0) {
            $this->worker();
        } else {
            $this->_pidMap[$pid] = $pid;
        }
    }

    public function Start()
    {
        static::$_status = self::STATUS_STARTING;

        $this->init();
        global $argv;

        $command = $argv[1] ?? '';

        switch ($command) {
            case "start":

                cli_set_process_title("JT/master");

                if (is_file(static::$_pidFile)) {
                    $masterPid = file_get_contents(static::$_pidFile);
                } else {
                    $masterPid = 0;
                }

                //  检测进程是否存在 ,posix_kill($masterPid, 0)   0
                /**
                 * posix_kill($masterPid, 0)：posix_kill() 函数用于发送信号给指定进程。在这里，我们使用信号编号为0的信号，它实际上并不发送给进程，而是用于检查进程是否存在。如果进程存在，该函数将返回 true，否则返回 false。
                 */
                $masterPidisAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();

                /**
                 * $masterPid != posix_getpid()：这是一个额外的条件，
                 * 用于确保 $masterPid 不是当前进程的进程ID。这是为了避免将当前进程误判为主进程。
                 */

                if ($masterPidisAlive) {
                    exit("server is running...\r\n");
                }

                $this->runEventCallBack("masterStart", [$this]);

                if ("LINUX" == static::$_os) {

                    if ($this->checkSetting("daemon")) {

                        $this->daemon();
                        $this->resetFd();
                    }
                    $this->saveMasterPid();
                    $this->installSignalHandler();
                    $this->forkWorker();
//                    $this->forkTaskWorker();

                    static::$_status = self::STATUS_RUNNING;

                    //不要再使用echo,print var_dump
                    //fpm 框架[laravel,tp,yii,ci..]
                    $this->displayStartInfo();
                    $this->masterWork();
                } else {
                    //c /c ++ win api   msdn
                    $this->displayStartInfo();
                    $this->worker();
                }

                break;

            case "stop":
                $masterPid = file_get_contents(static::$_pidFile);
                if ($masterPid && posix_kill($masterPid, 0)) {

                    //  给主进程发
                    posix_kill($masterPid, SIGINT);
                    echo "发送了SIGTERM信号了\r\n";
                    echo $masterPid . "\r\n";
                    $timeout = 5;
                    $stopTime = time();
                    while (1) {

                        $masterPidisAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();
                        if ($masterPidisAlive) {

                            if (time() - $stopTime >= $timeout) {

                                fprintf(STDOUT, "server stop failure\r\n");
                                break;
                            }
                            sleep(1);
                            continue;
                        }

                        fprintf(STDOUT, "server stop success\r\n");
//                        $this->echoLog("server stop success\r\n");
                        break;
                    }

                } else {
                    exit("server not exist...");
                }

                break;

            default:
                //php te.php start|stop
                $usage = "php " . pathinfo(static::$_startFile)['filename'] . ".php [start|stop]\r\n";
                exit($usage);
        }
    }

    public function saveMasterPid()
    {
        $pid = posix_getpid();

        file_put_contents(static::$_pidFile, $pid);
    }

    public function displayStartInfo()
    {
        $info = "";
//        $info = "\r\n\e[31;40m" . file_get_contents("logo.txt") . " \e[0m";
        $info .= "\e[33;40mTe workerNum:" . $this->_setting['workerNum'] . " \e[0m \r\n";
        $info .= "\e[33;40m Te taskNum:" . $this->_setting['taskNum'] . " \e[0m \r\n";

        $info .= "\e[33;40m Te run mode:" . ($this->checkSetting("daemon") ? "deamon" : "debug") . " \e[0m \r\n";
        $info .= "\e[33;40m Te working with :" . $this->_usingProtocol . " protocol \e[0m \r\n";
        $info .= "\e[33;40m Te server listen on :" . $this->_local_socket . " \e[0m \r\n";
        $info .= "\e[33;40m Te run on :" . static::$_os . " platform \e[0m \r\n";

        fwrite(STDOUT, $info);

    }


    private function resetFd()
    {
//        fclose(STDIN);
//        fclose(STDOUT);
//        fclose(STDERR);
//
//        fopen("/dev/null","a");
//        fopen("/dev/null","a");
//        fopen("/dev/null","a");


    }

    public function daemon()
    {
        umask(000);

        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        }

        if (-1 == posix_setsid()) {

            throw new Exception("setsid failure");
        }

        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        }
    }

    public function setting(array $array)
    {
        $this->_setting = $array;
    }

    public function installSignalHandler()
    {
        /**
         * 在pcntl_signal(SIGINT,[$this,"sigHandler"],false)中，false参数表示信号处理函数是否可重入。
         *
         * 当设置为false时，表示信号处理函数sigHandler不可重入。这意味着如果在处理信号期间再次收到相同的信号，那么第二个信号将被忽略，直到第一个信号处理完毕。
         *
         * 如果将该参数设置为true，则表示信号处理函数可重入。这意味着如果在处理信号期间再次收到相同的信号，那么第二个信号不会被忽略，而是会立即触发信号处理函数。
         *
         * 通常情况下，将该参数设置为false是比较常见的做法，以避免信号处理函数的重入导致意外的行为或竞争条件
         */
        pcntl_signal(SIGINT, [$this, "sigHandler"], false);
        pcntl_signal(SIGTERM, [$this, "sigHandler"], false);
        pcntl_signal(SIGQUIT, [$this, "sigHandler"], false);

        // 读写socket文件时产生信号时候忽略, 主要是 对端关闭了,  你还在发,就会产生这信号
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }


    // 主进程和子进程收到中断信号执行
    public function sigHandler($sigNum)
    {
//        var_dump("主收到sigHandler:" . $sigNum);

        $masterPid = file_get_contents(static::$_pidFile);
        switch ($sigNum) {

            case SIGINT:
            case SIGTERM:
            case SIGQUIT:

                //主进程
                if ($masterPid == posix_getpid()) {

                    print_r($this->_pidMap);

                    foreach ($this->_pidMap as $pid => $pid) {

                        posix_kill($pid, $sigNum);//SIGKILL 它是粗暴的关掉，不过子进程在干什么 SIGTERM,SIGQUIT
                    }
                    static::$_status = self::STATUS_SHUTDOWN;

                } else {

                    static::$_eventLoop->exitLoop();
                    //子进程的 就要停掉现在的任务了
                    static::$_eventLoop->del($this->_mainSocket, Event::EV_READ);
                    set_error_handler(function () {
                    });
                    fclose($this->_mainSocket);
                    restore_error_handler();
                    $this->_mainSocket = null;
                    foreach (static::$_connections as $fd => $connection) {

                        $connection->Close();
                    }
                    static::$_connections = [];

                    static::$_eventLoop->clearSignalEvents();
                    static::$_eventLoop->clearTimer();

                    if (static::$_eventLoop->exitLoop()) {

                        fprintf(STDOUT, "<pid:%d> worker exit event loop success\r\n", posix_getpid());
//                        $this->echoLog("<pid:%d> worker exit event loop success\r\n",posix_getpid());
                    }
                }
                break;
        }
    }
}
