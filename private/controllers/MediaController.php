<?php
class MediaController
{
    protected $dbManager;

    const MEDIA_FOLDER = '/usr/www/igfastdl/imperfectandcompany-cdn/assets/tenant/imperfect_gamers/media/uploaded';
    const MAX_FILE_SIZE = 4000000; // 4MB
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    private $dbConnection;
    private $logger;

    public function __construct($dbManager, $logger)
    {
        $this->dbConnection = $dbManager->getConnectionByDbName('default', 'igfastdl_imperfectgamers_media');
        $this->logger = $logger;
    }

    private function validateFile($file)
    {
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception('File size exceeds the maximum limit of 4MB.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.');
        }
    }

    private function uploadFile($file, $destinationFolder)
    {
        $this->validateFile($file);

        if (!file_exists($destinationFolder)) {
            mkdir($destinationFolder, 0777, true);
        }

        $filePath = $destinationFolder . '/' . basename($file['name']);
        if (file_exists($filePath)) {
            throw new Exception('File already exists. Please rename the file and try again.');
        }

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to upload file.');
        }

        return $filePath;
    }

    public function createMedia()
    {
        if (empty($_FILES['upload']) || !isset($_FILES['upload']['name'])) {
            return ResponseHandler::sendResponse('error', ['message' => 'No file provided for upload'], 400);
        }

        $file = $_FILES['upload'];
        $folderId = $_POST['folder_id'] ?? null; // Folder ID from form data, if any

        $this->dbConnection->getConnection()->beginTransaction();

        try {
            // Determine the destination path based on the folder ID
            $destinationPath = $this->getFolderPathById($folderId);

            $filePath = $this->uploadFile($file, $destinationPath);

            // Insert into Media table
            $rows = 'current_version_id';
            $values = ':current_version_id';
            $filterParams = [['value' => null, 'type' => PDO::PARAM_NULL]];
            $this->dbConnection->insertData('Media', $rows, $values, $filterParams);

            $mediaId = $this->dbConnection->lastInsertId();
            $versionData = [
                'media_id' => $mediaId,
                'folder_id' => $folderId,
                'filename' => basename($file['name']),
                'filepath' => $filePath,
                'filesize' => $file['size'],
                'filetype' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
            ];
            $newVersionId = $this->createMediaVersion($versionData);

            // Log the creation action
            $this->logAction($newVersionId, 'media', 'create', null, 'Media created');

            $this->dbConnection->getConnection()->commit();
            return ResponseHandler::sendResponse('success', ['media_id' => $mediaId], 201);
        } catch (Exception $e) {
            $this->dbConnection->getConnection()->rollBack();
            $this->logger->log('error', 'create_media_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error creating media: ' . $e->getMessage()], 500);
        }
    }


    private function createMediaVersion($versionData)
    {
        // Extract fields and values for the insert statement
        $fields = implode(', ', array_keys($versionData));
        $placeholders = ':' . implode(', :', array_keys($versionData));
        $filterParams = [];
        foreach ($versionData as $key => $value) {
            $filterParams[] = [
                'value' => $value,
                'type' => is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
            ];
        }

        // Insert into MediaVersions table
        $this->dbConnection->insertData('MediaVersions', $fields, $placeholders, $filterParams);
        $versionId = $this->dbConnection->lastInsertId();

        // Update the Media table with the new version ID
        $setClause = 'current_version_id = :versionId';
        $whereClause = 'media_id = :mediaId';
        $updateParams = [
            ['value' => $versionId, 'type' => PDO::PARAM_INT],
            ['value' => $versionData['media_id'], 'type' => PDO::PARAM_INT],
        ];
        $this->dbConnection->updateData('Media', $setClause, $whereClause, $updateParams);

        return $versionId;
    }


    private function logAction($contextVersionId, $contextType, $action, $userId = null, $details = null)
    {
        $logData = [
            'context_version_id' => $contextVersionId,
            'context_type' => $contextType,
            'action' => $action,
            'user_id' => $userId,
            'details' => $details
        ];
        // Extract fields and values for the insert statement
        $fields = implode(', ', array_keys($logData));
        $placeholders = ':' . implode(', :', array_keys($logData));
        $filterParams = [];
        foreach ($logData as $key => $value) {
            $filterParams[] = [
                'value' => $value,
                'type' => is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
            ];
        }

        $this->dbConnection->insertData('MediaLogs', $fields, $placeholders, $filterParams);
    }

    private function getRootFolderId()
    {
        // If root folder ID logic is needed, implement it here
        return null;
    }

    public function createFolder()
    {
        if (empty($_POST['name'])) {
            return ResponseHandler::sendResponse('error', ['message' => 'Folder name is required'], 400);
        }

        $folderName = trim($_POST['name']);

        // Prevent the creation of a folder named "deleted"
        if (strtolower($folderName) === 'deleted') {
            return ResponseHandler::sendResponse('error', ['message' => 'The folder name "deleted" is reserved and cannot be used'], 400);
        }

        $parentFolderId = $_POST['parent_folder_id'] ?? null;

        // Generate the full path for the new folder
        $parentPath = $parentFolderId ? $this->getFolderPathById($parentFolderId) : self::MEDIA_FOLDER;
        $newFolderPath = $parentPath . '/' . $folderName;

        // Check if the directory already exists
        if (file_exists($newFolderPath)) {
            return ResponseHandler::sendResponse('error', ['message' => 'Folder already exists'], 400);
        }

        // Start a transaction
        $this->dbConnection->getConnection()->beginTransaction();

        try {
            // Insert into MediaFolder table
            $folderData = [
                'current_version_id' => null // Initially null, will update after creating the version
            ];
            $folderFields = implode(', ', array_keys($folderData));
            $folderPlaceholders = ':' . implode(', :', array_keys($folderData));
            $folderParams = [];
            foreach ($folderData as $key => $value) {
                $folderParams[] = [
                    'value' => $value,
                    'type' => is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
                ];
            }

            $this->dbConnection->insertData('MediaFolder', $folderFields, $folderPlaceholders, $folderParams);
            $folderId = $this->dbConnection->lastInsertId();

            // Insert into MediaFolderVersions table
            $versionData = [
                'folder_id' => $folderId,
                'parent_folder_id' => $parentFolderId,
                'name' => $folderName,
                'description' => $_POST['description'] ?? null
            ];
            $versionFields = implode(', ', array_keys($versionData));
            $versionPlaceholders = ':' . implode(', :', array_keys($versionData));
            $versionParams = [];
            foreach ($versionData as $key => $value) {
                $versionParams[] = [
                    'value' => $value,
                    'type' => is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
                ];
            }

            $this->dbConnection->insertData('MediaFolderVersions', $versionFields, $versionPlaceholders, $versionParams);
            $versionId = $this->dbConnection->lastInsertId();

            // Update the MediaFolder with the new version ID
            $setClause = 'current_version_id = :versionId';
            $whereClause = 'folder_id = :folderId';
            $updateParams = [
                ['value' => $versionId, 'type' => PDO::PARAM_INT],
                ['value' => $folderId, 'type' => PDO::PARAM_INT],
            ];
            $this->dbConnection->updateData('MediaFolder', $setClause, $whereClause, $updateParams);

            // Create the physical folder
            if (!mkdir($newFolderPath, 0777, true)) {
                throw new Exception('Failed to create the folder on the filesystem.');
            }

            // Commit the transaction
            $this->dbConnection->getConnection()->commit();

            // Log the action
            $this->logAction($versionId, 'folder', 'create');

            return ResponseHandler::sendResponse('success', ['folder_id' => $folderId], 201);
        } catch (Exception $e) {
            $this->dbConnection->getConnection()->rollBack();
            $this->logger->log('error', 'create_folder_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error creating folder: ' . $e->getMessage()], 500);
        }
    }

    private function getFolderPathById($folderId)
    {
        if ($folderId === null) {
            return self::MEDIA_FOLDER;
        }

        // Initialize an array to hold the path components
        $pathComponents = [];

        // Traverse up the folder hierarchy
        while ($folderId !== null) {
            $query = "
                SELECT 
                    mfv.name, 
                    mfv.parent_folder_id
                FROM MediaFolderVersions mfv
                WHERE mfv.folder_id = :folderId
                AND mfv.deleted_at IS NULL
                ORDER BY mfv.version_id DESC
                LIMIT 1
            ";

            $params = [
                ['value' => $folderId, 'type' => PDO::PARAM_INT]
            ];

            $result = $this->dbConnection->runQuery('single', $query, $params);

            if (!$result || empty($result['result'])) {
                throw new Exception('Folder path not found.');
            }

            // Add the folder name to the path components
            $pathComponents[] = $result['result']['name'];

            // Move to the parent folder
            $folderId = $result['result']['parent_folder_id'];
        }

        // Reverse the path components to build the correct path from root to the target folder
        $folderPath = self::MEDIA_FOLDER . '/' . implode('/', array_reverse($pathComponents));

        return $folderPath;
    }

    public function getAllMedia()
    {
        try {
            // Define the query to join Media, MediaVersions, and MediaFolderVersions tables
            $query = "SELECT 
                m.media_id, 
                mv.folder_id,
                mv.filename, 
                mv.filepath, 
                mv.filesize, 
                mv.filetype, 
                mv.description,
                mv.folder_id, 
                mv.created_at,
                mv.updated_at,
                mfv.name AS folder_name
                
            FROM Media m
            JOIN MediaVersions mv ON mv.version_id = m.current_version_id
            LEFT JOIN MediaFolderVersions mfv ON mv.folder_id = mfv.folder_id
            WHERE mv.deleted_at IS NULL
        ";

            // Execute the query using query method
            $mediaList = $this->dbConnection->query($query);

            // Check if the query execution was successful and returned data
            if ($mediaList) {
                return ResponseHandler::sendResponse('success', ['media' => $mediaList], 200);
            } else {
                return ResponseHandler::sendResponse('success', ['media' => []], 200);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_media_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error fetching media: ' . $e->getMessage()], 500);
        }
    }

    public function updateMedia()
    {
        // Read the raw POST data
        $rawData = file_get_contents('php://input');
        // Decode the JSON data
        $data = json_decode($rawData, true);

        // Check if media_id is set and not empty
        if (empty($data['media_id'])) {
            return ResponseHandler::sendResponse('error', ['message' => 'Media ID is required'], 400);
        }

        $mediaId = intval($data['media_id']);
        $newFilename = $data['filename'] ?? null;
        $newFolderId = $data['folder_id'] ?? null;
        $newDescription = $data['description'] ?? null;

        try {
            // Start a transaction
            $this->dbConnection->getConnection()->beginTransaction();

            // Fetch the current media version data
            $query = "SELECT 
                mv.version_id, 
                mv.filename, 
                mv.filepath, 
                mv.folder_id,
                mv.filesize,
                mv.filetype,
                mv.description
            FROM MediaVersions mv
            JOIN Media m ON mv.version_id = m.current_version_id
            WHERE m.media_id = :mediaId
            AND mv.deleted_at IS NULL
        ";
            $mediaVersion = $this->dbConnection->runQuery('single', $query, [['value' => $mediaId, 'type' => PDO::PARAM_INT]]);
            if (empty($mediaVersion)) {
                throw new Exception('Media not found or has been deleted.');
            }

            $currentFilename = $mediaVersion['result']['filename'];
            $currentFilePath = $mediaVersion['result']['filepath'];
            $currentFolderId = $mediaVersion['result']['folder_id'];
            $currentFileSize = $mediaVersion['result']['filesize'];
            $currentFileType = $mediaVersion['result']['filetype'];
            $currentDescription = $mediaVersion['result']['description'];


            // Track changes
            $changes = [];

            // Determine new file path if filename or folder_id is changing
            $newFilePath = $currentFilePath;
            if ($newFilename && $newFilename !== $newFilename . '.' . $currentFileType) {
                $newFilePath = dirname($currentFilePath) . '/' . $newFilename . '.' . $currentFileType;
                rename($currentFilePath, $newFilePath); // Rename the file on the filesystem
                $changes[] = ['action' => 'rename', 'details' => "Renamed file from $currentFilename to " . $newFilename . '.' . $currentFileType];
            }
            if ($newFolderId && $newFolderId !== $currentFolderId) {
                $newFolderPath = $this->getFolderPathById($newFolderId);
                $newFilePath = $newFolderPath . '/' . basename($newFilePath);
                rename($currentFilePath, $newFilePath); // Move the file to the new folder
                $changes[] = ['action' => 'move', 'details' => "Moved file to folder ID $newFolderId"];
            }

            // Check for description changes
            if ($newDescription !== $currentDescription) {
                $changeType = $newDescription ? ($currentDescription ? 'updated' : 'added') : 'removed';
                $changes[] = ['action' => $changeType, 'details' => "Description $changeType"];
            }

            // Insert new version in MediaVersions
            $versionData = [
                'media_id' => $mediaId,
                'folder_id' => $newFolderId ?? $currentFolderId,
                'filename' => $newFilename . '.' . $currentFileType ?? $currentFilename,
                'filepath' => $newFilePath ?? $currentFilePath,
                'filesize' => $currentFileSize,
                'filetype' => $currentFileType,
                'description' => $newDescription ?? $currentDescription
            ];

            $newVersionId = $this->createMediaVersion($versionData);

            // Update the Media table with the new version ID
            $this->dbConnection->updateData(
                'Media',
                'current_version_id = :newVersionId',
                'media_id = :mediaId',
                [
                    ['value' => $newVersionId, 'type' => PDO::PARAM_INT],
                    ['value' => $mediaId, 'type' => PDO::PARAM_INT]
                ]
            );

            // Log actions for each change
            foreach ($changes as $change) {
                $this->logAction($newVersionId, 'media', $change['action'], null, $change['details']);
            }

            // Commit the transaction
            $this->dbConnection->getConnection()->commit();

            return ResponseHandler::sendResponse('success', ['media_id' => $mediaId], 200);
        } catch (Exception $e) {
            $this->dbConnection->getConnection()->rollBack();
            $this->logger->log('error', 'update_media_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error updating media: ' . $e->getMessage()], 500);
        }
    }


    public function deleteMedia()
    {
        if (empty($_POST['media_id'])) {
            return ResponseHandler::sendResponse('error', ['message' => 'Media ID is required'], 400);
        }

        $mediaId = intval($_POST['media_id']);

        try {
            // Start a transaction
            $this->dbConnection->getConnection()->beginTransaction();

            // Fetch the current media version data
            $query = "SELECT 
                        mv.version_id, 
                        mv.filename, 
                        mv.filepath, 
                        mv.folder_id
                        mv.description,
                        mv.created_at,
                        mv.updated_at
                    FROM MediaVersions mv
                    JOIN Media m ON mv.version_id = m.current_version_id
                    WHERE m.media_id = :mediaId
                    AND mv.deleted_at IS NULL";
            $mediaVersion = $this->dbConnection->runQuery('single', $query, [['value' => $mediaId, 'type' => PDO::PARAM_INT]]);
            if (empty($mediaVersion)) {
                throw new Exception('Media not found or has been deleted.');
            }

            $currentFilePath = $mediaVersion['result']['filepath'];
            $currentFilename = $mediaVersion['result']['filename'];

            // Move the file to a deleted directory
            $deletedDir = self::MEDIA_FOLDER . '/deleted';
            if (!file_exists($deletedDir)) {
                mkdir($deletedDir, 0777, true);
            }
            $deletedFilePath = $deletedDir . '/' . basename($currentFilePath);
            rename($currentFilePath, $deletedFilePath); // Move the file to the deleted directory

            // Mark the current version as deleted
            $this->dbConnection->updateData(
                'MediaVersions',
                'deleted_at = NOW()',
                'version_id = :versionId',
                [['value' => $mediaVersion['result']['version_id'], 'type' => PDO::PARAM_INT]]
            );

            // Log the delete action
            $this->logAction($mediaVersion['result']['version_id'], 'media', 'delete', null, 'Media deleted and moved to deleted directory');

            // Commit the transaction
            $this->dbConnection->getConnection()->commit();

            return ResponseHandler::sendResponse('success', ['message' => 'Media deleted successfully'], 200);
        } catch (Exception $e) {
            $this->dbConnection->getConnection()->rollBack();
            $this->logger->log('error', 'delete_media_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error deleting media: ' . $e->getMessage()], 500);
        }
    }


    public function getMediaById(int $media_id)
    {
        try {
            $query = "SELECT 
                    m.media_id, 
                    mv.folder_id,
                    mv.filename, 
                    mv.filepath, 
                    mv.filesize, 
                    mv.filetype, 
                    mv.description, 
                    mv.created_at,
                    mv.updated_at,
                    mfv.name AS folder_name
                FROM Media m
                JOIN MediaVersions mv ON mv.version_id = m.current_version_id
                LEFT JOIN MediaFolderVersions mfv ON mv.folder_id = mfv.folder_id
                WHERE m.media_id = :mediaId
                AND mv.deleted_at IS NULL
            ";

            $media = $this->dbConnection->runQuery('single', $query, [['value' => $media_id, 'type' => PDO::PARAM_INT]]);

            if ($media) {
                return ResponseHandler::sendResponse('success', ['media' => $media['result']], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'Media not found'], 404);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_media_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error fetching media: ' . $e->getMessage()], 500);
        }
    }

    public function getFolderContents(int $folder_id)
    {
        try {
            $query = "SELECT 
                    m.media_id, 
                    mv.filename, 
                    mv.filepath, 
                    mv.filesize, 
                    mv.filetype, 
                    mv.description, 
                    mv.created_at,
                    mv.updated_at
                FROM Media m
                JOIN MediaVersions mv ON mv.version_id = m.current_version_id
                WHERE mv.folder_id = :folderId
                AND mv.deleted_at IS NULL
            ";

            $filterParams = [
                ':folderId' => $folder_id,
            ];

            $mediaList = $this->dbConnection->query($query, $filterParams);

            $folderQuery = "SELECT 
                    mf.folder_id,
                    mfv.name, 
                    mfv.description,
                    mfv.created_at,
                    mfv.updated_at
                FROM MediaFolder mf
                JOIN MediaFolderVersions mfv ON mf.current_version_id = mfv.version_id
                WHERE mfv.parent_folder_id = :folderId
                AND mfv.deleted_at IS NULL
            ";

            $folderList = $this->dbConnection->query($folderQuery, $filterParams);

            return ResponseHandler::sendResponse('success', ['media' => $mediaList, 'folders' => $folderList], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_folder_contents_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error fetching folder contents: ' . $e->getMessage()], 500);
        }
    }

    public function getTopLevelFoldersAndRootMedia()
    {
        try {
            $query = "SELECT 
                    m.media_id, 
                    mv.filename, 
                    mv.filepath, 
                    mv.filesize, 
                    mv.filetype, 
                    mv.description, 
                    mv.created_at,
                    mv.updated_at
                FROM Media m
                JOIN MediaVersions mv ON mv.version_id = m.current_version_id
                WHERE mv.folder_id IS NULL
                AND mv.deleted_at IS NULL
            ";

            $rootMediaList = $this->dbConnection->query($query);

            $folderQuery = "SELECT 
                    mf.folder_id,
                    mfv.name, 
                    mfv.description,
                    mfv.created_at,
                    mfv.updated_at
                FROM MediaFolder mf
                JOIN MediaFolderVersions mfv ON mf.current_version_id = mfv.version_id
                WHERE mfv.parent_folder_id IS NULL
                AND mfv.deleted_at IS NULL
            ";

            $topLevelFolders = $this->dbConnection->query($folderQuery);

            return ResponseHandler::sendResponse('success', ['root_media' => $rootMediaList, 'folders' => $topLevelFolders], 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_top_level_contents_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error fetching top-level contents: ' . $e->getMessage()], 500);
        }
    }


    public function getMediaLogs()
    {
        try {
            $query = "SELECT 
                        log_id, 
                        context_version_id, 
                        context_type, 
                        action, 
                        user_id, 
                        details, 
                        created_at 
                      FROM MediaLogs 
                      ORDER BY created_at DESC";

            $logs = $this->dbConnection->query($query);

            if ($logs) {
                return ResponseHandler::sendResponse('success', ['logs' => $logs], 200);
            } else {
                return ResponseHandler::sendResponse('error', ['message' => 'No logs found'], 404);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'fetch_logs_error', ['error' => $e->getMessage()]);
            return ResponseHandler::sendResponse('error', ['message' => 'Error fetching logs: ' . $e->getMessage()], 500);
        }
    }

}
