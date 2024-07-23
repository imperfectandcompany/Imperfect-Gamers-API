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
        // Fetch all categories with their parent relationships and default priority from CategoryVersions
        $categoriesQuery = "SELECT 
                                c1.id AS category_id, 
                                cv1.name AS category_name, 
                                c1.parent_id, 
                                cv2.name AS parent_name,
                                cv1.default_priority
                            FROM Categories c1
                            LEFT JOIN CategoryVersions cv1 ON c1.current_version_id = cv1.category_version_id
                            LEFT JOIN Categories c2 ON c1.parent_id = c2.id
                            LEFT JOIN CategoryVersions cv2 ON c2.current_version_id = cv2.category_version_id
                            WHERE cv1.deleted_at IS NULL AND (cv2.deleted_at IS NULL OR cv2.deleted_at IS NOT NULL)";
    
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
    
public function updateIssue($issueId, $data)
{
    try {
        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        // Fetch the current version id of the issue
        $fetchCurrentVersionQuery = "SELECT current_version_id FROM Issues WHERE id = :issue_id";
        $params = [':issue_id' => $issueId];
        $result = $this->supportSiteRequestsDb->query($fetchCurrentVersionQuery, $params);

        if (!$result || empty($result[0]['current_version_id'])) {
            throw new Exception("Issue or its current version not found.");
        }

        // Create a new version with the updated details
        $createVersionQuery = "INSERT INTO IssueVersions (issue_id, description, user_id, created_at) 
                               VALUES (:issue_id, :description, :user_id, CURRENT_TIMESTAMP)";
        $createParams = [
            ':issue_id' => $issueId,
            ':description' => $data['description'],
            ':user_id' => $GLOBALS['user_id'] ?? null
        ];
        $this->supportSiteRequestsDb->query($createVersionQuery, $createParams);

        $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

        // TODO UPDATE SCHEMA FOR ISSUEVERSIONS TO INCLUDE INFO FOR WHEN THE CATEGORY_ID CHANGED. 
        // ADD VALIDATION TO PREVENT AN MULTIPLE ISSUES TO BE ASSIGNED TO ONE CATEGORY.
        // PREVENT ISSUE FROM BEING ADDED TO CATEGORY THAT HAS NESTED CHILDREN.
        // Update the current version id in the Issues table
        $updateCurrentVersionQuery = "UPDATE Issues SET current_version_id = :new_version_id, category_id = :category_id WHERE id = :issue_id";
        $updateParams = [
            ':new_version_id' => $newVersionId,
            ':category_id' => $data['category_id'],
            ':issue_id' => $issueId,
        ];
        $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, $updateParams);

        // Log the action
        $this->logAction($issueId, 'Issue', $newVersionId, 'update_issue');

        // Commit transaction
        $this->supportSiteRequestsDb->getConnection()->commit();

    } catch (Exception $e) {
        // Rollback transaction in case of error
        $this->supportSiteRequestsDb->getConnection()->rollBack();
        throw $e;
    }
}


public function fetchAllRequestFormData() {
    // Fetch all RequestFormData including name and default_priority from CategoryVersions
    $categoriesQuery = "SELECT 
                            c.id AS category_id, 
                            cv.name AS category_name, 
                            c.parent_id,
                            cv.default_priority
                        FROM Categories c
                        LEFT JOIN CategoryVersions cv ON c.current_version_id = cv.category_version_id
                        WHERE cv.deleted_at IS NULL";

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
            $children = $this->buildHierarchicalTree($categories, $category['category_id']);

            $category['subcategories'] = $children ?: [];
            unset($category['parent_id']);  // Remove parent_id from the parent category

            // Fetch inputs and issue details for each category
            $formData = $this->fetchInputsAndIssueForCategory($category['category_id']);
            $category['inputs'] = $formData['inputs'];
            $category['issue'] = $formData['issue'];

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


private function fetchIssueForCategory($categoryId) {
    $issuesQuery = "SELECT iv.issue_version_id, iv.description as issue_description, iv.user_id, iv.created_at
    FROM Issues i
    INNER JOIN IssueVersions iv ON i.current_version_id = iv.issue_version_id
    WHERE i.category_id = :category_id AND iv.deleted_at IS NULL";
    $issues = $this->supportSiteRequestsDb->query($issuesQuery, [':category_id' => $categoryId]);

    return $issues !== false && count($issues) > 0 ? $issues[0] : null;
}

private function fetchInputsForCategory($categoryId) {
    $inputsQuery = "SELECT i.id as input_id, iv.type as input_type, iv.input_version_id as input_version_id, iv.label as input_label
                    FROM Inputs i
                    INNER JOIN InputVersions iv ON i.current_version_id = iv.input_version_id
                    WHERE i.category_id = :category_id AND iv.deleted_at IS NULL";
    $inputs = $this->supportSiteRequestsDb->query($inputsQuery, [':category_id' => $categoryId]);

    // Fetch options for each input if type is dropdown or radio
    foreach ($inputs as &$input) {
        if (in_array($input['input_type'], ['dropdown', 'radio'])) {
            $optionsQuery = "SELECT iov.option_value
                             FROM InputOptions io
                             INNER JOIN InputOptionVersions iov ON io.current_version_id = iov.input_option_version_id
                             WHERE io.input_id = :input_id AND iov.deleted_at IS NULL";
            $options = $this->supportSiteRequestsDb->query($optionsQuery, [':input_id' => $input['input_id']]);
            $input['options'] = array_column($options, 'option_value');
        } else {
            $input['options'] = [];
        }
    }

    return $inputs !== false ? $inputs : [];
}




function fetchInputsAndIssueForCategory($categoryId)
{
    // Define parameters
    $params = [':categoryId' => $categoryId];

    // Fetch inputs and their latest versions for the specified category
    $inputsQuery = "SELECT i.id AS input_id, iv.type AS input_type, iv.input_version_id AS input_version_id, iv.label AS input_label
                    FROM Inputs i
                    JOIN InputVersions iv ON i.current_version_id = iv.input_version_id
                    WHERE i.category_id = :categoryId AND iv.deleted_at IS NULL";

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

        // Fetch the latest options for each input
        $optionsQuery = "SELECT iov.option_value
                         FROM InputOptionVersions iov
                         JOIN InputOptions io ON io.id = iov.input_option_id
                         WHERE io.input_id = :input_id AND iov.input_version_id = io.current_version_id AND iov.deleted_at IS NULL";
        $optionsParams = [
            ':input_id' => $input['input_id']
        ];
        $options = $this->supportSiteRequestsDb->query($optionsQuery, $optionsParams);

        if ($options) {
            foreach ($options as $option) {
                $groupedInputs[$input['input_id']]['options'][] = $option['option_value'];
            }
        }
    }

    // Convert grouped inputs to a list
    $inputsList = array_values($groupedInputs);

    // Check if the category has child categories
    $childCategoriesQuery = "SELECT id FROM Categories WHERE parent_id = :categoryId";
    $childCategoriesParams = [':categoryId' => $categoryId];
    $childCategories = $this->supportSiteRequestsDb->query($childCategoriesQuery, $childCategoriesParams);

    $issue = null;
    if ($childCategories === false || empty($childCategories)) {
        // Fetch the latest version of the issue for the specified category if no child categories exist
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
            $issue = $issue[0] ?? null; // Assuming the issue query returns a single row
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }

    // Combine all the data into an associative array
    $formData = [
        'inputs' => $inputsList,
        'issue' => $issue
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
    // public function fetchAllInputs()
    // {
    //     $inputsQuery = "SELECT * FROM Inputs ORDER BY category_id, id";

    //     try {
    //         $inputs = $this->supportSiteRequestsDb->query($inputsQuery);

    //         if ($inputs === false) {
    //             throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
    //         }
    //     } catch (Exception $e) {
    //         throw new Exception($e);
    //     }

    //     return $inputs;
    // }

    public function fetchAllInputs()
{
    $query = "SELECT i.id as input_id, iv.input_version_id, i.category_id, iv.label, iv.type, iv.created_at
              FROM Inputs i
              INNER JOIN InputVersions iv ON i.current_version_id = iv.input_version_id
              WHERE iv.deleted_at IS NULL
              ORDER BY i.category_id, i.id";

    try {
        $result = $this->supportSiteRequestsDb->query($query);
        if ($result === false) {
            throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }
    } catch (Exception $e) {
        throw new Exception($e);
    }
    
    return $result;
}


// Fetch all issues with their latest version, excluding deleted issues
public function fetchAllIssues()
{
    $issuesQuery = "SELECT i.id as issue_id, iv.issue_version_id, i.category_id, iv.description, iv.user_id, iv.created_at
                    FROM Issues i
                    INNER JOIN IssueVersions iv ON i.current_version_id = iv.issue_version_id
                    WHERE iv.deleted_at IS NULL
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
    // Fetch basic request details
    $query = "SELECT sr.id AS request_id, sr.category_id, sr.issue_version_id, srv.status, srv.priority, srv.email, srv.created_at, srv.deleted_at 
              FROM SupportRequests sr
              INNER JOIN SupportRequestVersions srv ON sr.current_version_id = srv.support_request_version_id
              WHERE sr.id = :request_id AND srv.deleted_at IS NULL";
    $params = [':request_id' => $supportRequestId];

    try {
        $request = $this->supportSiteRequestsDb->query($query, $params);
        if ($request === false) {
            throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }
    } catch (Exception $e) {
        throw new Exception($e);
    }

            // Fetch inputs and their values for the support request
            $inputs = $this->fetchRequestInputValues($supportRequestId);

    // Fetch comments
    $commentsQuery = "SELECT id, user_id, comment, created_at FROM SupportRequestComments WHERE support_request_id = :request_id AND deleted_at IS NULL";
    $comments = $this->supportSiteRequestsDb->query($commentsQuery, $params);

    return [
        'request' => $request[0],
        'inputs' => $inputs,
        'comments' => $comments
    ];
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
        $query = "SELECT sr.id AS request_id, sr.category_id, sr.issue_version_id, srv.status, srv.priority, srv.email, srv.created_at 
        FROM SupportRequests sr
        INNER JOIN SupportRequestVersions srv ON sr.current_version_id = srv.support_request_version_id
        WHERE srv.deleted_at IS NULL";

try {
  $requests = $this->supportSiteRequestsDb->query($query);
  if ($requests === false) {
      throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
  }
} catch (Exception $e) {
  throw new Exception($e);
}

return $requests;
    }

    


    // Fetch all categories' hierarchy
    public function fetchCategoriesHierarchy()
    {
        $categoriesQuery = "SELECT 
                                c.id AS category_id, 
                                cv.name AS category_name, 
                                c.parent_id,
                                cv.default_priority
                            FROM Categories c
                            LEFT JOIN CategoryVersions cv ON c.current_version_id = cv.category_version_id";
    
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

    // Fetch all versions of a specific issue
    public function fetchIssueVersions($issueId)
    {
        $query = "SELECT issue_version_id, description, created_at 
                  FROM IssueVersions 
                  WHERE issue_id = :issue_id 
                  ORDER BY issue_version_id DESC";
    
        $params = [':issue_id' => $issueId];
        
        try {
            $result = $this->supportSiteRequestsDb->query($query, $params);
            if ($result === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }
        
        return $result;
    }

    


    // Fetch version history of a specific issue
    public function fetchIssueVersionHistory($issueId)
    {
        $historyQuery = "SELECT iv.issue_version_id, iv.description, iv.user_id, iv.created_at
                         FROM IssueVersions iv
                         WHERE iv.issue_id = :issueId
                         ORDER BY iv.issue_version_id ASC";
        $params = [':issueId' => $issueId];

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


    public function getFormData() {
        $categoriesQuery = "SELECT id, category_name FROM Categories WHERE deleted_at IS NULL";
        $categories = $this->supportSiteRequestsDb->query($categoriesQuery);
        
        if ($categories === false) {
            throw new Exception("Failed to fetch categories: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }
        
        return ['categories' => $categories];
    }

    public function getCategoryDetails($categoryId) {
        // Fetch inputs for the category
        $inputsQuery = "SELECT i.id as input_id, iv.type as input_type, iv.id as input_version_id, iv.label as input_label
                        FROM Inputs i
                        INNER JOIN InputVersions iv ON i.current_version_id = iv.id
                        WHERE i.category_id = :category_id AND iv.deleted_at IS NULL";
        $inputs = $this->supportSiteRequestsDb->query($inputsQuery, [':category_id' => $categoryId]);

        if ($inputs === false) {
            throw new Exception("Failed to fetch inputs: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }

        // Fetch issues for the category
        $issuesQuery = "SELECT iv.id as issue_version_id, iv.description as issue_description
                        FROM Issues i
                        INNER JOIN IssueVersions iv ON i.current_version_id = iv.id
                        WHERE i.category_id = :category_id AND iv.deleted_at IS NULL";
        $issues = $this->supportSiteRequestsDb->query($issuesQuery, [':category_id' => $categoryId]);

        if ($issues === false) {
            throw new Exception("Failed to fetch issues: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }

        return [
            'inputs' => $inputs,
            'issues' => $issues
        ];
    }



        // Fetch issue categories
        public function fetchIssueCategories()
        {
                $query = "SELECT c.id AS category_id, c.current_version_id AS category_version_id, cv.name AS category_name, 
                           sc.id AS sub_category_id, sc.current_version_id AS sub_category_version_id, scv.name AS sub_category_name,
                           si.id AS sub_issue_id, si.current_version_id AS sub_issue_version_id, siv.description AS sub_issue_description,
                           iov.id AS input_option_version_id, iov.option_value, iov.input_version_id, iov.user_id, iov.created_at
                    FROM Categories c
                    JOIN CategoryVersions cv ON c.current_version_id = cv.category_version_id
                    LEFT JOIN SubCategories sc ON sc.category_id = c.id
                    LEFT JOIN SubCategoryVersions scv ON sc.current_version_id = scv.sub_category_version_id
                    LEFT JOIN SubIssues si ON si.sub_category_id = sc.id
                    LEFT JOIN SubIssueVersions siv ON si.current_version_id = siv.sub_issue_version_id
                    LEFT JOIN InputOptionVersions iov ON iov.input_version_id = siv.id
                    WHERE c.deleted_at IS NULL AND cv.deleted_at IS NULL AND (sc.deleted_at IS NULL OR sc.deleted_at IS NOT NULL)
                    AND (scv.deleted_at IS NULL OR scv.deleted_at IS NOT NULL) AND (si.deleted_at IS NULL OR si.deleted_at IS NOT NULL)
                    AND (siv.deleted_at IS NULL OR siv.deleted_at IS NOT NULL) AND (iov.deleted_at IS NULL OR iov.deleted_at IS NOT NULL)
                    ORDER BY c.id, sc.id, si.id, iov.id
                ";
            
                $result = $this->supportSiteRequestsDb->query($query);
            
                $categories = [];
                foreach ($result as $row) {
                    if (!isset($categories[$row['category_id']])) {
                        $categories[$row['category_id']] = [
                            'id' => $row['category_id'],
                            'label' => $row['category_name'],
                            'versionId' => $row['category_version_id'],
                            'subCategories' => [],
                        ];
                    }
            
                    if (!isset($categories[$row['category_id']]['subCategories'][$row['sub_category_id']])) {
                        $categories[$row['category_id']]['subCategories'][$row['sub_category_id']] = [
                            'id' => $row['sub_category_id'],
                            'label' => $row['sub_category_name'],
                            'versionId' => $row['sub_category_version_id'],
                            'subIssues' => [],
                        ];
                    }
            
                    if (!isset($categories[$row['category_id']]['subCategories'][$row['sub_category_id']]['subIssues'][$row['sub_issue_id']])) {
                        $categories[$row['category_id']]['subCategories'][$row['sub_category_id']]['subIssues'][$row['sub_issue_id']] = [
                            'id' => $row['sub_issue_id'],
                            'label' => $row['sub_issue_description'],
                            'versionId' => $row['sub_issue_version_id'],
                            'inputs' => [],
                        ];
                    }
            
                    $categories[$row['category_id']]['subCategories'][$row['sub_category_id']]['subIssues'][$row['sub_issue_id']]['inputs'][] = [
                        'id' => $row['input_option_version_id'],
                        'label' => $row['option_value'],
                        'type' => 'text', // Adjust the type based on actual data structure
                        'placeholder' => '', // Add placeholder if needed
                        'tooltip' => '', // Add tooltip if needed
                        'versionId' => $row['input_option_version_id'],
                    ];
                }
            
                return array_values($categories);
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
    try {
        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        // Validate parent category
        if (!empty($data['parent_id'])) {
            $parentCategory = $this->fetchCategoryById($data['parent_id']);
            if (!$parentCategory) {
                throw new Exception("Parent category does not exist.");
            }
            if ($this->hasIssuesOrInputs($data['parent_id'])) {
                throw new Exception("Parent category cannot have issues or inputs attached.");
            }
        }

        // Validate category name uniqueness
        if ($this->isCategoryNameExists($data['name'])) {
            throw new Exception("Category name already exists.");
        }

        // Insert category
        $query = "INSERT INTO Categories (parent_id) VALUES (:parent_id)";
        $params = [
            ':parent_id' => $data['parent_id']
        ];
        $this->supportSiteRequestsDb->query($query, $params);
        $categoryId = $this->supportSiteRequestsDb->lastInsertId();

        // Create initial version
        $versionId = $this->createCategoryVersion($categoryId, $data);

        // Update category with the new version ID
        $updateVersionQuery = "UPDATE Categories SET current_version_id = :version_id WHERE id = :category_id";
        $updateVersionParams = [
            ':version_id' => $versionId,
            ':category_id' => $categoryId
        ];
        $this->supportSiteRequestsDb->query($updateVersionQuery, $updateVersionParams);

        // Log action
        $this->logAction($categoryId, 'Category', $versionId, 'create_category');

        // Commit transaction
        $this->supportSiteRequestsDb->getConnection()->commit();

        return $categoryId;
    } catch (Exception $e) {
        // Rollback transaction in case of error
        $this->supportSiteRequestsDb->getConnection()->rollBack();
        throw $e;
    }
}
    
    private function fetchCategoryById($categoryId)
    {
        $query = "SELECT * FROM Categories WHERE id = :id";
        $params = [':id' => $categoryId];
        $result = $this->supportSiteRequestsDb->query($query, $params);
        return !empty($result) ? $result[0] : null;
    }
    

    // TODO USE LATEST CATEGORY VERSION
    private function isCategoryNameExists($name)
    {
        $query = "SELECT COUNT(*) AS name_count FROM CategoryVersions WHERE name = :name";
        $params = [':name' => $name];
        $result = $this->supportSiteRequestsDb->query($query, $params);
        return $result[0]['name_count'] > 0;
    }
    
    

    public function updateCategory($categoryId, $data)
    {
        try {
            // Start transaction
            $this->supportSiteRequestsDb->getConnection()->beginTransaction();
    
            // Fetch the existing category
            $existingCategory = $this->fetchCategoryById($categoryId);
            if (!$existingCategory) {
                throw new Exception("Category does not exist.");
            }
    
            // Ensure at least one field is provided for update
            if (empty($data['name']) && empty($data['parent_id']) && empty($data['default_priority'])) {
                throw new Exception("No valid fields provided for update.");
            }
    
            // Prepare update parts and parameters
            $updateParts = [];
            $params = [':id' => $categoryId];
    
            // Update parent_id if provided
            if (isset($data['parent_id'])) {
                // Validate parent category
                if (!empty($data['parent_id'])) {
                    $parentCategory = $this->fetchCategoryById($data['parent_id']);
                    if (!$parentCategory) {
                        throw new Exception("Parent category does not exist.");
                    }
                    if ($this->hasIssuesOrInputs($data['parent_id'])) {
                        throw new Exception("Parent category cannot have issues or inputs attached.");
                    }
                }
                // Check if there are nested categories under the current category
                if ($this->hasChildCategories($categoryId) && empty($data['parent_id'])) {
                    throw new Exception("Cannot remove parent_id for a category with nested subcategories.");
                }
                $updateParts[] = "parent_id = :parent_id";
                $params[':parent_id'] = $data['parent_id'];
            }
    
            // If there are any parts to update
            if (!empty($updateParts)) {
                $query = "UPDATE Categories SET " . implode(', ', $updateParts) . " WHERE id = :id";
                $this->supportSiteRequestsDb->query($query, $params);
            }
    
            // Create new version
            $versionId = $this->createCategoryVersion($categoryId, $data);
    
            // Update category with the new version ID
            $updateVersionQuery = "UPDATE Categories SET current_version_id = :version_id WHERE id = :category_id";
            $updateVersionParams = [
                ':version_id' => $versionId,
                ':category_id' => $categoryId
            ];
            $this->supportSiteRequestsDb->query($updateVersionQuery, $updateVersionParams);
    
            // Log action
            $this->logAction($categoryId, 'Category', $versionId, 'update_category');
    
            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
    
            return true;
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }
    
    
    private function hasIssuesOrInputs($categoryId)
    {
        // Check for issues attached to the category
        $issueQuery = "SELECT COUNT(*) AS issue_count FROM Issues WHERE category_id = :category_id";
        $issueParams = [':category_id' => $categoryId];
        $issueResult = $this->supportSiteRequestsDb->query($issueQuery, $issueParams);
    
        // Check for inputs attached to the category
        $inputQuery = "SELECT COUNT(*) AS input_count FROM Inputs WHERE category_id = :category_id";
        $inputParams = [':category_id' => $categoryId];
        $inputResult = $this->supportSiteRequestsDb->query($inputQuery, $inputParams);
    
        return ($issueResult[0]['issue_count'] > 0 || $inputResult[0]['input_count'] > 0);
    }
    
    private function hasChildCategories($categoryId)
    {
        $query = "SELECT COUNT(*) AS child_count FROM Categories WHERE parent_id = :category_id";
        $params = [':category_id' => $categoryId];
        $result = $this->supportSiteRequestsDb->query($query, $params);
        return $result[0]['child_count'] > 0;
    }
    
    
    
    private function createCategoryVersion($categoryId, $data)
    {
        $query = "INSERT INTO CategoryVersions (category_id, name, parent_id, default_priority, user_id) VALUES (:category_id, :name, :parent_id, :default_priority, :user_id)";
        $params = [
            ':category_id' => $categoryId,
            ':name' => $data['name'] ?? null,
            ':parent_id' => $data['parent_id'] ?? null,
            ':default_priority' => $data['default_priority'] ?? null,
            ':user_id' => $GLOBALS['user_id'] ?? null 
        ];
        $this->supportSiteRequestsDb->query($query, $params);
    
        $versionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
        // Log action
        $this->logAction($categoryId, 'Category', $versionId, 'create_category_version');
    
        return $versionId;
    }

    public function deleteCategory($categoryId)
    {
        try {
            // Start transaction
            $this->supportSiteRequestsDb->getConnection()->beginTransaction();
            
            // Fetch the category to check if it exists and get the parent ID
            $category = $this->fetchCategoryById($categoryId);
            if (!$category) {
                throw new Exception("Category does not exist.");
            }
    
            // Check if the category has child categories
            if ($this->hasChildCategories($categoryId)) {
                throw new Exception("Category has child categories and cannot be deleted.");
            }

            // In future make it so it cant delete if category is available
    
            // Mark category versions as deleted
            $markCategoryVersionsDeletedQuery = "UPDATE CategoryVersions SET deleted_at = NOW() WHERE category_id = :category_id";
            $markCategoryVersionsDeletedParams = [':category_id' => $categoryId];
            $this->supportSiteRequestsDb->query($markCategoryVersionsDeletedQuery, $markCategoryVersionsDeletedParams);
    
            // Mark the category itself as deleted
            $markCategoryDeletedQuery = "UPDATE Categories SET deleted_at = NOW() WHERE id = :id";
            $markCategoryDeletedParams = [':id' => $categoryId];
            $this->supportSiteRequestsDb->query($markCategoryDeletedQuery, $markCategoryDeletedParams);
    
            // Log action
            $this->logAction($categoryId, 'Category', null, 'delete_category');
    
            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
    
            return true;
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }


    // Create a new issue
    public function createIssue($data)
    {
        try {
            // Start transaction
            $this->supportSiteRequestsDb->getConnection()->beginTransaction();

            // Insert new issue
            $issueQuery = "INSERT INTO Issues (category_id) VALUES (:category_id)";
            $params = [':category_id' => $data['category_id']];
            $this->supportSiteRequestsDb->query($issueQuery, $params);
            $issueId = $this->supportSiteRequestsDb->lastInsertId();

            // Create initial issue version
            $versionQuery = "INSERT INTO IssueVersions (issue_id, description, user_id) VALUES (:issue_id, :description, :user_id)";
            $versionParams = [
                ':issue_id' => $issueId,
                ':description' => $data['description'],
                ':user_id' => $GLOBALS['user_id'] ?? null 
            ];
            $this->supportSiteRequestsDb->query($versionQuery, $versionParams);
            $versionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

            // Update issue with current version id
            $updateIssueQuery = "UPDATE Issues SET current_version_id = :version_id WHERE id = :issue_id";
            $updateIssueParams = [':version_id' => $versionId, ':issue_id' => $issueId];
            $this->supportSiteRequestsDb->query($updateIssueQuery, $updateIssueParams);

            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();

            // TODO: add action logs

            return $issueId;
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }


// Delete an issue and create a new version with deleted_at updated
public function deleteIssue($issueId)
{
    try {
        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        // Fetch the current version id of the issue
        $fetchCurrentVersionQuery = "SELECT current_version_id FROM Issues WHERE id = :issue_id";
        $params = [':issue_id' => $issueId];
        $result = $this->supportSiteRequestsDb->query($fetchCurrentVersionQuery, $params);

        if (!$result || empty($result[0]['current_version_id'])) {
            throw new Exception("Issue or its current version not found.");
        }

        $currentVersionId = $result[0]['current_version_id'];

        // Fetch the details of the current issue version
        $fetchIssueVersionQuery = "SELECT * FROM IssueVersions WHERE issue_version_id = :issue_version_id";
        $issueVersionParams = [':issue_version_id' => $currentVersionId];
        $issueVersionResult = $this->supportSiteRequestsDb->query($fetchIssueVersionQuery, $issueVersionParams);

        if (!$issueVersionResult || empty($issueVersionResult[0])) {
            throw new Exception("Issue version details not found.");
        }

        $issueVersionDetails = $issueVersionResult[0];

        // Create a new version with deleted_at updated
        $createNewVersionQuery = "INSERT INTO IssueVersions (issue_id, description, user_id, created_at, deleted_at)
                                  VALUES (:issue_id, :description, :user_id, :created_at, CURRENT_TIMESTAMP)";
        $newVersionParams = [
            ':issue_id' => $issueVersionDetails['issue_id'],
            ':description' => $issueVersionDetails['description'],
            ':user_id' => $issueVersionDetails['user_id'],
            ':created_at' => $issueVersionDetails['created_at']
        ];
        $this->supportSiteRequestsDb->query($createNewVersionQuery, $newVersionParams);

        // Fetch the new version id
        $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

        // Update the current version id in the Issues table
        $updateCurrentVersionQuery = "UPDATE Issues SET current_version_id = :new_version_id WHERE id = :issue_id";
        $updateParams = [
            ':new_version_id' => $newVersionId,
            ':issue_id' => $issueId,
        ];
        $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, $updateParams);

        // Log the action
        $this->logAction($issueId, 'Issue', $newVersionId, 'delete_issue');

        // Commit transaction
        $this->supportSiteRequestsDb->getConnection()->commit();

    } catch (Exception $e) {
        // Rollback transaction in case of error
        $this->supportSiteRequestsDb->getConnection()->rollBack();
        throw $e;
    }
}


    // Input methods
    public function createInput($data)
    {
        try {
            // Start transaction
            $this->supportSiteRequestsDb->getConnection()->beginTransaction();
    
            // Insert into Inputs table
            $insertInputQuery = "INSERT INTO Inputs (category_id) VALUES (:category_id)";
            $params = [':category_id' => $data['category_id']];
            $this->supportSiteRequestsDb->query($insertInputQuery, $params);
    
            // Get the newly created input ID
            $inputId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
            // Insert into InputVersions table
            $insertVersionQuery = "INSERT INTO InputVersions (input_id, label, type, user_id) VALUES (:input_id, :label, :type, :user_id)";
            $params = [
                ':input_id' => $inputId,
                ':label' => $data['label'],
                ':type' => $data['type'],
                ':user_id' => $GLOBALS['user_id'] ?? null
            ];
            $this->supportSiteRequestsDb->query($insertVersionQuery, $params);
    
            // Get the newly created version ID
            $versionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
            // Update the current_version_id in the Inputs table
            $updateInputQuery = "UPDATE Inputs SET current_version_id = :version_id WHERE id = :input_id";
            $params = [
                ':version_id' => $versionId,
                ':input_id' => $inputId,
            ];
            $this->supportSiteRequestsDb->query($updateInputQuery, $params);
    
            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
    
            return $inputId;
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }

    public function createInputWithOptions($data)
    {
        try {
            // Start transaction
            $this->supportSiteRequestsDb->getConnection()->beginTransaction();
    
            // Insert into Inputs table
            $insertInputQuery = "INSERT INTO Inputs (category_id) VALUES (:category_id)";
            $params = [':category_id' => $data['category_id']];
            $this->supportSiteRequestsDb->query($insertInputQuery, $params);
    
            // Get the newly created input ID
            $inputId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
            // Insert into InputVersions table
            $insertVersionQuery = "INSERT INTO InputVersions (input_id, label, type, user_id) VALUES (:input_id, :label, :type, :user_id)";
            $params = [
                ':input_id' => $inputId,
                ':label' => $data['label'],
                ':type' => $data['type'],
                ':user_id' => $GLOBALS['user_id'] ?? null
            ];
            $this->supportSiteRequestsDb->query($insertVersionQuery, $params);
    
            // Get the newly created version ID
            $versionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
            // Update the current_version_id in the Inputs table
            $updateInputQuery = "UPDATE Inputs SET current_version_id = :version_id WHERE id = :input_id";
            $params = [
                ':version_id' => $versionId,
                ':input_id' => $inputId,
            ];
            $this->supportSiteRequestsDb->query($updateInputQuery, $params);
    
            // If type is dropdown or radio, insert options
            if ($data['type'] === 'dropdown' || $data['type'] === 'radio') {
                // Insert into InputOptions table
                $insertInputOptionsQuery = "INSERT INTO InputOptions (input_id) VALUES (:input_id)";
                $params = [':input_id' => $inputId];
                $this->supportSiteRequestsDb->query($insertInputOptionsQuery, $params);
    
                // Get the newly created input option ID
                $inputOptionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
                // Insert the input option versions
                foreach ($data['options'] as $option) {
                    // Insert into InputOptionVersions table
                    $insertOptionQuery = "INSERT INTO InputOptionVersions (input_option_id, input_version_id, option_value, user_id) VALUES (:input_option_id, :input_version_id, :option_value, :user_id)";
                    $params = [
                        ':input_option_id' => $inputOptionId,
                        ':input_version_id' => $versionId,
                        ':option_value' => $option,
                        ':user_id' => $GLOBALS['user_id'] ?? null
                    ];
                    $this->supportSiteRequestsDb->query($insertOptionQuery, $params);
                }
    
                // Update the current_version_id in the InputOptions table
                $updateInputOptionsQuery = "UPDATE InputOptions SET current_version_id = :version_id WHERE id = :input_option_id";
                $params = [
                    ':version_id' => $versionId,
                    ':input_option_id' => $inputOptionId,
                ];
                $this->supportSiteRequestsDb->query($updateInputOptionsQuery, $params);
            }
    
            // Commit transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
    
            return $inputId;
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollBack();
            throw $e;
        }
    }
    


    private function createInputOptions($data)
{
    try {
        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        foreach ($data['options'] as $option) {
            // Insert into InputOptionVersions table
            $insertOptionQuery = "INSERT INTO InputOptionVersions (input_option_id, input_version_id, option_value, user_id) VALUES (:input_option_id, :input_version_id, :option_value, :user_id)";
            $params = [
                ':input_option_id' => $data['input_id'],
                ':input_version_id' => $data['input_version_id'],
                ':option_value' => $option,
                ':user_id' => $data['user_id'] ?? null,
            ];
            $this->supportSiteRequestsDb->query($insertOptionQuery, $params);
        }

        // Commit transaction
        $this->supportSiteRequestsDb->getConnection()->commit();

    } catch (Exception $e) {
        // Rollback transaction in case of error
        $this->supportSiteRequestsDb->getConnection()->rollBack();
        throw $e;
    }
}

public function updateInput($inputId, $data)
{
    try {
        // Start transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();

        // Fetch the latest snapshot from InputVersions
        $latestVersionQuery = "SELECT * FROM InputVersions WHERE input_version_id = (SELECT current_version_id FROM Inputs WHERE id = :id) AND deleted_at IS NULL";
        $latestVersionParams = [':id' => $inputId];
        $latestVersionResult = $this->supportSiteRequestsDb->query($latestVersionQuery, $latestVersionParams);

        if (empty($latestVersionResult)) {
            throw new Exception("Latest input version not found or already deleted.");
        }

        $latestVersion = $latestVersionResult[0];

        // Create a new input version entry using provided data or latest version values
        $insertQuery = "INSERT INTO InputVersions (input_id, label, type, user_id, created_at) VALUES (:input_id, :label, :type, :user_id, NOW())";
        $insertParams = [
            ':input_id' => $inputId,
            ':label' => $data['label'] ?? $latestVersion['label'],
            ':type' => $data['type'] ?? $latestVersion['type'],
            ':user_id' => $GLOBALS['user_id'] ?? null,
        ];
        $result = $this->supportSiteRequestsDb->query($insertQuery, $insertParams);

        if ($result === false) {
            throw new Exception("Failed to insert new input version.");
        }

        // Get the ID of the newly inserted version
        $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();

        // Update the current_version_id in the Inputs table
        $updateCurrentVersionQuery = "UPDATE Inputs SET current_version_id = :version_id WHERE id = :id";
        $updateCurrentVersionParams = [':version_id' => $newVersionId, ':id' => $inputId];
        $result = $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, $updateCurrentVersionParams);

        if ($result === false) {
            throw new Exception("Failed to update current_version_id in Inputs table.");
        }

        // If the input type is dropdown or radio, handle the associated options
        if (($data['type'] ?? $latestVersion['type']) === 'dropdown' || ($data['type'] ?? $latestVersion['type']) === 'radio') {
            // Check if InputOptions exists
            $inputOptionsQuery = "SELECT id FROM InputOptions WHERE input_id = :input_id";
            $inputOptionsParams = [':input_id' => $inputId];
            $inputOptionsResult = $this->supportSiteRequestsDb->query($inputOptionsQuery, $inputOptionsParams);

            if (empty($inputOptionsResult)) {
                // InputOptions does not exist, create it
                $insertInputOptionQuery = "INSERT INTO InputOptions (input_id) VALUES (:input_id)";
                $insertInputOptionParams = [':input_id' => $inputId];
                $this->supportSiteRequestsDb->query($insertInputOptionQuery, $insertInputOptionParams);

                // Get the ID of the newly inserted option
                $optionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
            } else {
                // InputOptions exists, use the existing ID
                $optionId = $inputOptionsResult[0]['id'];
            }

            // Insert new options into InputOptionVersions
            foreach ($data['options'] as $optionValue) {
                $insertOptionQuery = "INSERT INTO InputOptionVersions (input_option_id, input_version_id, option_value, user_id, created_at) 
                                      VALUES (:input_option_id, :input_version_id, :option_value, :user_id, NOW())";
                $insertOptionParams = [
                    ':input_option_id' => $optionId,
                    ':input_version_id' => $newVersionId,
                    ':option_value' => $optionValue,
                    ':user_id' => $GLOBALS['user_id'] ?? null,
                ];
                $result = $this->supportSiteRequestsDb->query($insertOptionQuery, $insertOptionParams);
                if ($result === false) {
                    throw new Exception("Failed to insert new input option version.");
                }
            }

            // Update the current_version_id in the InputOptions table
            $updateInputOptionQuery = "UPDATE InputOptions SET current_version_id = :version_id WHERE id = :option_id";
            $updateInputOptionParams = [
                ':version_id' => $newVersionId,
                ':option_id' => $optionId,
            ];
            $result = $this->supportSiteRequestsDb->query($updateInputOptionQuery, $updateInputOptionParams);
            if ($result === false) {
                throw new Exception("Failed to update current_version_id in InputOptions table.");
            }
        } else if (($latestVersion['type'] === 'dropdown' || $latestVersion['type'] === 'radio') && ($data['type'] !== 'dropdown' && $data['type'] !== 'radio')) {
            // Handle case where type changes from dropdown or radio to something else
            $latestOptionsQuery = "SELECT * FROM InputOptionVersions WHERE input_option_id = (SELECT id FROM InputOptions WHERE input_id = :input_id) AND deleted_at IS NULL";
            $latestOptionsParams = [':input_id' => $inputId];
            $latestOptionsResult = $this->supportSiteRequestsDb->query($latestOptionsQuery, $latestOptionsParams);

            foreach ($latestOptionsResult as $option) {
                // Create a new option version entry marking it as deleted
                $insertOptionQuery = "INSERT INTO InputOptionVersions (input_option_id, input_version_id, option_value, user_id, created_at, deleted_at) 
                                      VALUES (:input_option_id, :input_version_id, NULL, :user_id, NOW(), NOW())";
                $insertOptionParams = [
                    ':input_option_id' => $option['input_option_id'],
                    ':input_version_id' => $newVersionId,
                    ':user_id' => $GLOBALS['user_id'] ?? null,
                ];
                $result = $this->supportSiteRequestsDb->query($insertOptionQuery, $insertOptionParams);
                if ($result === false) {
                    throw new Exception("Failed to insert new input option version marked as deleted.");
                }
            }

            // Update the current_version_id in the InputOptions table to reflect the new input version
            $updateInputOptionQuery = "UPDATE InputOptions SET current_version_id = :version_id WHERE input_id = :input_id";
            $updateInputOptionParams = [
                ':version_id' => $newVersionId,
                ':input_id' => $inputId,
            ];
            $result = $this->supportSiteRequestsDb->query($updateInputOptionQuery, $updateInputOptionParams);
            if ($result === false) {
                throw new Exception("Failed to update current_version_id in InputOptions table.");
            }
        }

        // Log the action
        $this->logAction($inputId, 'Input', $newVersionId, 'update_input');

        // Commit transaction
        $this->supportSiteRequestsDb->getConnection()->commit();

        return true;
    } catch (Exception $e) {
        // Rollback transaction in case of error
        $this->supportSiteRequestsDb->getConnection()->rollBack();
        throw $e;
    }
}



    // public function updateInput($inputId, $data)
    // {
    //     // Step 1: Fetch the current version of the input
    //     $currentVersionQuery = "SELECT current_version_id FROM Inputs WHERE id = :id";
    //     $currentVersionParams = [':id' => $inputId];
    //     $currentVersionResult = $this->supportSiteRequestsDb->query($currentVersionQuery, $currentVersionParams);
        
    //     if (empty($currentVersionResult)) {
    //         throw new Exception("Input not found.");
    //     }
    
    //     $currentVersionId = $currentVersionResult[0]['current_version_id'];
    
    //     // Step 2: Create a new input version
    //     $inputVersionId = $this->createInputVersion($inputId, $data, $currentVersionId);
    
    //     // Step 3: Update input options if necessary
    //     if (isset($data['options']) && is_array($data['options'])) {
    //         $this->updateInputOptions($inputId, $inputVersionId, $data['options']);
    //     }
    
    //     // Step 4: Update the input with the new current_version_id
    //     $updateQuery = "UPDATE Inputs SET current_version_id = :current_version_id, updated_at = NOW() WHERE id = :id";
    //     $params = [
    //         ':current_version_id' => $inputVersionId,
    //         ':id' => $inputId
    //     ];
    //     $this->supportSiteRequestsDb->query($updateQuery, $params);
    
    //     return $inputId;
    // }
    
    
    private function createInputVersion($inputId, $data, $previousVersionId)
    {
        // Fetch the previous version data
        $previousVersionQuery = "SELECT * FROM InputVersions WHERE input_version_id = :version_id";
        $previousVersionParams = [':version_id' => $previousVersionId];
        $previousVersionResult = $this->supportSiteRequestsDb->query($previousVersionQuery, $previousVersionParams);
    
        if (empty($previousVersionResult)) {
            throw new Exception("Previous input version not found.");
        }
    
        $previousVersion = $previousVersionResult[0];
    
        // Insert a new input version entry
        $query = "INSERT INTO InputVersions (input_id, label, type, user_id, created_at, updated_at, deleted_at) 
                  VALUES (:input_id, :label, :type, :user_id, NOW(), NOW(), NULL)";
        $params = [
            ':input_id' => $inputId,
            ':label' => $data['label'] ?? $previousVersion['label'],
            ':type' => $data['type'] ?? $previousVersion['type'],
            ':user_id' => $GLOBALS['user_id'] ?? $previousVersion['user_id']
        ];
        $this->supportSiteRequestsDb->query($query, $params);
        return $this->supportSiteRequestsDb->lastInsertId();
    }
    
    private function updateInputOptions($inputId, $inputVersionId, $options)
    {
        // Fetch existing options
        $existingOptionsQuery = "SELECT * FROM InputOptions WHERE input_id = :input_id AND deleted_at IS NULL";
        $existingOptionsParams = [':input_id' => $inputId];
        $existingOptions = $this->supportSiteRequestsDb->query($existingOptionsQuery, $existingOptionsParams);
    
        // Create a map of existing options for easy lookup
        $existingOptionsMap = [];
        foreach ($existingOptions as $option) {
            $existingOptionsMap[$option['option_value']] = $option;
        }
    
        // Process new options
        foreach ($options as $optionValue) {
            if (isset($existingOptionsMap[$optionValue])) {
                // Option exists, create a new version
                $this->createInputOptionVersion($existingOptionsMap[$optionValue]['id'], $inputId, $inputVersionId, $optionValue, 'update');
                // Remove from the map to mark it as processed
                unset($existingOptionsMap[$optionValue]);
            } else {
                // New option, insert and create a version
                $insertQuery = "INSERT INTO InputOptions (input_id, input_version_id, option_value, created_at, updated_at) 
                                VALUES (:input_id, :input_version_id, :option_value, NOW(), NOW())";
                $insertParams = [
                    ':input_id' => $inputId,
                    ':input_version_id' => $inputVersionId,
                    ':option_value' => $optionValue
                ];
                $this->supportSiteRequestsDb->query($insertQuery, $insertParams);
                $newOptionId = $this->supportSiteRequestsDb->lastInsertId();
                $this->createInputOptionVersion($newOptionId, $inputId, $inputVersionId, $optionValue, 'insert');
            }
        }
    
        // Mark remaining options as deleted and re-insert unchanged options with the new input version ID
        foreach ($existingOptionsMap as $optionValue => $option) {
            if (!in_array($optionValue, $options)) {
                $this->createInputOptionVersion($option['id'], $inputId, $inputVersionId, $option['option_value'], 'delete');
                $deleteQuery = "UPDATE InputOptions SET deleted_at = NOW() WHERE id = :id";
                $deleteParams = [':id' => $option['id']];
                $this->supportSiteRequestsDb->query($deleteQuery, $deleteParams);
            } else {
                $this->createInputOptionVersion($option['id'], $inputId, $inputVersionId, $option['option_value'], 're-insert');
            }
        }
    }
    
    private function createInputOptionVersion($inputOptionId, $inputId, $inputVersionId, $optionValue, $action)
    {
        $query = "INSERT INTO InputOptionVersions (input_option_id, input_id, input_version_id, option_value, user_id, created_at, updated_at, deleted_at) 
                  VALUES (:input_option_id, :input_id, :input_version_id, :option_value, :user_id, NOW(), NOW(), NULL)";
        $params = [
            ':input_option_id' => $inputOptionId,
            ':input_id' => $inputId,
            ':input_version_id' => $inputVersionId,
            ':option_value' => $optionValue,
            ':user_id' => $GLOBALS['user_id'] ?? null,
            ':deleted_at' => $action === 'delete' ? date('Y-m-d H:i:s') : null
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
        $inputExistsQuery = "SELECT id, current_version_id FROM Inputs WHERE id = :id";
        $inputExistsParams = [':id' => $inputId];
        $inputExistsResult = $this->supportSiteRequestsDb->query($inputExistsQuery, $inputExistsParams);
    
        // todo revisit to prevent deletion when current version is already marked as deleted. 
    
        if (empty($inputExistsResult)) {
            throw new Exception("Input not found or already deleted.");
        }
    
        $input = $inputExistsResult[0];
    
        // Start a transaction
        $this->supportSiteRequestsDb->getConnection()->beginTransaction();
        
        try {
            // Get the current timestamp
            $currentTimestamp = date('Y-m-d H:i:s');
    
            // Fetch the latest snapshot from InputVersions
            $latestVersionQuery = "SELECT * FROM InputVersions WHERE input_version_id = :version_id AND deleted_at IS NULL";
            $latestVersionParams = [':version_id' => $input['current_version_id']];
            $latestVersionResult = $this->supportSiteRequestsDb->query($latestVersionQuery, $latestVersionParams);
    
            if (empty($latestVersionResult)) {
                throw new Exception("Latest input version not found or already deleted.");
            }
    
            $latestVersion = $latestVersionResult[0];
    
            // Create a new input version entry marking it as deleted
            $insertQuery = "INSERT INTO InputVersions (input_id, label, type, user_id, created_at, deleted_at) VALUES (:input_id, :label, :type, :user_id, :created_at, :deleted_at)";
            $insertParams = [
                ':input_id' => $inputId,
                ':label' => $latestVersion['label'],
                ':type' => $latestVersion['type'],
                ':user_id' => $GLOBALS['user_id'] ?? null,
                ':created_at' => $latestVersion['created_at'],
                ':deleted_at' => $currentTimestamp
            ];
            $result = $this->supportSiteRequestsDb->query($insertQuery, $insertParams);
            if ($result === false) {
                throw new Exception("Failed to insert new input version.");
            }
            // Get the ID of the newly inserted version
            $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
            // Update the current_version_id in the Inputs table
            $updateCurrentVersionQuery = "UPDATE Inputs SET current_version_id = :version_id WHERE id = :id";
            $updateCurrentVersionParams = [':version_id' => $newVersionId, ':id' => $inputId];
            $result = $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, $updateCurrentVersionParams);
            if ($result === false) {
                throw new Exception("Failed to update current_version_id in Inputs table.");
            }
    
            // If the input type is dropdown or radio, handle the associated options
            if ($latestVersion['type'] === 'dropdown' || $latestVersion['type'] === 'radio') {
                // Fetch the latest version of input options
                $latestOptionsQuery = "SELECT * FROM InputOptionVersions WHERE input_option_id = (SELECT id FROM InputOptions WHERE input_id = :input_id)";
                $latestOptionsParams = [':input_id' => $inputId];
                $latestOptionsResult = $this->supportSiteRequestsDb->query($latestOptionsQuery, $latestOptionsParams);
    
                // Create new versions of the options marking them as deleted
                foreach ($latestOptionsResult as $option) {
                    $insertOptionQuery = "INSERT INTO InputOptionVersions (input_option_id, input_version_id, option_value, user_id, created_at, deleted_at) 
                                          VALUES (:input_option_id, :input_version_id, :option_value, :user_id, :created_at, :deleted_at)";
                    $insertOptionParams = [
                        ':input_option_id' => $option['input_option_id'],
                        ':input_version_id' => $newVersionId,
                        ':option_value' => $option['option_value'],
                        ':user_id' => $GLOBALS['user_id'] ?? null,
                        ':created_at' => $option['created_at'],
                        ':deleted_at' => $currentTimestamp
                    ];
                    $insertOption = $this->supportSiteRequestsDb->query($insertOptionQuery, $insertOptionParams);
                    if ($insertOption === false) {
                        throw new Exception("Failed to insert new input option version.");
                    }
                }
    
                    // Update the current_version_id in the InputOptions table
                    $updateInputOptionQuery = "UPDATE InputOptions SET current_version_id = :version_id WHERE input_id = :inputId";
                    $updateInputOptionParams = [
                        ':version_id' => $newVersionId,
                        ':inputId' => $inputId,
                    ];
                    $result = $this->supportSiteRequestsDb->query($updateInputOptionQuery, $updateInputOptionParams);
                    if ($result === false) {
                        throw new Exception("Failed to update current_version_id in InputOptions table.");
                    }
            }
    
            // Log the action
            $this->logAction($inputId, 'Input', $newVersionId, 'delete_input');
    
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
        WHERE i.category_id = :category_id AND iv.deleted_at IS NULL 
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
        $query = "SELECT issue_version_id FROM IssueVersions WHERE issue_version_id = :current_version_id LIMIT 1";
        $params = [':current_version_id' => $currentVersionId];
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
            $inputId = $dynamicInput['input_id'];
            $value = null;

            // Find the value in the inputs array that matches the input_id
            foreach ($data['inputs'] as $input) {
                if ($input['input_id'] == $inputId) {
                    $value = $input['value'];
                    break;
                }
            }

            if ($value !== null) {
                $this->storeRequestInputValue($supportRequestId, $dynamicInput['input_version_id'], $value);
            }
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
    public function updateStatus($requestId, $newStatus)
    {
        try {
            $this->supportSiteRequestsDb->getConnection()->beginTransaction();
    
            // Step 1: Get the current version ID from the SupportRequests table
            $currentVersionQuery = "SELECT current_version_id FROM SupportRequests WHERE id = :request_id";
            $currentVersion = $this->supportSiteRequestsDb->query($currentVersionQuery, [':request_id' => $requestId]);
            if (!$currentVersion) {
                throw new Exception("Support request not found.");
            }
            $currentVersionId = $currentVersion[0]['current_version_id'];
    
            // Step 2: Retrieve the latest details from SupportRequestVersions using the current version ID
            $versionDetailsQuery = "SELECT * FROM SupportRequestVersions WHERE support_request_version_id = :version_id";
            $versionDetails = $this->supportSiteRequestsDb->query($versionDetailsQuery, [':version_id' => $currentVersionId]);
            if (!$versionDetails) {
                throw new Exception("Support request version not found.");
            }
            $versionDetails = $versionDetails[0];
    
            // Step 3: Insert new entry in SupportRequestVersions table with updated status
            $insertVersionQuery = "INSERT INTO SupportRequestVersions 
                                    (support_request_id, status, priority, email, ip_address, user_id, created_at, deleted_at)
                                    VALUES (:support_request_id, :status, :priority, :email, :ip_address, :user_id, :created_at, :deleted_at)";
            $this->supportSiteRequestsDb->query($insertVersionQuery, [
                ':support_request_id' => $requestId,
                ':status' => $newStatus,
                ':priority' => $versionDetails['priority'],
                ':email' => $versionDetails['email'],
                ':ip_address' => $versionDetails['ip_address'],
                ':user_id' => $versionDetails['user_id'],
                ':created_at' => $versionDetails['created_at'],
                ':deleted_at' => null
            ]);
    
            // Get the ID of the newly inserted version
            $newVersionId = $this->supportSiteRequestsDb->getConnection()->lastInsertId();
    
            // Step 4: Update the current_version_id in the SupportRequests table
            $updateCurrentVersionQuery = "UPDATE SupportRequests SET current_version_id = :new_version_id WHERE id = :request_id";
            $this->supportSiteRequestsDb->query($updateCurrentVersionQuery, [
                ':new_version_id' => $newVersionId,
                ':request_id' => $requestId
            ]);
    
            // Log the action
            $this->logAction($requestId, 'SupportRequest', $newVersionId, 'update_status');
    
            // Commit the transaction
            $this->supportSiteRequestsDb->getConnection()->commit();
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $this->supportSiteRequestsDb->getConnection()->rollback();
            throw new Exception($e->getMessage());
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
            $latestVersionQuery = "SELECT status, email, ip_address, user_id FROM SupportRequestVersions WHERE support_request_version_id = :version_id";
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



public function changeStatus($requestId, $status) {
    $query = "UPDATE SupportRequestVersions SET status = :status WHERE support_request_id = :request_id AND deleted_at IS NULL";
    $params = [
        ':request_id' => $requestId,
        ':status' => $status
    ];

    try {
        $result = $this->supportSiteRequestsDb->query($query, $params);
        if ($result === false) {
            throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }
    } catch (Exception $e) {
        throw new Exception($e);
    }

    return $result;
}


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

// Fetch request details
public function fetchSupportRequestDetails($requestId) {
    // Fetch basic request details
    $query = "SELECT sr.id AS request_id, sr.category_id, sr.issue_version_id, srv.status, srv.priority, srv.email, srv.created_at 
              FROM SupportRequests sr
              INNER JOIN SupportRequestVersions srv ON sr.current_version_id = srv.support_request_version_id
              WHERE sr.id = :request_id AND srv.deleted_at IS NULL";
    $params = [':request_id' => $requestId];

    try {
        $request = $this->supportSiteRequestsDb->query($query, $params);
        if ($request === false) {
            throw new Exception("Query execution failed: " . implode(", ", $this->supportSiteRequestsDb->getConnection()->errorInfo()));
        }
    } catch (Exception $e) {
        throw new Exception($e);
    }

    // Fetch inputs
    $inputsQuery = "SELECT i.id as input_id, iv.type as input_type, iv.input_version_id as input_version_id, iv.label as input_label, sr.input_value
                    FROM SupportRequestInputs sr
                    INNER JOIN Inputs i ON sr.input_id = i.id
                    INNER JOIN InputVersions iv ON i.current_version_id = iv.input_version_id
                    WHERE sr.support_request_id = :request_id AND iv.deleted_at IS NULL";
    $inputs = $this->supportSiteRequestsDb->query($inputsQuery, $params);

    // Fetch comments
    $commentsQuery = "SELECT id, user_id, comment, created_at FROM SupportRequestComments WHERE support_request_id = :request_id AND deleted_at IS NULL";
    $comments = $this->supportSiteRequestsDb->query($commentsQuery, $params);

    return [
        'request' => $request[0],
        'inputs' => $inputs,
        'comments' => $comments
    ];
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