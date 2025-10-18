# Student Documents.php Analysis

## 📋 **File Overview**
**File**: `public/student/documents.php`  
**Purpose**: Document management interface for students to view, download, and submit OJT documents  
**Authentication**: Student role required  
**Dependencies**: Multiple services and database tables  

---

## 🔗 **Connected Files & Services**

### **1. Core Services**
- **`src/Services/DocumentService.php`** - Document CRUD operations
- **`src/Services/OverdueService.php`** - Overdue document detection
- **`src/Middleware/AuthMiddleware.php`** - Authentication & authorization
- **`src/Utils/Database.php`** - Database connection management

### **2. Related Files**
- **`public/student/view_document.php`** - Document preview/download handler
- **`public/student/student-sidebar.php`** - Navigation sidebar
- **`public/student/profile.php`** - Student profile (redirect target)

### **3. External Dependencies**
- **Bootstrap 5.3.0** - UI framework
- **Bootstrap Icons** - Icon library
- **Google Fonts (Poppins)** - Typography

---

## 🗄️ **Database Tables & Columns**

### **Primary Tables Used:**

#### **1. `users` Table**
```sql
- id (INT UNSIGNED, PRIMARY KEY)
- school_id (VARCHAR(20), UNIQUE)
- full_name (VARCHAR(255))
- role (ENUM: 'student', 'instructor', 'admin')
- section_id (INT UNSIGNED, NULL)
- profile_picture (VARCHAR(500), NULL)
```

#### **2. `student_profiles` Table**
```sql
- user_id (INT UNSIGNED, FOREIGN KEY)
- workplace_name (VARCHAR(255))
- supervisor_name (VARCHAR(255))
- ojt_start_date (DATE)
```

#### **3. `documents` Table**
```sql
- id (INT UNSIGNED, PRIMARY KEY)
- document_name (VARCHAR(255))
- document_type (ENUM: 'moa', 'endorsement', 'parental_consent', 'misdemeanor_penalty', 'ojt_plan', 'notarized_consent', 'pledge', 'weekly_report', 'other')
- file_path (VARCHAR(500))
- uploaded_by (INT UNSIGNED, FOREIGN KEY)
- uploaded_for_section (INT UNSIGNED, NULL)
- deadline (DATE, NULL)
- created_at (TIMESTAMP)
```

#### **4. `student_documents` Table**
```sql
- id (INT UNSIGNED, PRIMARY KEY)
- student_id (INT UNSIGNED, FOREIGN KEY)
- document_id (INT UNSIGNED, FOREIGN KEY)
- submission_file_path (VARCHAR(500), NULL)
- status (ENUM: 'pending', 'approved', 'rejected', 'revision_required')
- instructor_feedback (TEXT, NULL)
- submitted_at (TIMESTAMP, NULL)
- reviewed_at (TIMESTAMP, NULL)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

#### **5. `sections` Table**
```sql
- id (INT UNSIGNED, PRIMARY KEY)
- section_code (VARCHAR(20), UNIQUE)
- section_name (VARCHAR(100))
- instructor_id (INT UNSIGNED, NULL)
```

---

## 🔍 **Key Database Queries**

### **1. Authentication & Profile Check**
```php
// Check if student has profile
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");

// Get user's section
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
```

### **2. Document Retrieval**
```php
// Get student submissions
$stmt = $pdo->prepare("
    SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.student_id = ?
    ORDER BY d.document_type
");

// Get template documents
$stmt = $pdo->prepare("
    SELECT file_path, deadline, created_at 
    FROM documents 
    WHERE document_type = ? 
    AND (uploaded_for_section = ? OR uploaded_for_section IS NULL) 
    AND uploaded_by = 1 
    LIMIT 1
");
```

### **3. Document Submission**
```php
// Insert/Update submission
$stmt = $pdo->prepare("
    INSERT INTO student_documents (student_id, document_id, submission_file_path, status, submitted_at, created_at, updated_at) 
    VALUES (?, ?, ?, 'pending', NOW(), NOW(), NOW())
    ON DUPLICATE KEY UPDATE 
    submission_file_path = VALUES(submission_file_path),
    status = 'pending',
    submitted_at = NOW(),
    updated_at = NOW()
");
```

---

## 📊 **Data Flow & Processing**

### **1. Document Status Mapping**
```php
$statusText = match($status) {
    'approved' => 'Completed',
    'pending' => 'Sent',
    'revision_required' => 'Suggest Edits',
    'rejected' => 'Expired',
    default => 'Draft'
};
```

### **2. Document Categories**
- **Pre-loaded Templates**: System templates (`uploaded_by = 1`)
- **Additional Requirements**: Custom documents (`document_type = 'other'`)

### **3. File Path Resolution**
```php
$testPaths = [
    $templatePath,
    '../../' . $templatePath,
    '../' . $templatePath
];
```

---

## ⚠️ **Common Error Sources**

### **1. Database Column Mismatches**
- **Error**: `Unknown column 'section_id'` 
- **Fix**: Use `uploaded_for_section` instead
- **Error**: `Unknown column 'file_path'` in student_documents
- **Fix**: Use `submission_file_path` for student uploads

### **2. File Path Issues**
- **Double Extensions**: `.pdf.pdf` → `.pdf`
- **Path Resolution**: Multiple relative path attempts
- **File Existence**: Check before display

### **3. Status Mapping**
- **Database Status**: `pending`, `approved`, `revision_required`, `rejected`
- **Display Status**: `Sent`, `Completed`, `Suggest Edits`, `Expired`

---

## 🛠️ **Service Methods Used**

### **DocumentService Methods:**
- `getDocumentsForSection($sectionId)` - Get section documents
- `getCustomDocumentsForSection($sectionId)` - Get custom documents
- `getRequiredDocumentTypes()` - Get required document types

### **OverdueService Methods:**
- `getOverdueDocumentsForStudent($studentId)` - Get overdue documents

---

## 📁 **File Upload Handling**

### **Upload Directory**: `../../uploads/student_documents/`
### **File Validation**:
- **Size Limit**: 10MB
- **Allowed Types**: PDF, DOC, DOCX
- **MIME Type Check**: `mime_content_type()`

### **File Naming**:
```php
$fileName = 'submission_' . $_SESSION['user_id'] . '_' . $documentId . '_' . time() . '.' . $fileExtension;
```

---

## 🔒 **Security Considerations**

### **1. Authentication**
- Student role required
- Session-based authentication
- Profile existence check

### **2. File Security**
- Path validation
- File type restrictions
- Size limits
- Directory traversal prevention

### **3. SQL Injection Prevention**
- Prepared statements used throughout
- Parameter binding for all queries

---

## 🎯 **Key Features**

### **1. Document Management**
- View all documents (templates + custom)
- Download templates
- Submit documents
- Track submission status

### **2. Status Tracking**
- Real-time status updates
- Progress calculation
- Overdue detection

### **3. File Operations**
- PDF preview in modal
- File download
- Bulk download of completed documents

### **4. User Interface**
- Responsive design
- Status filtering
- Search functionality
- Modern UI with Bootstrap

---

## 🚨 **Potential Issues to Avoid**

### **1. Database Schema Changes**
- Always check actual table structure
- Use correct column names
- Verify foreign key relationships

### **2. File Path Consistency**
- Handle relative vs absolute paths
- Check file existence before operations
- Clean double extensions

### **3. Status Synchronization**
- Keep database and display statuses in sync
- Handle edge cases (null values)
- Validate status transitions

### **4. Error Handling**
- Wrap database operations in try-catch
- Validate file uploads
- Provide meaningful error messages

---

## 📝 **Best Practices**

### **1. Database Operations**
- Always use prepared statements
- Check for null values with `??` operator
- Use transactions for complex operations

### **2. File Handling**
- Validate file types and sizes
- Use secure file naming
- Check file existence before operations

### **3. User Experience**
- Provide loading states
- Show meaningful error messages
- Implement proper validation

### **4. Code Organization**
- Separate concerns (services, middleware)
- Use consistent naming conventions
- Document complex logic

---

This analysis provides a comprehensive understanding of the `student/documents.php` file, its connections, database interactions, and potential issues to avoid when making modifications.
