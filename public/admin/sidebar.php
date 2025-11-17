<?php
// Get current page filename to determine active link
$currentPage = basename($_SERVER['PHP_SELF']);

// Function to check if a link should be active (only declare if not already declared)
if (!function_exists('isActive')) {
    function isActive($page, $currentPage) {
        // Handle sections.php and sections_view.php as the same
        if ($page === 'sections.php') {
            return ($currentPage === 'sections.php' || $currentPage === 'sections_view.php') ? 'active' : '';
        }
        return $page === $currentPage ? 'active' : '';
    }
}
?>
<nav id="sidebar">
    <ul>
      <li>
        <div class="role-toggle-container">
          <div class="role-toggle-header">
            <span class="role-toggle-label">Role Switch</span>
            <button onclick="toggleSidebar()" id="toggle-btn">
              <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
            </button>
          </div>
          
          <!-- Role Toggle Switch -->
          <div class="role-toggle-switch">
            <form method="POST" action="switch_role.php" id="roleSwitchForm">
              <input type="hidden" name="action" id="roleAction" value="">
              
              <div class="toggle-options">
                <div class="toggle-option <?= !isset($_SESSION['acting_role']) ? 'active' : '' ?>" data-role="admin">
                  <div class="toggle-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                      <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0-33-23.5-56.5T760-120H200Zm0-80h560v-560H200v560Zm0 0v-560 560Zm80-80h400v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80Z"/>
                    </svg>
                  </div>
                  <span class="toggle-text">Admin mode</span>
                </div>
                
                <div class="toggle-option <?= isset($_SESSION['acting_role']) && $_SESSION['acting_role'] === 'instructor' ? 'active' : '' ?>" data-role="instructor">
                  <div class="toggle-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                      <path d="M360-390q-21 0-35.5-14.5T310-440q0-21 14.5-35.5T360-490q21 0 35.5 14.5T410-440q0 21-14.5 35.5T360-390Zm240 0q-21 0-35.5-14.5T550-440q0-21 14.5-35.5T600-490q21 0 35.5 14.5T650-440q0 21-14.5 35.5T600-390ZM480-160q134 0 227-93t93-227q0-24-3-46.5T786-570q-21 5-42 7.5t-44 2.5q-91 0-172-39T390-708q-32 78-91.5 135.5T160-486v6q0 134 93 227t227 93Zm0 80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm-54-715q42 70 114 112.5T700-640q14 0 27-1.5t27-3.5q-42-70-114-112.5T480-800q-14 0-27 1.5t-27 3.5ZM177-581q51-29 89-75t57-103q-51 29-89 75t-57 103Zm249-214Zm-103 36Z"/>
                    </svg>
                  </div>
                  <span class="toggle-text">Instructor mode</span>
                </div>
              </div>
            </form>
          </div>
        </div>
      </li>
            <li>
        <a href="dashboard.php" class="<?= isActive('dashboard.php', $currentPage) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M520-600v-240h320v240H520ZM120-440v-400h320v400H120Zm400 320v-400h320v400H520Zm-400 0v-240h320v240H120Zm80-400h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z"/></svg>
          <span>Dashboard</span>
        </a>
      </li>
      <li >
        <a href="users.php" class="<?= isActive('users.php', $currentPage) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M411-480q-28 0-46-21t-13-49l12-72q8-43 40.5-70.5T480-720q44 0 76.5 27.5T597-622l12 72q5 28-13 49t-46 21H411Zm24-80h91l-8-49q-2-14-13-22.5t-25-8.5q-14 0-24.5 8.5T443-609l-8 49ZM124-441q-23 1-39.5-9T63-481q-2-9-1-18t5-17q0 1-1-4-2-2-10-24-2-12 3-23t13-19l2-2q2-19 15.5-32t33.5-13q3 0 19 4l3-1q5-5 13-7.5t17-2.5q11 0 19.5 3.5T208-626q1 0 1.5.5t1.5.5q14 1 24.5 8.5T251-596q2 7 1.5 13.5T250-570q0 1 1 4 7 7 11 15.5t4 17.5q0 4-6 21-1 2 0 4l2 16q0 21-17.5 36T202-441h-78Zm676 1q-33 0-56.5-23.5T720-520q0-12 3.5-22.5T733-563l-28-25q-10-8-3.5-20t18.5-12h80q33 0 56.5 23.5T880-540v20q0 33-23.5 56.5T800-440ZM0-240v-63q0-44 44.5-70.5T160-400q13 0 25 .5t23 2.5q-14 20-21 43t-7 49v65H0Zm240 0v-65q0-65 66.5-105T480-450q108 0 174 40t66 105v65H240Zm560-160q72 0 116 26.5t44 70.5v63H780v-65q0-26-6.5-49T754-397q11-2 22.5-2.5t23.5-.5Zm-320 30q-57 0-102 15t-53 35h311q-9-20-53.5-35T480-370Zm0 50Zm1-280Z"/></svg> 
        <span>Users</span>
        </a>
      </li>
      <li>
        <a href="sections.php" class="<?= isActive('sections.php', $currentPage) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0 0v-560 560Zm80-80h400v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80Z"/></svg>
          <span>Sections</span>
        </a>
      </li>
      <li>
        <a href="profile.php" class="<?= isActive('profile.php', $currentPage) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M360-390q-21 0-35.5-14.5T310-440q0-21 14.5-35.5T360-490q21 0 35.5 14.5T410-440q0 21-14.5 35.5T360-390Zm240 0q-21 0-35.5-14.5T550-440q0-21 14.5-35.5T600-490q21 0 35.5 14.5T650-440q0 21-14.5 35.5T600-390ZM480-160q134 0 227-93t93-227q0-24-3-46.5T786-570q-21 5-42 7.5t-44 2.5q-91 0-172-39T390-708q-32 78-91.5 135.5T160-486v6q0 134 93 227t227 93Zm0 80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm-54-715q42 70 114 112.5T700-640q14 0 27-1.5t27-3.5q-42-70-114-112.5T480-800q-14 0-27 1.5t-27 3.5ZM177-581q51-29 89-75t57-103q-51 29-89 75t-57 103Zm249-214Zm-103 36Z"/></svg>
           <span>Profile</span>
        </a>
      </li>
      <li>
        <a href="attendance_reports.php" class="<?= isActive('attendance_reports.php', $currentPage) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M280-280h80v-200h-80v200Zm320 0h80v-400h-80v400Zm-160 0h80v-120h-80v120Zm0-200h80v-80h-80v80ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/></svg>
        <span>Attendance Reports</span>
        </a>
      </li>
      <li>
        <a href="../logoutadmin.php" data-bs-toggle="modal" data-bs-target="#logoutModalDashboard">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z"/></svg>
        <span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>

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
                    <a href="../logoutadmin.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

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

    /* Active page styling */
    #sidebar li a.active {
        background-color: #0ea539;
        color: white !important;
        border-radius: 8px;
        font-weight: 600;
    }

    #sidebar li a.active svg {
        fill: white !important;
    }
    
    #sidebar li a.active span {
        color: white !important;
    }

    /* Hover effect for non-active items */
    #sidebar li a:not(.active):hover {
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

    /* Role Toggle Switch Styles */
    .role-toggle-container {
        
        background: rgba(14, 165, 57, 0.05);
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .role-toggle-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .role-toggle-label {
        font-weight: 600;
        color: #0ea539;
        font-size: 12px;
        margin-left: 10px;
    }

    .role-toggle-switch {
        background: white;
        border-radius: 6px;

        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .toggle-options {
        display: flex;
        gap: 2px;
    }

    .toggle-option {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 6px 8px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 10px;
        font-weight: 500;
    }

    .toggle-option:not(.active) {
        color: #666;
        background: transparent;
    }

    .toggle-option:not(.active):hover {
        background: rgba(14, 165, 57, 0.1);
        color: #0ea539;
    }

    .toggle-option:not(.active) .toggle-icon svg {
        fill: white ;
    }

    .toggle-option.active {
        background: white;
        color: white !important;
        box-shadow: 0 2px 4px rgba(14, 165, 57, 0.3);
    }

    .toggle-option.active .toggle-icon svg {
        fill: white;
    }

    .toggle-option.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f5f5f5;
        color: #999;
    }

    .toggle-icon {
        display: flex;
        align-items: center;
    }

    .toggle-text {
        font-size: 10px;
        font-weight: 500;
    }

    /* Disabled state for when admin has no section */
    .role-toggle-container.disabled {
        opacity: 0.6;
        background: rgba(255, 0, 0, 0.05);
    }

    .role-toggle-container.disabled .role-toggle-label {
        color: #dc3545;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if admin has section_id for instructor mode
    const hasSection = <?= json_encode(isset($user) && $user->section_id) ?>;
    
    if (!hasSection) {
        const container = document.querySelector('.role-toggle-container');
        const instructorOption = document.querySelector('[data-role="instructor"]');
        
        container.classList.add('disabled');
        instructorOption.classList.add('disabled');
        instructorOption.title = 'You must be assigned to a section to switch to instructor mode';
    }

    // Handle toggle option clicks
    document.querySelectorAll('.toggle-option').forEach(option => {
        option.addEventListener('click', function() {
            if (this.classList.contains('disabled')) {
                return;
            }

            const role = this.dataset.role;
            const form = document.getElementById('roleSwitchForm');
            const actionInput = document.getElementById('roleAction');

            // Remove active class from all options
            document.querySelectorAll('.toggle-option').forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');

            // Set form action based on current state
            if (role === 'instructor' && !<?= json_encode(isset($_SESSION['acting_role']) && $_SESSION['acting_role'] === 'instructor') ?>) {
                actionInput.value = 'switch_to_instructor';
            } else if (role === 'admin' && <?= json_encode(isset($_SESSION['acting_role']) && $_SESSION['acting_role'] === 'instructor') ?>) {
                actionInput.value = 'switch_back_to_admin';
            } else {
                return; // No action needed
            }

            // Submit form
            form.submit();
        });
    });
});
</script>

