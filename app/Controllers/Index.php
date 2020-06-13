<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class Index extends Controller
{
    public function index()
    {
//        session()->set("testwswoole",100111111);
//
//        echo session()->get("testwswoole");
//
//        cache()->save("redis_test",10000);
//        echo cache()->get("redis_test");
    }

    public function index1()
    {
        echo "router1";
    }

    public function index2()
    {
        echo "router2";
    }

    public function index3()
    {
        echo "router3";
    }

}