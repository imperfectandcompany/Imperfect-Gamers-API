<?php

// classes/class.support.php

/**
 * Support class handles support site activities.
 */

class Support
{
    private $mainSiteDb; // Connection for main site related operations
    private $supportSiteDb;   // Connection for support site related operations
    // private $simpleAdminDb;  // Connection for game server role management related operations

    // Define action type constants
    const ARTICLE_DELETED = 'article_deleted';
    const ARTICLE_ARCHIVED = 'article_archived';
    const ARTICLE_UNARCHIVED = 'article_unarchived';
    const ARTICLE_CATEGORY_MOVED = 'article_category_moved';
    const ARTICLE_TITLE_CHANGED = 'article_title_changed';
    const ARTICLE_DESCRIPTION_CHANGED = 'article_description_changed';
    const ARTICLE_DETAILED_DESCRIPTION_CHANGED = 'article_detailed_description_changed';
    const ARTICLE_IMG_SRC_CHANGED = 'article_img_src_changed';
    const ARTICLE_SET_STAFF_ONLY = 'article_set_staff_only';
    const ARTICLE_SET_PUBLIC = 'article_set_public';
    const ARTICLE_RESTORED = 'article_restored';
    const ARTICLE_CREATED = 'article_created';
    const ARTICLE_CONTENT_UPDATE_FAILED = 'article_content_update_failed';
    const CATEGORY_CREATED = 'category_created';
    const CATEGORY_UPDATED = 'category_updated';
    const CATEGORY_DELETED = 'category_deleted';
    const CATEGORY_RESTORED = 'category_restored';

    /**
     * Constructor for the Support class.
     * @param DatabaseConnector $mainSiteDb Connection object for the main website server database
     * @param DatabaseConnector $supportSiteDb Connection object for the support website server database
     */

     // @param DatabaseConnector $simpleAdminDb Connection object for the game server database


    public function __construct($mainSiteDb, $supportSiteDb)
    {
        $this->mainSiteDb = $mainSiteDb;
        $this->supportSiteDb = $supportSiteDb;
        // $this->simpleAdminDb = $simpleAdminDb;
    }

    public function fetchAllCategories()
    {
        $query = "SELECT Categories.*, COUNT(Articles.ArticleID) AS ArticleCount
        FROM Categories
        LEFT JOIN Articles ON Categories.CategoryID = Articles.CategoryID AND Articles.DeletedAt IS NULL
        WHERE Categories.DeletedAt IS NULL
        GROUP BY Categories.CategoryID";
        return $this->supportSiteDb->query($query);
    }

    public function fetchArticleById($id, $deletedAt = null)
    {
        if($deletedAt === 3){
        $query = "SELECT * FROM Articles WHERE ArticleID = :id AND DeletedAt IS NOT NULL";
        } else {
        $query = "SELECT * FROM Articles WHERE ArticleID = :id AND DeletedAt IS NULL";
        }
        $params = [':id' => $id];
        return $this->supportSiteDb->query($query, $params);
    }

    public function fetchArticleBySlug($slug)
    {
        $query = "SELECT * FROM Articles WHERE Slug = :slug AND DeletedAt IS NULL";

        $params = [':slug' => $slug];
        return $this->supportSiteDb->query($query, $params);
    }

    public function fetchArticlesByCategory($categoryId)
    {
        $query = "SELECT * FROM Articles WHERE CategoryID = :categoryId AND DeletedAt IS NULL";
        $params = [':categoryId' => $categoryId];
        return $this->supportSiteDb->query($query, $params);
    }

    public function createCategory($userId, $title)
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
                $categoryId = $this->supportSiteDb->lastInsertId();


                // log the creation in a CategoryActionLog
                $this->logCategoryAction($userId, $categoryId, self::CATEGORY_CREATED);

                return $categoryId;
            } else {
                throw new Exception('Failed to add category. Please check if the category title data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to create category' . $e->getMessage());
        }
    }

    public function createArticle($userId, $categoryId, $title, $description, $detailedDescription, $imgSrc)
    {
        $slug = strtolower(str_replace(' ', '-', $title));

        // Check if the slug or title already exists
        $existingArticle = $this->supportSiteDb->query('SELECT * FROM Articles WHERE Slug = :slug OR Title = :title', [':slug' => $slug, ':title' => $title]);

        if (!empty($existingArticle)) {
            throw new Exception('This article title or slug is already taken!');
        }

        // Insert the article with a NULL version initially
        $table = "Articles";
        $rows = "CategoryID, Title, Description, DetailedDescription, ImgSrc, Archived, StaffOnly, Slug, VersionID";
        $values = "?, ?, ?, ?, ?, 0, 0, ?, NULL"; // Set Version as NULL
        $params = makeFilterParams([$categoryId, $title, $description, $detailedDescription, $imgSrc, $slug]);

        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                $articleId = $this->supportSiteDb->lastInsertId();
                // Create a new article version and get the version ID
                $newArticleVersionId = $this->createArticleVersion(
                    $articleId,
                    $categoryId,
                    $title,
                    $description,
                    $detailedDescription,
                    $imgSrc
                );

                // Update the article with the new version ID
                $updateResult = $this->supportSiteDb->updateData(
                    "Articles",
                    "VersionID = ?",
                    "ArticleID = ?",
                    makeFilterParams([$newArticleVersionId, $articleId])
                );
                if (!$updateResult) {
                    throw new Exception('Failed to update article with new version ID.');
                }

                // log the creation in a CategoryActionLog
                $this->logArticleAction($userId, $newArticleVersionId, [self::ARTICLE_CREATED]);
// Return both IDs as an associative array
                return [
                    'articleId' => $articleId,
                    'versionId' => $newArticleVersionId
                ];
            } else {
                throw new Exception('Failed to add article. Please check if the article data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to create article: ' . $e->getMessage());
        }
    }

    public function updateCategoryWithVersionId($categoryId, $versionId)
    {
        // Generate filter params using makeFilterParams function
        $params = makeFilterParams([$versionId, $categoryId]);


        // Then, update the category
        try {
            // Call updateData method with generated filter params
            $updateResult = $this->supportSiteDb->updateData(
                "Categories",
                "VersionID = :versionId",
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

    public function updateCategory($categoryId, $newTitle, $userId)
    {
        // First, create a new category version and get the version ID
        $newVersionId = $this->createCategoryVersion($categoryId, $newTitle);
        $newSlug = strtolower(str_replace(' ', '-', $newTitle));

        // Generate filter params using makeFilterParams function
        $params = makeFilterParams([$newTitle, $newSlug, $newVersionId, $categoryId]);


        // Then, update the category
        try {
            // Call updateData method with generated filter params
            $updateResult = $this->supportSiteDb->updateData(
                "Categories",
                "Title = :title, Slug = :slug, VersionID = :versionId",
                "CategoryID = :categoryId",
                $params
            );
            if ($updateResult) {

                // log the update in a CategoryActionLog
                $this->logCategoryAction($userId, $newVersionId, self::CATEGORY_UPDATED);
                return $newVersionId; // Return the new version ID
            } else {
                throw new Exception("Failed to update category. Ensure common data integrity points and try again.");
            }
        } catch (Exception $e) {
            // Consider checking the reason for failure: was it a database connection issue, or were no rows affected?
            throw new PDOException('Failed to update category: ' . $e->getMessage());
        }
    }

    // public function deleteCategoryVersions($categoryID)
    // {
    //     // First, delete associated versions
    //     $this->deleteCategoryVersions($categoryID);

    //     // Then, delete the category
    //     $table = 'CategoryVersions';
    //     $rows = 'CategoryID';
    //     $values = '?';
    //     $whereClause = 'WHERE ' . $rows . ' = ' . $values;

    //     try {
    //         $deleteResult = $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $categoryID, 'type' => PDO::PARAM_INT)));
    //         if ($deleteResult) {
    //             return $deleteResult;
    //         } else {
    //             throw new Exception('Failed to delete category versions. Please check if the category ID is correct and try again.');
    //         }
    //     } catch (Exception $e) {
    //         throw new PDOException('Failed to delete category versions: ' . $e->getMessage());
    //     }
    // }

    public function createCategoryVersion($categoryId, $title, $isDeleted = 0)
    {
        // Determine if the DeletedAt timestamp should be set
        $deletedAt = $isDeleted ? date('Y-m-d H:i:s') : null;
    
        $table = "CategoryVersions";
        $rows = "CategoryID, Title, DeletedAt";
        $values = "?, ?, ?";
        $params = makeFilterParams([$categoryId, $title, $deletedAt]);
    
        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                return $addResult['insertID'];
            } else {
                throw new Exception('Failed to add category version. Please check if the data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to create category version: ' . $e->getMessage());
        }
    }
    
    public function fetchCategoryVersions($categoryId)
    {
        // Query to get the current version ID from the Categories table
        $currentVersionIdQuery = "SELECT 
                VersionID 
            FROM 
                Categories 
            WHERE 
                CategoryID = :categoryId
            LIMIT 1
        ";
        
        // Execute the query to get the current version ID
        $currentVersionIdResult = $this->supportSiteDb->query($currentVersionIdQuery, [':categoryId' => $categoryId]);
        $currentVersionId = $currentVersionIdResult[0]['VersionID'] ?? null;
        
        // Query to get the current version details from the CategoryVersions table
        $currentVersionQuery = "SELECT 
                cv.*, 
                (SELECT COUNT(*) 
                 FROM Articles a 
                 WHERE a.VersionID IN (
                     SELECT av.VersionID 
                     FROM ArticleVersions av 
                     WHERE av.ArticleID = a.ArticleID 
                     AND av.CategoryID = cv.CategoryID 
                     AND (av.DeletedAt IS NULL)
                 )
                ) AS ArticleCount
            FROM 
                CategoryVersions cv
            WHERE 
                cv.VersionID = :versionId
            LIMIT 1
        ";
        
        // Query to get the historical versions excluding the current version
        $historicalVersionsQuery = "SELECT 
                cv.*, 
                COUNT(DISTINCT av.ArticleID) AS ArticleCount 
            FROM 
                CategoryVersions cv
            LEFT JOIN 
                (SELECT 
                    av.ArticleID, 
                    av.CategoryID,
                    av.CreatedAt,
                    av.DeletedAt
                 FROM 
                    ArticleVersions av
                 INNER JOIN 
                    (SELECT 
                        ArticleID, 
                        MAX(CreatedAt) AS MaxCreatedAt
                     FROM 
                        ArticleVersions 
                     WHERE 
                        CategoryID = :categoryId1
                     GROUP BY 
                        ArticleID) AS LatestArticleVersions
                 ON 
                    av.ArticleID = LatestArticleVersions.ArticleID 
                    AND av.CreatedAt = LatestArticleVersions.MaxCreatedAt
                ) AS av 
            ON 
                cv.CategoryID = av.CategoryID 
                AND av.CreatedAt <= cv.CreatedAt
                AND (av.DeletedAt IS NULL OR av.DeletedAt > cv.CreatedAt)
            WHERE 
                cv.CategoryID = :categoryId2
                AND cv.VersionID != :currentVersionId
            GROUP BY 
                cv.VersionID
            ORDER BY 
                cv.CreatedAt DESC
        ";
    
        $params = [':categoryId1' => $categoryId, ':categoryId2' => $categoryId, ':currentVersionId' => $currentVersionId];
        
        // Fetch the current version details
        $currentVersion = $this->supportSiteDb->query($currentVersionQuery, [':versionId' => $currentVersionId]);
        
        // Fetch the historical versions excluding the current version
        $historicalVersions = $this->supportSiteDb->query($historicalVersionsQuery, $params);
        
        return [
            'currentVersion' => $currentVersion,
            'historicalVersions' => $historicalVersions
        ];
    }

    public function updateArticle($articleId, $newCategoryId, $newTitle, $newDescription, $newDetailedDescription, $newImgSrc)
    {
        // Fetch current article data to create a new version
        $currentArticle = $this->fetchArticleById($articleId);
        if (!$currentArticle || count($currentArticle) === 0) {
            throw new Exception('Article not found');
        }

        // Initialize an array to keep track of specific changes
        $specificChanges = [];

        // Detect specific changes
        if ($currentArticle[0]['CategoryID'] != $newCategoryId) {
            $specificChanges[] = self::ARTICLE_CATEGORY_MOVED;
        }
        if ($currentArticle[0]['Title'] != $newTitle) {
            $specificChanges[] = self::ARTICLE_TITLE_CHANGED;
        }
        if ($currentArticle[0]['ImgSrc'] != $newImgSrc) {
            $specificChanges[] = self::ARTICLE_IMG_SRC_CHANGED;
        }
        if ($currentArticle[0]['Description'] != $newDescription) {
            $specificChanges[] = self::ARTICLE_DESCRIPTION_CHANGED;
        }
        if ($currentArticle[0]['DetailedDescription'] != $newDetailedDescription) {
            $specificChanges[] = self::ARTICLE_DETAILED_DESCRIPTION_CHANGED;
        }


        // TODO: Fail call if completely empty (!empty($specificChanges) since there are no changes to be made...)

        // Create a new version of the article with the new detailed description
        $newArticleVersionId = $this->createArticleVersion(
            $articleId,
            $newCategoryId,
            $newTitle,
            $newDescription,
            $newDetailedDescription, // Pass the new Detailed Description
            $newImgSrc,
            $currentArticle[0]['StaffOnly'],
            $currentArticle[0]['Archived']
        );

        // Update the article with new data and new version number
        $slug = strtolower(str_replace(' ', '-', $newTitle));
        $params = makeFilterParams([$newCategoryId, $newTitle, $newDescription, $newDetailedDescription, $slug, $newImgSrc, $newArticleVersionId, $articleId]);
        try {
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "CategoryID = :categoryId, Title = :title, Description = :description, DetailedDescription = :detailedDescription, Slug = :slug, ImgSrc = :imgSrc, VersionID = :version",
                "ArticleID = :articleId",
                $params
            );
            $userId = $GLOBALS['user_id']; // user ID is stored from initial start

            if ($updateResult) {
                // Log the successful content update
                // $this->logArticleAction($userId, $articleId, self::ARTICLE_CONTENT_UPDATED);
                if (!empty($specificChanges)) {
                    $this->logArticleAction($userId, $newArticleVersionId, $specificChanges);
                }
                return $newArticleVersionId;
            } else {
                // Log the failed content update
                $this->logArticleAction($userId, $newArticleVersionId, self::ARTICLE_CONTENT_UPDATE_FAILED);
                throw new Exception("Failed to update article. Ensure common data integrity points and try again.");
            }
        } catch (Exception $e) {
            // Log the exception during content update
            $this->logArticleAction($userId, $newArticleVersionId, self::ARTICLE_CONTENT_UPDATE_FAILED);
            throw new PDOException('Failed to update article: ' . $e->getMessage());
        }
    }


    public function archiveArticle($articleId)
    {
        // Fetch current article data to create a new version
        $currentArticle = $this->fetchArticleById($articleId);
        if (!$currentArticle || count($currentArticle) === 0) {
            throw new Exception('Article not found');
        }

        // Toggle the Archive status for the new version
        $newStatus = $currentArticle[0]['Archived'] == 1 ? 0 : 1;

        // Create a new version of the article with the toggled StaffOnly status
        $newArticleVersionId = $this->createArticleVersion(
            $articleId,
            $currentArticle[0]['CategoryID'],
            $currentArticle[0]['Title'],
            $currentArticle[0]['Description'],
            $currentArticle[0]['DetailedDescription'],
            $currentArticle[0]['ImgSrc'],
            $currentArticle[0]['StaffOnly'],
            $newStatus // Pass the new StaffOnly status
        );

        // Prepare parameters for the update
        $params = makeFilterParams([$newStatus, $newArticleVersionId, $articleId]);

        try {
            // Update the current article's StaffOnly status
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "Archived = ?, VersionID = ?",
                "ArticleID = ?",
                $params
            );

            if ($updateResult) {

                $userId = $GLOBALS['user_id']; // user ID is stored from initial start

                // Log the action in ArticleActionLog
                $this->logArticleAction($userId, $newArticleVersionId, $newStatus == 1 ? [self::ARTICLE_ARCHIVED] : [self::ARTICLE_UNARCHIVED]);

                return $newArticleVersionId;
            } else {
                throw new Exception("Failed to archive article. Ensure data integrity and try again.");

            }
        } catch (Exception $e) {
            throw new PDOException('Failed to archive article: ' . $e->getMessage());
        }
    }


    public function toggleArticleStaffOnly($articleId)
    {
        // Fetch current article data to create a new version
        $currentArticle = $this->fetchArticleById($articleId);
        if (!$currentArticle || count($currentArticle) === 0) {
            throw new Exception('Article not found');
        }

        // // Get the latest version number and increment it for the new version
        // $latestVersion = $this->getLatestArticleVersion($articleId);
        // $newVersion = $latestVersion + 1;

        // Toggle the StaffOnly status for the new version
        $newStatus = $currentArticle[0]['StaffOnly'] == 1 ? 0 : 1;

        // Create a new version of the article with the toggled StaffOnly status
        $newArticleVersionId = $this->createArticleVersion(
            $articleId,
            $currentArticle[0]['CategoryID'],
            $currentArticle[0]['Title'],
            $currentArticle[0]['Description'],
            $currentArticle[0]['DetailedDescription'],
            $currentArticle[0]['ImgSrc'],
            $newStatus, // Pass the new StaffOnly status
            $currentArticle[0]['Archived'],
        );

        // Prepare parameters for the update
        $params = makeFilterParams([$newStatus, $newArticleVersionId, $articleId]);

        try {
            // Update the current article's StaffOnly status
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "StaffOnly = ?, VersionID = ?",
                "ArticleID = ?",
                $params
            );

            if ($updateResult) {

                $userId = $GLOBALS['user_id']; // user ID is stored from initial start

                // Log the action in ArticleActionLog
                $this->logArticleAction($userId, $newArticleVersionId, $newStatus == 1 ? [self::ARTICLE_SET_STAFF_ONLY] : [self::ARTICLE_SET_PUBLIC]);

                return $newArticleVersionId;
            } else {
                throw new Exception("Failed to toggle article's staff-only status. Ensure data integrity and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to toggle article\'s staff-only status: ' . $e->getMessage());
        }
    }

    public function logArticleAction($userId, $articleVersionId, $actionTypes)
    {
        // Encode the array of action types as a JSON string
        $actionTypesJson = json_encode($actionTypes);

        $query = "INSERT INTO ArticleActionLog (UserID, VersionID, ActionType) VALUES (:userId, :versionId, :actionType)";
        $params = [
            ':userId' => $userId,
            ':versionId' => $articleVersionId,
            ':actionType' => $actionTypesJson // Store the JSON string
        ];
        $this->supportSiteDb->query($query, $params);
    }


    public function createArticleVersion(
        $articleId,
        $categoryId = null,
        $title = null,
        $description = null,
        $detailedDescription = null,
        $imgSrc = null,
        $staffOnly = 0, // New optional parameter for StaffOnly status
        $archiveStatus = 0, // New optional parameter for archive status
        $isDeleted = 0 // New optional parameter for delete status
    ) {

        if($isDeleted === 3){
            $currentArticle = $this->fetchArticleById($articleId, 3);
            $isDeleted = 0;
        } else{
            $currentArticle = $this->fetchArticleById($articleId);
        }
        // Fetch current article data to use as defaults
        if (!$currentArticle || count($currentArticle) === 0) {
            throw new Exception('Article not found');
        }
    
        // Use existing values if new ones are not provided
        $categoryId = $categoryId ?? $currentArticle[0]['CategoryID'];
        $title = $title ?? $currentArticle[0]['Title'];
        $description = $description ?? $currentArticle[0]['Description'];
        $detailedDescription = $detailedDescription ?? $currentArticle[0]['DetailedDescription'];
        $imgSrc = $imgSrc ?? $currentArticle[0]['ImgSrc'];
        $staffOnly = $staffOnly ?? $currentArticle[0]['StaffOnly'];
        $archiveStatus = $archiveStatus ?? $currentArticle[0]['Archived'];
    
        $slug = strtolower(str_replace(' ', '-', $title));
    
        // Check if the new slug or title already exists and is not itself
        $existingArticle = $this->supportSiteDb->query(
            'SELECT * FROM Articles WHERE (Slug = :slug OR Title = :title) AND ArticleID != :articleId',
            [':slug' => $slug, ':title' => $title, ':articleId' => $articleId]
        );
    
        if (!empty($existingArticle)) {
            throw new Exception('This article title or slug is already taken!');
        }
    
        $table = "ArticleVersions";
        $rows = "ArticleID, CategoryID, Title, Description, DetailedDescription, Slug, ImgSrc, StaffOnly, Archived, DeletedAt";
        $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
        $deletedAt = $isDeleted ? date('Y-m-d H:i:s') : null; // Set DeletedAt timestamp if marked as deleted
        $params = makeFilterParams([
            $articleId,
            $categoryId,
            $title,
            $description,
            $detailedDescription,
            $slug,
            $imgSrc,
            $staffOnly,
            $archiveStatus,
            $deletedAt
        ]);
    
        try {
            $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
            if ($addResult) {
                $newVersionId = $this->supportSiteDb->lastInsertId(); // Get the last inserted ID
                return $newVersionId;
            } else {
                throw new Exception('Failed to add article version. Please check if the data integrity is correct and try again.');
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to create article version: ' . $e->getMessage());
        }
    }

    // public function createArticleVersion($articleId, $categoryId, $title, $description, $detailedDescription, $newVersion, $imgSrc = null,)
    // {
    //     $slug = strtolower(str_replace(' ', '-', $title));

    //     // Check if the new slug or title already exists and is not itself
    //     $slug = strtolower(str_replace(' ', '-', $title));
    //     $existingArticle = $this->supportSiteDb->query(
    //         'SELECT * FROM Articles WHERE (Slug = :slug OR Title = :title) AND ArticleID != :articleId',
    //         [':slug' => $slug, ':title' => $title, ':articleId' => $articleId]
    //     );

    //     if (!empty($existingArticle)) {
    //         throw new Exception('This article title or slug is already taken!');
    //     }

    //     $table = "ArticleVersions";
    //     $rows = "ArticleID, CategoryID, Title, Description, DetailedDescription, Slug, Version, ImgSrc";
    //     $values = "?, ?, ?, ?, ?, ?, ?, ?";
    //     $params = makeFilterParams([$articleId, $categoryId, $title, $description, $detailedDescription, $slug, $newVersion, $imgSrc]);

    //     try {
    //         $addResult = $this->supportSiteDb->insertData($table, $rows, $values, $params);
    //         if ($addResult) {
    //             return $addResult;
    //         } else {
    //             throw new Exception('Failed to add article version. Please check if the data integrity is correct and try again.');
    //         }
    //     } catch (Exception $e) {
    //         throw new PDOException('Failed to create article version: ' . $e->getMessage());
    //     }
    // }

    public function getLatestArticleVersion($articleId)
    {
        $query = "SELECT MAX(VersionID) as latestVersion FROM ArticleVersions WHERE ArticleID = :articleId";
        $params = [':articleId' => $articleId];
        $result = $this->supportSiteDb->query($query, $params);

        if ($result && count($result) > 0) {
            return $result[0]['latestVersion'] ?? 1; // If no versions exist, return 1 as the initial version
        } else {
            return 1; // If no versions exist, return 1 as the initial version
        }
    }

    //     public function getLatestArticleVersion($articleId)
// {
//     // Query to select the latest version based on the CreatedAt timestamp
//     $query = "SELECT * FROM ArticleVersions WHERE ArticleID = :articleId ORDER BY CreatedAt DESC LIMIT 1";
//     $params = [':articleId' => $articleId];
//     $result = $this->supportSiteDb->query($query, $params);

    //     if ($result && count($result) > 0) {
//         return $result[0]['VersionID']; // Return the VersionID of the latest entry
//     } else {
//         return 1; // Return 1 as the initial version if no entries are found
//     }
// }

    public function fetchArticleVersions($articleId)
    {
        $query = "SELECT * FROM ArticleVersions WHERE ArticleID = :articleId ORDER BY VersionID DESC";
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


    public function fetchArticleActionLogs($articleId)
    {
        // First, get all version IDs for the given article ID
        $versionQuery = "SELECT VersionID FROM ArticleVersions WHERE ArticleID = :articleId";
        $versionParams = [':articleId' => $articleId];
        $versionIds = $this->supportSiteDb->query($versionQuery, $versionParams);

        // Now, fetch all logs for the retrieved version IDs
        $logs = [];
        foreach ($versionIds as $version) {
            $logQuery = "SELECT * FROM ArticleActionLog WHERE VersionID = :versionId ORDER BY CreatedAt DESC";
            $logParams = [':versionId' => $version['VersionID']];
            $versionLogs = $this->supportSiteDb->query($logQuery, $logParams);

            // Fetch usernames from the main website database for each log entry
            foreach ($versionLogs as $key => $log) {
                $userQuery = "SELECT username FROM profiles WHERE user_id = :userId";
                $userParams = [':userId' => $log['UserID']];
                $userResult = $this->mainSiteDb->query($userQuery, $userParams);

                // Add the username to the log entry if found
                if ($userResult) {
                    $versionLogs[$key]['Username'] = $userResult[0]['username'];
                } else {
                    $versionLogs[$key]['Username'] = null; // or 'Unknown' or any placeholder you prefer
                }
            }

            $logs = array_merge($logs, $versionLogs);
        }

        return $logs;
    }

    private function fetchCategoryById($categoryId)
    {
        // Fetch the current version ID associated with the category
        $query = "SELECT VersionID FROM Categories WHERE CategoryID = :categoryId";
        $params = [':categoryId' => $categoryId];
        $result = $this->supportSiteDb->query($query, $params);
        $versionId = !empty($result) ? $result[0]['VersionID'] : null;
    
        if ($versionId) {
            // Fetch the latest category version details using the VersionID
            $versionQuery = "SELECT * FROM CategoryVersions WHERE VersionID = :versionId";
            $versionParams = [':versionId' => $versionId];
            $versionResult = $this->supportSiteDb->query($versionQuery, $versionParams);
            return !empty($versionResult) ? $versionResult[0] : null;
        }
    
        return null;
    }

    public function deleteCategory($categoryId, $userId)
    {
        // Fetch current category data to create a new version
        $currentCategory = $this->fetchCategoryById($categoryId);
        if (!$currentCategory) {
            throw new Exception('Category not found');
        }
    
        // Create a new version of the category marking it as deleted
        $newCategoryVersionId = $this->createCategoryVersion(
            $categoryId,
            $currentCategory['Title'],
            1 // Mark as deleted
        );
    
        // Prepare parameters for the update
        $params = makeFilterParams([$newCategoryVersionId, $categoryId]);
    
        try {
            // Update the current category to reference the new deleted version and set DeletedAt
            $updateResult = $this->supportSiteDb->updateData(
                "Categories",
                "VersionID = ?, DeletedAt = NOW()",
                "CategoryID = ?",
                $params
            );
    
            if ($updateResult) {
                // Log the action in CategoryActionLog
                $this->logCategoryAction($userId, $newCategoryVersionId, self::CATEGORY_DELETED);
                return true;
            } else {
                throw new Exception("Failed to delete category. Ensure data integrity and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to delete category: ' . $e->getMessage());
        }
    }

public function deleteArticle($articleId, $userId)
{
    // Fetch the current version ID from the Articles table
    $currentArticleData = $this->supportSiteDb->query(
        "SELECT VersionID FROM Articles WHERE ArticleID = :articleId",
        [':articleId' => $articleId]
    );

    if (empty($currentArticleData)) {
        throw new Exception('Article not found');
    }

    $currentVersionId = $currentArticleData[0]['VersionID'];

    // Fetch the current article version data from the ArticleVersions table
    $currentArticleVersion = $this->supportSiteDb->query(
        "SELECT * FROM ArticleVersions WHERE VersionID = :versionId",
        [':versionId' => $currentVersionId]
    );

    if (empty($currentArticleVersion)) {
        throw new Exception('Article version not found');
    }

    $articleVersionData = $currentArticleVersion[0];

    // Create a new version of the article marking it as deleted
    $newArticleVersionId = $this->createArticleVersion(
        $articleId,
        $articleVersionData['CategoryID'],
        $articleVersionData['Title'],
        $articleVersionData['Description'],
        $articleVersionData['DetailedDescription'],
        $articleVersionData['ImgSrc'],
        $articleVersionData['StaffOnly'],
        $articleVersionData['Archived'],
        1 // Mark as deleted
    );

    // Prepare parameters for the update
    $params = makeFilterParams([$newArticleVersionId, $articleId]);

    try {
        // Update the current article to reference the new deleted version
        $updateResult = $this->supportSiteDb->updateData(
            "Articles",
            "VersionID = ?, DeletedAt = NOW()",
            "ArticleID = ?",
            $params
        );

        if ($updateResult) {
            // Log the action in ArticleActionLog
            $this->logArticleAction($userId, $newArticleVersionId, self::ARTICLE_DELETED);
            return true;
        } else {
            throw new Exception("Failed to delete article. Ensure data integrity and try again.");
        }
    } catch (Exception $e) {
        throw new PDOException('Failed to delete article: ' . $e->getMessage());
    }
}


    // public function deleteCategory($categoryID)
    // {
    //     $table = 'Categories';
    //     $rows = 'CategoryID';
    //     $values = '?';
    //     $whereClause = 'WHERE ' . $rows . ' = ' . $values;
    //     // Delete the specified token
    //     return $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $categoryID, 'type' => PDO::PARAM_INT)));
    // }


    // public function deleteArticle($articleID)
    // {
    //     // First, delete associated versions
    //     $this->deleteArticleVersions($articleID);

    //     // Then, delete the article
    //     $table = 'Articles';
    //     $rows = 'ArticleID';
    //     $values = '?';
    //     $whereClause = 'WHERE ' . $rows . ' = ' . $values;

    //     try {
    //         $deleteResult = $this->supportSiteDb->deleteData($table, $whereClause, array(array('value' => $articleID, 'type' => PDO::PARAM_INT)));
    //         if ($deleteResult) {
    //             return $deleteResult;
    //         } else {
    //             throw new Exception('Failed to delete article. Please check if the article ID is correct and try again.');
    //         }
    //     } catch (Exception $e) {
    //         throw new PDOException('Failed to delete article: ' . $e->getMessage());
    //     }
    // }


    public function logCategoryAction($userId, $articleVersionId, $actionType)
    {
        $query = "INSERT INTO CategoryActionLog (UserID, VersionID, ActionType) VALUES (:userId, :versionId, :actionType)";
        $params = [
            ':userId' => $userId,
            ':versionId' => $articleVersionId,
            ':actionType' => $actionType
        ];
        $this->supportSiteDb->query($query, $params);
    }

    public function restoreArticle($articleId, $userId)
    {
        // Fetch the current version ID from the Articles table
        $currentArticleData = $this->supportSiteDb->query(
            "SELECT VersionID FROM Articles WHERE ArticleID = :articleId",
            [':articleId' => $articleId]
        );
    
        if (empty($currentArticleData)) {
            throw new Exception('Article not found');
        }
    
        $currentVersionId = $currentArticleData[0]['VersionID'];
    
        // Fetch the current article version data from the ArticleVersions table
        $currentArticleVersion = $this->supportSiteDb->query(
            "SELECT * FROM ArticleVersions WHERE VersionID = :versionId",
            [':versionId' => $currentVersionId]
        );
    
        if (empty($currentArticleVersion)) {
            throw new Exception('Article version not found');
        }
    
        $articleVersionData = $currentArticleVersion[0];
    
        // Create a new version of the article marking it as not deleted
        $newArticleVersionId = $this->createArticleVersion(
            $articleId,
            $articleVersionData['CategoryID'],
            $articleVersionData['Title'],
            $articleVersionData['Description'],
            $articleVersionData['DetailedDescription'],
            $articleVersionData['ImgSrc'],
            $articleVersionData['StaffOnly'],
            $articleVersionData['Archived'],
            3 // Special flag to handle restoring
        );
    
        try {
            // Update the current article to reference the new restored version and set DeletedAt to NULL
            $params = makeFilterParams([$newArticleVersionId, $articleId]);
            $updateResult = $this->supportSiteDb->updateData(
                "Articles",
                "VersionID = ?, DeletedAt = NULL",
                "ArticleID = ?",
                $params
            );
    
            if ($updateResult) {
                // Log the action in ArticleActionLog
                $this->logArticleAction($userId, $newArticleVersionId, self::ARTICLE_RESTORED);
                return $newArticleVersionId;
            } else {
                throw new Exception("Failed to restore article. Ensure data integrity and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to restore article: ' . $e->getMessage());
        }
    }
    
    
    public function restoreCategory($categoryId, $userId)
    {
        // Fetch current category data to create a new version
        $currentCategory = $this->fetchCategoryById($categoryId);
        if (!$currentCategory) {
            throw new Exception('Category not found');
        }
    
        // Create a new version of the category marking it as restored (not deleted)
        $newCategoryVersionId = $this->createCategoryVersion(
            $categoryId,
            $currentCategory['Title'],
            0 // Mark as not deleted
        );
    
        // Prepare parameters for the update
        $params = makeFilterParams([$newCategoryVersionId, $categoryId]);
    
        try {
            // Update the current category to reference the new restored version and set DeletedAt to NULL
            $updateResult = $this->supportSiteDb->updateData(
                "Categories",
                "VersionID = ?, DeletedAt = NULL",
                "CategoryID = ?",
                $params
            );
    
            if ($updateResult) {
                // Log the action in CategoryActionLog
                $this->logCategoryAction($userId, $newCategoryVersionId, self::CATEGORY_RESTORED);
                return true;
            } else {
                throw new Exception("Failed to restore category. Ensure data integrity and try again.");
            }
        } catch (Exception $e) {
            throw new PDOException('Failed to restore category: ' . $e->getMessage());
        }
    }
    

    public function fetchDeletedCategories()
    {
        // Query to fetch all categories marked as deleted
        $query = "SELECT CategoryID, VersionID FROM Categories WHERE DeletedAt IS NOT NULL";
        $deletedCategories = $this->supportSiteDb->query($query);
    
        // Prepare an array to hold the results
        $deletedCategoryVersions = [];
    
        if (!empty($deletedCategories)) {
            foreach ($deletedCategories as $category) {
                $versionId = intval($category['VersionID']);
                $categoryId = intval($category['CategoryID']);
    
                // Prepare a query to fetch the deleted versions from CategoryVersions
                $versionQuery = "SELECT * FROM CategoryVersions WHERE VersionID = :versionId AND CategoryID = :categoryId AND DeletedAt IS NOT NULL";
                $params = [
                    ':versionId' => $versionId,
                    ':categoryId' => $categoryId
                ];
    
                $result = $this->supportSiteDb->query($versionQuery, $params);
                if (!empty($result)) {
                    $deletedCategoryVersions[] = $result[0]; // Store the matching version
                }
            }
        }
    
        return $deletedCategoryVersions;
    }
    

    public function fetchDeletedArticles()
    {
        // Query to fetch all articles marked as deleted
        $query = "SELECT ArticleID, VersionID FROM Articles";
        $deletedArticles = $this->supportSiteDb->query($query);
    
        // Prepare an array to hold the results
        $deletedArticleVersions = [];
    
        if (!empty($deletedArticles)) {
            foreach ($deletedArticles as $article) {
                $versionId = intval($article['VersionID']);
                $articleId = intval($article['ArticleID']);
    
                // Prepare a query to fetch the non-deleted versions from ArticleVersions
                $versionQuery = "SELECT * FROM ArticleVersions WHERE VersionID = :versionId AND ArticleID = :articleId AND DeletedAt IS NOT NULL";
                $params = [
                    ':versionId' => $versionId,
                    ':articleId' => $articleId
                ];
    
                $result = $this->supportSiteDb->query($versionQuery, $params);
                if (!empty($result)) {
                    $deletedArticleVersions[] = $result[0]; // Store the matching version
                }
            }
        }
    
        return $deletedArticleVersions;
    }

    public function fetchAllArticles()
    {
        $query = "SELECT * FROM Articles WHERE DeletedAt IS NULL ORDER BY CreatedAt DESC";
        return $this->supportSiteDb->query($query);
    }

    
}

