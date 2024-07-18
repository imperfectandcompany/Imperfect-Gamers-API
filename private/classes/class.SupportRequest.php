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

    public function fetchAllRequestFormData() {
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


    private function buildHierarchicalTree($categories, $parentId = null) {
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


    private function buildCategoryTree($categories, $parentId = null) {
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

    private function fetchInputsForCategory($categoryId) {
        $params = [$categoryId];

        $inputsQuery = "SELECT i.id AS input_id, i.type AS input_type, iv.id AS input_version_id, iv.label AS input_label, io.option_value
                        FROM Inputs i
                        JOIN InputVersions iv ON i.id = iv.input_id
                        LEFT JOIN InputOptions io ON iv.id = io.input_id
                        WHERE i.category_id = ? AND iv.version = (
                            SELECT MAX(version) FROM InputVersions iv2 WHERE iv2.input_id = i.id
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

    private function fetchIssueForCategory($categoryId) {
        $params = [$categoryId];

        $issueQuery = "SELECT iv.id AS issue_version_id, iv.description AS issue_description
                       FROM IssueVersions iv
                       WHERE iv.category_id = ? AND iv.version = (
                           SELECT MAX(version) FROM IssueVersions iv2 WHERE iv2.category_id = iv.category_id
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



    function fetchInputsAndIssueForCategory($categoryId) {
        // Define parameters
        $params = [$categoryId];
    
        // Fetch inputs and their latest versions for the specified category
        $inputsQuery = "SELECT i.id AS input_id, i.type AS input_type, iv.id AS input_version_id, iv.label AS input_label, io.option_value
        FROM Inputs i
        JOIN InputVersions iv ON i.id = iv.input_id
        LEFT JOIN InputOptions io ON iv.id = io.input_id
        WHERE i.category_id = ? AND iv.version = (
            SELECT MAX(version) FROM InputVersions iv2 WHERE iv2.input_id = i.id
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
        $issueQuery = "SELECT iv.id AS issue_version_id, iv.description AS issue_description
                       FROM IssueVersions iv
                       WHERE iv.category_id = ? AND iv.version = (
                           SELECT MAX(version) FROM IssueVersions iv2 WHERE iv2.category_id = iv.category_id
                       )";
    
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
public function fetchActionLogs() {
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
    public function fetchAllInputs() {
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


        // Fetch all issues
// Fetch all issues
public function fetchAllIssues() {
    $issuesQuery = "SELECT * FROM IssueVersions ORDER BY category_id, id";

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
    public function fetchSupportRequest($supportRequestId) {
        $params = [$supportRequestId];

        $supportRequestQuery = "SELECT * FROM SupportRequests WHERE id = ?";

        try {
            $supportRequest = $this->supportSiteRequestsDb->query($supportRequestQuery, $params)[0];

            if ($supportRequest === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }

        // Fetch inputs and their values for the support request
        $supportRequest['inputs'] = $this->fetchRequestInputValues($supportRequestId);

        return $supportRequest;
    }


    private function fetchRequestInputValues($supportRequestId) {
        $params = [$supportRequestId];

        $inputValuesQuery = "SELECT riv.*, iv.label, iv.type
                             FROM RequestInputValues riv
                             JOIN InputVersions iv ON riv.input_version_id = iv.id
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
        public function fetchAllSupportRequests() {
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
    public function fetchCategoriesHierarchy() {
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
    public function fetchAllInputVersions() {
        $inputVersionsQuery = "SELECT * FROM InputVersions ORDER BY input_id, version";

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


    public function fetchCategoryVersions($categoryId) {
        $params = [$categoryId];
        $versionsQuery = "SELECT * FROM CategoryVersions WHERE category_id = ? ORDER BY version DESC";

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


    public function fetchCategoryVersionHistory($categoryId) {
        $params = [$categoryId];
        $historyQuery = "SELECT * FROM CategoryVersions WHERE category_id = ? ORDER BY version DESC";

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

    public function fetchIssueVersions($issueId) {
        $params = [$issueId];
        $versionsQuery = "SELECT * FROM IssueVersions WHERE category_id = ? ORDER BY version DESC";

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

    public function fetchIssueVersionHistory($issueId) {
        $params = [$issueId];
        $historyQuery = "SELECT * FROM IssueVersions WHERE category_id = ? ORDER BY version DESC";

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


    public function fetchSupportRequestVersions($supportRequestId) {
        $params = [$supportRequestId];
        $versionsQuery = "SELECT * FROM SupportRequestVersions WHERE support_request_id = ? ORDER BY version DESC";

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

    public function fetchSupportRequestVersionHistory($supportRequestId) {
        $params = [$supportRequestId];
        $historyQuery = "SELECT * FROM SupportRequestVersions WHERE support_request_id = ? ORDER BY version DESC";

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