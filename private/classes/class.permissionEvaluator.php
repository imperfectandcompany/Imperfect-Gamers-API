<?php
// PermissionEvaluator.php

class PermissionEvaluator {
    /**
     * Compiles a user's permissions based on their roles.
     *
     * @param array $roles An array of role names assigned to the user.
     * @return array The compiled list of permissions.
     */
    public static function compilePermissions(array $roles) {
        $permissions = [];

        foreach ($roles as $roleName) {
            try {
                $rolePermissions = PermissionManager::getRolePermissions($roleName);
                $permissions = array_merge($permissions, $rolePermissions);
            } catch (Exception $e) {
                // Handle exception, possibly log it
                // For now, continue to next role
                continue;
            }
        }

        // Process permissions to expand wildcards, apply negations and exceptions
        $permissions = self::processPermissions($permissions);

        return $permissions;
    }

    private static function processPermissions(array $permissions) {
        $granted = [];
        $denied = [];
        $exceptions = [];

        foreach ($permissions as $perm) {
            if (strpos($perm, '-') === 0) {
                // Negation node
                $denied[] = substr($perm, 1);
            } elseif (strpos($perm, '^') === 0) {
                // Exception node
                $exceptions[] = substr($perm, 1);
            } else {
                // Granted permission (can be wildcard)
                $granted[] = $perm;
            }
        }

        // Expand wildcards
        $grantedExpanded = self::expandWildcards($granted);

        // Apply negations
        $grantedFinal = array_filter($grantedExpanded, function($perm) use ($denied) {
            foreach ($denied as $denyPerm) {
                if (self::permissionMatches($denyPerm, $perm)) {
                    return false;
                }
            }
            return true;
        });

        // Apply exceptions
        foreach ($exceptions as $exceptionPerm) {
            foreach ($grantedExpanded as $perm) {
                if (self::permissionMatches($exceptionPerm, $perm)) {
                    $grantedFinal[] = $perm;
                }
            }
        }

        // Remove duplicates
        $grantedFinal = array_unique($grantedFinal);

        return $grantedFinal;
    }

    private static function expandWildcards(array $permissions) {
        $expanded = [];

        $allPermissions = PermissionManager::getPermissions();

        foreach ($permissions as $perm) {
            if (strpos($perm, '*') !== false) {
                // Wildcard permission
                $pattern = str_replace('*', '.*', preg_quote($perm, '/'));
                $pattern = '/^' . $pattern . '$/';

                foreach ($allPermissions as $registeredPerm) {
                    if (preg_match($pattern, $registeredPerm)) {
                        $expanded[] = $registeredPerm;
                    }
                }
            } else {
                // Exact permission
                $expanded[] = $perm;
            }
        }

        return $expanded;
    }

    /**
     * Checks if a permission matches a pattern (used for negations and exceptions).
     *
     * @param string $pattern The permission pattern (supports wildcards).
     * @param string $permission The permission to check.
     * @return bool True if it matches, false otherwise.
     */
    public static function permissionMatches($pattern, $permission)
    {
        // If the pattern is '*', it matches all permissions
        if ($pattern === '*') {
            return true;
        }

        $patternRegex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return preg_match($patternRegex, $permission);
    }
}
