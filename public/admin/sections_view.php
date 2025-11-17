<?php

/**
 * Section Management
 * OJT Route - Admin section management page
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use App\Utils\AdminAccess;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}


// Check admin access (including acting as instructor)
AdminAccess::requireAdminAccess();

// Get current user
$user = $authMiddleware->getCurrentUser();

// Get database connection
$pdo = Database::getInstance();

// Handle section actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_section':
            $sectionCode = $_POST['section_code'] ?? '';
            $sectionName = $_POST['section_name'] ?? '';
            $instructorId = $_POST['instructor_id'] ?? null;
            
            if ($sectionCode && $sectionName) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO sections (section_code, section_name, instructor_id) VALUES (?, ?, ?)");
                    $stmt->execute([$sectionCode, $sectionName, $instructorId]);
                    $_SESSION['success'] = 'Section created successfully.';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error creating section: ' . $e->getMessage();
                }
            }
            break;
            
        case 'update_section':
            $sectionId = $_POST['section_id'] ?? '';
            $sectionCode = $_POST['section_code'] ?? '';
            $sectionName = $_POST['section_name'] ?? '';
            $instructorId = $_POST['instructor_id'] ?? null;
            
            if ($sectionId && $sectionCode && $sectionName) {
                try {
                    $stmt = $pdo->prepare("UPDATE sections SET section_code = ?, section_name = ?, instructor_id = ? WHERE id = ?");
                    $stmt->execute([$sectionCode, $sectionName, $instructorId, $sectionId]);
                    $_SESSION['success'] = 'Section updated successfully.';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error updating section: ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete_section':
            $sectionId = $_POST['section_id'] ?? '';
            if ($sectionId) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
                    $stmt->execute([$sectionId]);
                    $_SESSION['success'] = 'Section deleted successfully.';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error deleting section: ' . $e->getMessage();
                }
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: sections_view.php');
    exit;
}

// Get sections with student count and assigned instructor (one instructor per section)
// Check both junction table and old users.section_id as fallback
// Include admins who can act as instructors
$sections = $pdo->query("
    SELECT s.*, 
           COALESCE(u_junction.full_name, u_old.full_name) as instructor_name,
           COALESCE(u_junction.school_id, u_old.school_id) as instructor_school_id,
           COALESCE(u_junction.profile_picture, u_old.profile_picture) as instructor_profile_picture,
           COUNT(DISTINCT st.id) as student_count
    FROM sections s 
    LEFT JOIN instructor_sections is_rel ON s.id = is_rel.section_id
    LEFT JOIN users u_junction ON is_rel.instructor_id = u_junction.id
    LEFT JOIN users u_old ON s.id = u_old.section_id AND (u_old.role = 'instructor' OR u_old.role = 'admin')
    LEFT JOIN users st ON st.section_id = s.id AND st.role = 'student'
    GROUP BY s.id, u_junction.full_name, u_junction.school_id, u_junction.profile_picture, u_old.full_name, u_old.school_id, u_old.profile_picture
    ORDER BY s.section_name
")->fetchAll();

// Initialize FileUploadService for profile pictures
$fileUploadService = new FileUploadService();

// Get instructors for dropdown - include both instructors and admins (admins can act as instructors)
$instructors = $pdo->query("
    SELECT id, full_name, school_id, role 
    FROM users 
    WHERE role = 'instructor' OR role = 'admin'
    ORDER BY role DESC, full_name
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Management - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
        }
        
        .section-card {
            transition: transform 0.2s;
        }
        
        .section-card:hover {
            transform: translateY(-2px);
        }
        
        .student-count-badge {
            cursor: pointer;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Fix modal positioning and z-index issues */
        .modal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 1055 !important;
            display: none;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            outline: 0;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-backdrop {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 1050 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        
        .modal-backdrop.show {
            opacity: 0.5;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            pointer-events: none;
            z-index: 1055 !important;
        }
        
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 0.3rem;
            outline: 0;
            z-index: 1055 !important;
        }
        
        /* Ensure sidebar stays below modals */
        #sidebar {
            z-index: 1000 !important;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main>
    
        
        <!-- Main Content -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <i class="bi bi-collection me-2" ></i>Section Management
                        </h2>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createSectionModal">
                            <i class="bi bi-plus-circle me-2" style="color:white;"></i>Add Section
                        </button>
                    </div>
                    
                    <!-- Search and Filter -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" class="d-flex">
                                <input type="text" 
                                       class="form-control me-2" 
                                       name="search" 
                                       placeholder="Search sections..." 
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="sections.php" class="btn btn-outline-danger ms-2">
                                        <i class="bi bi-x"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                Showing <?= count($sections) ?> of <?= $totalSections ?> sections
                            </small>
                        </div>
                    </div>
                    
                    <!-- Sections Table -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th class="fw-semibold py-3 px-4">Section Code</th>
                                            <th class="fw-semibold py-3 px-4">Section Name</th>
                                            <th class="fw-semibold py-3 px-4">Assigned Instructor</th>
                                            <th class="fw-semibold py-3 px-4 text-center">Students</th>
                                            <th class="fw-semibold py-3 px-4 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($sections)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="bi bi-inbox me-2 fs-4"></i>
                                                    <div class="mt-2">No sections found</div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($sections as $section): ?>
                                                <tr class="border-bottom">
                                                    <td class="px-4 py-3">
                                                        <span class="badge bg-success bg-gradient fs-6 px-3 py-2">
                                                            <i class="bi bi-bookmark me-1" style="color:white;"></i>
                                                            <?= htmlspecialchars($section['section_code']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="fw-medium text-dark">
                                                            <?= htmlspecialchars($section['section_name'] ?: 'No name') ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <?php if ($section['instructor_name']): 
                                                            $profilePictureUrl = $fileUploadService->getProfilePictureUrl($section['instructor_profile_picture'] ?? null);
                                                        ?>
                                                            <div class="d-flex align-items-center">
                                                                <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                                                                     alt="<?= htmlspecialchars($section['instructor_name']) ?>"
                                                                     class="rounded-circle me-3"
                                                                     style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e9ecef;">
                                                                <div>
                                                                    <div class="fw-semibold text-dark">
                                                                        <?= htmlspecialchars($section['instructor_name']) ?>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <?= htmlspecialchars($section['instructor_school_id'] ?? 'N/A') ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="d-flex align-items-center text-muted">
                                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3"
                                                                     style="width: 40px; height: 40px;">
                                                                    <i class="bi bi-person-x text-muted"></i>
                                                                </div>
                                                                <span>No instructor assigned</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <span class="badge bg-success bg-success fs-6 px-3 py-2 cursor-pointer" 
                                                              onclick='viewStudents(<?= $section['id'] ?>, <?= str_replace("'", "\\'", json_encode($section['section_code'])) ?>)'
                                                              style="cursor: pointer;">
                                                            <i class="bi bi-people me-1" style="color:white;"></i>
                                                            <?= $section['student_count'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary border-0 shadow-sm" 
                                                                    onclick='editSection(<?= str_replace("'", "\\'", json_encode($section)) ?>)'
                                                                    title="Edit Section"
                                                                    style="border-radius: 6px 0 0 6px;">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-info border-0 shadow-sm" 
                                                                    onclick='assignInstructor(<?= $section['id'] ?>, <?= str_replace("'", "\\'", json_encode($section['section_code'])) ?>, <?= $section['instructor_name'] ? 'true' : 'false' ?>)'
                                                                    title="Assign Instructor">
                                                                <i class="bi bi-person-plus"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger border-0 shadow-sm" 
                                                                    onclick='deleteSection(<?= $section['id'] ?>, <?= str_replace("'", "\\'", json_encode($section['section_code'])) ?>, <?= $section['student_count'] ?>)'
                                                                    title="Delete Section"
                                                                    style="border-radius: 0 6px 6px 0;">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Section pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Create Section Modal -->
    <div class="modal fade" id="createSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="sections.php?action=create">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="section_code" class="form-label">Section Code *</label>
                            <input type="text" class="form-control" id="section_code" name="section_code" 
                                   placeholder="e.g., BSIT-4A" required>
                            <div class="form-text">Unique identifier for the section</div>
                        </div>
                        <div class="mb-3">
                            <label for="section_name" class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="section_name" name="section_name" 
                                   placeholder="e.g., BSIT 4th Year Section A">
                            <div class="form-text">Optional descriptive name</div>
                        </div>
                        <div class="mb-3">
                            <label for="instructor_id" class="form-label">Assign Instructor</label>
                            <select class="form-select" id="instructor_id" name="instructor_id">
                                <option value="">No instructor (assign later)</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= $instructor['id'] ?>">
                                        <?= htmlspecialchars($instructor['full_name']) ?> 
                                        (<?= htmlspecialchars($instructor['school_id']) ?>)
                                        <?= $instructor['role'] === 'admin' ? ' [Admin]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="sections.php?action=update">
                    <input type="hidden" id="edit_section_id" name="section_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_section_code" class="form-label">Section Code</label>
                            <input type="text" class="form-control" id="edit_section_code" readonly>
                            <div class="form-text">Section code cannot be changed</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_section_name" class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="edit_section_name" name="section_name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Instructor Modal -->
    <div class="modal fade" id="assignInstructorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="sections.php?action=assign_instructor">
                    <input type="hidden" id="assign_section_id" name="section_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Instructor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="assign_instructor_id" class="form-label">Select Instructor</label>
                            <select class="form-select" id="assign_instructor_id" name="instructor_id">
                                <option value="">Remove instructor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= $instructor['id'] ?>">
                                        <?= htmlspecialchars($instructor['full_name']) ?> 
                                        (<?= htmlspecialchars($instructor['school_id']) ?>)
                                        <?= $instructor['role'] === 'admin' ? ' [Admin]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Instructor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Section Modal -->
    <div class="modal fade" id="deleteSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="sections.php?action=delete">
                    <input type="hidden" id="delete_section_id" name="section_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete section <strong id="delete_section_code"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <span id="delete_warning_text"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Students in Section Modal -->
    <div class="modal fade" id="studentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Students in Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="students-content">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <link rel="stylesheet" href="../css/minimal-modal-fix.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/minimal-modal-fix.js"></script>
    <script>
        // Fix modal positioning when opened
        document.addEventListener('show.bs.modal', function(e) {
            const modal = e.target;
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.zIndex = '1055';
        });
        
        document.addEventListener('shown.bs.modal', function(e) {
            const modal = e.target;
            const modalDialog = modal.querySelector('.modal-dialog');
            if (modalDialog) {
                modalDialog.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            let backdrop = document.querySelector('.modal-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
            backdrop.style.position = 'fixed';
            backdrop.style.top = '0';
            backdrop.style.left = '0';
            backdrop.style.zIndex = '1050';
        });
        
        document.addEventListener('hidden.bs.modal', function(e) {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
        });
    </script>
    <script>
        // Edit section
        function editSection(section) {
            document.getElementById('edit_section_id').value = section.id;
            document.getElementById('edit_section_code').value = section.section_code;
            document.getElementById('edit_section_name').value = section.section_name || '';
            
            new bootstrap.Modal(document.getElementById('editSectionModal')).show();
        }
        
        // Assign instructor
        function assignInstructor(sectionId, sectionCode, hasInstructor) {
            document.getElementById('assign_section_id').value = sectionId;
            
            const modal = new bootstrap.Modal(document.getElementById('assignInstructorModal'));
            modal.show();
        }
        
        // Delete section
        function deleteSection(sectionId, sectionCode, studentCount) {
            document.getElementById('delete_section_id').value = sectionId;
            document.getElementById('delete_section_code').textContent = sectionCode;
            
            let warningText = 'This action cannot be undone.';
            if (studentCount > 0) {
                warningText = `${studentCount} students are currently in this section. They will become unassigned.`;
            }
            document.getElementById('delete_warning_text').textContent = warningText;
            
            new bootstrap.Modal(document.getElementById('deleteSectionModal')).show();
        }
        
        // View students in section
        function viewStudents(sectionId, sectionCode) {
            document.getElementById('students-content').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Fetch students data
            fetch(`sections.php?action=students&section_id=${sectionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('students-content').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('students-content').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading students: ${error.message}
                        </div>
                    `;
                });
            
            new bootstrap.Modal(document.getElementById('studentsModal')).show();
        }
    </script>
</body>
</html>
