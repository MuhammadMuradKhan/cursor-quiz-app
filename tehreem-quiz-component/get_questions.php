<?php
// get_questions.php
// Simulating a database fetch by reading the JSON file
header('Content-Type: application/json');

// In a real scenario, you would connect to a DB here
// $conn = new mysqli($servername, $username, $password, $dbname);
// $result = $conn->query("SELECT * FROM questions");
// ...

$json_data = file_get_contents('questions.json');

// Determine the end time server-side (e.g., 5 minutes from now)
// We send the duration, but ideally, the server validates the final timestamp submission.
$response = [
    'duration_seconds' => 300, // 5 minutes
    'questions' => json_decode($json_data)
];

echo json_encode($response);
?>
