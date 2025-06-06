<?php
include 'config.php';

$bins = [
    ["label" => "0.01 – 0.27", "min" => 0.00, "max" => 0.27],
    ["label" => "0.27 – 3.56", "min" => 0.27, "max" => 3.56],
    ["label" => "3.56 – 5.00", "min" => 3.56, "max" => 5.00],
    ["label" => "5.00 – 7.54", "min" => 5.00, "max" => 7.54],
    ["label" => "7.54 – 10.00+", "min" => 7.54, "max" => 999] // ✅ Update label here
];
// adjust max as needed


$data = [];

foreach ($bins as $bin) {
    $min = $bin["min"];
    $max = $bin["max"];
    $label = $bin["label"];

    $query = "SELECT COUNT(*) as count FROM barangay WHERE SES >= $min AND SES < $max";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $data[] = [
        "label" => $label,
        "count" => (int)$row["count"]
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
