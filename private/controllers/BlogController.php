<?php

// controller/SupportController.php

include ($GLOBALS['config']['private_folder'] . '/classes/class.support.php');

class BlogController
{
    protected $dbManager;
    private $dbConnection;
    private $logger;
    private $supportModel;
    private $secondaryConnection;
    //private $tertiaryConnection;

    public function __construct($dbManager, $logger)
    {
        // Connect specifically to the 'igfastdl_imperfectgamers' database for main website related data (auth)
        $this->dbConnection = $dbManager->getConnection('default');
        // Connect specifically to the 'igfastdl_imperfectgamers_support' database for support website related data
        $this->secondaryConnection = $dbManager->getConnectionByDbName('default', 'igfastdl_imperfectgamers_blog');
        // Connect specifically to the 'simple_admins' database (gameserver database server default) for user role management
        // $this->tertiaryConnection = $dbManager->getConnection('gameserver');
        // for logging purposes
        $this->logger = $logger;

        $this->supportModel = new Support($this->dbConnection, $this->secondaryConnection);
        // $this->supportModel = new Support($this->dbConnection, $this->secondaryConnection, $this->tertiaryConnection);
    }

    public function fetchAllCategories()
    {
        try {
            $categories = $this->supportModel->fetchAllCategories();
            return ResponseHandler::sendResponse('success', ['categories' => $categories], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_categories_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch categories'], 500);
        }
    }

    public function fetchArticleById($id)
    {
        try {
            $article = $this->supportModel->fetchArticleById($id);
            if ($article) {
                return ResponseHandler::sendResponse('success', ['article' => $article], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Article not found'], 404);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch article'], 500);
        }
    }


    public function fetchArticleBySlug($slug)
    {
        try {
            $article = $this->supportModel->fetchArticleBySlug($slug);
            if ($article) {
                return ResponseHandler::sendResponse('success', ['article' => $article], 200);
            } else {
                return ResponseHandler::sendResponse('success', ['article' => 'Article not found'], 200);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch article'], 500);
        }
    }

    public function fetchArticlesByCategory($categoryId)
    {
        try {
            $articles = $this->supportModel->fetchArticlesByCategory($categoryId);
            return ResponseHandler::sendResponse('success', ['articles' => $articles], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_articles_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch articles by category'], 500);
        }
    }

    public function createCategory()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $categoryTitle = $data['categoryTitle'] ?? null;

        if ($categoryTitle === null) {
            return ResponseHandler::sendResponse('error', ['message' => 'Category title is required'], 400);
        }

        $userId = $GLOBALS['user_id']; // identify the current user

        try {
            // Step 1: Create the category
            $categoryIdResult = $this->supportModel->createCategory($userId, $categoryTitle);
            if (!$categoryIdResult) {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create category'], 500);
            }

            // Step 2: Create the category version
            $categoryVersionIdResult = $this->supportModel->createCategoryVersion($categoryIdResult, $categoryTitle);
            if (!$categoryVersionIdResult) {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create category version'], 500);
            }

            // Step 3: Update the category with the version ID
            $updateResult = $this->supportModel->updateCategoryWithVersionId($categoryIdResult, $categoryVersionIdResult);
            if (!$updateResult) {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to update category with version ID'], 500);
            }

            return ResponseHandler::sendResponse('success', [
                'message' => 'Category and category version created successfully',
                'categoryId' => $categoryIdResult,
                'categoryVersionId' => $categoryVersionIdResult
            ], 201);

        } catch (Exception $e) {
            $this->logger->log('error', 'create_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
        }
    }

    public function checkCategoryTitleExists()
    {
        $title = $_GET['categoryTitle'] ?? null;

        if ($title === null) {
            return ResponseHandler::sendResponse('error', ['message' => 'Title query parameter is required'], 400);
        }

        try {
            $exists = $this->supportModel->checkCategoryTitleExists($title);
            return ResponseHandler::sendResponse('success', ['exists' => $exists], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'check_category_title_exists_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to check category title'], 500);
        }
    }

    public function checkArticleTitleOrSlugExists($title, $slug)
    {
        try {
            $exists = $this->supportModel->checkArticleTitleOrSlugExists($title, $slug);
            return ResponseHandler::sendResponse('success', ['exists' => $exists], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'check_article_title_or_slug_exists_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to check article title or slug'], 500);
        }
    }

    public function createArticle()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Extract data from the request body
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $detailedDescription = $data['detailedDescription'] ?? null;
        $categoryId = $data['categoryId'] ?? null;
        // Convert title to slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $imgSrc = $data['imgSrc'] ?? 'https://placehold.co/100x100.png?text=' . $slug;

        // Check for required fields
        if ($title === null || $description === null || $detailedDescription === null || $categoryId === null) {
            return ResponseHandler::sendResponse('error', ['message' => 'Missing required fields'], 400);
        }

        $userId = $GLOBALS['user_id']; // identify the current user

        try {
            $creationResult = $this->supportModel->createArticle($userId, $categoryId, $title, $description, $detailedDescription, $imgSrc);

            if ($creationResult) {
                $articleId = $creationResult['articleId'];
                $versionId = $creationResult['versionId'];
                return ResponseHandler::sendResponse('success', ['message' => 'Article created successfully', 'articleID' => $articleId, 'versionID' => $versionId], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'create_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500); // Adjusted to return the exception message
        }
    }


    public function fetchAllArticles()
    {
        try {
            $articles = $this->supportModel->fetchAllArticles();
            return ResponseHandler::sendResponse('success', ['articles' => $articles], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_all_articles_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch articles'], 500);
        }
    }


    public function updateCategory($categoryId)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Extract data from the request body
        $categoryTitle = $data['categoryTitle'] ?? null;
        $userId = $GLOBALS['user_id']; // identify the current user

        if ($categoryTitle === null) {
            return ResponseHandler::sendResponse('error', ['message' => 'Title query parameter is required'], 400);
        }

        try {
            $newVersionId = $this->supportModel->updateCategory($categoryId, $categoryTitle, $userId);

            if ($newVersionId) {
                return ResponseHandler::sendResponse('success', ['message' => 'Category updated successfully', 'versionID' => $newVersionId], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to update category'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'update_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to update category'], 500);
        }
    }


    public function updateArticle($articleId)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Extract data from the request body
        $categoryId = $data['categoryId'] ?? null;
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $detailedDescription = $data['detailedDescription'] ?? null;
        $imgSrc = $data['imgSrc'] ?? null; // Assuming 'imgSrc' is optional

        try {
            $newVersionId = $this->supportModel->updateArticle($articleId, $categoryId, $title, $description, $detailedDescription, $imgSrc);
            if ($newVersionId) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article updated successfully', 'versionID' => $newVersionId], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to update article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'update_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to update article'], 500);
        }
    }

    public function archiveArticle($articleId)
    {
        try {
            $newVersionId = $this->supportModel->archiveArticle($articleId);
            if ($newVersionId) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article archived successfully', 'versionID' => $newVersionId], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to archive article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'archive_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to archive article'], 500);
        }
    }

    public function toggleArticleStaffOnly($articleId)
    {
        try {
            $newVersionId = $this->supportModel->toggleArticleStaffOnly($articleId);
            if ($newVersionId) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article marked as staff-only successfully', 'versionID' => $newVersionId], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to mark article as staff-only'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'make_article_staff_only_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to mark article as staff-only'], 500);
        }
    }

    public function createArticleVersion($articleId, $categoryId, $title, $description, $detailedDescription, $imgSrc = null)
    {
        try {
            $latestVersion = $this->supportModel->getLatestArticleVersion($articleId);
            $newVersion = $latestVersion + 1;
            $result = $this->supportModel->createArticleVersion($articleId, $categoryId, $title, $description, $detailedDescription, $newVersion, $imgSrc);

            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article version created successfully', 'version' => $newVersion], 201);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create article version'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'create_article_version_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to create article version'], 500);
        }
    }

    public function fetchArticleVersions($articleId)
    {
        try {
            $versions = $this->supportModel->fetchArticleVersions($articleId);
            return ResponseHandler::sendResponse('success', ['versions' => $versions], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_article_versions_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch article versions'], 500);
        }
    }

    public function createCategoryVersion($categoryId, $title)
    {
        try {
            $result = $this->supportModel->createCategoryVersion($categoryId, $title);

            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Category version created successfully'], 201);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create category version'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'create_category_version_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to create category version'], 500);
        }
    }

    public function fetchCategoryVersions($categoryId)
    {
        try {
            // Fetch the current version and historical versions
            $versionsData = $this->supportModel->fetchCategoryVersions($categoryId);
            $currentVersion = $versionsData['currentVersion'];
            $historicalVersions = $versionsData['historicalVersions'];

            return ResponseHandler::sendResponse('success', ['currentVersion' => $currentVersion, 'versions' => $historicalVersions], 200);

        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_category_versions_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch category versions'], 500);
        }
    }



    public function fetchArticleActionLogs($articleId)
    {
        try {
            $logs = $this->supportModel->fetchArticleActionLogs($articleId);
            return ResponseHandler::sendResponse('success', ['logs' => $logs], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_article_action_logs_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch article action logs'], 500);
        }
    }

    // public function deleteArticle($articleId)
    // {
    //     try {
    //         $result = $this->supportModel->deleteArticle($articleId);
    //         if ($result) {
    //             return ResponseHandler::sendResponse('success', ['message' => 'Article deleted successfully'], 200);
    //         } else {
    //             return ResponseHandler::sendResponse('error', ['message' => 'Failed to delete article'], 500);
    //         }
    //     } catch (Exception $e) {
    //         $this->logger->log('error', 'delete_article_error', ['error' => $e->getMessage()]);
    //         return ResponseHandler::sendResponse('error', ['message' => 'Failed to delete article'], 500);
    //     }
    // }


    public function deleteArticle($articleId)
    {
        $userId = $GLOBALS['user_id']; // identify the current user

        try {
            $result = $this->supportModel->deleteArticle($articleId, $userId);
            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article deleted successfully'], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to delete article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'delete_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to delete article'], 500);
        }
    }

    public function deleteCategory($categoryId)
    {
        $userId = $GLOBALS['user_id']; // identify the current user

        try {
            $result = $this->supportModel->deleteCategory($categoryId, $userId);
            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Category deleted successfully'], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to delete category'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'delete_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to delete category'], 500);
        }
    }

    public function restoreArticle(int $articleId)
    {
        $userId = $GLOBALS['user_id']; // identify the current user

        try {
            $newVersion = $this->supportModel->restoreArticle($articleId, $userId);
            if ($newVersion) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article restored successfully', 'version' => $newVersion], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to restore article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'restore_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to restore article', 'error' => $e->getMessage()], 500);
        }
    }

    public function restoreCategory($categoryId)
    {
        $userId = $GLOBALS['user_id']; // identify the current user

        try {
            $result = $this->supportModel->restoreCategory($categoryId, $userId);
            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Category restored successfully'], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to restore category'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'restore_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to restore category'], 500);
        }
    }

    public function fetchDeletedCategories()
    {
        try {
            $deletedCategories = $this->supportModel->fetchDeletedCategories();
            return ResponseHandler::sendResponse('success', ['deletedCategories' => $deletedCategories], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_deleted_categories_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch deleted categories'], 500);
        }
    }

    public function fetchDeletedArticles()
    {
        try {
            $deletedArticles = $this->supportModel->fetchDeletedArticles();
            return ResponseHandler::sendResponse('success', ['deletedArticles' => $deletedArticles], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_deleted_articles_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch deleted articles'], 500);
        }
    }

}
 