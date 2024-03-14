<?php
define ('loggedIn', true);
define('devmode', true);
define('baseDirectory', '/usr/www/igfastdl/postogon-api');
define('region', 'en_US');
define('SteamAPIKey', '52A66B13219F645834149F1A1180770A');

if(loggedIn){
    define('DEV_MODE_TOKEN', '4c6dd8086fdcc9b859416ca264f513d9635f0204baa5d9b7bb95364eae39c43d0e0f81c9f4f5190395fd114384702b1f13f47c13d422d9e98499018a94c5cd5a');
} else {
// force broken devmode token
define('DEV_MODE_TOKEN', '830a1b6ef7e2246925056eaa81282dd84c5214577b9aec043e7dd1b374c23654601d92ded53a119369ffa1e5ed90d22b7c66feadd44ca9e4ed5adc3948721daf');
}


define('ERROR_GENERIC', 'An unexpected error occurred.');
// define('ERROR_NOT_FOUND', 'The requested resource was not found.');
// define('ERROR_BAD_REQUEST', 'Bad request. Please check your request data.');

