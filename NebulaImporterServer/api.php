<?php

//Function to send a JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

//Load user credentials and resources data
$userData = file_get_contents("usercredentials_gvj8c3iogc85oi3n8clslllohm7x5m.json");
$users = json_decode($userData, true);

$resourcesData = file_get_contents("resources_and_buttons.json");
$data = json_decode($resourcesData, true);
$resources = $data['resources'];
$sections = $data['buttons'];

//Get the action from the request
$action = $_GET['action'] ?? '';

//Handle login, button fetching, and file download based on the action
switch ($action) {
    case 'login':
        if (empty($_GET['username']) || empty($_GET['password'])) {
            sendJsonResponse(["error" => "Username and Password are required"], 400);
        }

        $username = $_GET['username'];
        $password = $_GET['password'];

        if (array_key_exists($username, $users) && $users[$username]['password'] === $password) {
            sendJsonResponse(["access_level" => $users[$username]['access_level']]);
        } else {
            sendJsonResponse(["error" => "Denied Access"], 403);
        }
        break;

    case 'getButtons':
        if (empty($_GET['username']) || empty($_GET['password'])) {
            sendJsonResponse(["error" => "Authentication required"], 401);
        }

        $username = $_GET['username'];
        $password = $_GET['password'];

        if (!array_key_exists($username, $users) || $users[$username]['password'] !== $password) {
            sendJsonResponse(["error" => "Access Denied"], 403);
        }

        //Filter sections and buttons based on user access level
        $userAccessLevel = (int)$users[$username]['access_level']; // Ensure integer comparison
        $filteredSections = [];

        foreach ($sections as $sectionData) {
            $sectionButtons = [];
            foreach ($sectionData['buttons'] as $buttonInfo) {
                //Get resource details for filtering
                $resourceName = $buttonInfo['name'];

                if ($resourceName === "Empty") {
                    continue; //Skip "Empty" buttons for the importer
                }

                if (isset($resources[$resourceName])) {
                    $resourceAccessLevel = (int)$resources[$resourceName]['access_level']; //Ensure integer comparison

                    //Public (access_level 0) users should only see access_level 0 resources.
                    //Private users (access_level 1 or higher) should see resources with access_level <= their own.
                    if ($userAccessLevel == 0) {
                        if ($resourceAccessLevel == 0) {
                            $sectionButtons[] = $buttonInfo;
                        }
                    } else { // Private user
                        if ($userAccessLevel >= $resourceAccessLevel) {
                            $sectionButtons[] = $buttonInfo;
                        }
                    }
                }
            }
            if (!empty($sectionButtons)) {
                $filteredSections[] = [
                    "section" => $sectionData['section'],
                    "buttons" => $sectionButtons
                ];
            }
        }
        sendJsonResponse(["buttons" => $filteredSections]);
        break;

    case 'downloadFile':
        if (empty($_GET['username']) || empty($_GET['password']) || empty($_GET['buttonName'])) {
            sendJsonResponse(["error" => "Username, Password, and buttonName are required"], 400);
        }

        $username = $_GET['username'];
        $password = $_GET['password'];
        $buttonName = $_GET['buttonName'];

        if (array_key_exists($username, $users) && $users[$username]['password'] === $password) {
            if (array_key_exists($buttonName, $resources)) {
                $userAccessLevel = (int)$users[$username]['access_level'];
                $resourceAccessLevel = (int)$resources[$buttonName]['access_level'];

                if ($userAccessLevel >= $resourceAccessLevel) {
                    sendJsonResponse(["url" => $resources[$buttonName]['url']]);
                } else {
                    sendJsonResponse(["error" => "Access Denied"], 403);
                }
            } else {
                sendJsonResponse(["error" => "Resource Not Found"], 404);
            }
        } else {
            sendJsonResponse(["error" => "Access Denied"], 403);
        }
        break;

    default:
        sendJsonResponse(["error" => "Invalid action"], 400);
        break;
}

?>