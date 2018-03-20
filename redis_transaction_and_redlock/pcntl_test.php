<?php

$parentPid = getmypid();

$childPid = 0;

$i = 3;

echo "开始fork".PHP_EOL;

while($i--){
    $childPid = pcntl_fork();   //创建子进程
    if($childPid === 0){
        echo  "我是子进程,进程id:{$childPid},实际进程id:".getmypid().PHP_EOL;
    }
}

echo "我是父进程，进程ID：".getmypid().",子进程ID: {$childPid}".PHP_EOL;

pcntl_waitpid($childPid,$status);
var_dump($status);
