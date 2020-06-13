<?php

class Http {
    CONST HOST = "0.0.0.0";
    CONSt PORT = 8811;

    public $http = null;

    public function __construct()
    {
        $this->http = new swoole_http_server(self::HOST,self::PORT);

        $this->http->set([
            "enable_static_handler"=>true,
            "document_root"=>"/Users/github_project/ci4-swoole/public",
            "worker_num"=>4,
            "task_worker_num"=>4
        ]);

        $this->http->on("workerstart",[$this,"onWorkerStart"]);
        $this->http->on("request",[$this,"onRequest"]);
        $this->http->on("task",[$this,"onTask"]);
        $this->http->on("finish",[$this,"onFinish"]);
        $this->http->on("close",[$this,"onClose"]);

        $this->http->start();
    }

    /**
     * 定义codeigniter4的全局变量，加载codeigniter4的框架文件
     * @param $serber
     * @param $worker_id
     */
    public function onWorkerStart($serber,$worker_id)
    {
        // Path to the front controller (this file)
        define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
        $pathsPath = FCPATH . '../app/Config/Paths.php';

        require $pathsPath;

        $paths = new Config\Paths();

        // Location of the framework bootstrap file.
        /**
         * The path to the application directory.
         */
        if (! defined('APPPATH'))
        {
            define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
        }

        /**
         * The path to the project root directory. Just above APPPATH.
         */
        if (! defined('ROOTPATH'))
        {
            define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
        }

        /**
         * The path to the system directory.
         */
        if (! defined('SYSTEMPATH'))
        {
            define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
        }

        /**
         * The path to the writable directory.
         */
        if (! defined('WRITEPATH'))
        {
            define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);
        }

        /**
         * The path to the tests directory
         */
        if (! defined('TESTPATH'))
        {
            define('TESTPATH', realpath($paths->testsDirectory) . DIRECTORY_SEPARATOR);
        }

        /*
         * ---------------------------------------------------------------
         * GRAB OUR CONSTANTS & COMMON
         * ---------------------------------------------------------------
         */
        if (! defined('APP_NAMESPACE'))
        {
            require_once APPPATH . 'Config/Constants.php';
        }

        // Let's see if an app/Common.php file exists
        if (file_exists(APPPATH . 'Common.php'))
        {
            require_once APPPATH . 'Common.php';
        }

        // Require system/Common.php
        require_once SYSTEMPATH . 'Common.php';

        /*
         * ---------------------------------------------------------------
         * LOAD OUR AUTOLOADER
         * ---------------------------------------------------------------
         *
         * The autoloader allows all of the pieces to work together
         * in the framework. We have to load it here, though, so
         * that the config files can use the path constants.
         */

        if (! class_exists(Config\Autoload::class, false))
        {
            require_once APPPATH . 'Config/Autoload.php';
            require_once APPPATH . 'Config/Modules.php';
        }

        require_once SYSTEMPATH . 'Autoloader/Autoloader.php';
        require_once SYSTEMPATH . 'Config/BaseService.php';
        require_once APPPATH . 'Config/Services.php';

        // Use Config\Services as CodeIgniter\Services
        if (! class_exists('CodeIgniter\Services', false))
        {
            class_alias('Config\Services', 'CodeIgniter\Services');
        }

        $loader = CodeIgniter\Services::autoloader();
        $loader->initialize(new Config\Autoload(), new Config\Modules());
        $loader->register();    // Register the loader with the SPL autoloader stack.

        // Now load Composer's if it's available
        if (is_file(COMPOSER_PATH))
        {
            require_once COMPOSER_PATH;
        }

        // Load environment settings from .env files
        // into $_SERVER and $_ENV
        require_once SYSTEMPATH . 'Config/DotEnv.php';

        $env = new \CodeIgniter\Config\DotEnv(ROOTPATH);
        $env->load();

        // Always load the URL helper -
        // it should be used in 90% of apps.
        helper('url');
    }

    /**
     * 1. 因为是常驻内存的关系，$_SERVER,$_GET,$_POST等超全局变量都是常驻内存的，所以每次请求需要进行清空操作。
     * 2. ci4 进行路由解析的时候，$_SERVER中需要包含 SCRIPT_NAME 参数，才会进行正常解析
     * 3. 修改 全局方法 is_cli()的返回为false 这样才能获取到 IncomingRequest类 而不是获取到CliRequest类  (system/Common.php文件)
     * 4. 底层需要更改路由的镜像，设置为实时获取，不保存镜像请求，system/HTTP/Request.php文件下，fetchGlobal()方法 和 populateGlobals()方法
     * 5. 更改 system/Config/Services.php下的request() 方法，注释
     * @param $request
     * @param $response
     */
    public function onRequest($request,$response)
    {
        $_SERVER  =  [];

        if(isset($request->server)) {
            foreach($request->server as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        if(isset($request->header)) {
            foreach($request->header as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }

        $_GET = [];
        if(isset($request->get)) {
            foreach($request->get as $k => $v) {
                $_GET[$k] = $v;
            }
        }
        $_POST = [];
        if(isset($request->post)) {
            foreach($request->post as $k => $v) {
                $_POST[$k] = $v;
            }
        }

        //ci4 解析路径需要参数
        $_SERVER['SCRIPT_NAME'] = "index.php";

        $_POST['http_server'] = $this->http;

        ob_start();
        // 执行应用并响应
        try {
            $appConfig = config(\Config\App::class);
            $app       = new \CodeIgniter\CodeIgniter($appConfig);
            $app->initialize();
            $app->run();

//            print_r($request->server);
        }catch (\Exception $e) {
            // todo
        }

        $res = ob_get_contents();
        ob_end_clean();
        $response->end($res);
    }

    public function onTask($serv,$taskId,$workerId)
    {

    }

    /**
     * @param $serv
     * @param $taskId
     * @param $data
     */
    public function onFinish($serv, $taskId, $data) {
        echo "taskId:{$taskId}\n";
        echo "finish-data-sucess:{$data}\n";
    }

    /**
     * close
     * @param $ws
     * @param $fd
     */
    public function onClose($ws, $fd) {
        echo "clientid:{$fd}\n";
    }
}

new Http();