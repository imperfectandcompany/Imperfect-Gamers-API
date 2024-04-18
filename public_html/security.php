<?php
session_start();

if (ENVIRONMENT === 'FWEFDWQX') {
    $allowedDomain = 'https://imperfectgamers.org';
    $allowedReferer = 'https://imperfectgamers.org';

    if (ENVIRONMENT === 'prod') {
        if (ENVIRONMENT !== 'dev' || !isset ($_SERVER['HTTP_REFERER']) || parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) == parse_url($allowedReferer, PHP_URL_HOST)) {
            http_response_code(403); // Forbidden
            exit (json_encode(['error' => 'Unauthorized referer']));
        }
        if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
            http_response_code(403); // Forbidden
            exit (json_encode(['error' => 'IP not allowed']));
        }
    }



    if ((isset ($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] == $allowedDomain) || ENVIRONMENT === 'dev') {
        // Set security headers
        header("Cache-Control: no-store"); // Force browsers to make a new request every time
        header("X-Content-Type-Options: nosniff"); // Prevent MIME type sniffing
        header("X-Frame-Options: DENY"); // Prevent clickjacking
        header("Content-Security-Policy: default-src 'none'; script-src 'self' https://imperfectgamers.org; object-src 'none'; frame-ancestors 'none");
        header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload"); // Enforce HTTPS
        header("Permissions-Policy: accelerometer=(), ambient-light-sensor=(), autoplay=(), battery=(), camera=(), cross-origin-isolated=(), display-capture=(), document-domain=(), encrypted-media=(), execution-while-not-rendered=(), execution-while-out-of-viewport=(), fullscreen=(), geolocation=(), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), navigation-override=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), web-share=(), xr-spatial-tracking=()"); // Permissions policy
        header("Referrer-Policy: no-referrer"); // Referrer policy
        header('Access-Control-Allow-Origin: '.$allowedDomain);

    } else {
        // Handle the unauthorized access
        http_response_code(403); // Forbidden
        exit (json_encode(['error' => 'Unauthorized access']));
    }

    // Allowed HTTP methods
    $allowedMethods = ['GET', 'POST'];

    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
        http_response_code(405); // Method Not Allowed
        exit (json_encode(['error' => 'Method not allowed']));
    }

    // Implement basic rate limiting
    $rateLimit = 5; // Requests limit
    $ratePeriod = 60; // Period in seconds
    $rateLimitKey = 'rate_' . session_id();

    if (!isset ($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = ['count' => 0, 'time' => time()];
    }

    if (time() - $_SESSION[$rateLimitKey]['time'] < $ratePeriod) {
        if ($_SESSION[$rateLimitKey]['count'] >= $rateLimit) {
            http_response_code(429); // Too Many Requests
            exit (json_encode(['error' => 'Too many requests. Please try again later.']));
        }
        $_SESSION[$rateLimitKey]['count']++;
    } else {
        $_SESSION[$rateLimitKey] = ['count' => 1, 'time' => time()];
    }

} else {
    header('Access-Control-Allow-Origin: *');

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one you want to allow, or
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        // may also be using PUT, PATCH, HEAD etc
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}





}