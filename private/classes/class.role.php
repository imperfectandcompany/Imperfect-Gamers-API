<?php
// classes/class.role.php

class Role
{
    private $db;

    public function __construct($dbConnection)
    {
        $this->db = $dbConnection;
    }

    public function getAllRoles()
    {
        $query = 'SELECT * FROM roles';
        $roles = $this->db->query($query);

        foreach ($roles as &$role) {
            $role['permissions'] = $this->getRolePermissions($role['id']);
            $role['variables'] = $this->getRoleVariables($role['id']);
            $role['inherits'] = $this->getRoleInheritance($role['id']);
        }

        return $roles;
    }

    public function createRole($name, $permissions = [], $variables = [], $inheritsFrom = [])
    {
        $this->db->beginTransaction();
        try {
            // Insert into roles table
            $query = 'INSERT INTO roles (name) VALUES (:name)';
            $params = [':name' => $name];
            $this->db->query($query, $params);
            $roleId = $this->db->lastInsertId();

            // Insert permissions
            foreach ($permissions as $permission) {
                $this->addPermissionToRole($roleId, $permission);
            }

            // Insert variables
            foreach ($variables as $varName => $varValue) {
                $this->addVariableToRole($roleId, $varName, $varValue);
            }

            // Insert inheritance
            foreach ($inheritsFrom as $parentRoleName) {
                $parentRoleId = $this->getRoleIdByName($parentRoleName);
                if ($parentRoleId) {
                    $this->addRoleInheritance($roleId, $parentRoleId);
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getRoleById($roleId)
    {
        $query = 'SELECT * FROM roles WHERE id = :id';
        $params = [':id' => $roleId];
        $role = $this->db->query($query, $params);

        if ($role) {
            $role = $role[0];
            $role['permissions'] = $this->getRolePermissions($roleId);
            $role['variables'] = $this->getRoleVariables($roleId);
            $role['inherits'] = $this->getRoleInheritance($roleId);
            return $role;
        }

        return null;
    }

    public function updateRole($roleId, $name = null, $permissions = null, $variables = null, $inheritsFrom = null)
    {
        $this->db->beginTransaction();
        try {
            // Update role name if provided
            if ($name !== null) {
                $query = 'UPDATE roles SET name = :name WHERE id = :id';
                $params = [':name' => $name, ':id' => $roleId];
                $this->db->query($query, $params);
            }

            // Update permissions if provided
            if ($permissions !== null) {
                // Delete existing permissions
                $query = 'DELETE FROM role_permissions WHERE role_id = :role_id';
                $params = [':role_id' => $roleId];
                $this->db->query($query, $params);

                // Insert new permissions
                foreach ($permissions as $permission) {
                    $this->addPermissionToRole($roleId, $permission);
                }
            }

            // Update variables if provided
            if ($variables !== null) {
                // Delete existing variables
                $query = 'DELETE FROM role_variables WHERE role_id = :role_id';
                $params = [':role_id' => $roleId];
                $this->db->query($query, $params);

                // Insert new variables
                foreach ($variables as $varName => $varValue) {
                    $this->addVariableToRole($roleId, $varName, $varValue);
                }
            }

            // Update inheritance if provided
            if ($inheritsFrom !== null) {
                // Delete existing inheritance
                $query = 'DELETE FROM role_inheritance WHERE role_id = :role_id';
                $params = [':role_id' => $roleId];
                $this->db->query($query, $params);

                // Insert new inheritance
                foreach ($inheritsFrom as $parentRoleName) {
                    $parentRoleId = $this->getRoleIdByName($parentRoleName);
                    if ($parentRoleId) {
                        $this->addRoleInheritance($roleId, $parentRoleId);
                    }
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteRole($roleId)
    {
        $query = 'DELETE FROM roles WHERE id = :id';
        $params = [':id' => $roleId];
        $this->db->query($query, $params);
    }

    // Helper methods...

    private function getRolePermissions($roleId)
    {
        $query = 'SELECT permission FROM role_permissions WHERE role_id = :role_id';
        $params = [':role_id' => $roleId];
        $result = $this->db->query($query, $params);
        return array_column($result, 'permission');
    }

    private function getRoleVariables($roleId)
    {
        $query = 'SELECT variable_name, variable_value FROM role_variables WHERE role_id = :role_id';
        $params = [':role_id' => $roleId];
        $result = $this->db->query($query, $params);
        $variables = [];
        foreach ($result as $row) {
            $variables[$row['variable_name']] = $row['variable_value'];
        }
        return $variables;
    }

    private function getRoleInheritance($roleId)
    {
        $query = 'SELECT parent_role_id FROM role_inheritance WHERE role_id = :role_id';
        $params = [':role_id' => $roleId];
        $result = $this->db->query($query, $params);
        $inherits = [];
        foreach ($result as $row) {
            $parentRoleName = $this->getRoleNameById($row['parent_role_id']);
            if ($parentRoleName) {
                $inherits[] = $parentRoleName;
            }
        }
        return $inherits;
    }

    private function addPermissionToRole($roleId, $permission)
    {
        $query = 'INSERT INTO role_permissions (role_id, permission) VALUES (:role_id, :permission)';
        $params = [':role_id' => $roleId, ':permission' => $permission];
        $this->db->query($query, $params);
    }

    private function addVariableToRole($roleId, $variableName, $variableValue)
    {
        $query = 'INSERT INTO role_variables (role_id, variable_name, variable_value) VALUES (:role_id, :variable_name, :variable_value)';
        $params = [
            ':role_id' => $roleId,
            ':variable_name' => $variableName,
            ':variable_value' => $variableValue
        ];
        $this->db->query($query, $params);
    }

    private function addRoleInheritance($roleId, $parentRoleId)
    {
        $query = 'INSERT INTO role_inheritance (role_id, parent_role_id) VALUES (:role_id, :parent_role_id)';
        $params = [':role_id' => $roleId, ':parent_role_id' => $parentRoleId];
        $this->db->query($query, $params);
    }

    private function getRoleIdByName($roleName)
    {
        $query = 'SELECT id FROM roles WHERE name = :name';
        $params = [':name' => $roleName];
        $result = $this->db->query($query, $params);
        return $result[0]['id'] ?? null;
    }

    private function getRoleNameById($roleId)
    {
        $query = 'SELECT name FROM roles WHERE id = :id';
        $params = [':id' => $roleId];
        $result = $this->db->query($query, $params);
        return $result[0]['name'] ?? null;
    }
}
