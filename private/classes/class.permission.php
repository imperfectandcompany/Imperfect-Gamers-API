<?php
// classes/class.permission.php

class Permission
{
    private $db;

    public function __construct($dbConnection)
    {
        $this->db = $dbConnection;
    }

    public function getAllPermissions()
    {
        $query = 'SELECT * FROM permissions';
        $permissions = $this->db->query($query);
        return $permissions;
    }

    public function createPermission($name)
    {
        // Check if the permission already exists
        $query = 'SELECT id FROM permissions WHERE name = :name';
        $params = [':name' => $name];
        $result = $this->db->query($query, $params);

        if ($result && count($result) > 0) {
            throw new Exception('Permission already exists');
        }

        // Insert the new permission
        $query = 'INSERT INTO permissions (name) VALUES (:name)';
        $params = [':name' => $name];
        $this->db->query($query, $params);
    }

    public function getPermissionById($permissionId)
    {
        $query = 'SELECT * FROM permissions WHERE id = :id';
        $params = [':id' => $permissionId];
        $permission = $this->db->query($query, $params);

        if ($permission && count($permission) > 0) {
            return $permission[0];
        }

        return null;
    }

    public function updatePermission($permissionId, $name)
    {
        // Check if the permission exists
        $query = 'SELECT id FROM permissions WHERE id = :id';
        $params = [':id' => $permissionId];
        $result = $this->db->query($query, $params);

        if (!$result || count($result) === 0) {
            throw new Exception('Permission not found');
        }

        // Update the permission
        $query = 'UPDATE permissions SET name = :name WHERE id = :id';
        $params = [':name' => $name, ':id' => $permissionId];
        $this->db->query($query, $params);
    }

    public function deletePermission($permissionId)
    {
        // Check if the permission exists
        $query = 'SELECT id FROM permissions WHERE id = :id';
        $params = [':id' => $permissionId];
        $result = $this->db->query($query, $params);

        if (!$result || count($result) === 0) {
            throw new Exception('Permission not found');
        }

        // Delete the permission
        $query = 'DELETE FROM permissions WHERE id = :id';
        $params = [':id' => $permissionId];
        $this->db->query($query, $params);
    }
}
