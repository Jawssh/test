<?php
require_once 'config.php'; // assumes DB connection is already configured

header('Content-Type: application/json');

$labels = ['Severe Illness', 'Significant Illness', 'Managed Illness', 'No Illness'];
$data = [];

foreach ($labels as $label) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM beneficiaries WHERE health = ?");
    $stmt->bind_param("s", $label);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data[] = [
        'label' => $label,
        'value' => (int)$result['count']
    ];
    $stmt->close();
}

echo json_encode($data);
