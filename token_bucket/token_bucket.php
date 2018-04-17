<?php

$sha = '2ff7ad2d1b49da8430e5adc8675e';

/**
 * 初始化lua脚本
 */
function initScript($redis) {
    global $sha;
    //不存在脚本，load进去
    $script = file_get_contents('check_and_set.lua');
    $sha = $redis->script('load', $script);
    if($redis->getLastError() !== NULL){
        echo "出错了：".$redis->getLastError().'\n';
    }
    echo "初始化的脚本sha:".$sha.PHP_EOL;
}

function getTokenFromBucket($redis, $bucket) {
    global $sha;
    $capacity = 10;
    $time = time();
    $cycle = 0.5;     //一秒2个token
    $params = array($bucket, $capacity, $time, $cycle);
    $result = $redis->evalSha($sha, $params, 1);
    if($redis->getLastError() !== NULL){
        echo "出错了：".$redis->getLastError().'\n';
    }
    return $result;
}

$redis = new Redis();
$redis->pconnect("127.0.0.1");

initScript($redis);

$start = microtime(true) * 1000;

while(true) {
    $result =  getTokenFromBucket($redis, 'bucket1');
    if (!$result) {
        break;
    } else {
        echo time()."--拿到令牌".PHP_EOL;
        usleep(250000);    //每秒请求4次,10 + 2x = 4x, x = 5。5秒左右无法拿到令牌
    }
}

$end = microtime(true) * 1000;

echo "共耗时：".($end - $start)."毫秒".PHP_EOL.PHP_EOL;

$start = microtime(true) * 1000;

while(true) {
    $result =  getTokenFromBucket($redis, 'bucket2');
    if (!$result) {
        break;
    } else {
        echo "拿到令牌".PHP_EOL;
        usleep(500000);    //每秒请求2次，永远可以拿到令牌
    }
}

$end = microtime(true) * 1000;

echo "共耗时：".($end - $start)."毫秒".PHP_EOL.PHP_EOL;