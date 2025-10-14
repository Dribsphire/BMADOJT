# Project Brief: OJT Route

**Project Name:** OJT Route - Student OJT Tracking & Monitoring System  
**Project Type:** Capstone Project (Computer Studies - IS/IT)  
**Date:** October 5, 2025  
**Version:** 1.0  
**Status:** Planning Phase

---

## Executive Summary

**OJT Route** is a comprehensive web-based system that transforms On-the-Job Training (OJT) management for educational institutions. By eliminating manual, paper-based processes and implementing advanced geofencing technology with photo verification, the system ensures attendance integrity while providing complete transparency in student progress tracking.

**Core Innovation:** Real-time geolocation validation with 40-meter radius enforcement and photo capture prevents attendance fraud while enabling students to submit documents digitally, eliminating the need for physical school visits.

**Target Impact:** Saves students time and money, provides instructors with powerful monitoring tools, and gives administrators complete program oversight.

---

## Problem Statement

### Current Challenges

**For Students:**
- Must physically travel to school to submit OJT documents (time-consuming, expensive)
- No real-time visibility into accumulated OJT hours
- Unclear document submission status
- Manual attendance tracking prone to errors

**For Instructors:**
- Heavy administrative burden managing multiple students across different workplaces
- Difficult to verify actual workplace attendance
- Time-consuming document review process
- No early warning system for struggling students

**For Administrators:**
- Limited visibility into program-wide OJT status
- Manual consolidation of reports
- Attendance fraud concerns
- Compliance documentation challenges

### Business Impact

- **Time Waste:** Students spend 2-4 hours per week traveling to school for submissions
- **Lack of Transparency:** No real-time tracking of progress toward 600-hour requirement
- **Fraud Risk:** Manual attendance systems vulnerable to manipulation
- **Administrative Overhead:** Instructors spend 30-40% of time on OJT paperwork

---

## Solution Overview

### Core Value Proposition

> *"OJT Route eliminates manual, paper-based OJT tracking while ensuring attendance integrity through advanced geofencing and photo verification technology."*

### Key Differentiators

1. **Geofencing with Photo Verification** - 40-meter radius enforcement with GPS accuracy validation
2. **Complete Digital Transformation** - End-to-end paperless workflow
3. **Proactive Student Monitoring** - Pattern detection and status labels (On Track/Needs Attention/At Risk)
4. **Flexible Administration** - Admin can switch to instructor role for smaller programs

---

## Target Users

### Primary Users

**1. Students (BSIT-IS & BSIT-IT)**
- Track 600-hour OJT requirement
- Submit documents digitally
- Time-in/time-out with location verification
- Communicate with instructors

**2. Instructors**
- Monitor section of students
- Review and approve documents
- Track attendance patterns
- Identify at-risk students

**3. Administrators**
- System-wide oversight
- Bulk user registration
- Section management
- Program analytics

### User Scale
- **Phase 1:** Support 100-200 students
- **Scalable to:** 1,000+ students across multiple programs

---

## Core Features

### MVP Features (Must-Have)

#### 🎯 Attendance System (WOW FACTOR #1)
- **Geofencing:** 40m radius tolerance with GPS accuracy validation
- **Time-In:** Location verification + Real-time photo capture
- **Time-Out:** Location verification only (no photo)
- **Blocks:** Morning, Afternoon, Overtime (optional)
- **Forgot Time-Out:** Correction request workflow with letter submission
- **Automated Hours:** Calculates and tracks toward 600-hour requirement

#### 📄 Document Management (WOW FACTOR #2)
- **7 Required Documents:** MOA, Endorsement Letter, Parental Consent, Misdemeanor Penalty, OJT Plan, Notarized Consent, Pledge
- **Digital Workflow:** Upload → Review → Approve/Revise
- **Compliance Gate:** All 7 documents must be approved before attendance access
- **Optional Deadlines:** Instructors can set submission deadlines
- **Bulk Operations:** Instructors can approve multiple documents at once

#### 👥 User Management
- **School ID Login:** Format LJD12040300 (initials + birthdate)
- **Bulk Registration:** CSV import with validation
- **Access Control:**
  - Instructors blocked if no section assigned
  - Students blocked from attendance until profile + documents complete
- **Profile Management:** Photo upload, workplace setup (one-time via map)

#### 💬 Communication System
- **Private Messaging:** Student ↔ Instructor
- **Group Chat:** Instructor → Section
- **Email Notifications:** 11 event types via PHPMailer
- **Read Receipts:** Message tracking

#### 📊 Dashboards

**Admin Dashboard:**
- Master student list (filterable by IS/IT)
- Missing documents report
- OJT hours progress bars
- Email status widget (sent/pending/failed)

**Instructor Dashboard:**
- Pending documents list
- Student status overview (On Track/Needs Attention/At Risk)
- Section roster
- Document review interface

**Student Dashboard:**
- OJT hours tracker (X/600 hours)
- Document status checklist (X/7 approved)
- Attendance records with photos
- Profile completion status

### Priority Enhancements (Nice-to-Have)

**Priority 1:** Calendar view of student time-ins/time-outs  
**Priority 2:** Notification history for all users  
**Priority 3:** Quick action buttons (bulk approve, send announcements)  
**Priority 4:** Advanced search & filter  
**Priority 5:** Export attendance for school records/compliance

---

## Technical Architecture

### Technology Stack

**Backend:**
- PHP (latest stable)
- MySQL 8.0+

**Frontend:**
- HTML5, Bootstrap 5, JavaScript ES6+
- Leaflet.js + OpenStreetMap (mapping)

**Infrastructure:**
- PHPMailer 6.x (email notifications)
- JavaScript Geolocation API (GPS)
- XAMPP (development)
- Git (version control)

### Database Design
- **10 Tables:** users, sections, attendance, forgot_timeout_requests, document_templates, document_submissions, messages, notifications, email_queue, activity_logs
- **Proper indexing** on school_id, user_id, section_id, status fields
- **Foreign key constraints** for referential integrity

### Key Technical Implementations

**Geofencing Logic:**
- Haversine formula for distance calculation
- GPS accuracy factored into validation
- `effectiveDistance = distance + accuracy`
- Hard block if > 40m radius

**File Management:**
- Organized folder structure (profiles, attendance, documents, letters)
- Auto-compression for images (80% quality)
- File size limits: 2-10 MB depending on type
- Security: Whitelist extensions, MIME validation, sanitized filenames

**Email System:**
- Immediate batch sending (30 students at once)
- Queue system for failed emails (max 3 retries)
- Admin dashboard monitoring
- Rate limiting (10 emails/user/hour)

---

## Success Metrics

### Technical KPIs
- ✅ Geofencing accuracy: 100% within 40m radius
- ✅ Photo compression: 50%+ file size reduction
- ✅ Email delivery: >95% success rate
- ✅ Page load times: <3 seconds
- ✅ System uptime: >99%

### User Experience KPIs
- ✅ Navigation: <3 clicks to any feature
- ✅ Mobile responsive: Works on all devices
- ✅ User onboarding: <10 minutes to complete profile
- ✅ Document submission: <5 minutes per document

### Business KPIs
- ✅ Time saved: 2-4 hours/week per student (no school visits)
- ✅ Attendance fraud: Reduced to near-zero
- ✅ Document processing: 50% faster for instructors
- ✅ Admin oversight: Real-time visibility into program status

---

## Project Timeline

### Development Phases (12 Weeks)

**Phase 1: Foundation (Weeks 1-2)**
- Database setup and migrations
- User authentication (school ID login)
- Role-based access control
- Basic CRUD operations

**Phase 2: Attendance System (Weeks 3-4)**
- Geolocation implementation
- Map interface for workplace setup
- Time-in with photo capture
- Time-out location verification
- Forgot time-out workflow

**Phase 3: Document Management (Weeks 5-6)**
- Template upload system
- Document submission workflow
- Approval/revision process
- Bulk operations
- 7 required documents pre-loaded

**Phase 4: Communication (Week 7)**
- Messaging system (private + group)
- PHPMailer integration
- Email templates (11 types)
- Notification system

**Phase 5: Dashboards & Analytics (Week 8)**
- Dashboard development (all 3 roles)
- Charts and progress bars
- Pattern detection logic
- Student status auto-updates

**Phase 6: Polish & Testing (Weeks 9-10)**
- UI/UX refinement
- Comprehensive testing
- Bug fixes
- Performance optimization
- Security audit

**Phase 7: Documentation & Defense Prep (Weeks 11-12)**
- User manual
- Technical documentation
- Demo preparation
- Presentation materials

---

## Risk Assessment

### Technical Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| GPS accuracy issues | High | Medium | Factor accuracy into validation; test on multiple devices |
| PHPMailer Gmail limits | Medium | Low | Implement queue system; rate limiting |
| Large photo file sizes | Medium | Medium | Auto-compression; file size limits |
| Database performance | High | Low | Proper indexing; query optimization |

### Scope Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Feature creep | High | High | Strict MVP focus; NICE-TO-HAVE clearly separated |
| Time constraints | High | Medium | Prioritized phases; parallel documentation work |
| Integration complexity | Medium | Medium | Modular development; early integration testing |

### Academic/Panel Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Originality concerns | High | Low | Emphasize geofencing innovation; custom workflows |
| Complexity questions | Medium | Low | Detailed technical documentation; code comments |
| Practical viability | Medium | Low | User research backing; real pain points solved |

---

## Competitive Advantages

### 1. Technical Innovation (Geofencing)
- Most OJT systems use manual attendance or simple check-ins
- OJT Route prevents fraud automatically with location + photo verification
- GPS accuracy validation adds extra layer of precision

### 2. Complete Solution
- Not just attendance OR documents - full OJT lifecycle management
- Integrated communication system
- Comprehensive analytics and reporting

### 3. Practical Impact
- Solves actual, documented pain points
- Ready for real-world deployment
- Immediate ROI: Time and cost savings for students

### 4. Well-Architected
- Proper database design with normalization
- Security best practices from day one
- Scalability planning (can handle 1,000+ students)

### 5. Thorough Planning
- 85+ features identified through structured brainstorming
- Clear MVP prioritization
- Detailed technical architecture

---

## Capstone Defense Strategy

### Primary Talking Points

**1. Innovation:**
> "Our geofencing system with GPS accuracy validation prevents attendance fraud while remaining user-friendly. We use the Haversine formula for precise distance calculation between coordinates."

**2. Impact:**
> "Students save 2-4 hours per week and eliminate travel costs by submitting documents digitally instead of visiting school."

**3. Technical Depth:**
> "We've implemented a 10-table normalized database, integrated Leaflet for interactive mapping, and built a queue-based email system with PHPMailer."

**4. User-Centric Design:**
> "Through role-playing brainstorming with actual user scenarios, we identified 85+ features and prioritized based on real pain points."

**5. Scalability:**
> "The modular architecture allows the system to scale from 100 to 1,000+ students across multiple programs and schools."

### Live Demo Flow

1. **Geofencing Demo** - Show live GPS validation and 40m radius enforcement
2. **Photo Capture** - Demonstrate real-time photo capture with compression
3. **Document Workflow** - Walk through upload → review → approve process
4. **Admin Dashboard** - Display analytics, student status, email monitoring
5. **Email Notifications** - Show triggered emails for various events
6. **Student Progress** - Track hours toward 600-hour requirement

---

## Resource Requirements

### Development Team
- **Project Leader:** Pia Fernandez
- **Developer:** Manuel A. Colorado
- **Team Member:** Kyla Rolan
- **Team Size:** 3 members (Full-Stack Development, UI/UX, QA)

### Infrastructure
- **Development:** XAMPP (local) - Free
- **Version Control:** GitHub - Free
- **Email Service:** Gmail SMTP (App Password) - Free
- **Maps:** OpenStreetMap - Free
- **Deployment:** Shared hosting or VPS - $5-20/month

### Software/Tools
- **IDE:** VS Code, PHPStorm - Free/Licensed
- **Design:** Figma, Adobe XD - Free tier available
- **Database:** MySQL Workbench - Free
- **Testing:** PHPUnit, Browser DevTools - Free

### Testing Devices
- Multiple smartphones (Android/iOS) for GPS testing
- Different browsers (Chrome, Firefox, Safari, Edge)
- Various screen sizes for responsive testing

---

## Stakeholder Communication

### Internal Stakeholders

**Development Team:**
- **Frequency:** Daily standups, weekly sprint reviews
- **Tools:** Git, Slack/Discord, Trello/Jira
- **Documentation:** Technical specs, API docs, code comments

**Capstone Advisors:**
- **Frequency:** Bi-weekly progress reports
- **Deliverables:** Phase completion demos, documentation milestones
- **Key Concerns:** Technical complexity, timeline adherence, originality

### External Stakeholders (Future)

**School Administration:**
- Potential system adopters
- Need: Cost-benefit analysis, deployment plan, training materials

**Students & Instructors:**
- End users for UAT (User Acceptance Testing)
- Need: User manuals, tutorial videos, support channels

---

## Next Steps

### Immediate Actions (Week 1)

**1. Project Setup**
- [ ] Create GitHub repository
- [ ] Set up XAMPP development environment
- [ ] Install required tools (VS Code, MySQL Workbench)
- [ ] Configure Git workflow (branching strategy)

**2. Design Phase**
- [ ] Create wireframes for 3 dashboards
- [ ] Design attendance flow mockups
- [ ] Plan document management UI
- [ ] Design messaging interface
- [ ] Create color scheme and branding

**3. Documentation**
- [ ] Create Entity Relationship Diagram (ERD)
- [ ] Write functional requirements document
- [ ] Document API endpoints
- [ ] Set up project management board (Trello/Jira)

**4. Technical Preparation**
- [ ] Test Leaflet.js with OpenStreetMap
- [ ] Set up PHPMailer with Gmail App Password
- [ ] Prototype geofencing logic
- [ ] Test camera API on target devices

### Weekly Milestones

**Week 1-2:** Foundation (Auth, Database, CRUD)  
**Week 3-4:** Attendance System (Geofencing, Photos)  
**Week 5-6:** Document Management  
**Week 7:** Communication System  
**Week 8:** Dashboards & Analytics  
**Week 9-10:** Testing & Polish  
**Week 11-12:** Documentation & Defense Prep

---

## Approval & Sign-Off

### Project Approval

**Approved By:**
- [ ] Capstone Advisor (Jayrelle Sy): _________________________ Date: _______
- [ ] Department Head: _________________________ Date: _______
- [ ] Project Leader (Pia Fernandez): _________________________ Date: _______

### Change Control

**Major changes to scope, timeline, or technical approach require:**
1. Written proposal documenting the change
2. Impact analysis (scope, time, resources)
3. Approval from capstone advisor
4. Updated project brief version

---

## Appendix

### Quick Reference: Key Numbers

- **OJT Requirement:** 600 hours
- **Geofence Radius:** 40 meters
- **Required Documents:** 7
- **User Roles:** 3 (Admin, Instructor, Student)
- **Programs:** 2 (IS, IT)
- **Attendance Blocks:** 3 per day (Morning, Afternoon, Overtime)
- **Database Tables:** 10
- **Email Types:** 11
- **Max Email Attempts:** 3
- **GPS Accuracy Threshold:** 50 meters
- **Photo Compression:** 80% quality

### Related Documents

- **Brainstorming Session Results:** `docs/brainstorming-session-results.md`
- **Technical Architecture:** TBD
- **PRD (Product Requirements):** TBD
- **User Stories & Epics:** TBD
- **Test Plan:** TBD

### Contact Information

**Project Leader:** Pia Fernandez  
**Developer:** Manuel A. Colorado  
**Email:** coloradomanuel.002@gmail.com  
**Team Member:** Kyla Rolan  
**Capstone Advisor:** Jayrelle Sy  
**Institution:** Carlos Hilado Memorial State University  
**Program:** Bachelor of Science in Information Technology

---

**Document Version:** 1.0  
**Last Updated:** October 5, 2025  
**Next Review:** Start of Phase 1 (Week 1)

---

*This project brief serves as the primary reference document for OJT Route development. All team members and stakeholders should review this document before beginning work. Updates to this brief require change control approval.*
