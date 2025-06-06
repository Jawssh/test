<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../index.php");
    exit;
}

if (empty($_SESSION['delete_household_csrf_token'])) {
    $_SESSION['delete_household_csrf_token'] = bin2hex(random_bytes(32));
}
$delete_csrf_token = $_SESSION['delete_household_csrf_token'];

$url_notification_message = null;
$url_notification_type = null;
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $status_from_url = htmlspecialchars($_GET['status']);
    $message_from_url = htmlspecialchars(urldecode($_GET['msg']));
    if ($status_from_url === 'success') {
        $url_notification_message = $message_from_url;
        $url_notification_type = 'success';
    } elseif ($status_from_url === 'error') {
        $url_notification_message = $message_from_url;
        $url_notification_type = 'error';
    }
}

$session_successMessage = $_SESSION['success_message'] ?? null;
$session_errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$households = [];
$fetchError = null;
$params = [];
$types = "";

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1; // PAGINATION
$recordsPerPage = 12; // PAGINATION
$offset = ($page - 1) * $recordsPerPage; // PAGINATION

if (!$conn) {
    $fetchError = "Database connection is not available. Check config.php.";
    error_log("editHousehold.php: \$conn object not found after including config.php.");
} else {
    $queryBase = "FROM household h";
    if (!empty($search)) {
        $searchTerm = "%" . $search . "%";
        $queryBase .= " WHERE h.householdID LIKE ? OR h.barangayName LIKE ? OR h.householdname LIKE ?";
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) AS total " . $queryBase;
    $countStmt = $conn->prepare($countQuery);
    if (!empty($search)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRows = $countResult->fetch_assoc()['total'] ?? 0;
    $totalPages = ceil($totalRows / $recordsPerPage);
    $countStmt->close();

    // Main query
    $query = "SELECT
                h.householdID,
                h.barangayName,
                h.householdname,
                h.totalMembers,
                h.sesScore
              " . $queryBase . " ORDER BY h.householdID ASC LIMIT ? OFFSET ?";

    $params[] = $recordsPerPage;
    $params[] = $offset;
    $types .= "ii";

    try {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Query prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $households = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            throw new Exception("Failed to fetch household results: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching households in editHousehold.php: " . $e->getMessage());
        $fetchError = "Could not load household list due to a database error.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <title>Edit Household - Mapping Hope</title>
    <link rel="stylesheet" href="../css/editHousehold.css">
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>

</head>

<body>
    <?php include 'navbar.php'; ?>

    <section class="home">
        <div class="text">
            <h3>Edit Household</h3>
            <span class="profession">Page where you can edit the household information</span>
        </div>

        <div class="main-content-area">
            <div class="search-wrapper">
                <form method="GET" action="" class="search-form">
                    <div class="search-container">
                        <i class='bx bx-search'></i>
                        <input type="text" name="search" placeholder="Search ID, BRGY, or Name"
                            value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    </div>
                </form>
            </div>

            <?php if ($url_notification_message): ?>
                <div id="urlNotification" class="alert-notification <?php echo htmlspecialchars($url_notification_type); ?>">
                    <p><?php echo $url_notification_message; ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($session_successMessage)): ?>
                <div class="alert-notification success">
                    <p><?php echo htmlspecialchars($session_successMessage); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($session_errorMessage)): ?>
                <div class="alert-notification error">
                    <p><?php echo htmlspecialchars($session_errorMessage); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($fetchError) && empty($url_notification_message) && empty($session_errorMessage)): ?>
                <div class="alert-notification error">
                    <p><?php echo htmlspecialchars($fetchError); ?></p>
                </div>
            <?php endif; ?>

            <div class="table-wrapper animate-table" id="tableWrapper">
                <div class="table-container">
                    <table class="content-table">
                        <thead>
                            <tr>
                                <th>HOUSEHOLD ID</th>
                                <th>BARANGAY</th>
                                <th>HOUSEHOLD NAME</th>
                                <th>MEMBERS</th>
                                <th>SES SCORE</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($households)): ?>
                                <?php foreach ($households as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['householdID']); ?></td>
                                        <td><?php echo htmlspecialchars($row['barangayName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['householdname']); ?></td>
                                        <td><?php echo htmlspecialchars($row['totalMembers']); ?></td>
                                        <td class="ses-score"><?php echo ($row['sesScore'] !== null) ? number_format((float)$row['sesScore'], 3) : 'N/A'; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-edit" data-household-id="<?php echo htmlspecialchars($row['householdID']); ?>" title="Edit Household">
                                                    <i class='bx bx-edit-alt'></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-delete"
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($row['householdID']); ?>', '<?php echo htmlspecialchars(addslashes($row['householdname'])); ?>')"
                                                    title="Delete Household">
                                                    <i class='bx bx-trash'></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-results">
                                        <?php echo empty($search) ? 'No households found.' : 'No results found for "' . htmlspecialchars($search) . '".'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">&laquo;</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div id="editModal" class="modal">
        <div class="modal-content wide">
            <span class="modal-close" title="Close">&times;</span>
            <h2>Edit Household</h2>
            <hr style="margin-bottom: 20px;">
            <div id="modalFormContent">
                <p style="text-align:center; padding:40px;">Loading form...</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/editHousehold.js"></script>
    <script>
        const csrfTokenForDelete = '<?php echo htmlspecialchars($delete_csrf_token); ?>';

        function confirmDelete(householdID, householdName) {
            if (confirm(`Are you sure you want to delete household '${householdName}' (ID: ${householdID})?\nThis action cannot be undone and will delete all associated members.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'deletehousehold.php'; // Ensure this file exists
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'householdID';
                idInput.value = householdID;
                form.appendChild(idInput);
                <?php
                $general_csrf_token = $_SESSION['csrf_token'] ?? '';
                if (empty($general_csrf_token)) {
                    $general_csrf_token = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token'] = $general_csrf_token;
                }
                ?>
                const csrfTokenValue = '<?php echo $general_csrf_token; ?>';
                if (csrfTokenValue) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfTokenValue;
                    form.appendChild(csrfInput);
                } else {
                    alert('Security token missing. Delete action aborted.');
                    return;
                }
                document.body.appendChild(form);
                form.submit();
            }
        }

        (function() { // URL Notification Cleanup
            const notificationElement = document.getElementById('urlNotification');
            if (notificationElement) {
                setTimeout(() => {
                    notificationElement.classList.add('alert-hidden');
                }, 5000);
            }
            const url = new URL(window.location.href);
            if (url.searchParams.has('status') || url.searchParams.has('msg')) {
                url.searchParams.delete('status');
                url.searchParams.delete('msg');
                window.history.replaceState({
                    path: url.pathname + url.search
                }, '', url.pathname + url.search);
            }
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tableWrapper = document.getElementById('tableWrapper');
            const paginationLinks = document.querySelectorAll('.pagination a');

            paginationLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Remove and re-add animation class
                    tableWrapper.classList.remove('animate-table');
                    void tableWrapper.offsetWidth; // Force reflow
                    tableWrapper.classList.add('animate-table');
                });
            });
        });
    </script>

</body>

</html>
<?php
// 4. Close the connection at the VERY END of the script, if $conn is valid and open.
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>