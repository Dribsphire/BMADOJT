<?php
// Count pending forgot timeout requests for notification badge
$pendingForgotTimeoutCount = 0;
$pendingWorkplaceEditCount = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'instructor') {
    try {
        require_once '../../vendor/autoload.php';
        $pdo = \App\Utils\Database::getInstance();
        
        // Count forgot timeout requests
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_requests
            FROM forgot_timeout_requests ftr
            JOIN users u ON ftr.student_id = u.id
            WHERE u.section_id = ? AND ftr.status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pendingForgotTimeoutCount = $stmt->fetchColumn();
        
        // Count workplace edit requests
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_requests
            FROM student_profiles sp
            JOIN users u ON sp.user_id = u.id
            WHERE u.section_id = ? AND sp.workplace_edit_request_status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pendingWorkplaceEditCount = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        // Silently fail if there's an error
        error_log("Sidebar notification error for user {$_SESSION['user_id']}: " . $e->getMessage());
    }
}

// Get current page filename to determine active link
$currentPage = basename($_SERVER['PHP_SELF']);

// Function to check if a link should be active (only declare if not already declared)
if (!function_exists('isActive')) {
    function isActive($page, $currentPage) {
        return $page === $currentPage ? 'active' : '';
    }
}
?>
<nav id="sidebar">
    <ul>
      <li>
        <span class="logo">OJT ROUTE</span>
        <button onclick=toggleSidebar() id="toggle-btn">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
        </button>
      </li>
      <li>
        <a href="dashboard.php" class="<?= isActive('dashboard.php', $currentPage) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M520-600v-240h320v240H520ZM120-440v-400h320v400H120Zm400 320v-400h320v400H520Zm-400 0v-240h320v240H120Zm80-400h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z"/></svg>
        <span>Dashboard</span>
        </a>
      </li>
      <li>
        <a href="templates.php" class="<?= isActive('templates.php', $currentPage) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-200h80v-167l64 64 56-57-160-160-160 160 57 56 63-63v167ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
        <span>Templates</span>
        </a>
      </li>
      <li>
        <a href="messages.php" class="<?= isActive('messages.php', $currentPage) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-400h320v-80H240v80Zm0-120h480v-80H240v80Zm0-120h480v-80H240v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z"/></svg>
        <span>Messages</span>
        </a>
      </li>
      <li>
        <button onclick=toggleSubMenu(this) class="dropdown-btn <?= (isActive('document_reports.php', $currentPage) || isActive('document_dashboard.php', $currentPage)) ? 'active' : '' ?>">
<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M280-280h80v-200h-80v200Zm320 0h80v-400h-80v400Zm-160 0h80v-120h-80v120Zm0-200h80v-80h-80v80ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/></svg>
        <span>Reports</span>
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>
        </button>
        <ul class="sub-menu">
          <div>
            <li><a href="document_reports.php" class="<?= isActive('document_reports.php', $currentPage) ?>">Documents reports</a></li>
            <li><a href="document_dashboard.php" class="<?= isActive('document_dashboard.php', $currentPage) ?>">Attendance reports</a></li>
          </div>
        </ul>
      </li>
      <li>
        <a href="../logout.php" data-bs-toggle="modal" data-bs-target="#logoutModalDashboard">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z"/></svg>
        <span>Logout</span>
        </a>
      </li>
      
    </ul>
  </nav>
  <style>
            body {
            background: #f5f7fa;
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-size: 12px;
        }
    .notification-link {
        position: relative;
    }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #0ea539;
        color: white;
        border-radius: 30%;
        padding: 2px 6px;
        font-size: 12px;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #sidebar li a {
        position: relative;
    }
    /* Active page styling - Higher specificity to override main CSS */
    #sidebar ul li a.active {
        background-color:rgb(66, 192, 95) !important;
        color: white !important;
        border-radius: 8px;
        font-weight: 600;
    }

    #sidebar ul li a.active svg {
        fill: white !important;
    }

    #sidebar ul li a.active span {
        color: white !important;
    }
    
    /* Active dropdown button styling */
    #sidebar ul li button.dropdown-btn.active {
        background-color:rgb(66, 192, 95) !important;
        color: white !important;
        border-radius: 8px;
        font-weight: 600;
    }
    
    #sidebar ul li button.dropdown-btn.active svg {
        fill: white !important;
    }
    
    #sidebar ul li button.dropdown-btn.active span {
        color: white !important;
    }
    
    /* Active submenu link styling */
    #sidebar ul li .sub-menu a.active {
        background-color:rgb(66, 192, 95) !important;
        color: white !important;
        border-radius: 8px;
        font-weight: 600;
        padding: 8px 12px;
        display: block;
    }

    /* Hover effect for non-active items */
    #sidebar li a:not(.active):hover {
        background-color: rgba(14, 165, 57, 0.1);
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    /* Hover effect for submenu links */
    #sidebar .sub-menu a:not(.active):hover {
        background-color: rgba(14, 165, 57, 0.1);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    /* Apply to all elements in sidebar */
    .sidebar * {
        font-size: 12px;
    }

    .sidebar h1, .sidebar h2 {
        font-size: inherit; /* Keep original size for headers */
    }
</style>

<style>
    .admin-teacher-switch {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        margin: 10px;
        border-radius: 8px;
        padding: 10px;
    }
    
    .switch-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    
    .switch-text {
        color: white;
        font-size: 12px;
        font-weight: 500;
        text-align: center;
    }
    
    .back-to-admin-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: all 0.3s ease;
        width: 100%;
        justify-content: center;
    }
    
    .back-to-admin-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }
    
    .back-to-admin-btn svg {
        flex-shrink: 0;
    }
</style>
 <!-- Logout Confirmation Modal -->
      <div class="modal fade" id="logoutModalDashboard" tabindex="-1" aria-labelledby="logoutModalDashboardLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalDashboardLabel">
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