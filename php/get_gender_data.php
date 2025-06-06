<?php
require_once 'config.php';

$sql = "SELECT sex, COUNT(*) as total FROM beneficiaries WHERE sex IN ('Male', 'Female') GROUP BY sex";
$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'label' => $row['sex'],
            'value' => (int)$row['total']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
