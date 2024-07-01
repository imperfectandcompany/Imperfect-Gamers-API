<?php

// classes/class.support.php

/**
 * Support class handles support site activities.
 */

class Support
{
    private $mainSiteDb; // Connection for main site related operations
    private $supportSiteDb;   // Connection for support site related operations
    private $simpleAdminDb;  // Connection for game server role management related operations

    /**
     * Constructor for the Support class.
     * @param DatabaseConnector $mainSiteDb Connection object for the main website server database
     * @param DatabaseConnector $supportSiteDb Connection object for the support website server database
     * @param DatabaseConnector $simpleAdminDb Connection object for the game server database
     */

    public function __construct($mainSiteDb, $supportSiteDb, $simpleAdminDb)
    {
        $this->mainSiteDb = $mainSiteDb;
        $this->supportSiteDb = $supportSiteDb;
        $this->simpleAdminDb = $simpleAdminDb;
    }

    public function fetchAllCategories()
    {
        $query = "SELECT * FROM Categories";
        return $this->supportSiteDb->query($query);
    }

    public function fetchArticleById($id)
    {
        $query = "SELECT * FROM Articles WHERE ArticleID = :id";
        $params = [':id' => $id];
        return $this->supportSiteDb->query($query, $params);
    }

    public function fetchArticlesByCategory($categoryId)
    {
        $query = "SELECT * FROM Articles WHERE CategoryID = :categoryId";
        $params = [':categoryId' => $categoryId];
        return $this->supportSiteDb->query($query, $params);
    }

    public function createCategory($title)
    {
        $table = "Categories";
        // First, check if the title already exists
        $existingTitle = $this->supportSiteDb->query('SELECT title FROM Categories WHERE title = :title', [':title' => $title]);

        if (!empty($existingTitle)) {
            throw new Exception('This category title is already taken!');
        }

        // If the username does not exist, proceed to insert the new username
        $rows = 'title';
        $values = '?';
        $params = makeFilterParams([$title]);
        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                return $addResult;
            } else {
                throw new Exception('Failed to add category. Please check if the category title data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to create category' . $e->getMessage());
        }
    }

    public function createArticle($categoryId, $title, $description, $detailedDescription, $imgSrc = null)
    {
        $table = "Articles";
        $rows = "CategoryID, Title, Description, DetailedDescription, ImgSrc";
        $values = "?, ?, ?, ?, ?";
        $params = makeFilterParams([$categoryId, $title, $description, $detailedDescription, $imgSrc]);
        
        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                return $addResult;
            } else {
                throw new Exception('Failed to add article. Please check if the article data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to create article: ' . $e->getMessage());
        }
    }


    public function deleteCategory($categoryID)
    {
        $table = 'Categories';
        $rows = 'CategoryID';
        $values = '?';
        $whereClause = 'WHERE ' . $rows . ' = ' . $values;
        // Delete the specified token
       return $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $categoryID, 'type' => PDO::PARAM_INT)));
    }

    public function deleteArticle($articleID)
    {
        $table = 'Articles';
        $rows = 'ArticleID';
        $values = '?';
        $whereClause = 'WHERE ' . $rows . ' = ' . $values;
        
        try {
            $deleteResult = $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $articleID, 'type' => PDO::PARAM_INT)));
            if ($deleteResult) {
                return $deleteResult;
            } else {
                throw new Exception('Failed to delete article. Please check if the article ID is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to delete article: ' . $e->getMessage());
        }
    }

    public function updateCategory($categoryId, $newTitle)
    {
        // Generate filter params using makeFilterParams function
        $params = makeFilterParams([$newTitle, $categoryId]);
        try {
            // Call updateData method with generated filter params
            $updateResult = $this->supportSiteDb->updateData(
                "Categories",
                "title = :title",
                "CategoryID = :categoryId",
                $params
            );
            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Failed to update category. Ensure common data integrity points and try again.");
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to update category: ' . $e->getMessage());
        }
    }
    

public function updateArticle($articleId, $newTitle, $newDescription, $newDetailedDescription, $newImgSrc = null)
{
    // Generate filter params using makeFilterParams function
    $params = makeFilterParams([$newTitle, $newDescription, $newDetailedDescription, $newImgSrc, $articleId]);
    try {
        // Call updateData method with generated filter params
        $updateResult = $this->supportSiteDb->updateData(
            "Articles",
            "Title = :title, Description = :description, DetailedDescription = :detailedDescription, ImgSrc = :imgSrc",
            "ArticleID = :articleId",
            $params
        );
        if ($updateResult) {
            return true;
        } else {
            throw new Exception("Failed to update article. Ensure common data integrity points and try again.");
        }
    } catch (Exception $e) {
        // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
        throw new PDOException('Failed to update article: ' . $e->getMessage());
    }
}

}
