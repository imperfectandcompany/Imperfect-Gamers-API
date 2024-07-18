<?php

// controller/SupportRequest.php

include ($GLOBALS['config']['private_folder'] . '/classes/class.SupportRequest.php');

class SupportRequestController
{
    protected $dbManager;
    private $dbConnection;
    private $logger;
    private $supportRequestModel;
    private $secondaryConnection;
    private $tertiaryConnection;


    public function __construct($dbManager, $logger)
    {
        // Connect specifically to the 'igfastdl_imperfectgamers' database for main website related data (auth)
        $this->dbConnection = $dbManager->getConnection('default');
        // Connect specifically to the 'igfastdl_imperfectgamers_support' database for support website related data
        $this->secondaryConnection = $dbManager->getConnectionByDbName('default', 'igfastdl_imperfectgamers_support');
        // Connect specifically to the 'igfastdl_imperfectgamers_requests database for support request website related data
        $this->tertiaryConnection = $dbManager->getConnectionByDbName('default', 'igfastdl_imperfectgamers_requests');
        // for logging purposes
        $this->logger = $logger;

        $this->supportRequestModel = new SupportRequest($this->dbConnection, $this->secondaryConnection, $this->tertiaryConnection);
    }


    public function handleCategorySelection(int $categoryId)
    {
        try {
            $formData = $this->fetchInputsAndIssueForCategory($categoryId);
            return ResponseHandler::sendResponse('success', ['data' => $formData], 200);

        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_categories_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch categories'], 500);
        }
    }

    private function fetchInputsAndIssueForCategory($categoryId)
    {
        return $this->supportRequestModel->fetchInputsAndIssueForCategory($categoryId);
    }

    // Handles fetching all categories
    public function handleFetchAllCategories()
    {
        try {
            $categories = $this->fetchAllCategories();
            return ResponseHandler::sendResponse('success', ['data' => $categories], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_all_categories_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch all categories'], 500);
        }
    }

    private function fetchAllCategories()
    {
        return $this->supportRequestModel->fetchAllLevelCategories();
    }

    // Handles fetching all request form data
    public function handleFetchAllRequestFormData()
    {
        try {
            $requestFormData = $this->fetchAllRequestFormData();
            return ResponseHandler::sendResponse('success', ['data' => $requestFormData], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_all_request_form_data_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch all request form data'], 500);
        }
    }

    private function fetchAllRequestFormData()
    {
        return $this->supportRequestModel->fetchAllRequestFormData();
    }


    // Fetch action logs
    public function handleFetchActionLogs()
    {
        try {
            $logs = $this->fetchActionLogs();
            return ResponseHandler::sendResponse('success', ['data' => $logs], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_action_logs_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
        }
    }

    private function fetchActionLogs()
    {
        return $this->supportRequestModel->fetchActionLogs();
    }

    // Fetch all inputs
    public function handleFetchAllInputs()
    {
        try {
            $inputs = $this->fetchAllInputs();
            return ResponseHandler::sendResponse('success', ['data' => $inputs], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_all_inputs_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch inputs'], 500);
        }
    }

    private function fetchAllInputs()
    {
        return $this->supportRequestModel->fetchAllInputs();
    }

    // Fetch all issues
    public function handleFetchAllIssues()
    {
        try {
            $issues = $this->fetchAllIssues();
            return ResponseHandler::sendResponse('success', ['data' => $issues], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_all_issues_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);

        }
    }

    private function fetchAllIssues()
    {
        return $this->supportRequestModel->fetchAllIssues();
    }


    // Fetch a specific support request
    public function handleFetchSupportRequest(int $supportRequestId) {
        try {
            $supportRequest = $this->fetchSupportRequest($supportRequestId);
            return ResponseHandler::sendResponse('success', ['data' => $supportRequest], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_support_request_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
        }
    }

    private function fetchSupportRequest($supportRequestId) {
        return $this->supportRequestModel->fetchSupportRequest($supportRequestId);
    }

        // Fetch all support requests
        public function handleFetchAllSupportRequests() {
            try {
                $supportRequests = $this->fetchAllSupportRequests();
                return ResponseHandler::sendResponse('success', ['data' => $supportRequests], 200);
            } catch (Exception $e) {
                $this->logger->log('error', 'fetch_all_support_requests_error', ['error' => $e->getMessage()]);
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch support requests'], 500);
            }
        }
    
        private function fetchAllSupportRequests() {
            return $this->supportRequestModel->fetchAllSupportRequests();
        }


            // Fetch all categories' hierarchy
    public function handleFetchCategoriesHierarchy() {
        try {
            $categoriesHierarchy = $this->fetchCategoriesHierarchy();
            return ResponseHandler::sendResponse('success', ['data' => $categoriesHierarchy], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_categories_hierarchy_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch categories hierarchy'], 500);
        }
    }

    private function fetchCategoriesHierarchy() {
        return $this->supportRequestModel->fetchCategoriesHierarchy();
    }

    // Fetch all input versions
    public function handleFetchAllInputVersions() {
        try {
            $inputVersions = $this->fetchAllInputVersions();
            return ResponseHandler::sendResponse('success', ['data' => $inputVersions], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_all_input_versions_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch input versions'], 500);
        }
    }

    private function fetchAllInputVersions() {
        return $this->supportRequestModel->fetchAllInputVersions();
    }



    // Fetch specific category versions
    public function handleFetchCategoryVersions($categoryId) {
        try {
            $categoryVersions = $this->fetchCategoryVersions($categoryId);
            return ResponseHandler::sendResponse('success', ['data' => $categoryVersions], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_category_versions_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch category versions'], 500);
        }
    }

    private function fetchCategoryVersions($categoryId) {
        return $this->supportRequestModel->fetchCategoryVersions($categoryId);
    }


    // Fetch historical versions of a specific category
    public function handleFetchCategoryVersionHistory($categoryId) {
        try {
            $categoryHistory = $this->fetchCategoryVersionHistory($categoryId);
            return ResponseHandler::sendResponse('success', ['data' => $categoryHistory], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_category_version_history_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch category version history'], 500);
        }
    }

    private function fetchCategoryVersionHistory($categoryId) {
        return $this->supportRequestModel->fetchCategoryVersionHistory($categoryId);
    }


   // Fetch specific issue versions
   public function handleFetchIssueVersions($issueId) {
    try {
        $issueVersions = $this->fetchIssueVersions($issueId);
        return ResponseHandler::sendResponse('success', ['data' => $issueVersions], 200);
    } catch (Exception $e) {
        $this->logger->log('error', 'fetch_issue_versions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch issue versions'], 500);
    }
}

private function fetchIssueVersions($issueId) {
    return $this->supportRequestModel->fetchIssueVersions($issueId);
}

// Fetch historical versions of a specific issue
public function handleFetchIssueVersionHistory($issueId) {
    try {
        $issueHistory = $this->fetchIssueVersionHistory($issueId);
        return ResponseHandler::sendResponse('success', ['data' => $issueHistory], 200);
    } catch (Exception $e) {
        $this->logger->log('error', 'fetch_issue_version_history_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch issue version history'], 500);
    }
}

private function fetchIssueVersionHistory($issueId) {
    return $this->supportRequestModel->fetchIssueVersionHistory($issueId);
}





    // Fetch specific support request versions
    public function handleFetchSupportRequestVersions($supportRequestId) {
        try {
            $supportRequestVersions = $this->fetchSupportRequestVersions($supportRequestId);
            return ResponseHandler::sendResponse('success', ['data' => $supportRequestVersions], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_support_request_versions_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch support request versions'], 500);
        }
    }

    private function fetchSupportRequestVersions($supportRequestId) {
        return $this->supportRequestModel->fetchSupportRequestVersions($supportRequestId);
    }

    // Fetch historical versions of a specific support request
    public function handleFetchSupportRequestVersionHistory($supportRequestId) {
        try {
            $supportRequestHistory = $this->fetchSupportRequestVersionHistory($supportRequestId);
            return ResponseHandler::sendResponse('success', ['data' => $supportRequestHistory], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_support_request_version_history_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch support request version history'], 500);
        }
    }

    private function fetchSupportRequestVersionHistory($supportRequestId) {
        return $this->supportRequestModel->fetchSupportRequestVersionHistory($supportRequestId);
    }




















    public function getIssueCategories()
    {
        try {
            $query = "SELECT
                    ic.id AS IssueCategoryID,
                    ic.label AS IssueCategoryLabel,
                    sc.id AS SubCategoryID,
                    sc.label AS SubCategoryLabel,
                    si.id AS SubIssueID,
                    si.label AS SubIssueLabel,
                    i.id AS InputID,
                    i.context_id AS InputContextID,
                    i.context_type AS InputContextType,
                    i.label AS InputLabel,
                    i.type AS InputType,
                    i.placeholder AS InputPlaceholder,
                    i.tooltip AS InputTooltip,
                    v.VersionID,
                    v.ContextID,
                    v.ContextType,
                    v.Label AS VersionLabel,
                    v.Type AS VersionType,
                    v.Placeholder AS VersionPlaceholder,
                    v.Tooltip AS VersionTooltip
                FROM
                    IssueCategories ic
                LEFT JOIN SubCategories sc ON sc.category_id = ic.id AND sc.isArchived = 0 AND sc.DeletedAt IS NULL
                LEFT JOIN SubIssues si ON si.sub_category_id = sc.id AND si.isArchived = 0 AND si.DeletedAt IS NULL
                LEFT JOIN Inputs i ON i.context_id = ic.id AND i.context_type = 'Category' AND i.isArchived = 0 AND i.DeletedAt IS NULL
                LEFT JOIN Versions v ON (
                    (v.ContextID = ic.id AND v.ContextType = 'Category')
                    OR (v.ContextID = sc.id AND v.ContextType = 'SubCategory')
                    OR (v.ContextID = si.id AND v.ContextType = 'SubIssue')
                    OR (v.ContextID = i.id AND v.ContextType = 'Input')
                )
                WHERE
                    ic.isArchived = 0 AND ic.DeletedAt IS NULL
                ORDER BY
                    ic.id, sc.id, si.id, i.id";

            // Execute the query
            $result = $this->secondaryConnection->query($query);

            if ($result === false) {
                throw new Exception("Query execution failed: " . implode(", ", $this->secondaryConnection->errorInfo()));
            }

            $rows = $result; // $result is already an array of rows

            // Check if $rows is an array before processing
            if (is_array($rows)) {
                $categories = [];

                // Process the rows
                foreach ($rows as $row) {
                    $categoryId = $row['category_id'];
                    if (!isset($categories[$categoryId])) {
                        $categories[$categoryId] = [
                            'id' => $categoryId,
                            'label' => $row['category_label'],
                            'versionId' => $row['category_version_id'],
                            'subCategories' => []
                        ];
                    }

                    if ($row['subcategory_id']) {
                        $subCategoryId = $row['subcategory_id'];
                        if (!isset($categories[$categoryId]['subCategories'][$subCategoryId])) {
                            $categories[$categoryId]['subCategories'][$subCategoryId] = [
                                'id' => $subCategoryId,
                                'label' => $row['subcategory_label'],
                                'versionId' => $row['subcategory_version_id'],
                                'subIssues' => []
                            ];
                        }

                        if ($row['subissue_id']) {
                            $subIssues = &$categories[$categoryId]['subCategories'][$subCategoryId]['subIssues'];
                            $subIssue = array_filter($subIssues, function ($s) use ($row) {
                                return $s['id'] === $row['subissue_id'];
                            });

                            if (empty($subIssue)) {
                                $subIssues[] = [
                                    'id' => $row['subissue_id'],
                                    'label' => $row['subissue_label'],
                                    'versionId' => $row['subissue_version_id'],
                                    'inputs' => []
                                ];
                            }

                            if ($row['subissue_input_id']) {
                                $subIssueIndex = array_search($row['subissue_id'], array_column($subIssues, 'id'));
                                $subIssues[$subIssueIndex]['inputs'][] = [
                                    'id' => $row['subissue_input_id'],
                                    'label' => $row['subissue_input_label'],
                                    'type' => $row['subissue_input_type'],
                                    'placeholder' => $row['subissue_input_placeholder'],
                                    'tooltip' => $row['subissue_input_tooltip'],
                                    'versionId' => $row['subissue_input_version_id']
                                ];
                            }
                        }
                    }
                }

                return ResponseHandler::sendResponse('success', ['categories' => array_values($categories)], 200);
            } else {
                // Handle the case where $rows is not an array
                throw new Exception("Query returned no results or failed.");
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_categories_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch categories'], 500);
        }
    }



    public function submitSupportRequest($request)
    {
        // Logic to submit a support request with version tracking
    }

    // public function createSupportRequest($request) {
    //     // Logic to create a new support request
    // }

    // public function updateSupportRequest($request, $id) {
    //     // Logic to update an existing support request
    // }

    // public function deleteSupportRequest($id) {
    //     // Logic to delete a support request
    // }

    // public function fetchSupportRequests() {
    //     // Logic to fetch all support requests
    // }

    // public function fetchSupportRequestById($id) {
    //     // Logic to fetch a single support request by ID
    // }

}
