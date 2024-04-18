<?php
/**
 * Class Logger
 * Handles logging of user activities in the database.
 */
class Logger {
    private $dbConnection;

    /**
     * Logger constructor.
     *
     * @param DatabaseConnector $dbConnection A database connection object.
     */
    public function __construct($dbConnection, $secondaryDbConnection = null) {
        $this->dbConnection = $dbConnection;
        if($secondaryDbConnection) {
            $this->secondaryDbConnection = $secondaryDbConnection;
        }
    }

    /**
     * Log an activity.
     *
     * @param int $userId The ID of the user performing the activity.
     * @param string $action The action being logged.
     * @param array|null $data Additional data related to the activity.
     */
    // TODO add error handling for the query execution and error reporting
    public function log($userId, $action, $data = null) {
        if($userId == 0) {
            // if user is not logged in lets use uid 19 which is reserved for guest
            $userId = 19;
        }        
        // Create a log entry in the database
        $query = "INSERT INTO activity_log (user_id, action, activity_data) VALUES (?, ?, ?)";
        $params = [$userId, $action, json_encode($data)];
        $result = $this->dbConnection->query($query, $params);
    }

/**
 * Get logs with a specific action for a specific user.
 *
 * @param int $userId The ID of the user for whom logs are retrieved.
 * @param string $action The action for which logs are retrieved.
 *
 * @return array An array of log entries matching the criteria.
 */
public function getUserLogsByAction($userId, $action) {
    if($userId == 0) {
        // if the user is not logged in, let's use uid 19 which is reserved for guest
        $userId = 19;
    }
    
    // Retrieve logs for a specific user with a specific action
    $table = 'activity_log';
    $select = 'action';
    $whereClause = "WHERE user_id = ? AND action = ?";
    $filter_params = makeFilterParams(array($userId, $action));
    
    return $this->dbConnection->viewData($table, $select, $whereClause, $filter_params); 
}


    /**
     * Log an activity with custom data.
     *
     * @param int $userId The ID of the user performing the activity.
     * @param string $action The action being logged.
     * @param array $customData Custom data specific to the activity.
     */
    public function logWithCustomData($userId, $action, $customData) {
        if($userId == 0) {
            // if user is not logged in lets use uid 19 which is reserved for guest
            $userId = 19;
        }
        // Create a log entry in the database with custom data
        $query = "INSERT INTO activity_log (user_id, action, activity_data, custom_data) VALUES (?, ?, ?, ?)";
        $params = [$userId, $action, json_encode($customData), json_encode($customData)];
        $this->dbConnection->query($query, $params);
    }

}
