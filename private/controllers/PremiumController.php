<?php
include($GLOBALS['config']['private_folder'] . '/classes/class.premium.php');

class PremiumController
{
    protected $dbManager;

    private $dbConnection;

    private $logger;
    private $premiumModel;
 
    private $secondaryConnection;

    private $tertiaryConnection;
    private $quaternaryConnection;

    public function __construct($dbManager, $logger)
    {
        // Connect specifically to the 'igfastdl_imperfectgamers' database for website related data
        $this->dbConnection = $dbManager->getConnection('default');
        // Connect specifically to the 'simple_admins' database (gameserver database server default) for user role management
        $this->secondaryConnection = $dbManager->getConnection('gameserver');
        // Connect specifically to the 'sharptimer' database for premium user management
        $this->tertiaryConnection = $dbManager->getConnectionByDbName('gameserver', 'sharptimer');
        $this->quaternaryConnection = $dbManager->getConnectionByDbName('gameserver', 'whitelist');

        // logging
        $this->logger = $logger;

        $this->premiumModel = new Premium($this->secondaryConnection, $this->tertiaryConnection, $this->quaternaryConnection);  // initialize Premium with the correct DB connections
    }

    public function checkPremiumStatus(int $userId)
    {
        if (!$userId) {
            $this->logger->log(0, 'invalid_user_id', ['userId' => $userId]);
            return ResponseHandler::sendResponse('error', ['message' => 'Invalid user ID provided'], 400);
        }

        try {

            $userModel = new User($this->dbConnection);


            $usernameCheck = $userModel->getUsernameById($userId);


            if (!$usernameCheck) {
                $this->logger->log($userId, 'username_not_set', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'User does not have a username (may not even exist)'], 404);
            }

            $steamCheck = $userModel->hasSteam($userId);


            if (!$steamCheck['hasSteam']) {
                $this->logger->log($userId, 'invalid_steam_id', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Steam account not found'], 404);
            }

            $steamId = $steamCheck['steamId'];

            if (!preg_match('/^7656119[0-9]{10}+$/', $steamId)) {
                $this->logger->log($userId, 'invalid_steam_id', ['userid' => $userId, 'steam_id' => $steamId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Invalid Steam ID format'], ERROR_INVALID_INPUT);
            }

            $isPremium = $this->premiumModel->checkPremiumStatusFromSteamId($steamId);
            if ($isPremium !== null) {
                $this->logger->log($userId, 'check_premium_status', ['status' => $isPremium]);
                return ResponseHandler::sendResponse('success', ['user_id' => $userId, 'is_premium' => $isPremium], 200);
            } else {
                $this->logger->log($userId, 'steam_not_found', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Steam not found'], 404);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->log($userId, 'database_error', ['error' => $errorMessage]);
            return ResponseHandler::sendResponse('error', ['message' => $errorMessage], 500);
        }
    }
    
    
    
    
    public function checkPremiumStatusEmail(string $email)
    {
//        if (!$userId) {
  //          $this->logger->log(0, 'invalid_user_id', ['userId' => $userId]);
           // return ResponseHandler::sendResponse('error', ['message' => 'Invalid email provided'], 400);
    //    }

        try {

            $userModel = new User($this->dbConnection);
            
            $userId = $userModel->getUserByEmail($email);


            if (!$userId) {
                $this->logger->log(0, 'email_not_set', ['userId' => 0]);
                return ResponseHandler::sendResponse('error', ['message' => 'Email does not match a user in the database.'], 404);
            }
            
            // OK User exists.. but do they have a username? (Completed onboarding)
            
            $usernameCheck = $userModel->getUsernameById($userId);
            
            if (!$usernameCheck) {
                $this->logger->log($userId, 'username_not_set', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'User does not have a username (may not even exist)'], 404);
            }

            $steamCheck = $userModel->hasSteam($userId);
            
            // If they don't have steam, no way they have premium. We can stop here.
            
            if (!$steamCheck['hasSteam']) {
                $this->logger->log($userId, 'invalid_steam_id', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Steam account not found'], 404);
            }

            $steamId = $steamCheck['steamId'];
            
            // This shouldn't happened, means there is a bigger issue.

            if (!preg_match('/^7656119[0-9]{10}+$/', $steamId)) {
                $this->logger->log($userId, 'invalid_steam_id', ['userid' => $userId, 'steam_id' => $steamId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Invalid Steam ID format'], ERROR_INVALID_INPUT);
            }

            $isPremium = $this->premiumModel->checkPremiumStatusFromSteamId($steamId);
            if ($isPremium !== null) {
                $this->logger->log($userId, 'check_premium_status', ['status' => $isPremium]);
                return ResponseHandler::sendResponse('success', ['user_id' => $userId, 'is_premium' => $isPremium], 200);
            } else {
                $this->logger->log($userId, 'steam_not_found', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Steam not found'], 404);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->log($userId, 'database_error', ['error' => $errorMessage]);
            return ResponseHandler::sendResponse('error', ['message' => $errorMessage], 500);
        }
    }
    
    
    
    
    
    
    

    public function updatePremiumUser(int $userId, bool $premiumStatus)
    {
        // Parse the request body for JSON input
        $putBody = json_decode(file_get_contents('php://input'), true);

        // Validate input fields
        $missingFields = $this->validateInputFields(['username', 'email', 'steam_id'], $putBody);
        if (!empty($missingFields)) {
            $errorMessage = 'Missing required fields: ' . implode(', ', $missingFields);
            $this->logger->log($userId, 'validation_error', ['message' => $errorMessage]);
            return ResponseHandler::sendResponse('error', ['message' => $errorMessage], ERROR_INVALID_INPUT);
        }

        if (!preg_match('/^7656119[0-9]{10}+$/', $putBody['steam_id'])) {
            $this->logger->log($userId, 'invalid_steam_id', ['steam_id' => $putBody['steam_id']]);
            return ResponseHandler::sendResponse('error', ['message' => 'Invalid Steam ID'], ERROR_INVALID_INPUT);
        }

        // Validate user data
        $validationResult = $this->validateUserDataConsolidated($userId, $putBody['username'], $putBody['steam_id']);

        // Check the validation result for userId
        if (!$validationResult['userId']['isValid']) {
            $this->logger->log($userId, 'validation_error', ['message' => $validationResult['userId']['message']]);
            return ResponseHandler::sendResponse('error', ['message' => $validationResult['userId']['message']], ERROR_NOT_FOUND);
        }

        // Check the validation result for username
        if (!$validationResult['username']['isValid']) {
            $this->logger->log($userId, 'validation_error', ['message' => $validationResult['username']['message']]);
            return ResponseHandler::sendResponse('error', ['message' => $validationResult['username']['message']], ERROR_INVALID_INPUT);
        }

        // Check the validation result for steamId
        if (!$validationResult['steamId']['isValid']) {
            $this->logger->log($userId, 'validation_error', ['message' => $validationResult['steamId']['message']]);
            return ResponseHandler::sendResponse('error', ['message' => $validationResult['steamId']['message']], ERROR_INVALID_INPUT);
        }

        // $errorMessages = $this->parseValidationErrors($validationResult);
        // if (!empty($errorMessages)) {
        //     $errorMessage = '{' . implode(', ', $errorMessages) . '}';
        //     $errorMessage = rtrim($errorMessage, '. ') . '.';
        //     $this->logger->log($userId, 'validation_error', $errorMessages);
        //     return ResponseHandler::sendResponse('error', ['message' => 'Validation for associated User ID Data failed', 'errors' => $errorMessage], ERROR_INVALID_INPUT);
        // }

        try {
            // Update premium status
            if ($this->premiumModel->updatePremiumStatus($putBody['steam_id'], $putBody['username'], $premiumStatus)) {
                $this->logger->log($userId, 'update_premium_status', ['status' => 'success']);

                // Record the payment (example data, adjust as necessary)
                $transactionAuditSuccess = $this->recordPayment(
                    $userId, // userId is the payer in this case - for now until gifting is introduced
                    $userId, // recipient is also the same user for this case - for now until gifting is introduced
                    $putBody['email'], // Transaction email
                    12.00, // Amount, current value until we introduce multiple packages which will necessitate dynamic
                    'USD', // Currency, current value until we introduce multiple currencies which will necessitate dynamic
                    'Tebex', // Payment method, current value until we introduce multiple vendors which will necessitate dynamic
                    $premiumStatus ? 'completed' : 'refunded', // Status, current value until we introduce multiple transaction types (refunded, canceled etc) which will necessitate dynamic
                    [] // Payment data, empty array until we find use during iterations which will later transform to structured data if necessitated
                );

                if ($transactionAuditSuccess) {
                    if($premiumStatus){
                        return ResponseHandler::sendResponse('success', ['message' => 'Premium status updated and payment recorded successfully'], 200);
                    } else {
                        return ResponseHandler::sendResponse('success', ['message' => 'Premium status revoked and refund processed successfully'], 200);
                    }

                } else {
                    if($premiumStatus){
                        return ResponseHandler::sendResponse('success', ['message' => 'Premium status updated, but failed to record payment'], 422);
                    } else {
                        return ResponseHandler::sendResponse('success', ['message' => 'Premium status revoked, but failed to process refund'], 422);
                    }
                }
            } else {
                $this->logger->log($userId, 'update_premium_status_failed', ['status' => 'failed']);
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to update premium status'], 500);
            }

        } catch (RuntimeException $e) {
                return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 200);
        }

    }

    public function recordPayment($payerUserId, $recipientUserId, $transactionEmail, $amount, $currency, $paymentMethod, $status, $paymentData)
    {
        try {
            // Prepare SQL query to insert payment details
            $query = "INSERT INTO payments (payer_user_id, recipient_user_id, transaction_email, amount, currency, payment_method, status, payment_data)
                  VALUES (:payerUserId, :recipientUserId, :transactionEmail, :amount, :currency, :paymentMethod, :status, :paymentData)";

            // Bind parameters
            $params = [
                ':payerUserId' => $payerUserId,
                ':recipientUserId' => $recipientUserId,
                ':transactionEmail' => $transactionEmail,
                ':amount' => $amount,
                ':currency' => $currency,
                ':paymentMethod' => $paymentMethod,
                ':status' => $status,
                ':paymentData' => json_encode($paymentData) // Store as JSON
            ];

            // Execute query
            $this->dbConnection->query($query, $params);

            $this->logger->log($payerUserId, 'payment_recorded', ['transaction_email' => $transactionEmail, 'amount' => $amount, 'currency' => $currency, 'status' => $status]);

            return true;
        } catch (Exception $e) {
            $this->logger->log($payerUserId, 'payment_record_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }



    public function checkUserExistsInServer(int $userId)
    {

        if (!$userId) {
            $this->logger->log(0, 'invalid_user_id', ['userId' => $userId]);
            return ResponseHandler::sendResponse('error', ['message' => 'Invalid user ID provided'], 400);
        }

        try {

            $userModel = new User($this->dbConnection);


            $usernameCheck = $userModel->getUsernameById($userId);


            if (!$usernameCheck) {
                $this->logger->log($userId, 'username_not_found', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'User does not have a username (may not even exist)'], 404);
            }

            $steamCheck = $userModel->hasSteam($userId);


            if (!$steamCheck['hasSteam']) {
                $this->logger->log($userId, 'invalid_steam_id', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Steam account not found'], 404);
            }

            $steamId = $steamCheck['steamId'];

            if (!preg_match('/^7656119[0-9]{10}+$/', $steamId)) {
                $this->logger->log($userId, 'invalid_steam_id', ['userid' => $userId, 'steam_id' => $steamId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Invalid Steam ID format'], ERROR_INVALID_INPUT);
            }

            $exists = $this->premiumModel->isPlayerExistsSharpTimer($steamId);
            return ResponseHandler::sendResponse('success', ['user_id' => $userId, 'exists' => $exists], 200);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->log($userId, 'database_error', ['error' => $errorMessage]);
            return ResponseHandler::sendResponse('error', ['message' => $errorMessage], 500);
        }
    }

    public function checkSteamExistsInServer(int $steamId)
    {

        if (!$steamId) {
            $this->logger->log(0, 'invalid_steam_id', ['steamId' => $steamId]);
            return ResponseHandler::sendResponse('error', ['message' => 'Invalid steam ID provided'], 400);
        }

        try {

            if (!preg_match('/^7656119[0-9]{10}+$/', $steamId)) {
                $this->logger->log(0, 'invalid_steam_id', ['steam_id' => $steamId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Invalid Steam ID format'], ERROR_INVALID_INPUT);
            }

            $exists = $this->premiumModel->isPlayerExistsSharpTimer($steamId);
            return ResponseHandler::sendResponse('success', ['steam_id' => $steamId, 'exists' => $exists], 200);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->log(0, 'database_error', ['error' => $errorMessage]);
            return ResponseHandler::sendResponse('error', ['message' => $errorMessage], 500);
        }
    }




    public function listAllPremiumUsers()
    {
        try {
            // Get the list of premium users with lastConnected info from the Premium class
            $premiumUsers = $this->premiumModel->getAllPremiumUsers();

            // Fetch additional information about these users from the User class
            $userModel = new User($this->dbConnection);
            $premiumUsersDetailed = $userModel->getUsersBySteamIds($premiumUsers);

            return ResponseHandler::sendResponse('success', ['premium_users' => $premiumUsersDetailed], 200);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->log('error', 'database_error', ['error' => $errorMessage]);
            return ResponseHandler::sendResponse('error', ['message' => $errorMessage], 500);
        }
    }



    protected static function getInputStream()
    {
        return file_get_contents('php://input');
    }

    private function parseValidationErrors($validationResult)
    {
        $errors = [];
        foreach ($validationResult as $field => $result) {
            if (!$result['isValid']) {
                // Append the field name and error message, remove any trailing period from the message
                $message = rtrim($result['message'], '.');
                $errors[] = ucfirst($field) . ": " . $message;
            }
        }
        return $errors;
    }

    private function validateInputFields($requiredFields, $data)
    {
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }
        return $missingFields;
    }
    private function validateUserDataConsolidated($userId, $username, $steamId)
    {
        $query = "SELECT users.id AS user_id, profiles.username, profiles.steam_id_64 
                  FROM users 
                  LEFT JOIN profiles ON users.id = profiles.user_id
                  WHERE users.id = :userId";
        $params = [':userId' => $userId];

        $result = $this->dbConnection->query($query, $params);

        if (empty($result)) {
            return [
                'userId' => ['isValid' => false, 'message' => 'User ID not found.'],
                'username' => ['isValid' => false, 'message' => 'No user data to compare username.'],
                // 'email' => ['isValid' => false, 'message' => 'No user data to compare email.'],
                'steamId' => ['isValid' => false, 'message' => 'No user data to compare Steam ID.'],
            ];
        }

        $userData = $result[0];

        $validation = [
            'userId' => ['isValid' => true, 'message' => ''],
            'username' => ['isValid' => $username === $userData['username'], 'message' => 'Username mismatch.'],
            // 'email' => ['isValid' => $email === $userData['email'], 'message' => 'Email mismatch.'],
            'steamId' => ['isValid' => $steamId === $userData['steam_id_64'], 'message' => $userData['steam_id_64']]
        ];

        return $validation;
    }




}
