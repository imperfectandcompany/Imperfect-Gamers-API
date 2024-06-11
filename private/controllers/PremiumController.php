<?php
include ($GLOBALS['config']['private_folder'] . '/classes/class.premium.php');

class PremiumController
{
    protected $dbManager;

    private $dbConnection;

    private $logger;
    private $premiumModel;

    private $secondaryConnection;

    private $tertiaryConnection;

    public function __construct($dbManager, $logger)
    {
        // Connect specifically to the 'igfastdl_imperfectgamers' database for website related data
        $this->dbConnection = $dbManager->getConnection('default');
        // Connect specifically to the 'simple_admins' database (gameserver database server default) for user role management
        $this->secondaryConnection = $dbManager->getConnection('gameserver');
        // Connect specifically to the 'sharptimer' database for premium user management
        $this->tertiaryConnection = $dbManager->getConnectionByDbName('gameserver', 'sharptimer');
        // logging
        $this->logger = $logger;

        $this->premiumModel = new Premium($this->secondaryConnection, $this->tertiaryConnection);  // initialize Premium with the correct DB connections
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


    public function updatePremiumUser(int $userId, bool $premiumStatus)
    {
        // Parse the request body for JSON input
        $putBody = json_decode(static::getInputStream(), true);

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

        // Make sure sent values match 1:1 from the website database
        $validationResult = $this->validateUserDataConsolidated($userId, $putBody['username'], $putBody['email'], $putBody['steam_id']);
        // Check the validation result for userId
        if (!$validationResult['userId']['isValid']) {
            $this->logger->log($userId, 'validation_error', ['message' => $validationResult['userId']['message']]);
            // Return an error if the user cannot be found
            return ResponseHandler::sendResponse('error', ['message' => $validationResult['userId']['message']], ERROR_NOT_FOUND);
        }
        $errorMessages = $this->parseValidationErrors($validationResult);

        if (!empty($errorMessages)) {

            // Concatenate errors with a comma and ensure the message ends with a single period.
            $errorMessage = '{' . implode(', ', $errorMessages) . '}';
            // Ensure only one final period by trimming any trailing space and periods before adding the final period
            $errorMessage = rtrim($errorMessage, '. ') . '';

            $this->logger->log($userId, 'validation_error', $errorMessages);
            return ResponseHandler::sendResponse('error', ['message' => 'Validation for associated User ID Data failed', 'errors' => $errorMessage], ERROR_INVALID_INPUT);
        }
        if ($this->premiumModel->updatePremiumStatus($putBody['steam_id'], $putBody['username'], $premiumStatus)) {
            $this->logger->log($userId, 'update_premium_status', ['status' => 'success']);
            return ResponseHandler::sendResponse('success', ['message' => 'Premium status updated successfully'], 200);
        } else {
            $this->logger->log($userId, 'update_premium_status_failed', ['status' => 'failed']);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to update premium status'], 500);
        }
    }


    public function checkUserExistsInServer(int $userId) {

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


    public function listAllPremiumUsers() {
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
    private function validateUserDataConsolidated($userId, $username, $email, $steamId)
    {
        $query = "SELECT users.id AS user_id, profiles.username, users.email, profiles.steam_id_64 FROM users 
                  LEFT JOIN profiles ON users.id = profiles.user_id
                  WHERE users.id = :userId";
        $params = [':userId' => $userId];

        $result = $this->dbConnection->query($query, $params);

        if (empty($result)) {
            return [
                'userId' => ['isValid' => false, 'message' => 'User ID not found.'],
                'username' => ['isValid' => false, 'message' => 'No user data to compare username.'],
                'email' => ['isValid' => false, 'message' => 'No user data to compare email.'],
                'steamId' => ['isValid' => false, 'message' => 'No user data to compare Steam ID.']
            ];
        }
        $userData = $result[0]; // Assume the first result is the relevant one

        $validationResult = [
            'userId' => ['isValid' => true, 'message' => 'User ID is valid.'],
            'username' => ['isValid' => isset($userData['username']) && strtolower($userData['username']) === strtolower($username), 'message' => 'Username does not match.'],
            'email' => ['isValid' => isset($userData['email']) && strtolower($userData['email']) === strtolower($email), 'message' => 'Email does not match.'],
            'steamId' => ['isValid' => isset($userData['steam_id_64']) && $userData['steam_id_64'] === $steamId, 'message' => 'Steam ID does not match.']
        ];

        // Ensure all fields are checked for presence to avoid comparing against non-existent data
        foreach (['username', 'email', 'steam_id_64'] as $field) {
            if (!isset($userData[$field])) {
                $validationResult[$field] = ['isValid' => false, 'message' => ucfirst($field) . ' is not set in the database.'];
            }
        }

        return $validationResult;
    }



}
