<?php
    require 'vendor/autoload.php';
    use AfricasTalking\SDK\AfricasTalking;

    include_once 'util.php';
    include_once 'db.php';


    class Sms {
        protected $phone;
        protected $AT;

        function __construct($phone)
        {
            $this->phone = $phone;
            $this->AT = new AfricasTalking(Util::$API_USERNAME, Util::$API_KEY);
        }

        public function getPhone(){
            return $this->phone;
        }

        public function sendSMS($message, $recipients){
            //get the sms service
            $sms = $this->AT->sms();
            //use the service 
            $result = $sms->send([
                'to'      => $recipients,
                'message' => $message,
                'from'    => Util::$SMS_SHORTCODE,
                'keyword' => Util::$SMS_SHORTCODE_KEYWORD
            ]);
            //  'from'    => Util::$SMS_SHORTCODE, meaning recipients can respond to the message
            //  'from'    => Util::$SENDER_ID, meaning recipients can not respond to the message
            return $result;
        }

        public function fetchRecipients (){
            //Read all user phone numbers
            $db = new DBConnector();
            $pdo = $db->connectToDB();
            //prepare an sql query 
            $stmt = $pdo->prepare('SELECT phone FROM user');
            $stmt->execute();
            $result = $stmt->fetchAll();
            //hopinf that there were records to simplify logic
            //result has an array of objects
            $recipients = array();
           foreach($result as $row){
                array_push($recipients,$row['phone']);                 
           }
           return join(",", $recipients);
        }

        public function subscribeUser ($pdo, $shortcode, $keyword){
            $stmt = $pdo->prepare('INSERT INTO subscribers (phoneNumber, shortcode,keyword,isActive) VALUES(?,?,?,?)');
            $stmt->execute([$this->getPhone(), $shortcode, $keyword, 1]);
            $stmt = null;//terminating the db connection
        }


        public function unSubscribeUser($pdo, $shortcode, $keyword){
            $stmt = $pdo->prepare('UPDATE subscribers SET isActive=? WHERE phoneNumber=? AND shortcode=? AND keyword=?');
            $stmt->execute([0,$this->getPhone(),$shortcode, $keyword]);
            $stmt = null;
        }

        public function sendPremiumSms ($pdo, $shortcode, $keyword, $message){
            $recipients = $this->fetchActivePhoneNumbers($pdo, $shortcode, $keyword);

            $content = $this->AT->content();
            $response = $content->send([
                'message'=> $message,
                'to'=>$recipients,
                'from'=>$shortcode,
                'keyword' => $keyword
            ]);

            return $response;
        }

        public function fetchActivePhoneNumbers ($pdo, $shortcode, $keyword){
            $stmt= $pdo->prepare('SELECT phoneNumber FROM subscribers WHERE isActive=? AND shortcode=? AND keyword=?');
            $stmt->execute([1, $shortcode, $keyword]);
            $activePhoneNumbers = $stmt->fetchAll();
            $recipients = [];

            foreach($activePhoneNumbers as $phone){
                array_push($recipients, $phone['phoneNumber']);
            }

            return $recipients;//return an array
        }
        
        public function subscribeUserWithToken ($shortcode, $keyword, $phone){
            //subscribing users with checkout token functionality
            $content = $this->AT->content();
            $checkoutToken = $this->getToken($phone);
            $response = $content->createSubscription([
                'shortCode'=>$shortcode,
                'keyword'=>$keyword,
                'phoneNumber'=>$phone,//that you wish to get a checkout token for
                'checkoutToken'=>$checkoutToken
            ]);

            return $response;
        }

        public function getToken($phone) {
            $token = $this->AT->token();
            $tokenResult = $token->createCheckoutToken([
                'phoneNumber'=>$phone
            ]);

            $checkoutToken = $tokenResult['data']->token;
            return $checkoutToken;
        }        

        //ping the gateway to get users who have subscribed
        public function fecthNewSubscribers($pdo, $shortcode, $keyword){
            $content = $this->AT->content();

            $responseArray = $content->fetchSubscriptions([
                'shortCode'=>$shortcode,
                'keyword'=>$keyword,
                'lastReceivedId'=>0,//fetch everything
            ])['data']->responses;

            foreach($responseArray as $res){
                $sms = new Sms($res->phoneNumber);
                $sms->subscribeUser($pdo,$shortcode,$keyword);
            }

            return $responseArray;
        }
    }

?>