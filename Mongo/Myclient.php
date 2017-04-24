<?php
/***************************************************************************
 * 
 * Copyright (c) 2017 Baidu.com, Inc. All Rights Reserved
 * 
 **************************************************************************/
 
 
 
/**
 * @file Mongo/Myclient.php
 * @author zhangwei26(com@baidu.com)
 * @date 2017/04/24 16:01:16
 * @brief 
 *  
 **/

#namespace Mongo;
use MongoDB\Driver\Manager;
use MongoDB\Client;

class Myclient{
        static $instance;
        protected $client;

        private $_server = "127.0.0.1";
        private $_port = "27017";
        private $_user = "ez";
        private $_pwd = "cptbtptp";
        public static function getInstance(){
                if (!static::$instance) {
                        static::$instance = new Myclient();
                }

                return static::$instance;
        }

        private function __construct(){
                $this->client = new Client(sprintf("mongodb://%s:%s", $this->_server, $this->_port), array("username" => $this->_user, "password" => $this->_pwd = "cptbtptp"));
        }

        public function getClient(){
                return $this->client;
        }
}





/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
