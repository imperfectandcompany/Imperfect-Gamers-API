<?php

// classes/class.SupportRequest.php

/**
 * Support class handles request related activities for support website.
 */

class SupportRequest
{
    private $mainSiteDb; // Connection for main site related operations
    private $supportSiteDb;   // Connection for support site article related operations
    private $supportSiteRequestsDb;  // Connection for support site requests related operations

    // Define action type constants

    /**
     * Constructor for the Support class.
     * @param DatabaseConnector $mainSiteDb Connection object for the main website server database
     * @param DatabaseConnector $supportSiteDb Connection object for the support website server database
     * @param DatabaseConnector $supportSiteRequestsDb Connection object for the support website requests server database
     */

    public function __construct($mainSiteDb, $supportSiteDb, $supportSiteRequestsDb)
    {
        $this->mainSiteDb = $mainSiteDb;
        $this->supportSiteDb = $supportSiteDb;
        $this->supportSiteRequestsDb = $supportSiteRequestsDb;
    }


    function fetchAllLevelCategories()
    {
        // Fetch all categories with their parent relationships
        $categoriesQuery = "SELECT c1.id AS category_id, c1.name AS category_name, c2.id AS parent_id, c2.name AS parent_name
                            FROM Categories c1
                            LEFT JOIN Categories c2 ON c1.parent_id = c2.id";

        try {
            $categories = $this->supportSiteRequestsDb->query($categoriesQuery);

            if ($categories === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $categories;
    }

    public function fetchAllRequestFormData()
    {
        // Fetch all RequestFormData
        $categoriesQuery = "SELECT id AS category_id, name AS category_name, parent_id FROM Categories";

        try {
            $categories = $this->supportSiteRequestsDb->query($categoriesQuery);

            if ($categories === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        // Build entire hierarchical tree
        $categoryTree = $this->buildHierarchicalTree($categories);

        return $categoryTree;
    }


    private function buildHierarchicalTree($categories, $parentId = null)
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = $this->buildCategoryTree($categories, $category['category_id']);

                $category['subcategories'] = $children ?: [];
                unset($category['parent_id']);  // Remove parent_id from the parent category

                // Fetch inputs and issue details for each category
                $category['inputs'] = $this->fetchInputsForCategory($category['category_id']);
                $category['issue'] = $this->fetchIssueForCategory($category['category_id']);

                $tree[] = $category;
            }
        }

        return $tree;
    }


    private function buildCategoryTree($categories, $parentId = null)
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = $this->buildCategoryTree($categories, $category['category_id']);

                $category['subcategories'] = $children ?: [];
                unset($category['parent_id']);

                $tree[] = $category;
            }
        }

        return $tree;
    }

    private function fetchInputsForCategory($categoryId)
    {
        $params = [':categoryId' => $categoryId];

        $inputsQuery = "SELECT 
                            i.id AS input_id, 
                            iv.type AS input_type, 
                            iv.input_version_id AS input_version_id, 
                            iv.label AS input_label, 
                            io.option_value
                        FROM Inputs i
                        JOIN InputVersions iv ON i.id = iv.input_id
                        LEFT JOIN InputOptions io ON iv.input_version_id = io.input_id
                        WHERE i.category_id = :categoryId 
                        AND iv.input_version_id = (
                            SELECT MAX(iv2.input_version_id) 
                            FROM InputVersions iv2 
                            WHERE iv2.input_id = i.id
                        )";

        try {
            $inputs = $this->supportSiteRequestsDb->query($inputsQuery, $params);

            if ($inputs === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        // Group inputs by input_id and organize options
        $groupedInputs = [];
        foreach ($inputs as $input) {
            if (!isset($groupedInputs[$input['input_id']])) {
                $groupedInputs[$input['input_id']] = [
                    'input_id' => $input['input_id'],
                    'input_type' => $input['input_type'],
                    'input_version_id' => $input['input_version_id'],
                    'input_label' => $input['input_label'],
                    'options' => []
                ];
            }
            if ($input['option_value'] !== null) {
                $groupedInputs[$input['input_id']]['options'][] = $input['option_value'];
            }
        }

        return array_values($groupedInputs);
    }
    private function fetchIssueForCategory($categoryId)
    {
        $params = [':categoryId' => $categoryId];

        $issueQuery = "SELECT 
                        iv.issue_version_id, 
                        iv.description AS issue_description, 
                        iv.user_id,
                        iv.created_at
                    FROM Issues i
                    JOIN IssueVersions iv ON i.current_version_id = iv.issue_version_id
                    WHERE i.category_id = :categoryId
                    AND iv.deleted_at IS NULL
                    AND iv.issue_version_id = (
                        SELECT MAX(iv2.issue_version_id) 
                        FROM IssueVersions iv2 
                        WHERE iv2.issue_id = i.id
                    )";

        try {
            $issue = $this->supportSiteRequestsDb->query($issueQuery, $params);

            if ($issue === false || empty($issue)) {
                return null;  // No issue found
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $issue[0]; // Assuming the issue query returns a single row
    }




    function fetchInputsAndIssueForCategory($categoryId)
    {
        // Define parameters
        $params = [':categoryId' => $categoryId];

        // Fetch inputs and their latest versions for the specified category
        $inputsQuery = "SELECT i.id AS input_id, iv.type AS input_type, iv.input_version_id AS input_version_id, iv.label AS input_label, io.option_value
        FROM Inputs i
        JOIN InputVersions iv ON i.id = iv.input_id
        LEFT JOIN InputOptions io ON iv.input_version_id = io.input_id
        WHERE i.category_id = ? AND iv.input_version_id = (
            SELECT MAX(input_version_id) FROM InputVersions iv2 WHERE iv2.input_id = i.id
        )";

        try {
            $inputs = $this->supportSiteRequestsDb->query($inputsQuery, $params);
            if ($inputs === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        // Group inputs by input_id and organize options
        $groupedInputs = [];
        foreach ($inputs as $input) {
            if (!isset($groupedInputs[$input['input_id']])) {
                $groupedInputs[$input['input_id']] = [
                    'input_id' => $input['input_id'],
                    'input_type' => $input['input_type'],
                    'input_version_id' => $input['input_version_id'],
                    'input_label' => $input['input_label'],
                    'options' => []
                ];
            }
            if ($input['option_value'] !== null) {
                $groupedInputs[$input['input_id']]['options'][] = $input['option_value'];
            }
        }

        // Convert grouped inputs to a list
        $inputsList = array_values($groupedInputs);

        // Fetch the latest version of the issue for the specified category
        $issueQuery = "SELECT 
                            iv.issue_version_id, 
                            iv.description AS issue_description, 
                            iv.user_id
                        FROM Issues i
                        JOIN IssueVersions iv ON i.current_version_id = iv.issue_version_id
                        WHERE i.category_id = :categoryId 
                        AND iv.deleted_at IS NULL";

        try {
            $issue = $this->supportSiteRequestsDb->query($issueQuery, $params);
            if ($issue === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        // Combine all the data into an associative array
        $formData = [
            'inputs' => $inputsList,
            'issue' => $issue[0]  // Assuming the issue query returns a single row
        ];

        return $formData;
    }




    // Fetch action logs
// Fetch action logs
    public function fetchActionLogs()
    {
        $logsQuery = "SELECT * FROM ActionLogs ORDER BY created_at DESC";

        try {
            $logs = $this->supportSiteRequestsDb->query($logsQuery);

            if ($logs === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $logs;
    }


    // Fetch all inputs
    public function fetchAllInputs()
    {
        $inputsQuery = "SELECT * FROM Inputs ORDER BY category_id, id";

        try {
            $inputs = $this->supportSiteRequestsDb->query($inputsQuery);

            if ($inputs === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $inputs;
    }


    // Fetch all issues with their latest version
    public function fetchAllIssues()
    {
        // Join the Issues table with IssueVersions to get the latest version for each issue
        $issuesQuery = "SELECT i.id as issue_id, iv.issue_version_id, i.category_id, iv.description, iv.user_id, iv.created_at, iv.updated_at
            FROM Issues i
            INNER JOIN IssueVersions iv ON i.current_version_id = iv.issue_version_id
            ORDER BY i.category_id, i.id";

        try {
            $issues = $this->supportSiteRequestsDb->query($issuesQuery);

            if ($issues === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $issues;
    }


    // Fetch a specific support request
    public function fetchSupportRequest($supportRequestId)
    {
        $params = [$supportRequestId];

        $supportRequestQuery = "SELECT * FROM SupportRequests WHERE id = ?";

        try {
            $result = $this->supportSiteRequestsDb->query($supportRequestQuery, $params);

            if ($result === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }

            if (empty($result)) {
                throw new Exception("Support request not found");
            }

            $supportRequest = $result[0];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // Fetch inputs and their values for the support request
        $supportRequest['inputs'] = $this->fetchRequestInputValues($supportRequestId);

        return $supportRequest;
    }


    private function fetchRequestInputValues($supportRequestId)
    {
        $params = [$supportRequestId];

        $inputValuesQuery = "SELECT riv.*, iv.label, iv.type
                             FROM RequestInputValues riv
                             JOIN InputVersions iv ON riv.input_version_id = iv.input_version_id
                             WHERE riv.support_request_id = ?";

        try {
            $inputValues = $this->supportSiteRequestsDb->query($inputValuesQuery, $params);

            if ($inputValues === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $inputValues;
    }


    // Fetch all support requests
    public function fetchAllSupportRequests()
    {
        $supportRequestsQuery = "SELECT * FROM SupportRequests ORDER BY created_at DESC";

        try {
            $supportRequests = $this->supportSiteRequestsDb->query($supportRequestsQuery);

            if ($supportRequests === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $supportRequests;
    }


    // Fetch all categories' hierarchy
    public function fetchCategoriesHierarchy()
    {
        $categoriesQuery = "SELECT id AS category_id, name AS category_name, parent_id FROM Categories";

        try {
            $categories = $this->supportSiteRequestsDb->query($categoriesQuery);

            if ($categories === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        // Build hierarchical category tree
        $categoryTree = $this->buildCategoryTree($categories);

        return $categoryTree;
    }


    // Fetch all input versions
    public function fetchAllInputVersions()
    {
        $inputVersionsQuery = "SELECT * FROM InputVersions ORDER BY input_id, input_version_id";

        try {
            $inputVersions = $this->supportSiteRequestsDb->query($inputVersionsQuery);

            if ($inputVersions === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $inputVersions;
    }


    public function fetchCategoryVersions($categoryId)
    {
        $params = [$categoryId];
        $versionsQuery = "SELECT * FROM CategoryVersions WHERE category_version_id = ? ORDER BY category_version_id DESC";

        try {
            $versions = $this->supportSiteRequestsDb->query($versionsQuery, $params);

            if ($versions === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $versions;
    }


    public function fetchCategoryVersionHistory($categoryId)
    {
        $params = [$categoryId];
        $historyQuery = "SELECT * FROM CategoryVersions WHERE category_version_id = ? ORDER BY category_version_id DESC";

        try {
            $history = $this->supportSiteRequestsDb->query($historyQuery, $params);

            if ($history === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $history;
    }

    public function fetchIssueVersions($issueId)
    {
        $params = [$issueId];
        $versionsQuery = "SELECT * FROM IssueVersions WHERE issue_id = ? ORDER BY issue_version_id DESC";

        try {
            $versions = $this->supportSiteRequestsDb->query($versionsQuery, $params);

            if ($versions === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $versions;
    }

    public function fetchIssueVersionHistory($issueId)
    {
        $params = [$issueId];
        $historyQuery = "SELECT * FROM IssueVersions WHERE issue_id = ? ORDER BY issue_version_id DESC";

        try {
            $history = $this->supportSiteRequestsDb->query($historyQuery, $params);

            if ($history === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $history;
    }


    public function fetchSupportRequestVersions($supportRequestId)
    {
        $params = [$supportRequestId];
        $versionsQuery = "SELECT * FROM SupportRequestVersions WHERE support_request_id = ? ORDER BY supportrequest_version_id DESC";

        try {
            $versions = $this->supportSiteRequestsDb->query($versionsQuery, $params);

            if ($versions === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $versions;
    }

    public function fetchSupportRequestVersionHistory($supportRequestId)
    {
        $params = [$supportRequestId];
        $historyQuery = "SELECT * FROM SupportRequestVersions WHERE support_request_id = ? ORDER BY supportrequest_version_id DESC";

        try {
            $history = $this->supportSiteRequestsDb->query($historyQuery, $params);

            if ($history === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $history;
    }











    // Category methods
    public function createCategory($data)
    {
        $query = "INSERT INTO Category (name, parent_id, default_priority) VALUES (:name, :parent_id, :default_priority)";
        $params = [':name' => $data['name'], ':parent_id' => $data['parent_id'], ':default_priority' => $data['default_priority']];
        $this->supportSiteRequestsDb->query($query, $params);
        $categoryId = $this->supportSiteRequestsDb->lastInsertId();

        // Create initial version
        $this->createCategoryVersion($categoryId, $data);

        return $categoryId;
    }

    public function updateCategory($categoryId, $data)
    {
        $query = "UPDATE Category SET name = :name, parent_id = :parent_id, default_priority = :default_priority WHERE id = :id";
        $params = [':name' => $data['name'], ':parent_id' => $data['parent_id'], ':default_priority' => $data['default_priority'], ':id' => $categoryId];
        $this->supportSiteRequestsDb->query($query, $params);

        // Create new version
        $this->createCategoryVersion($categoryId, $data);

        return true;
    }

    public function deleteCategory($categoryId)
    {
        $query = "DELETE FROM Category WHERE id = :id";
        $params = [':id' => $categoryId];
        return $this->supportSiteRequestsDb->query($query, $params);
    }

    // Input methods
    public function createInput($data)
    {
        // Step 1: Insert a new input entry with placeholder for current_version_id
        $query = "INSERT INTO Inputs (category_id) VALUES (:category_id)";
        $params = [':category_id' => $data['category_id']];
        $this->supportSiteRequestsDb->query($query, $params);
        $inputId = $this->supportSiteRequestsDb->lastInsertId();

        // Step 2: Create initial input version
        $inputVersionId = $this->createInputVersion($inputId, $data['label'], $data['type']);

        // Step 3: Update the input with the current_version_id
        $query = "UPDATE Inputs SET current_version_id = :current_version_id WHERE id = :id";
        $params = [
            ':current_version_id' => $inputVersionId,
            ':id' => $inputId
        ];
        $this->supportSiteRequestsDb->query($query, $params);

        return $inputId;
    }

    public function updateInput($inputId, $data)
    {
        // Step 1: Create a new input version
        $inputVersionId = $this->createInputVersion($inputId, $data['label'], $data['type']);

        // Step 2: Update the input with the new current_version_id
        $query = "UPDATE Inputs SET current_version_id = :current_version_id WHERE id = :id";
        $params = [
            ':current_version_id' => $inputVersionId,
            ':id' => $inputId
        ];
        $this->supportSiteRequestsDb->query($query, $params);

        return $inputId;
    }

private function createInputVersion($inputId, $data, $version)
{
    $query = "INSERT INTO InputVersions (input_id, input_version_id, label, type, user_id, deleted_at, created_at, updated_at) 
              VALUES (:input_id, :version, :label, :type, :user_id, :deleted_at, NOW(), NOW())";
    $params = [
        ':input_id' => $inputId,
        ':version' => $version,
        ':label' => $data['label'] ?? null,
        ':type' => $data['type'] ?? null,
        ':user_id' => $GLOBALS['user_id'] ?? null,
        ':deleted_at' => $data['deleted_at'] ?? null
    ];
    $this->supportSiteRequestsDb->query($query, $params);
}




    private function getLatestInputVersion($inputId)
    {
        $query = "SELECT MAX(version) as latest_version FROM InputVersions WHERE input_id = :input_id";
        $params = [':input_id' => $inputId];
        $result = $this->supportSiteRequestsDb->query($query, $params);

        if (!empty($result)) {
            return $result[0]['latest_version'] ?? 0;
        } else {
            return 0; // Default to 0 if no versions found
        }
    }

    public function deleteInput($inputId)
    {
        // Check if the input exists and is not already deleted
        $inputExistsQuery = "SELECT id FROM Inputs WHERE id = :id AND deleted_at IS NULL";
        $inputExistsParams = [':id' => $inputId];
        $inputExistsResult = $this->supportSiteRequestsDb->query($inputExistsQuery, $inputExistsParams);
    
        if (empty($inputExistsResult)) {
            throw new Exception("Input not found or already deleted.");
        }
    
        // Start a transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();
    
        try {
            // Mark the input as deleted
            $query = "UPDATE Inputs SET deleted_at = NOW() WHERE id = :id";
            $params = [':id' => $inputId];
            $this->supportSiteRequestsDb->query($query, $params);
    
            // Get the latest version for logging
            $latestVersion = $this->getLatestInputVersion($inputId) + 1;
    
            // Create a new input version entry marking it as deleted
            $this->createInputVersion($inputId, [
                'deleted_at' => date('Y-m-d H:i:s')
            ], $latestVersion);
    
            // Log action
            $this->logAction($inputId, 'Input', $latestVersion, 'delete_input');
    
            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
    
            return true;
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }




    // Support request methods
    public function fetchDynamicInputsByCategory($categoryId)
    {
        $query = "SELECT i.id as input_id, iv.input_version_id, iv.label, iv.type 
              FROM Inputs i 
              JOIN InputVersions iv ON i.id = iv.input_id 
              WHERE i.category_id = :category_id AND i.deleted_at IS NULL AND iv.deleted_at IS NULL 
              AND iv.input_version_id = i.current_version_id";
        $params = [':category_id' => $categoryId];
        return $this->supportSiteRequestsDb->query($query, $params);
    }

    public function createSupportRequest($data, $dynamicInputs)
    {
        // Step 1: Retrieve the current_version_id from the Issues table based on the category_id
        $query = "SELECT current_version_id FROM Issues WHERE category_id = :category_id LIMIT 1";
        $params = [':category_id' => $data['category_id']];
        $result = $this->supportSiteRequestsDb->query($query, $params);

        if (empty($result)) {
            throw new Exception("No issue found for the given category_id");
        }

        $currentVersionId = $result[0]['current_version_id'];

        // Step 2: Retrieve the issue_version_id from the IssueVersions table based on the current_version_id
        $query = "SELECT issue_version_id FROM IssueVersions WHERE issue_id = :issue_id LIMIT 1";
        $params = [':issue_id' => $currentVersionId];
        $result = $this->supportSiteRequestsDb->query($query, $params);

        if (empty($result)) {
            throw new Exception("No issue version found for the given issue_id");
        }

        $issueVersionId = $result[0]['issue_version_id'];

        // Step 3: Insert a new support request entry with the retrieved issue_version_id and placeholder for current_version_id
        $query = "INSERT INTO SupportRequests (category_id, issue_version_id, current_version_id) VALUES (:category_id, :issue_version_id, NULL)";
        $params = [
            ':category_id' => $data['category_id'],
            ':issue_version_id' => $issueVersionId
        ];
        $this->supportSiteRequestsDb->query($query, $params);
        $supportRequestId = $this->supportSiteRequestsDb->lastInsertId();

        // Step 4: Create initial support request version and get the new version ID then log action
        $insertQuery = "INSERT INTO SupportRequestVersions 
                        (support_request_id, status, priority, email, ip_address, user_id, created_at) 
                        VALUES (:support_request_id, :status, :priority, :email, :ip_address, :user_id, NOW())";
        $insertParams = [
            ':support_request_id' => $supportRequestId,
            ':status' => $data['status'] ?? 'open',
            ':priority' => $data['priority'] ?? 'low',
            ':email' => $data['email'],
            ':ip_address' => $_SERVER['REMOTE_ADDR'],
            ':user_id' => $GLOBALS['user_id'] ?? null
        ];
        $this->supportSiteRequestsDb->query($insertQuery, $insertParams);

        // Get the ID of the newly inserted version
        $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

        // Log action
        $this->logAction($supportRequestId, 'SupportRequest', $newVersionId, 'create_support_request');

        // Step 5: Update the support request with the new current_version_id
        $query = "UPDATE SupportRequests SET current_version_id = :current_version_id WHERE id = :id";
        $params = [
            ':current_version_id' => $newVersionId,
            ':id' => $supportRequestId
        ];
        $this->supportSiteRequestsDb->query($query, $params);

        // Step 6: Store dynamic input values
        foreach ($dynamicInputs as $dynamicInput) {
            $label = $dynamicInput['label'];
            $value = $data[$label];
            $this->storeRequestInputValue($supportRequestId, $dynamicInput['input_version_id'], $value);
        }

        return $supportRequestId;
    }


    private function storeRequestInputValue($supportRequestId, $inputVersionId, $value)
    {
        $query = "INSERT INTO RequestInputValues (support_request_id, input_version_id, value) VALUES (:support_request_id, :input_version_id, :value)";
        $params = [
            ':support_request_id' => $supportRequestId,
            ':input_version_id' => $inputVersionId,
            ':value' => $value
        ];
        return $this->supportSiteRequestsDb->query($query, $params);
    }

    private function createSupportRequestVersion($supportRequestId, $data)
    {

        $query = "INSERT INTO SupportRequestVersions (support_request_id, version, status, priority, email, ip_address";
        $params = [
            ':support_request_id' => $supportRequestId,
            ':status' => $data['status'] ?? 'open',
            ':priority' => $data['priority'] ?? 'low',
            ':email' => $data['email'],
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ];

        if (isset($GLOBALS['user_id'])) {
            $query .= ", user_id";
            $params[':user_id'] = $GLOBALS['user_id'];
        }

        $query .= ") VALUES (:support_request_id, :version, :status, :priority, :email, :ip_address";

        if (isset($GLOBALS['user_id'])) {
            $query .= ", :user_id";
        }

        $query .= ")";

        $version = $this->getLatestSupportRequestVersion($supportRequestId) + 1;

        $this->supportSiteRequestsDb->query($query, $params);

        $newVersionId = $this->supportSiteRequestsDb->lastInsertId();

        // Log action
        $action = ($version == 1) ? 'create_support_request' : 'update_support_request';
        $this->logAction($supportRequestId, 'SupportRequest', $version, $action);

        // Return the new version ID
        return $newVersionId;
    }

    public function updateSupportRequest($supportRequestId, $data)
    {
        $setClauses = [];
        $params = [':id' => $supportRequestId];

        if (isset($data['category_id'])) {
            $setClauses[] = 'category_id = :category_id';
            $params[':category_id'] = $data['category_id'];
        }
        if (isset($data['issue_version_id'])) {
            $setClauses[] = 'issue_version_id = :issue_version_id';
            $params[':issue_version_id'] = $data['issue_version_id'];
        }
        if (isset($data['current_version_id'])) {
            $setClauses[] = 'current_version_id = :current_version_id';
            $params[':current_version_id'] = $data['current_version_id'];
        }

        if (!empty($setClauses)) {
            $query = "UPDATE SupportRequests SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $this->supportSiteRequestsDb->query($query, $params);
        }

        // Create new version
        $newVersionId = $this->createSupportRequestVersion($supportRequestId, $data);

        // Update the current_version_id with the new version ID
        $query = "UPDATE SupportRequests SET current_version_id = :current_version_id WHERE id = :id";
        $params = [':current_version_id' => $newVersionId, ':id' => $supportRequestId];
        $this->supportSiteRequestsDb->query($query, $params);

        return true;
    }

    public function deleteSupportRequest($supportRequestId)
    {
        $query = "DELETE FROM SupportRequests WHERE id = :id";
        $params = [':id' => $supportRequestId];
        return $this->supportSiteRequestsDb->query($query, $params);
    }

    // Version and Logging methods

    private function createCategoryVersion($categoryId, $data)
    {
        $query = "INSERT INTO CategoryVersions (category_id, version, name, parent_id) VALUES (:category_id, :version, :name, :parent_id)";
        $version = $this->getLatestCategoryVersion($categoryId) + 1;
        $params = [
            ':category_id' => $categoryId,
            ':version' => $version,
            ':name' => $data['name'],
            ':parent_id' => $data['parent_id']
        ];
        $this->supportSiteRequestsDb->query($query, $params);

        // Log action
        $this->logAction($categoryId, 'Category', $version, 'update_category');
    }

    private function getLatestCategoryVersion($categoryId)
    {
        $query = "SELECT MAX(version) as latest_version FROM CategoryVersions WHERE category_version_id = :category_id";
        $params = [':category_id' => $categoryId];
        $result = $this->supportSiteRequestsDb->query($query, $params);
        return $result[0]['latest_version'] ?? 0;
    }

    private function getLatestSupportRequestVersion($supportRequestId)
    {
        $query = "SELECT MAX(supportrequest_version_id) as latest_version FROM SupportRequestVersions WHERE support_request_id = :support_request_id";
        $params = [':support_request_id' => $supportRequestId];
        $result = $this->supportSiteRequestsDb->query($query, $params);
        return $result[0]['latest_version'] ?? 0;
    }

    private function logAction($targetId, $targetType, $targetVersion, $action)
    {
        $userId = $GLOBALS['user_id'];
        $query = "INSERT INTO ActionLogs (user_id, action, target_id, target_type, target_version) VALUES (:user_id, :action, :target_id, :target_type, :target_version)";
        $params = [
            ':user_id' => $userId,
            ':action' => $action,
            ':target_id' => $targetId,
            ':target_type' => $targetType,
            ':target_version' => $targetVersion
        ];
        $this->supportSiteRequestsDb->query($query, $params);
    }














    // Fetching Open Requests
    public function fetchOpenRequests()
    {
        // Define the query to fetch open requests along with their current version details
        $query = "SELECT 
            sr.id AS request_id,
            sr.category_id,
            sr.issue_version_id,
            sr.current_version_id,
            sr.created_at,
            sr.updated_at,
            srv.status,
            srv.priority,
            srv.email,
            srv.ip_address,
            srv.user_id,
            srv.created_at AS version_created_at,
            srv.updated_at AS version_updated_at,
            c.name AS category_name,
            iv.description AS issue_description
        FROM SupportRequests sr
        JOIN SupportRequestVersions srv ON sr.current_version_id = srv.supportrequest_version_id
        LEFT JOIN Categories c ON sr.category_id = c.id
        LEFT JOIN IssueVersions iv ON sr.issue_version_id = iv.issue_version_id
        WHERE srv.status = 'open'
        ORDER BY srv.priority DESC, srv.updated_at DESC";

        return $this->supportSiteRequestsDb->query($query);
    }



    // Updating Status
    public function updateStatus($requestId, $status)
    {
        // Validate inputs
        if (empty($requestId) || !in_array($status, ['open', 'in progress', 'closed'])) {
            throw new InvalidArgumentException("Invalid request ID or status.");
        }

        // Ensure the support request exists and is not deleted
        $supportRequest = $this->fetchSupportRequest($requestId);
        if (!$supportRequest || $supportRequest['deleted_at'] !== null) {
            throw new Exception("Support request not found or has been deleted.");
        }

        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        try {
            // Step 1: Get the current version ID from the SupportRequests table
            $currentVersionIdQuery = "SELECT current_version_id FROM SupportRequests WHERE id = :id";
            $currentVersionParams = [':id' => $requestId];
            $currentVersionResult = $this->supportSiteRequestsDb->query($currentVersionIdQuery, $currentVersionParams);
            $currentVersionId = $currentVersionResult[0]['current_version_id'];

            // Step 2: Retrieve the latest details from SupportRequestVersions using the current version ID
            $latestVersionQuery = "SELECT priority, email, ip_address, user_id FROM SupportRequestVersions WHERE supportrequest_version_id = :version_id";
            $latestVersionParams = [':version_id' => $currentVersionId];
            $latestVersionResult = $this->supportSiteRequestsDb->query($latestVersionQuery, $latestVersionParams)[0];
            $latestVersion = $latestVersionResult;
            // Step 3: Insert new entry in SupportRequestVersions table with updated status
            $insertQuery = "INSERT INTO SupportRequestVersions 
                        (support_request_id, status, priority, email, ip_address, user_id, created_at) 
                        VALUES (:support_request_id, :status, :priority, :email, :ip_address, :user_id, NOW())";
            $insertParams = [
                ':support_request_id' => $requestId,
                ':status' => $status,
                ':priority' => $latestVersion['priority'],
                ':email' => $latestVersion['email'],
                ':ip_address' => $latestVersion['ip_address'],
                ':user_id' => $GLOBALS['user_id'] ?? null
            ];
            $this->supportSiteRequestsDb->query($insertQuery, $insertParams);

            // Get the ID of the newly inserted version
            $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

            // Step 4: Update the current_version_id in the SupportRequests table
            $updateCurrentVersionQuery = "UPDATE SupportRequests SET current_version_id = :version_id WHERE id = :id";
            $updateCurrentVersionParams = [':version_id' => $newVersionId, ':id' => $requestId];
            $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, $updateCurrentVersionParams);

            // Log the action
            $this->logAction($requestId, 'SupportRequest', $newVersionId, 'update_status');

            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }


    // Updating Priority
    public function updatePriority($requestId, $priority)
    {
        // Validate inputs
        if (empty($requestId) || !in_array($priority, ['low', 'medium', 'high'])) {
            throw new InvalidArgumentException("Invalid request ID or priority.");
        }

        // Ensure the support request exists and is not deleted
        $supportRequest = $this->fetchSupportRequest($requestId);
        if (!$supportRequest || $supportRequest['deleted_at'] !== null) {
            throw new Exception("Support request not found or has been deleted.");
        }

        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        try {
            // Step 1: Get the current version ID from the SupportRequests table
            $currentVersionIdQuery = "SELECT current_version_id FROM SupportRequests WHERE id = :id";
            $currentVersionParams = [':id' => $requestId];
            $currentVersionResult = $this->supportSiteRequestsDb->query($currentVersionIdQuery, $currentVersionParams);
            $currentVersionId = $currentVersionResult[0]['current_version_id'];

            // Step 2: Retrieve the latest details from SupportRequestVersions using the current version ID
            $latestVersionQuery = "SELECT status, email, ip_address, user_id FROM SupportRequestVersions WHERE supportrequest_version_id = :version_id";
            $latestVersionParams = [':version_id' => $currentVersionId];
            $latestVersionResult = $this->supportSiteRequestsDb->query($latestVersionQuery, $latestVersionParams)[0];
            $latestVersion = $latestVersionResult;
            // Step 3: Insert new entry in SupportRequestVersions table with updated priority
            $insertQuery = "INSERT INTO SupportRequestVersions 
                        (support_request_id, status, priority, email, ip_address, user_id, created_at) 
                        VALUES (:support_request_id, :status, :priority, :email, :ip_address, :user_id, NOW())";
            $insertParams = [
                ':support_request_id' => $requestId,
                ':status' => $latestVersion['status'],
                ':priority' => $priority,
                ':email' => $latestVersion['email'],
                ':ip_address' => $latestVersion['ip_address'],
                ':user_id' => $GLOBALS['user_id'] ?? null
            ];
            $this->supportSiteRequestsDb->query($insertQuery, $insertParams);

            // Get the ID of the newly inserted version
            $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

            // Step 4: Update the current_version_id in the SupportRequests table
            $updateCurrentVersionQuery = "UPDATE SupportRequests SET current_version_id = :version_id WHERE id = :id";
            $updateCurrentVersionParams = [':version_id' => $newVersionId, ':id' => $requestId];
            $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, $updateCurrentVersionParams);

            // Log the action
            $this->logAction($requestId, 'SupportRequest', $newVersionId, 'update_priority');

            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }


    // Fetching Request Details and History:

    public function fetchRequestDetails($requestId)
    {
        // Fetch basic support request details
        $requestQuery = "SELECT 
                            sr.id AS request_id,
                            sr.category_id,
                            sr.issue_version_id,
                            sr.current_version_id,
                            sr.created_at,
                            sr.updated_at,
                            c.name AS category_name,
                            iv.description AS issue_description,
                            cv.name AS current_issue
                         FROM SupportRequests sr
                         LEFT JOIN Categories c ON sr.category_id = c.id
                         LEFT JOIN IssueVersions iv ON sr.issue_version_id = iv.issue_version_id
                         LEFT JOIN CategoryVersions cv ON sr.current_version_id = cv.category_version_id
                         WHERE sr.id = :id";
        $requestParams = [':id' => $requestId];
        $requestDetails = $this->supportSiteRequestsDb->query($requestQuery, $requestParams);

        if (empty($requestDetails)) {
            throw new Exception("Support request not found.");
        }

        // Fetch the current version details from SupportRequestVersions
        $versionQuery = "SELECT 
                    srv.supportrequest_version_id AS version_id,
                    srv.status,
                    srv.priority,
                    srv.email,
                    srv.ip_address,
                    srv.user_id,
                    srv.created_at
                 FROM SupportRequestVersions srv
                 WHERE srv.support_request_id = :support_request_id
                 AND srv.supportrequest_version_id = :current_version_id";
        $versionParams = [
            ':support_request_id' => $requestId,
            ':current_version_id' => $requestDetails[0]['current_version_id']
        ];
        $versionDetails = $this->supportSiteRequestsDb->query($versionQuery, $versionParams);

        if (empty($versionDetails)) {
            throw new Exception("Support request version not found.");
        }

        // Combine the basic request details and current version details
        $details = array_merge($requestDetails[0], $versionDetails[0]);

        return $details;
    }



    public function fetchRequestHistory($requestId)
    {
        try {
            $query = "SELECT 
                    srv.supportrequest_version_id AS version_id,
                    srv.status,
                    srv.priority,
                    srv.email,
                    srv.ip_address,
                    srv.user_id,
                    srv.created_at
                  FROM SupportRequestVersions srv
                  WHERE srv.support_request_id = :support_request_id 
                  ORDER BY srv.supportrequest_version_id DESC";
            $params = [':support_request_id' => $requestId];
            $request = $this->supportSiteRequestsDb->query($query, $params);

            if (empty($request)) {
                throw new Exception("Support request not found.");
            }

            return $request;

        } catch (PDOException $e) {
            // Handle the error, e.g., log it and throw a custom exception
            error_log("Failed to fetch request history: " . $e->getMessage());
            throw new Exception("Failed to fetch request history");
        }
    }



    public function addComment($supportRequestId, $userId, $comment)
    {
        $query = "INSERT INTO SupportRequestComments (support_request_id, user_id, comment) VALUES (:support_request_id, :user_id, :comment)";
        $params = [
            ':support_request_id' => $supportRequestId,
            ':user_id' => $userId,
            ':comment' => $comment
        ];
        try {
            $result = $this->supportSiteRequestsDb->query($query, $params);
            var_dump($result);
            die();

            if ($result === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }

        } catch (PDOException $e) {
            // Handle the error, e.g., log it and return a meaningful message
            throw new PDOException("Failed to add comment: " . $e->getMessage());
        }
    }

    public function fetchComments($supportRequestId)
    {
        $query = "SELECT * FROM SupportRequestComments 
                  WHERE support_request_id = :support_request_id 
                  ORDER BY created_at DESC";
        $params = [':support_request_id' => $supportRequestId];
        return $this->supportSiteRequestsDb->query($query, $params);
    }

    // public function createSupportRequest($data)
// {
//     $query = "INSERT INTO SupportRequests (category_id, issue, description) VALUES (:category_id, :issue, :description)";
//     $params = [
//         ':category_id' => $data['category_id'], 
//         ':issue' => $data['issue'], 
//         ':description' => $data['description']
//     ];
//     $this->supportSiteRequestsDb->query($query, $params);
//     $supportRequestId = $this->supportSiteRequestsDb->lastInsertId();

    //     // Create initial version
//     $this->createSupportRequestVersion($supportRequestId, $data);

    //     return $supportRequestId;
// }


    public function updateSupportRequestVersion($supportRequestId, $data)
    {
        $query = "INSERT INTO SupportRequestVersions (support_request_id, version, status, priority, email, ip_address, user_id) 
              SELECT support_request_id, version + 1, :status, :priority, email, ip_address, :user_id 
              FROM SupportRequestVersions 
              WHERE support_request_id = :support_request_id 
              ORDER BY version DESC 
              LIMIT 1";
        $params = [
            ':support_request_id' => $supportRequestId,
            ':status' => $data['status'],
            ':priority' => $data['priority'],
            ':user_id' => $GLOBALS['user_id']
        ];
        return $this->supportSiteRequestsDb->query($query, $params);
    }













    // User-facing
    function fetchAllCategoriesz()
    {
        // SQL query to fetch all categories
    }

    function fetchLatestInputVersionsForCategory($categoryId)
    {
        // SQL query to fetch the latest versions of inputs for a specific category
    }

    function fetchLatestIssueVersionForCategory($categoryId)
    {
        // SQL query to fetch the latest version of an issue for a specific category
    }

    function fetchSupportRequestsByUserEmail($email)
    {
        // SQL query to fetch all support requests for a specific user email
    }

    function fetchSupportRequestDetails($supportRequestId)
    {
        // SQL query to fetch all details of a specific support request
    }

    function insertSupportRequest($categoryId, $issueVersionId, $status, $priority, $email, $ipAddress)
    {
        // SQL query to insert a new support request
    }

    function insertRequestInputValues($supportRequestId, $inputVersionId, $value)
    {
        // SQL query to insert input values for a support request
    }


    // Internal admin/management
    function fetchCategoriesWithInputsAndLatestVersions()
    {
        // SQL query to fetch categories with their inputs and the latest input versions
    }

    function fetchSupportRequestsWithDetails()
    {
        // SQL query to fetch all support requests with their input values and issue descriptions
    }



}