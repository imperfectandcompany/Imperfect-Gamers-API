<?php

class User
{

    private $dbObject;

    /**
     * Constructor for the User class.
     *
     * @param DatabaseConnector $dbObject A database connection object
     */
    public function __construct($dbObject)
    {
        $this->dbObject = $dbObject;
    }

    /**
     * Verifies the token and returns the associated user ID if the token is valid.
     *
     * @param string $token The token to verify
     *
     * @return int|false Returns the associated user ID if the token is valid, or false if the token is invalid
     */
    public function verifyToken($token)
    {
        // Query the database for the user ID associated with the token
        $sql = "SELECT user_id FROM login_tokens WHERE token = ?";
        // Hash the token to match the stored token
        $token_hash = sha1($token);
        $result = $this->dbObject->query($sql, array($token_hash));
        // If the query returned a result, return the user ID associated with the token
        if ($result && count($result) > 0) {
            return $result[0]['user_id'];
        } else {
            // If the query did not return a result, the token is invalid
            return false;
        }
    }


    public function getUsersBySteamIds($premiumUsers) {
        if (empty($premiumUsers)) {
            return [];
        }

        // Extract the SteamIDs from the premium users array
        $steamIds = array_column($premiumUsers, 'SteamID');

        // Prepare the query to fetch user information based on SteamIDs
        $placeholders = str_repeat('?,', count($steamIds) - 1) . '?';
        $query = "SELECT u.id AS userid, p.username, p.steam_id_64 AS steamid, p.avatar, u.admin, u.verified, u.updatedAt
                  FROM users u 
                  JOIN profiles p ON u.id = p.user_id 
                  WHERE p.steam_id_64 IN ($placeholders)";

        $results = $this->dbObject->query($query, $steamIds);

        // Merge the lastConnected information with the user data
        foreach ($results as &$result) {
            foreach ($premiumUsers as $premiumUser) {
                if ($result['steamid'] === $premiumUser['SteamID']) {
                    $result['lastConnected'] = $premiumUser['lastConnected'];
                    break;
                }
            }
        }

        return $results ? $results : [];
    }


    /**
     * Checks if a given user ID has a linked Steam account.
     *
     * Queries the database to determine if the user associated with the provided user ID
     * has a linked Steam account. Returns information about the presence of a Steam account
     * and the Steam ID if available.
     *
     * @param int $userId The unique identifier of the user.
     * @return array An array containing a flag indicating whether a Steam account is linked
     *               and the Steam ID if applicable.
     */
    public function hasSteam($userId)
    {
        try {
            // Query to check if the user has a linked Steam account
            $query = 'SELECT steam_id_64 FROM profiles WHERE user_id = :id';
            $params = array(':id' => $userId);
            $result = $this->dbObject->query($query, $params);

            // Check if a Steam ID was found
            if (!empty($result) && isset($result[0]['steam_id_64'])) {
                return ['hasSteam' => true, 'steamId' => $result[0]['steam_id_64']];
            } else {
                return ['hasSteam' => false];
            }
        } catch (Exception $e) {
            throw new Exception('An unexpected error occurred.');
        }
    }



    public function isSteamIdLinked($steam_id_64)
    {

        if (!$this->isValidSteamID($steam_id_64)) {
            throw new Exception("Invalid Steam ID.");
        }

        try {
            // Query to check if the Steam ID is linked to any user
            $query = 'SELECT user_id FROM profiles WHERE steam_id_64 = :steam_id';
            $params = array(':steam_id' => $steam_id_64);
            $result = $this->dbObject->query($query, $params);

            // Check if a user ID was found
            if (!empty($result) && isset($result[0]['user_id'])) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            throw new Exception('An unexpected error occurred while checking the Steam ID.');
        }
    }


    /**
     * Links a Steam account ID to a user's profile.
     *
     * @param int $userId The user's ID.
     * @param string $steam_id_64 The Steam account ID 64.
     * @return bool Returns true if the account is successfully linked.
     * @throws Exception If the Steam ID is invalid or the update fails.
     */
    public function linkSteamAccount($user_id, $steam_id_64)
    {
        if (!$this->isValidSteamID($steam_id_64)) {
            throw new Exception("Invalid Steam ID.");
        }

        // First, check if a Steam account is already linked
        $currentSteam = $this->hasSteam($user_id);
        if ($currentSteam['hasSteam']) {
            throw new Exception("A Steam account is already linked. Unlink the current account before linking a new one.");
        }

        $steam_id = $this->steamid64_to_steamid2($steam_id_64);

        // Generate filter params using makeFilterParams function
        $params = makeFilterParams([$steam_id, $steam_id_64, $user_id]);
        try {
            // Call updateData method with generated filter params
            $updateResult = $this->dbObject->updateData(
                "profiles",
                "steam_id = :steam_id, steam_id_64 = :steam_id_64",
                "user_id = :user_id",
                $params
                //array(':steam_id' => $steam_id, ':steam_id_64' => $steam_id_64, ':user_id' => $user_id)
            );
            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Ensure common data integrity points and try again.");
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to link Steam account: ' . $e->getMessage());
        }
    }

    public function updateCheckoutDetails($userId, $basketId, $packageId, $checkoutUrl)
    {
        $params = makeFilterParams([$basketId, $packageId, $checkoutUrl, $userId]);
        try {
            $updateResult = $this->dbObject->updateData(
                "profiles",
                "basket_id = :basket_id, package_id = :package_id, checkout_url = :checkout_url",
                "user_id = :user_id",
                $params
            );

            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Failed to update profile with initialized store data. Ensure common data integrity points and try again.");
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to update profile: ' . $e->getMessage());
        }
    }

    /**
     * Unlinks a Steam account from a user's profile.
     *
     * @param int $userId The user's ID.
     * @return bool Returns true if the account is successfully unlinked.
     * @throws Exception If there is no Steam account linked or the unlink operation fails.
     */
    public function unlinkSteamAccount($userId)
    {
        // First, check if a Steam account is already linked
        $currentSteam = $this->hasSteam($userId);
        if (!$currentSteam['hasSteam']) {
            throw new Exception("No Steam account is linked to this profile.");
        }
        $params = makeFilterParams([$userId]);

        try {
            // Set steam_id and steam_id_64 fields to NULL to unlink the Steam account
            $updateResult = $this->dbObject->updateData(
                "profiles",
                "steam_id = NULL, steam_id_64 = NULL",
                "user_id = :userId",
                $params
            );

            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Failed to unlink Steam account.");
            }
        } catch (Exception $e) {
            throw new Exception('Failed to unlink Steam account: ' . $e->getMessage());
        }
    }

    private function isValidSteamID($steam_id)
    {
        return preg_match('/^7656119[0-9]{10}+$/', $steam_id);
    }

    private function steamid64_to_steamid2($steamid64)
    {
        $accountID = bcsub($steamid64, '76561197960265728');
        return 'STEAM_1:' . bcmod($accountID, '2') . ':' . bcdiv($accountID, 2);
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
    public function doesUsernameExist($username)
    {
        // Query to check if the user has a username

        $result = $this->dbObject->query('SELECT username FROM profiles WHERE username = :username', [
            ':username' => $username
        ]);

        // Return true if the username is found, false otherwise
        return !empty($result) && isset($result[0]['username']);
    }

    
    /**
     * Check to see if email exists in the database.
     *
     * This method queries the database to determine if a specific email is in the database
     * It returns the email if the email exists, false otherwise.
     *
     * @param string $email The email to check for existence.
     * @return Integer User ID if the email exists, false otherwise.
     */
    public function doesEmailExist($email)
    {
        // Query to check if the user has a username

        $result = $this->dbObject->query('SELECT username FROM users WHERE email = :email', [
            ':email' => $email
        ]);

        // Return true if the username is found, false otherwise
        return !empty($result) && isset($result[0]['id']);
    }



    /**
     * Updates the username based on the user ID.
     *
     * @param string $uid The user's ID.
     * @param string $username The new username to set.
     * @return bool Indicates success or failure.
     */
    public function changeUsernameFromUid($uid, $username)
    {
        // Check if the username is already taken
        // Ensure that the query method properly checks for non-results as empty arrays or similar.
        $existingUsername = $this->dbObject->query('SELECT username FROM profiles WHERE username = :username', [':username' => $username]);
        if (!empty($existingUsername)) {
            throw new Exception('This username is already taken!');
        }

        // Generate filter params using makeFilterParams function
        $params = makeFilterParams([$username, $uid]);
        try {
            // Call updateData method with generated filter params
            $updateResult = $this->dbObject->updateData(
                "profiles",
                "username = :username",
                "user_id = :uid",
                $params
            );
            if ($updateResult) {
                return true;
            } else {
                throw new Exception('Failed to update username. Please check if the user ID is correct and try again.');
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to update username' . $e->getMessage());
        }
    }

    /**
     * Adds a new username based on the user ID.
     *
     * This method checks if the provided username is already in use, and if not, inserts it
     * into the database associated with the specified user ID.
     *
     * @param string $uid The user's ID.
     * @param string $username The new username to add.
     * @return bool Indicates success or failure.
     */
    public function addUsernameFromUid($uid, $username)
    {
        // First, check if the username already exists
        $existingUsername = $this->dbObject->query('SELECT username FROM profiles WHERE username = :username', [':username' => $username]);
        if (!empty($existingUsername)) {
            throw new Exception('This username is already taken!');
        }

        // If the username does not exist, proceed to insert the new username
        $rows = 'user_id, username';
        $values = '?, ?';
        $params = makeFilterParams([$uid, $username]);
        try {
            $addResult = $this->dbObject->insertData('profiles', $rows, $values, $params);

            if ($addResult) {
                return true;
            } else {
                throw new Exception('Failed to update username. Please check if the user ID is correct and try again.');
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to update username' . $e->getMessage());
        }
    }

    /**
     * Retrieves the permissions associated with a user based on their user ID.
     *
     * This method queries the database to determine the permissions associated with a user
     * based on their unique identifier. It retrieves the user's permissions from the 'profiles'
     * table and returns the result. This method is part of the service layer, focusing solely
     * on data retrieval without concerning itself with application logic or response formatting.
     * TODO TODO TODO
     * @param int $userId The unique identifier of the user whose permissions are to be retrieved.
     * @return array|false The query result array containing the user's permissions if found, otherwise false.
     */


    private function generateAndAssociateToken($uid, $deviceInfo)
    {
        // Implement logic to generate and associate a token with the user and device
        try {
            $token = generateNewToken(); // Implement this function to generate a unique token

            if ($token) {
                $query = "INSERT INTO login_tokens (token, user_id, device_name, expiration_time) 
                          VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))";
                $params = [$token, $uid, $deviceInfo['device_name']];
                $this->dbObject->query($query, $params);
                return $token;
            }

            return false;
        } catch (Exception $e) {
            // Handle unexpected exceptions and log them
            $this->logger->log(0, 'token_generation_error', ['error_message' => $e->getMessage()]);
            return false;
        }
    }

    public function getPasswordFromEmail($email)
    {
        $table = 'users';
        $select = 'password';
        $whereClause = 'WHERE email = :email';
        $filterParams = makeFilterParams($email);

        $result = $this->dbObject->viewSingleData($table, $select, $whereClause, $filterParams)['result'];
        return $result ? $result['password'] : null;
    }

    public function getPasswordFromUsername($username)
    {
        $table = 'profiles';
        $select = 'user_id';
        $whereClause = 'WHERE username = :username';
        $filterParams = makeFilterParams($username);

        $result = $this->dbObject->viewSingleData($table, $select, $whereClause, $filterParams)['result'];

        if ($result) {

            $uid = $result['user_id'];
            $table = 'users';
            $select = 'password';
            $whereClause = 'WHERE id = :uid';
            $filterParams = makeFilterParams($uid);

            $result = $this->dbObject->viewSingleData($table, $select, $whereClause, $filterParams)['result'];
            return $result ? $result['password'] : null;
        }
    }

    /**
     * Queries the database for a user with the given email and returns their unique identifier.
     *
     * @param string $email The email of the user to query
     *
     * @return int|false Returns the user's unique identifier if the query is successful, or false if the user cannot be found
     */
    public function getUidFromEmail($email)
    {
        $table = 'users';
        $select = 'id';
        $whereClause = 'WHERE email = :email';
        $filterParams = makeFilterParams($email);

        $result = $this->dbObject->viewSingleData($table, $select, $whereClause, $filterParams)['result'];
        return $result ? $result['id'] : null;
    }


    /**
     * Retrieves the username associated with a given user ID.
     *
     * This method queries the database to find the username linked to the provided user ID.
     * It abstracts the database access layer by preparing and executing a SQL query to fetch
     * the username from the 'profiles' table. This method is part of the service layer, focusing
     * solely on data retrieval without concerning itself with application logic or response formatting.
     *
     * @param int $userId The unique identifier of the user whose username is to be retrieved.
     * @return array|false The query result array containing the username if found, otherwise false.
     */
    public function getUsernameById($userId)
    {
        // Prepare and execute the query to fetch the username
        $query = 'SELECT username FROM profiles WHERE user_id = :id';
        $params = [':id' => $userId];
        return $this->dbObject->query($query, $params);
    }


    /**
     * Get user by email address
     *
     * @param string $email User email
     * @return array User Id
     */
    public function getUserByEmail($email)
    {
        $result = $this->dbObject->query(
            'SELECT id from users WHERE email=:email',
            array(
                ':email' => strtolower($email)
            )
        );
        if ($result && count($result) > 0) {

            return $result[0]['id'];
        } else {
            return false;
        }
    }

    public function createUser($email, $password)
    {
        $result = $this->dbObject->query(
            'INSERT INTO users (email, password, verified) VALUES (:email, :password, :verified)',
            array(
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_BCRYPT),
                ':verified' => 0
            )
        );
        return $result !== false;
    }

    /**
     * Queries the database for a user with the given username and returns their unique identifier.
     *
     * @param string $username The username of the user to query
     *
     * @return int|false Returns the unique identifier of the user, or false if the user is not found
     */
    public function getUidFromUsername($username)
    {
        $sql = "SELECT user_id FROM profiles WHERE username = ?";
        $result = $this->dbObject->query($sql, array($username));
        if ($result && count($result) > 0) {
            return $result[0]['user_id'];
        } else {
            return false;
        }
    }

    /**
     * Sets the database to set the token for the user with the given unique identifier.
     *
     * @param int $uid The unique identifier of the user
     *
     * @return string|false Returns the newly generated token if it was set successfully, or false otherwise
     */
    public function setToken($uid, $deviceId)
    {
        // Generate a token
        $cstrong = True;
        $token = bin2hex(openssl_random_pseudo_bytes(64, $cstrong));

        // Hash the token for security
        $token_hash = sha1($token);

        // Calculate the expiration time (7 days from now)
        $expiration_time = date('Y-m-d H:i:s', strtotime('+7 days'));

        // Prepare the SQL statement to insert a new record with the user ID, hashed token, and expiration time
        $rows = 'user_id, token, device_id, expiration_time';
        $values = '?, ?, ?, ?';
        $paramValues = array($uid, $token_hash, $deviceId, $expiration_time);
        $filterParams = makeFilterParams($paramValues);
        $result = $this->dbObject->insertData('login_tokens', $rows, $values, $filterParams);

        // Check if the insert was successful and return the token if so
        return $result !== false ? $token : false;
    }


}
