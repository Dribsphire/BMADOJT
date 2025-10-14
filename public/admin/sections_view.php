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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main>
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #0ea539;">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-mortarboard me-2"></i>OJT Route
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-house me-1"></i>Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="bi bi-people me-1"></i>Users
                    </a>
                    <a class="nav-link active" href="sections.php">
                        <i class="bi bi-collection me-1"></i>Sections
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person me-1"></i>My Profile
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Main Content -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <i class="bi bi-collection me-2"></i>Section Management
                        </h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSectionModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Section
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
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Section Code</th>
                                            <th>Section Name</th>
                                            <th>Assigned Instructor</th>
                                            <th>Student Count</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($sections)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    <i class="bi bi-inbox me-2"></i>No sections found
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($sections as $section): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($section['section_code']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($section['section_name'] ?: 'No name') ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($section['instructor_name']): ?>
                                                            <div>
                                                                <strong><?= htmlspecialchars($section['instructor_name']) ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?= htmlspecialchars($section['instructor_school_id']) ?></small>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">No instructor assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary student-count-badge" 
                                                              onclick="viewStudents(<?= $section['id'] ?>, '<?= htmlspecialchars($section['section_code']) ?>')">
                                                            <?= $section['student_count'] ?> students
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($section['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-outline-primary btn-sm btn-action" 
                                                                    onclick="editSection(<?= htmlspecialchars(json_encode($section)) ?>)">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-outline-info btn-sm btn-action" 
                                                                    onclick="assignInstructor(<?= $section['id'] ?>, '<?= htmlspecialchars($section['section_code']) ?>', <?= $section['instructor_name'] ? 'true' : 'false' ?>)">
                                                                <i class="bi bi-person-plus"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger btn-sm btn-action" 
                                                                    onclick="deleteSection(<?= $section['id'] ?>, '<?= htmlspecialchars($section['section_code']) ?>', <?= $section['student_count'] ?>)">
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
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Section</button>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
