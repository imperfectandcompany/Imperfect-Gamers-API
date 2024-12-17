<?php
// controllers/RolesController.php

include ($GLOBALS['config']['private_folder'] . '/classes/class.role.php');

class RolesController
{
    private $db;
    private $roleService;
    private $logger;

    public function __construct($dbManager, $logger)
    {
        $this->db = $dbManager->getConnection('default');
        $this->roleService = new Role($this->db);
        $this->logger = $logger;
    }

    public function getAllRoles()
    {
        try {
            $roles = $this->roleService->getAllRoles();
            ResponseHandler::sendResponse('success', ['roles' => $roles], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to fetch roles', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch roles'], ERROR_INTERNAL_SERVER);
        }
    }

    public function createRole()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $roleName = $data['name'] ?? null;
        $permissions = $data['permissions'] ?? [];
        $variables = $data['variables'] ?? [];
        $inheritsFrom = $data['inherits'] ?? [];

        if (empty($roleName)) {
            ResponseHandler::sendResponse('error', ['message' => 'Role name is required'], ERROR_BAD_REQUEST);
            return;
        }

        try {
            $this->roleService->createRole($roleName, $permissions, $variables, $inheritsFrom);
            ResponseHandler::sendResponse('success', ['message' => 'Role created successfully'], SUCCESS_CREATED);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to create role', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to create role'], ERROR_INTERNAL_SERVER);
        }
    }

    public function getRoleById($roleId)
    {
        try {
            $role = $this->roleService->getRoleById($roleId);
            if ($role) {
                ResponseHandler::sendResponse('success', ['role' => $role], SUCCESS_OK);
            } else {
                ResponseHandler::sendResponse('error', ['message' => 'Role not found'], ERROR_NOT_FOUND);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to fetch role', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to fetch role'], ERROR_INTERNAL_SERVER);
        }
    }

    public function updateRole($roleId)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $roleName = $data['name'] ?? null;
        $permissions = $data['permissions'] ?? null; // Allow null to indicate no change
        $variables = $data['variables'] ?? null;     // Allow null to indicate no change
        $inheritsFrom = $data['inherits'] ?? null;   // Allow null to indicate no change

        try {
            $this->roleService->updateRole($roleId, $roleName, $permissions, $variables, $inheritsFrom);
            ResponseHandler::sendResponse('success', ['message' => 'Role updated successfully'], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to update role', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to update role'], ERROR_INTERNAL_SERVER);
        }
    }

    public function deleteRole($roleId)
    {
        try {
            $this->roleService->deleteRole($roleId);
            ResponseHandler::sendResponse('success', ['message' => 'Role deleted successfully'], SUCCESS_OK);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to delete role', ['exception' => $e]);
            ResponseHandler::sendResponse('error', ['message' => 'Failed to delete role'], ERROR_INTERNAL_SERVER);
        }
    }
}
