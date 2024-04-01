<?php
    class MySingleton {
        private static $myInstance;
    
        private function __construct() {}
    
        public static function getInstance() {
            if (!self::$myInstance) {
                self::$myInstance = new self();
            }
            return self::$myInstance;
        }
    }
    $oneTonn1 = Singleton::getInstance();
    $oneTonn2 = Singleton::getInstance();
    var_dump($oneTonn1 === $oneTonn2);    
?>