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
        .event-excuse {
            background-color: #6f42c1 !important;
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
                                                        <div class="event-excuse me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                        <small class="text-muted">Excused</small>
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
                                // Handle excused days
                                if (record.is_excused || record.block_type === 'excuse') {
                                    return {
                                        id: record.id,
                                        title: 'Excused',
                                        start: record.date,
                                        allDay: true,
                                        className: 'event-excuse',
                                        extendedProps: {
                                            record: record,
                                            status: 'excused',
                                            statusClass: 'excused'
                                        }
                                    };
                                }
                                
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
            // Check if this is an excused day
            if (record.is_excused || record.block_type === 'excuse') {
                return 'excused';
            }
            
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
                'pending': 'event-pending',
                'excused': 'event-excuse'
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

        // Print monthly attendance function - DTR Format
        function printMonthlyAttendance(data, month) {
            // Fetch student data for DTR header
            fetch('get_student_dtr_data.php')
                .then(response => response.json())
                .then(studentData => {
                    const printWindow = window.open('', '_blank');
                    const monthDate = new Date(month + '-01');
                    const monthName = monthDate.toLocaleDateString('en-US', { month: 'long' }).toUpperCase();
                    const year = monthDate.getFullYear();
                    
                    // Parse student name
                    const fullName = studentData.full_name || '';
                    const nameParts = fullName.trim().split(/\s+/);
                    const surname = nameParts.length > 0 ? nameParts[nameParts.length - 1] : '';
                    const firstName = nameParts.length > 0 ? nameParts[0] : '';
                    const middleInitial = nameParts.length > 2 ? nameParts[1].charAt(0).toUpperCase() + '.' : '';
                    
                    // Get section code (e.g., "4-B" from "BSIT4B" or "BSIT4A")
                    const sectionCode = studentData.section_code || '';
                    // Extract year (number) and section letter
                    const yearMatch = sectionCode.match(/(\d+)/);
                    const letterMatch = sectionCode.match(/([A-Z])$/);
                    const yearNum = yearMatch ? yearMatch[1] : '';
                    const letter = letterMatch ? letterMatch[1] : '';
                    const yearSec = yearNum && letter ? yearNum + '-' + letter : sectionCode;
                    
                    // Organize attendance by date
                    const attendanceByDate = {};
                    
                    data.forEach(record => {
                        const date = new Date(record.date).getDate();
                        if (!attendanceByDate[date]) {
                            attendanceByDate[date] = {
                                am_in: '',
                                am_out: '',
                                pm_in: '',
                                pm_out: '',
                                overtime_in: '',
                                overtime_out: '',
                                actual_hours: 0,
                                counted_hours: 0,
                                remarks: ''
                            };
                        }
                        
                        const timeIn = record.time_in ? new Date(record.time_in) : null;
                        const timeOut = record.time_out ? new Date(record.time_out) : null;
                        const hours = parseFloat(record.hours_earned || 0);
                        
                        // Only include completed blocks (both time_in and time_out exist)
                        const isCompleted = timeIn && timeOut;
                        
                        if (record.block_type === 'morning' && isCompleted) {
                            attendanceByDate[date].am_in = formatTime(timeIn);
                            attendanceByDate[date].am_out = formatTime(timeOut);
                            attendanceByDate[date].actual_hours += hours;
                            attendanceByDate[date].counted_hours += hours;
                        } else if (record.block_type === 'afternoon' && isCompleted) {
                            attendanceByDate[date].pm_in = formatTime(timeIn);
                            attendanceByDate[date].pm_out = formatTime(timeOut);
                            attendanceByDate[date].actual_hours += hours;
                            attendanceByDate[date].counted_hours += hours;
                        } else if (record.block_type === 'overtime' && isCompleted) {
                            attendanceByDate[date].overtime_in = formatTime(timeIn);
                            attendanceByDate[date].overtime_out = formatTime(timeOut);
                            attendanceByDate[date].actual_hours += hours;
                            attendanceByDate[date].counted_hours += hours;
                        }
                    });
                    
                    // Get days in month
                    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
                    
                    // Calculate totals
                    let totalActualHours = 0;
                    let totalCountedHours = 0;
                    
                    // Generate DTR rows
                    let dtrRows = '';
                    for (let day = 1; day <= daysInMonth; day++) {
                        const dayData = attendanceByDate[day] || {
                            am_in: '', am_out: '', pm_in: '', pm_out: '',
                            overtime_in: '', overtime_out: '',
                            actual_hours: 0, counted_hours: 0, remarks: ''
                        };
                        
                        totalActualHours += dayData.actual_hours;
                        totalCountedHours += dayData.counted_hours;
                        
                        const actualHrs = formatHours(dayData.actual_hours);
                        const countedHrs = formatHours(dayData.counted_hours);
                        
                        dtrRows += `
                            <tr>
                                <td class="date-cell">${day}</td>
                                <td class="time-cell">${dayData.am_in}</td>
                                <td class="time-cell">${dayData.am_out}</td>
                                <td class="time-cell">${dayData.pm_in}</td>
                                <td class="time-cell">${dayData.pm_out}</td>
                                <td class="time-cell">${dayData.overtime_in}</td>
                                <td class="time-cell">${dayData.overtime_out}</td>
                                <td class="hours-cell">${actualHrs}</td>
                                <td class="hours-cell">${countedHrs}</td>
                                <td class="remarks-cell">${dayData.remarks}</td>
                            </tr>
                        `;
                    }
                    
                    // Add total row
                    const totalActualHrs = formatHours(totalActualHours);
                    const totalCountedHrs = formatHours(totalCountedHours);
                    dtrRows += `
                        <tr class="total-row">
                            <td class="date-cell" colspan="7" style="text-align: right; font-weight: bold; padding-right: 10px;">TOTAL:</td>
                            <td class="hours-cell">${totalActualHrs}</td>
                            <td class="hours-cell">${totalCountedHrs}</td>
                            <td class="remarks-cell"></td>
                        </tr>
                    `;
                    
                    const headerImagePath = window.location.protocol + '//' + window.location.host + '/bmadOJT/public/images/header.png';
                    
                    const printContent = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>DAILY TIME RECORD - ${monthName} ${year}</title>
                            <style>
                                @page { size: 210mm 297mm; margin: 0.5cm; }
                                * { margin: 0; padding: 0; box-sizing: border-box; }
                                body { 
                                    font-family: 'Arial', sans-serif; 
                                    font-size: 12px;
                                    padding: 10px;
                                    background: white;
                                }

                                .dtr-header {
                                    display: flex;
                                    justify-content: space-between;
                                    margin-bottom: 15px;
                                    border: 2px solid #000;
                                    padding: 10px;
                                }
                                .dtr-header-left {
                                    flex: 1;
                                    border-right: 1px solid #000;
                                    padding-right: 10px;
                                }
                                .dtr-header-middle {
                                    flex: 1;
                                    border-right: 1px solid #000;
                                    padding: 0 10px;
                                }
                                .dtr-header-right {
                                    flex: 1;
                                    padding-left: 10px;
                                }
                                .header-field {
                                    margin-bottom: 8px;
                                    display: flex;
                                    align-items: baseline;
                                }
                                .header-label {
                                    font-weight: bold;
                                    min-width: 80px;
                                    font-size: 9px;
                                }
                                .header-value {
                                    border-bottom: 1px dotted #000;
                                    flex: 1;
                                    min-height: 14px;
                                    padding-left: 5px;
                                    font-size: 9px;
                                }
                                .header-fixed {
                                    font-weight: bold;
                                    font-size: 9px;
                                }
                                .header-image-section {
                                    text-align: center;
                                    margin-bottom: 10px;
                                }
                                .header-image-section img {
                                    width: 100%;
                                    max-width: 100%;
                                    height: auto;
                                    display: block;
                                    margin: 0 auto;
                                }
                                .dtr-table {
                                    width: 100%;
                                    border-collapse: collapse;
                                    font-size: 8px;
                                    margin-top: 10px;
                                }
                                .dtr-table th {
                                    background-color: #333;
                                    color: white;
                                    border: 1px solid #000;
                                    padding: 4px 2px;
                                    text-align: center;
                                    font-weight: bold;
                                    font-size: 7px;
                                }
                                .dtr-table td {
                                    border: 1px solid #000;
                                    padding: 3px 2px;
                                    text-align: center;
                                    font-size: 8px;
                                }
                                .date-cell {
                                    font-weight: bold;
                                    width: 30px;
                                }
                                .time-cell {
                                    width: 50px;
                                    min-width: 50px;
                                }
                                .hours-cell {
                                    background-color: #e6e6fa;
                                    font-weight: bold;
                                    width: 60px;
                                }
                                .remarks-cell {
                                    width: 80px;
                                    text-align: left;
                                    padding-left: 5px;
                                }
                                .col-group {
                                    border-left: 2px solid #000;
                                    border-right: 2px solid #000;
                                }
                                .total-row {
                                    font-weight: bold;
                                    background-color: #f0f0f0;
                                }
                                .dtr-footer {
                                    display: flex;
                                    justify-content: space-between;
                                    margin-top: 20px;
                                    padding-top: 10px;
                                }
                                .footer-section {
                                    flex: 1;
                                    padding: 0 20px;
                                }
                                .footer-label {
                                    font-weight: bold;
                                    font-size: 9px;
                                    margin-bottom: 5px;
                                }
                                .signature-line {
                                    border-bottom: 1px solid #000;
                                    margin-bottom: 5px;
                                    padding-bottom: 2px;
                                    min-height: 40px;
                                    position: relative;
                                }
                                .signature-space {
                                    min-height: 35px;
                                }
                                .signature-label {
                                    font-size: 7px;
                                    color: #666;
                                    text-align: center;
                                    margin-top: 2px;
                                }
                                .name-line {
                                    font-size: 8px;
                                    text-align: center;
                                    margin-top: 5px;
                                    border-bottom: 1px dotted #000;
                                    padding-bottom: 2px;
                                }
                                .date-line {
                                    font-size: 8px;
                                    text-align: center;
                                    margin-top: 10px;
                                }
                                @media print {
                                    body { margin: 0; padding: 10px; }
                                    .dtr-table { page-break-inside: avoid; }
                                    .dtr-footer { page-break-inside: avoid; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="dtr-container">
                                <div class="header-image-section">
                                    <img src="${headerImagePath}" alt="DTR Header" onerror="this.style.display='none'">
                                </div>
                                
                                <div class="dtr-header">
                                    <div class="dtr-header-left">
                                        <div class="header-field">
                                            <span class="header-label">Surname:</span>
                                            <span class="header-value">${surname}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-fixed">CHMSU COLLEGE OF COMPUTER STUDIES</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-fixed">Campus: ALIJIS CAMPUS</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">Host Training Establishment (HTE):</span>
                                            <span class="header-value">${studentData.workplace_name || ''}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">OJT Supervisor:</span>
                                            <span class="header-value">${studentData.supervisor_name || ''}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="dtr-header-middle">
                                        <div class="header-field">
                                            <span class="header-label">First Name:</span>
                                            <span class="header-value">${firstName}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">Program:</span>
                                            <span class="header-fixed">BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">Department Assigned To:</span>
                                            <span class="header-value">${studentData.student_position || ''}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">Designation:</span>
                                            <span class="header-value">${studentData.student_position || ''}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="dtr-header-right">
                                        <div class="header-field">
                                            <span class="header-label">M.I.:</span>
                                            <span class="header-value">${middleInitial}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">Yr./Sec.:</span>
                                            <span class="header-fixed">${yearSec}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">For the Month of:</span>
                                            <span class="header-fixed">${monthName}</span>
                                        </div>
                                        <div class="header-field">
                                            <span class="header-label">Year:</span>
                                            <span class="header-fixed">${year}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <table class="dtr-table">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="date-cell">DATE</th>
                                            <th colspan="2" class="col-group">AM</th>
                                            <th colspan="2" class="col-group">PM</th>
                                            <th colspan="2" class="col-group">OVERTIME</th>
                                            <th colspan="2" class="col-group" style="background-color: #dda0dd;">TOTAL DUTY HOURS</th>
                                            <th rowspan="2" class="remarks-cell">REMARKS</th>
                                        </tr>
                                        <tr>
                                            <th class="col-group">AM <br>IN</th>
                                            <th class="col-group">AM <br>OUT</th>
                                            <th class="col-group">PM <br>IN</th>
                                            <th class="col-group">PM <br>OUT</th>
                                            <th class="col-group">IN</th>
                                            <th class="col-group">OUT</th>
                                            <th class="col-group" style="background-color: #dda0dd;">ACTUAL HRS:MINS</th>
                                            <th class="col-group" style="background-color: #dda0dd;">COUNTED HRS:MINS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${dtrRows}
                                    </tbody>
                                </table>
                                
                                <div class="dtr-footer">
                                    <div class="footer-section">
                                        <div class="footer-label">Prepared by:</div>
                                        <div class="signature-line">
                                            <div class="signature-space"></div>
                                            <div class="signature-label">Signature over Printed Name</div>
                                        </div>
                                        <div class="name-line">${fullName}</div>
                                        <div class="date-line">Date: _______________</div>
                                    </div>
                                    
                                    <div class="footer-section">
                                        <div class="footer-label">Reviewed by:</div>
                                        <div class="signature-line">
                                            <div class="signature-space"></div>
                                            <div class="signature-label">Signature over Printed Name</div>
                                        </div>
                                        <div class="name-line">${studentData.supervisor_name || '________________'}</div>
                                        <div class="date-line">Date: _______________</div>
                                    </div>
                                </div>
                            </div>
                        </body>
                        </html>
                    `;
                    
                    printWindow.document.write(printContent);
                    printWindow.document.close();
                    
                    printWindow.onload = function() {
                        setTimeout(() => {
                            printWindow.print();
                        }, 250);
                    };
                })
                .catch(error => {
                    console.error('Error fetching student data:', error);
                    alert('Error loading student information for DTR.');
                });
        }
        
        function formatTime(date) {
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const displayHours = hours % 12 || 12; // Convert to 12-hour format
            return String(displayHours) + ':' + String(minutes).padStart(2, '0');
        }
        
        function formatHours(hours) {
            if (!hours || hours === 0) return '0:00:00';
            const h = Math.floor(hours);
            const m = Math.floor((hours - h) * 60);
            const s = Math.floor(((hours - h) * 60 - m) * 60);
            return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        // Print all attendance function
        function printAllAttendance(data) {
            // Fetch student data for header
            fetch('get_student_dtr_data.php')
                .then(response => response.json())
                .then(studentData => {
                    const printWindow = window.open('', '_blank');
                    
                    // Filter only completed records (both time_in and time_out exist)
                    const completedRecords = data.filter(record => record.time_in && record.time_out);
                    
                    // Calculate total hours from completed records only
                    let totalHours = 0;
                    completedRecords.forEach(record => {
                        if (record.hours_earned) {
                            totalHours += parseFloat(record.hours_earned);
                        }
                    });
                    
                    const headerImagePath = window.location.protocol + '//' + window.location.host + '/bmadOJT/public/images/header.png';
                    const studentName = studentData.full_name || 'Student Name';
                    const workplaceName = studentData.workplace_name || '';
                    const supervisorName = studentData.supervisor_name || '';
                    
                    const printContent = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Complete OJT Attendance Report</title>
                            <style>
                                @page { size: 210mm 297mm; margin: 0.5cm; }
                                * { margin: 0; padding: 0; box-sizing: border-box; }
                                body { 
                                    font-family: Arial, sans-serif; 
                                    font-size: 12px;
                                    padding: 10px;
                                    background: white;
                                }
                                .header-image-section {
                                    text-align: center;
                                    margin-bottom: 10px;
                                }
                                .header-image-section img {
                                    width: 100%;
                                    max-width: 100%;
                                    height: auto;
                                    display: block;
                                    margin: 0 auto;
                                }
                                .student-info {
                                    margin-bottom: 15px;
                                    padding: 10px;
                                }
                                .student-name {
                                    text-align: center;
                                    font-size: 14px;
                                    font-weight: bold;
                                    margin-bottom: 10px;
                                }
                                .info-row {
                                    font-size: 11px;
                                    margin-bottom: 5px;
                                    padding: 3px 0;
                                }
                                .info-label {
                                    font-weight: bold;
                                    display: inline-block;
                                    min-width: 200px;
                                }
                                .info-value {
                                    display: inline-block;
                                }
                                table { 
                                    width: 100%; 
                                    border-collapse: collapse; 
                                    margin-top: 20px;
                                    font-size: 10px;
                                }
                                th, td { 
                                    border: 1px solid #000; 
                                    padding: 8px; 
                                    text-align: left; 
                                }
                                th { 
                                    background-color: #333; 
                                    color: white; 
                                    font-weight: bold;
                                    text-align: center;
                                }
                                .total-row { 
                                    background-color: #f0f0f0; 
                                    font-weight: bold; 
                                }
                                @media print { 
                                    body { margin: 0; padding: 5px; }
                                    .header-image-section { margin-bottom: 5px; }
                                    .student-info { margin-bottom: 10px; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="header-image-section">
                                <img src="${headerImagePath}" alt="DTR Header" onerror="this.style.display='none'">
                            </div>
                            
                            <div class="student-info">
                                <div class="student-name">
                                    ${studentName}
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Host Training Establishment (HTE):</span>
                                    <span class="info-value">${workplaceName}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">OJT Supervisor:</span>
                                    <span class="info-value">${supervisorName}</span>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    ${completedRecords.map(record => {
                                        const timeIn = record.time_in ? formatTime(new Date(record.time_in)) : '';
                                        const timeOut = record.time_out ? formatTime(new Date(record.time_out)) : '';
                                        
                                        return `
                                            <tr>
                                                <td>${new Date(record.date).toLocaleDateString()}</td>
                                                <td>${record.block_type.charAt(0).toUpperCase() + record.block_type.slice(1)}</td>
                                                <td>${timeIn}</td>
                                                <td>${timeOut}</td>
                                                <td>${record.hours_earned || 0}</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                    <tr class="total-row">
                                        <td colspan="4" style="text-align: right; padding-right: 10px;"><strong>Total Hours</strong></td>
                                        <td><strong>${totalHours.toFixed(2)}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </body>
                        </html>
                    `;
                    
                    printWindow.document.write(printContent);
                    printWindow.document.close();
                    
                    printWindow.onload = function() {
                        setTimeout(() => {
                            printWindow.print();
                        }, 250);
                    };
                })
                .catch(error => {
                    console.error('Error fetching student data:', error);
                    alert('Error loading student information for report.');
                });
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
                    
                    // Check if this is an excused day
                    const excusedRecord = records.find(r => r.is_excused || r.block_type === 'excuse');
                    if (excusedRecord) {
                        showExcusedDayModal(clickedDate);
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
        
        function showExcusedDayModal(date) {
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
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-check me-2"></i>${dateFormatted}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center py-5">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 mb-2">Excused</h4>
                                <p class="text-muted">You have been excused for this date. Your absence has been approved by your instructor.</p>
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
