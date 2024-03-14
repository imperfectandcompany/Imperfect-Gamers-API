<?php
class InfractionService
{
    protected $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function fetchAllInfractions($type = null, $page = 1, $perPage = 10)
    {
        // Calculate offset for pagination
        $offset = ($page - 1) * $perPage;

        // Adjust the query based on the infraction type and include LIMIT and OFFSET for pagination
        if ($type === null) {
            $baseQuery = "FROM (SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status FROM sa_bans
                          UNION ALL
                          SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, type, status FROM sa_mutes) AS combined";
        } elseif ($type === 'comms') {
            $baseQuery = "FROM sa_mutes";
        } elseif ($type === 'bans') {
            $baseQuery = "FROM sa_bans";
        } else {
            throw new InvalidArgumentException('Invalid infraction type provided.');
        }

        // Get total items count
        $totalCountQuery = "SELECT COUNT(*) AS total " . $baseQuery;
        $totalCountResult = $this->dbConnection->query($totalCountQuery);
        $totalItems = $totalCountResult ? (int) $totalCountResult[0]['total'] : 0;
        $totalPages = ceil($totalItems / $perPage);

        // Now fetch the actual page data
        $dataQuery = "SELECT * " . $baseQuery . " LIMIT ? OFFSET ?";


        $results = $this->dbConnection->query($dataQuery, [$perPage, $offset]);

        // Collect Steam IDs for players and admins
        $steamIDs = [];
        foreach ($results as $infraction) {
            $steamIDs[] = $infraction['player_steamid'];
            // Assuming admin_steamid is also part of your data and you want their names too
            $steamIDs[] = $infraction['admin_steamid'];
        }

        // Get player names without duplicates
        $playerNames = $this->getPlayerNameBySteamID(array_unique($steamIDs));

        // Append player names to results
        foreach ($results as &$infraction) {
            $infraction['current_player_name'] = $playerNames[$infraction['player_steamid']] ?? 'Unknown Player';
            $infraction['current_admin_name'] = $playerNames[$infraction['admin_steamid']] ?? 'Unknown Admin';
        }
        unset($infraction); // Break the reference with the last element


        if ($results === false) {
            throw new RuntimeException('Failed to fetch infractions.');
        }

        // Return results along with pagination details
        return [
            'pagination' => [
                'totalPageItems' => count($results),
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'perPage' => $perPage,
                'currentPage' => $page
            ],            
            'results' => $results,
        ];
    }



    public function searchForPlayerNamesOptionalType(?string $type = null)
    {
        try {
            // Define the base and union queries with ORDER BY for sorting
            $baseQuery = "SELECT DISTINCT player_steamid, player_name, admin_steamid, created FROM sa_bans";
            $unionQuery = " UNION SELECT DISTINCT player_steamid, player_name, admin_steamid, created FROM sa_mutes";

            // Adjust query based on type
            if ($type === 'comms') {
                $query = "SELECT DISTINCT player_steamid, player_name, admin_steamid, created FROM sa_mutes";
            } elseif ($type === 'mutes' || $type === 'gags') {
                $query = "SELECT DISTINCT player_steamid, player_name, admin_steamid, created FROM sa_mutes WHERE type = :type";
            } else {
                $query = $baseQuery . $unionQuery;
            }

            // Append ORDER BY clause for sorting
            $query .= " ORDER BY created DESC";

            // Fetch results, handling type-specific queries with parameters
            if ($type === 'mutes' || $type === 'gags') {
                $stmt = $this->dbConnection->prepare($query);
                $stmt->execute(['type' => $type]);
                $results = $stmt->fetchAll();
            } else {
                $results = $this->dbConnection->query($query);
            }

            if ($results === false) {
                throw new RuntimeException('Failed to fetch player and admin information.');
            }

            // Fetch current names for the Steam IDs
            $steamIDs = array_unique(array_merge(array_column($results, 'player_steamid'), array_column($results, 'admin_steamid')));
            $currentNames = $this->getPlayerNameBySteamID($steamIDs);

            // Associate current names with the results
            foreach ($results as &$result) {
                $result['current_player_name'] = $currentNames[$result['player_steamid']] ?? 'Unknown';
                $result['current_admin_name'] = $currentNames[$result['admin_steamid']] ?? 'Unknown';
            }
            unset($result); // Break reference to the last element

            return $results;
        } catch (\PDOException $e) {
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }






    public function searchForPlayerNames(?string $type = null)
    {
        try {
            $query = "SELECT DISTINCT player_steamid, player_name FROM sa_bans";
            if ($type === 'comms') {
                $query .= " UNION SELECT DISTINCT player_steamid, player_name FROM sa_mutes";
            } elseif ($type !== null) {
                throw new InvalidArgumentException('Invalid infraction type provided.');
            }

            $results = $this->dbConnection->query($query);

            if ($results === false) {
                throw new RuntimeException('Failed to fetch player names.');
            }

            return $results;
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }








    public function fetchInfractionDetails($type, $id)
    {
        // Determine the appropriate table based on the infraction type
        if ($type === 'comms') {
            $table = "sa_mutes";
            $selectWhat = "id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, type, status";
        } elseif ($type === 'bans') {
            $table = "sa_bans";
            $selectWhat = "id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status";
        } else {
            throw new InvalidArgumentException('Invalid infraction type provided.');
        }

        // Prepare the WHERE clause and filter parameters
        $whereClause = "WHERE id = :id";

        // Use the DatabaseConnector's method to fetch a single row of data
        try {

            $result = $this->dbConnection->viewSingleData($table, $selectWhat, $whereClause, array(array("value" => $id, "type" => PDO::PARAM_INT)));

            // Check if a result was found
            if (!$result['result']) {
                throw new RuntimeException('Infraction not found.');
            }

            $infraction = $result['result'];

            $playerNames = $this->getPlayerNameBySteamID([$infraction['player_steamid'], $infraction['admin_steamid']]);
            $infraction['current_player_name'] = &$playerNames[$infraction['player_steamid']] ?? 'Unknown Player';
            $infraction['current_admin_name'] = &$playerNames[$infraction['admin_steamid']] ?? 'Unknown Admin';

            return $infraction;
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    public function fetchInfractionsCount($type = null)
    {
        try {
            $counts = ['total' => 0, 'active' => 0, 'expired' => 0];

            if ($type === null || $type === 'bans' || $type === 'comms') {
                $tablesToQuery = [];
                if ($type === null) {
                    $tablesToQuery = ['sa_bans', 'sa_mutes'];
                } elseif ($type === 'bans') {
                    $tablesToQuery = ['sa_bans'];
                } elseif ($type === 'comms') {
                    $tablesToQuery = ['sa_mutes'];
                }

                foreach ($tablesToQuery as $table) {
                    $activeQuery = "SELECT COUNT(*) AS count FROM {$table} WHERE status = 'ACTIVE'";
                    $expiredQuery = "SELECT COUNT(*) AS count FROM {$table} WHERE status = 'EXPIRED'";

                    $activeResult = $this->dbConnection->query($activeQuery);
                    $expiredResult = $this->dbConnection->query($expiredQuery);

                    if ($activeResult !== false && isset($activeResult[0])) {
                        $counts['active'] += (int) $activeResult[0]['count'];
                    }

                    if ($expiredResult !== false && isset($expiredResult[0])) {
                        $counts['expired'] += (int) $expiredResult[0]['count'];
                    }
                }
                $counts['total'] = $counts['active'] + $counts['expired'];
            } else {
                throw new InvalidArgumentException('Invalid infraction type provided. Valid types are null (for all), "bans", or "comms".');
            }

            return $counts;
        } catch (\Exception $e) {
            // Handle any exceptions that occur within the try block
            $GLOBALS['messages']['errors'][] = '<b>Error fetching infraction count: </b>' . $e->getMessage();
            return false;
        }
    }



    public function fetchInfractionsCountByType($type = null)
    {
        $queryParts = [];
        $params = [];

        // Define the base query parts depending on the infraction type
        switch ($type) {
            case 'bans':
                $baseTable = "sa_bans";
                break;
            case 'comms':
                $baseTable = "sa_mutes";
                break;
            case 'mutes':
                $baseTable = "sa_mutes";
                $queryParts[] = "type = 'mute'";
                break;
            case 'gags':
                $baseTable = "sa_mutes";
                $queryParts[] = "type = 'gag'";
                break;
            default:
                // Handle the default case where no specific type is requested
                // This requires combining counts from both tables, which is handled later
                $baseTable = null;
        }

        if ($baseTable) {
            // Construct the query for a specific table
            $where = !empty($queryParts) ? "WHERE " . implode(" AND ", $queryParts) : "";
            $queryActive = "SELECT COUNT(*) AS active FROM {$baseTable} {$where} AND status = 'ACTIVE'";
            $queryExpired = "SELECT COUNT(*) AS expired FROM {$baseTable} {$where} AND status = 'EXPIRED'";
        } else {
            // Construct combined queries for both tables when no specific type is requested
            $queryActive = "SELECT (SELECT COUNT(*) FROM sa_bans WHERE status = 'ACTIVE') + (SELECT COUNT(*) FROM sa_mutes WHERE status = 'ACTIVE') AS active";
            $queryExpired = "SELECT (SELECT COUNT(*) FROM sa_bans WHERE status = 'EXPIRED') + (SELECT COUNT(*) FROM sa_mutes WHERE status = 'EXPIRED') AS expired";
        }

        // Execute queries and fetch results
        $activeResult = $this->dbConnection->query($queryActive, $params);
        $expiredResult = $this->dbConnection->query($queryExpired, $params);

        if ($activeResult === false || !isset($activeResult[0]) || $expiredResult === false || !isset($expiredResult[0])) {
            throw new RuntimeException('Failed to fetch infractions count.');
        }

        // Aggregate results
        $counts = [
            'active' => (int) $activeResult[0]['active'],
            'expired' => (int) $expiredResult[0]['expired']
        ];

        // Return counts for all, active, and expired infractions
        return [
            'total' => $counts['active'] + $counts['expired'],
            'active' => $counts['active'],
            'expired' => $counts['expired']
        ];
    }

    public function searchInfractionsByQuery($query, $type = null, $page = 1, $perPage = 10)
    {
        $perPage = $perPage ?? 10;
        $decodedQuery = urldecode($query);
        $queryParam = "%" . trim($decodedQuery) . "%";

        // First, get the total count of infractions matching the query
        $totalItems = $this->getTotalInfractionsCountByQuery($query, $type);
        $totalPages = ceil($totalItems / $perPage);


        // Check if the requested page exceeds the total pages
        if ($page > $totalPages && $totalItems > 0) {
            throw new RuntimeException('Requested page does not exist.');
        }

        $offset = ($page - 1) * $perPage;

        if ($type === null) {
            $sql = "SELECT id, player_steamid, player_name, admin_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status FROM sa_bans
                    WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?
                    UNION ALL
                    SELECT id, player_steamid, player_name, admin_name, admin_steamid, reason, duration, ends, created, type, status FROM sa_mutes
                    WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?
                    LIMIT ? OFFSET ?";
            $params = array_merge([$queryParam, $queryParam, $queryParam, $queryParam, $queryParam, $queryParam, $queryParam, $queryParam], [$perPage, $offset]);
        } elseif ($type === 'comms') {
            $sql = "SELECT id, player_steamid, player_name, admin_name, admin_steamid, reason, duration, ends, created, type, status FROM sa_mutes
                    WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?
                    LIMIT ? OFFSET ?";
            $params = array_merge([$queryParam, $queryParam, $queryParam, $queryParam], [$perPage, $offset]);
        } elseif ($type === 'bans') {
            $sql = "SELECT id, player_steamid, player_name, admin_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status FROM sa_bans
            WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?
            LIMIT ? OFFSET ?";
            $params = array_merge([$queryParam, $queryParam, $queryParam, $queryParam], [$perPage, $offset]);
        } else {
            throw new InvalidArgumentException('Invalid infraction type provided.');
        }

        $results = $this->dbConnection->query($sql, $params);

        // Collect Steam IDs for players and admins
        $steamIDs = [];
        foreach ($results as $infraction) {
            $steamIDs[] = $infraction['player_steamid'];
            // Assuming admin_steamid is also part of your data and you want their names too
            $steamIDs[] = $infraction['admin_steamid'];
        }

        // Get player names without duplicates
        $playerNames = $this->getPlayerNameBySteamID(array_unique($steamIDs));

        // Append player names to results
        foreach ($results as &$infraction) {
            $infraction['current_player_name'] = $playerNames[$infraction['player_steamid']] ?? 'Unknown Player';
            $infraction['current_admin_name'] = $playerNames[$infraction['admin_steamid']] ?? 'Unknown Admin';
        }
        unset($infraction); // Break the reference with the last element

        if ($results === false) {
            throw new RuntimeException('Failed to fetch infractions.');
        }

        return [
            'data' => $results,
            'totalPageItems' => count($results),
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'currentPage' => $page
        ];
    }


    public function getTotalInfractionsCountByQuery($query, $type = null)
    {
        $queryParam = "%{$query}%";
        $totalItems = 0;

        // Setup initial query parts
        $whereConditions = "WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?";
        $params = [$queryParam, $queryParam, $queryParam, $queryParam];

        if ($type === null || $type === 'bans') {
            // Counting bans if type is 'bans' or not specified
            $sql = "SELECT COUNT(*) AS total FROM sa_bans {$whereConditions}";
            $result = $this->dbConnection->query($sql, $params);
            if ($result && isset($result[0]['total'])) {
                $totalItems += (int) $result[0]['total'];
            }
        }

        if ($type === null || $type === 'comms') {
            // Counting mutes and gags if type is 'comms' or not specified
            $sql = "SELECT COUNT(*) AS total FROM sa_mutes {$whereConditions}";
            $result = $this->dbConnection->query($sql, $params);
            if ($result && isset($result[0]['total'])) {
                $totalItems += (int) $result[0]['total'];
            }
        }

        return $totalItems;
    }







    public function checkInfractionsBySteamId(string $steamId)
    {
        try {
            // Query to check if there are any infractions for the given Steam ID
            $query = "SELECT COUNT(*) AS count FROM sa_bans WHERE player_steamid = ?";
            $result = $this->dbConnection->query($query, [$steamId]);

            // If the count is greater than 0, there are infractions
            $hasInfractions = $result[0]['count'] > 0;

            return $hasInfractions;
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    public function getInfractionsDetailsByAdminSteamId(string $adminSteamId)
    {
        try {
            // Query to fetch detailed information about bans associated with the given Admin Steam ID
            $banQuery = "SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status 
                         FROM sa_bans 
                         WHERE admin_steamid = ?";
            $bans = $this->dbConnection->query($banQuery, [$adminSteamId]);

            // Query to fetch detailed information about mutes associated with the given Admin Steam ID
            $muteQuery = "SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, type, status 
                          FROM sa_mutes 
                          WHERE admin_steamid = ?";
            $mutes = $this->dbConnection->query($muteQuery, [$adminSteamId]);

            // Combine bans and mutes into a single array
            $results = [
                'bans' => $bans,
                'mutes' => $mutes
            ];

            // Get player and admin names using getPlayerNameBySteamID method
            $steamIDs = array_unique(
                array_merge(
                    array_column($bans, 'player_steamid'),
                    array_column($bans, 'admin_steamid'),
                    array_column($mutes, 'player_steamid'),
                    array_column($mutes, 'admin_steamid')
                )
            );

            $playerNames = $this->getPlayerNameBySteamID($steamIDs);
            // Update player names for bans
            foreach ($results['bans'] as &$ban) {
                $ban['current_player_name'] = $playerNames[$ban['player_steamid']] ?? 'Unknown Player';
                $ban['current_admin_name'] = $playerNames[$ban['admin_steamid']] ?? 'Unknown Admin';
            }

            // Update player names for mutes
            foreach ($results['mutes'] as &$mute) {
                $mute['current_player_name'] = $playerNames[$mute['player_steamid']] ?? 'Unknown Player';
                $mute['current_admin_name'] = $playerNames[$mute['admin_steamid']] ?? 'Unknown Admin';
            }

            return $results;
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }



    public function getInfractionsDetailsByAdminIdPaginated(string $adminId, int $page, int $perPage)
    {
        try {
            // Query to fetch detailed information about bans associated with the given Admin ID with pagination
            $banQuery = "SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status 
             FROM sa_bans 
             WHERE admin_steamid = ?
             LIMIT ? OFFSET ?";
            $bans = $this->dbConnection->query($banQuery, [$adminId, $perPage, ($page - 1) * $perPage]);

            // Query to fetch detailed information about mutes associated with the given Admin ID with pagination
            $muteQuery = "SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, type, status 
              FROM sa_mutes 
              WHERE admin_steamid = ?
              LIMIT ? OFFSET ?";
            $mutes = $this->dbConnection->query($muteQuery, [$adminId, $perPage, ($page - 1) * $perPage]);

            // Combine bans and mutes into a single array
            $results = [
                'bans' => $bans,
                'mutes' => $mutes
            ];

            // Get unique Steam IDs of players and admins
            $steamIDs = array_unique(
                array_merge(
                    array_column($bans, 'player_steamid'),
                    array_column($bans, 'admin_steamid'),
                    array_column($mutes, 'player_steamid'),
                    array_column($mutes, 'admin_steamid')
                )
            );

            // Fetch player names and admin names using getPlayerNameBySteamID method
            $playerNames = $this->getPlayerNameBySteamID($steamIDs);

            // Update player names for bans
            foreach ($results['bans'] as &$ban) {
                $ban['current_player_name'] = $playerNames[$ban['player_steamid']] ?? 'Unknown Player';
                $ban['current_admin_name'] = $playerNames[$ban['admin_steamid']] ?? 'Unknown Admin';
            }

            // Update player names for mutes
            foreach ($results['mutes'] as &$mute) {
                $mute['current_player_name'] = $playerNames[$mute['player_steamid']] ?? 'Unknown Player';
                $mute['current_admin_name'] = $playerNames[$mute['admin_steamid']] ?? 'Unknown Admin';
            }

            // Get total items for bans and mutes
            $totalItems = count($bans) + count($mutes);

            // Calculate total pages
            $totalPages = ceil($totalItems / $perPage);

            // Check if requested page is valid
            if ($page < 1 || $page > $totalPages) {
                throw new InvalidArgumentException('Invalid page number.');
            }

            return [
                'data' => $results,
                'pagination' => [
                    'totalItems' => $totalItems,
                    'totalPageItems' => $totalItems,
                    'totalPages' => $totalPages,
                    'perPage' => $perPage,
                    'currentPage' => $page
                ]
            ];
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    public function getInfractionsDetailsBySteamId(string $steamId)
    {
        try {
            // Query to fetch detailed information about bans associated with the given Steam ID
            $banQuery = "SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status 
                     FROM sa_bans 
                     WHERE player_steamid = ?";
            $bans = $this->dbConnection->query($banQuery, [$steamId]);

            // Query to fetch detailed information about mutes associated with the given Steam ID
            $muteQuery = "SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, type, status 
                      FROM sa_mutes 
                      WHERE player_steamid = ?";
            $mutes = $this->dbConnection->query($muteQuery, [$steamId]);


            // Combine bans and mutes into a single array
            $results = [
                'bans' => $bans,
                'mutes' => $mutes
            ];

            // Get player and admin names using getPlayerNameBySteamID method
            $steamIDs = array_unique(
                array_merge(
                    array_column($bans, 'player_steamid'),
                    array_column($bans, 'admin_steamid'),
                    array_column($mutes, 'player_steamid'),
                    array_column($mutes, 'admin_steamid')
                )
            );

            $playerNames = $this->getPlayerNameBySteamID($steamIDs);
            // Update player names for bans
            foreach ($results['bans'] as &$ban) {
                $ban['current_player_name'] = $playerNames[$ban['player_steamid']] ?? 'Unknown Player';
                $ban['current_admin_name'] = $playerNames[$ban['admin_steamid']] ?? 'Unknown Admin';
            }

            // Update player names for mutes
            foreach ($results['mutes'] as &$mute) {
                $mute['current_player_name'] = $playerNames[$mute['player_steamid']] ?? 'Unknown Player';
                $mute['current_admin_name'] = $playerNames[$mute['admin_steamid']] ?? 'Unknown Admin';
            }

            return $results;
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    public function checkInfractionsByAdminId(string $adminId)
    {
        try {
            // Query to check if there are any infractions placed by the given Admin ID
            $query = "SELECT COUNT(*) AS count FROM sa_bans WHERE admin_steamid = ?";
            $result = $this->dbConnection->query($query, [$adminId]);

            // If the count is greater than 0, there are infractions placed by the admin
            $hasInfractions = $result[0]['count'] > 0;

            return $hasInfractions;
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    private function getTotalInfractionsCount($query, $type = null)
    {
        $queryParam = "%$query%";
        if ($type === 'comms') {
            $baseSql = "FROM sa_mutes WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?";
        } elseif ($type === 'bans') {
            $baseSql = "FROM sa_bans WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?";
        } else if ($type === null) {
            // Handle the union case
            $baseSql = "FROM sa_bans WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?
                        UNION ALL
                        SELECT id, player_steamid, player_name, admin_steamid, reason, duration, ends, created, 'COMMS' AS type, status 
                        FROM sa_mutes WHERE player_steamid LIKE ? OR player_name LIKE ? OR admin_steamid LIKE ? OR admin_name LIKE ?";
        } else {
            throw new InvalidArgumentException('Invalid infraction type provided.');
        }

        $sql = "SELECT COUNT(*) as total " . $baseSql;
        $params = $type === null ? [$queryParam, $queryParam, $queryParam, $queryParam, $queryParam, $queryParam, $queryParam, $queryParam] : [$queryParam, $queryParam, $queryParam, $queryParam];
        $result = $this->dbConnection->query($sql, $params);
        return $result ? (int) $result[0]['total'] : 0;
    }

    private function getPlayerNameBySteamID($steamIDs)
    {
        $apiKey = SteamAPIKey;
        $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/";
        $url .= "?key=$apiKey&steamids=" . implode(',', (array) $steamIDs);

        // Use cURL to fetch data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        // Parse the result
        $data = json_decode($result, true);

        // Extract player names
        $playerNames = [];
        foreach ($data['response']['players'] as $player) {
            $playerNames[$player['steamid']] = $player['personaname'];
        }

        // Return single name or array based on input
        return is_array($steamIDs) ? $playerNames : $playerNames[$steamIDs] ?? null;
    }

    public function getInfractionsDetailsBySteamIdPaginated(string $steamId, int $page, int $perPage)
    {
        try {
            // Query to fetch detailed information about bans associated with the given Steam ID with pagination
            $banQuery = "SELECT id, player_steamid, admin_steamid, reason, duration, ends, created, 'BAN' AS type, status 
             FROM sa_bans 
             WHERE player_steamid = ?
             LIMIT ? OFFSET ?";
            $bans = $this->dbConnection->query($banQuery, [$steamId, $perPage, ($page - 1) * $perPage]);

            // Query to fetch detailed information about mutes associated with the given Steam ID with pagination
            $muteQuery = "SELECT id, player_steamid, admin_steamid, reason, duration, ends, created, type, status 
              FROM sa_mutes 
              WHERE player_steamid = ?
              LIMIT ? OFFSET ?";
            $mutes = $this->dbConnection->query($muteQuery, [$steamId, $perPage, ($page - 1) * $perPage]);

            // Combine bans and mutes into a single array
            $results = [
                'bans' => $bans,
                'mutes' => $mutes
            ];

            // Get unique Steam IDs of players and admins
            $steamIDs = array_unique(
                array_merge(
                    array_column($bans, 'player_steamid'),
                    array_column($bans, 'admin_steamid'),
                    array_column($mutes, 'player_steamid'),
                    array_column($mutes, 'admin_steamid')
                )
            );

            // Fetch player names and admin names using getPlayerNameBySteamID method
            $playerNames = $this->getPlayerNameBySteamID($steamIDs);

            // Update player names for bans
            foreach ($results['bans'] as &$ban) {
                $ban['current_player_name'] = $playerNames[$ban['player_steamid']] ?? 'Unknown Player';
                $ban['current_admin_name'] = $playerNames[$ban['admin_steamid']] ?? 'Unknown Admin';
            }

            // Update player names for mutes
            foreach ($results['mutes'] as &$mute) {
                $mute['current_player_name'] = $playerNames[$mute['player_steamid']] ?? 'Unknown Player';
                $mute['current_admin_name'] = $playerNames[$mute['admin_steamid']] ?? 'Unknown Admin';
            }

            // Get total items for bans and mutes
            $totalItems = count($bans) + count($mutes);

            // Calculate total pages
            $totalPages = ceil($totalItems / $perPage);

            // Check if requested page is valid
            if ($page < 1 || $page > $totalPages) {
                throw new InvalidArgumentException('Invalid page number.');
            }

            return [
                'data' => $results,
                'pagination' => [
                    'totalItems' => $totalItems,
                    'totalPageItems' => $totalItems,
                    'totalPages' => $totalPages,
                    'perPage' => $perPage,
                    'currentPage' => $page
                ]
            ];
        } catch (\PDOException $e) {
            // Log the error or handle it as needed
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }
}

