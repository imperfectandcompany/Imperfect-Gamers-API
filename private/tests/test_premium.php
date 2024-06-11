<?php

// tests/test_premium.php

function testCanCheckPremiumStatus($premiumController) {
    $userId = 116;  // Example test user ID

    // Using the test double with the overridden sendResponse
    $result = $premiumController->checkPremiumStatus($userId);

    // Our method returns an array with status, data, and httpCode
    $expectedResult = ['status' => 'success', 'data' => ['user_id' => $userId, 'is_premium' => true], 'httpCode' => 200];

    // TODO: throwWarning("<strong>Result:</strong><br><br> " . json_encode($result));
    
    // Check if result matches expected result
    customAssert(
        $result === $expectedResult,
        "Expected premium status check to pass but it failed"
    );

}

/**
 * Test to ensure that the `updatePremiumUser` method correctly handles a scenario where required fields are missing in the input.
 * This test simulates a PUT request with incomplete data and checks for the appropriate error handling.
 */
function testUpdatePremiumUserHandlesMissingFields($premiumController) {
    // Create update data
    $updateData = [
        "username" => "Daiyaan",
        "steam_id" => "f2a1ed52710d4533bde25be6da03b6e3",  // Missing the 'email' field
    ];

    // Convert the update data to JSON
    $updateDataJson = json_encode($updateData);

    // Set the input stream to simulate PUT body with missing required fields for premium update
    $premiumController::setInputStream($updateDataJson);

    // Execute the method
    $result = $premiumController->updatePremiumUser(999, true);

    // Define the expected result for invalid input
    $expectedResult = ['status' => 'error', 'data' => ['message' => 'Missing required fields: email'], 'httpCode' => ERROR_INVALID_INPUT];
    
    // Reset the input stream
    $premiumController::setInputStream('php://input');
    
    // Assert the result matches the expected output for error handling
    customAssert(
        $result === $expectedResult,
        "Test failed: Method did not handle missing required fields correctly."
    );
}

function testUpdatePremiumUserInvalidSteamIdFormat($premiumController) {
    // Create update data with an incorrect Steam ID format
    $updateData = [
    "username" => "Daiyaan",
    "email" => "daiyaan@imperfectsounds.com",
        "steam_id" => "1234567890",  // Incorrect format
    ];

    // Convert the update data to JSON
    $updateDataJson = json_encode($updateData);

    // Set the input stream to simulate PUT body
    $premiumController::setInputStream($updateDataJson);

    // Execute the method
    $result = $premiumController->updatePremiumUser(116, true);

    // Define the expected result for an invalid Steam ID format
    $expectedResult = ['status' => 'error', 'data' => ['message' => 'Invalid Steam ID'], 'httpCode' => ERROR_INVALID_INPUT];
    
    // Reset the input stream
    $premiumController::setInputStream('php://input');

    // Assert the result matches the expected output for error handling
    customAssert(
        $result === $expectedResult,
        "Test failed: Method did not handle invalid Steam ID format correctly."
    );
}

function testUpdatePremiumUserDataMismatch($premiumController) {
    // Create update data that intentionally fails validation
    $updateData = [
        "username" => "raz_is_my_name",
        "email" => "incorrect@email.com", // Assuming this is the correct format but incorrect for the user
        "steam_id" => "76561197990793412",
    ];

    // Convert the update data to JSON and simulate PUT body
    $updateDataJson = json_encode($updateData);
    $premiumController::setInputStream($updateDataJson);
    // Execute the method with a known user ID
    $result = $premiumController->updatePremiumUser(146, true);
    
    // Expected result if there is a data mismatch
    $expectedResult = ['status' => 'error', 'data' => ['message' => 'Validation for associated User ID Data failed', 'errors' => '{Email: Email does not match}'], 'httpCode' => ERROR_INVALID_INPUT];

    // Reset the input stream
    $premiumController::setInputStream('php://input');

    // Assert the outcome
    customAssert(
        $result === $expectedResult,
        "Test failed: The method did not correctly handle data mismatch."
    );
}

function testUpdatePremiumUserUnregisteredUserId($premiumController) {
    // Create update data for an unregistered user ID
    $updateData = [
        "username" => "Daiyaan",
        "email" => "spotify@spotify.com",
        "steam_id" => "76561198000000000",
    ];

    // Convert the update data to JSON
    $updateDataJson = json_encode($updateData);

    // Set the input stream to simulate PUT body
    $premiumController::setInputStream($updateDataJson);

    // Execute the method with an unregistered user ID
    $result = $premiumController->updatePremiumUser(123456, true);

    // Define the expected result for an unregistered user ID
    $expectedResult = ['status' => 'error', 'data' => ['message' => 'User ID not found.'], 'httpCode' => ERROR_NOT_FOUND];
    
    // Reset the input stream
    $premiumController::setInputStream('php://input');

    // Assert the result matches the expected output for error handling
    customAssert(
        $result === $expectedResult,
        "Test failed: Method did not correctly handle unregistered user ID."
    );
}

function testUpdatePremiumUserValidData($premiumController) {
    // Valid and matched data by associated that will pass validation
    $updateData = [
        "username" => "raz_is_my_name",
        "email" => "tomatoedev@gmail.com", // Val
        "steam_id" => "76561197990793412",  //  valid Steam ID
    ];

    // Convert the update data to JSON
    $updateDataJson = json_encode($updateData);

    // Set the input stream to simulate PUT body
    $premiumController::setInputStream($updateDataJson);

    // Execute the method
    $userId = 146;  // valid user ID
    $result = $premiumController->updatePremiumUser($userId, true);

    // Define the expected result for a successful update
    $expectedResult = ['status' => 'success', 'data' => ['message' => 'Premium status updated successfully'], 'httpCode' => 200];

    // Reset the input stream
    $premiumController::setInputStream('php://input');

    // Assert the result matches the expected output for a successful update
    customAssert(
        $result === $expectedResult,
        "Test failed: Premium status update did not succeed as expected."
    );

}

function testTogglePremiumStatus($premiumController) {
    $userId = 146;  // Use a valid user ID known to exist in the system

    // 1. Add Premium Status
    $addData = [
        "username" => "raz_is_my_name",
        "email" => "tomatoedev@gmail.com",
        "steam_id" => "76561197990793412",  // Valid Steam ID
    ];

    // Set the input stream and add premium
    $premiumController::setInputStream(json_encode($addData));
    $addResult = $premiumController->updatePremiumUser($userId, true);
    customAssert(
        $addResult['status'] === 'success',
        "Failed to add premium status."
    );

    // 2. Check if Premium Status was added
    $checkAddResult = $premiumController->checkPremiumStatus($userId);
    customAssert(
        $checkAddResult['data']['is_premium'],
        "Premium status check failed after addition."
    );

    // 3. Remove Premium Status
    $removeData = [
        "username" => "raz_is_my_name",
        "email" => "tomatoedev@gmail.com",
        "steam_id" => "76561197990793412",  // Valid Steam ID
    ];

    // Set the input stream and remove premium
    $premiumController::setInputStream(json_encode($removeData));
    $removeResult = $premiumController->updatePremiumUser($userId, false);
    customAssert(
        $removeResult['status'] === 'success',
        "Failed to remove premium status."
    );

    // 4. Check if Premium Status was removed
    $checkRemoveResult = $premiumController->checkPremiumStatus($userId);
    customAssert(
        !$checkRemoveResult['data']['is_premium'],
        "Premium status check failed after removal."
    );

    // Reset input stream after the test
    $premiumController::setInputStream('php://input');
}

function testCheckUserExistsInServer($premiumController) {
    // Test with a non-existing user ID
    $nonExistingUserId = 9999;  // This ID does not exist
    $result = $premiumController->checkUserExistsInServer($nonExistingUserId);
    customAssert(
        $result['httpCode'] === 404,
        "Test failed: Non-existing user ID should return 404."
    );

    // Test with a valid user ID but no associated Steam ID
    $validUserIdNoSteam = 235;  // Valid user ID on site that lacks a Steam association
    $result = $premiumController->checkUserExistsInServer($validUserIdNoSteam);
    customAssert(
        $result['httpCode'] === 404,
        "Test failed: Valid user without Steam ID should return 404."
    );

    // Test with a valid user ID with Steam ID but invalid format
    $validUserIdInvalidSteam = 234;  // Valid user ID having incorrect Steam ID format
    $result = $premiumController->checkUserExistsInServer($validUserIdInvalidSteam);
    customAssert(
        $result['httpCode'] === ERROR_INVALID_INPUT,
        "Test failed: Invalid Steam ID format should return ERROR_INVALID_INPUT."
    );

    // Test with a valid user ID with correct Steam ID format and existing in database in server
    $validUserIdValidSteamInServer = 146;  // Valid user ID existing in database and steam association
    $result = $premiumController->checkUserExistsInServer($validUserIdValidSteamInServer);
    customAssert(
        $result['httpCode'] === 200 && $result['data']['exists'] === true,
        "Test failed: Valid user with Steam ID existing in database should return 200 with existence true."
    );

    // Test with a valid user ID with correct Steam ID format but not existing in server
    $validUserIdValidSteamNotInServer = 547;  // Replace with a valid user ID with a correct Steam ID format but not in database
    $result = $premiumController->checkUserExistsInServer($validUserIdValidSteamNotInServer);
    customAssert(
        $result['httpCode'] === 200 && $result['data']['exists'] === false,
        "Test failed: Valid user with Steam ID not existing in server should return 200 with existence false."
    );
}

function testListAllPremiumUsers($premiumController) {
    $result = $premiumController->listAllPremiumUsers();
    customAssert(
        $result['httpCode'] === 200,
        "Test failed: Expected HTTP status code 200."
    );

    $premiumUsers = $result['data']['premium_users'];
    customAssert(
        is_array($premiumUsers),
        "Test failed: Expected premium_users to be an array."
    );

    if (!empty($premiumUsers)) {
        $sampleUser = $premiumUsers[0];
        customAssert(
            isset($sampleUser['userid']) && isset($sampleUser['username']) && isset($sampleUser['steamid']) &&
            isset($sampleUser['avatar']) && isset($sampleUser['admin']) && isset($sampleUser['verified']) &&
            isset($sampleUser['updatedAt']) && isset($sampleUser['lastConnected']),
            "Test failed: Expected user information to include userid, username, steamid, avatar, admin, verified, updatedAt, and lastConnected."
        );
    }
}

// function testConcurrentPremiumUpdates($premiumController) {
//     $userId = 146;  // valid user ID

//     // Simulate concurrent requests
//     $task1 = function() use ($premiumController, $userId) {
//         $updateData = [
//             "username" => "raz_is_my_name",
//             "email" => "tomatoedev@gmail.com", // Val
//             "steam_id" => "76561197990793412",  //  valid Steam ID
//         ];
//         $premiumController::setInputStream(json_encode($updateData));
//         return $premiumController->updatePremiumUser($userId, true);
//     };

//     $task2 = function() use ($premiumController, $userId) {
//         $updateData = [
//             "username" => "raz_is_not_my_name",
//             "email" => "tomatoedev@gmail.com", // Val
//             "steam_id" => "76561197990793412",  //  valid Steam ID
//         ];
//         $premiumController::setInputStream(json_encode($updateData));
//         return $premiumController->updatePremiumUser($userId, false);
//     };

//     $results = runConcurrently($task1, $task2);
//     customAssert(
//         $results[0] !== $results[1],
//         "Test failed: Concurrent updates should not produce the same result."
//     );
// }

// function storeSharedResult($result) {
//     // Convert the result to a string if necessary
//     $resultString = serialize($result);
//     // Store the result in a file
//     file_put_contents('/usr/www/igfastdl/imperfectgamers-api/private/tests/result.txt', $resultString);
// }

// function retrieveSharedResult() {
//     $filePath = '/usr/www/igfastdl/imperfectgamers-api/private/tests/result.txt';

//     // Read the result from a file
//     $resultString = file_get_contents($filePath);

//     // Check if the file read was successful before attempting to delete
//     if ($resultString !== false) {
//         // Convert the string back to the original data type
//         $result = unserialize($resultString);

//         // Clean up: delete the file after reading
//         unlink($filePath);

//         return $result;
//     } else {
//         // Handle the error if the file does not exist or is unreadable
//         throw new Exception("Failed to read the shared result file.");
//     }
// }


// function runConcurrently($task1, $task2) {

//     $pid = pcntl_fork();
//     if ($pid == -1) {
//         die('could not fork');
//     } else if ($pid) {
//         // Parent process runs task 1
//         $result1 = $task1();

//         // Wait for the child process to finish
//         pcntl_wait($status);  // Protect against zombie children

//         // Retrieve the result stored by the child process
//         $sharedResult = retrieveSharedResult(); // This function reads and then deletes the file

//         return [$result1, $sharedResult];
//     } else {
//         // Child process runs task 2
//         $sharedResult = $task2();

//         // Store the result in a file
//         storeSharedResult($sharedResult);  // Serialize and store the result to a file

//         exit();
//     }
// }
