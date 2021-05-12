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
declare(strict_types=1);
namespace SilangPHP;
use Illuminate\Database\Capsule\Manager as Capsule;
// use Illuminate\Events\Dispatcher;
// use Illuminate\Container\Container;

/**
 * Class SilangPHP
 * @package SilangPHP
 */
final Class SilangPHP
{
    const VERSION = '1.4.0';
    
    public static $app;
    // 默认运行模式
    public static $mode = 0;
    public static $httpmode = 0;
    public static $cache = [];
    public static $db = null;

    /**
     * 获取临时缓存
     */
    public static function get($key)
    {
        return self::$cache[$key] ?? '';
    }

    /**
     * 设置临时缓存
     */
    public static function set($key, $value)
    {
        self::$cache[$key] = $value;
    }

    /**
     * 设置程序目录
     * @param $path
     */
    public static function setAppDir($path)
    {
        if(PHP_SAPI == 'cli')
        {
            define("run_mode",2);
            define("lr",PHP_EOL);
        }else{
            define("run_mode",1);
            define("lr","<br/>");
        }
        $appName = basename($path);
        // 默认时区
        date_default_timezone_set('Asia/Shanghai');
        define('DS',                 DIRECTORY_SEPARATOR);
        define("PS_APP_PATH",        $path);
        define("PS_APP_NAME",        $appName);
        define("PS_CONFIG_PATH",     PS_ROOT_PATH."/Config/");
        define("PS_RUNTIME_PATH",	 PS_ROOT_PATH."/Runtime/");
        if(file_exists(PS_CONFIG_PATH.'/define.php'))
        {
            require_once(PS_CONFIG_PATH.'/define.php');
        }
        $dbconfig = \SilangPHP\Config::get("Db.mysql");
        if($dbconfig)
        {
            self::$db = new Capsule;
            foreach($dbconfig as $connection_name => $config)
            {
                $db_arr = [
                    'driver'    => $config['dbtype'] ?: 'mysql', 
                    'host'      => $config['host'],
                    'port'      => $config['port'],
                    'database'  => $config['dbname'],
                    'username'  => $config['username'],
                    'password'  => $config['password'],
                    'charset'   => $config['charset'] ?: 'utf8',
                    'collation' => $config['collation'] ?: 'utf8_general_ci',
                    'prefix'    => '',
                ];
                self::$db->addConnection($db_arr,$connection_name);
            }
            // self::$db->setEventDispatcher(new Dispatcher(new Container));
            self::$db->setFetchMode(\PDO::FETCH_ASSOC);
            self::$db->setAsGlobal();
            self::$db->bootEloquent();
        }
        self::$mode = \SilangPHP\Config::get("Site.mode");
        self::$httpmode = \SilangPHP\Config::get("Site.httpmode") ?? 0;
        if(is_array(self::$httpmode))
        {
            self::$httpmode = 0;
        }
        if (defined('SLPHP_HTTPMODE')) {
            self::$httpmode = SLPHP_HTTPMODE;
        }
        switch(self::$httpmode)
        {
            case 0:
                self::$app = new \SilangPHP\Httpmode\Appfpm();
                break;
            case 1:
                self::$app = new \SilangPHP\Httpmode\Appworker();
                break;
            case 2:
                self::$app = new \SilangPHP\Httpmode\Appswoole();
                break;
            case 3:
                self::$app = new \SilangPHP\Httpmode\AppswooleCo();
                break;
            default:
                echo 'app error!';
                return false;
                break;
        }
        self::$app->appDir = $path;
    }

    /**
     * 运行程序
     */
    public static function run($pathinfo = '')
    {
        if(is_object(self::$app))
        {
            try{
                $result = self::$app->run($pathinfo);
            }catch(\Exception $e)
            {
                $result = $e->getMessage();
                self::$app->logger->error("run:".$result);
                if(self::$app->response)
                {
                    self::$app->response->end($result);
                }else{
                    echo $result.PHP_EOL;
                }
            }
            return $result;
        }
    }
}