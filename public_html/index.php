<?php

include '../private/config.php';

// set timezone
date_default_timezone_set($GLOBALS['config']['timezone']);


// start output buffering
// if(!ob_start("ob_gzhandler")) ob_start();
// start session
require_once('./security.php');
// set error reporting level
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// includes
include($GLOBALS['config']['private_folder'].'/functions/functions.general.php');

include($GLOBALS['config']['private_folder'].'/functions/functions.json.php');
include($GLOBALS['config']['private_folder'].'/functions/functions.database.php');
include($GLOBALS['config']['private_folder'].'/constants.php');
include($GLOBALS['config']['private_folder'].'/constants/localization_manager.php');

// include the necessary files and create a database connection object
require_once $GLOBALS['config']['private_folder'].'/classes/class.database.php';
require_once $GLOBALS['config']['private_folder'].'/classes/class.DatabaseManager.php';
require_once $GLOBALS['config']['private_folder'].'/classes/class.user.php';
require_once $GLOBALS['config']['private_folder'].'/classes/class.dev.php';
require_once($GLOBALS['config']['private_folder'] . '/classes/class.localizationCache.php');

// Create a cache instance
$cache = new LocalizationCache();

// Initialize LocalizationManager with the cach
$localizationManager = new LocalizationManager(
    $cache
);

$localizationManager->initialize();

//include($GLOBALS['config']['private_folder'].'/structures/create_constants_structure.php');


// Instantiate the DatabaseManager
$dbManager = new DatabaseManager();

// Dynamically add connection parameters
$dbManager->addConnectionParams('default', [
    'host' => $GLOBALS['db_conf']['db_host'],
    'port' => $GLOBALS['db_conf']['port'],
    'db' => $GLOBALS['db_conf']['db_db'],
    'user' => $GLOBALS['db_conf']['db_user'],
    'pass' => $GLOBALS['db_conf']['db_pass'],
    'charset' => 'utf8mb4'
]);

// Correctly add connection parameters for 'gameserver'
$dbManager->addConnectionParams('gameserver', [
    'host' => $GLOBALS['db_conf']['gs_db_host'],
    'port' => $GLOBALS['db_conf']['gs_db_port'],
    'db' => $GLOBALS['db_conf']['gs_db_db'],
    'user' => $GLOBALS['db_conf']['gs_db_user'],
    'pass' => $GLOBALS['db_conf']['gs_db_pass'],
    'charset' => 'utf8mb4'
]);


require_once $GLOBALS['config']['private_folder'].'/classes/class.router.php';

require("./auth.php");
// check if token is provided in the request header or query parameter or default to dev_mode_token if dev mode is enabled
$token = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (empty($token)) {
    $token = isset($_GET['token']) ? $_GET['token'] : '';
}

// authenticate the user
$result = authenticate_user($token, $dbManager->getConnection());

    // get an instance of the Devmode class
    $devMode = new Dev($dbManager->getConnection());
    $GLOBALS['config']['devmode'] = 0;



    // // Create a cache instance
    // $cache = new LocalizationCache();

    // // Initialize LocalizationManager with the cache
    // $localizationManager = new LocalizationManager(
    //     $GLOBALS['config']['private_folder'] . "/constants",
    //     $GLOBALS['config']['devmode'] ? 'dev' : 'prod',
    //     'en_US',
    //     $cache
    // );
    
    // $message = $localizationManager->getLocalizedString('ERROR_LOGIN_FAILED');
    
    // echo $message;
    // yo sterling was here lmaooo
    // echo "afwefw;";
    


// handle case where user is not authenticated
if ($result['status'] === 'error') {
    
    // create a new instance for unauthenticated routes from router class
    $notAuthenticatedRouter = new Router();


    if($GLOBALS['config']['devmode'] == 1){
        include($GLOBALS['config']['private_folder'].'/frontend/devmode.php');  
    }


    // add the non-authenticated routes to the router
    $notAuthenticatedRouter->add('/register', 'UserController@register', 'POST');
    $notAuthenticatedRouter->add('/auth', 'UserController@authenticate', 'POST');
    $notAuthenticatedRouter->add('/devmode', 'DevController@getDevMode', 'GET');
    $notAuthenticatedRouter->add('/devmode/toggle', 'DevController@toggleDevMode', 'GET');
    $notAuthenticatedRouter->add('/devmode/toggle/:value', 'DevController@toggleDevModeValue', 'GET');


$notAuthenticatedRouter->add('/infractions', 'InfractionController@getAllInfractions', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions', 'GET', 'Fetches all infractions.');

$notAuthenticatedRouter->add('/infractions/pp/:perPage', 'InfractionController@getAllInfractions', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/pp/:perPage', 'GET', 'Fetches all infractions.');

$notAuthenticatedRouter->add('/infractions/p/:page', 'InfractionController@getAllInfractionsPaginated', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/p/:page', 'GET', 'Fetches paginated infractions.');

$notAuthenticatedRouter->add('/infractions/p/:page/pp/:perPage', 'InfractionController@getAllInfractionsPaginated', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/p/:page/pp/:perPage', 'GET', 'Fetches paginated infractions with custom entry count per page.');

$notAuthenticatedRouter->add('/infractions/list/type/:type', 'InfractionController@getInfractions', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/list/type/:type', 'GET', 'Fetches all infractions by type.');

$notAuthenticatedRouter->add('/infractions/list/type/:type/p/:page', 'InfractionController@getInfractionsByTypePaginated', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/list/type/:type/p/:page', 'GET', 'Fetches paginated infractions by type.');


// Route to get the count of infractions or by type
$notAuthenticatedRouter->add('/infractions/count', 'InfractionController@getInfractionsAllCount', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/count', 'GET', 'Gets the count of infractions.');

// Route to get the count of infractions or by type
$notAuthenticatedRouter->add('/infractions/count/:type', 'InfractionController@getInfractionsTypeCount', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/count/:type', 'GET', 'Gets the count of infractions or filters by type.');


// Route for searching infractions by name
$notAuthenticatedRouter->add('/infractions/search/:query', 'InfractionController@searchInfractionsByName', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/search/:query', 'GET', 'Searches for infractions by player name or admin name and player steam id or admin ID.');

// Route for searching infractions by name
$notAuthenticatedRouter->add('/infractions/list/players/all', 'InfractionController@searchForPlayerNamesOptionalType', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/list/players/all', 'GET', 'Searches for all players that are belonging to infractions, bans or admin alike..');

// Route for searching infractions by name
$notAuthenticatedRouter->add('/infractions/list/players/all/type/:type', 'InfractionController@searchForPlayerNamesOptionalType', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/list/players/all/type/:type', 'GET', 'Searches for all players that are belonging to infractions type, bans or admin alike..');


$notAuthenticatedRouter->add('/infractions/search/:query/p/:page', 'InfractionController@searchInfractionsByName', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/search/:query/p/:page', 'GET', 'Searches for infractions by player name or admin name and player Steam ID or admin ID and optional page.');


$notAuthenticatedRouter->add('/infractions/search/:query/p/:page/pp/:perPage', 'InfractionController@searchInfractionsByName', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/search/:query/p/:page/pp/:perPage', 'GET', 'Searches for infractions by player name or admin name and player Steam ID or admin ID and optional page.');


// Route for searching infractions by name with an optional type
$notAuthenticatedRouter->add('/infractions/search/:query/type/:type', 'InfractionController@searchInfractionsByNameAndType', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/search/:query/type/:type', 'GET', 'Searches for infractions by name and type filter.');

$notAuthenticatedRouter->add('/infractions/search/:query/type/:type/p/:page', 'InfractionController@searchInfractionsByNameAndType', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/search/:query/type/:type/p/:page', 'GET', 'Searches for infractions by name and type filter and page.');

// Route to get infraction details by ID and type
$notAuthenticatedRouter->add('/infractions/item/:type/:id', 'InfractionController@getInfractionDetails', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/item/:type/:id'
, 'GET', 'Fetches details for a specific infraction by infraction type (comms/bans) ID.');

$notAuthenticatedRouter->add('/infractions/details/:steamId/p/:page', 'InfractionController@getInfractionDetailsBySteamIdPaginated', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/details/:steamId/p/:page', 'GET', 'Fetches paginated infraction details by Steam ID.');

$notAuthenticatedRouter->add('/infractions/details/:steamId', 'InfractionController@getInfractionsDetailsBySteamId', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/details/:steamId', 'GET', 'Fetches detailed information about infractions by Steam ID.');

// Route to check for any infractions by Steam ID
$notAuthenticatedRouter->add('/infractions/check/:steamId', 'InfractionController@checkInfractionsBySteamId', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/check/:steamId', 'GET', 'Checks for any infractions by Steam ID.');

// Route to check for any infractions by Admin Steam ID
$notAuthenticatedRouter->add('/infractions/check/admin/:adminId', 'InfractionController@checkInfractionsByAdminId', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/check/admin/:adminId', 'GET', 'Checks for any infractions placed by Admin Steam ID.');

// Route to fetch detailed information about infractions by Admin Steam ID
$notAuthenticatedRouter->add('/infractions/details/admin/:adminSteamId', 'InfractionController@getInfractionsDetailsByAdminSteamId', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/details/admin/:adminSteamId', 'GET', 'Fetches detailed information about infractions by Admin Steam ID.');

// Route to fetch paginated infraction details by Admin Steam ID
$notAuthenticatedRouter->add('/infractions/details/admin/:adminSteamId/p/:page', 'InfractionController@getInfractionDetailsByAdminIdPaginated', 'GET');
$notAuthenticatedRouter->addDocumentation('/infractions/details/admin/:adminSteamId/p/:page', 'GET', 'Fetches paginated infraction details by Admin Steam ID.');



// get all the routes that have been added to the router
$routes = $notAuthenticatedRouter->getRoutes();
    
    //check if the requested route does not match one of the non-authenticated routes
    if(!$notAuthenticatedRouter->routeExists($GLOBALS['url_loc'], $routes)){
        http_response_code(ERROR_UNAUTHORIZED);
        echo $result['message'];
        exit();
    }
    
    // dispatch the request to the appropriate controller
    $notAuthenticatedRouter->dispatch($GLOBALS['url_loc'], $dbManager, 1);
    exit();
}

// set user ID and token in global variable
$GLOBALS['user_id'] = $result['user_id'];

$GLOBALS['token'] = $result['token'];
$GLOBALS['logged_in'] = true;

// at this point we have our user_id and can set global data
include_once($GLOBALS['config']['private_folder'].'/data/user.php');

// if the user is authenticated, create a new instance of the Router class and dispatch the incoming request
$router = new Router();

// get an instance of the Devmode class
$devMode = new Dev($dbManager->getConnection());
$GLOBALS['config']['devmode'] = $devMode->getDevModeStatus();
$GLOBALS['config']['testmode'] = 0; //This disables testing

// Assuming you have a $router object available from your routing setup
// Add routes for InfractionController










if($GLOBALS['config']['devmode'] == 1){
    $router->add('/list-routes', 'DevController@listRoutes', 'GET');
}

$GLOBALS['config']['testmode'] = 0; //This enables testing
//dispatch router since authentication and global variables are set!
$router->dispatch($GLOBALS['url_loc'], $dbManager, $GLOBALS['config']['devmode']);
// Check if we're in devmode
if($GLOBALS['config']['devmode'] == 1){
    include($GLOBALS['config']['private_folder'].'/frontend/devmode.php');  
}

$GLOBALS['config']['testmode'] = 0; //This enables testing

if ($GLOBALS['config']['devmode'] && $GLOBALS['config']['testmode']) {
    // Run testing script
    include_once($GLOBALS['config']['private_folder'].'/tests/tests.php');
    $GLOBALS['config']['testmode'] = 0; //This disables testing
}

// unset token to prevent accidental use
// TODO find the other two easter egss left haha
unset($token);
ob_end_flush();

exit();
?>