<?php
// PermissionManager.php

class PermissionManager {

    private static $permissions = []; // Registered permissions
    private static $roles = [];       // Registered roles

    public static function loadPermissionsFromDatabase($dbConnection) {
        $query = 'SELECT name FROM permissions';
        if($dbConnection !== null){
            $result = $dbConnection->query($query);
            foreach ($result as $row) {
                self::registerPermission($row['name']);
            }
        } else {
            die('Database connection is null.');
        }
    }

public static function loadRolesFromDatabase($dbConnection) {
    if($dbConnection !== null){
        // Load roles
        $query = 'SELECT id, name FROM roles';
        $rolesResult = $dbConnection->query($query);
        foreach ($rolesResult as $roleRow) {
            $roleId = $roleRow['id'];
            $roleName = $roleRow['name'];
            // Load variables for the role from the role_variables table
            $variablesQuery = 'SELECT variable_name, variable_value FROM role_variables WHERE role_id = :role_id';
            $variablesResult = $dbConnection->query($variablesQuery, [':role_id' => $roleId]);
            $variables = [];
            foreach ($variablesResult as $varRow) {
                $variables[$varRow['variable_name']] = $varRow['variable_value'];
            }

            // Load permissions for the role
            $permissionsQuery = 'SELECT permission FROM role_permissions WHERE role_id = :role_id';
            $permissionsResult = $dbConnection->query($permissionsQuery, [':role_id' => $roleId]);
            $permissions = array_column($permissionsResult, 'permission');

            // Load inherited roles
            $inheritsQuery = 'SELECT parent_role_id FROM role_inheritance WHERE role_id = :role_id';
            $inheritsResult = $dbConnection->query($inheritsQuery, [':role_id' => $roleId]);
            $inheritsFrom = [];
            foreach ($inheritsResult as $inheritRow) {
                $parentRoleId = $inheritRow['parent_role_id'];
                // Fetch parent role name
                $parentRoleNameQuery = 'SELECT name FROM roles WHERE id = :id';
                $parentRoleNameResult = $dbConnection->query($parentRoleNameQuery, [':id' => $parentRoleId]);
                if ($parentRoleNameResult) {
                    $inheritsFrom[] = $parentRoleNameResult[0]['name'];
                }
            }

            self::registerRole($roleName, $permissions, $variables ?? [], $inheritsFrom);
        }
    } else {
        die('Database connection is null.');
    }
}


    /**
     * Registers a new permission.
     *
     * @param string $name The name of the permission (e.g., 'support.articles.create').
     */
    public static function registerPermission($name) {
        self::$permissions[$name] = $name;
    }

    /**
     * Registers a new role with permissions, variables, and inheritance.
     *
     * @param string $name The name of the role.
     * @param array $permissions An array of permission nodes (can include wildcards, negations, exceptions).
     * @param array $variables Optional array of variables unique to the role.
     * @param array $inheritsFrom Optional array of roles this role inherits from.
     */
    public static function registerRole($name, array $permissions, array $variables = [], array $inheritsFrom = []) {
        self::$roles[$name] = [
            'permissions' => $permissions,
            'variables' => $variables,
            'inherits' => $inheritsFrom,
        ];
    }

    /**
     * Gets the compiled permissions for a role, including inherited permissions.
     *
     * @param string $roleName The name of the role.
     * @return array The compiled list of permissions.
     * @throws Exception if the role is not found.
     */
    public static function getRolePermissions($roleName) {
        if (!isset(self::$roles[$roleName])) {
            throw new Exception("Role '{$roleName}' not found.");
        }

        $role = self::$roles[$roleName];

        $permissions = [];

        // Inherit permissions from parent roles
        foreach ($role['inherits'] as $parentRoleName) {
            $parentPermissions = self::getRolePermissions($parentRoleName);
            $permissions = array_merge($permissions, $parentPermissions);
        }

        // Merge this role's permissions
        $permissions = array_merge($permissions, $role['permissions']);

        return $permissions;
    }

    /**
     * Gets the variables for a role, including inherited variables.
     *
     * @param string $roleName The name of the role.
     * @return array The compiled list of variables.
     * @throws Exception if the role is not found.
     */
    public static function getRoleVariables($roleName) {
        if (!isset(self::$roles[$roleName])) {
            throw new Exception("Role '{$roleName}' not found.");
        }

        $role = self::$roles[$roleName];

        $variables = [];

        // Inherit variables from parent roles
        foreach ($role['inherits'] as $parentRoleName) {
            $parentVariables = self::getRoleVariables($parentRoleName);
            $variables = array_merge($variables, $parentVariables);
        }

        // Merge this role's variables
        $variables = array_merge($variables, $role['variables']);

        return $variables;
    }

    /**
     * Gets all registered permissions.
     *
     * @return array An array of registered permissions.
     */
    public static function getPermissions() {
        return array_keys(self::$permissions);
    }

    /**
     * Gets all registered roles.
     *
     * @return array An array of registered roles.
     */
    public static function getRoles() {
        return array_keys(self::$roles);
    }

    public static function deleteRole($roleName) {
        if (isset(self::$roles[$roleName])) {
            unset(self::$roles[$roleName]);
            return true;
        }
        return false;
    }

    public static function listRoles() {
        return array_keys(self::$roles);
    }

    public static function getRole($roleName) {
        return self::$roles[$roleName] ?? null;
    }
}
?>
