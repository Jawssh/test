<?php
require_once 'config.php'; // Ensure this path is correct to your DB config

// Define income brackets
$brackets = [
    'Below PHP 5,000' => 'income < 5000',
    'PHP 5,001 - PHP 10,000' => 'income BETWEEN 5001 AND 10000',
    'PHP 10,001 - PHP 15,000' => 'income BETWEEN 10001 AND 15000',
    'PHP 15,001 - PHP 20,000' => 'income BETWEEN 15001 AND 20000',
    'Above PHP 20,000' => 'income > 20000'
];

$data = [];

foreach ($brackets as $label => $condition) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM beneficiaries WHERE $condition");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data[] = [
        'label' => $label,
        'value' => (int)$result['count']
    ];
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($data);
