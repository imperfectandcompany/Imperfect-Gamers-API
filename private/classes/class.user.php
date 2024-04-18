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
        // Query to check if the user has a linked Steam account

        $result = $this->dbObject->query('SELECT username FROM profiles WHERE username = :username', [
            ':username' => $username
        ]);

        // Return true if the username is found, false otherwise
        return !empty($result) && isset($result[0]['username']);
    }




/**
 * Changes the username associated with a specific user, identified by their token.
 *
 * This method verifies the user's token, checks if the new username is already taken,
 * and updates the username if it is available. It sends appropriate HTTP responses
 * based on the outcome of these operations.
 *
 * @param string $token The authentication token of the user.
 * @param string $username The new username to set.
 */
public function changeUsernameFromToken($token, $username)
{
    // Get the user ID from the token and verify if the token is valid
    $uid = $GLOBALS['user_id'];
    // $oldUsername = $GLOBALS['user_data']['username'];
    if ($uid) {
        // Check if the desired username is already in use
        if (!self::doesUsernameExist($username)) {
            // Update the username in the database
            $this->dbObject->query('UPDATE users SET username = :username WHERE id = :userid', [
                ':userid' => $uid,
                ':username' => $username
            ]);
            // Send success response
            return true;
        } else {
            // Send error response if the username is already taken
            return false;
        }
    } else {
        return false;
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
