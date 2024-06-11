<?php

class ResponseHandler
{
    public static function sendResponse($status, $data, $httpCode)
    {
        if (!isTest()) {
            echo json_response(['status' => $status] + $data, $httpCode);
            $GLOBALS['messages'][$status][] = $data && isset($data['message']) ? $data['message'] : null;
        } else {
            global $currentTest;
            if ($data && isset($data['message'])) {
                $GLOBALS['logs'][$currentTest][$status][] = $data['message'];  // Store the message with the test name
            }
            // During tests, directly return the response for assertion
            return ['status' => $status, 'data' => $data, 'httpCode' => $httpCode];
        }
        if ($GLOBALS['config']['devmode']) {
            return;
        } else {
            exit();
        }
    }
}

