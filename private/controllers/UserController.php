<?php
// Includes
include ($GLOBALS['config']['private_folder'] . '/classes/class.device.php');

/**
 * UserController handles user authentication.
 */
class UserController
{

    protected $dbConnection;
    protected $logger;

    public function __construct($dbManager, $logger)
    {
        $this->dbConnection = $dbManager->getConnection('default');
        $this->logger = $logger;
    }

    /**
     * Checks if the given input fields are set and not empty.
     * Returns an error message if any of the fields are missing.
     */
    private function checkInputFields($inputFields, $postBody)
    {
        foreach ($inputFields as $field) {
            if (!isset($postBody->{$field}) || empty($postBody->{$field})) {
                throwError("Error: " . ucfirst($field) . " field is required");
                http_response_code(ERROR_BAD_REQUEST);
                ResponseHandler::sendResponse('error', ['message' => ucfirst($field) . ' field is required'], 400);
                exit;
            }
        }
    }

    //TODO: implement device check for logs. See if ip has registered before. Also save device on register, without UID (unclaimed). No login_tokens association until logged in.
    public function register()
    {
        //header('Content-Type: application/json');
        // Retrieve the post body from the request
        $postBody = file_get_contents("php://input");
        $postBody = json_decode($postBody);

        // Validate email and password fields
        $this->checkInputFields(['email', 'password'], $postBody);

        $email = strtolower($postBody->email);
        $password = $postBody->password;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
            ResponseHandler::sendResponse('error', ['message' => $error], ERROR_INVALID_INPUT);
            exit;
        }

        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
            ResponseHandler::sendResponse('error', ['message' => $error], ERROR_INVALID_INPUT);
            exit;
        }

        // check if the email already exists in the database
        $user = new User($this->dbConnection);
        $result = $user->getUserByEmail($email);
        if ($result) {
            $error = "Email already exists";
            ResponseHandler::sendResponse('error', ['message' => $error], ERROR_USER_ALREADY_EXISTS);
            exit;
        }

        // create the new user
        $newUser = $user->createUser($email, $password);

        if ($newUser) {
            ResponseHandler::sendResponse('success', ['message' => 'Account registered'], SUCCESS_OK);
            exit();
        } else {
            $error = "Unable to register account";
            ResponseHandler::sendResponse('success', ['error' => $error], ERROR_INTERNAL_SERVER);
            exit();
        }
    }

    // TODO for phastify, setup the last error thing so it can breakpoint through logger where flow flopped for a user if it does
    /**
     * Authenticate a user.
     *
     * @throws Exception If an unexpected error occurs.
     */
    public function authenticate()
    {
        // Log the start of the authentication process
        $this->logger->log(0, 'authentication_start', $_SERVER);
        $username = false;
        $email = false;
        try {
            // Parse the request body
            $postBody = json_decode(file_get_contents("php://input"));

            // Check that the required fields are present and not empty
            $this->checkInputFields(['username', 'password'], $postBody);

            // Extract the username and password from the request body
            $identifier = $postBody->username;
            $password = $postBody->password;

            // Query the database for the user with the given username
            $user = new User($this->dbConnection);

            // Determine whether the identifier is an email or a username
            $emailPassword = $user->getPasswordFromEmail($identifier);
            if ($emailPassword) {
                throwSuccess('User email found');
                $email = true;
                $dbPassword = $emailPassword;
                $uid = $user->getUidFromEmail($identifier);
            } else {
                throwWarning('User email not found');
                $userPassword = $user->getPasswordFromUsername($identifier);
                if ($userPassword) {
                    $username = true;
                    throwSuccess('Username found');
                    $dbPassword = $userPassword;
                    $uid = $user->getUidFromUsername($identifier);
                } else {
                    throwWarning('Username not found');
                    $this->logger->log(0, 'authentication_failed', 'User not found');
                    // Return an error if the user cannot be found
                    ResponseHandler::sendResponse('error', ['message' => "User not found'."], ERROR_NOT_FOUND);
                    return false;
                }
            }

            // Check if the password is correct
            if (password_verify($password, $dbPassword)) {
                throwSuccess('Provided password was correct');

                // Save Device of user logging in
                $device = new Device($this->dbConnection, $this->logger);

                $deviceId = $device->saveDevice($uid);

                if ($deviceId) {
                    throwSuccess('Device saved');
                    $this->logger->log($uid, 'device_login_save_success', '{device_id: ' . $deviceId . '}');
                    // Save the token in the database
                    if (($device->associateDeviceIdWithLogin($uid, $deviceId, $device->getDevice(), $_SERVER['REMOTE_ADDR']))) {
                        $this->logger->log($uid, 'token_save_initiated', '{device_id: ' . $deviceId . '}');

                        $token = $user->setToken($uid, $deviceId);

                        if (!$token) {
                            // Return an error if the password is incorrect
                            ResponseHandler::sendResponse('error', ['message' => "Token could not be saved."], ERROR_INTERNAL_SERVER);
                            $this->logger->log($uid, 'token_save_fail', $token);
                            http_response_code(ERROR_UNAUTHORIZED);
                            return false;
                        }
                        // Return the token to the client
                        ResponseHandler::sendResponse('success', ['token' => $token, 'uid' => $uid], SUCCESS_OK);
                        $this->logger->log($uid, 'token_save_success', $token);
                        $this->logger->log($uid, 'authentication_end', 'User authenticated successfully');
                        return true;
                    } else {
                        throwError('Device not associated with login');
                        ResponseHandler::sendResponse('error', ['message' => "Device of user could not be associated with login."], ERROR_INTERNAL_SERVER);
                        return false;
                    }
                } else {
                    throwError('Device not saved');
                    ResponseHandler::sendResponse('error', ['message' => "Device of user could not be saved."], ERROR_INTERNAL_SERVER);
                    $this->logger->log($uid, 'device_login_save_fail', $device->getDeviceInfo());
                    return false;
                }
            } else {
                throwError('Provided password was incorrect');
                // use later once logging becomes really serious
                //$identifierKey = $email === true ? "email" : "username";

                // Log a failed login attempt
                $this->logger->log(0, 'login_failed', ['user_id' => $uid, 'ip' => $_SERVER['REMOTE_ADDR']]);

                // It was an invalid password but we don't want to confirm or deny info just in case it was an opp
                // loool im dead, forgot i wrote this. we the oppa stoppas
                ResponseHandler::sendResponse('error', ['message' => "Invalid Username or Password."], ERROR_UNAUTHORIZED);
                return false;
            }
        } catch (Exception $e) {
            // Handle unexpected exceptions and log them
            $this->logger->log(0, 'authentication_error', ['error_message' => $e->getMessage()]);
            // Return an error response
            ResponseHandler::sendResponse('error', ['message' => "An unexpected error occurred."], ERROR_INTERNAL_SERVER);
            return false;
        }
    }




    public function fetchCheckoutDetails()
    {
        if (!isset($GLOBALS['user_id'])) {
            ResponseHandler::sendResponse('error', ['message' => 'User is not logged in.'], 400);
            return;
        }

        $userId = $GLOBALS['user_id']; // Assuming session is started and user ID is stored

        // Query to fetch basket, package, and checkout details
        $query = 'SELECT basket_id, package_id, checkout_url FROM profiles WHERE user_id = :user_id';
        $params = array(':user_id' => $userId);
        $results = $this->dbConnection->query($query, $params);

        if ($results) {
            // Filter out null values
            $filteredResults = array_filter($results[0], function ($value) {
                return !is_null($value);
            });

            if (!empty($filteredResults)) {
                ResponseHandler::sendResponse('success', ['data' => $filteredResults], 200);
            } else {
                ResponseHandler::sendResponse('success', ['message' => 'No relevant details found'], 200);
            }
        } else {
            ResponseHandler::sendResponse('error', ['message' => 'Details not found'], 404);
        }
    }

    public function updateCheckoutDetails()
    {
        if (!isset($GLOBALS['user_id'])) {
            ResponseHandler::sendResponse('error', ['message' => 'User is not logged in.'], 401);
            return;
        }

        $userId = $GLOBALS['user_id']; // Assuming session is started and user ID is stored

        // Get the input data
        $input = json_decode(file_get_contents('php://input'), true);


        if (!isset($input['basket_id']) || !isset($input['package_id']) || !isset($input['checkout_url'])) {
            ResponseHandler::sendResponse('error', ['message' => 'Missing required parameters.'], 400);
            return;
        }

        $basketId = $input['basket_id'];
        $packageId = $input['package_id'];
        $checkoutUrl = $input['checkout_url'];

        $user = new User($this->dbConnection);
        $result = $user->updateCheckoutDetails($userId, $basketId, $packageId, $checkoutUrl);

        try {
            // Instantiate the DatabaseConnector and User classes
            $user = new User($this->dbConnection);
            // Update the user's profile with the new details
            $result = $user->updateCheckoutDetails($userId, $basketId, $packageId, $checkoutUrl);

            if ($result) {
                ResponseHandler::sendResponse('success', ['message' => 'Checkout details updated successfully.'], 200);
            } else {
                ResponseHandler::sendResponse('error', ['message' => 'Failed to update checkout details.'], 500);
            }
        } catch (Exception $e) {
            ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
        }
    }

    public function logout()
    {
        $userId = $GLOBALS['user_id']; // Assuming user ID is set after successful authentication
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? ''; // Token from the Authorization header

        try {
            $device = new Device($this->dbConnection, $this->logger);
            $deviceId = $device->getDeviceIdByToken($token);
            if (!$deviceId) {
                throw new Exception('Unable to find associated device.');
            }

            // Dissociate the device from the user session
            if (!$device->dissociateDevice($userId, $deviceId)) {
                throw new Exception('Logout failed. Unable to dissociate device.');
            }

            // Assuming removeToken method exists and removes the token for the specific device
            if (!$this->removeToken($userId, $token, $deviceId)) {
                throw new Exception('Logout failed. Unable to remove token.');
            }

            // Log successful logout along with device information
            $this->logger->log($userId, 'user_logout_success', ['deviceId' => $deviceId, 'message' => 'User and device successfully logged out.']);

            // Return success response
            ResponseHandler::sendResponse('success', ['message' => 'Successfully logged out.'], 200); // HTTP 200 OK
        } catch (Exception $e) {
            $this->logger->log($userId, 'logout_error', ['error_message' => $e->getMessage(), 'token' => sha1($token)]);
            ResponseHandler::sendResponse('error', ['message' => 'Logout failed. Please try again.'], 500); // HTTP 500 Internal Server Error
        }
    }



    public function removeToken($uid, $token = null)
    {
        if ($token) {
            // Delete the specified token
            $result = $this->dbConnection->deleteData('login_tokens', 'WHERE user_id = ? AND token = ?', array(array('value' => $uid, 'type' => PDO::PARAM_INT), array('value' => sha1($token), 'type' => PDO::PARAM_STR)));
        } else {
            // Delete all tokens for the user
            $result = $this->dbConnection->deleteData('login_tokens', 'WHERE user_id = ?', array(array('value' => $uid, 'type' => PDO::PARAM_INT)));
        }

        return $result;
    }



    public function checkSteamLink()
    {
        if (!isset($GLOBALS['user_id'])) {
            ResponseHandler::sendResponse('error', ['message' => 'User ID is not set.'], 400);
            return;
        }

        $userId = $GLOBALS['user_id'];

        try {
            $user = new User($this->dbConnection);
            $result = $user->hasSteam($userId);

            if ($result['hasSteam']) {
                ResponseHandler::sendResponse('success', $result, 200);
            } else {
                ResponseHandler::sendResponse('success', ['hasSteam' => false], 200);
            }
        } catch (Exception $e) {
            $this->logger->log($userId, 'checkSteamLink_error', ['error_message' => $e->getMessage()]);
            ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Links a Steam account to the user's profile.
     * This method assumes the user is already authenticated and the Steam ID is included in the POST request.
     * TODO: link steam error logging.
     * $this->logger->log($userId, 'linkSteam_error', ['error_message' => $e->getMessage()]);
     *
     * @return void Outputs a JSON response.
     */
    public function linkSteamAccount()
    {
        if (!isset($GLOBALS['user_id'])) {
            ResponseHandler::sendResponse('error', ['message' => 'User ID is not set.'], 400);
            return;
        }

        // Assume user is authenticated and the necessary middleware has already run
        $user_id = $GLOBALS['user_id'];

        // Parse the request body
        $postBody = json_decode(file_get_contents("php://input"));

        $this->checkInputFields(['steamId64'], $postBody);


        if (!$postBody->steamId64) {
            ResponseHandler::sendResponse('error', ['message' => 'Steam ID missing.'], 400);
            return;
        }

        // Extract the steamId from the request body
        $steam_id_64 = $postBody->steamId64;

        try {
            // Instantiate the DatabaseConnector and User classes
            $user = new User($this->dbConnection);

            $updateResult = $user->linkSteamAccount($user_id, $steam_id_64);
            if ($updateResult) {
                ResponseHandler::sendResponse('success', ['message' => 'Steam account linked successfully'], 200);
            } else {
                ResponseHandler::sendResponse('error', ['message' => 'Failed to link Steam account'], 500);
            }
        } catch (Exception $e) {
            ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Handles the HTTP request to unlink a Steam account from the logged in user's profile.
     *
     * @return void Outputs a JSON response.
     */
    public function unlinkSteamAccount()
    {
        if (!isset($GLOBALS['user_id'])) {
            ResponseHandler::sendResponse('error', ['message' => 'User is not logged in.'], 400);
            return;
        }

        $userId = $GLOBALS['user_id']; // Assuming session is started and user ID is stored

        try {
            // Attempt to unlink the Steam account
            $user = new User($this->dbConnection);

            $unlinkResult = $user->unlinkSteamAccount($userId);
            if ($unlinkResult) {
                ResponseHandler::sendResponse('success', ['message' => 'Steam account unlinked successfully'], 200);
            } else {
                ResponseHandler::sendResponse('error', ['message' => 'Failed to unlink Steam account'], 500);
            }
        } catch (Exception $e) {
            ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
        }
    }


    /**
     * Checks if the current user has linked a Steam account based on a global variable.
     *
     * This function relies on a global variable, populated through a preceding filter,
     * which hydrates the global variable with the current user's ID.
     * It queries the database to determine if the user associated with the globally available
     * user ID has a linked Steam account. The function outputs a JSON response indicating
     * whether a linked Steam account exists and, if present, includes the associated Steam ID.
     * 
     * The function expects the global variable `$GLOBALS['user_id']` to be set prior to invocation.
     * If this variable is not set, or if no matching record is found in the database, the function
     * responds with an appropriate message. It also handles exceptions, logging them accordingly, 
     * and responds with an error message in case of unexpected database errors.
     * 
     * This approach allows the function to operate without direct input parameters, relying instead
     * on the application's architecture and flow control mechanisms to provide the necessary user
     * identification implicitly.
     * 
     * @return void Outputs a JSON response. On success, the response contains 'hasSteam', indicating
     *              whether the Steam account is linked, and 'steamId' with the account's Steam ID if
     *              applicable. On failure due to an unset user ID or database errors, it responds
     *              with an appropriate error message.
     */
    public function hasSteam()
    {
        // Check if the user_id is defined in the global scope
        if (!isset($GLOBALS['user_id'])) {
            // If not, respond with an appropriate message and status code
            ResponseHandler::sendResponse('error', array('message' => 'User ID is not set.'), 400); // 400 Bad Request seems appropriate
            return; // Stop execution of the function
        }

        // If it is set, proceed with existing logic
        $userId = $GLOBALS['user_id'];

        try {
            // Query to check if the user has a linked Steam account
            $query = 'SELECT steam_id_64 FROM profiles WHERE user_id = :id';
            $params = array(':id' => $userId);
            $result = $this->dbConnection->query($query, $params);

            // Check if a Steam ID was found
            if (!empty($result) && isset($result[0]['steam_id_64'])) {
                // User has a Steam account linked
                ResponseHandler::sendResponse('success', array('hasSteam' => true, 'steamId' => $result[0]['steam_id_64']), 200);
            } else {
                // User does not have a Steam account linked
                ResponseHandler::sendResponse('success', array('hasSteam' => false), 200);
            }
        } catch (Exception $e) {
            // Log error or handle exception
            $this->logger->log($userId, 'hasSteam_error', ['error_message' => $e->getMessage()]);
            ResponseHandler::sendResponse('error', array('message' => 'An unexpected error occurred.'), 500);
        }
    }

    /**
     * Handles retrieving the username for the current user and sends an appropriate JSON response.
     *
     * This function operates within the controller layer, where it manages application logic and client communication.
     * It leverages a global variable, populated through a filter, to identify the current user's ID.
     * Utilizing the service layer, it requests the username associated with this ID and formats the response.
     * If the user ID is not globally set or if the service layer indicates no matching record, it sends an error response.
     */
    public function verifyOnboarding()
    {
        // Check if the user_id is defined in the global scope
        if (!isset($GLOBALS['user_id'])) {
            ResponseHandler::sendResponse('error', ['message' => 'User ID is not set.'], 400); // 400 Bad Request
            return;
        }

        $userId = $GLOBALS['user_id'];

        try {
            // Query the database for the user with the given username
            $user = new User($this->dbConnection);

            // Determine whether the identifier is an email or a username
            $result = $user->getUsernameById($userId);

            // Check if a username was found and return it, or false if not found
            if (!empty($result) && isset($result[0]['username'])) {
                ResponseHandler::sendResponse('success', array('onboarded' => true, 'username' => $result[0]['username']), 200);
            } else {
                ResponseHandler::sendResponse('success', array('onboarded' => false), 404);
            }
        } catch (Exception $e) {
            $this->logger->log($userId, 'getUsernameById_error', ['error_message' => $e->getMessage()]);
            ResponseHandler::sendResponse('error', ['message' => 'An unexpected error occurred.'], 500); // 500 Internal Server Error
        }
    }


    /**
     * Changes the username for the authenticated user.
     */
    public function changeUsername()
    {
        $userId = $GLOBALS['user_id'];
        $currentUsername = $GLOBALS['user_data']['username'] ?? '';
        $postBody = json_decode(file_get_contents("php://input"));
        $newUsername = $postBody->username ?? '';

        if (empty($newUsername)) {
            ResponseHandler::sendResponse('error', ['message' => 'Username is required.'], 400);
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
            ResponseHandler::sendResponse('error', ['message' => 'Username can only contain letters, numbers, and underscores.'], 400);
            return;
        }
        if (!empty($newUsername)) {
            if ($newUsername === $currentUsername) {
                ResponseHandler::sendResponse('error', ['message' => 'New username is the same as the current username.'], 400);
                return;
            }
        }

        if (strlen($newUsername) < 3) {
            $this->logger->log($userId, 'username_too_short', ['username' => $newUsername]);
            ResponseHandler::sendResponse('error', ['message' => 'Username is too short. Must be at least 3 characters.'], 400);
            return;
        }

        if (strlen($newUsername) > 20) {
            $this->logger->log($userId, 'username_too_long', ['username' => $newUsername]);
            ResponseHandler::sendResponse('error', ['message' => 'Username is too long. Must not exceed 20 characters.'], 400);
            return;
        }

        if (!$userId) {
            $this->logger->log(0, 'user_not_authenticated', ['attempted_username' => $newUsername]);
            ResponseHandler::sendResponse('error', ['message' => 'Client not authenticated'], 401);
            return;
        }

        $this->logger->log($userId, 'username_change_initiated', ['new' => $newUsername]);

        $user = new User($this->dbConnection);

        if (!empty($currentUsername) && $currentUsername !== $newUsername) {
            try {
                $usernameChanged = $user->changeUsernameFromUid($userId, $newUsername);
                if ($usernameChanged) {
                    $this->logger->log($userId, 'username_update_success', ['from' => $currentUsername, 'to' => $newUsername]);
                    ResponseHandler::sendResponse('success', ['message' => 'Username updated successfully from ' . $currentUsername . ' to ' . $newUsername], 200);
                } else {
                    throw new Exception('Update operation failed, no rows affected.');
                }
            } catch (Exception $e) {
                $this->logger->log($userId, 'username_update_failed', ['error' => $e->getMessage()]);
                ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
            }
        } elseif (empty($currentUsername)) {
            try {
                $usernameAdded = $user->addUsernameFromUid($userId, $newUsername);
                if ($usernameAdded) {
                    $this->logger->log($userId, 'username_add_success', ['username' => $newUsername]);
                    ResponseHandler::sendResponse('success', ['message' => 'Username "' . $newUsername . '" added successfully'], 200);
                } else {
                    throw new Exception('Failed to add username despite no conflicts.');
                }
            } catch (Exception $e) {
                $this->logger->log($userId, 'username_add_failed', ['error' => $e->getMessage()]);
                ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
            }
        } else {
            $this->logger->log($userId, 'username_change_no_action', ['reason' => 'Username unchanged']);
            ResponseHandler::sendResponse('error', ['message' => 'New username is the same as the current username.'], 400);
        }
    }


    /**
     * Checks if the specified username already exists.
     */
    public function checkUsernameExistence()
    {
        // Extract the username from the request body
        $postBody = json_decode(file_get_contents("php://input"));

        $username = $postBody->username ?? '';

        // Query the database for the user with the given username
        $user = new User($this->dbConnection);
        $exists = $user->doesUsernameExist($username);

        // Return the result
        if ($exists) {
            ResponseHandler::sendResponse('success', array('exists' => true), 200);
        } else {
            ResponseHandler::sendResponse('success', array('exists' => false), 200);
        }
    }

    public function verifyToken()
    {
        // Assuming the token is already validated before reaching this function
        ResponseHandler::sendResponse('success', array('uid' => $GLOBALS['user_id']), 200);
    }

    public function logoutAll()
    {
        echo json_encode(array('status' => 'testing', 'message' => 'cant logout all'));
        http_response_code(ERROR_UNAUTHORIZED);
        exit;
    }

    public function logoutAllParam(string $deviceToken)
    {
        echo json_encode(array('status' => 'testing', 'message' => 'cant logout all', 'token' => $deviceToken));
        http_response_code(ERROR_UNAUTHORIZED);
        exit;
    }

    public function logoutMultipleParams(string $deviceToken, int $param2, ?string $optionalParam = "jcas")
    {
        echo json_encode(array('status' => 'testing', 'message' => 'cant logout all', 'token' => $deviceToken, 'param' => $param2, 'Optional' => $optionalParam));
        http_response_code(ERROR_UNAUTHORIZED);
        exit;
    }

    public function theOnewokring(string $deviceToken, int $toggle, string $optionalParam)
    {
        echo json_encode(array('status' => 'testing', 'message' => 'cant logout all', 'token' => $deviceToken, 'param' => $toggle, 'Optional' => $optionalParam));
        http_response_code(ERROR_UNAUTHORIZED);
        exit;
    }

    //implement next..
    public function logoutMultOptional(string $deviceToken, int $param2, ?string $optionalParam = "default fr")
    {
        echo json_encode(array('status' => 'testing', 'message' => 'cant logout all', 'token' => $deviceToken, 'param' => $param2, 'optional' => $optionalParam));
        http_response_code(ERROR_UNAUTHORIZED);
        exit;
    }

}
?>