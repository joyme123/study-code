<?php

/**
 * redis的事务实验
 */

/**
 * 向市场中陈列商品
 * @param $conn redis连接
 * @param $itemId 商品Id
 * @param $sellerId 商家id
 * @param $price 价格
 */
function listItem($conn,$itemId,$sellerId,$price){
    $inventory = "inventory:".$sellerId;
    $item = "$itemId.$sellerId";
    $end = time() + 5;

    while(time() < $end){
        $conn->watch($inventory);

        if (!$conn->sIsMember($inventory,$itemId)){
            //如果没有库存
            $conn->unwatch();
            return null;
        }

        $conn->multi();
        $conn->zAdd("market:",$price,$item);
        $conn->sRem($inventory,$itemId);
        $result = $conn->exec();

        if($result){
            return true;
        }
    }

    return false;
}

/**
 * 在市场中购买商品
 * @param $conn redis连接
 * @param $buyerId 购买者的id
 * @param $itemId 商品id
 * @param $sellerId 商家id
 * @param $lprice
 */
function purchase($conn, $buyerId, $itemId, $sellerId, $lprice){
    $buyer = "users:".$buyerId;
    $seller = "users:".$sellerId;
    $item = "$itemId.$sellerId";
    $inventory = "inventory:".$buyerId;
    $end = time() + 10;
 
    while (time() < $end){

        $conn->watch(array("market:", $buyer));
        
        $price = $conn->zScore("market:", $item);
        $funds = $conn->hGet($buyer, "funds");
        if ($lprice != $price || $price > $funds){
            $conn->unwatch();
            return null;
        }
        
        $conn->multi();
        $conn->hIncrBy($seller, "funds", $price);
        $conn->hIncrBy($buyer, "funds", -$price);
        $conn->sAdd($inventory, $itemId);
        $conn->zRem("market:", $item);
        $result = $conn->exec();

        if($result){
            return true;
        }
    }

    return false;
}

function init($sellerId,$buyerId) {
    global $items;
    $redis = new Redis();
    $redis->connect('127.0.0.1');

    $buyInventory = "inventory:".$buyerId;
    $inventory = "inventory:".$sellerId;
    $buyer = "users:".$buyerId;
    $seller = "users:".$sellerId;
    $market = "market:";

    $redis->del($buyer,$seller,$inventory,$market,$buyInventory);

    array_walk($items,function($value,$key) use($inventory,$redis){
        $redis->sAdd($inventory,$key);
    });

    $redis->hSet($buyer,"funds",10000);      //买家10000
    $redis->hSet($seller,"funds",10);      //卖家10块
}

$items = array("apple"=>10,"orange"=>20,"balala"=>30,"keyboard"=>40,"mouse"=>50,"pc"=>60);
$sellerId = 87;
$buyerId = 90;

init($sellerId,$buyerId);


$redis = new Redis();

$redis->connect('127.0.0.1');

foreach($items as $key=>$value) {
    $listValue = listItem($redis,$key,$sellerId,$value);

    if($listValue === null){
        echo $key."库存不足\n";
    }else if($listValue){
        echo $key."陈列成功\n";
    }else{
        echo $key."陈列失败\n";
    }
}

function buyerP(swoole_process $worker) {
    global $sellerId,$buyerId;
    $redis = new Redis();

    $redis->connect('127.0.0.1');

    $items = $redis->zRange('market:', 0, -1, true);

    $retryTimes = 0;
    $successBuy = 0;

    usleep(1000);

    while($redis->zSize('market:') > 0 && $retryTimes < 1000) {

        foreach($items as $key=>$value) {
            $itemId = explode(".", $key)[0];
            $purchaseValue = purchase($redis,$buyerId,$itemId,$sellerId,$value);

            if($purchaseValue === null){
                echo "价格变动或余额不足\n";
                $retryTimes++;
            }else if($purchaseValue){
                $successBuy++;
                echo "购买成功\n";
            }else{
                echo "商品情况发生变动，无法购买\n";
                $retryTimes++;
            }
        }

        
    }

    echo "失败次数:".$retryTimes.PHP_EOL;
    echo "成功购买:".$successBuy.PHP_EOL;
    $worker->exit(0);
}

swoole_process::signal(SIGCHLD, function($sig) {
    //必须为false，非阻塞模式
    while($ret =  swoole_process::wait(false)) {
        echo "PID={$ret['pid']}\n";
    }
});

$buy1Process = new Swoole\Process("buyerP");
$buy2Process = new Swoole\Process("buyerP");
$buy3Process = new Swoole\Process("buyerP");

$buy1Process->start();
$buy2Process->start();
$buy3Process->start();