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
    function sendResponse($status, $data, $httpCode)
    {
        // Instead of printing, return the data for assertion in tests
        return ['status' => $status, 'data' => $data, 'httpCode' => $httpCode];
    }

}
