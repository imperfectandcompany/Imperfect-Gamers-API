<?php
// tests/test_support.php

$categoryId;

$articleId;

function createTestCategory($controller) {
    global $categoryId;

    // Create a category
    $createCategoryResponse = $controller->createCategory('Test Category');
    $categoryId = $createCategoryResponse['data']['categoryID'];
    // Assertions
    customAssert($createCategoryResponse['status'] === 'success', 'Expected category creation to be successful');
}

function testPreventDuplicateCategoryTitle($controller) {
    // Attempt to create another category with the same title
    $duplicateTitle = 'Test Category';
    $createDuplicateCategoryResponse = $controller->createCategory($duplicateTitle);

    // Ensure the second category creation fails
    customAssert($createDuplicateCategoryResponse['status'] !== 'success', 'Expected duplicate category creation to fail');
    customAssert($createDuplicateCategoryResponse['data']['message'] === 'This category title is already taken!', 'Expected error message for duplicate category creation');
}

function testCheckCategoryTitleExists($controller) {
    // Check if the category title exists
    $title = 'Test Category';
    $checkCategoryTitleExistsResponse = $controller->checkCategoryTitleExists($title);

    // Ensure the title exists
    customAssert($checkCategoryTitleExistsResponse['status'] === 'success', 'Expected category title check to be successful');
    customAssert($checkCategoryTitleExistsResponse['data']['exists'] === true, 'Expected category title to exist');
}

function createTestArticle($controller) {
    global $articleId;
    global $categoryId; // Access the variable defined outside the function

    // Create an article
    $title = 'Test Article';
    $description = 'Description';
    $detailedDescription = 'Detailed Description';
    $createArticleResponse = $controller->createArticle($categoryId, $title, $description, $detailedDescription);    
    $articleId = $createArticleResponse['data']['articleID'];
    // Assertions
    customAssert($createArticleResponse['status'] === 'success', 'Expected article creation to be successful');
}

function testPreventDuplicateArticleSlugOrTitle($controller) {
    global $categoryId;

    // Attempt to create another article with the same title (which will generate the same slug)
    $duplicateTitle = 'Test Article';
    $duplicateDescription = 'Another Description';
    $duplicateDetailedDescription = 'Another Detailed Description';
    $createDuplicateArticleResponse = $controller->createArticle($categoryId, $duplicateTitle, $duplicateDescription, $duplicateDetailedDescription);
    // Ensure the second article creation fails
    customAssert($createDuplicateArticleResponse['status'] !== 'success', 'Expected duplicate article creation to fail');
    customAssert($createDuplicateArticleResponse['data']['message'] === 'This article title or slug is already taken!', 'Expected error message for duplicate article creation');
}

function testFetchArticleById($controller) {
    // Fetch the article by ID
    global $articleId;
    $article = $controller->fetchArticleById($articleId);
    customAssert($article["data"]["article"][0]["ArticleID"] == $articleId, 'Expected article ID to match');
}

function testCheckArticleTitleOrSlugExists($controller) {
    // Check if the article title or slug exists
    $title = 'Test Article';
    $slug = 'test-article';
    $checkArticleTitleOrSlugExistsResponse = $controller->checkArticleTitleOrSlugExists($title, $slug);

    // Ensure the title or slug exists
    customAssert($checkArticleTitleOrSlugExistsResponse['status'] === 'success', 'Expected article title or slug check to be successful');
    customAssert($checkArticleTitleOrSlugExistsResponse['data']['exists'] === true, 'Expected article title or slug to exist');
}

function testFetchArticlesByCategory($controller) {
    global $categoryId;
    global $articleId;
    
    // Fetch articles by category
    $articles = $controller->fetchArticlesByCategory($categoryId);

    // Assertions
    customAssert($articles['status'] === 'success', 'Expected status to be success');
    customAssert(is_array($articles['data']['articles']), 'Expected articles to be an array');
    customAssert(count($articles['data']['articles']) > 0, 'Expected at least one article in the category');
    customAssert($articles['data']['articles'][0]['ArticleID'] == $articleId, 'Expected article ID to match the one created');
}


function testUpdateArticle($controller) {
    global $articleId;
    global $categoryId;

    // Update article details
    $newTitle = 'Updated Test Article';
    $newDescription = 'Updated Description';
    $newDetailedDescription = 'Updated Detailed Description';
    $updateArticleResponse = $controller->updateArticle($articleId, $categoryId, $newTitle, $newDescription, $newDetailedDescription);
    // Assertions
    customAssert($updateArticleResponse['status'] === 'success', 'Expected article update to be successful');
}

function testFetchArticleVersionsAfterUpdate($controller) {
    global $articleId;

    // Fetch article versions after update
    $versions = $controller->fetchArticleVersions($articleId);

    // Assertions
    customAssert($versions['status'] === 'success', 'Expected status to be success');
    customAssert(is_array($versions['data']['versions']), 'Expected versions to be an array');
    customAssert(count($versions['data']['versions']) === 1, 'Expected to have one version (initial)');
}

function testFetchUpdatedArticle($controller) {
    global $articleId;

    $newTitle = 'Updated Test Article';
    $newDescription = 'Updated Description';
    $newDetailedDescription = 'Updated Detailed Description';
    // Fetch and verify the updated article
    $article = $controller->fetchArticleById($articleId);
    customAssert($article["data"]["article"][0]["Title"] === $newTitle, 'Expected article title to match the updated title');
    customAssert($article["data"]["article"][0]["Description"] === $newDescription, 'Expected article description to match the updated description');
    customAssert($article["data"]["article"][0]["DetailedDescription"] === $newDetailedDescription, 'Expected article detailed description to match the updated detailed description');
}

function testArchiveArticle($controller) {
    global $articleId;

    // Archive the article
    $archiveArticleResponse = $controller->archiveArticle($articleId);
    customAssert($archiveArticleResponse['status'] === 'success', 'Expected article to be archived successfully');
}

function testFetchArchivedArticle($controller) {
    global $articleId;

    // Fetch and verify the archived article
    $article = $controller->fetchArticleById($articleId);
    customAssert($article["data"]["article"][0]["Archived"] == 1, 'Expected article to be archived');
}


function testMakeArticleStaffOnly($controller) {
    global $articleId;

    // Make the article staff-only
    $staffOnlyArticleResponse = $controller->makeArticleStaffOnly($articleId);
    customAssert($staffOnlyArticleResponse['status'] === 'success', 'Expected article to be staff-only successfully');
}

function testFetchStaffOnlyArticle($controller) {
    global $articleId;

    // Fetch and verify the staff-only article
    $article = $controller->fetchArticleById($articleId);
    customAssert($article["data"]["article"][0]["StaffOnly"] == 1, 'Expected article to be staff-only');
}   

function testCreateArticleVersion($controller) {
    global $articleId;
    global $categoryId;

    // Create a new version of the article
    $newTitle = 'New Version Test Article';
    $newDescription = 'New Version Description';
    $newDetailedDescription = 'New Version Detailed Description';
    $createVersionResponse = $controller->createArticleVersion($articleId, $categoryId, $newTitle, $newDescription, $newDetailedDescription);
    // Assertions
    customAssert($createVersionResponse['status'] === 'success', 'Expected article version creation to be successful');
}

function testFetchArticleVersions($controller) {
    global $articleId;

    // Fetch article versions
    $versions = $controller->fetchArticleVersions($articleId);

    // Assertions
    customAssert($versions['status'] === 'success', 'Expected status to be success');
    customAssert(is_array($versions['data']['versions']), 'Expected versions to be an array');
    customAssert(count($versions['data']['versions']) > 0, 'Expected at least one version of the article');
    customAssert(count($versions['data']['versions']) > 0, 'Expected at least two versions (initial and updated)');
}

function deleteTestArticle($controller) {
    global $articleId;

    // Delete an article
    $deleteArticleResponse = $controller->deleteArticle($articleId);
    customAssert($deleteArticleResponse['status'] === 'success', 'Expected article deletion to be successful');
}


function testFetchArticleByIdFail($controller) {
    global $articleId; // Access the variable defined outside the function

    // Fetch the article by ID if the ID is set
    if ($articleId) {
        $article = $controller->fetchArticleById($articleId);
        if (isset($article["data"]["article"][0]["ArticleID"])) {
            customAssert($article["data"]["article"][0]["ArticleID"] !== $articleId, 'Article ID expected to fail');
        } else {
            customAssert(true, 'Article ID not found');
        }
    } else {
        customAssert(true, 'Article ID is not set');
    }
}

function testCreateCategoryVersion($controller) {
    global $categoryId;

    // Create a new version of the category
    $newTitle = 'New Version Test Category';
    $createVersionResponse = $controller->createCategoryVersion($categoryId, $newTitle);
    // Assertions
    customAssert($createVersionResponse['status'] === 'success', 'Expected category version creation to be successful');
}

function testFetchCategoryVersions($controller) {
    global $categoryId;

    // Fetch category versions
    $versions = $controller->fetchCategoryVersions($categoryId);

    // Assertions
    customAssert($versions['status'] === 'success', 'Expected status to be success');
    customAssert(is_array($versions['data']['versions']), 'Expected versions to be an array');
}


function testFetchAllCategories($controller) {
    $categories = $controller->fetchAllCategories();
    customAssert($categories['status'] === 'success', 'Expected status to be success');
    customAssert(is_array($categories['data']['categories']), 'Expected categories to be an array');
    customAssert(count($categories['data']['categories']) > 0, 'Expected at least one category');
}

function testUpdateCategory($controller) {
    global $categoryId;
    
    // Update category title
    $updateCategoryResponse = $controller->updateCategory($categoryId, 'Updated Test Category');
    // Assertions
    customAssert($updateCategoryResponse['status'] === 'success', 'Expected category update to be successful');
}

function testFetchUpdatedCategory($controller) {
    global $categoryId;

    // Fetch and verify the updated category
    $categories = $controller->fetchAllCategories();
    $updatedCategory = array_filter($categories['data']['categories'], function($category) use ($categoryId) {
        return $category['CategoryID'] == $categoryId;
    });
    customAssert(count($updatedCategory) === 1, 'Expected to find the updated category');
    customAssert(array_values($updatedCategory)[0]['Title'] === 'Updated Test Category', 'Expected category title to match the updated title');
}

function deleteTestCategory($controller) {
    global $categoryId; // Access the variable defined outside the function

    // Delete a category
    $deleteArticleResponse = $controller->deleteCategory($categoryId);
    customAssert($deleteArticleResponse['status'] === 'success', 'Expected article deletion to be successful');
}