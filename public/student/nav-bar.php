 <!-- Navigation -->
 <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard me-2"></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="attendance.php">
                    <i class="bi bi-clock me-1"></i>Attendance
                </a>
                <a class="nav-link" href="documents.php">
                    <i class="bi bi-file-text me-1"></i>Documents
                </a>
                <a class="nav-link" href="messages.php">
                    <i class="bi bi-chat me-1"></i>Messages
                </a>
                <a class="nav-link" href="profile.php">
                    <i class="bi bi-person me-1"></i>My Profile
                </a>
                <span class="navbar-text me-3" style="margin-left: 2rem;">
                    Welcome, <?= htmlspecialchars($user->getDisplayName()) ?>
                </span>
                <button type="button" class="btn btn-outline-light btn-sm" 
                        data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </div>
        </div>
    </nav>