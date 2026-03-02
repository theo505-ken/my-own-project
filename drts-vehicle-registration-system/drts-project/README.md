# 🚗 DRTS Online Vehicle Registration System
**University of Botswana | Computer Science – Software Engineering**  
**Student:** Theo T Kentsheng (202201306)  
**Supervisors:** Dr Zimudzi & Dr Zuva  
**Year:** 2026

---

## Project Overview
A full-stack web application for Botswana's Department of Road Transport and Safety (DRTS) that digitises the vehicle registration process. Vehicle owners can register, upload documents, pay fees, and track applications from anywhere with internet access.

---

## Project Structure

```
drts-project/
├── public/                  # Frontend (HTML, CSS, JS)
│   ├── index.html           # Single-page application
│   ├── css/
│   │   └── style.css        # Full stylesheet
│   └── js/
│       └── app.js           # Frontend logic (fetch API)
│
├── api/                     # PHP REST API backend
│   ├── config.php           # DB config, auth helpers, JSON response
│   ├── register.php         # POST – Create user account
│   ├── login.php            # POST – Authenticate, return session token
│   ├── submit_application.php  # POST – Submit registration application
│   ├── my_applications.php  # GET  – List user's applications
│   ├── upload_document.php  # POST – Upload supporting documents
│   ├── check_status.php     # GET  – Public status lookup by reference
│   ├── create_payment.php   # POST – Create Stripe PaymentIntent
│   ├── confirm_payment.php  # POST – Confirm payment, generate plate
│   ├── all_applications.php # GET  – Admin: all applications + stats
│   └── update_status.php    # POST – Admin: approve/reject/review
│
└── db/
    └── schema.sql           # Full MySQL database schema + seed data
```

---

## Technology Stack

| Layer      | Technology                         |
|------------|-------------------------------------|
| Frontend   | HTML5, CSS3, JavaScript (ES2025)    |
| Backend    | PHP 8.5 (REST API)                  |
| Database   | MySQL 8.4                           |
| Payments   | Stripe API                          |
| Dev Tools  | VS Code, Postman, Git               |
| Server     | Apache/Nginx + PHP-FPM              |

---

## API Endpoints

| Method | Endpoint                     | Auth       | Description                        |
|--------|------------------------------|------------|------------------------------------|
| POST   | /api/register.php            | None       | Register new user account           |
| POST   | /api/login.php               | None       | Login, receive session token        |
| POST   | /api/submit_application.php  | User       | Submit vehicle registration         |
| GET    | /api/my_applications.php     | User       | List own applications               |
| POST   | /api/upload_document.php     | User       | Upload a document file              |
| GET    | /api/check_status.php        | None       | Public status check by reference    |
| POST   | /api/create_payment.php      | User       | Initiate Stripe payment             |
| POST   | /api/confirm_payment.php     | User       | Confirm payment completion          |
| GET    | /api/all_applications.php    | Officer+   | Admin: all applications + stats     |
| POST   | /api/update_status.php       | Officer+   | Admin: update application status    |

---

## Database Schema (Key Tables)

- **users** – Vehicle owners, officers, admins (role-based)
- **vehicle_types** – Types with registration fees (P200–P1,200)
- **vehicles** – Vehicle records linked to owners
- **applications** – Registration applications with status tracking
- **documents** – Uploaded supporting files per application
- **payments** – Stripe payment records
- **sessions** – User session tokens
- **audit_log** – Full audit trail of all actions

---

## Setup Instructions

### 1. Database
```sql
mysql -u root -p < db/schema.sql
```

### 2. PHP Configuration
Edit `api/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'drts_vrs');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('STRIPE_SECRET_KEY', 'sk_live_...');
define('STRIPE_PUBLIC_KEY', 'pk_live_...');
```

### 3. Web Server
Place the project in your web server root (e.g. `/var/www/html/drts/`).

Configure Apache `.htaccess` for API routing:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/(.*)$ api/$1 [L]
```

### 4. File Uploads Directory
```bash
mkdir -p uploads && chmod 755 uploads
```

### 5. HTTPS (Production)
Configure SSL certificate (Let's Encrypt recommended) — required for Stripe payments.

---

## User Roles

| Role    | Permissions                                                  |
|---------|--------------------------------------------------------------|
| public  | Register, submit applications, upload docs, pay, track status |
| officer | All public permissions + review/approve/reject applications  |
| admin   | All officer permissions + manage users, generate reports     |

---

## Registration Fees

| Vehicle Type      | Fee (BWP) |
|-------------------|-----------|
| Private Car       | P 550     |
| Light Commercial  | P 750     |
| Heavy Commercial  | P 1,200   |
| Motorcycle        | P 300     |
| Minibus / Combi   | P 850     |
| Trailer           | P 200     |

---

## Development Methodology
**Agile** iterative approach with focused sprints:
1. Database schema & backend API
2. User auth & application submission  
3. Document upload & payment integration
4. Admin dashboard & reporting
5. Security, testing & UAT
6. Deployment & HTTPS configuration

---

## Security Features
- bcrypt password hashing (cost 12)
- Session-based authentication (server-side token validation)
- Role-based access control on every protected endpoint
- SQL injection prevention via PDO prepared statements
- MIME type validation for file uploads
- File size limits (5MB)
- Audit logging of all significant actions
- HTTPS required for production (Stripe requirement)

---

## References
- Oloyede et al. (2024) – Web-based vehicle registration, Nigeria
- Adisa & Eludiora (2021) – Secured Vehicle Registration System (SVRS)
- Chimezie & Chukwudi (2020) – Computerised vehicle licence registration
- Oni et al. (2015) – Web-based portal for vehicle licensing management
