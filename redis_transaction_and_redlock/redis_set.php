<?php

/**
 * redis的事务实验
 */

$redis = new Redis();

$redis->connect('127.0.0.1');

while(true){

    $num = rand(2,9);

    $redis->set('test_key',$num);

    sleep(1);

}