<?php
    include_once 'util/db.php';
    include_once 'util/sms.php';


    $sms = new Sms('08634533');//dummy number
    $db = new DBConnector();
    $pdo = $db->connectToDB();

    $shortcode = $_POST['shortcode'];
    $keyword = $_POST['keyword'];
    $message = $_POST['message'];

    $response = $sms->sendPremiumSms($pdo,$shortcode, $keyword, $message);

    
    echo json_encode($response);

?>