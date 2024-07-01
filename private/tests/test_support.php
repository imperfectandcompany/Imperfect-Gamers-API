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

function createTestArticle($controller) {
    global $articleId;
    global $categoryId; // Access the variable defined outside the function

    // Create an article
    $createArticleResponse = $controller->createArticle($categoryId, 'Test Article', 'Description', 'Detailed Description');
    $articleId = $createArticleResponse['data']['articleID'];
    // Assertions
    customAssert($createArticleResponse['status'] === 'success', 'Expected article creation to be successful');
}

function testFetchArticleById($controller) {
    // Fetch the article by ID
    global $articleId;
    $article = $controller->fetchArticleById($articleId);
    customAssert($article["data"]["article"][0]["ArticleID"] == $articleId, 'Expected article ID to match');
}

function deleteTestArticle($controller) {
    global $articleId;

    // Delete an article
    $deleteArticleResponse = $controller->deleteArticle($articleId);
    customAssert($deleteArticleResponse['status'] === 'success', 'Expected article deletion to be successful');
}

function testFetchAllCategories($controller) {
    $categories = $controller->fetchAllCategories();
    customAssert($categories['status'] === 'success', 'Expected status to be success');
    customAssert(is_array($categories['data']['categories']), 'Expected categories to be an array');
    customAssert(count($categories['data']['categories']) > 0, 'Expected at least one category');
}

function deleteTestCategory($controller) {
    global $categoryId; // Access the variable defined outside the function

    // Delete a category
    $deleteArticleResponse = $controller->deleteCategory($categoryId);
    customAssert($deleteArticleResponse['status'] === 'success', 'Expected article deletion to be successful');
}