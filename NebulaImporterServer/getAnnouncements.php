<?php
header('Content-Type: application/json');

$announcement_text = "WARNING! Nebula Importer has been discontinued, all source code is available on Github: https://github.com/Awooochy/NebulaImporter";

$data = [
    'announcement' => $announcement_text
];

echo json_encode($data);
?>