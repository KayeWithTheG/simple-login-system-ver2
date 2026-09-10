<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $client = new MongoDB\Client("mongodb+srv://emersonkarlguillermo28_db_user:FAHHHH12345@cluster0.xk4721z.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0");
    $database = $client->selectDatabase('student_access_pass_db');
    $collection = $database->selectCollection('students');
    $admins_collection = $database->selectCollection('admins');
} catch (Exception $e) {
    die("Error in database connection: " . $e->getMessage());
}
?>