<?php
    class Transaction{
        protected $amount;
        protected $transaction_type;

        function __construct($amount, $transaction_type)
        {
            $this->amount = $amount;
            $this->transaction_type = $transaction_type;
        }

        public function getAmount(){
            return $this->amount;
        }

        public function getTransactionType(){
            return $this->transaction_type;
        }

        public function sendMoney($pdo, $uid, $ruid, $newSenderBalance, $newReceiverBalance){

            $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);

            try{

                $pdo->beginTransaction();

                $stmtT = $pdo->prepare("INSERT INTO transaction (amount, uid, ruid, ttype) VALUES(?,?,?,?)");
                $stmtU = $pdo->prepare("UPDATE USER SET balance=? WHERE uid=?");//executed twice with different values

                $stmtT->execute([$this->getAmount(), $uid, $ruid, $this->getTransactionType()]);
                $stmtU->execute([$newSenderBalance,$uid]);//sender
                $stmtU->execute([$newReceiverBalance,$ruid]);//receiver

                $pdo->commit();

                return true;

            }catch(Exception $e){
                $pdo->rollBack();
                return "An error was encountered";
            }

        }

        public function withDrawCash($pdo, $uid, $aid, $newBalance){
            $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT,FALSE);  
            //$pdo->setAttribute (PDO::ATTR_AUTOCOMMIT,FALSE)          
            try {
                $pdo->beginTransaction();
                
                //prepare queries
                $stmtT = $pdo->prepare("INSERT INTO transaction (amount, uid, aid, ttype) VALUES (?,?,?,?)");
                $stmtU = $pdo->prepare("UPDATE user SET balance=? WHERE uid=?");
                
                //execute queries 
                $stmtT->execute([$this->getAmount(), $uid, $aid, $this->getTransactionType()]);
                $stmtU->execute([$newBalance,$uid]);

                $pdo->commit();

                return true;
            } catch (PDOException $e){
                $pdo->rollBack();
                //echo $e->getMessage();
                return "An error occurred";
            }
        }
    }

