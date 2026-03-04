# SafeClinic Vulnerable - Educational Security Reference

## 🎯 Project Overview

This folder contains an **intentionally vulnerable** version of the SafeClinic medical application. It demonstrates common security mistakes and serves as an educational reference to compare against the secure version.

**Status:** ✅ All role-based functionality implemented with vulnerabilities highlighted

**Database:** Uses separate database `safeclinic_db_vulnerable` with plain-text passwords

---

## ⚠️ Security Issues (Intentional for Learning)

This version contains the following vulnerabilities:

### 1. **SQL Injection**
- Direct string interpolation in queries
- No prepared statements
- User input directly embedded in SQL

```php
// VULNERABLE in index.php
$sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
```

**Attack Example:** `admin' OR '1'='1`

### 2. **Plain-Text Passwords**
- Passwords stored without hashing
- No bcrypt or password_hash()
- Anyone with database access can read passwords

```php
// Database stores: admin123, doctor456, nurse789
// Should be: $2y$10$...bcrypt hash...
```

### 3. **No Input Validation**
- Username/password not validated
- No regex patterns or length checks
- XSS vulnerabilities in forms

### 4. **Missing CSRF Protection**
- No CSRF tokens in forms
- No verification on POST requests
- Vulnerable to cross-site request forgery attacks

### 5. **No Audit Logging**
- No record of who accessed what
- No modification tracking
- Cannot detect security incidents

---

## 📁 Folder Structure

```
vulnerable/
├── includes/
│   └── db_connect.php          # Points to safeclinic_db_vulnerable
├── index.php                   # Login (SQL injection vulnerability)
├── register.php                # Registration (no validation)
├── dashboard.php               # Role-based dashboard
├── manage_users.php            # Admin CRUD (vulnerable)
├── manage_records.php          # Doctor CRUD (vulnerable)
├── update_vitals.php           # Nurse update-only (UI-enforced only)
├── view_record.php             # Patient records (IDOR vulnerability)
└── logout.php                  # Session cleanup
```

---

## 🗄️ Database Setup

### Create Vulnerable Database:
```bash
mysql -u root -p < /path/to/safeclinic/sql/init_db_vulnerable.sql
```

### Database Name: `safeclinic_db_vulnerable`

**Tables:**
- `users` - Plain-text passwords (VULNERABLE!)
- `patients` - Linked to users
- `medical_records` - Diagnoses and treatments

### Test Accounts:
```
Admin:     admin_user / admin123
Doctor:    dr_smith / doctor456
Nurse:     nurse_joy / nurse789
Patient 1: patient_zero / password
Patient 2: patient_one / password123
```

---

## 👥 Role-Based Functionality

### Admin (`admin_user`)
- **File:** `manage_users.php`
- **Capabilities:**
  - Create new user accounts
  - Delete user accounts
- **Vulnerabilities:**
  - SQL injection in INSERT/DELETE queries
  - No CSRF token verification
  - No input validation
  - Plain-text passwords stored

### Doctor (`dr_smith`)
- **File:** `manage_records.php`
- **Capabilities:**
  - Create medical records
  - Read all patient records
  - Update any existing record
  - Delete any record
- **Vulnerabilities:**
  - All queries vulnerable to SQL injection
  - XSS in diagnosis and treatment fields
  - No access control (could access patient data as someone else)
  - No activity logging

### Nurse (`nurse_joy`)
- **File:** `update_vitals.php`
- **Capabilities:**
  - Read ALL patient records (unlimited access)
  - Update treatment notes and vitals
  - Cannot delete (UI-only enforcement)
- **Vulnerabilities:**
  - SQL injection in UPDATE queries
  - Delete button not present, but not enforced at code level
  - No CSRF protection
  - Can still see all patient data (privacy issue for nurses)

### Patient (`patient_zero`, `patient_one`)
- **File:** `view_record.php`
- **Capabilities:**
  - View their own medical records
  - Read-only access
- **Vulnerabilities:**
  - **IDOR (Insecure Direct Object Reference):** Can access any patient's records by changing `?id=1` to `?id=2`
  - No access control in queries
  - Should only see own records, but can see others

---

## 🔍 Security Testing Guide

### 1. Test SQL Injection in Login
1. Navigate to `/vulnerable/index.php`
2. Username: `admin' OR '1'='1`
3. Password: `anything`
4. **Result:** Should login without knowing password!

### 2. Test SQL Injection in Record Creation
1. Login as `dr_smith` / `doctor456`
2. Go to `manage_records.php`
3. Diagnosis field: `Treatment', 'hacked') OR ('1'='1`
4. **Result:** Malicious SQL injected into database!

### 3. Test IDOR in View Records
1. Login as `patient_zero` / `password`
2. Visit `/vulnerable/view_record.php?id=1`
3. Change URL to `view_record.php?id=2`
4. **Result:** Can view another patient's records!

### 4. Test No Validation
1. Register with username: `' OR '1'='1`
2. Register with password: `123`
3. **Result:** Invalid inputs accepted without validation!

### 5. Test No Audit Logging
1. Login as doctor and create a record
2. Check if any logs are created
3. **Result:** No audit trail - cannot track who did what!

---

## 📊 Comparison with Secure Version

| Feature | Vulnerable | Secure |
|---------|-----------|--------|
| **SQL Queries** | Direct string interpolation | Prepared statements with bound parameters |
| **Passwords** | Plain-text in database | Bcrypt hashing (cost=10) |
| **Input Validation** | None | Regex patterns + length checks |
| **CSRF Protection** | No tokens | Random 32-byte hex tokens |
| **Access Control** | UI-only enforcement | Database query-level enforcement |
| **Audit Logging** | Not logged | SecurityLogger to `/var/log/safeclinic_security.log` |
| **Error Messages** | Detailed (info leak) | Generic (secure) |

---

## 🎓 Learning Objectives

By studying this vulnerable version, you'll learn:

1. **How SQL injection works** - and why prepared statements fix it
2. **Why password hashing matters** - and how bcrypt protects users
3. **What input validation does** - and what happens without it
4. **How CSRF attacks happen** - and how tokens prevent them
5. **Why audit logging is essential** - for security and compliance
6. **What IDOR vulnerabilities are** - and how to prevent them
7. **The difference between UI and security enforcement** - UI is not security!

---

## 🚀 Running the Application

### 1. Create the database:
```bash
mysql -u root -p < sql/init_db_vulnerable.sql
```

### 2. Access via browser:
```
http://localhost/SafeClinic/vulnerable/
```

### 3. Login with any test account (see Test Accounts above)

---

## 🔗 Comparing with Secure Version

To see how each vulnerability is fixed, visit:
- **Secure Version:** `/secure/` folder
- **Comparison Guide:** `/secure/COMPARISON.md`
- **Security Documentation:** `/secure/SECURITY_DOCUMENTATION.md`

---

## ⚖️ Legal & Ethical Notice

This vulnerable code is provided **FOR EDUCATIONAL PURPOSES ONLY**:

- ✅ DO study this to understand security vulnerabilities
- ✅ DO compare with the secure version to learn fixes
- ✅ DO use for training and learning
- ❌ DO NOT deploy to production
- ❌ DO NOT use on systems with real user data
- ❌ DO NOT attempt to exploit other systems

---

## 📞 Questions?

Refer to:
1. `/secure/SECURITY_DOCUMENTATION.md` - Deep technical explanations
2. `/secure/COMPARISON.md` - Side-by-side code examples
3. Comments in code files - Inline vulnerability notes

**Learn, understand, and apply these lessons to build secure applications!**
