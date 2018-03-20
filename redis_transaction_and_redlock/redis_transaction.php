<?php

/**
 * redis的事务实验
 */

/**
 * 向市场中陈列商品
 */
function listItem($conn,$itemId,$sellerId,$price){
    $inventory = "inventory:".$sellerId;
    $item = "item.".$sellerId;
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
        $conn->sRem($inventory,$item);
        $result = $conn->exec();

        if($result){
            return true;
        }
    }

    return false;
}

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

$redis = new Redis();

$redis->connect('127.0.0.1');

$listValue = listItem($redis,'1','87','100');

if($listValue === null){
    echo "库存不足\n";
}else if($listValue){
    echo "陈列成功";
}else{
    echo "陈列失败";
}

$purchaseValue = purchase($redis,'90','1','87','100');

if($purchaseValue === null){
    echo "余额不足\n";
}else if($listValue){
    echo "购买成功";
}else{
    echo "购买失败";
}

// echo "重试次数：$retryCount";