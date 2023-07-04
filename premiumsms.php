<?php
    include_once 'util/sms.php';
    include_once 'util/db.php';

    $phoneNumber = $_POST['phoneNumber'];
    $shortCode = $_POST['shortCode'];
    $keyword = $_POST['keyword'];
    $updateType = $_POST['updateType'];

    $sms = new Sms($phoneNumber);

    $db = new DBConnector();
    $pdo = $db->connectToDB();

    if($updateType === "Addition"){
        $sms->subscribeUser($pdo,$shortCode,$keyword);
    }else{
        $sms->unSubscribeUser($pdo, $shortCode,$keyword);
    }

