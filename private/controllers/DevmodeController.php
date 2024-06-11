<?php

class DevmodeController {
        
    protected $dbConnection;
    protected $logger;

    public function __construct($dbConnection, $logger)
    {
        $this->dbConnection = $dbConnection;
        $this->logger = $logger;
    }
    
    public function getDevMode() {
        $devMode = new Devmode($this->dbConnection);
        $devModeStatus = $devMode->getDevModeStatus();
        ResponseHandler::sendResponse('success', ['devmode' => $devModeStatus], SUCCESS_OK);
    }
    
    public function toggleDevMode() {
        $devMode = new Devmode($this->dbConnection);

        $result = $devMode->toggleDevMode();
        if ($result) {
            ResponseHandler::sendResponse('success', ['message' => 'Devmode toggled'], SUCCESS_OK);
        } else {
            ResponseHandler::sendResponse('error', ['message' => 'Failed to toggle devmode'], ERROR_INTERNAL_SERVER);
        }
    }

    public function toggleDevModeValue(string $value) {
        $devMode = new Devmode($this->dbConnection);
        if($value != null){
            if($value == 'true' || $value == 'false' || $value == '1' || $value == '0'){
                $bool = $value == 'true' || $value == '1' ? true : false;
                $result = $devMode->toggleDevModeFromValue($bool);
                if ($result) {
                    ResponseHandler::sendResponse('success', ['message' => 'Devmode status updated'], SUCCESS_OK);
                } else {
                    ResponseHandler::sendResponse('error', ['message' => 'Unable to update devmode status'], ERROR_INTERNAL_SERVER);
                }
            } else {
                ResponseHandler::sendResponse('error', ['message' => $value . ' is not a true or false value'], ERROR_INTERNAL_SERVER);
            }
        } else {
            ResponseHandler::sendResponse('error', ['message' => 'Value for toggle cannot be null'], ERROR_INTERNAL_SERVER);
        }
    }
}
?>