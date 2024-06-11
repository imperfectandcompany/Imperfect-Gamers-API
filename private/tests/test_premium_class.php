<?php

// tests/test_premium_class.php

function testGenerateNewFlags($premiumClass) {
    $initialFlags = '#css/headadmin';
    $isPremium = true;

    $reflection = new ReflectionClass(get_class($premiumClass));
    $method = $reflection->getMethod('generateNewFlags');
    $method->setAccessible(true);

    // Test adding premium flag
    $newFlags = $method->invoke($premiumClass, $initialFlags, $isPremium);

    customAssert(
        strpos($newFlags, '#css/premium') !== false,
        "Test failed: Premium flag should be added."
    );

    // Test removing premium flag
    $isPremium = false;
    $newFlags = $method->invoke($premiumClass, $newFlags, $isPremium);

    customAssert(
        strpos($newFlags, '#css/premium') === false,
        "Test failed: Premium flag should be removed."
    );
}

function testGetCurrentFlagsExists($premiumClass) {
    $reflection = new ReflectionClass(get_class($premiumClass));
    $method = $reflection->getMethod('getCurrentFlags');
    $method->setAccessible(true); // Make the method accessible

    $steamIdExists = '76561199130155178';
    $expectedFlags = '#css/headadmin, #css/premium';
    $flags = $method->invoke($premiumClass, $steamIdExists);
    customAssert(
        $expectedFlags === $flags,
        "Test failed: Flags should match the expected value."
    );
}

function testGetCurrentFlagsDoesNotExist($premiumClass) {
    $reflection = new ReflectionClass(get_class($premiumClass));
    $method = $reflection->getMethod('getCurrentFlags');
    $method->setAccessible(true);

    $steamIdNotExists = 'nonExistingSteamId';
    $flags = $method->invoke($premiumClass, $steamIdNotExists);

    customAssert(
        '' === $flags,
        "Test failed: Flags should be empty for non-existing Steam ID."
    );
}

function testGenerateNewFlagsAddPremium($premiumClass) {
    $reflection = new ReflectionClass(get_class($premiumClass));
    $method = $reflection->getMethod('generateNewFlags');
    $method->setAccessible(true);

    $flagsWithoutPremium = '#css/headadmin';
    $newFlags = $method->invoke($premiumClass, $flagsWithoutPremium, true);

    customAssert(
        strpos($newFlags, '#css/premium') !== false,
        "Test failed: Premium flag should be added."
    );
}

function testGenerateNewFlagsRemovePremium($premiumClass) {
    $reflection = new ReflectionClass(get_class($premiumClass));
    $method = $reflection->getMethod('generateNewFlags');
    $method->setAccessible(true);

    $flagsWithPremium = '#css/headadmin, #css/premium';
    $newFlags = $method->invoke($premiumClass, $flagsWithPremium, false);

    customAssert(
        strpos($newFlags, '#css/premium') === false,
        "Test failed: Premium flag should be removed."
    );
}

function testIsPlayerExistsSharpTimer($premiumClass) {
    // Test with a non-existing Steam ID
    $nonExistingSteamId = '76561198000000000';  // This Steam ID should not exist
    $result = $premiumClass->isPlayerExistsSharpTimer($nonExistingSteamId);
    customAssert(
        $result === false,
        "Test failed: Non-existing Steam ID should return false."
    );

    // Test with an existing Steam ID
    $existingSteamId = '76561197990793412';  // Actual existing Steam ID
    $result = $premiumClass->isPlayerExistsSharpTimer($existingSteamId);
    customAssert(
        $result === true,
        "Test failed: Existing Steam ID should return true."
    );
}


