<?php

/**
 * Section Attendance Overview
 * OJT Route - Instructor section attendance monitoring
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\NotificationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Mark attendance page as visited to reset notification count
$notificationService = new NotificationService();
$notificationService->markAttendancePageVisited($user->id);

// Initialize database
$pdo = Database::getInstance();

// Pagination parameters
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Search parameter
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$baseQuery = "
    FROM attendance_records ar
    INNER JOIN users u ON ar.student_id = u.id
    INNER JOIN sections s ON u.section_id = s.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE s.instructor_id = ? AND ar.time_in IS NOT NULL
";

// Add search conditions if search term is provided
$searchConditions = "";
$params = [$user->id];

if (!empty($searchTerm)) {
    $searchConditions = " AND (
        u.full_name LIKE ? OR 
        u.school_id LIKE ? OR 
        s.section_name LIKE ? OR 
        sp.workplace_name LIKE ? OR
        ar.block_type LIKE ?
    )";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total " . $baseQuery . $searchConditions;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get paginated attendance records
$dataQuery = "
    SELECT 
        ar.*,
        u.full_name,
        u.school_id,
        u.profile_picture,
        s.section_name,
        sp.workplace_name,
        sp.workplace_latitude,
        sp.workplace_longitude
    " . $baseQuery . $searchConditions . "
    ORDER BY ar.date DESC, ar.time_in DESC
    LIMIT $recordsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Attendance Records - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .attendance-table {
            font-size: 14px;
        }
        .student-name {
            cursor: pointer;
            color: #0ea539;
            font-weight: 600;
        }
        .student-name:hover {
            text-decoration: underline;
        }
        .status-badge {
            font-size: 12px;
        }
        .location-info {
            font-size: 12px;
            color: #666;
        }
        .modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
        .location-map {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <?php include 'teacher-sidebar.php'; ?>
    
    <main>
        <?php include 'navigation-header.php'; ?>
        <div class="container-fluid py-4">  
        <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Attendance</h2>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" onclick="exportToCSV()">
                                <i class="bi bi-download me-1" style="color: white;"></i>Export CSV
                            </button>
                            <button class="btn btn-primary" onclick="refreshPage()">
                                <i class="bi bi-arrow-clockwise me-1" style="color: white;"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div

            <!-- Summary Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><?php echo count($attendanceRecords); ?></h5>
                            <p class="card-text">Total Records</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?php echo count(array_filter($attendanceRecords, function($r) { return $r['time_out'] !== null; })); ?></h5>
                            <p class="card-text">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning"><?php echo count(array_filter($attendanceRecords, function($r) { return $r['time_out'] === null; })); ?></h5>
                            <p class="card-text">Incomplete</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       placeholder="Search by student name, school ID, section, workplace, or block type..." 
                                       value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                                <?php if (!empty($searchTerm)): ?>
                                    <a href="?page=1" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Attendance Records Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>All Attendance Records
                            <span class="badge bg-primary ms-2"><?php echo $totalRecords; ?> records</span>
                        </h5>
                        <?php if (!empty($searchTerm)): ?>
                            <small class="text-muted">
                                Showing results for: "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>"
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover attendance-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Workplace</th>
                                    <th>Date</th>
                                    <th>Block Type</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                                    <?php 
                                                    $nameParts = explode(' ', $record['full_name']);
                                                    $initials = '';
                                                    if (count($nameParts) >= 2) {
                                                        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                                                    } else {
                                                        $initials = strtoupper(substr($record['full_name'], 0, 2));
                                                    }
                                                    echo $initials;
                                                    ?>
                                                </div>
                                                <div>
                                                    <div class="student-name" onclick="showStudentDetails(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                                        <?php echo htmlspecialchars($record['full_name']); ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['school_id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($record['workplace_name'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($record['block_type']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($record['time_in']): ?>
                                                <span class="text-success"><?php echo date('H:i', strtotime($record['time_in'])); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['time_out']): ?>
                                                <span class="text-success"><?php echo date('H:i', strtotime($record['time_out'])); ?></span>
                                            <?php else: ?>
                                                <span class="text-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary"><?php echo number_format($record['hours_earned'], 2); ?>h</span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            if ($record['time_in'] && $record['time_out']) {
                                                $statusClass = 'bg-success';
                                                $statusText = 'Completed';
                                            } elseif ($record['time_in'] && !$record['time_out']) {
                                                $statusClass = 'bg-warning';
                                                $statusText = 'Incomplete';
                                            } else {
                                                $statusClass = 'bg-danger';
                                                $statusText = 'Missed';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <div class="card-body">
                                            <?php if ($record['location_lat_in'] && $record['location_long_in']): ?>
                                                <span class="location-info">
                                                    <i class="bi bi-geo-alt"></i> 
                                                    <?php echo number_format($record['location_lat_in'], 4); ?>, 
                                                    <?php echo number_format($record['location_long_in'], 4); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No location</span>
                                            <?php endif; ?>
                                            </div>
                                            
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Attendance records pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <!-- Previous button -->
                                    <?php if ($currentPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                                <i class="bi bi-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">
                                                <i class="bi bi-chevron-left"></i> Previous
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $currentPage + 2);
                                    
                                    if ($startPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">1</a>
                                        </li>
                                        <?php if ($startPage > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                                <?php echo $totalPages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next button -->
                                    <?php if ($currentPage < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                                Next <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">
                                                Next <i class="bi bi-chevron-right"></i>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                
                                <!-- Pagination info -->
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> records
                                        <?php if (!empty($searchTerm)): ?>
                                            (filtered from total)
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1" aria-labelledby="studentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentDetailsModalLabel">Student Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Student Information</h6>
                            <div id="studentInfo"></div>
                        </div>
                        <div class="col-md-6">
                            <h6>Attendance Details</h6>
                            <div id="attendanceInfo"></div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Time-in Photo</h6>
                            <div id="timeInPhoto"></div>
                        </div>
                        <div class="col-md-6">
                            <h6>Location</h6>
                            <div id="locationInfo"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Calculate distance between two coordinates using Haversine formula
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in kilometers
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c; // Distance in kilometers
        }
        
        function showStudentDetails(record) {
            // Populate student information
            document.getElementById('studentInfo').innerHTML = `
                <p><strong>Name:</strong> ${record.full_name}</p>
                <p><strong>School ID:</strong> ${record.school_id}</p>
                <p><strong>Section:</strong> ${record.section_name}</p>
                <p><strong>Workplace:</strong> ${record.workplace_name || 'N/A'}</p>
            `;
            
            // Populate attendance information
            document.getElementById('attendanceInfo').innerHTML = `
                <p><strong>Date:</strong> ${new Date(record.date).toLocaleDateString()}</p>
                <p><strong>Block Type:</strong> ${record.block_type}</p>
                <p><strong>Time In:</strong> ${record.time_in ? new Date(record.time_in).toLocaleTimeString() : 'N/A'}</p>
                <p><strong>Time Out:</strong> ${record.time_out ? new Date(record.time_out).toLocaleTimeString() : 'Pending'}</p>
                <p><strong>Hours Earned:</strong> ${record.hours_earned} hours</p>
            `;
            
            // Populate time-in photo
            if (record.photo_path) {
                // Check if photo_path already includes the full path
                let imageSrc;
                if (record.photo_path.startsWith('uploads/')) {
                    // Go up two levels from public/instructor/ to reach uploads/
                    imageSrc = `../../${record.photo_path}`;
                } else {
                    imageSrc = `../../uploads/attendance_photos/${record.photo_path}`;
                }
                
                document.getElementById('timeInPhoto').innerHTML = `
                    <img src="${imageSrc}" 
                         class="modal-image" 
                         alt="Time-in Photo"
                         onerror="this.src='../assets/images/default-avatar.svg'">
                `;
            } else {
                document.getElementById('timeInPhoto').innerHTML = `
                    <div class="text-muted">
                        <i class="bi bi-image"></i> No photo available
                    </div>
                `;
            }
            
            // Populate location
            if (record.location_lat_in && record.location_long_in) {
                // Calculate distance to workplace if workplace coordinates exist
                let distanceInfo = '';
                if (record.workplace_latitude && record.workplace_longitude) {
                    const distance = calculateDistance(
                        parseFloat(record.location_lat_in), 
                        parseFloat(record.location_long_in),
                        parseFloat(record.workplace_latitude), 
                        parseFloat(record.workplace_longitude)
                    );
                    const distanceKm = distance.toFixed(2);
                    const isWithinRadius = distance <= 0.1; // 100 meters radius
                    
                    distanceInfo = `
                        <div class="mt-3">
                            <div class="alert ${isWithinRadius ? 'alert-success' : 'alert-warning'} py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-building me-1"></i>
                                        <strong>Distance to Workplace:</strong>
                                    </div>
                                    <div>
                                        <span class="badge ${isWithinRadius ? 'bg-success' : 'bg-warning'}">${distanceKm} km</span>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    ${isWithinRadius ? '✅ Within workplace radius' : '⚠️ Outside workplace radius'}
                                </small>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('locationInfo').innerHTML = `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Time-in Location</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Latitude</small>
                                    <p class="mb-1 fw-bold">${record.location_lat_in}</p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Longitude</small>
                                    <p class="mb-1 fw-bold">${record.location_long_in}</p>
                                </div>
                            </div>
                            ${distanceInfo}
                            <div class="mt-2">
                                <a href="https://www.google.com/maps?q=${record.location_lat_in},${record.location_long_in}" 
                                   target="_blank" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-geo-alt"></i> View on Google Maps
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('locationInfo').innerHTML = `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Location Data</h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="text-muted">
                                <i class="bi bi-geo-alt-fill fs-1"></i>
                                <p class="mt-2 mb-0">No location data available</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
            modal.show();
        }
    </script>
</body>
</html>