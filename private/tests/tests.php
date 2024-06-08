<?php

// tests/tests.php:

include_once ($GLOBALS['config']['private_folder'] . '/tests/test_infraction.php');
include_once ($GLOBALS['config']['private_folder'] . '/controllers/InfractionController.php');
include_once ($GLOBALS['config']['private_folder'] . '/tests/test_infraction.php');
include_once ($GLOBALS['config']['private_folder'] . '/controllers/PremiumController.php');
include_once ($GLOBALS['config']['private_folder'] . '/tests/controllers/PremiumControllerTestDouble.php');
include_once ($GLOBALS['config']['private_folder'] . '/tests/test_premium.php');
include_once ($GLOBALS['config']['private_folder'] . '/classes/class.testRunner.php');
include_once ($GLOBALS['config']['private_folder'] . '/classes/class.logger.php');

// Instantiate the [TESTING] DatabaseManager
$testDbManager = new DatabaseManager();

// Dynamically add connection parameters
$testDbManager->addConnectionParams('default', [
    'host' => $GLOBALS['db_conf']['db_host'],
    'port' => $GLOBALS['db_conf']['port'],
    'db' => $GLOBALS['db_conf']['db_db_test'],
    'user' => $GLOBALS['db_conf']['db_user'],
    'pass' => $GLOBALS['db_conf']['db_pass'],
    'charset' => 'utf8mb4'
]);


// TODO: Make sure it checks to see if we can connect, otherwise hang here
// currently if db doesnt exist and we try to use it we get: Call to a member function prepare() on null

// Correctly add connection parameters for 'gameserver'
$testDbManager->addConnectionParams('gameserver', [
    'host' => $GLOBALS['db_conf']['gs_db_host'],
    'port' => $GLOBALS['db_conf']['gs_db_port'],
    'db' => $GLOBALS['db_conf']['gs_db_db_test'],
    'user' => $GLOBALS['db_conf']['gs_db_user'],
    'pass' => $GLOBALS['db_conf']['gs_db_pass'],
    'charset' => 'utf8mb4'
]);

// Connect specifically to the 'igfastdl_imperfectgamers_test' database for logging related data
$testDbConnection = $testDbManager->getConnection('default');
// Initialize the logger
$testLogger = new Logger($testDbConnection);
// Initialize the Controller object once
$testControllers = [
    'infractions' => new InfractionController($testDbManager, $testLogger),
    'premium' => new PremiumController($testDbManager, $testLogger),
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


// Infraction tests
$testPremium = [
    "testCanCheckPremiumStatus",
];


// TODO: Make sure it checks to see each test file in tests exists...

$tests = [
    "Infraction Tests" => ['controller' => 'infractions', 'tests' => $testInfraction],
    "Premium Tests" => ['controller' => 'premium', 'tests' => $testPremium],
];

$runner = new TestRunner($testControllers);
$runner->runTests($tests);

unset($testDbManager);
unset($testDbConnection);
unset($testLogger);
unset($testControllers);
unset($testInfraction);
unset($testPremium);
unset($tests);
unset($runner);


// -- Create new databases (FUTURE PLANS TO AUTOMATE)
// CREATE DATABASE sharptimer_tests;
// CREATE DATABASE simple_admin_tests;

// -- Copy tables (example for one table, repeat for each table)
// CREATE TABLE sharptimer_tests.PlayerRecords AS SELECT * FROM sharptimer.PlayerRecords;
// CREATE TABLE sharptimer_tests.PlayerStats AS SELECT * FROM sharptimer.PlayerStats;
// CREATE TABLE simple_admin_tests.sa_admins AS SELECT * FROM simple_admin.sa_admins;
// CREATE TABLE simple_admin_tests.sa_bans AS SELECT * FROM simple_admin.sa_bans;
// CREATE TABLE simple_admin_tests.sa_mutes AS SELECT * FROM simple_admin.sa_mutes;
// CREATE TABLE simple_admin_tests.sa_servers AS SELECT * FROM simple_admin.sa_servers;


