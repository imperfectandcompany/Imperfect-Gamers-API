<?php

// controller/SupportController.php

include ($GLOBALS['config']['private_folder'] . '/classes/class.support.php');

class SupportController
{
    protected $dbManager;
    private $dbConnection;
    private $logger;
    private $supportModel;
    private $secondaryConnection;
    private $tertiaryConnection;

    public function __construct($dbManager, $logger)
    {
        // Connect specifically to the 'igfastdl_imperfectgamers' database for main website related data (auth)
        $this->dbConnection = $dbManager->getConnection('default');
        // Connect specifically to the 'igfastdl_imperfectgamers_support' database for support website related data
        $this->secondaryConnection = $dbManager->getConnectionByDbName('default', 'igfastdl_imperfectgamers_support');
        // Connect specifically to the 'simple_admins' database (gameserver database server default) for user role management
        $this->tertiaryConnection = $dbManager->getConnection('gameserver');
        // for logging purposes
        $this->logger = $logger;

        $this->supportModel = new Support($this->dbConnection, $this->secondaryConnection, $this->tertiaryConnection);
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

    public function createCategory($title)
    {
        try {
            $result = $this->supportModel->createCategory($title);

            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Category created successfully', 'categoryID' => $result["insertID"]], 201);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create category'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'create_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to create category'], 500);
        }
    }

    public function createArticle($categoryId, $title, $description, $detailedDescription, $imgSrc = null)
    {
        try {
            $result = $this->supportModel->createArticle($categoryId, $title, $description, $detailedDescription, $imgSrc);

            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article created successfully', 'articleID' => $result["insertID"]], 201);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to create article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'create_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to create article'], 500);
        }
    }

    public function deleteArticle($articleId)
    {
        try {
            $result = $this->supportModel->deleteArticle($articleId);
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
        try {
            $result = $this->supportModel->deleteCategory($categoryId);
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


    public function updateCategory($categoryId, $newTitle)
    {
        try {
            $result = $this->supportModel->updateCategory($categoryId, $newTitle);

            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Category updated successfully'], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to update category'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'update_category_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to update category'], 500);
        }
    }

    public function updateArticle($articleId, $newTitle, $newDescription, $newDetailedDescription, $newImgSrc = null)
    {
        try {
            $result = $this->supportModel->updateArticle($articleId, $newTitle, $newDescription, $newDetailedDescription, $newImgSrc);
            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article updated successfully'], 200);
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
            $result = $this->supportModel->archiveArticle($articleId);
            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article archived successfully'], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to archive article'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'archive_article_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to archive article'], 500);
        }
    }

    public function makeArticleStaffOnly($articleId)
    {
        try {
            $result = $this->supportModel->makeArticleStaffOnly($articleId);
            if ($result) {
                return ResponseHandler::sendResponse('success', ['message' => 'Article marked as staff-only successfully'], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Failed to mark article as staff-only'], 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'make_article_staff_only_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Failed to mark article as staff-only'], 500);
        }
    }

}
