<?php
include_once($GLOBALS['config']['private_folder'] . '/tests/test_infraction.php');
include_once($GLOBALS['config']['private_folder'] . '/controllers/InfractionController.php');
include_once($GLOBALS['config']['private_folder'] . '/classes/class.testRunner.php');
include_once($GLOBALS['config']['private_folder'] . '/classes/class.logger.php');
$logger = new Logger($dbConnection);
// Initialize the Controller object once
$controllers = [
    'infractions' => new InfractionController($dbConnection, $logger),
];

function customAssert($condition, $message)
{
    global $currentTest;
    if (!$condition) {
        throw new Exception($message);
    }
}

// Infraction tests
$testInfraction = [
    "testCanFetchInfractions",
    "testCanFetchInfractionDetails",
    "testCanSaveInfraction",
    "testCanRemoveInfraction",
    "testCanGetInfractionsCount",
    "testCanCheckInfractionsBySteamId",
    // Additional test functions as needed...
];

$tests = [
    "Infraction Tests" => ['controller' => 'infractions', 'tests' => $testInfraction],
];
$runner = new TestRunner($controllers);
$runner->runTests($tests);

unset($post);
unset($comments);
?>