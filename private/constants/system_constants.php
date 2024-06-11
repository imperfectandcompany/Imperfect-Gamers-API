<?php
// Base directory setup
define('BASE_DIRECTORY', '/usr/www/igfastdl/imperfectgamers-api');

define('PRIVATE_FOLDER', "/usr/www/igfastdl/imperfectgamers-api/private");

// Site URL configuration
define('SITE_URL', "https://api.imperfectgamers.org");

// Avatar and Service URLs and paths
define('AVATAR_URL', "https://cdn.postogon.com/assets/img/profile_pictures");
define('SERVICE_URL', "https://cdn.postogon.com/assets/img/service_logos");
define('AVATAR_FOLDER', "/usr/www/igfastdl/postogon-cdn/assets/img/profile_pictures");
define('SERVICE_FOLDER', "/usr/www/igfastdl/postogon-cdn/assets/img/service_logos");
define('ENVIRONMENT', 'dev');

$GLOBALS['config']['max_username_length'] = '32';
$GLOBALS['config']['max_password_length'] = '32';
$GLOBALS['config']['max_timeline_lookup'] = '30';
$GLOBALS['config']['avatar_max_size'] = '156';
$GLOBALS['config']['default_avatar'] = 'default.png';


// Timezone setting
define('TIMEZONE', "America/New_York");

//When we're looking up a user's profile, we use this query to easily not accidentally call their password when we don't need it.
define('PROFILE_LOOKUP', "id,username,email,admin,verified,createdAt,avatar,display_name");

// General settings for user interaction
define('MAX_USERNAME_LENGTH', 32);
define('MAX_PASSWORD_LENGTH', 32);
define('MAX_TIMELINE_LOOKUP', 30);
define('AVATAR_MAX_SIZE', 156); // Assuming this is in KB
define('DEFAULT_AVATAR', 'default.png');

// Region setup
define('REGION', 'en_US');

require (BASE_DIRECTORY . '/private/dbconfig.php');

// Steam API Key
define('STEAM_API_KEY', $steam_api_key);

// Error message for generic errors
define('ERROR_GENERIC', 'An unexpected error occurred.');

//Database variables
$GLOBALS['db_conf']['db_host'] = $domain;
$GLOBALS['db_conf']['db_user'] = $user;
$GLOBALS['db_conf']['db_pass'] = $pass;
$GLOBALS['db_conf']['db_db'] = $table;
$GLOBALS['db_conf']['db_db_test'] = $table_test;
$GLOBALS['db_conf']['db_port'] = '3306';
$GLOBALS['db_conf']['db_charset'] = 'utf8mb4';

// Game server database variables
$GLOBALS['db_conf']['gs_db_host'] = $game_serverHost;
$GLOBALS['db_conf']['gs_db_port'] = $game_serverPort;
$GLOBALS['db_conf']['gs_db_user'] = $game_serverUser;
$GLOBALS['db_conf']['gs_db_pass'] = $game_serverPass;
$GLOBALS['db_conf']['gs_db_db'] = $game_serverDB;
$GLOBALS['db_conf']['gs_db_db_test'] = $game_serverTestDB;
$GLOBALS['db_conf']['gs_db_charset'] = "utf8mb4";


// Database configurations
define('DB_HOST', $domain);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_NAME', $table);
define('DB_NAME_TEST', $table_test);
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

// Game server database configurations
define('GS_DB_HOST', $game_serverHost);
define('GS_DB_PORT', $game_serverPort);
define('GS_DB_USER', $game_serverUser);
define('GS_DB_PASS', $game_serverPass);
define('GS_DB_NAME', $game_serverDB);
define('GS_DB_NAME_TEST', $game_serverTestDB);
define('GS_DB_CHARSET', 'utf8mb4');



// Note: The original code snippet includes paths and URLs for avatars, service logos, and private folders. 
// These are not directly translatable to constants in the format provided and seem to be part of a configuration array. 
// If needed, they could be adapted to constants or managed through another configuration strategy.

// Dev Mode Token 
define('DEV_MODE_TOKEN', '79fcd438ff5baced3fea7c1c2dacf8f99b9596318a374f2ff0340010d555eb9a1e677b3798b4a903035ed218cff1dce4c5b1bb3c74235e111f80d1acc83d0751');


// Imperfect Host Webhook token
define('IMPERFECT_HOST_SECRET', $imperfect_host_webhook_key);

define('baseDirectory', '/usr/www/igfastdl/imperfectgamers-api');
define('SteamAPIKey', '52A66B13219F645834149F1A1180770A');

// Dev mode is a tool for diagnosing issues with the API
// It is not intended to be used in production
// Dev mode is different from development environment in that it is a toggleable feature
// Dev mode does not switch the environment, it simply enables or disables certain features

// Development and Test Modes
// Development mode (for debugging and development, not for production use)

// List of allowed IP addresses for development and test modes
$allowedIPs = $debug_IPs; // Add your IP address here

$config = [
    'DEVMODE' => false, // Enable dev mode
    'loggedIn' => true, // Switch between loggedin and loggedout 
    'TESTMODE' => true // requires DEVMODE to be true
];

if ($_SERVER['HTTP_HOST'] === 'api.imperfectgamers.org' && in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
    // Check if the referrer is not your main site
    if (!isset($_SERVER['HTTP_REFERER']) || parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) !== 'imperfectgamers.org') {
        // Development and Test Modes
        // Development mode (for debugging and development, not for production use)
        define('DEVMODE', $config['DEVMODE']);
        // Keep in mind when dev mode is enabled, loggedIn injects a token into the request
        // Meaning it spoofs your login for you, so you don't have to login to test the API      
        define('loggedIn', $config['loggedIn']); // Spoofs your login for testing
        ###############################################
        // Dev mode must be enabled to use test mode //
        ###############################################
        // Test mode is a tool for testing the API
        // Test mode is not intended to be used in production
        // Test mode is different from development environment in that it is a toggleable feature
        // Test mode does not switch the environment, it simply enables or disables certain features
        // Test mode accesses a different database than the production and development database
        // Test mode is used to test the API in a production-like environment            
        define('TESTMODE', $config['TESTMODE']); // Enables test-specific features (requires DEVMODE)
    } else {
        define('DEVMODE', false);
        define('loggedIn', false);
        define('TESTMODE', false);
    }
} else {
    // Disable all special modes if not accessed from allowed IP
    define('DEVMODE', false);
    define('loggedIn', false);
    define('TESTMODE', false);
}


// TODO Admin Login
// Create a new instance for admin routes from router class
// Admin logged is entirely separate login process from user login
// not ready for phastify / yaan framework as a module
// require_once('internal_admin_auth.php');
//$isAdminLoggedIn = checkAdmin();
// define('loggedIn', $isAdminLoggedIn);
