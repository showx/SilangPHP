<?php
namespace App\Controller;

use App\Model\IndexModel;
use SilangPHP\Tpl;
use SilangPHP\Cache;

class IndexController extends \SilangPHP\Controller
{
    public function index()
    {
        Tpl::assign("test","test1");
        Tpl::display('index');
    }
}