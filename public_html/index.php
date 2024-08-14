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


// TODO Consider versioning the API (/api/v1/...) to accommodate future changes without breaking existing integrations.

// includes
include ($GLOBALS['config']['private_folder'] . '/functions/functions.general.php');
include ($GLOBALS['config']['private_folder'] . '/classes/class.ResponseHandler.php');
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

$isLoggedIn = ($result['status'] === 'error' ? false : true);

$GLOBALS['token'] = $result['token'];

if ($isLoggedIn) {
    // set user ID and token in global variable
    $GLOBALS['user_id'] = $result['user_id'];
    $GLOBALS['logged_in'] = true;
    $token = $result['token'];
    // at this point we have our user_id and can set global data
    include_once (PRIVATE_FOLDER . '/data/user.php');
}

// Check if we're in devmode and authenticated
if (DEVMODE == 1 && $isLoggedIn) {
    include (PRIVATE_FOLDER . '/frontend/devmode.php');
    if (DEVMODE && $GLOBALS['config']['testmode']) {
        // Run testing script

        include_once (PRIVATE_FOLDER . '/tests/tests.php');


        $GLOBALS['config']['testmode'] = 0; //This disables testing

    }
}


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


// handle case where user is not authenticated
// if the user is authenticated, create a new instance of the Router class and dispatch the incoming request
$router = new Router();

// create a new instance for unauthenticated routes from router class
$notAuthenticatedRouter = new Router();

$echo = $isLoggedIn ? "Logged in" : "Not logged in";
// Determine which router to add routes to
$mutualRoute = $isLoggedIn ? $router : $notAuthenticatedRouter;


$mutualRoute->add('/support/requests/populate/all', 'SupportRequestController@handleFetchAllRequestFormData', 'GET');


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


// ## FOR SUPPORT.IMPERFECTGAMERS.ORG

// Route to fetch an article by ID
$mutualRoute->add('/support/article/fetchById/:id', 'SupportController@fetchArticleById', 'GET');
$mutualRoute->addDocumentation('/support/article/fetchById/:id', 'GET', 'Fetches an article by its ID.');

// Route to fetch an article by ID
$mutualRoute->add('/support/article/fetchBySlug/:slug', 'SupportController@fetchArticleBySlug', 'GET');
$mutualRoute->addDocumentation('/support/article/fetchBySlug/:slug', 'GET', 'Fetches an article by its slug.');

// Route to fetch articles by category
$mutualRoute->add('/support/articles/fetchByCategory/:categoryId', 'SupportController@fetchArticlesByCategory', 'GET');
$mutualRoute->addDocumentation('/support/articles/fetchByCategory/:categoryId', 'GET', 'Fetches articles by category ID.');

// Route to fetch all categories
$mutualRoute->add('/support/categories', 'SupportController@fetchAllCategories', 'GET');
$mutualRoute->addDocumentation('/support/categories', 'GET', 'Fetches all categories.');


if (DEVMODE == 1) {
    $mutualRoute->add('/list-routes', 'DevController@listRoutes', 'GET');
}

// get all the routes that have been added to the router
$routes = $notAuthenticatedRouter->getRoutes();

// handle case where user is not authenticated
if (!$isLoggedIn) {

    // add the non-authenticated routes to the router
    $notAuthenticatedRouter->add('/register', 'UserController@register', 'POST');
    $notAuthenticatedRouter->add('/auth', 'UserController@authenticate', 'POST');



// Ensure the constant is defined
if (!defined('IMPERFECT_HOST_SECRET')) {
    define('IMPERFECT_HOST_SECRET', $imperfect_host_webhook_key);
}

// Fetch the payload and signature
$receivedPayload = file_get_contents('php://input');
$receivedSignature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : ''; // Handle missing signature

// Compute the HMAC
$computedHmac = hash_hmac('sha256', $receivedPayload, IMPERFECT_HOST_SECRET);

if (!empty($receivedPayload) && hash_equals($computedHmac, $receivedSignature)) {
    $notAuthenticatedRouter->add('/premium/update/user/:userId/:premiumStatus', 'PremiumController@updatePremiumUser', 'PUT');
    $notAuthenticatedRouter->enforceParameters('/premium/update/user/:userId/:premiumStatus', 'PUT', [
        'steam_id' => 'body',
        'username' => 'body',
        'email' => 'body'
    ]);
    $notAuthenticatedRouter->addDocumentation('/premium/update/user/:userId/:premiumStatus', 'PUT', 'Updates the premium status of a user, ensuring user data consistency.');
}


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

    exit();
}

$router->add('/logout', 'UserController@logout', 'POST');
$router->add('/user/onboarded', 'UserController@verifyOnboarding', 'GET');
$router->addDocumentation('/user/onboarded', 'GET', 'Confirms whether the user completed onboarding or not.');

$router->add('/user/verifySteam', 'UserController@checkSteamLink', 'POST');
$router->addDocumentation('/user/verifySteam', 'POST', 'Verifies the logged in user has a steam account');

$router->add('/user/checkSteamLinked/:steam_id_64', 'UserController@checkSteamLinked', 'GET');
$router->addDocumentation('/user/checkSteamLinked/:steam_id_64', 'GET', 'Checks if the specified Steam ID is already linked to a user.');

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

// Premium user management

// todo: throw error if additional parameter doesnt match controler variable name... we only throw for first
// todo: throw error if parameterized route is already taken
// todo: throw proper error for when route with enforced parameters does not receive expected input


// TODO: Make route add fail is the controller paramaeters name does not match the parameterized part as variable (eg. :user_id => int $user_id) 
$router->add('/premium/status/:userId', 'PremiumController@checkPremiumStatus', 'GET');
$router->addDocumentation('/premium/status/:userId', 'GET', 'Checks if a user is a premium member.');

$router->add('/premium/all', 'PremiumController@listAllPremiumUsers', 'GET');
$router->addDocumentation('/premium/all', 'GET', 'Retrieves a list of all premium users.');

// Add the route for checking if a user ID's linked steam id exists in the server
$router->add('/premium/exists/:userId', 'PremiumController@checkUserExistsInServer', 'GET');
$router->addDocumentation('/premium/exists/:userId', 'GET', 'Checks if a user ID (from website) exists in the server through linked Steam ID.');


// Add the route for checking if a steam ID exists in the server
$router->add('/premium/steamExists/:steamId', 'PremiumController@checkSteamExistsInServer', 'GET');
$router->addDocumentation('/premium/steamExists/:steamId', 'GET', 'Checks if a steam ID in the server through linked Steam ID.');

// if the user is authenticated, use that instance of the Router class and dispatch the incoming request

// ## FOR SUPPORT.IMPERFECTGAMERS.ORG

// Route to create a new category
$router->add('/support/category/create', 'SupportController@createCategory', 'POST');
$router->enforceParameters('/support/category/create', 'POST', [
    'categoryTitle' => 'body',
]);
$router->addDocumentation('/support/category/create', 'POST', 'Creates a new category.');

// Route to check if a category title exists
$router->add('/support/category/checkTitleExists', 'SupportController@checkCategoryTitleExists', 'GET');
$router->addDocumentation('/support/category/checkTitleExists', 'GET', 'Checks if a category title already exists.');

// Route to create a new article
$router->add('/support/article/create', 'SupportController@createArticle', 'POST');
// $router->enforceParameters('/support/article/create', 'POST', [
//     'title' => 'body',
//     'description' => 'body',
//     'detailedDescription' => 'body',
//     'categoryId' => 'body'
// ]);
// TODO DO NOT THROW 404 IN ROUTER CLASS
$router->addDocumentation('/support/article/create', 'POST', 'Creates a new article.');

// Route to check if an article title or slug exists
$router->add('/support/article/checkTitleOrSlugExists', 'SupportController@checkArticleTitleOrSlugExists', 'GET');
$router->addDocumentation('/support/article/checkTitleOrSlugExists', 'GET', 'Checks if an article title or slug already exists.');

// Route to update an article
$router->add('/support/article/update/:articleId', 'SupportController@updateArticle', 'PUT');
$router->enforceParameters('/support/article/update/:articleId', 'PUT', [
    'categoryId' => 'body',
    'title' => 'body',
    'description' => 'body',
    'detailedDescription' => 'body',
    'imgSrc' => 'body'
]);
$router->addDocumentation('/support/article/update/:articleId', 'PUT', 'Updates an article with the given ID.');

// Route to archive an article
$router->add('/support/article/toggleArchive/:articleId', 'SupportController@archiveArticle', 'PUT');
$router->addDocumentation('/support/article/toggleArchive/:articleId', 'PUT', 'Archives an article by its ID.');

// Route to make an article staff-only
$router->add('/support/article/toggleStaffOnly/:articleId', 'SupportController@toggleArticleStaffOnly', 'PUT');
$router->addDocumentation('/support/article/toggleStaffOnly/:articleId', 'PUT', 'Makes an article staff-only by its ID.');

// Route to fetch action logs for an article version
$router->add('/support/article/fetchActionLogs/:articleId', 'SupportController@fetchArticleActionLogs', 'GET');
$router->addDocumentation('/support/article/fetchActionLogs/:articleId', 'GET', 'Fetches action logs for a specific article.');

// Route to create a new article version
$router->add('/support/article/createVersion', 'SupportController@createArticleVersion', 'POST');
$router->addDocumentation('/support/article/createVersion', 'POST', 'Creates a new version of an article.');

// Route to fetch article versions
$router->add('/support/article/fetchVersions/:articleId', 'SupportController@fetchArticleVersions', 'GET');
$router->addDocumentation('/support/article/fetchVersions/:articleId', 'GET', 'Fetches article versions by article ID.');

// Route to fetch category versions
$router->add('/support/category/fetchVersions/:categoryId', 'SupportController@fetchCategoryVersions', 'GET');
$router->addDocumentation('/support/category/fetchVersions/:categoryId', 'GET', 'Fetches category versions by category ID.');

// Route to delete an article
$router->add('/support/article/delete/:articleId', 'SupportController@deleteArticle', 'DELETE');
$router->addDocumentation('/support/article/delete/:articleId', 'DELETE', 'Deletes an article by its ID.');

// Route to update a category
$router->add('/support/category/update/:categoryId', 'SupportController@updateCategory', 'PUT');
$router->enforceParameters('/support/category/update/:categoryId', 'PUT', [
    'categoryTitle' => 'body',
]);
$router->addDocumentation('/support/category/update/:categoryId', 'PUT', 'Updates a category.');

// Route to delete a category
$router->add('/support/category/delete/:categoryId', 'SupportController@deleteCategory', 'DELETE');
$router->addDocumentation('/support/category/delete/:categoryId', 'DELETE', 'Deletes a category by its ID.');

// Route to create a new category version
$router->add('/support/category/createVersion', 'SupportController@createCategoryVersion', 'POST');
$router->addDocumentation('/support/category/createVersion', 'POST', 'Creates a new version of a category.');

$router->add('/support/categories/deleted', 'SupportController@fetchDeletedCategories', 'GET');
$router->add('/support/articles/deleted', 'SupportController@fetchDeletedArticles', 'GET');

// Route to restore an article
$router->add('/support/article/restore/:articleId', 'SupportController@restoreArticle', 'PUT');
$router->addDocumentation('/support/article/restore/:articleId', 'PUT', 'Restores a deleted article by its ID.');

// Route to restore a category
$router->add('/support/category/restore/:categoryId', 'SupportController@restoreCategory', 'PUT');
$router->addDocumentation('/support/category/restore/:categoryId', 'PUT', 'Restores a deleted category by its ID.');

// Route to retrieve all categories, subcategories, sub-issues, and inputs with version IDs
$router->add('/support/issue-categories', 'SupportRequestController@getIssueCategories', 'GET');
$router->addDocumentation('/support/issue-categories', 'GET', 'Retrieves all categories, subcategories, sub-issues, and inputs with version IDs.');

// Route to submit a support request with version tracking
$router->add('/support/support-request', 'SupportRequestController@submitSupportRequest', 'POST');
$router->enforceParameters('/support/support-request', 'POST', [
    'title' => 'body',
    'description' => 'body',
    'versionId' => 'body',
]);
$router->addDocumentation('/support/support-request', 'POST', 'Submits a support request with version tracking.');


$router->add('/support/requests/populate', 'SupportRequestController@handleFetchAllCategories', 'GET');
$router->add('/support/requests/populate/category/:categoryId', 'SupportRequestController@handleCategorySelection', 'GET');

$router->add('/support/requests/logs', 'SupportRequestController@handleFetchActionLogs', 'GET');
// $router->add('/support/requests/inputs', 'SupportRequestController@handleFetchAllInputs', 'GET');
$router->add('/support/requests/inputs', 'SupportRequestController@handleFetchInputs', 'GET');

$router->add('/support/requests/:supportRequestId', 'SupportRequestController@handleFetchSupportRequest', 'GET');

$router->add('/support/requests', 'SupportRequestController@handleFetchAllSupportRequests', 'GET');



$router->add('/support/requests/inputs/versions', 'SupportRequestController@handleFetchAllInputVersions', 'GET');
$router->add('/support/requests/categories/hierarchy', 'SupportRequestController@handleFetchCategoriesHierarchy', 'GET');

// Fetch a specific category's versions
$router->add('/support/requests/categories/:categoryId/versions', 'SupportRequestController@handleFetchCategoryVersions', 'GET');

// Fetch historical versions of a specific category
$router->add('/support/requests/categories/:categoryId/versions/history', 'SupportRequestController@handleFetchCategoryVersionHistory', 'GET');



// Fetch specific support request versions
$router->add('/support/requests/:supportRequestId/versions', 'SupportRequestController@handleFetchSupportRequestVersions', 'GET');

// Fetch historical versions of a specific support request
$router->add('/support/requests/:supportRequestId/versions/history', 'SupportRequestController@handleFetchSupportRequestVersionHistory', 'GET');



$router->add('/support/requests/populate/form', 'SupportRequestController@handleFetchAllCategories', 'GET');

$router->add('/support/requests/populate/category/{categoryId}', 'SupportRequestController@handlePopulateCategory', 'GET');





// Routes for categories
$router->add('/support/requests/categories', 'SupportRequestController@handleCreateCategory', 'POST');
$router->enforceParameters('/support/requests/categories', 'POST', [
    'name' => 'body',
    // 'parent_id' => 'body', // This will be optional in the controller logic
    // 'default_priority' => 'body' // This will be optional in the controller logic
]);
$router->addDocumentation('/support/requests/categories', 'POST', 'Creates a new support request category.');

$router->add('/support/requests/populate/categories', 'SupportRequestController@handleFetchIssueCategories', 'GET');

$router->add('/support/requests/categories/:categoryId', 'SupportRequestController@handleUpdateCategory', 'PUT');
$router->enforceParameters('/support/requests/categories/:categoryId', 'PUT', [
    // 'name' => 'body',
    // 'parent_id' => 'body', // This will be optional in the controller logic
    // 'default_priority' => 'body' // This will be optional in the controller logic
]);
$router->addDocumentation('/support/requests/categories/:categoryId', 'PUT', 'Updates a support request category.');

$router->add('/support/requests/categories/:categoryId', 'SupportRequestController@handleDeleteCategory', 'DELETE');
$router->addDocumentation('/support/requests/categories/:categoryId', 'DELETE', 'Deletes a support request category.');

// Routes for inputs
$router->add('/support/requests/inputs', 'SupportRequestController@handleCreateInput', 'POST');
$router->enforceParameters('/support/requests/inputs', 'POST', [
    'category_id' => 'body',
    'type' => 'body',
    'label' => 'body'
]);
$router->addDocumentation('/support/requests/inputs', 'POST', 'Creates a new input for support requests.');

$router->add('/support/requests/inputs/:inputId', 'SupportRequestController@handleUpdateInput', 'PUT');
$router->enforceParameters('/support/requests/inputs/:inputId', 'PUT', [
    'category_id' => 'body',
    'type' => 'body',
    'label' => 'body'
]);
$router->addDocumentation('/support/requests/inputs/:inputId', 'PUT', 'Updates an input for support requests.');

$router->add('/support/requests/inputs/:inputId', 'SupportRequestController@handleDeleteInput', 'DELETE');
$router->addDocumentation('/support/requests/inputs/:inputId', 'DELETE', 'Deletes an input for support requests.');

$router->add('/support/requests/input-options', 'SupportRequestController@handleCreateInputOptions', 'POST');

// Routes for issues
$router->add('/support/requests/issues/all', 'SupportRequestController@handleFetchAllIssues', 'GET');
// Fetch a specific issue's versions
$router->add('/support/requests/issues/:issueId/versions', 'SupportRequestController@handleFetchIssueVersions', 'GET');
// Fetch historical versions of a specific issue
$router->add('/support/requests/issues/:issueId/versions/history', 'SupportRequestController@handleFetchIssueVersionHistory', 'GET');
// $router->add('/support/issue-categories', 'SupportRequestController@handleFetchIssueCategories', 'GET');
$router->add('/support/requests/issues', 'SupportRequestController@handleCreateIssue', 'POST');
$router->enforceParameters('/support/requests/issues', 'POST', [
    'category_id' => 'body',
    'description' => 'body',
]);
$router->add('/support/requests/issues/:issueId', 'SupportRequestController@handleDeleteIssue', 'DELETE');
$router->add('/support/requests/issues/:issueId', 'SupportRequestController@handleUpdateIssue', 'PUT');
$router->addDocumentation('/support/requests/issues/:issueId', 'PUT', 'Updates an existing issue.');




// Routes for handling support requests
$router->add('/support/requests', 'SupportRequestController@handleCreateSupportRequest', 'POST');
$router->enforceParameters('/support/requests', 'POST', [
    'category_id' => 'body',
    'email' => 'body'
]);
$router->addDocumentation('/support/requests', 'POST', 'Creates a new support request.');

// Routes for handling support requests
$router->add('/support/requests/submit', 'SupportRequestController@handleCreateSupportRequest', 'POST');
$router->enforceParameters('/support/requests', 'POST', [
    'category_id' => 'body',
    'email' => 'body'
]);
$router->addDocumentation('/support/requests', 'POST', 'Creates a new support request.');


$router->add('/support/requests/:supportRequestId', 'SupportRequestController@handleUpdateSupportRequest', 'PUT');
$router->enforceParameters('/support/requests/:supportRequestId', 'PUT', [
    'category_id' => 'body',
    'issue' => 'body',
    'description' => 'body',
    'email' => 'body',
    'status' => 'body',
    'priority' => 'body'
]);
$router->addDocumentation('/support/requests/:supportRequestId', 'PUT', 'Updates a support request.');


$router->add('/support/requests/fetch/open', 'SupportRequestController@getOpenRequests', 'GET');
$router->addDocumentation('/support/requests/fetch/open', 'GET', 'Fetches all open support requests sorted by priority and last updated date.');

$router->add('/support/requests/:supportRequestId/details', 'SupportRequestController@getRequestDetails', 'GET');
$router->addDocumentation('/support/requests/:supportRequestId/details', 'GET', 'Fetches detailed information for a specific support request.');

$router->add('/support/requests/:supportRequestId/history', 'SupportRequestController@getRequestHistory', 'GET');
$router->addDocumentation('/support/requests/:supportRequestId/history', 'GET', 'Fetches the version history for a specific support request.');

$router->add('/support/requests/:supportRequestId/status', 'SupportRequestController@updateRequestStatus', 'PUT');
$router->enforceParameters('/support/requests/:supportRequestId/status', 'PUT', [
    'status' => 'body'
]);
$router->addDocumentation('/support/requests/:supportRequestId/status', 'PUT', 'Updates the status of a specific support request.');

$router->add('/support/requests/:supportRequestId/priority', 'SupportRequestController@updateRequestPriority', 'PUT');
$router->enforceParameters('/support/requests/:supportRequestId/priority', 'PUT', [
    'priority' => 'body'
]);
$router->addDocumentation('/support/requests/:supportRequestId/priority', 'PUT', 'Updates the priority of a specific support request.');

// Add a comment to a support request
$router->add('/support/requests/:supportRequestId/comments', 'SupportRequestController@handleAddComment', 'POST');
$router->enforceParameters('/support/requests/:supportRequestId/comments', 'POST', [
    'comment' => 'body'
]);
$router->addDocumentation('/support/requests/:supportRequestId/comments', 'POST', 'Adds a comment to a support request.');

// Fetch comments for a support request
$router->add('/support/requests/:supportRequestId/comments', 'SupportRequestController@handleFetchComments', 'GET');
$router->addDocumentation('/support/requests/:supportRequestId/comments', 'GET', 'Fetches comments for a support request.');

$router->add('/support/requests/:supportRequestId', 'SupportRequestController@handleDeleteSupportRequest', 'DELETE');
$router->addDocumentation('/support/requests/:supportRequestId', 'DELETE', 'Deletes a support request.');

// TODO ADD ERROR HANDLING FOR WHEN MULTIPLE OF THE SAME CONTROLLER FUNCTION NAMES EXIST FOR THE ADDED ROUTE DEFINED CONTROLLER METHOD




// $router->add('/admin/media', 'AdminMediaController@index', 'GET');
// $router->add('/admin/media/upload', 'AdminMediaController@upload', 'POST');
// $router->add('/admin/media/logs', 'AdminMediaController@logs', 'GET');
// $router->add('/admin/media/view/{id}', 'AdminMediaController@viewMedia', 'GET');
// $router->add('/admin/media/delete/{id}', 'AdminMediaController@deleteMedia', 'DELETE');

$router->add('/media/upload', 'MediaController@createMedia', 'POST');
$router->addDocumentation('/media/upload', 'POST', 'Uploads a new media file.');

$router->add('/media/folder/create', 'MediaController@createFolder', 'POST');
$router->addDocumentation('/media/folder/create', 'POST', 'Creates a new folder for organizing media files.');

$router->add('/media/all', 'MediaController@getAllMedia', 'GET');
$router->addDocumentation('/media/all', 'GET', 'Fetches all media files.');

$router->add('/media/update', 'MediaController@updateMedia', 'POST');
$router->addDocumentation('/media/update', 'POST', 'Updates an existing media file.');

$router->add('/media/delete', 'MediaController@deleteMedia', 'POST');
$router->addDocumentation('/media/delete', 'POST', 'Deletes a media file by moving it to a deleted folder.');

$router->add('/media/single/:media_id', 'MediaController@getMediaById', 'GET');
$router->addDocumentation('/media/single/:media_id', 'GET', 'Fetches details of a specific media file.');

$router->add('/media/folder/fetch/:folder_id', 'MediaController@getFolderContents', 'GET');
$router->addDocumentation('/media/folder/fetch/:folder_id', 'GET', 'Fetches all media files and subfolders within a specific folder.');

$router->add('/media/top-level', 'MediaController@getTopLevelFoldersAndRootMedia', 'GET');
$router->addDocumentation('/media/top-level', 'GET', 'Fetches the top-level folders and media items in the root directory.');

$router->add('/media/logs', 'MediaController@getMediaLogs', 'GET');
$router->addDocumentation('/media/logs', 'GET', 'Fetches logs for media actions.');


 // Routes for folders
// $router->add('/media/folder/create', 'MediaController@createFolder', 'POST');
// $router->addDocumentation('/media/folder/create', 'POST', 'Creates a new media folder.');
















// Update a category and create a new version
// $router->add('/support/requests/categories/:categoryId/update', 'SupportRequestController@handleUpdateCategory', 'POST');



//dispatch router since authentication and global variables are set!
$router->dispatch($GLOBALS['url_loc'], $dbManager, DEVMODE);

// unset token to prevent accidental use
unset($token);
ob_end_flush();

exit();
?>