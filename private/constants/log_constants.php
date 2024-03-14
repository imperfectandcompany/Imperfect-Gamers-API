<?php

define('LOG_USER_LOGIN_START', 'User Login');
define('LOG_USER_LOGOUT_END', 'User Logout');
define('LOG_USER_REGISTER', 'User Registration');
define('LOG_POST_CREATED_END', 'Post Created');
define('LOG_COMMENT_ADDED_END', 'Comment Added');

define('LOG_ACTIVITY_USER_LOGIN', 'LOG-001');
define('LOG_ACTIVITY_USER_LOGOUT', 'LOG-002');
define('LOG_ACTIVITY_POST_CREATED', 'LOG-003');

// New log activity constants for infractions
define('LOG_FETCH_ALL_INFRACTIONS', 'LOG-004');
define('LOG_FETCH_COMMS_INFRACTIONS', 'LOG-005');
define('LOG_FETCH_BAN_INFRACTIONS', 'LOG-006');

define('LOG_FETCH_COMMS_INFRACTION_DETAILS', 'LOG-007');
define('LOG_FETCH_BAN_INFRACTION_DETAILS', 'LOG-008');


$logMessages = [
    'LOG-001' => 'User logged in successfully.',
    'LOG-002' => 'User logged out.',
    'LOG-003' => 'New post created by user.',
    'LOG-004' => 'Fetched all infractions.',
    'LOG-005' => 'Fetched communication infractions.',
    'LOG-006' => 'Fetched ban infractions.'];