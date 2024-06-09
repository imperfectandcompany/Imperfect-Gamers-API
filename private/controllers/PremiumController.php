<?php

class PremiumController
{
    protected $dbManager;
    private $logger;

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
    }



    protected static function getInputStream()
    {
        return file_get_contents('php://input');
    }

    /**
     * Utility function to check if the given input fields are set and not empty.
     * Returns an error message if any of the fields are missing.
     */
    function checkInputFields($inputFields, $postBody)
    {
        $returnFlag = true;
        foreach ($inputFields as $field) {
            if (!isset($postBody->{$field}) || empty($postBody->{$field})) {
                $error = "Error: " . ucfirst($field) . " field is required";
                ResponseHandler::sendResponse('error', ['message' => $error], ERROR_INVALID_INPUT);
                $returnFlag = false;
            }
        }
        return $returnFlag;
    }

    public function createPremiumUser()
    {
        $postBody = json_decode(static::getInputStream(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('json_decode_error', ['error' => json_last_error_msg()]);
            ResponseHandler::sendResponse('error', ['message' => json_last_error_msg()], 400);
            return;
        }

        if (!$this->checkInputFields(['user_id', 'premium_status'], $postBody)) {
            return;  // Response is handled inside checkInputFields
        }

        $userId = $postBody['user_id'];


        try {
            $getSteamId = $this->hasSteam($userId);
        } catch (Exception $e) {
            $this->logger->log($userId, 'database_error', ['userId' => $userId]);
        }

        $dbSteamId = $getSteamId['steamId'];

        // Validate if the user exists and has steam integrated
        if (!$dbSteamId) {
            $this->logger->log($userId, 'missing_steam', ['userId' => $userId]); // Log invalid user attempt
            ResponseHandler::sendResponse('error', ['message' => 'User ID does not exist or is missing a steam account'], 404);
            return;
        }

        // Check if incoming User ID and steam ID match with database for integrity
        if ($dbSteamId !== $postBody['steam_id']) {
            $this->logger->log($userId, 'invalid_steam_id', ['userId' => $userId, 'incomingSteamId' => $postBody['steam_id'], 'dbSteamId' => $dbSteamId]); // Log invalid steam ID attempt
            ResponseHandler::sendResponse('error', ['message' => 'Invalid Steam ID'], 400);
            return;
        }

        try {
            $premiumStatus = $postBody['premium_status'];

            $userModel = new User($this->tertiaryConnection);
            if ($userModel->setPremiumStatus($userId, $premiumStatus)) {
                $this->logger->log($userId, 'create_premium_user', ['status' => $premiumStatus]);
                ResponseHandler::sendResponse('success', ['message' => 'User added to premium list successfully'], 200);
            } else {
                throw new Exception('Failed to add user to premium list');
            }
        } catch (Exception $e) {
            $this->logger->log($userId, 'database_error', ['error' => $e->getMessage()]);
            ResponseHandler::sendResponse('error', ['message' => 'Database error: ' . $e->getMessage()], 500);
            return;
        }
    }


    public function removePremiumUser($userId)
    {
        // Directly use $userId passed as a parameter
        if (!isset($userId) || empty($userId)) {
            ResponseHandler::sendResponse('error', ['message' => 'User ID is required'], 400);
            return;
        }

        $userModel = new User($this->tertiaryConnection);
        if ($userModel->removePremiumStatus($userId)) {
            $this->logger->log($userId, 'remove_premium_user', ['status' => 'success']);
            ResponseHandler::sendResponse('success', ['message' => 'User removed from premium list successfully'], 200);
        } else {
            $this->logger->log($userId, 'remove_premium_user_failed', ['status' => 'failure']);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to remove user from premium list'], 500);
        }
    }

    public function checkPremiumStatus(int $userId)
    {
        if (!$userId) {
            $this->logger->log(0, 'invalid_user_id', ['userId' => $userId]);
            return ResponseHandler::sendResponse('error', ['message' => 'Invalid user ID provided'], 400);
        }
    
        try {

        $userModel = new User($this->dbConnection);
        $steamCheck = $userModel->hasSteam($userId);

        
            if (!$steamCheck['hasSteam']) {
                $this->logger->log($userId, 'steam_not_linked', ['userId' => $userId]);
                return ResponseHandler::sendResponse('error', ['message' => 'Steam account not linked'], 404);
            }

            $steamId = $steamCheck['steamId'];
            $isPremium = $this->checkClassPremiumStatus($steamId);
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

    private function checkClassPremiumStatus($steamId)
    {

        // The 'is_premium' status is stored in the 'PlayerStats' table under 'IsVip' column
        try {
            $query = "SELECT IsVip FROM PlayerStats WHERE SteamID = :steamId";
            $params = [':userId' => $steamId];
            $result = $this->tertiaryConnection->query($query, $params);
    
            if (!empty($result) && isset($result[0]['IsVip'])) {
                $isPremium = (bool) $result[0]['IsVip'];
                $this->logger->log($steamId, 'check_premium_status', ['status' => $isPremium]);
                return $isPremium;
            } else {
                // Handle the case where $result is null or an empty array
                $this->logger->log($steamId, 'steam_not_found', ['userId' => $steamId]);
                throw new Exception('Steam not found');
            }
        } catch (Exception $e) {
            $this->logger->log($steamId, 'database_error', ['error' => $e->getMessage()]);
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }
    

    // private function checkClassPremiumStatus($userId)
    // {
    //     // Convert the $userId parameter to an integer if it's passed as a string
    //     $userId = intval($userId);

    //     if (!$userId) {
    //         throw new Exception('Invalid user ID provided');
    //     }

    //     // The 'is_premium' status is stored in the 'PlayerStats' table under 'IsVip' column
    //     try {
    //         $query = "SELECT IsVip FROM PlayerStats WHERE SteamID = :userId";
    //         $params = [':userId' => $userId];
    //         $result = $this->tertiaryConnection->query($query, $params);

    //         if (!empty($result) && isset($result[0]['IsVip'])) {
    //             $isPremium = (bool) $result[0]['IsVip'];
    //             $this->logger->log($userId, 'check_premium_status', ['status' => $isPremium]);
    //             return $isPremium;
    //         } else {
    //             throw new Exception('User not found');
    //         }
    //     } catch (Exception $e) {
    //         $this->logger->log($userId, 'database_error', ['error' => $e->getMessage()]);
    //         throw new Exception('Database error: ' . $e->getMessage());
    //     }
    // }

    public function listAllPremiumUsers()
    {
        $userModel = new User($this->tertiaryConnection);
        $premiumUsers = $userModel->getAllPremiumUsers();
        ResponseHandler::sendResponse('success', ['premium_users' => $premiumUsers], 200);
    }

    private function updatePremiumUser(int $userId, bool $premiumStatus)
    {
        $postBody = json_decode(static::getInputStream(), true);

        // Check if required fields are present
        if (!$this->checkInputFields(['steam_id', 'username', 'email'], $postBody)) {
            return;  // `checkInputFields` already sends the response
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log($userId, 'json_decode_error', ['error' => json_last_error_msg()]); // Log JSON decode error
            ResponseHandler::sendResponse('error', ['message' => json_last_error_msg()], 400);
            return;
        }

        // Validate user data with consolidated checks
        $validationResults = $this->validateUserDataConsolidated($userId, $postBody['username'], $postBody['email'], $postBody['steam_id']);
        foreach ($validationResults as $key => $result) {
            if (!$result['isValid']) {
                $this->logger->log($userId, 'validation_error', ['field' => $key, 'message' => $result['message']]);
                ResponseHandler::sendResponse('error', ['message' => $result['message']], 400);
                return;
            }
        }

        $userModel = new User($this->tertiaryConnection);  // Use the appropriate connection

        // Update premium status
        if ($userModel->updatePremiumStatus($userId, $premiumStatus)) {
            $this->logger->log($userId, 'update_premium_status', ['status' => $premiumStatus]); // Log successful update
            ResponseHandler::sendResponse('success', ['message' => 'Premium status updated successfully'], 200);
            return;
        } else {
            $this->logger->log($userId, 'failed_update_premium_status', ['status' => $premiumStatus]); // Log failed update attempt
            ResponseHandler::sendResponse('error', ['message' => 'Failed to update premium status'], 500);
            return;
        }
    }


    private function setPremiumStatusSharpTimer($steamId, $isVip)
    {
        $vipStatus = $isVip ? 1 : 0;

        $updateQuery = "UPDATE PlayerStats SET IsVip = :vipStatus WHERE SteamID = :steamId";
        $updateParams = [
            ':vipStatus' => $vipStatus,
            ':steamId' => $steamId
        ];

        $updateResult = $this->tertiaryConnection->query($updateQuery, $updateParams);
        if ($updateResult) {
            return ['success' => true, 'message' => 'VIP status successfully updated.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update VIP status. Please check the database connection and data integrity.'];
        }
    }

    /**
     * Checks if a username already exists in the database.
     *
     * This method queries the database to determine if a specific username is already
     * associated with a user account. It returns true if the username exists, false otherwise.
     *
     * @param string $username The username to check for existence.
     * @return bool True if the username exists, false otherwise.
     */
    private function doesUsernameExist($username)
    {
        // Query to check if the user has a username

        $result = $this->dbConnection->query('SELECT username FROM profiles WHERE username = :username', [
            ':username' => $username
        ]);

        // Return true if the username is found, false otherwise
        return !empty($result) && isset($result[0]['username']);
    }

    private function validateUserDataConsolidated($userId, $username, $email, $steamId)
    {
        // Specify the table names or use aliases for clarity
        $query = "SELECT users.id AS user_id, profiles.username, users.email, profiles.steam_id_64 FROM users 
                  LEFT JOIN profiles ON users.id = profiles.user_id
                  WHERE users.id = :userId";
        $params = [':userId' => $userId];
        $result = $this->dbConnection->query($query, $params);

        $validationResult = [
            'userId' => ['isValid' => false, 'message' => 'User ID not found.'],
            'username' => ['isValid' => false, 'message' => 'Username does not match.'],
            'email' => ['isValid' => false, 'message' => 'Email does not match.'],
            'steamId' => ['isValid' => false, 'message' => 'Steam ID does not match.']
        ];

        if (!empty($result)) {
            $validationResult['userId']['isValid'] = true;
            $validationResult['userId']['message'] = 'User ID is valid.';
            $validationResult['username']['isValid'] = ($result[0]['username'] === $username);
            $validationResult['username']['message'] = $validationResult['username']['isValid'] ? 'Username is valid.' : 'Username does not match.';
            $validationResult['email']['isValid'] = (strtolower($result[0]['email']) === strtolower($email));
            $validationResult['email']['message'] = $validationResult['email']['isValid'] ? 'Email is valid.' : 'Email does not match.';
            $validationResult['steamId']['isValid'] = (isset($result[0]['steam_id_64']) && $result[0]['steam_id_64'] === $steamId);
            $validationResult['steamId']['message'] = $validationResult['steamId']['isValid'] ? 'Steam ID is valid.' : 'Steam ID does not match.';
        }

        return $validationResult;
    }

    /**
     * Checks if a player exists in the PlayerStats table.
     *
     * @param string $steamId The Steam ID to check for.
     * @return bool Returns true if the player exists, false otherwise.
     */
    private function playerExists($steamId)
    {
        // Prepare the SQL query to check if the Steam ID exists in the PlayerStats table
        $query = "SELECT 1 FROM PlayerStats WHERE SteamID = :steamId";
        $params = [':steamId' => $steamId];

        // Execute the query
        $result = $this->tertiaryConnection->query($query, $params);

        // Check if any rows are returned
        return !empty($result);
    }

    /**
     * Checks if a player is marked as a Premium in the PlayerStats table. (Premium)
     *
     * @param string $steamId The Steam ID to check.
     * @return bool|null Returns true if the player is a Premium, false if not a Premium, and null if the player doesn't exist.
     */
    private function isPlayerPremiumSharpTimer($steamId)
    {
        // Prepare the SQL query to check the Premium status of the Steam ID in the PlayerStats table
        $query = "SELECT IsVip FROM PlayerStats WHERE SteamID = :steamId";
        $params = [':steamId' => $steamId];

        // Execute the query
        $result = $this->tertiaryConnection->query($query, $params);

        // Check if any rows are returned and return the Premium status
        if (!empty($result)) {
            // IsVip is stored as an integer (1 for true, 0 for false)
            return (bool) $result[0]['IsVip'];
        } else {
            // Return null if no record is found, indicating the player does not exist
            return null;
        }
    }

    /**
     * Checks if a Steam ID exists in the sa_admins table and retrieves their flags.
     *
     * @param string $steamId The Steam ID to check for.
     * @return string|null Returns the flags associated with the premium membership if found, or null if not found.
     */
    private function gePremiumFlagsSimpleAdmin($steamId)
    {
        // Prepare the SQL query to retrieve the flags for a given Steam ID from the sa_admins table
        $query = "SELECT flags FROM sa_admins WHERE player_steamid = :steamId";
        $params = [':steamId' => $steamId];

        // Execute the query
        $result = $this->secondaryConnection->query($query, $params);

        // Check if any rows are returned and return the flags
        if (!empty($result)) {
            return $result[0]['flags'];
        } else {
            // Return null if no premium record is found for the Steam ID
            return null;
        }
    }

    /**
     * Checks if a user exists in PlayerStats and sa_admins tables based on their Steam ID.
     * CHECKS BOTH SHARPTIMER AND SIMPLEADMIN
     * @param string $steamId The Steam ID to check.
     * @return array Returns a message and data depending on the user's existence in the tables.
     */
    private function checkUserAndPremiumStatus($steamId)
    {
        // Check for user in PlayerStats
        $playerQuery = "SELECT 1 FROM PlayerStats WHERE SteamID = :steamId";
        $playerParams = [':steamId' => $steamId];
        $playerResult = $this->tertiaryConnection->query($playerQuery, $playerParams);

        if (empty($playerResult)) {
            return ['message' => 'User does not exist in PlayerStats.', 'data' => null];
        }

        // User exists in PlayerStats, now check sa_admins
        $premiumQuery = "SELECT flags FROM sa_admins WHERE player_steamid = :steamId";
        $premiumParams = [':steamId' => $steamId];
        $premiumResult = $this->secondaryConnection->query($premiumQuery, $premiumParams);

        if (!empty($premiumResult)) {
            // User exists in sa_admins, return flags
            return ['message' => 'User exists in PlayerStats and is a Premium in sa_admins.', 'data' => $premiumResult[0]['flags']];
        } else {
            // User exists in PlayerStats but not in sa_admins
            return ['message' => 'User exists in PlayerStats but not as a Premium user in sa_admins.', 'data' => null];
        }
    }

    /**
     * Inserts or updates a user in the sa_admins table based on their existence.
     *
     * @param string $steamId The Steam ID of the user to insert or update.
     * @param string $username The username of the user.
     * @return array Returns a message indicating the outcome of the operation.
     */
    private function upsertPremium($steamId, $username)
    {
        // Check if the user already exists in sa_admins
        $checkQuery = "SELECT 1 FROM sa_admins WHERE player_steamid = :steamId";
        $checkParams = [':steamId' => $steamId];
        $checkResult = $this->dbObject->query($checkQuery, $checkParams);

        // Prepare the common parameters
        $flags = '#css/premium';
        $immunity = 10;

        if (!empty($checkResult)) {
            // Update the existing Premium record
            $updateQuery = "UPDATE sa_admins SET player_name = :username, flags = :flags, immunity = :immunity WHERE player_steamid = :steamId";
            $updateParams = [
                ':steamId' => $steamId,
                ':username' => $username,
                ':flags' => $flags,
                ':immunity' => $immunity
            ];

            $updateResult = $this->secondaryConnection->query($updateQuery, $updateParams);
            if ($updateResult) {
                return ['success' => true, 'message' => 'User successfully updated as an Premium.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update user as an Premium. Please check the database connection and data integrity.'];
            }
        } else {
            // Insert the new Premium record
            $insertQuery = "INSERT INTO sa_admins (player_steamid, player_name, flags, immunity) VALUES (:steamId, :username, :flags, :immunity)";
            $insertParams = [
                ':steamId' => $steamId,
                ':username' => $username,
                ':flags' => $flags,
                ':immunity' => $immunity
            ];

            $insertResult = $this->secondaryConnection->query($insertQuery, $insertParams);
            if ($insertResult) {
                return ['success' => true, 'message' => 'User successfully inserted as an Premium.'];
            } else {
                return ['success' => false, 'message' => 'Failed to insert user as an Premium. Please check the database connection and data integrity.'];
            }
        }
    }

    /**
     * Removes a user from the sa_admins table based on their Steam ID.
     *
     * @param string $steamId The Steam ID of the user to remove.
     * @return array Returns a message indicating the outcome of the operation.
     */
    public function removePremiumSimpleAdmin($steamId)
    {
        // Check if the user exists in sa_admins
        $checkQuery = "SELECT 1 FROM sa_admins WHERE player_steamid = :steamId";
        $checkParams = [':steamId' => $steamId];
        $checkResult = $this->secondaryConnection->query($checkQuery, $checkParams);

        if (empty($checkResult)) {
            return ['success' => false, 'message' => 'User does not exist as an admin.'];
        }

        // Remove the user from sa_admins
        $deleteQuery = "DELETE FROM sa_admins WHERE player_steamid = :steamId";
        $deleteParams = [':steamId' => $steamId];

        $deleteResult = $this->secondaryConnection->query($deleteQuery, $deleteParams);
        if ($deleteResult) {
            return ['success' => true, 'message' => 'User successfully removed from admin.'];
        } else {
            return ['success' => false, 'message' => 'Failed to remove user from admin. Please check the database connection and data integrity.'];
        }
    }



}
