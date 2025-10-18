<?php
/**
 * Student Attendance History Page
 * OJT Route - Attendance History System
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\AttendanceHistoryService;
use App\Utils\Database;

// Start session
session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication
if (!$authMiddleware->requireRole('student')) {
    header('Location: ../login.php');
    exit;
}

// Get current user
$user = $authService->getCurrentUser();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

// Initialize services
$pdo = Database::getInstance();
$historyService = new AttendanceHistoryService($pdo);

// Get filter parameters
$dateRange = $_GET['date_range'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get attendance history and statistics
$attendanceHistory = $historyService->getAttendanceHistory($user->id, $dateRange, $limit, $offset);
$stats = $historyService->getAttendanceStats($user->id);
$weeklyHours = $historyService->getWeeklyHours($user->id, 4);

// Calculate pagination
$totalRecords = $stats['total_records'];
$totalPages = ceil($totalRecords / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    
    <style>
        root{
            --chmsu-green: #0ea539;
            --chmsu-dark: #0d8a32;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .hours-progress {
            height: 8px;
        }
        .status-legend {
            font-size: 0.85rem;
        }
        
        /* Calendar Customization */
        .fc {
            font-family: inherit;
        }
        .fc-event {
            border-radius: 4px;
            border: none;
            font-size: 0.8rem;
            padding: 2px 4px;
        }
        .fc-event-title {
            font-weight: 500;
        }
        
        /* Make calendar days clickable */
        .fc-daygrid-day {
            cursor: pointer;
        }
        .fc-daygrid-day:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        
        /* Status Colors */
        .event-completed {
            background-color:rgb(132, 207, 150) !important;
            color: white !important;
        }
        .event-incomplete {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }
        .event-missed {
            background-color: #dc3545 !important;
            color: white !important;
        }
        .event-pending {
            background-color: #0ea539 !important;
            color: white !important;
        }
        
        .calendar-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .export-actions {
            display: flex;
            gap: 8px;
        }
        
        .view-toggle {
            display: flex;
            gap: 5px;
        }
        
        .view-toggle .btn {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        a{
            cursor: pointer;
            color: #0ea539;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <?php include 'student-sidebar.php'; ?>

    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-12">

                <!-- Calendar View -->
                <div class="row">
                    <div class="col-12">
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar3 me-2"></i>Attendance Calendar
                                </h5>
                                <div class="export-actions">
                                    <button class="btn btn-info btn-sm" onclick="showPrintOptions()" title="Print Report">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Status Legend -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body py-2">
                                            <div class="row g-2">
                                                <div class="col-6 col-md-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="event-completed me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                        <small class="text-muted">Completed</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="event-incomplete me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                        <small class="text-muted">Time-in Only</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="event-missed me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                        <small class="text-muted">Missed</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="event-pending me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                        <small class="text-muted">Pending Request</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FullCalendar -->
                            <div id="attendanceCalendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    
    <script>
        let calendar;
        let currentView = 'month';

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize FullCalendar
            const calendarEl = document.getElementById('attendanceCalendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                events: function(info, successCallback, failureCallback) {
                    // Fetch attendance data for the calendar
                    fetch('attendance_calendar_data.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Check if data is an array
                            if (!Array.isArray(data)) {
                                console.error('Invalid data format:', data);
                                successCallback([]);
                                return;
                            }
                            
                            const events = data.map(record => {
                                const status = getAttendanceStatus(record);
                                const statusClass = getStatusColorClass(status);
                                
                                // Handle missed days specially
                                let title;
                                if (record.is_missed || record.block_type === 'missed') {
                                    title = `Missed - No Attendance`;
                                } else {
                                    title = `${record.block_type.charAt(0).toUpperCase() + record.block_type.slice(1)} - ${status}`;
                                }
                                
                                return {
                                    id: record.id,
                                    title: title,
                                    start: record.date,
                                    allDay: true,
                                    className: `event-${status}`,
                                    extendedProps: {
                                        record: record,
                                        status: status,
                                        statusClass: statusClass
                                    }
                                };
                            });
                            successCallback(events);
                        })
                        .catch(error => {
                            console.error('Error loading calendar events:', error);
                            successCallback([]); // Return empty array instead of calling failureCallback
                        });
                },
                eventClick: function(info) {
                    showDayAttendanceDetails(info.event.start);
                },
                dateClick: function(info) {
                    showDayAttendanceDetails(info.date);
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.title = `${info.event.title} - Click for details`;
                },
                dayMaxEvents: 3,
                moreLinkClick: 'popover'
            });

            calendar.render();
        });

        function changeView(view) {
            currentView = view;
            calendar.changeView(view === 'month' ? 'dayGridMonth' : 
                              view === 'week' ? 'timeGridWeek' : 'timeGridDay');
        }

        function getAttendanceStatus(record) {
            // Check if this is a missed day (virtual record)
            if (record.is_missed || record.block_type === 'missed') {
                return 'missed';
            }
            
            if (record.time_in === null) {
                return 'missed';
            }
            
            if (record.time_out === null) {
                if (record.forgot_timeout_request_id && record.forgot_timeout_status === 'pending') {
                    return 'pending';
                }
                return 'incomplete';
            }
            
            return 'completed';
        }

        function getStatusColorClass(status) {
            const classes = {
                'completed': 'event-completed',
                'incomplete': 'event-incomplete', 
                'missed': 'event-missed',
                'pending': 'event-pending'
            };
            return classes[status] || 'event-missed';
        }
        
        // Show print options modal
        function showPrintOptions() {
            const modalHtml = `
                <div class="modal fade" id="printOptionsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-printer me-2"></i>Print Attendance Report
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-calendar-month me-2"></i>Print Specific Month
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label for="printMonth" class="form-label">Select Month:</label>
                                                    <input type="month" class="form-control" id="printMonth">
                                                </div>
                                                <button class="btn btn-primary w-100" onclick="printMonthlyReport()">
                                                    <i class="bi bi-printer me-2"></i>Print Monthly Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-calendar-range me-2"></i>Print All Records
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted mb-3">Print complete OJT attendance records with detailed breakdown.</p>
                                                <button class="btn btn-success w-100" onclick="printAllRecords()">
                                                    <i class="bi bi-printer me-2"></i>Print All Records
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('printOptionsModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('printOptionsModal'));
            modal.show();
        }

        // Print monthly report
        function printMonthlyReport() {
            const selectedMonth = document.getElementById('printMonth').value;
            if (!selectedMonth) {
                alert('Please select a month to print.');
                return;
            }
            
            // Fetch attendance data for the selected month
            fetch(`attendance_calendar_data.php?month=${selectedMonth}`)
                .then(response => response.json())
                .then(data => {
                    printMonthlyAttendance(data, selectedMonth);
                })
                .catch(error => {
                    console.error('Error fetching monthly data:', error);
                    alert('Error loading attendance data.');
                });
        }

        // Print all records
        function printAllRecords() {
            fetch('attendance_calendar_data.php')
                .then(response => response.json())
                .then(data => {
                    printAllAttendance(data);
                })
                .catch(error => {
                    console.error('Error fetching all data:', error);
                    alert('Error loading attendance data.');
                });
        }

        // Print monthly attendance function
        function printMonthlyAttendance(data, month) {
            const printWindow = window.open('', '_blank');
            const monthName = new Date(month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            // Calculate totals
            let totalHours = 0;
            let morningHours = 0;
            let afternoonHours = 0;
            let overtimeHours = 0;
            let completedDays = 0;
            let incompleteDays = 0;
            let missedDays = 0;
            
            data.forEach(record => {
                if (record.hours_earned) {
                    totalHours += parseFloat(record.hours_earned);
                    if (record.block_type === 'morning') morningHours += parseFloat(record.hours_earned);
                    if (record.block_type === 'afternoon') afternoonHours += parseFloat(record.hours_earned);
                    if (record.block_type === 'overtime') overtimeHours += parseFloat(record.hours_earned);
                }
                
                if (record.time_in && record.time_out) completedDays++;
                else if (record.time_in && !record.time_out) incompleteDays++;
                else if (!record.time_in) missedDays++;
            });
            
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Monthly Attendance Report - ${monthName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                        .header h1 { color: #333; margin-bottom: 5px; }
                        .header p { color: #666; margin: 0; }
                        .summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
                        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
                        .summary-item { text-align: center; padding: 10px; background: white; border-radius: 5px; }
                        .summary-item h4 { margin: 0; color: #0ea539; }
                        .summary-item p { margin: 5px 0 0 0; color: #666; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        th { background-color: #0ea539; color: white; }
                        .status-completed { color: #28a745; font-weight: bold; }
                        .status-incomplete { color: #ffc107; font-weight: bold; }
                        .status-missed { color: #dc3545; font-weight: bold; }
                        .total-row { background-color: #f8f9fa; font-weight: bold; }
                        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                            <img src="../images/CHMSU.png" alt="CHMSU Logo" style="height: 80px; margin-right: 20px;">
                            <div>
                                <h1 style="margin: 0; color: #0ea539;">Monthly Attendance Report</h1>
                                <p style="margin: 5px 0 0 0; color: #666;">Carlos Hilado Memorial State University</p>
                            </div>
                        </div>
                        <p style="text-align: center; color: #666; margin: 0;">${monthName} - Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="summary">
                        <h3>Summary</h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <h4>${totalHours.toFixed(2)}</h4>
                                <p>Total Hours</p>
                            </div>
                            <div class="summary-item">
                                <h4>${completedDays}</h4>
                                <p>Completed Days</p>
                            </div>
                            <div class="summary-item">
                                <h4>${incompleteDays}</h4>
                                <p>Incomplete Days</p>
                            </div>
                            <div class="summary-item">
                                <h4>${missedDays}</h4>
                                <p>Missed Days</p>
                            </div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Block</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(record => {
                                const status = getAttendanceStatus(record);
                                const statusClass = status === 'completed' ? 'status-completed' : 
                                                   status === 'incomplete' ? 'status-incomplete' : 'status-missed';
                                const statusText = status === 'completed' ? 'Completed' : 
                                                status === 'incomplete' ? 'Incomplete' : 'Missed';
                                
                                return `
                                    <tr>
                                        <td>${new Date(record.date).toLocaleDateString()}</td>
                                        <td>${record.block_type.charAt(0).toUpperCase() + record.block_type.slice(1)}</td>
                                        <td>${record.time_in ? new Date(record.time_in).toLocaleTimeString() : 'N/A'}</td>
                                        <td>${record.time_out ? new Date(record.time_out).toLocaleTimeString() : 'N/A'}</td>
                                        <td>${record.hours_earned || 0}</td>
                                        <td class="${statusClass}">${statusText}</td>
                                    </tr>
                                `;
                            }).join('')}
                            <tr class="total-row">
                                <td colspan="4"><strong>Total Hours</strong></td>
                                <td><strong>${totalHours.toFixed(2)}</strong></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p><strong>Carlos Hilado Memorial State University</strong></p>
                        <p>OJT Attendance Management System</p>
                        <p>This report was generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }

        // Print all attendance function
        function printAllAttendance(data) {
            const printWindow = window.open('', '_blank');
            
            // Calculate totals
            let totalHours = 0;
            let morningHours = 0;
            let afternoonHours = 0;
            let overtimeHours = 0;
            let completedDays = 0;
            let incompleteDays = 0;
            let missedDays = 0;
            
            data.forEach(record => {
                if (record.hours_earned) {
                    totalHours += parseFloat(record.hours_earned);
                    if (record.block_type === 'morning') morningHours += parseFloat(record.hours_earned);
                    if (record.block_type === 'afternoon') afternoonHours += parseFloat(record.hours_earned);
                    if (record.block_type === 'overtime') overtimeHours += parseFloat(record.hours_earned);
                }
                
                if (record.time_in && record.time_out) completedDays++;
                else if (record.time_in && !record.time_out) incompleteDays++;
                else if (!record.time_in) missedDays++;
            });
            
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Complete OJT Attendance Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                        .header h1 { color: #333; margin-bottom: 5px; }
                        .header p { color: #666; margin: 0; }
                        .summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
                        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
                        .summary-item { text-align: center; padding: 10px; background: white; border-radius: 5px; }
                        .summary-item h4 { margin: 0; color: #0ea539; }
                        .summary-item p { margin: 5px 0 0 0; color: #666; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        th { background-color: #0ea539; color: white; }
                        .status-completed { color: #28a745; font-weight: bold; }
                        .status-incomplete { color: #ffc107; font-weight: bold; }
                        .status-missed { color: #dc3545; font-weight: bold; }
                        .total-row { background-color: #f8f9fa; font-weight: bold; }
                        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                            <img src="../images/CHMSU.png" alt="CHMSU Logo" style="height: 80px; margin-right: 20px;">
                            <div>
                                <h1 style="margin: 0; color: #0ea539;">Complete OJT Attendance Report</h1>
                                <p style="margin: 5px 0 0 0; color: #666;">Carlos Hilado Memorial State University</p>
                            </div>
                        </div>
                        <p style="text-align: center; color: #666; margin: 0;">Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="summary">
                        <h3>Overall Summary</h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <h4>${totalHours.toFixed(2)}</h4>
                                <p>Total Hours</p>
                            </div>
                            <div class="summary-item">
                                <h4>${morningHours.toFixed(2)}</h4>
                                <p>Morning Hours</p>
                            </div>
                            <div class="summary-item">
                                <h4>${afternoonHours.toFixed(2)}</h4>
                                <p>Afternoon Hours</p>
                            </div>
                            <div class="summary-item">
                                <h4>${overtimeHours.toFixed(2)}</h4>
                                <p>Overtime Hours</p>
                            </div>
                            <div class="summary-item">
                                <h4>${completedDays}</h4>
                                <p>Completed Days</p>
                        </div>
                            <div class="summary-item">
                                <h4>${incompleteDays}</h4>
                                <p>Incomplete Days</p>
                            </div>
                            <div class="summary-item">
                                <h4>${missedDays}</h4>
                                <p>Missed Days</p>
                            </div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Block</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(record => {
                                const status = getAttendanceStatus(record);
                                const statusClass = status === 'completed' ? 'status-completed' : 
                                                   status === 'incomplete' ? 'status-incomplete' : 'status-missed';
                                const statusText = status === 'completed' ? 'Completed' : 
                                                status === 'incomplete' ? 'Incomplete' : 'Missed';
                                
                                return `
                                    <tr>
                                        <td>${new Date(record.date).toLocaleDateString()}</td>
                                        <td>${record.block_type.charAt(0).toUpperCase() + record.block_type.slice(1)}</td>
                                        <td>${record.time_in ? new Date(record.time_in).toLocaleTimeString() : 'N/A'}</td>
                                        <td>${record.time_out ? new Date(record.time_out).toLocaleTimeString() : 'N/A'}</td>
                                        <td>${record.hours_earned || 0}</td>
                                        <td class="${statusClass}">${statusText}</td>
                                    </tr>
                                `;
                            }).join('')}
                            <tr class="total-row">
                                <td colspan="4"><strong>Total Hours</strong></td>
                                <td><strong>${totalHours.toFixed(2)}</strong></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p><strong>Carlos Hilado Memorial State University</strong></p>
                        <p>OJT Attendance Management System</p>
                        <p>This report was generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }

        function showDayAttendanceDetails(clickedDate) {
            // Use local date formatting to avoid timezone issues
            const year = clickedDate.getFullYear();
            const month = String(clickedDate.getMonth() + 1).padStart(2, '0');
            const day = String(clickedDate.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            // Fetch all attendance records for this date
            fetch(`attendance_calendar_data.php?date=${dateStr}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(records => {
                    // Check if data is an array
                    if (!Array.isArray(records)) {
                        console.error('Invalid records format:', records);
                        showNoAttendanceModal(clickedDate);
                        return;
                    }
                    
                    if (records.length === 0) {
                        showNoAttendanceModal(clickedDate);
                        return;
                    }
                    
                    // Check if this is a missed day
                    const missedRecord = records.find(r => r.is_missed || r.block_type === 'missed');
                    if (missedRecord) {
                        showMissedDayModal(clickedDate);
                        return;
                    }
                    
                    // Group records by block type
                    const morningRecord = records.find(r => r.block_type === 'morning');
                    const afternoonRecord = records.find(r => r.block_type === 'afternoon');
                    const overtimeRecord = records.find(r => r.block_type === 'overtime');
                    
                    const modalHtml = createDayModalHtml(clickedDate, morningRecord, afternoonRecord, overtimeRecord);
                    
                    // Remove existing modal if any
                    const existingModal = document.getElementById('dayAttendanceModal');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Add modal to body
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('dayAttendanceModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error fetching day attendance:', error);
                    alert('Error loading attendance data: ' + error.message);
                    showNoAttendanceModal(clickedDate);
                });
        }
        
        function createDayModalHtml(date, morningRecord, afternoonRecord, overtimeRecord) {
            const dateFormatted = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const totalHours = parseFloat(morningRecord?.hours_earned || 0) + 
                              parseFloat(afternoonRecord?.hours_earned || 0) + 
                              parseFloat(overtimeRecord?.hours_earned || 0);
            
            return `
                <div class="modal fade" id="dayAttendanceModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-date me-2"></i>${dateFormatted}
                                    <span class="badge bg-primary ms-2">${(totalHours || 0).toFixed(2)} hours</span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    ${createBlockCard('Morning', morningRecord, 'morning')}
                                    ${createBlockCard('Afternoon', afternoonRecord, 'afternoon')}
                                    ${createBlockCard('Overtime', overtimeRecord, 'overtime')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function createBlockCard(blockName, record, blockType) {
            if (!record) {
                return `
                    <div class="col-md-4 mb-3">
                        <div class="card border-secondary">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="bi bi-sun me-1"></i>${blockName}
                                    <span class="badge bg-secondary float-end">No Record</span>
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <i class="bi bi-dash-circle text-muted fs-1"></i>
                                <p class="text-muted mb-0">No attendance recorded</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            const status = getAttendanceStatus(record);
            const statusText = getStatusDisplayText(status);
            const statusClass = getStatusColorClass(status).replace('event-', '');
            
            const timeIn = record.time_in ? new Date(record.time_in).toLocaleTimeString() : 'Not recorded';
            const timeOut = record.time_out ? new Date(record.time_out).toLocaleTimeString() : 'Not recorded';
            const hours = record.hours_earned > 0 ? `${record.hours_earned} hours` : 'No hours earned';
            
            const photoHtml = record.time_in_photo_path ? `
                <div class="text-center mt-2">
                    <img src="../view_attendance_photo.php?id=${record.id}&type=time_in" 
                         class="img-fluid rounded shadow-sm" 
                         style="max-width: 200px; max-height: 150px; object-fit: cover;"
                         alt="Time-in Photo"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="alert alert-warning mt-2" style="display: none; font-size: 0.8rem;">
                        <i class="bi bi-image me-1"></i>Photo not available
                    </div>
                </div>
            ` : `
                <div class="text-center mt-2">
                    <div class="alert alert-info py-2" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle me-1"></i>No photo available
                    </div>
                </div>
            `;
            
            return `
                <div class="col-md-4 mb-3">
                    <div class="card border-${statusClass}">
                        <div class="card-header bg-${statusClass} text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-${blockType === 'morning' ? 'sun' : blockType === 'afternoon' ? 'sun-fill' : 'moon'} me-1"></i>${blockName}
                                <span class="badge bg-white text-${statusClass} float-end">${statusText}</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted">Time In:</small><br>
                                    <span class="fw-bold text-success">${timeIn}</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Time Out:</small><br>
                                    <span class="fw-bold text-success">${timeOut}</span>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-12">
                                    <small class="text-muted">Hours:</small><br>
                                    <span class="fw-bold text-primary">${hours}</span>
                                </div>
                            </div>
                            
                            ${photoHtml}
                            
                            ${record.forgot_timeout_request_id ? `
                            <div class="alert alert-info mt-2 py-1" style="font-size: 0.8rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Request:</strong> ${record.forgot_timeout_status}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
        
        function showMissedDayModal(date) {
            const dateFormatted = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const modalHtml = `
                <div class="modal fade" id="dayAttendanceModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-x me-2"></i>${dateFormatted}
                                    <span class="badge bg-white text-danger ms-2">Missed Day</span>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card border-danger">
                                            <div class="card-header bg-danger text-white">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>No Attendance Recorded
                                                </h6>
                                            </div>
                                            <div class="card-body text-center py-4">
                                                <i class="bi bi-calendar-x text-danger" style="font-size: 3rem;"></i>
                                                <h5 class="text-danger mt-3">Missed Day</h5>
                                                <p class="text-muted">No attendance was recorded for this date during your OJT period.</p>
                                                <div class="alert alert-warning mt-3">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <strong>Note:</strong> This day is marked as missed because no attendance was recorded during your scheduled OJT period.
                                                </div>
                                            </div>
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
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('dayAttendanceModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dayAttendanceModal'));
            modal.show();
        }
        
        function showNoAttendanceModal(date) {
            const dateFormatted = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const modalHtml = `
                <div class="modal fade" id="dayAttendanceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-date me-2"></i>${dateFormatted}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <i class="bi bi-calendar-x text-muted fs-1 mb-3"></i>
                                <h5>No Attendance Records</h5>
                                <p class="text-muted">No attendance was recorded for this date.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('dayAttendanceModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dayAttendanceModal'));
            modal.show();
        }

        function getStatusDisplayText(status) {
            const texts = {
                'completed': 'Completed',
                'incomplete': 'Time-in Only',
                'missed': 'Missed',
                'pending': 'Pending Request'
            };
            return texts[status] || 'Unknown';
        }
    </script>
</body>
</html>
