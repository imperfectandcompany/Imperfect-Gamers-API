<?php
if($dbManager->getConnection() && $GLOBALS['logged_in']){
    //We need to make sure all of our global variables are lined up right.
    if($GLOBALS['user_id']){

        $query = "SELECT u.id, u.email, u.admin, u.verified, u.createdAt, p.username, p.avatar, p.bio_short FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?";
        $params = [$GLOBALS['user_id']]; // Simplified parameter array
        $result = $dbManager->getConnection()->query($query, $params);
        
        if ($result !== false && count($result) > 0) {
            // Assume the query successfully returned data
            $GLOBALS['user_data'] = $result[0]; // If you're expecting a single row, take the first one
            // Check and set default avatar if necessary
            if (empty($GLOBALS['user_data']['avatar'])) {
                $GLOBALS['user_data']['avatar'] = $GLOBALS['config']['default_avatar'];
            }
            $userModel = new User($dbManager->getConnection());
                $GLOBALS['user_permissions'] = $userModel->getUserPermissions($GLOBALS['user_id']);
        } else {
            // Handle cases where user data could not be fetched or result is empty
            // This could be due to the user not existing in the database
            $GLOBALS['messages']['errors'][] = "No user data found or error in query execution.";
        }
    } else {
        // Handle the case where no user ID is provided
        $GLOBALS['messages']['errors'][] = "No user ID provided.";
    }
}