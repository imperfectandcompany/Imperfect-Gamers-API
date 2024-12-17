<?php
// controllers/PermissionsController.php

include ($GLOBALS['config']['private_folder'] . '/classes/class.permission.php');

class PermissionsController
{
    private $db;
    private $permissionService;
    private $logger;

    public function __construct($dbManager, $logger)
    {
        $this->db = $dbManager->getConnection('default');
        $this->permissionService = new Permission($this->db);
        $this->logger = $logger;
    }

    public function getAllPermissions()
    {
        try {
            $permissions = $this->permissionService->getAllPermissions();
            ResponseHandler::sendResponse('success', ['permissions' => $permissions], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to fetch permissions', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch permissions'], ERROR_INTERNAL_SERVER);
        }
    }

    public function createPermission()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $permissionName = $data['name'] ?? null;

        if (empty($permissionName)) {
            ResponseHandler::sendResponse('error', ['message' => 'Permission name is required'], ERROR_BAD_REQUEST);
            return;
        }

        try {
            $this->permissionService->createPermission($permissionName);
            ResponseHandler::sendResponse('success', ['message' => 'Permission created successfully'], SUCCESS_CREATED);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to create permission', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to create permission'], ERROR_INTERNAL_SERVER);
        }
    }

    public function getPermissionById($permissionId)
    {
        try {
            $permission = $this->permissionService->getPermissionById($permissionId);
            if ($permission) {
                ResponseHandler::sendResponse('success', ['permission' => $permission], SUCCESS_OK);
            } else {
                ResponseHandler::sendResponse('error', ['message' => 'Permission not found'], ERROR_NOT_FOUND);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to fetch permission', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch permission'], ERROR_INTERNAL_SERVER);
        }
    }

    public function updatePermission($permissionId)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $permissionName = $data['name'] ?? null;

        if (empty($permissionName)) {
            ResponseHandler::sendResponse('error', ['message' => 'Permission name is required'], ERROR_BAD_REQUEST);
            return;
        }

        try {
            $this->permissionService->updatePermission($permissionId, $permissionName);
            ResponseHandler::sendResponse('success', ['message' => 'Permission updated successfully'], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to update permission', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to update permission'], ERROR_INTERNAL_SERVER);
        }
    }

    public function deletePermission($permissionId)
    {
        try {
            $this->permissionService->deletePermission($permissionId);
            ResponseHandler::sendResponse('success', ['message' => 'Permission deleted successfully'], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to delete permission', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to delete permission'], ERROR_INTERNAL_SERVER);
        }
    }
}
