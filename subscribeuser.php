<?php
    include_once 'util/db.php';
    include_once 'util/sms.php';

    $shortcode = $_POST['shortcode'];
    $keyword = $_POST['keyword'];
    $phoneNumber = $_POST['phoneNumber'];

    $db = new DBConnector();
    $pdo = $db->connectToDB();

    $sms = new Sms($phoneNumber);//use the object to call subscribeUserWithToken()

    echo json_encode($sms->subscribeUserWithToken($shortcode,$keyword,$phoneNumber));
