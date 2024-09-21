<?php

/**
 * PremiumController handles user authentication.
 */

class Premium
{
    private $simpleAdminDb;  // Connection for game server role management related operations
    private $sharpTimerDb;   // Connection for game server surf game-mode related operations
    private $whiteListDb;   // Connection for game server vip whitelist related operations
    

    /**
     * Constructor for the Premium class.
     *
     * @param DatabaseConnector $simpleAdminDb Connection object for the game server database
     * @param DatabaseConnector $sharpTimerDb Connection object for the web server database
     */
    public function __construct($simpleAdminDb, $sharpTimerDb, $whiteListDb)
    {
        $this->simpleAdminDb = $simpleAdminDb;
        $this->sharpTimerDb = $sharpTimerDb;
        $this->whiteListDb = $whiteListDb;
    }

    /**
     * Updates the IsVip status in the PlayerStats table and ensures proper flags management in the sa_admins table.
     * 
     * @param string $steamId The Steam ID of the user.
     * @param bool $isPremium Whether the user is being granted or revoked Premium status.
     * @param string $username The Website Username of the user.
     * @throws RuntimeException if updating the premium status fails.
     * @return bool Returns true if the operation is successful.
     */
    public function updatePremiumStatus($steamId, $username, $isPremium)
    {
        $this->sharpTimerDb->getConnection()->beginTransaction();  // Start transaction
        $this->simpleAdminDb->getConnection()->beginTransaction();  // Start transaction
        $this->whiteListDb->getConnection()->beginTransaction();    // Start transaction
        try {
            // Update the PlayerStats table
            $this->updateIsPremiumStatus($steamId, $isPremium);
            // Insert or update in sa_admins table if playerstats was successfully updated.
            $this->upsertPremium($steamId, $username, $isPremium);
            // Handle whitelist addition/removal for vip server
            $this->handleWhitelist($steamId, $isPremium);
            
            // Commit all transactions if everything is successful

            $this->sharpTimerDb->getConnection()->commit();  // Commit transaction
            $this->simpleAdminDb->getConnection()->commit();  // Commit transaction
            $this->whiteListDb->getConnection()->commit();
            // If everything is successful
            return true;
        } catch (RuntimeException $e) {
            $this->sharpTimerDb->getConnection()->rollBack();  // Rollback transaction on error
            $this->simpleAdminDb->getConnection()->rollBack();  // Rollback transaction on error
            $this->whiteListDb->getConnection()->rollBack();   // Rollback transaction on error
            throw $e;  // Re-throw the exception
        }
    }
    
    // Adding/removing from whitelist based on $isPremium
    private function handleWhitelist($steamId, $isPremium)
    {
        if ($isPremium) {
            // Add to whitelist
            $query = "INSERT INTO whitelist (value, server_id) VALUES (?, 1) ON DUPLICATE KEY UPDATE value = value";
        } else {
            // Remove from whitelist
            $query = "DELETE FROM whitelist WHERE value = ? AND server_id = 1";
        }

        $params = [$steamId];
        $result = $this->whiteListDb->query($query, $params);
        return $result;
    }

    private function updateIsPremiumStatus($steamId, $isPremium)
    {
        // First, check if the record exists
        $existQuery = "SELECT COUNT(*) FROM PlayerStats WHERE SteamID = ?";
        $existStmt = $this->sharpTimerDb->getConnection()->prepare($existQuery);
        $existStmt->execute([$steamId]);
        $existCount = $existStmt->fetchColumn();

        // If the record does not exist
        if ($existCount == 0) {
            throw new RuntimeException("No record found for Steam ID: $steamId");
        }

        // Proceed with the update
        $isVipValue = $isPremium ? 1 : 0;
        $sql = "UPDATE PlayerStats SET IsVip = ? WHERE SteamID = ?";
        $params = array($isVipValue, $steamId);
        $stmt = $this->sharpTimerDb->getConnection()->prepare($sql);
        $success = $stmt->execute($params);

        // Even if no rows are affected, it's still a success because the record exists and the request was valid
        if (!$success) {
            throw new RuntimeException("Failed to update premium status for Steam ID: $steamId");
        }

        return true;
    }


    public function isPlayerExistsSharpTimer($steamId) {
        $query = "SELECT COUNT(1) FROM PlayerStats WHERE SteamID = ?";
        $result = $this->sharpTimerDb->query($query, [$steamId]);
        return $result[0]['COUNT(1)'] > 0;
    }
    

    /**
     * Inserts or updates a user in the sa_admins table based on their existence.
     *
     * @param string $steamId The Steam ID of the user to insert or update.
     * @param string $username The username of the user.
     * @return bool Returns a boolean indicating the outcome of the operation.
     */
    private function upsertPremium($steamId, $username, $isPremium)
    {
        // Check if the user already exists in sa_admins
        $checkQuery = "SELECT 1 FROM sa_admins WHERE player_steamid = :steamId";
        $checkParams = [':steamId' => $steamId];
        $checkResult = $this->simpleAdminDb->query($checkQuery, $checkParams);
 
        // Prepare the common parameters
        $flags = $this->generateNewFlags($this->getCurrentFlags($steamId), $isPremium);

        $fixedImmunity = 10;  // Fixed immunity value for premium

        // Update the existing Premium record
        if (!empty($checkResult)) {
        // Existing record found, so update it
        $currentImmunity = $checkResult[0]['immunity'] ?? 0;  // fetch returns an array
        $immunity = max($currentImmunity, $fixedImmunity);  // Ensure we do not lower immunity

            // Ensure $flags is a string
            if (!is_string($flags)) {
                $flags = (string) $flags;  // Convert $flags to string if not already
            }
            $params = makeFilterParams([$flags, $immunity, $steamId]);
                    // Prepare parameters for the update

            // Call updateData method with generated filter params
            $updateResult = $this->simpleAdminDb->updateData(
                "sa_admins",
                "flags = :flags, immunity = :immunity",
                "player_steamid = :steamId",
                $params
            );
            if ($updateResult) {
                return true;
            } else {
                throw new RuntimeException("Failed to update " . $username . " under Steam ID: " . $steamId . " as Premium. Please check the database connection and data integrity");
            }

        } else {
            // Insert the new Premium record
            //$insertQuery = "INSERT INTO sa_admins (player_steamid, player_name, flags, immunity) VALUES (:steamId, :username, :flags, :immunity)";
            //$insertParams = [
              //  ':steamId' => $steamId,
                //':username' => $username,
           //     ':flags' => $flags,
           //     ':immunity' => $fixedImmunity
           // ];
            
            //$insertQueryRaw = "INSERT INTO sa_admins (player_steamid, player_name, flags, immunity) VALUES (".$steamId." ".$username." ".$flags." ".$fixedImmunity.")";

            //$insertResult = $this->simpleAdminDb->query($insertQuery, $insertParams);

            $table = "sa_admins";
            $rows = "player_steamid, player_name, flags, immunity, created";
            $values = "?, ?, ?, ?, ?";
        $createdAt = date('Y-m-d H:i:s'); // Set DeletedAt timestamp if marked as deleted

            $params = makeFilterParams([
                $steamId,
                $username,
                $flags,
                $fixedImmunity,
                $createdAt
            ]);

            try {
                $addResult = $this->simpleAdminDb->insertData($table, $rows, $values, $params);
                if ($addResult) {
                    return true;
                } else {
                throw new Exception("Failed to insert" . $username . " under Steam ID: " . $steamId . " Please check the database connection and data integrity");
                }
            } catch (Exception $e) {
                throw new PDOException('I really dunno how we got here... but this may help: ' . $e->getMessage());
            }
        }
    }

    private function getCurrentFlags($steamId)
    {
        $sql = "SELECT flags FROM sa_admins WHERE player_steamid = ?";
        $params = array($steamId);
        $result = $this->simpleAdminDb->query($sql, $params);
        return $result ? $result[0]['flags'] : '';
    }

    // private function generateNewFlags($flags, $isPremium)
    // {
    //     $flagParts = explode(', ', $flags);
    //     $premiumFlagIndex = array_search('#css/premium', $flagParts);

    //     if ($isPremium && $premiumFlagIndex === false) {
    //         $flagParts[] = '#css/premium';
    //     } elseif (!$isPremium && $premiumFlagIndex !== false) {
    //         unset($flagParts[$premiumFlagIndex]);
    //     }

    //     return implode(', ', $flagParts);
    // }

    private function generateNewFlags($flags, $isPremium)
    {
        // Handle NULL or empty string cases
        if ($flags === null || trim($flags) === '') {
            $flagParts = [];
        } else {
            // Split flags by comma and remove any extra spaces around the flags
            $flagParts = array_filter(array_map('trim', explode(',', $flags)));
        }
    
        // Search for the #css/premium flag
        $premiumFlagIndex = array_search('#css/premium', $flagParts);
    
        if ($isPremium && $premiumFlagIndex === false) {
            // Add the premium flag if it doesn't exist
            $flagParts[] = '#css/premium';
        } elseif (!$isPremium && $premiumFlagIndex !== false) {
            // Remove the premium flag if it exists
            unset($flagParts[$premiumFlagIndex]);
        }
    
        // Return the flags as a comma-separated string, ensuring consistent formatting
        return implode(',', $flagParts);
    }
    

    // private function updateSaAdminsFlags($steamId, $isPremium)
    // {
    //     $currentFlags = $this->getCurrentFlags($steamId);
    //     $newFlags = $this->generateNewFlags($currentFlags, $isPremium);

    //     $sql = "UPDATE sa_admins SET flags = ? WHERE player_steamid = ?";
    //     $params = array($newFlags, $steamId);
    //     $result = $this->simpleAdminDb->query($sql, $params);
    //     return $result !== false;
    // }

    public function checkPremiumStatusFromSteamId($steamId)
    {

        // The 'is_premium' status is stored in the 'PlayerStats' table under 'IsVip' column
        try {
            $query = "SELECT IsVip FROM PlayerStats WHERE SteamID = :steamId";
            $params = [':userId' => $steamId];
            $result = $this->sharpTimerDb->query($query, $params);

            if (!empty($result) && isset($result[0]['IsVip'])) {
                $isPremium = (bool) $result[0]['IsVip'];
                return $isPremium;
            } else {
                // Handle the case where $result is null or an empty array
                throw new Exception('Steam not found');
            }
        } catch (Exception $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }
    
    public function getAllPremiumUsers() {
        // Query to get premium users from PlayerStats where they are marked as VIP
        $queryPlayerStats = "SELECT SteamID, lastConnected FROM PlayerStats WHERE IsVip = 1";
        $premiumUsersPlayerStats = $this->sharpTimerDb->query($queryPlayerStats);
    
        // Query to get premium users from sa_admins where they have premium flags
        $querySaAdmins = "SELECT player_steamid AS SteamID FROM sa_admins WHERE flags LIKE '%#css/premium%'";
        $premiumUsersSaAdmins = $this->simpleAdminDb->query($querySaAdmins);
    
        // Convert results to associative arrays keyed by SteamID for easy intersection
        $playerStatsSteamIds = array_column($premiumUsersPlayerStats, 'SteamID');
        $saAdminsSteamIds = array_column($premiumUsersSaAdmins, 'SteamID');
    
        // Intersect the two arrays to get SteamIDs that are in both tables
        $premiumSteamIds = array_intersect($playerStatsSteamIds, $saAdminsSteamIds);
    
        // Filter the premiumUsersPlayerStats to only include those in the intersection
        $premiumUsers = array_filter($premiumUsersPlayerStats, function ($user) use ($premiumSteamIds) {
            return in_array($user['SteamID'], $premiumSteamIds);
        });
    
        return array_values($premiumUsers);  // Return the list of premium users with lastConnected info
    }


    

    // private function setPremiumStatusSharpTimer($steamId, $isVip)
    // {
    //     $vipStatus = $isVip ? 1 : 0;

    //     $updateQuery = "UPDATE PlayerStats SET IsVip = :vipStatus WHERE SteamID = :steamId";
    //     $updateParams = [
    //         ':vipStatus' => $vipStatus,
    //         ':steamId' => $steamId
    //     ];

    //     $updateResult = $this->sharpTimerDb->query($updateQuery, $updateParams);
    //     if ($updateResult) {
    //         return ['success' => true, 'message' => 'VIP status successfully updated.'];
    //     } else {
    //         return ['success' => false, 'message' => 'Failed to update VIP status. Please check the database connection and data integrity.'];
    //     }
    // }

    // /**
    //  * Checks if a player exists in the PlayerStats table.
    //  *
    //  * @param string $steamId The Steam ID to check for.
    //  * @return bool Returns true if the player exists, false otherwise.
    //  */
    // private function playerExists($steamId)
    // {
    //     // Prepare the SQL query to check if the Steam ID exists in the PlayerStats table
    //     $query = "SELECT 1 FROM PlayerStats WHERE SteamID = :steamId";
    //     $params = [':steamId' => $steamId];

    //     // Execute the query
    //     $result = $this->sharpTimerDb->query($query, $params);

    //     // Check if any rows are returned
    //     return !empty($result);
    // }

    // /**
    //  * Checks if a player is marked as a Premium in the PlayerStats table. (Premium)
    //  *
    //  * @param string $steamId The Steam ID to check.
    //  * @return bool|null Returns true if the player is a Premium, false if not a Premium, and null if the player doesn't exist.
    //  */
    // private function isPlayerPremiumSharpTimer($steamId)
    // {
    //     // Prepare the SQL query to check the Premium status of the Steam ID in the PlayerStats table
    //     $query = "SELECT IsVip FROM PlayerStats WHERE SteamID = :steamId";
    //     $params = [':steamId' => $steamId];

    //     // Execute the query
    //     $result = $this->sharpTimerDb->query($query, $params);

    //     // Check if any rows are returned and return the Premium status
    //     if (!empty($result)) {
    //         // IsVip is stored as an integer (1 for true, 0 for false)
    //         return (bool) $result[0]['IsVip'];
    //     } else {
    //         // Return null if no record is found, indicating the player does not exist
    //         return null;
    //     }
    // }

    // /**
    //  * Checks if a Steam ID exists in the sa_admins table and retrieves their flags.
    //  *
    //  * @param string $steamId The Steam ID to check for.
    //  * @return string|null Returns the flags associated with the premium membership if found, or null if not found.
    //  */
    // private function gePremiumFlagsSimpleAdmin($steamId)
    // {
    //     // Prepare the SQL query to retrieve the flags for a given Steam ID from the sa_admins table
    //     $query = "SELECT flags FROM sa_admins WHERE player_steamid = :steamId";
    //     $params = [':steamId' => $steamId];

    //     // Execute the query
    //     $result = $this->simpleAdminDb->query($query, $params);

    //     // Check if any rows are returned and return the flags
    //     if (!empty($result)) {
    //         return $result[0]['flags'];
    //     } else {
    //         // Return null if no premium record is found for the Steam ID
    //         return null;
    //     }
    // }

    // /**
    //  * Checks if a user exists in PlayerStats and sa_admins tables based on their Steam ID.
    //  * CHECKS BOTH SHARPTIMER AND SIMPLEADMIN
    //  * @param string $steamId The Steam ID to check.
    //  * @return array Returns a message and data depending on the user's existence in the tables.
    //  */
    // private function checkUserAndPremiumStatus($steamId)
    // {
    //     // Check for user in PlayerStats
    //     $playerQuery = "SELECT 1 FROM PlayerStats WHERE SteamID = :steamId";
    //     $playerParams = [':steamId' => $steamId];
    //     $playerResult = $this->sharpTimerDb->query($playerQuery, $playerParams);

    //     if (empty($playerResult)) {
    //         return ['message' => 'User does not exist in PlayerStats.', 'data' => null];
    //     }

    //     // User exists in PlayerStats, now check sa_admins
    //     $premiumQuery = "SELECT flags FROM sa_admins WHERE player_steamid = :steamId";
    //     $premiumParams = [':steamId' => $steamId];
    //     $premiumResult = $this->simpleAdminDb->query($premiumQuery, $premiumParams);

    //     if (!empty($premiumResult)) {
    //         // User exists in sa_admins, return flags
    //         return ['message' => 'User exists in PlayerStats and is a Premium in sa_admins.', 'data' => $premiumResult[0]['flags']];
    //     } else {
    //         // User exists in PlayerStats but not in sa_admins
    //         return ['message' => 'User exists in PlayerStats but not as a Premium user in sa_admins.', 'data' => null];
    //     }
    // }



    // /**
    //  * Removes a user from the sa_admins table based on their Steam ID.
    //  *
    //  * @param string $steamId The Steam ID of the user to remove.
    //  * @return array Returns a message indicating the outcome of the operation.
    //  */
    // public function removePremiumSimpleAdmin($steamId)
    // {
    //     // Check if the user exists in sa_admins
    //     $checkQuery = "SELECT 1 FROM sa_admins WHERE player_steamid = :steamId";
    //     $checkParams = [':steamId' => $steamId];
    //     $checkResult = $this->simpleAdminDb->query($checkQuery, $checkParams);

    //     if (empty($checkResult)) {
    //         return ['success' => false, 'message' => 'User does not exist as in sa_admins.'];
    //     }

    //     // Remove the user from sa_admins
    //     $deleteQuery = "DELETE FROM sa_admins WHERE player_steamid = :steamId";
    //     $deleteParams = [':steamId' => $steamId];

    //     $deleteResult = $this->simpleAdminDb->query($deleteQuery, $deleteParams);
    //     if ($deleteResult) {
    //         return ['success' => true, 'message' => 'User successfully removed from admin.'];
    //     } else {
    //         return ['success' => false, 'message' => 'Failed to remove user from admin. Please check the database connection and data integrity.'];
    //     }
    // }

}


