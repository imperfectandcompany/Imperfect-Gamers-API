<?php
// controllers/AdminController.php

class AdminController
{
    private $db;
    private $logger;

    public function __construct($dbManager, $logger)
    {
        $this->db = $dbManager->getConnection('default');
        $this->logger = $logger;
    }

/**
 * Get a paginated list of users with email, roles, and timestamps, with sorting.
 *
 * @param int $page The page number.
 * @param int $perPage Number of users per page.
 */
public function getUsersList($page = 1, $perPage = 20)
{
    try {
        $offset = ($page - 1) * $perPage;

        // Get the 'sort' parameter, default to 'createdAt|DESC'
        $sortParams = $_GET['sort'] ?? 'createdAt|DESC';
        $sortArray = explode(',', $sortParams);

        // Valid sorting fields
        $validSortFields = ['id', 'username', 'email', 'createdAt', 'updatedAt'];
        $sortClauses = [];

        foreach ($sortArray as $sortParam) {
            [$sortField, $sortOrder] = explode('|', $sortParam);
            $sortField = in_array($sortField, $validSortFields) ? $sortField : 'createdAt';
            $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
            $sortClauses[] = "$sortField $sortOrder";
        }

        // Construct the ORDER BY clause
        $orderByClause = implode(', ', $sortClauses);

        // Count total users for pagination
        $countSql = 'SELECT COUNT(*) AS total FROM users';
        $totalUsers = $this->db->query($countSql)[0]['total'];
        $totalPages = ceil($totalUsers / $perPage);

        // Fetch users with roles and timestamps, including those without profiles
        $sql = "SELECT 
                    users.id, 
                    profiles.username, 
                    users.email, 
                    GROUP_CONCAT(roles.name) AS roles, 
                    users.createdAt AS created_at, 
                    users.updatedAt AS updated_at
                FROM users
                LEFT JOIN profiles ON profiles.user_id = users.id
                LEFT JOIN user_roles ON user_roles.user_id = users.id
                LEFT JOIN roles ON user_roles.role_id = roles.id
                GROUP BY users.id
                ORDER BY $orderByClause
                LIMIT :limit OFFSET :offset";

        $params = [
            ':limit' => $perPage,
            ':offset' => $offset,
        ];

        $users = $this->db->query($sql, $params) ?: [];

        // Handle null usernames in PHP
        foreach ($users as &$user) {
            $user['username'] = $user['username'] ?? 'Unknown'; // Replace null with 'Unknown'
        }

        // Response with pagination metadata
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_users' => $totalUsers,
                    'has_previous_page' => $page > 1,
                    'has_next_page' => $page < $totalPages,
                ]
            ],
            'message' => 'Users retrieved successfully'
        ];

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (Exception $e) {
        $this->logger->log('error', 'Failed to get users list', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Failed to get users list'], ERROR_INTERNAL_SERVER);
    }
}


    

/**
 * Search users by username, including their email, roles, and timestamps, with sorting.
 *
 * @param string $query The search query.
 * @param int $page The page number.
 * @param int $perPage Number of users per page.
 */
public function searchUsers($query, $page = 1, $perPage = 20)
{
    try {
        $offset = ($page - 1) * $perPage;

        // Get the 'sort' parameter, default to 'username|ASC'
        $sortParams = $_GET['sort'] ?? 'username|ASC';
        $sortArray = explode(',', $sortParams);

        // Valid sorting fields
        $validSortFields = ['id', 'username', 'email', 'createdAt', 'updatedAt'];
        $sortClauses = [];

        foreach ($sortArray as $sortParam) {
            [$sortField, $sortOrder] = explode('|', $sortParam);
            $sortField = in_array($sortField, $validSortFields) ? $sortField : 'username';
            $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
            $sortClauses[] = "$sortField $sortOrder";
        }

        // Construct the ORDER BY clause
        $orderByClause = implode(', ', $sortClauses);

        // Query for pagination count
        $countSql = 'SELECT COUNT(*) AS total FROM profiles WHERE username LIKE :query';
        $countParams = [':query' => '%' . $query . '%'];
        $totalUsers = $this->db->query($countSql, $countParams)[0]['total'];
        $totalPages = ceil($totalUsers / $perPage);

        // Main query to fetch users by search with roles
        $sql = "SELECT users.id, profiles.username, users.email, GROUP_CONCAT(roles.name) AS roles, 
                       users.createdAt AS created_at, users.updatedAt AS updated_at
                FROM profiles
                INNER JOIN users ON profiles.user_id = users.id
                LEFT JOIN user_roles ON user_roles.user_id = users.id
                LEFT JOIN roles ON user_roles.role_id = roles.id
                WHERE profiles.username LIKE :query
                GROUP BY profiles.id
                ORDER BY $orderByClause
                LIMIT :limit OFFSET :offset";

        $params = [
            ':query' => '%' . $query . '%',
            ':limit' => $perPage,
            ':offset' => $offset,
        ];

        $users = $this->db->query($sql, $params) ?: [];

        // Response with pagination metadata
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_users' => $totalUsers,
                    'has_previous_page' => $page > 1,
                    'has_next_page' => $page < $totalPages,
                ]
            ],
            'message' => 'Users retrieved successfully'
        ];

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (Exception $e) {
        $this->logger->log('error', 'Failed to search users', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Failed to search users'], ERROR_INTERNAL_SERVER);
    }
}


/**
 * Get details of a specific user, including related data with pagination for logs and devices.
 *
 * @param int $userId The user ID.
 * @param int $perPageLog Number of items per page for logs and devices.
 * @param int $perPageDevice Number of items per page for logs and devices.
 */
public function getUserDetails($userId, $perPageLog = 5, $perPageDevice = 5)
{
    // We want the first page from both for this, but we never know if user has set preferences for how much to show per page
    $logPage = 1;
    $devicePage = 1;

    // Enforce defaults if values are not provided or invalid
    $perPageLog = is_numeric($perPageLog) && $perPageLog > 0 ? (int) $perPageLog : 5;
    $perPageDevice = is_numeric($perPageDevice) && $perPageDevice > 0 ? (int) $perPageDevice : 5;
    
    try {
        // Validate userId is a positive integer
        if (!is_numeric($userId) || $userId <= 0) {
            ResponseHandler::sendResponse('error', ['message' => 'Invalid User ID'], ERROR_BAD_REQUEST);
            return;
        }

        // Query to fetch user details (excluding sensitive fields)
        $userQuery = 'SELECT id, email, status, admin, verified, createdAt, FROM_UNIXTIME(updatedAt) as updatedAt FROM users WHERE id = :id';
        $userParams = [':id' => $userId];
        $user = $this->db->query($userQuery, $userParams);

        if (!$user) {
            ResponseHandler::sendResponse('error', ['message' => 'User not found'], ERROR_NOT_FOUND);
            return;
        }

        // Fetch roles for the user
        $rolesQuery = 'SELECT roles.name FROM user_roles INNER JOIN roles ON user_roles.role_id = roles.id WHERE user_roles.user_id = :user_id';
        $roles = $this->db->query($rolesQuery, [':user_id' => $userId]);

        // Pagination for activity logs
        $logOffset = ($logPage - 1) * $perPageLog;
        $logCountQuery = 'SELECT COUNT(*) as total FROM activity_log WHERE user_id = :user_id';
        $totalLogs = $this->db->query($logCountQuery, [':user_id' => $userId])[0]['total'];
        $totalLogPages = ceil($totalLogs / $perPageLog);

        $logsQuery = 'SELECT action, created_at, location FROM activity_log WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $logs = $this->db->query($logsQuery, [
            ':user_id' => $userId,
            ':limit' => $perPageLog,
            ':offset' => $logOffset
        ]) ?: [];

        // Pagination for devices
        $deviceOffset = ($devicePage - 1) * $perPageDevice;
        $deviceCountQuery = 'SELECT COUNT(*) as total FROM devices WHERE user_id = :user_id';
        $totalDevices = $this->db->query($deviceCountQuery, [':user_id' => $userId])[0]['total'];
        $totalDevicePages = ceil($totalDevices / $perPageDevice);

        $devicesQuery = 'SELECT id, device_name, created_at, last_login, is_logged_in, expired FROM devices WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $devices = $this->db->query($devicesQuery, [
            ':user_id' => $userId,
            ':limit' => $perPageDevice,
            ':offset' => $deviceOffset
        ]) ?: [];

        // Response with detailed data and pagination metadata
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'user' => $user[0],
                'roles' => $roles ? array_column($roles, 'name') : [], // Default to empty array
                'recent_logs' => [
                    'data' => $logs,
                    'pagination' => [
                        'current_page' => $logPage,
                        'per_page' => $perPageLog,
                        'total_pages' => $totalLogPages,
                        'total_logs' => $totalLogs,
                        'has_previous_page' => $logPage > 1,
                        'has_next_page' => $logPage < $totalLogPages,
                    ]
                ],
                'devices' => [
                    'data' => $devices,
                    'pagination' => [
                        'current_page' => $devicePage,
                        'per_page' => $perPageDevice,
                        'total_pages' => $totalDevicePages,
                        'total_devices' => $totalDevices,
                        'has_previous_page' => $devicePage > 1,
                        'has_next_page' => $devicePage < $totalDevicePages,
                    ]
                ]
            ],
            'message' => 'User details retrieved successfully'
        ];

        // Send success response
        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (PDOException $e) {
        $this->logger->log('error', 'Database error while fetching user details', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Database error occurred'], ERROR_INTERNAL_SERVER);
    } catch (Exception $e) {
        $this->logger->log('error', 'Unexpected error while fetching user details', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'An unexpected error occurred'], ERROR_INTERNAL_SERVER);
    }
}

/**
 * Fetch user logs with pagination.
 *
 * @param int $userId The user ID.
 * @param int $page The current page.
 * @param int $perPage The number of logs per page.
 */
public function getUserLogs($userId, $page = 1, $perPage = 5)
{
    try {
        if (!is_numeric($userId) || $userId <= 0) {
            ResponseHandler::sendResponse('error', ['message' => 'Invalid User ID'], ERROR_BAD_REQUEST);
            return;
        }

        $offset = ($page - 1) * $perPage;

        // Count total logs
        $logCountQuery = 'SELECT COUNT(*) as total FROM activity_log WHERE user_id = :user_id';
        $totalLogs = $this->db->query($logCountQuery, [':user_id' => $userId])[0]['total'];
        $totalPages = ceil($totalLogs / $perPage);

        // Fetch logs
        $logsQuery = 'SELECT action, created_at, location FROM activity_log WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $logs = $this->db->query($logsQuery, [
            ':user_id' => $userId,
            ':limit' => $perPage,
            ':offset' => $offset
        ]) ?: [];

        // Response
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_logs' => $totalLogs,
                    'has_previous_page' => $page > 1,
                    'has_next_page' => $page < $totalPages,
                ]
            ],
            'message' => 'Logs retrieved successfully'
        ];

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (Exception $e) {
        $this->logger->log('error', 'Failed to fetch logs', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch logs'], ERROR_INTERNAL_SERVER);
    }
}


/**
 * Search user logs with pagination.
 *
 * @param int $userId The user ID.
 * @param string $searchQuery The search query.
 * @param int $page The current page.
 * @param int $perPage The number of logs per page.
 */
public function searchUserLogs($userId, $searchQuery, $page = 1, $perPage = 5)
{
    try {
        if (!is_numeric($userId) || $userId <= 0) {
            ResponseHandler::sendResponse('error', ['message' => 'Invalid User ID'], ERROR_BAD_REQUEST);
            return;
        }

        $offset = ($page - 1) * $perPage;

        // Count total logs matching search
        $logCountQuery = 'SELECT COUNT(*) as total FROM activity_log WHERE user_id = :user_id AND action LIKE :search';
        $totalLogs = $this->db->query($logCountQuery, [
            ':user_id' => $userId,
            ':search' => '%' . $searchQuery . '%'
        ])[0]['total'];
        $totalPages = ceil($totalLogs / $perPage);

        // Fetch logs
        $logsQuery = 'SELECT action, created_at, location FROM activity_log WHERE user_id = :user_id AND action LIKE :search ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $logs = $this->db->query($logsQuery, [
            ':user_id' => $userId,
            ':search' => '%' . $searchQuery . '%',
            ':limit' => $perPage,
            ':offset' => $offset
        ]) ?: [];

        // Response
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_logs' => $totalLogs,
                    'has_previous_page' => $page > 1,
                    'has_next_page' => $page < $totalPages,
                ]
            ],
            'message' => 'Logs retrieved successfully'
        ];

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (Exception $e) {
        $this->logger->log('error', 'Failed to search logs', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Failed to search logs'], ERROR_INTERNAL_SERVER);
    }
}


/**
 * Fetch user devices with pagination.
 *
 * @param int $userId The user ID.
 * @param int $page The current page.
 * @param int $perPage The number of devices per page.
 */
public function getUserDevices($userId, $page = 1, $perPage = 5)
{
    try {
        if (!is_numeric($userId) || $userId <= 0) {
            ResponseHandler::sendResponse('error', ['message' => 'Invalid User ID'], ERROR_BAD_REQUEST);
            return;
        }

        $offset = ($page - 1) * $perPage;

        // Count total devices
        $deviceCountQuery = 'SELECT COUNT(*) as total FROM devices WHERE user_id = :user_id';
        $totalDevices = $this->db->query($deviceCountQuery, [':user_id' => $userId])[0]['total'];
        $totalPages = ceil($totalDevices / $perPage);

        // Fetch devices
        $devicesQuery = 'SELECT id, device_name, created_at, last_login, is_logged_in, expired FROM devices WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $devices = $this->db->query($devicesQuery, [
            ':user_id' => $userId,
            ':limit' => $perPage,
            ':offset' => $offset
        ]) ?: [];

        // Response
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'devices' => $devices,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_devices' => $totalDevices,
                    'has_previous_page' => $page > 1,
                    'has_next_page' => $page < $totalPages,
                ]
            ],
            'message' => 'Devices retrieved successfully'
        ];

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (Exception $e) {
        $this->logger->log('error', 'Failed to fetch devices', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch devices'], ERROR_INTERNAL_SERVER);
    }
}

/**
 * Search user devices with pagination.
 *
 * @param int $userId The user ID.
 * @param string $searchQuery The search query.
 * @param int $page The current page.
 * @param int $perPage The number of devices per page.
 */
public function searchUserDevices($userId, $searchQuery, $page = 1, $perPage = 5)
{
    try {
        if (!is_numeric($userId) || $userId <= 0) {
            ResponseHandler::sendResponse('error', ['message' => 'Invalid User ID'], ERROR_BAD_REQUEST);
            return;
        }

        $offset = ($page - 1) * $perPage;

        // Count total devices matching search
        $deviceCountQuery = 'SELECT COUNT(*) as total FROM devices WHERE user_id = :user_id AND device_name LIKE :search';
        $totalDevices = $this->db->query($deviceCountQuery, [
            ':user_id' => $userId,
            ':search' => '%' . $searchQuery . '%'
        ])[0]['total'];
        $totalPages = ceil($totalDevices / $perPage);

        // Fetch devices
        $devicesQuery = 'SELECT id, device_name, created_at, last_login, is_logged_in, expired FROM devices WHERE user_id = :user_id AND device_name LIKE :search ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $devices = $this->db->query($devicesQuery, [
            ':user_id' => $userId,
            ':search' => '%' . $searchQuery . '%',
            ':limit' => $perPage,
            ':offset' => $offset
        ]) ?: [];

        // Response
        $response = [
            'status' => 'success',
            'status_code' => SUCCESS_OK,
            'data' => [
                'devices' => $devices,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_devices' => $totalDevices,
                    'has_previous_page' => $page > 1,
                    'has_next_page' => $page < $totalPages,
                ]
            ],
            'message' => 'Devices retrieved successfully'
        ];

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (Exception $e) {
        $this->logger->log('error', 'Failed to search devices', ['exception' => $e]);
        ResponseHandler::sendResponse('error', ['message' => 'Failed to search devices'], ERROR_INTERNAL_SERVER);
    }
}




    /**
     * Update user details.
     *
     * @param int $userId The user ID.
     */
    public function updateUser($userId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Prevent updating sensitive fields
            $immutableFields = ['id', 'password', 'createdAt', 'updatedAt'];
            $userData = array_diff_key($data, array_flip($immutableFields));

            if (empty($userData)) {
                ResponseHandler::sendResponse('error', ['message' => 'No valid fields to update'], ERROR_BAD_REQUEST);
                return;
            }

            // Construct the SET clause and parameters
            $setClause = implode(', ', array_map(function ($key) {
                return "{$key} = :{$key}";
            }, array_keys($userData)));

            $params = [];
            foreach ($userData as $key => $value) {
                $params[":{$key}"] = $value;
            }
            $params[':id'] = $userId;

            $sql = "UPDATE users SET {$setClause} WHERE id = :id";
            $result = $this->db->query($sql, $params);

            if ($result) {
                ResponseHandler::sendResponse('success', ['message' => 'User updated successfully'], SUCCESS_OK);
            } else {
                ResponseHandler::sendResponse('error', ['message' => 'Failed to update user'], ERROR_INTERNAL_SERVER);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to update user', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to update user'], ERROR_INTERNAL_SERVER);
        }
    }




    /**
     * Get the last login time of a user.
     *
     * @param int $userId The user ID.
     */
    public function getLastLoginTime($userId)
    {
        try {
            $query = 'SELECT created_at FROM activity_log WHERE user_id = :user_id AND action = "login" ORDER BY created_at DESC LIMIT 1';
            $params = [':user_id' => [$userId, PDO::PARAM_INT]];
            $result = $this->db->query($query, $params);

            if ($result) {
                ResponseHandler::sendResponse('success', ['last_login' => $result[0]['created_at']], SUCCESS_OK);
            } else {
                ResponseHandler::sendResponse('success', ['message' => 'No login records found'], SUCCESS_OK);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to get last login time', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to get last login time'], ERROR_INTERNAL_SERVER);
        }
    }


    /**
     * Get IP addresses associated with a user.
     *
     * @param int $userId The user ID.
     */
    public function getUserIPs($userId)
    {
        try {
            $query = 'SELECT di.ip_address
                      FROM device_ips di
                      JOIN devices d ON di.device_id = d.id
                      WHERE d.user_id = :user_id';
            $params = [':user_id' => [$userId, PDO::PARAM_INT]];
            $ips = $this->db->query($query, $params);
            ResponseHandler::sendResponse('success', ['ips' => $ips, 'count' => count($ips)], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to get user IPs', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to get user IPs'], ERROR_INTERNAL_SERVER);
        }
    }
}
