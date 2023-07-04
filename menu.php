<?php
include_once 'util/util.php';
include_once 'model\user.php';
include_once 'model\transactions.php';
include_once 'model\agent.php';
include_once 'util/sms.php';

class Menu
{
    protected $text;
    protected $sessionId;

    function __construct()
    {
    }

    public function mainMenuRegistered($name)
    {
        $response = "CON Welcome " . $name . ", please select option\n";
        //$response .= "";
        $response .= "1. Send money\n";
        $response .= "2. Withdraw\n";
        $response .= "3. Check balance\n";
        return $response;
    }

    public function mainMenuUnRegistered()
    {
        $response = "CON Welcome to this app. Reply with\n";
        $response .= "1. Register\n";
        echo $response;
    }

    public function registerMenu($textArray, $phoneNumber, $pdo)
    {
        //to determine the level
        $level = count($textArray);

        if ($level == 1) {
            echo "CON Please enter your full name:";
        } else if ($level == 2) {
            echo "CON Please enter your PIN:";
        } else if ($level == 3) {
            echo "CON Please re-enter your PIN:";
        } else if ($level == 4) {
            $name = $textArray[1];
            $pin = $textArray[2];
            $confirmPin = $textArray[3];

            if ($pin != $confirmPin) {
                echo "END Your pins do not match. Please try again";
            } else {
                //register the user
                //send sms
                $user = new User($phoneNumber);
                $user->setName($name);
                $user->setPin($pin);
                $user->setBalance(Util::$USER_BALANCE);
                $user->register($pdo);

                echo "END You have been registered";
            }

        }
    }

    public function sendMoneyMenu($textArray, $sender, $pdo, $sessionId)
    {
        $level = count($textArray);
        $receiver = null;
        $nameOfReceiver = null;
        $response = "";

        if ($level == 1) {
            echo "CON Enter mobile number of the receiver:";
        } else if ($level == 2) {
            echo "CON Enter amount:";
        } else if ($level == 3) {
            echo "CON Enter your PIN:";
        } else if ($level == 4) {

            $receiverMobile = $textArray[1];
            $receiverMobileWithCountryCode = $this->addCountryCodeToPhoneNumber($receiverMobile);

            $receiver = new User($receiverMobileWithCountryCode);

            //confirmation message
            $response .= "Send " . $textArray[2] . " to " . $receiver->getUserName($pdo) . " - " . $receiverMobile . "\n";
            $response .= "1. Confirm\n";
            $response .= "2. Cancel\n";
            $response .= Util::$GO_BACK . " Back\n";
            $response .= Util::$GO_TO_MAIN_MENU . " Main menu\n";
            echo "CON " . $response;

        } else if ($level == 5 && $textArray[4] == 1) {//Confirm

            //send the money plus process
            //check if PIN correct
            //If you have enough funds including charges etc..
            $amount = $textArray[2];
            $pin = $textArray[3];
            $transaction_type = "send";

            $sender->setPin($pin);

            $newSenderBalance = $sender->getUserBalance($pdo) - $amount - Util::$TRANSACTION_FEE;//sender balance afterwards

            $receiver = new User($this->addCountryCodeToPhoneNumber($textArray[1]));
            $newReceiverBalance = $receiver->getUserBalance($pdo) + $amount;

            if ($sender->isPinVerified($pdo) == false) {
                echo "END Wrong PIN";
                //send sms as well
            } else {

                $transaction = new Transaction($amount, $transaction_type);
                $result = $transaction->sendMoney($pdo, $sender->readUserId($pdo), $receiver->getUserId($pdo), $newSenderBalance, $newReceiverBalance);

                if ($result == true) {
                    echo "END We are processing your request. You will receive an SMS shortly";
                    //send an sms as well
                } else {
                    echo "CON " . $result;
                }

            }

        } else if ($level == 5 && $textArray[4] == 2) { //Cancel
            echo "END Thank you for using our service";
        } else if ($level == 5 && $textArray[4] == Util::$GO_BACK) {
            echo "END You have requested to back to one step - PIN";
        } else if ($level == 5 && $textArray[4] == Util::$GO_TO_MAIN_MENU) {
            echo "END You have requested to back to main menu";
        } else {
            //echo "END Invalid entry";

            $ussdLevel = count($textArray) - 1;
            $this->persistInvalidEntry($sessionId,$sender, $ussdLevel,$pdo);

            $response = "CON Invalid entry. Please try again\n" ;
            //$response .=  $this->mainMenuRegistered($sender->getUserName($pdo));
            echo $response;
            $this->sendMoneyMenu($textArray, $sender, $pdo, $sessionId);
        }
    }

    public function withdrawMoneyMenu($textArray, $user, $pdo,$sessionId)
    {
        //the level shows how many times the user has replied
        $level = count($textArray);
        $response = "";

        if ($level == 1) {
            echo "CON Enter agent number:";
        } else if ($level == 2) {
            echo "CON Enter amount:";
        } else if ($level == 3) {
            echo "CON Enter your PIN:";
        } else if ($level == 4) {
            //TODO: confirm if agent exists
            $agent = new Agent($textArray[1]);//passing agent number

            //confirmation message
            $response .= "Withdraw " . $textArray[2] . " from agent " . $agent->getNameByNumber($pdo) . "\n";
            $response .= "1. Confirm\n";
            $response .= "2. Cancel\n";
            echo "CON " . $response;

        } else if ($level == 5 && $textArray[4] == 1) {//confirm

            $user->setPin($textArray[3]);

            if ($user->isPinVerified($pdo) == false) {
                echo "END Wrong Pin";// or send an sms
                return;
            }
            if ($user->getUserBalance($pdo) < $textArray[2] + Util::$TRANSACTION_FEE) {
                echo "END You have insufficient funds";
                return;
            }

            $agent = new Agent($textArray[1]);
            $agentName = $agent->getNameByNumber($pdo);
            $ttype = "withdraw";

            $transaction = new Transaction($textArray[2], $ttype);

            $newBalance = $user->getUserBalance($pdo) - $textArray[2] - Util::$TRANSACTION_FEE;

            $result = $transaction->withDrawCash($pdo, $user->readUserId($pdo), $agent->getIdByNumber($pdo), $newBalance);

            if ($result) {
                echo "END Your request is being processed";
            } else {
                echo "END " . $result;
            }

        } else if ($level == 5 && $textArray[4] == 2) {//Cancel
            echo "END Thank you for using our service";
        } else {
            //echo "END Invalid entry";

            $ussdLevel = count($textArray) - 1;
            $this->persistInvalidEntry($sessionId,$user, $ussdLevel,$pdo);

            $response = "CON Invalid entry. Please try again\n" ;
            $response .=  $this->mainMenuRegistered($user->getUserName($pdo));
            echo $response;
        }
    }

    public function checkBalanceMenu($textArray, $user, $pdo, $sessionId)
    {
        $level = count($textArray);

        if ($level == 1) {
            
            echo "CON Enter your PIN";

        } else if ($level == 2) {
            //logic
            //check PIN correctness etc
            //$pin = $textArray[1];
            $user->setPin($textArray[1]);

            if ($user->isPinVerified($pdo)) {

                $msg = "Your wallet balance is " . $user->getUserBalance($pdo) . ". Thank you for using this service";//send an sms
                $sms = new Sms($user->getPhone());
                $result = $sms->sendSMS($msg, $user->getPhone());

                if ($result['status'] == "Success" || $result['status'] == "success") {
                    echo "END You will receive an SMS shortly";
                } else {
                    echo "END There was an error. Please try again";
                }

            } else {
                echo "END Wrong PIN";// send sms
            }

        } else {
            //echo "END Invalid entry";

            $response = "CON Invalid entry. Please try again\n" ;
            $response .=  $this->mainMenuRegistered($user->getUserName($pdo));
            echo $response;
        }
    }

    public function middleware($text, $user, $sessionId, $pdo)
    {
        //remove entries for going back and going to the main menu

        return $this->invalidEntry($this->goBack($this->goToMainMenu($text)), $user, $sessionId, $pdo);
    }

    public function goBack($text)
    {
        //1*4*5*1*98*2*1234
        $explodedText = explode("*", $text);

        while (array_search(Util::$GO_BACK, $explodedText) != false) {
            $firstIndex = array_search(Util::$GO_BACK, $explodedText);
            array_splice($explodedText, $firstIndex - 1, 2);//do away with the previous and current
        }

        return join("*", $explodedText);
    }

    public function goToMainMenu($text)
    {
        //1*4*5*1*99*2*1234*99
        $explodedText = explode("*", $text);

        while (array_search(Util::$GO_TO_MAIN_MENU, $explodedText) != false) {
            $firstIndex = array_search(Util::$GO_TO_MAIN_MENU, $explodedText);
            $explodedText = array_slice($explodedText, $firstIndex + 1);//do away with everything prepended
        }

        return join("*", $explodedText);
    }


    public function persistInvalidEntry($sessionId, $user, $ussdLevel, $pdo)
    {
        $stmt = $pdo->prepare("INSERT INTO ussdsession (sessionId,ussdLevel, uid) VALUES (?,?,?)");
        $stmt->execute([$sessionId, $ussdLevel, $user->getUserId($pdo)]);
        $stmt = null;
    }

    //removes invalid input that were captured
    public function invalidEntry($ussdStr, $user, $sessionId, $pdo)
    {
        $stmt = $pdo->prepare("SELECT ussdLevel FROM ussdsession WHERE sessionId=?");
        $stmt->execute([$sessionId]);
        $result = $stmt->fetchAll();

        if (count($result) == 0) {
            return $ussdStr;//there are no invalid options
        }

        $strArray = explode("*", $ussdStr);//return an array having all the inputs including the invalid ones

        //removing invalid menu options from user USSD string
        foreach ($result as $value) {
            //remove a ussd level
            unset($strArray[$value['ussdLevel']]);
        }

        $strArray = array_values($strArray);

        return join("*", $strArray);
    }

    public function addCountryCodeToPhoneNumber($phone)
    {
        return Util::$COUNTRY_CODE . substr($phone, 1);
    }


}

?>