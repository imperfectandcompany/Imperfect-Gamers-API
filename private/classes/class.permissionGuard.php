<?php

class PermissionGuard {
    /**
     * Checks if the user has the required permission.
     *
     * @param array $userPermissions The user's array of permissions.
     * @param string|null $requiredPermission The required permission string or null if no permission is needed.
     * @return bool True if the user has the permission or no permission is required, false otherwise.
     */
    public static function hasPermission(array $userPermissions, ?string $requiredPermission): bool {
        // If no permission is required, grant access
        if ($requiredPermission === null) {
            return true;
        }

        // Handle wildcard permissions (e.g., 'admin.*')
        foreach ($userPermissions as $perm) {
            if (self::permissionMatches($perm, $requiredPermission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a permission pattern matches the required permission.
     *
     * @param string $pattern The permission pattern (supports wildcards '*').
     * @param string $permission The required permission.
     * @return bool True if it matches, false otherwise.
     */
    private static function permissionMatches(string $pattern, string $permission): bool {
        // Escape special regex characters except '*'
        $escapedPattern = preg_quote($pattern, '/');
        // Replace wildcard '*' with regex '.*'
        $regexPattern = '/^' . str_replace('\*', '.*', $escapedPattern) . '$/';
        return preg_match($regexPattern, $permission) === 1;
    }
}
