<?php

namespace App\Controllers;

use App\Services\SectionService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

/**
 * Section Controller
 * OJT Route - Section management operations
 */
class SectionController
{
    private SectionService $sectionService;
    private AuthMiddleware $authMiddleware;
    
    public function __construct()
    {
        $this->sectionService = new SectionService();
        $this->authMiddleware = new AuthMiddleware();
    }
    
    /**
     * Display section management dashboard
     */
    public function index(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        $user = $this->authMiddleware->getCurrentUser();
        
        // Get search parameters
        $search = trim($_GET['search'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 25;
        $offset = ($page - 1) * $limit;
        
        // Get sections with pagination and search
        $sections = $this->sectionService->getAllSections($limit, $offset, $search);
        $totalSections = $this->sectionService->getTotalSections($search);
        $totalPages = ceil($totalSections / $limit);
        
        // Get all instructors for assignment
        $instructors = $this->sectionService->getAllInstructors();
        
        // Include the view
        include __DIR__ . '/../../public/admin/sections_view.php';
    }
    
    /**
     * Create new section
     */
    public function create(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: sections.php');
            exit;
        }
        
        $sectionData = [
            'section_code' => trim($_POST['section_code'] ?? ''),
            'section_name' => trim($_POST['section_name'] ?? ''),
            'instructor_id' => !empty($_POST['instructor_id']) ? (int) $_POST['instructor_id'] : null
        ];
        
        try {
            $result = $this->sectionService->createSection($sectionData);
            
            if ($result['success']) {
                $_SESSION['success'] = "Section {$sectionData['section_code']} created successfully!";
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error creating section: ' . $e->getMessage();
        }
        
        header('Location: sections.php');
        exit;
    }
    
    /**
     * Update section
     */
    public function update(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: sections.php');
            exit;
        }
        
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $sectionData = [
            'section_name' => trim($_POST['section_name'] ?? ''),
            'instructor_id' => !empty($_POST['instructor_id']) ? (int) $_POST['instructor_id'] : null
        ];
        
        try {
            $result = $this->sectionService->updateSection($sectionId, $sectionData);
            
            if ($result['success']) {
                $_SESSION['success'] = 'Section updated successfully!';
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error updating section: ' . $e->getMessage();
        }
        
        header('Location: sections.php');
        exit;
    }
    
    /**
     * Delete section
     */
    public function delete(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: sections.php');
            exit;
        }
        
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        
        try {
            $result = $this->sectionService->deleteSection($sectionId);
            
            if ($result['success']) {
                $_SESSION['success'] = 'Section deleted successfully!';
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error deleting section: ' . $e->getMessage();
        }
        
        header('Location: sections.php');
        exit;
    }
    
    /**
     * Assign instructor to section
     */
    public function assignInstructor(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: sections.php');
            exit;
        }
        
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $instructorId = !empty($_POST['instructor_id']) ? (int) $_POST['instructor_id'] : null;
        
        try {
            $result = $this->sectionService->assignInstructor($sectionId, $instructorId);
            
            if ($result['success']) {
                $_SESSION['success'] = 'Instructor assignment updated successfully!';
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error assigning instructor: ' . $e->getMessage();
        }
        
        header('Location: sections.php');
        exit;
    }
    
    /**
     * Get students in a section
     */
    public function getStudents(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        $sectionId = (int) ($_GET['section_id'] ?? 0);
        
        if ($sectionId <= 0) {
            echo '<div class="alert alert-danger">Invalid section ID.</div>';
            exit;
        }
        
        $students = $this->sectionService->getStudentsInSection($sectionId);
        
        if (empty($students)) {
            echo '<div class="text-center text-muted py-4">
                    <i class="bi bi-inbox me-2"></i>No students in this section
                  </div>';
        } else {
            echo '<div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>School ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($students as $student) {
                echo '<tr>
                        <td><strong>' . htmlspecialchars($student['school_id']) . '</strong></td>
                        <td>' . htmlspecialchars($student['full_name']) . '</td>
                        <td>' . htmlspecialchars($student['email']) . '</td>
                        <td>' . htmlspecialchars($student['contact'] ?: 'N/A') . '</td>
                      </tr>';
            }
            
            echo '</tbody>
                  </table>
                </div>';
        }
    }
}
