<?php
require_once ("/usr/www/igfastdl/imperfectgamers-api/private/constants/localization_manager.php");
require_once ("/usr/www/igfastdl/imperfectgamers-api/private/classes/class.localizationCache.php");

// Create a cache instance
$cache = new LocalizationCache();

// Initialize LocalizationManager with the cach
$localizationManager = new LocalizationManager(
    $cache,
    '/usr/www/igfastdl/imperfectgamers-api/private',
    'dev',
    'en_US'
);

$localizationManager->initialize();
require_once ('./security.php');


// set timezone
date_default_timezone_set(TIMEZONE);

// start output buffering
if (!ob_start("ob_gzhandler"))
    ob_start();
// start session
session_start();

// set error reporting level
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


$GLOBALS['config']['private_folder'] = "/usr/www/igfastdl/imperfectgamers-api/private";

$GLOBALS['config']['url_offset'] = 0;

//This is how we get what page we should be on based on URL.
$GLOBALS['url_loc'] = explode('/', htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES));

if ($GLOBALS['config']['url_offset'] > 0) {
    $x = 0;
    while ($x < ($GLOBALS['config']['url_offset'])) {
        unset($GLOBALS['url_loc'][$x]);
        $x++;
    }
    $GLOBALS['url_loc'] = array_values($GLOBALS['url_loc']);
}

//Do not touch -- These are settings we should define or set, but not adjust unless we absolutely need to.
$GLOBALS['errors'] = array();
$GLOBALS['logs'] = array();



//When we're looking up a user's profile, we use this query to easily not accidentally call their password when we don't need it.
$GLOBALS['config']['profile_lookup'] = "id,username,email,admin,verified,createdAt,avatar,display_name";

//Do not touch -- These are settings we should define or set, but not adjust unless we absolutely need to.
$GLOBALS['errors'] = array();
$GLOBALS['logs'] = array();



$GLOBALS['messages'] = array(); //Main array for all status messages
$GLOBALS['messages']['error'] = array(); //Main array for all status messages
$GLOBALS['messages']['warning'] = array(); //Main array for all status messages
$GLOBALS['messages']['success'] = array(); //Main array for all status messages
$GLOBALS['messages']['test'] = array(); //Main array for all status messages
// TODO: MOVE TO LOCALIZATION FEATURE
// Currently available environments: 'dev', 'prod';

// include '../private/configs/application_'.ENVIRONMENT.'.php';




// includes
include ($GLOBALS['config']['private_folder'] . '/functions/functions.general.php');
include ($GLOBALS['config']['private_folder'] . '/functions/functions.json.php');
include ($GLOBALS['config']['private_folder'] . '/functions/functions.database.php');



// include the necessary files and create a database connection object
require_once $GLOBALS['config']['private_folder'] . '/classes/class.database.php';
require_once $GLOBALS['config']['private_folder'] . '/classes/class.DatabaseManager.php';
require_once $GLOBALS['config']['private_folder'] . '/classes/class.user.php';
require_once $GLOBALS['config']['private_folder'] . '/classes/class.dev.php';

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


require_once $GLOBALS['config']['private_folder'] . '/classes/class.router.php';


require ("./auth.php");
$token = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

if (empty($token)) {
    $token = isset($_GET['token']) ? $_GET['token'] : '';
}


$GLOBALS['config']['testmode'] = TESTMODE; //This initializes the test value

// authenticate the user
$result = authenticate_user($token, $dbManager->getConnection());

// get an instance of the Devmode class
$GLOBALS['config']['devmode'] = DEVMODE;



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
// if the user is authenticated, create a new instance of the Router class and dispatch the incoming request
$router = new Router();

// create a new instance for unauthenticated routes from router class
$notAuthenticatedRouter = new Router();

// If dev mode is enabled and loggedIn is true, set $isLoggedIn for this page to true otherwise check if the token / login result status is not error
DEVMODE === true && loggedIn === true ? $isLoggedIn = true : $isLoggedIn = $result['status'] === 'error' ? false : true;
$echo = $isLoggedIn ? "Logged in" : "Not logged in";
// Determine which router to add routes to
$mutualRoute = $isLoggedIn ? $router : $notAuthenticatedRouter;

$mutualRoute->add('/infractions', 'InfractionController@getAllInfractions', 'GET');
$mutualRoute->addDocumentation('/infractions', 'GET', 'Fetches all infractions.');

$mutualRoute->add('/infractions/pp/:perPage', 'InfractionController@getAllInfractions', 'GET');
$mutualRoute->addDocumentation('/infractions/pp/:perPage', 'GET', 'Fetches all infractions.');

$mutualRoute->add('/infractions/p/:page', 'InfractionController@getAllInfractionsPaginated', 'GET');
$mutualRoute->addDocumentation('/infractions/p/:page', 'GET', 'Fetches paginated infractions.');

$mutualRoute->add('/infractions/p/:page/pp/:perPage', 'InfractionController@getAllInfractionsPaginated', 'GET');
$mutualRoute->addDocumentation('/infractions/p/:page/pp/:perPage', 'GET', 'Fetches paginated infractions with custom entry count per page.');

$mutualRoute->add('/infractions/list/type/:type', 'InfractionController@getInfractions', 'GET');
$mutualRoute->addDocumentation('/infractions/list/type/:type', 'GET', 'Fetches all infractions by type.');

$mutualRoute->add('/infractions/list/type/:type/p/:page', 'InfractionController@getInfractionsByTypePaginated', 'GET');
$mutualRoute->addDocumentation('/infractions/list/type/:type/p/:page', 'GET', 'Fetches paginated infractions by type.');


// Route to get the count of infractions or by type
$mutualRoute->add('/infractions/count', 'InfractionController@getInfractionsAllCount', 'GET');
$mutualRoute->addDocumentation('/infractions/count', 'GET', 'Gets the count of infractions.');

// Route to get the count of infractions or by type
$mutualRoute->add('/infractions/count/:type', 'InfractionController@getInfractionsTypeCount', 'GET');
$mutualRoute->addDocumentation('/infractions/count/:type', 'GET', 'Gets the count of infractions or filters by type.');


// Route for searching infractions by name
$mutualRoute->add('/infractions/search/:query', 'InfractionController@searchInfractionsByName', 'GET');
$mutualRoute->addDocumentation('/infractions/search/:query', 'GET', 'Searches for infractions by player name or admin name and player steam id or admin ID.');

// Route for searching infractions by name
$mutualRoute->add('/infractions/list/players/all', 'InfractionController@searchForPlayerNamesOptionalType', 'GET');
$mutualRoute->addDocumentation('/infractions/list/players/all', 'GET', 'Searches for all players that are belonging to infractions, bans or admin alike..');

// Route for searching infractions by name
$mutualRoute->add('/infractions/list/players/all/type/:type', 'InfractionController@searchForPlayerNamesOptionalType', 'GET');
$mutualRoute->addDocumentation('/infractions/list/players/all/type/:type', 'GET', 'Searches for all players that are belonging to infractions type, bans or admin alike..');


$mutualRoute->add('/infractions/search/:query/p/:page', 'InfractionController@searchInfractionsByName', 'GET');
$mutualRoute->addDocumentation('/infractions/search/:query/p/:page', 'GET', 'Searches for infractions by player name or admin name and player Steam ID or admin ID and optional page.');


$mutualRoute->add('/infractions/search/:query/p/:page/pp/:perPage', 'InfractionController@searchInfractionsByName', 'GET');
$mutualRoute->addDocumentation('/infractions/search/:query/p/:page/pp/:perPage', 'GET', 'Searches for infractions by player name or admin name and player Steam ID or admin ID and optional page.');


// Route for searching infractions by name with an optional type
$mutualRoute->add('/infractions/search/:query/type/:type', 'InfractionController@searchInfractionsByNameAndType', 'GET');
$mutualRoute->addDocumentation('/infractions/search/:query/type/:type', 'GET', 'Searches for infractions by name and type filter.');

$mutualRoute->add('/infractions/search/:query/type/:type/p/:page', 'InfractionController@searchInfractionsByNameAndType', 'GET');
$mutualRoute->addDocumentation('/infractions/search/:query/type/:type/p/:page', 'GET', 'Searches for infractions by name and type filter and page.');

// Route to get infraction details by ID and type
$mutualRoute->add('/infractions/item/:type/:id', 'InfractionController@getInfractionDetails', 'GET');
$mutualRoute->addDocumentation(
    '/infractions/item/:type/:id'
    ,
    'GET',
    'Fetches details for a specific infraction by infraction type (comms/bans) ID.'
);

$mutualRoute->add('/infractions/details/:steamId/p/:page', 'InfractionController@getInfractionDetailsBySteamIdPaginated', 'GET');
$mutualRoute->addDocumentation('/infractions/details/:steamId/p/:page', 'GET', 'Fetches paginated infraction details by Steam ID.');

$mutualRoute->add('/infractions/details/:steamId', 'InfractionController@getInfractionsDetailsBySteamId', 'GET');
$mutualRoute->addDocumentation('/infractions/details/:steamId', 'GET', 'Fetches detailed information about infractions by Steam ID.');

// Route to check for any infractions by Steam ID
$mutualRoute->add('/infractions/check/:steamId', 'InfractionController@checkInfractionsBySteamId', 'GET');
$mutualRoute->addDocumentation('/infractions/check/:steamId', 'GET', 'Checks for any infractions by Steam ID.');

// Route to check for any infractions by Admin Steam ID
$mutualRoute->add('/infractions/check/admin/:adminId', 'InfractionController@checkInfractionsByAdminId', 'GET');
$mutualRoute->addDocumentation('/infractions/check/admin/:adminId', 'GET', 'Checks for any infractions placed by Admin Steam ID.');

// Route to fetch detailed information about infractions by Admin Steam ID
$mutualRoute->add('/infractions/details/admin/:adminSteamId', 'InfractionController@getInfractionsDetailsByAdminSteamId', 'GET');
$mutualRoute->addDocumentation('/infractions/details/admin/:adminSteamId', 'GET', 'Fetches detailed information about infractions by Admin Steam ID.');

// Route to fetch paginated infraction details by Admin Steam ID
$mutualRoute->add('/infractions/details/admin/:adminSteamId/p/:page', 'InfractionController@getInfractionDetailsByAdminIdPaginated', 'GET');
$mutualRoute->addDocumentation('/infractions/details/admin/:adminSteamId/p/:page', 'GET', 'Fetches paginated infraction details by Admin Steam ID.');

if (DEVMODE == 1) {
    $mutualRoute->add('/list-routes', 'DevController@listRoutes', 'GET');
}

// get all the routes that have been added to the router
$routes = $notAuthenticatedRouter->getRoutes();

// handle case where user is not authenticated
if ($result['status'] === 'error') {

    // add the non-authenticated routes to the router
    $notAuthenticatedRouter->add('/register', 'UserController@register', 'POST');
    $notAuthenticatedRouter->add('/auth', 'UserController@authenticate', 'POST');

    // get all the routes that have been added to the router
    $routes = $notAuthenticatedRouter->getRoutes();

    //check if the requested route does not match one of the non-authenticated routes
    //TODO Move to router class?
    if (!$notAuthenticatedRouter->routeExists($GLOBALS['url_loc'], $routes)) {
        http_response_code(ERROR_UNAUTHORIZED);
        echo $result['message'];
        exit();
    }

    // dispatch the request to the appropriate controller
    $notAuthenticatedRouter->dispatch($GLOBALS['url_loc'], $dbManager, 1);
    if (DEVMODE == 1) {
        include (PRIVATE_FOLDER . '/frontend/devmode.php');
    }
    exit();
}

$router->add('/logout', 'UserController@logout', 'POST');
$router->add('/user/onboarded', 'UserController@verifyOnboarding', 'GET');
$router->addDocumentation('/user/onboarded', 'GET', 'Confirms whether the user completed onboarding or not.');

$router->add('/user/verifySteam', 'UserController@checkSteamLink', 'POST');
$router->addDocumentation('/user/verifySteam', 'POST', 'Verifies the logged in user has a steam account');

$router->add('/user/linkSteam', 'UserController@linkSteamAccount', 'POST');
$router->addDocumentation('/user/linkSteam', 'POST', 'Links the logged in user\'s Steam account by saving the Steam ID to their profile');

$router->add('/user/unlinkSteam', 'UserController@unlinkSteamAccount', 'POST');
$router->addDocumentation('/user/unlinkSteam', 'POST', 'Unlinks the logged in user\'s Steam account by updating the Steam ID(s) to null in their profile');

$router->add('/auth/verifyToken', 'UserController@verifyToken', 'GET');
$router->addDocumentation('/auth/verifyToken', 'GET', 'Returns true / success, since it passed through authenticated filter with token properly.');

// Adding the route to check if a username already exists
$router->add('/user/checkUsernameExistence', 'UserController@checkUsernameExistence', 'POST');
$router->addDocumentation('/user/checkUsernameExistence', 'POST', 'Checks if the specified username already exists in the system.');

$router->add('/user/changeusername', 'UserController@changeUsername', 'POST');
$router->addDocumentation('/user/changeusername', 'POST', 'Changes the username of the user.');

$router->add('/user/fetchCheckoutDetails', 'UserController@fetchCheckoutDetails', 'GET');
$router->addDocumentation('/user/fetchCheckoutDetails', 'GET', 'Fetches the basket, package, and checkout URL details for the logged-in user.');

$router->add('/user/updateCheckoutDetails', 'UserController@updateCheckoutDetails', 'POST');
$router->addDocumentation('/user/updateCheckoutDetails', 'POST', 'Updates the basket, package, and checkout URL details for the logged-in user.');

// set user ID and token in global variable
$GLOBALS['user_id'] = $result['user_id'];

$GLOBALS['token'] = $result['token'];
$token = $result['token'];
$GLOBALS['logged_in'] = true;

// at this point we have our user_id and can set global data
include_once (PRIVATE_FOLDER . '/data/user.php');

// if the user is authenticated, use that instance of the Router class and dispatch the incoming request

//dispatch router since authentication and global variables are set!
$router->dispatch($GLOBALS['url_loc'], $dbManager, DEVMODE);


// Check if we're in devmode
if (DEVMODE == 1) {
    include (PRIVATE_FOLDER . '/frontend/devmode.php');
    if (DEVMODE && $GLOBALS['config']['testmode']) {
        // Run testing script
        include_once (PRIVATE_FOLDER . '/tests/tests.php');
        $GLOBALS['config']['testmode'] = 0; //This disables testing
    }
}


// unset token to prevent accidental use
// TODO find the other two easter egss left haha
unset($token);
ob_end_flush();

exit();
?>