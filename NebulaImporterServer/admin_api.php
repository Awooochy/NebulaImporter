<?php
//Located at NebulaImporterServer/admin_api.php

//PHP Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//All files are in the same directory as admin_api.php
define('RESOURCES_JSON', __DIR__ . '/resources_and_buttons.json');
define('UPLOAD_DIR', __DIR__ . '/FileResources/');

// !!! IMPORTANT: CHANGE THIS TO A STRONG, UNIQUE SECRET KEY !!!
define('ADMIN_SECRET_KEY', 'AdminKeyPassGoesHere');


function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); //For development, allow all. Restrict in production.
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Admin-Key, Content-Type');

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse([], 204);
}

//Basic Auth
function authenticate() {
    $providedKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? $_POST['admin_key'] ?? '';

    if ($providedKey !== ADMIN_SECRET_KEY) {
        sendJsonResponse(["error" => "Unauthorized: Invalid Admin Key."], 401);
    }
}
authenticate();

//Ensure the upload directory exists and is writable
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        sendJsonResponse(["error" => "Failed to create upload directory: " . UPLOAD_DIR], 500);
    }
}
if (!is_writable(UPLOAD_DIR)) {
    sendJsonResponse(["error" => "Upload directory is not writable: " . UPLOAD_DIR . ". Please check permissions."], 500);
}


//Get the action from the request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_data':
        if (!file_exists(RESOURCES_JSON)) {
            sendJsonResponse(["error" => "resources_and_buttons.json not found at " . RESOURCES_JSON], 500);
        }
        $resourcesData = file_get_contents(RESOURCES_JSON);
        if ($resourcesData === false) {
             sendJsonResponse(["error" => "Failed to read resources_and_buttons.json"], 500);
        }
        $decodedData = json_decode($resourcesData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse(["error" => "Error decoding resources_and_buttons.json: " . json_last_error_msg()], 500);
        }
        sendJsonResponse($decodedData);
        break;

    case 'upload_file':
        if (empty($_FILES['unityPackage'])) {
            sendJsonResponse(["error" => "No file uploaded or file input name is incorrect (expected 'unityPackage')."], 400);
        }

        $file = $_FILES['unityPackage'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendJsonResponse(["error" => "File upload error: " . $file['error']], 500);
        }

        $fileName = basename($file['name']);
        $targetFilePath = UPLOAD_DIR . $fileName;
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension !== 'unitypackage') {
            sendJsonResponse(["error" => "Invalid file type. Only .unitypackage files are allowed. Got: " . $fileExtension], 400);
        }

        if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
            sendJsonResponse(["error" => "File size exceeds limit (50MB)."], 400);
        }

        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            //Construct the full public URL for the uploaded file
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            //This path directly reflects your server structure
            $publicPath = '/NebulaImporter/NebulaImporterServer/FileResources/' . $fileName;
            $fullUrl = $protocol . "://" . $host . $publicPath;

            sendJsonResponse(["message" => "File uploaded successfully", "filename" => $fileName, "url" => $fullUrl]);
        } else {
            sendJsonResponse(["error" => "Failed to move uploaded file. Check server logs for details."], 500);
        }
        break;

    case 'add_or_update_resource':
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name']) || empty($input['url']) || !isset($input['access_level']) || !isset($input['sectionIndex']) || empty($input['label'])) {
            sendJsonResponse(["error" => "Missing required fields: name, url, access_level, sectionIndex, or label."], 400);
        }

        $resourceName = $input['name'];
        $resourceURL = $input['url'];
        $accessLevel = (int)$input['access_level'];
        $sectionIndex = (int)$input['sectionIndex'];
        $buttonLabel = $input['label'];

        if (!file_exists(RESOURCES_JSON)) {
            sendJsonResponse(["error" => "resources_and_buttons.json not found."], 500);
        }
        $resourcesData = file_get_contents(RESOURCES_JSON);
        if ($resourcesData === false) { sendJsonResponse(["error" => "Failed to read resources_and_buttons.json for update"], 500); }

        $data = json_decode($resourcesData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse(["error" => "Error decoding resources_and_buttons.json: " . json_last_error_msg()], 500);
        }

        $data['resources'][$resourceName] = [
            "url" => $resourceURL,
            "access_level" => $accessLevel
        ];

        if (isset($data['buttons'][$sectionIndex])) {
            $found = false;
            // If buttonIndex is provided and is an integer, try to update existing button
            if (isset($input['buttonIndex']) && is_int($input['buttonIndex'])) {
                $buttonIndex = (int)$input['buttonIndex'];
                if (isset($data['buttons'][$sectionIndex]['buttons'][$buttonIndex])) {
                    $data['buttons'][$sectionIndex]['buttons'][$buttonIndex]['label'] = $buttonLabel;
                    $data['buttons'][$sectionIndex]['buttons'][$buttonIndex]['name'] = $resourceName;
                    $found = true;
                }
            }

            // If not found by index, try to replace an "Empty" slot or add new
            if (!$found) {
                $addedToEmpty = false;
                foreach ($data['buttons'][$sectionIndex]['buttons'] as $key => $button) {
                    if ($button['name'] === "Empty") {
                        $data['buttons'][$sectionIndex]['buttons'][$key]['label'] = $buttonLabel;
                        $data['buttons'][$sectionIndex]['buttons'][$key]['name'] = $resourceName;
                        $addedToEmpty = true;
                        break;
                    }
                }
                if (!$addedToEmpty) {
                    // Add new button if no empty slot found
                    $data['buttons'][$sectionIndex]['buttons'][] = [
                        "label" => $buttonLabel,
                        "name" => $resourceName
                    ];
                }
            }
        } else {
            sendJsonResponse(["error" => "Invalid section index: " . $sectionIndex], 400);
        }

        if (file_put_contents(RESOURCES_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
            sendJsonResponse(["message" => "Resource and button updated successfully."]);
        } else {
            sendJsonResponse(["error" => "Failed to write to resources file at " . RESOURCES_JSON . ". Check permissions."], 500);
        }
        break;

    case 'delete_resource':
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name'])) {
            sendJsonResponse(["error" => "Resource name is required for deletion."], 400);
        }

        $resourceToDelete = $input['name'];

        if (!file_exists(RESOURCES_JSON)) {
            sendJsonResponse(["error" => "resources_and_buttons.json not found for deletion."], 500);
        }
        $resourcesData = file_get_contents(RESOURCES_JSON);
        if ($resourcesData === false) { sendJsonResponse(["error" => "Failed to read resources_and_buttons.json for deletion"], 500); }

        $data = json_decode($resourcesData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse(["error" => "Error decoding resources_and_buttons.json: " . json_last_error_msg()], 500);
        }

        // Delete from resources
        if (isset($data['resources'][$resourceToDelete])) {
            unset($data['resources'][$resourceToDelete]);
        } else {
            error_log("Warning: Resource '" . $resourceToDelete . "' not found in 'resources' data, but proceeding with button cleanup.");
        }

        // Clear button entry (set to "Empty")
        $foundInButtons = false;
        foreach ($data['buttons'] as $sectionKey => $sectionData) {
            foreach ($sectionData['buttons'] as $buttonKey => $buttonInfo) {
                if ($buttonInfo['name'] === $resourceToDelete) {
                    $data['buttons'][$sectionKey]['buttons'][$buttonKey]['label'] = "Empty";
                    $data['buttons'][$sectionKey]['buttons'][$buttonKey]['name'] = "Empty";
                    $foundInButtons = true;
                }
            }
        }

        if (!$foundInButtons && !isset($data['resources'][$resourceToDelete])) {
            // Only send 404 if neither resource nor button was found
             sendJsonResponse(["error" => "Resource '" . $resourceToDelete . "' not found in resources or buttons."], 404);
        }

        // Delete the actual file
        $filePathToDelete = UPLOAD_DIR . $resourceToDelete . '.unitypackage';
        if (file_exists($filePathToDelete)) {
            if (!unlink($filePathToDelete)) {
                error_log("Error: Failed to delete file: " . $filePathToDelete . ". Check permissions.");
            }
        } else {
            error_log("Info: File not found on server for deletion: " . $filePathToDelete);
        }

        if (file_put_contents(RESOURCES_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
            sendJsonResponse(["message" => "Resource and associated buttons removed. File deleted from server if found."]);
        } else {
            sendJsonResponse(["error" => "Failed to update resources file after deletion. Check permissions."], 500);
        }
        break;
    
    case 'add_or_update_section':
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['sectionName'])) {
            sendJsonResponse(["error" => "Section name is required."], 400);
        }

        $sectionName = $input['sectionName'];
        $sectionIndex = isset($input['sectionIndex']) ? (int)$input['sectionIndex'] : null;

        if (!file_exists(RESOURCES_JSON)) {
            sendJsonResponse(["error" => "resources_and_buttons.json not found."], 500);
        }
        $resourcesData = file_get_contents(RESOURCES_JSON);
        if ($resourcesData === false) { sendJsonResponse(["error" => "Failed to read resources_and_buttons.json for section update"], 500); }

        $data = json_decode($resourcesData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse(["error" => "Error decoding resources_and_buttons.json: " . json_last_error_msg()], 500);
        }

        if ($sectionIndex !== null && isset($data['buttons'][$sectionIndex])) {
            // Update existing section
            $data['buttons'][$sectionIndex]['section'] = $sectionName;
            $message = "Section updated successfully.";
        } else {
            // Add new section
            $data['buttons'][] = [
                "section" => $sectionName,
                "buttons" => [] // New sections start with no buttons
            ];
            $message = "Section added successfully.";
        }

        if (file_put_contents(RESOURCES_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
            sendJsonResponse(["message" => $message]);
        } else {
            sendJsonResponse(["error" => "Failed to write to resources file. Check permissions."], 500);
        }
        break;

    case 'delete_section':
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['sectionIndex'])) {
            sendJsonResponse(["error" => "Section index is required for deletion."], 400);
        }

        $sectionIndex = (int)$input['sectionIndex'];

        if (!file_exists(RESOURCES_JSON)) {
            sendJsonResponse(["error" => "resources_and_buttons.json not found for section deletion."], 500);
        }
        $resourcesData = file_get_contents(RESOURCES_JSON);
        if ($resourcesData === false) { sendJsonResponse(["error" => "Failed to read resources_and_buttons.json for section deletion"], 500); }

        $data = json_decode($resourcesData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse(["error" => "Error decoding resources_and_buttons.json: " . json_last_error_msg()], 500);
        }

        if (isset($data['buttons'][$sectionIndex])) {
            // Check if the section contains any non-empty buttons before deleting
            foreach ($data['buttons'][$sectionIndex]['buttons'] as $buttonInfo) {
                if ($buttonInfo['name'] !== "Empty") {
                    sendJsonResponse(["error" => "Cannot delete section: it contains active resources. Please move or delete resources from this section first."], 400);
                }
            }

            array_splice($data['buttons'], $sectionIndex, 1); // Remove the section
            $message = "Section deleted successfully.";
        } else {
            sendJsonResponse(["error" => "Section not found at index: " . $sectionIndex], 404);
        }

        if (file_put_contents(RESOURCES_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
            sendJsonResponse(["message" => $message]);
        } else {
            sendJsonResponse(["error" => "Failed to write to resources file after section deletion. Check permissions."], 500);
        }
        break;

    default:
        sendJsonResponse(["error" => "Invalid action specified."], 400);
        break;
}

?>