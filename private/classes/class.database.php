<?php
class DatabaseConnector
{
    private $dbConnection = null;
    private $serverDetails = [];

    public function __construct($host, $port, $db, $user, $pass, $charset = 'utf8mb4')
    {
        $this->serverDetails = [
            'host' => $host,
            'port' => $port,
            'database' => $db
        ];
        $this->addConnection($host, $port, $db, $user, $pass, $charset);
    }

    public function addConnection($host, $port, $db, $user, $pass, $charset = 'utf8mb4')
    {
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $this->dbConnection = new PDO($dsn, $user, $pass, $options); // Set the default dbConnection
        } catch (\PDOException $e) {
            // Handle exception
            $GLOBALS['messages']['errors'][] = $e->getMessage();
        }
    }

    /**
     * Returns the count of rows in a table
     *
     * @param string $table The name of the table to count rows for
     * @param array|null $filter_params An ptional array of filter parameters to use in the query
     * @param string|null $query An optional WHERE clause to use in the query
     *
     * @return int|false Returns the count of rows or false on error
     */
    public function viewCount($table, $filter_params = null, $query = null)
    {
        try {
            $stmt = $this->dbConnection->prepare("SELECT * FROM $table $query");
            if ($filter_params) {
                foreach ($filter_params as $key => $data) {
                    $key++;
                    $stmt->bindParam($key, $data['value'], $data['type']);
                }
            }
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            $GLOBALS['messages']['errors'][] = $e->getMessage();
            return false;
        }
    }

    /**
     * Executes a query
     *
     * @param string $query The query to execute
     * @param array $params An optional array of parameters to bind to the query
     *
     * @return array|false Returns an array of rows on success or false on error
     */
    public function query($query, $params = array())
    {

        // Convert a single value to an array
        if (!is_array($params)) {
            $params = array($params);
        }

        $filterParams = array();

        foreach ($params as $value) {
            $type = PDO::PARAM_STR; // Default to string type

            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }

            $filterParams[] = array('value' => $value, 'type' => $type);
        }

        try {

            $statement = $this->dbConnection->prepare($query);
            if ($filterParams) {
                foreach ($filterParams as $key => $data) {
                    $key++;
                    $statement->bindParam($key, $data['value'], $data['type']);
                }
            }

            $statement->execute();


            //if the first keyword in the query is select, then run this.
            if (explode(' ', $query)[0] == 'SELECT' && explode(' ', $query)[1] != 'count(*)') {
                $data = $statement->fetchAll();
                return $data;
            }

            //if the second keyword in the query is select, then run this.
            if (explode(' ', $query)[0] == 'SELECT' && explode(' ', $query)[1] == 'count(*)') {
                $data = $statement->fetch();
                $data = $data['total'];
                return $data;
            }

        } catch (Exception $e) {
            // Log the executed query and parameters (for debugging)
            throwWarning($this->handleDatabaseException($e, $query, $params));
            return false;
        } catch (\PDOException $e) {
            // Log the executed query and parameters (for debugging)
            throwWarning($this->handleDatabaseException($e, $query, $params));
            if ($e->getCode() === '23000') {
                $GLOBALS['messages']['errors'][] = '<b>UNIQUE CONSTRAINT: </b>' . $e->getMessage();
            } else {
                $GLOBALS['messages']['errors'][] = '<b>INTERNAL ERROR: </b>' . $e->getMessage();
            }
            return false;
        } catch (PDOException $e) {
            // Log the executed query and parameters (for debugging)
            throwWarning($this->handleDatabaseException($e, $query, $params));
            if ($e->getCode() === '23000') {
                $GLOBALS['messages']['errors'][] = '<b>UNIQUE CONSTRAINT: </b>' . $e->getMessage();
            } else {
                $GLOBALS['messages']['errors'][] = '<b>INTERNAL ERROR: </b>' . $e->getMessage();
            }
            return false;
        }
    }

    // Getter method for dbConnection
    public function getConnection()
    {
        return $this->dbConnection;
    }

    /**
     * Handles database exceptions, enhancing error reporting with detailed information.
     *
     * @param Exception $e The exception object containing error details.
     * @param string $query The SQL query that triggered the exception.
     * @param array $params The parameters used in the SQL query.
     *
     * @return string A detailed error message with possible causes and solutions.
     */
    function handleDatabaseException(Exception $e, string $query, array $params)
    {
        $serverDetails = $this->serverDetails;

        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        $traceDetails = $e->getTrace();

        $summary = [];
        foreach ($traceDetails as $index => $frame) {
            $func = isset($frame['function']) ? $frame['function'] . '()' : 'N/A';
            $file = isset($frame['file']) ? basename($frame['file']) : 'N/A';
            $line = isset($frame['line']) ? $frame['line'] : 'N/A';
            $summary[] = "Step " . ($index + 1) . ": Called $func in $file on line $line";
        }

        $specifics = "<br><strong>Error message:</strong><br>$errorMessage<br>";
        $specifics .= "<br><strong>Call Stack:</strong><br>";

        foreach ($summary as $step) {
            $specifics .= $step . "<br>";
        }

        // Attempt to extract database name from error message
        preg_match("/'([\w_]+)\./", $errorMessage, $matches);
        $databaseName = $matches[1] ?? 'unknown';

        // Extract the table name and constraint name from the error message
        preg_match('/`([^`]+)`\.`([^`]+)`/', $errorMessage, $matches);
        $tableName = $this->extractTableNameFromError($errorMessage);
        $constraintName = $matches[2] ?? 'Unknown';

        // Check if the exception is a foreign key constraint violation
        if ($errorCode === '23000') {

            // Provide a clear explanation of the issue
            $explanation = "<strong>A foreign key constraint violation occurred</strong> while executing the SQL query in the '<strong>$databaseName</strong>' database:<br><br>";
            $explanation .= "<pre>$query</pre><br>With the following parameters:<br><pre>";
            $explanation .= print_r($params, true) . "</pre><br>";
            $explanation .= "This error typically occurs when you're attempting to insert, update, or delete a row that has a foreign key reference to another table, but the referenced row doesn't exist or has been deleted.<br><br>";

            // Offer a step-by-step breakdown for debugging
            $breakdown = "<strong>Step-by-Step Breakdown:</strong><br>";
            $breakdown .= "1. The query attempted to perform an operation on the '<strong>$tableName</strong>' table.<br>";
            $breakdown .= "2. The operation violated the '<strong>$constraintName</strong>' foreign key constraint.<br>";
            $breakdown .= "3. This constraint ensures that the values in the foreign key column(s) correspond to existing values in the referenced table.<br>";
            $breakdown .= "4. The query failed because the referenced row(s) were not found or had been deleted.<br><br>";

            // Suggest possible causes and solutions
            $suggestions = "<strong>Possible Causes and Solutions:</strong><br>";
            $suggestions .= "- The referenced row(s) in the related table may have been deleted or never existed.<br>";
            $suggestions .= "- You may have tried to insert or update a row with an invalid foreign key value.<br>";
            $suggestions .= "- There could be a bug in your application logic that is causing incorrect foreign key values to be used.<br><br>";
            $suggestions .= "To resolve this issue, you should:<br>";
            $suggestions .= "1. Verify that the foreign key value(s) being used are valid and correspond to existing rows in the referenced table.<br>";
            $suggestions .= "2. Check your application logic to ensure that foreign key values are being handled correctly.<br>";
            $suggestions .= "3. Consider modifying your database schema or application logic to handle foreign key violations more gracefully (e.g., using cascade delete or updating related rows).<br>";

            // Display the detailed explanation
            return ($explanation . $specifics . $breakdown . $suggestions);
        } elseif ($errorCode === '42S02') {
            $schemaCheck = $this->checkDatabaseSchema($databaseName, $tableName);

            // Handle "table or view not found" error
            $explanation = "<strong>A 'table or view not found' error occurred</strong> while executing the SQL query in the '<b>$databaseName</b>' database:<br><br>";
            $explanation .= "<pre>$query</pre><br>With the following parameters:<br><pre>";
            $explanation .= print_r($params, true) . "</pre><br>";
            $explanation .= "This error occurs when the query attempts to access a table or view that does not exist in the database.<br>";

            $explanation .= "<br><strong>Server:</strong><br> {$serverDetails['host']} on port {$serverDetails['port']} <br><br><strong>Database:</strong><br> {$serverDetails['database']}<br>";
            $explanation .= "<br>" . $schemaCheck; // Include schema validation results


            $breakdown = "<br><strong>Debugging Steps:</strong><br>";
            $breakdown .= "1. Verify that the table or view name in the query is spelled correctly.<br>";
            $breakdown .= "2. Ensure that the correct database is being queried and that the table or view exists in that database.<br>";
            $breakdown .= "3. Check for case sensitivity in table or view names if the database system is case-sensitive.<br>";

            $suggestions = "<br><strong>Possible Causes and Solutions:</strong><br>";
            $suggestions .= "- The table or view name may have been misspelled.<br>";
            $suggestions .= "- The table or view may have been deleted or not created.<br>";
            $suggestions .= "- There might be a misconfiguration in the database connection settings that is pointing to the wrong database.";

            return ($explanation . $specifics . $breakdown . $suggestions);
            // Handling SQLSTATE[HY093]: Invalid parameter number
        } else if ($errorCode === 'HY093') {
            $explanation = "<strong>Invalid parameter number error occurred</strong> while executing the SQL query:<br><br>";
            $explanation .= "<pre>$query</pre><br>With the following parameters:<br><pre>";
            $explanation .= print_r($params, true) . "</pre><br>";
            $explanation .= "This error typically occurs when the number of placeholders in the SQL query does not match the number of parameters provided.<br><br>";

            $debuggingSteps = "<br><br><strong>Debugging Steps:</strong><br><br>";
            $debuggingSteps .= "1. Verify that every placeholder in the query (usually a '?' for unnamed or ':name' for named placeholders) has a corresponding parameter in the provided parameter array.<br>";
            $debuggingSteps .= "2. Check if any parameter is missing or extra in the parameter array.<br>";
            $debuggingSteps .= "3. Ensure that parameters are passed in the correct order, especially for unnamed placeholders.<br><br>";

            $solutions = "<strong>Possible Solutions:</strong><br><br>";
            $solutions .= "- Adjust the query or the parameter array to ensure that their counts match.<br>";
            $solutions .= "- Review the logic that populates the parameter array to catch discrepancies early.<br>";

            return ($explanation . $specifics . $debuggingSteps . $solutions);
        } else {
            // Handle other types of exceptions
            return ("An unexpected database exception occurred: " . $errorMessage);
        }
    }

    function extractTableNameFromError($errorMessage)
    {
        // Pattern to extract table name, handling different quoting and schema inclusion
        if (
            preg_match("/'([a-zA-Z0-9_\.]+)'/", $errorMessage, $matches) ||  // Handles single quotes and schema.table format
            preg_match("/`([a-zA-Z0-9_\.]+)`/", $errorMessage, $matches)
        ) {  // Handles backticks and schema.table format
            // Extract table name which might include schema name
            $fullTableName = explode('.', $matches[1]);
            return end($fullTableName);  // Returns the table name part if schema.table, else just table
        }
        return 'Unknown';  // Return 'Unknown' if not found
    }

    function checkDatabaseSchema($databaseName, $tableName)
    {
        $connection = $this->dbConnection;  // Use your established database connection

        try {
            // Ensure the table name is a valid SQL identifier to prevent SQL injection
            if (!preg_match('/^[a-zA-Z0-9_$]+$/', $tableName)) {
                throw new InvalidArgumentException("Invalid table name");
            }

            // Prepare the SQL statement to check for the table
            $sql = "SHOW TABLES LIKE '$tableName'";  // Direct inclusion of the table name, use with caution
            $stmt = $connection->query($sql);
            $result = $stmt->fetchAll();

            if (empty($result)) {
                // Table does not exist
                return "<strong>No such table:</strong><br> The table '$tableName' does not exist in the database '$databaseName'.<br>";
            } else {
                // Table exists
                return "<strong>Table found:</strong><br> The table '$tableName' exists in the database '$databaseName'.<br>";
            }
        } catch (PDOException $e) {
            // Handle potential errors in a real-world scenario appropriately
            return "<strong>Database error:</strong> " . $e->getMessage() . "<br>";
        } catch (InvalidArgumentException $e) {
            // Handle invalid table names
            return "<strong>Input error:</strong> " . $e->getMessage() . "<br>";
        }
    }


    /**
     * Retrieves rows from a specified table.
     *
     * @param string $table The name of the table to retrieve data from
     * @param string $select The columns to select (default is '*')
     * @param string|null $query An optional WHERE clause to use in the query
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return array|false An array of database rows that match the query parameters or false on error
     */
    public function viewData($table, $select = '*', $query = null, $filter_params = null)
    {
        try {
            $stmt = $this->dbConnection->prepare("SELECT $select FROM $table $query");
            if ($filter_params) {
                foreach ($filter_params as $key => $data) {
                    $key++;
                    $stmt->bindParam($key, $data['value'], $data['type']);
                }
            }
            $stmt->execute();
            return array("count" => $stmt->rowCount(), "results" => $stmt->fetchAll());
        } catch (Exception $e) {
            $GLOBALS['messages']['errors'][] = '<b>Error: </b>' . $e->getMessage();
            return false;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $GLOBALS['messages']['errors'][] = '<b>UNIQUE CONSTRAINT: </b>' . $e->getMessage();
            } else {
                $GLOBALS['messages']['errors'][] = '<b>INTERNAL ERROR: </b>' . $e->getMessage();
            }
            return false;
        }
    }

    /**
     * Retrieves a single row from a specified table.
     *
     * @param string $table The name of the table to retrieve data from
     * @param string $select The columns to select (default is '*')
     * @param string|null $query An optional WHERE clause to use in the query
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return array|false An array with a single database row that matches the query parameters or false on error
     */
    public function viewSingleData($table, $select = '*', $where = null, $filter_params = null)
    {
        return $this->runQuery("single", 'SELECT ' . $select . ' FROM ' . $table . ' ' . $where . ' LIMIT 1', $filter_params);
    }

    /**
     * Inserts a new row into a specified table with the given column names and values.
     *
     * @param string $table The name of the table to insert data into
     * @param string $rows The columns to insert data into
     * @param string $values The values to insert
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return array|false An array with the ID of the last inserted row or false on error
     */
    public function insertData($table, $rows, $values, $filter_params = null)
    {
        return $this->runQuery("insert", 'INSERT INTO ' . $table . ' (' . $rows . ') VALUES (' . $values . ')', $filter_params);
    }

    /**
     * Inserts a new row into a specified table with the given column names and values,
     * or updates an existing row with the specified values if a duplicate key is found.
     *
     * @param string $table The name of the table to insert data into
     * @param string $rows The columns to insert data into
     * @param string $values The values to insert
     * @param string $update_values The values to update in case of a duplicate key
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return array|false An array with the ID of the last inserted row or an array with the ID of the row that was updated, or false on error
     */
    public function insertDataUnique($table, $rows, $values, $update_values, $filter_params = null)
    {
        return $this->runQuery("insert", 'INSERT INTO ' . $table . ' (' . $rows . ') VALUES (' . $values . ') ON DUPLICATE KEY UPDATE ' . $update_values, $filter_params);
        /*INSERT INTO t1 (a,b,c) VALUES (1,2,3),(4,5,6)  ON DUPLICATE KEY UPDATE c=VALUES(a)+VALUES(b);*/
    }

    /**
     * Updates data in a specified table with the given values, filtered as specified.
     *
     * @param string $table The name of the table to update
     * @param string $setClause The SET clause specifying the columns and values to update
     * @param string|null $whereClause An optional WHERE clause to specify the rows to update
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return true|false True if the data was updated successfully, false otherwise
     */
    public function updateData($table, $setClause, $whereClause = null, $filter_params = null)
    {
        $whereClause = $whereClause !== null ? ' WHERE ' . $whereClause : null;
        $query = 'UPDATE ' . $table . ' SET ' . $setClause . $whereClause;
        return $this->runQuery("update", $query, $filter_params);
    }

    /**
     * Deletes data from a specified table, filtered as specified.
     *
     * @param string $table The name of the table to delete data from
     * @param string $rows The rows to delete (e.g., 'WHERE id = :id')
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return true|false True if the data was deleted successfully, false otherwise
     */
    public function deleteData($table, $rows, $filter_params = null)
    {
        return $this->runQuery("delete", 'DELETE FROM ' . $table . ' ' . $rows, $filter_params);
    }

    /**
     * Executes a specified SQL query and returns the result.
     *
     * @param string $type The type of query (insert, update, single, delete)
     * @param string $query The SQL query to execute
     * @param array|null $filter_params An optional array of filter parameters to use in the query
     *
     * @return array|true|false An array with the ID of the last inserted row, or an array with the ID of the row that was updated, or an array with a single database row that matches the query parameters, or true/false depending on the query type
     */
    public function runQuery($type, $query, $filter_params)
    {
        try {
            $stmt = $this->dbConnection->prepare($query);
            if ($filter_params) {
                foreach ($filter_params as $key => $data) {
                    $key++;
                    $stmt->bindParam($key, $data['value'], $data['type']);
                }
            }
            $stmt->execute();
            switch ($type) {
                case "single":
                    return array("count" => $stmt->rowCount(), "result" => $stmt->fetch());
                    break;
                case "insert": //insert
                    return array("insertID" => $this->dbConnection->lastInsertId());
                    break;
                case "update": //insert
                    return array("insertID" => $this->dbConnection->lastInsertId());
                    return true;
                    break;
                case "delete": //insert
                    return array("insertID" => $this->dbConnection->lastInsertId());
                    return true;
                    break;
                default:
                    throw new Exception('No query type was specified.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $GLOBALS['messages']['errors'][] = '<b>Error: </b>' . $e->getMessage();
            return false;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $GLOBALS['messages']['errors'][] = '<b>UNIQUE CONSTRAINT: </b>' . $e->getMessage();
            } else {
                $GLOBALS['messages']['errors'][] = '<b>INTERNAL ERROR: </b>' . $e->getMessage();
            }
            return false;
        }
    }
}