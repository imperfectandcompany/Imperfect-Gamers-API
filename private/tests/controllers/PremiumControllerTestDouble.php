<?php

// tests/controllers/PremiumControllerTestDouble.php:

include_once($GLOBALS['config']['private_folder'] . '/controllers/PremiumController.php');

class PremiumControllerTestDouble extends PremiumController
{
    protected static $inputStream;

    public function __construct($dbManager, $logger)
    {
        parent::__construct($dbManager, $logger);
    }

    public static function setInputStream($input = 'php://input')
    {
        static::$inputStream = $input;
    }

    protected static function getInputStream()
    {
        return static::$inputStream;
    }

}
