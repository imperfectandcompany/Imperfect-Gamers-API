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

    public function createArticle($categoryId, $title, $description, $detailedDescription, $version, $imgSrc = null)
    {
        $slug = strtolower(str_replace(' ', '-', $title));

        // Check if the slug or title already exists
        $existingArticle = $this->supportSiteDb->query('SELECT * FROM Articles WHERE Slug = :slug OR Title = :title', [':slug' => $slug, ':title' => $title]);

        if (!empty($existingArticle)) {
            throw new Exception('This article title or slug is already taken!');
        }

        $table = "Articles";
        $rows = "CategoryID, Title, Description, DetailedDescription, Slug, Version, ImgSrc";
        $values = "?, ?, ?, ?, ?, ?, ?";
        $params = makeFilterParams([$categoryId, $title, $description, $detailedDescription, $slug, $version, $imgSrc]);

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

    public function deleteArticleVersions($articleID)
    {
        $table = 'ArticleVersions';
        $rows = 'ArticleID';
        $values = '?';
        $whereClause = 'WHERE ' . $rows . ' = ' . $values;

        try {
            $deleteResult = $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $articleID, 'type' => PDO::PARAM_INT)));
            if ($deleteResult) {
                return $deleteResult;
            } else {
                throw new Exception('Failed to delete article versions. Please check if the article ID is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to delete article versions: ' . $e->getMessage());
        }
    }

    public function deleteArticle($articleID)
    {
        // First, delete associated versions
        $this->deleteArticleVersions($articleID);

        // Then, delete the article
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

        // First, create a new category version
        $this->createCategoryVersion($categoryId, $newTitle);


        // Generate filter params using makeFilterParams function
        $params = makeFilterParams([$newTitle, $categoryId]);
        // Then, update the category
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

    public function deleteCategoryVersions($categoryID)
    {
        // First, delete associated versions
        $this->deleteCategoryVersions($categoryID);

        // Then, delete the category
        $table = 'CategoryVersions';
        $rows = 'CategoryID';
        $values = '?';
        $whereClause = 'WHERE ' . $rows . ' = ' . $values;

        try {
            $deleteResult = $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $categoryID, 'type' => PDO::PARAM_INT)));
            if ($deleteResult) {
                return $deleteResult;
            } else {
                throw new Exception('Failed to delete category versions. Please check if the category ID is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to delete category versions: ' . $e->getMessage());
        }
    }


    public function createCategoryVersion($categoryId, $title)
    {
        $versionData = json_encode(['title' => $title]);
        $table = "CategoryVersions";
        $rows = "CategoryID, VersionData";
        $values = "?, ?";
        $params = makeFilterParams([$categoryId, $versionData]);

        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                return $addResult;
            } else {
                throw new Exception('Failed to add category version. Please check if the data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to create category version: ' . $e->getMessage());
        }
    }

    public function fetchCategoryVersions($categoryId)
    {
        $query = "SELECT * FROM CategoryVersions WHERE CategoryID = :categoryId ORDER BY CreatedAt DESC";
        $params = [':categoryId' => $categoryId];
        return $this->supportSiteDb->query($query, $params);
    }

    public function updateArticle($articleId, $categoryId, $newTitle, $newDescription, $newDetailedDescription, $newImgSrc = null)
    {
        // Fetch current article data to create a new version
        $currentArticle = $this->fetchArticleById($articleId);
        if (!$currentArticle || count($currentArticle) === 0) {
            throw new Exception('Article not found');
        }

        // Create a new version of the current article data
        $latestVersion = $this->getLatestArticleVersion($articleId);
        $newVersion = $latestVersion + 1;
        $this->createArticleVersion(
            $articleId,
            $currentArticle[0]['CategoryID'],
            $currentArticle[0]['Title'],
            $currentArticle[0]['Description'],
            $currentArticle[0]['DetailedDescription'],
            $newVersion,
            $currentArticle[0]['ImgSrc']
        );

        // Update the article with new data and new version number
        $slug = strtolower(str_replace(' ', '-', $newTitle));
        $params = makeFilterParams([$categoryId, $newTitle, $newDescription, $newDetailedDescription, $slug, $newImgSrc, $newVersion, $articleId]);
        try {
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "CategoryID = :categoryId, Title = :title, Description = :description, DetailedDescription = :detailedDescription, Slug = :slug, ImgSrc = :imgSrc, Version = :version",
                "ArticleID = :articleId",
                $params
            );
            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Failed to update article. Ensure common data integrity points and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to update article: ' . $e->getMessage());
        }
    }

    public function archiveArticle($articleId)
    {
        $params = makeFilterParams([1, $articleId]);
        try {
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "Archived = :archived",
                "ArticleID = :articleId",
                $params
            );
            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Failed to archive article. Ensure data integrity and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to archive article: ' . $e->getMessage());
        }
    }

    public function makeArticleStaffOnly($articleId)
    {
        $params = makeFilterParams([1, $articleId]);
        try {
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "StaffOnly = :staffOnly",
                "ArticleID = :articleId",
                $params
            );
            if ($updateResult) {
                return true;
            } else {
                throw new Exception("Failed to mark article as staff-only. Ensure data integrity and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to mark article as staff-only: ' . $e->getMessage());
        }
    }


    public function createArticleVersion($articleId, $categoryId, $title, $description, $detailedDescription, $newVersion, $imgSrc = null)
    {
        $slug = strtolower(str_replace(' ', '-', $title));

        // Check if the new slug or title already exists and is not itself
        $slug = strtolower(str_replace(' ', '-', $title));
        $existingArticle = $this->supportSiteDb->query(
            'SELECT * FROM Articles WHERE (Slug = :slug OR Title = :title) AND ArticleID != :articleId',
            [':slug' => $slug, ':title' => $title, ':articleId' => $articleId]
        );

        if (!empty($existingArticle)) {
            throw new Exception('This article title or slug is already taken!');
        }
    
        $table = "ArticleVersions";
        $rows = "ArticleID, CategoryID, Title, Description, DetailedDescription, Slug, Version, ImgSrc";
        $values = "?, ?, ?, ?, ?, ?, ?, ?";
        $params = makeFilterParams([$articleId, $categoryId, $title, $description, $detailedDescription, $slug, $newVersion, $imgSrc]);

        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                return $addResult;
            } else {
                throw new Exception('Failed to add article version. Please check if the data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to create article version: ' . $e->getMessage());
        }
    }

    public function getLatestArticleVersion($articleId)
    {
        $query = "SELECT MAX(Version) as latestVersion FROM ArticleVersions WHERE ArticleID = :articleId";
        $params = [':articleId' => $articleId];
        $result = $this->supportSiteDb->query($query, $params);

        if ($result && count($result) > 0) {
            return $result[0]['latestVersion'] ?? 1; // If no versions exist, return 1 as the initial version
        } else {
            return 1; // If no versions exist, return 1 as the initial version
        }
    }

    public function fetchArticleVersions($articleId)
    {
        $query = "SELECT * FROM ArticleVersions WHERE ArticleID = :articleId ORDER BY Version DESC";
        $params = [':articleId' => $articleId];
        return $this->supportSiteDb->query($query, $params);
    }

    public function checkCategoryTitleExists($title)
    {
        $query = "SELECT COUNT(*) as count FROM Categories WHERE title = :title";
        $params = [':title' => $title];
        $result = $this->supportSiteDb->query($query, $params);
        return $result[0]['count'] > 0;
    }
    
    public function checkArticleTitleOrSlugExists($title, $slug)
    {
        $query = "SELECT COUNT(*) as count FROM Articles WHERE title = :title OR slug = :slug";
        $params = [':title' => $title, ':slug' => $slug];
        $result = $this->supportSiteDb->query($query, $params);
        return $result[0]['count'] > 0;
    }
    

}

