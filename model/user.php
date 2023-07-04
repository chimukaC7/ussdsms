<?php

    include_once 'sms.php';
    class User{
        protected $name;
        protected $phone;
        protected $pin;
        protected $balance;

        function __construct($phone)
        {
            $this->phone = $phone;
        }

        //setters and getters
        public function setName($name){
            $this->name = $name;;
        }

        public function getName(){
            return $this->name;
        }

        public function getPhone(){
            return $this->phone;
        }

        public function setPin($pin){
            $this->pin = $pin;;
        }

        public function getPin(){
            return $this->pin;
        }

        public function setBalance($balance){
            $this->balance = $balance;
        }

        public function getBalance(){
            return $this->balance;
        }

        public function register($pdo){
           try{

                $hashedPin = password_hash($this->getPin(), PASSWORD_DEFAULT);//hash the pin

                $stmt = $pdo->prepare("INSERT INTO user (name, pin, phone, balance) VALUES(?,?,?,?)");
                $stmt->execute([$this->getName(),$hashedPin, $this->getPhone(),$this->getBalance()]);

                //send an sms to a user 
                //$sms = new Sms($this->getPhone());
                //$sms->sendSMS("You have been registered",$this->getPhone());

           }catch(PDOException $e){
                echo $e->getMessage();
           }
        }

        public function isUserRegistered($pdo){
           $stmt = $pdo->prepare("SELECT name FROM user WHERE phone=?");
           $stmt->execute([$this->getPhone()]);
           if(count($stmt->fetchAll()) > 0){
               return true;
           }else{
               return false;
           }
        }

        public function getUserName($pdo){
            $stmt = $pdo->prepare("SELECT name FROM user WHERE phone=?");
            $stmt->execute([$this->getPhone()]);
            $row = $stmt->fetch();
            return $row['name'];
        }

        public function getUserId($pdo){
           $stmt = $pdo->prepare("SELECT uid FROM user WHERE phone=?");
           $stmt->execute([$this->getPhone()]);
           $row = $stmt->fetch();
           return $row['uid'];
        }

        public function isPinVerified($pdo){
           $stmt = $pdo->prepare("SELECT pin FROM user WHERE phone=?");
           $stmt->execute([$this->getPhone()]);
           $row = $stmt->fetch();
           if($row == null){
               return false;
           }

           if(password_verify($this->getPin(),$row['pin'])){
               return true;
           }

           return false;
        }

        public function getUserBalance($pdo){
            $stmt = $pdo->prepare("SELECT balance FROM user WHERE phone=?");
            $stmt->execute([$this->getPhone()]);
            $row = $stmt->fetch();
            return $row['balance'];
        }

    }
