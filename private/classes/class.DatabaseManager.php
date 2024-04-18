<?php
class DatabaseManager {
    private $connectionParams = [];
    private $connections = [];

    /**
     * Add connection parameters to the manager.
     *
     * @param string $name       The name of the connection.
     * @param array  $params     The parameters for the connection.
     */
    public function addConnectionParams($name, array $params) {
        $this->connectionParams[$name] = $params;
    }

    /**
     * Get or create a database connection.
     *
     * @param string $name       The name of the connection.
     * @return DatabaseConnector The database connection.
     */
    public function getConnection($name = 'default') {
        if (!isset($this->connections[$name])) {
            if (!isset($this->connectionParams[$name])) {
                throw new Exception("Connection parameters for '{$name}' not found.");
            }
            $params = $this->connectionParams[$name];
            $this->connections[$name] = new DatabaseConnector(
                $params['host'], $params['port'], $params['db'], 
                $params['user'], $params['pass'], $params['charset']
            );
        }

        return $this->connections[$name];
    }


    public function getConnectionByDbName($name, $dbName) {
        $uniqueName = $name . '_' . $dbName; // Create a unique name for the connection
    
        if (!isset($this->connections[$uniqueName])) {
            if (!isset($this->connectionParams[$name])) {
                throw new Exception("Connection parameters for '{$name}' not found.");
            }
            $params = $this->connectionParams[$name];
            $params['db'] = $dbName; // Override the database name
    
            $this->connections[$uniqueName] = new DatabaseConnector(
                $params['host'], $params['port'], $params['db'],
                $params['user'], $params['pass'], $params['charset']
            );
        }
    
        return $this->connections[$uniqueName];
    }
    

}
?>