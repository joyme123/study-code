<?php
/**
 * php 使用lua脚本实现事务的实验
 */

$redis = new Redis();

$redis->connect('127.0.0.1');

$sha = '303ae1806f84dcd0bc5fe9c8432e0905ca348f3a';

$result = $redis->script('exist',$sha);
if (!$result[0]){
    //不存在脚本，load进去
    $script = file_get_contents('check_and_set.lua');
    $sha = $redis->script('load', $script);
}

$value = $redis->evalSha($sha); // Returns 1

if($redis->getLastError() !== NULL){
    echo "出错了：".$redis->getLastError().'\n';
}else{
    echo "执行结果是:".$value."\n";
}