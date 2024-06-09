<?php

// tests/test_premium.php

function testCanCheckPremiumStatus($premiumController) {
    $userId = 116;  // Example test user ID

    // Using the test double with the overridden sendResponse
    $result = $premiumController->checkPremiumStatus($userId);

    // Assume your method returns an array with status, data, and httpCode
    $expectedResult = ['status' => 'success', 'data' => ['user_id' => $userId, 'is_premium' => true], 'httpCode' => 200];

    global $currentTest;

    // throwWarning("<strong>Result:</strong><br><br> " . json_encode($result));
    
    // Check if result matches expected result
    customAssert(
        $result === $expectedResult,
        "Expected premium status check to pass but it failed"
    );

    echo "Test Passed: Correct premium status returned.\n";
}
