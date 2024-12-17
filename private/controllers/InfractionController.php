<?php
include($GLOBALS['config']['private_folder'] . '/services/infractionService.php');
class InfractionController
{

    protected $dbManager;
    protected $logger;

    public function __construct($dbManager, $logger)
    {
        $this->dbConnection = $dbManager->getConnection('gameserver');
        $this->secondaryConnection = $dbManager->getConnectionByDbName('gameserver', 'sharptimer');

        $this->logger = $logger;
    }


public function getAllInfractions(?int $perPage = null)
{
    try {
        $perPage = $perPage ?? 10; // Define how many items we want per page
        $sortParams = $_GET['sort'] ?? 'created|DESC'; // Get the 'sort' parameter, default to 'created|DESC'

        // Split sortParams by comma to allow multiple sorting (e.g., 'created|DESC,player_name|ASC')
        $sortArray = explode(',', $sortParams);

        // Validate the sort array
        foreach ($sortArray as $sortParam) {
            if (!preg_match('/^[a-zA-Z_]+\|(ASC|DESC)$/', $sortParam)) {
                throw new InvalidArgumentException('Invalid sort parameter format. Expected format: column|ASC or column|DESC.');
            }
        }

        // Instantiate the service with database connection
        $infractionService = new InfractionService($this->dbConnection);
        $results = $infractionService->fetchAllInfractions(null, 1, $perPage, $sortArray);

        // Log the action
        $this->logger->log(0, LOG_FETCH_ALL_INFRACTIONS, 'all');
        
        // Send successful response with data
        ResponseHandler::sendResponse('success', $results, SUCCESS_OK);
    } catch (InvalidArgumentException $e) {
        throwError($e->getMessage(), ERROR_INVALID_INPUT);
        $this->logger->log('error', 'fetch_infractions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
    } catch (RuntimeException $e) {
        throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        $this->logger->log('error', 'fetch_infractions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
    } catch (Exception $e) {
        throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        $this->logger->log('error', 'fetch_infractions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 500);
    }       
}



    public function getInfractions(string $type)
    {
        try {
            // Instantiate the service with database connection
            $infractionService = new InfractionService($this->dbConnection);
            $results = $infractionService->fetchAllInfractions($type);

            // Log the action
            // Determine log action based on the type parameter
            switch ($type) {
                case 'comms':
                    $logAction = 'LOG_FETCH_COMMS_INFRACTIONS'; // Use your constant here
                    break;
                case 'bans':
                    $logAction = 'LOG_FETCH_BAN_INFRACTIONS'; // Use your constant here
                    break;
            }

            // Note: Make sure to replace '0' with the actual user ID where applicable
            $this->logger->log(0, $logAction, ['type' => $type]);

            // Send successful response with data
            ResponseHandler::sendResponse('success', ['results' => $results], SUCCESS_OK);
        } catch (InvalidArgumentException $e) {
            throwError($e->getMessage(), ERROR_INVALID_INPUT);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }


    public function getInfractionDetails($type, $id) {
        try {
            $infractionService = new InfractionService($this->dbConnection);
            $result = $infractionService->fetchInfractionDetails($type, $id);
    
            // Determine log action based on the type parameter
            $logAction = ($type === 'comms') ? 'LOG_FETCH_COMMS_INFRACTION_DETAILS' : 'LOG_FETCH_BAN_INFRACTION_DETAILS';
            // Log the action
            $this->logger->log(0, $logAction, ['id' => $id, 'type' => $type]);
    
            // Send successful response with infraction details
            ResponseHandler::sendResponse('success', ['result' => $result], SUCCESS_OK);
        } catch (InvalidArgumentException $e) {
            throwError($e->getMessage(), ERROR_INVALID_INPUT);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_NOT_FOUND); // Use NOT FOUND for infraction not found
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }


public function getAllInfractionsPaginated($page, ?int $perPage = null)
{
    try {
        $page = intval($page); // Ensure page is an integer
        $perPage = $perPage ?? 10; // Define how many items we want per page
        $sortParams = $_GET['sort'] ?? 'created|DESC'; // Get the 'sort' parameter, default to 'created|DESC'

        // Split sortParams by comma to allow multiple sorting (e.g., 'created|DESC,player_name|ASC')
        $sortArray = explode(',', $sortParams);

        // Validate the sort array
        foreach ($sortArray as $sortParam) {
            if (!preg_match('/^[a-zA-Z_]+\|(ASC|DESC)$/', $sortParam)) {
                throw new InvalidArgumentException('Invalid sort parameter format. Expected format: column|ASC or column|DESC.');
            }
        }

        $infractionService = new InfractionService($this->dbConnection);
        $response = $infractionService->fetchAllInfractions(null, $page, $perPage, $sortArray);

        // Log the action, adjust logging as necessary
        $this->logger->log(0, 'FETCH_ALL_INFRACTIONS_PAGINATED', "Fetching page $page of all infractions with sorting.");

        ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
    } catch (InvalidArgumentException $e) {
        $this->logger->log('error', 'fetch_infractions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], ERROR_INVALID_INPUT);
    } catch (RuntimeException $e) {
        $this->logger->log('error', 'fetch_infractions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], ERROR_INTERNAL_SERVER);
    } catch (Exception $e) {
        $this->logger->log('error', 'fetch_infractions_error', ['error' => $e->getMessage()]);
        return ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], ERROR_INTERNAL_SERVER);
    }
}

    public function getInfractionsByTypePaginated($type, $page)
    {
        try {
            $page = intval($page); // Ensure page is an integer
            $perPage = 10; // Define how many items you want per page

            $infractionService = new InfractionService($this->dbConnection);
            $response = $infractionService->fetchAllInfractions($type, $page, $perPage);

            // Log the action, adjust logging as necessary
            $this->logger->log(0, 'FETCH_INFRACTIONS_BY_TYPE_PAGINATED', "Fetching page $page of infractions for type: $type");

            ResponseHandler::sendResponse('success', $response, SUCCESS_OK);
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }


public function getInfractionsAllCount() {
    try {
        $infractionService = new InfractionService($this->dbConnection);
        $count = $infractionService->fetchInfractionsCount();

        // Log the action
        $this->logger->log(0, 'LOG_FETCH_INFRACTIONS_COUNT', 'all');

        // Send successful response with count
        ResponseHandler::sendResponse('success', ['count' => $count], SUCCESS_OK);
    } catch (InvalidArgumentException $e) {
        // Handle invalid argument error (e.g., invalid type)
        $this->logger->log('error', 'fetch_infractions_count_invalid_argument', ['error' => $e->getMessage()]);
        ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
    } catch (Exception $e) {
        // Handle any other internal errors
        $this->logger->log('error', 'fetch_infractions_count_error', ['error' => $e->getMessage()]);
        ResponseHandler::sendResponse('error', ['message' => 'Internal Server Error'], 500);
    }
}

public function getInfractionsTypeCount($type = null) {
    try {
        $infractionService = new InfractionService($this->dbConnection);
        if (in_array($type, ['mutes', 'gags', 'silences'])) {
            $count = $infractionService->fetchInfractionsCountByType($type);
        } else if (in_array($type, ['bans'])){
                $count = $infractionService->fetchInfractionsCount($type);
        } else {
                throw new InvalidArgumentException("Invalid infraction type provided: {$type}");
        }

        // Log the action
        $this->logger->log(0, 'LOG_FETCH_INFRACTIONS_COUNT', ['type' => $type]);

        // Send successful response with count
        ResponseHandler::sendResponse('success', ['count' => $count], SUCCESS_OK);
    } catch (InvalidArgumentException $e) {
        // Handle invalid argument error (e.g., invalid type)
        $this->logger->log('error', 'fetch_infractions_count_type_invalid_argument', ['error' => $e->getMessage()]);
        ResponseHandler::sendResponse('error', ['message' => $e->getMessage()], 400);
    } catch (Exception $e) {
        // Handle any other internal errors
        $this->logger->log('error', 'fetch_infractions_count_type_error', ['error' => $e->getMessage()]);
        ResponseHandler::sendResponse('error', ['message' => 'Internal Server Error'], 500);
    }
}

public function searchInfractionsByName(string $query, ?int $page = null, ?int $perPage = null) {
    $page = $page ?? 1;
    $this->searchInfractions($query, null, $page, $perPage);
}

public function searchInfractionsByNameAndType(string $query, ?string $type = null, ?int $page = null, ?int $perPage = null) {
    $page = $page ?? 1;

    $this->searchInfractions($query, $type, $page, $perPage);
}

    private function searchInfractions($query, $type, $page, ?int $perPage = null)
    {
        try {
            $infractionService = new InfractionService($this->dbConnection, $this->secondaryConnection);
            $results = $infractionService->searchInfractionsByQuery($query, $type, $page, $perPage);

            // Log the action
            $logAction = 'LOG_SEARCH_INFRACTIONS'; // Define this constant as needed
            $this->logger->log(0, $logAction, ['query' => $query, 'type' => $type]);

            // Send successful response with data
            ResponseHandler::sendResponse('success', ['results' => $results], SUCCESS_OK);
        } catch (InvalidArgumentException $e) {
            throwError($e->getMessage(), ERROR_INVALID_INPUT);
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }

    public function checkInfractionsBySteamId(string $steamId) {
        try {
            // Instantiate the service with database connection
            $infractionService = new InfractionService($this->dbConnection);
            $result = $infractionService->checkInfractionsBySteamId($steamId);
    
            // Log the action
            // Log action code for checking infractions by Steam ID
            $logAction = 'LOG_CHECK_INFRACTIONS_BY_STEAMID'; // Define this constant as needed
            $this->logger->log(0, $logAction, ['steam_id' => $steamId]);
    
            // Send successful response with infraction details
            ResponseHandler::sendResponse('success', ['result' => $result], SUCCESS_OK);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_NOT_FOUND); // Use NOT FOUND for infraction not found
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }

    public function getInfractionsDetailsBySteamId(string $steamId) {
        try {
            // Instantiate the service with database connection
            $infractionService = new InfractionService($this->dbConnection);
            $results = $infractionService->getInfractionsDetailsBySteamId($steamId);
    
            // Log the action
            // Log action code for fetching infraction details by Steam ID
            $logAction = 'LOG_FETCH_INFRACTIONS_DETAILS_BY_STEAMID'; // Define this constant as needed
            $this->logger->log(0, $logAction, ['steam_id' => $steamId]);
    
            // Send successful response with infraction details
            ResponseHandler::sendResponse('success', ['results' => $results], SUCCESS_OK);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_NOT_FOUND); // Use NOT FOUND for infraction details not found
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }

    public function getInfractionDetailsBySteamIdPaginated(string $steamId, int $page = null) {
        $page = $page ?? 1;

        $page = max($page, 1); // Ensure page is not less than 1
        $perPage = 10; // Define the number of items per page
    
        try {
            // Instantiate the service with database connection
            $infractionService = new InfractionService($this->dbConnection);
            
            // Fetch paginated infraction details by Steam ID
            $results = $infractionService->getInfractionsDetailsBySteamIdPaginated($steamId, $page, $perPage);
    
            // Send successful response with data
            ResponseHandler::sendResponse('success', $results, SUCCESS_OK);
        } catch (InvalidArgumentException $e) {
            throwError($e->getMessage(), ERROR_INVALID_INPUT);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }


public function searchForPlayerNamesOptionalType(?string $type = null){
    try {
        $infractionService = new InfractionService($this->dbConnection);
        $results = $infractionService->searchForPlayerNamesOptionalType($type);
        
        // Log the action
        $logAction = 'LOG_SEARCH_PLAYER_NAMES';
        $this->logger->log(0, $logAction, ['type' => $type]);

        // Send successful response with data
        ResponseHandler::sendResponse('success', ['result'=> $results], SUCCESS_OK);
    } catch (InvalidArgumentException $e) {
        throwError($e->getMessage(), ERROR_INVALID_INPUT);
    } catch (Exception $e) {
        throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
    }
}
    

    public function getInfractionsDetailsByAdminSteamId(string $adminSteamId)
{
    try {
        // Instantiate the service with database connection
        $infractionService = new InfractionService($this->dbConnection);

        // Fetch detailed information about infractions by Admin Steam ID
        $results = $infractionService->getInfractionsDetailsByAdminSteamId($adminSteamId);

        // Log the action
        // Log action code for fetching infraction details by Admin Steam ID
        $logAction = 'LOG_FETCH_INFRACTIONS_DETAILS_BY_ADMIN_STEAMID'; // Define this constant as needed
        $this->logger->log(0, $logAction, ['admin_steamid' => $adminSteamId]);

        // Send successful response with infraction details
        ResponseHandler::sendResponse('success', ['results' => $results], SUCCESS_OK);
    } catch (RuntimeException $e) {
        throwError($e->getMessage(), ERROR_NOT_FOUND); // Use NOT FOUND for infraction details not found
    } catch (Exception $e) {
        throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
    }
}



    public function checkInfractionsByAdminId(string $adminId) {
        try {
            // Instantiate the service with database connection
            $infractionService = new InfractionService($this->dbConnection);
            $result = $infractionService->checkInfractionsByAdminId($adminId);
    
            // Log the action
            // Log action code for checking infractions by Admin ID
            $logAction = 'LOG_CHECK_INFRACTIONS_BY_ADMINID'; // Define this constant as needed
            $this->logger->log(0, $logAction, ['admin_id' => $adminId]);
    
            // Send successful response with infraction details
            ResponseHandler::sendResponse('success', ['result' => $result], SUCCESS_OK);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_NOT_FOUND); // Use NOT FOUND for infraction not found
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }

    public function getInfractionDetailsByAdminIdPaginated(string $adminSteamId, int $page = null) {
        $page = $page ?? 1;
    
        $page = max($page, 1); // Ensure page is not less than 1
        $perPage = 10; // Define the number of items per page
    
        try {
            // Instantiate the service with database connection
            $infractionService = new InfractionService($this->dbConnection);
            
            // Fetch paginated infraction details by Admin ID
            $results = $infractionService->getInfractionsDetailsByAdminIdPaginated($adminSteamId, $page, $perPage);
    
            // Send successful response with data
            ResponseHandler::sendResponse('success', $results, SUCCESS_OK);
        } catch (InvalidArgumentException $e) {
            throwError($e->getMessage(), ERROR_INVALID_INPUT);
        } catch (RuntimeException $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        } catch (Exception $e) {
            throwError($e->getMessage(), ERROR_INTERNAL_SERVER);
        }
    }


    




    
    
    private function getInfractionTable(?string $type = null)
    {
        switch ($type) {
            case 'bans':
                return 'sa_bans';
            case 'comms':
                return 'sa_mutes';
            default:
                // If no type is provided, return a query that selects from both tables
                ResponseHandler::sendResponse('error', ['infractions' => $type], ERROR_INVALID_INPUT);
                die();
        }
    }






}