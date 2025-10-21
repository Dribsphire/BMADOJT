<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-green-light: #34d399;
            --chmsu-green-dark: #059669;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: var(--chmsu-green) !important;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            background: var(--chmsu-green) ;
            color: white;
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        /* Prevent page jump on form submission */
        html {
            scroll-behavior: smooth;
        }
        
        /* Ensure smooth transitions */
        * {
            scroll-behavior: smooth;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
            color: white;
        }
        
        .btn-primary {
            background: var(--chmsu-green);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--chmsu-green-dark);
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        /* Fix modal backdrop overlay issue - Custom backdrop */
        .modal-backdrop {
            display: none !important;
        }
        
        .modal {
            z-index: 1070 !important;
            pointer-events: auto !important;
        }
        
        .modal-dialog {
            z-index: 1070 !important;
            pointer-events: auto !important;
        }
        
        .modal-content {
            z-index: 1070 !important;
            pointer-events: auto !important;
        }
        
        /* Custom backdrop for modals */
        .modal.show::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1060;
            pointer-events: none;
        }
        
        /* Ensure modal content is clickable and cursor works */
        .modal-content * {
            pointer-events: auto !important;
        }
        
        /* Fix cursor in input fields */
        .modal input, .modal textarea, .modal select {
            pointer-events: auto !important;
            cursor: text !important;
        }
        
        .modal button {
            pointer-events: auto !important;
            cursor: pointer !important;
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    <main>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard me-2"></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                <a class="nav-link active" href="users.php">
                    <i class="bi bi-people me-1"></i>Users
                </a>
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($user->getDisplayName()) ?>
                </span>
                <button type="button" class="btn btn-outline-light btn-sm" 
                        data-bs-toggle="modal" data-bs-target="#logoutModalUsers">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-people me-2"></i>User Management
                </h2>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['student'] ?? 0 ?></h3>
                        <p class="stat-label">Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['instructor'] ?? 0 ?></h3>
                        <p class="stat-label">Instructors</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['admin'] ?? 0 ?></h3>
                        <p class="stat-label">Admins</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['total'] ?? 0 ?></h3>
                        <p class="stat-label">Total Users</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title">
                                    <i class="bi bi-upload me-2"></i>Bulk Registration
                                </h5>
                                <p class="card-text">Register multiple users from CSV file.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkRegisterModal">
                                    <i class="bi bi-upload me-1" style="color: white;"></i>Bulk Register
                                </button>
                            </div>
                            <div class="col-md-6">
                                <h5 class="card-title">
                                    <i class="bi bi-person-plus me-2"></i>Manual Registration
                                </h5>
                                <p class="card-text">Register individual users manually.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualRegisterModal">
                                    <i class="bi bi-person-plus me-1" style="color: white;"></i>Add User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        
        <!-- Users Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>All Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <?= htmlspecialchars($_SESSION['success']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($_SESSION['error']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <!-- Search and Filter Controls -->
                        <div class="row mb-4" id="search-filters">
                            <div class="col-12">
                                <form method="GET" class="row g-2 align-items-end" id="search-form">
                                    <div class="col-md-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               placeholder="Search by name, email, or school ID" 
                                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="font-size: 11px;">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="role_filter" class="form-label">Role</label>
                                        <select class="form-select" id="role_filter" name="role" style="font-size: 11px;">
                                            <option value="">All Roles</option>
                                            <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="instructor" <?= ($_GET['role'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                                            <option value="student" <?= ($_GET['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3" >
                                        <label for="section" class="form-label">Section</label>
                                        <select class="form-select" id="section" name="section" style="font-size: 11px;">
                                            <option value="">All Sections</option>
                                            <?php foreach ($sections as $section): ?>
                                                <option value="<?= $section['id'] ?>" 
                                                        <?= ($_GET['section'] ?? '') == $section['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($section['section_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" style="font-size: 11px;">
                                            <option value="">All Status</option>
                                            <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="d-grid" ">
                                            <button type="submit" class="btn btn-primary" id="search-btn" title="Search" >
                                                <i class="bi bi-search" id="search-icon" style="color: white; font-size: 11px;"></i>
                                                <span class="spinner-border spinner-border-sm d-none" id="search-spinner" role="status"></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="d-grid">
                                            <a href="users.php" class="btn btn-outline-secondary" title="Clear Filters">
                                                <i class="bi bi-x-circle" style="font-size: 11px;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>School ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userRow): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($userRow['school_id']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($userRow['full_name']) ?></td>
                                        <td><?= htmlspecialchars($userRow['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $userRow['role'] === 'admin' ? 'danger' : ($userRow['role'] === 'instructor' ? 'warning' : 'primary') ?>">
                                                <?= ucfirst($userRow['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $userRow['section_name'] ? htmlspecialchars($userRow['section_name']) : '<span class="text-muted">No section</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#assignSectionModal"
                                                        data-user-id="<?= $userRow['id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($userRow['full_name']) ?>"
                                                        data-current-section="<?= $userRow['section_id'] ?>">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <?php if ($userRow['role'] !== 'admin'): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="confirmDelete(<?= $userRow['id'] ?>, '<?= htmlspecialchars($userRow['full_name']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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
                        <nav aria-label="Users pagination">
                            <ul class="pagination justify-content-center">
                                <?php 
                                // Build query string for pagination
                                $queryParams = [];
                                if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
                                if (!empty($_GET['role'])) $queryParams['role'] = $_GET['role'];
                                if (!empty($_GET['section'])) $queryParams['section'] = $_GET['section'];
                                if (!empty($_GET['status'])) $queryParams['status'] = $_GET['status'];
                                
                                for ($i = 1; $i <= $totalPages; $i++): 
                                    $queryParams['page'] = $i;
                                    $queryString = http_build_query($queryParams);
                                ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= $queryString ?>"><?= $i ?></a>
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
    
    <!-- Bulk Registration Modal -->
    <div class="modal fade" id="bulkRegisterModal" tabindex="-1" aria-labelledby="bulkRegisterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkRegisterModalLabel">
                        <i class="bi bi-upload me-2" ></i>Bulk Registration
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                </div>
                <form method="POST" action="?action=bulk_register" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="user_type" class="form-label">User Type</label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="">Select user type</option>
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                CSV format: school_id, email, full_name, gender, contact, facebook_name, section_id
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Upload & Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Manual Registration Modal -->
    <div class="modal fade" id="manualRegisterModal" tabindex="-1" aria-labelledby="manualRegisterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manualRegisterModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="?action=register">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="school_id" class="form-label">School ID</label>
                                <input type="text" class="form-control" id="school_id" name="school_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select role</option>
                                    <option value="student">Student</option>
                                    <option value="instructor">Instructor</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="non-binary">Non-binary</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact" class="form-label">Contact</label>
                                <input type="text" class="form-control" id="contact" name="contact">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="facebook_name" class="form-label">Facebook Name</label>
                            <input type="text" class="form-control" id="facebook_name" name="facebook_name">
                        </div>
                        <div class="mb-3">
                            <label for="section_id" class="form-label">Section</label>
                            <select class="form-select" id="section_id" name="section_id">
                                <option value="">No section</option>
                                <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>">
                                    <?= htmlspecialchars($section['section_code']) ?> - <?= htmlspecialchars($section['section_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   value="Password@2024" required>
                            <div class="form-text">Default password: Password@2024</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Assign Section Modal -->
    <div class="modal fade" id="assignSectionModal" tabindex="-1" aria-labelledby="assignSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignSectionModalLabel">
                        <i class="bi bi-gear me-2"></i>Assign Section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="?action=assign_section">
                    <div class="modal-body">
                        <input type="hidden" id="assign_user_id" name="user_id">
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <p class="form-control-plaintext" id="assign_user_name"></p>
                        </div>
                        <div class="mb-3">
                            <label for="assign_section_id" class="form-label">Section</label>
                            <select class="form-select" id="assign_section_id" name="section_id">
                                <option value="">No section</option>
                                <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>">
                                    <?= htmlspecialchars($section['section_code']) ?> - <?= htmlspecialchars($section['section_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-gear me-1"></i>Assign Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModalUsers" tabindex="-1" aria-labelledby="logoutModalUsersLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalUsersLabel">
                        <i class="bi bi-box-arrow-right me-2"></i>Confirm Logout
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout from OJT Route?</p>
                    <p class="text-muted small">You will need to login again to access the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <a href="../logout.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fix modal backdrop overlay issue
        document.addEventListener('show.bs.modal', function(e) {
            // Remove all existing backdrops before opening
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());
        });
        
        document.addEventListener('shown.bs.modal', function(e) {
            // Remove ALL backdrops completely
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Ensure modal and content are clickable
            const modal = e.target;
            modal.style.zIndex = '1070';
            modal.style.pointerEvents = 'auto';
            
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.pointerEvents = 'auto';
                modalContent.style.zIndex = '1070';
            }
            
            // Fix cursor behavior for all form elements
            const inputs = modal.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.style.pointerEvents = 'auto';
                input.style.cursor = 'text';
            });
            
            const buttons = modal.querySelectorAll('button, a');
            buttons.forEach(button => {
                button.style.pointerEvents = 'auto';
                button.style.cursor = 'pointer';
            });
        });
    </script>
    <script>
        // Assign Section Modal
        document.getElementById('assignSectionModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            const currentSection = button.getAttribute('data-current-section');
            
            document.getElementById('assign_user_id').value = userId;
            document.getElementById('assign_user_name').textContent = userName;
            document.getElementById('assign_section_id').value = currentSection || '';
        });
        
        // Confirm Delete
        function confirmDelete(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=delete';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_id';
                input.value = userId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Show loading indicator
        function showLoadingIndicator() {
            const searchIcon = document.getElementById('search-icon');
            const searchSpinner = document.getElementById('search-spinner');
            const searchBtn = document.getElementById('search-btn');
            
            if (searchIcon && searchSpinner && searchBtn) {
                searchIcon.classList.add('d-none');
                searchSpinner.classList.remove('d-none');
                searchBtn.disabled = true;
            }
        }
        
        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const sectionSelect = document.getElementById('section');
            const statusSelect = document.getElementById('status');
            
            [roleSelect, sectionSelect, statusSelect].forEach(select => {
                if (select) {
                    select.addEventListener('change', function() {
                        // Save scroll position before submitting
                        sessionStorage.setItem('scrollPosition', window.pageYOffset);
                        showLoadingIndicator();
                        this.form.submit();
                    });
                }
            });
            
            // Add enter key support for search
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        // Save scroll position before submitting
                        sessionStorage.setItem('scrollPosition', window.pageYOffset);
                        showLoadingIndicator();
                        this.form.submit();
                    }
                });
            }
            
            // Add loading indicator for search button
            const searchForm = document.getElementById('search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    showLoadingIndicator();
                });
            }
            
            // Restore scroll position after page load
            const savedScrollPosition = sessionStorage.getItem('scrollPosition');
            if (savedScrollPosition !== null) {
                // Use requestAnimationFrame for smooth scroll restoration
                requestAnimationFrame(() => {
                    window.scrollTo(0, parseInt(savedScrollPosition));
                    sessionStorage.removeItem('scrollPosition');
                });
            }
            
            // Alternative: Scroll to search filters section if no saved position
            if (savedScrollPosition === null && window.location.search.includes('search=') || 
                window.location.search.includes('role=') || 
                window.location.search.includes('section=') || 
                window.location.search.includes('status=')) {
                const searchSection = document.getElementById('search-filters');
                if (searchSection) {
                    searchSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    </script>
    
    
     </main>
</body>
</html>

