# 🌙 Ramadan Buffet Booking and Management System

## 📌 Overview

The Ramadan Buffet Booking and Management System is a web-based platform designed to streamline the reservation and management of Iftar buffet events.

This system eliminates manual booking processes by providing:

* Real-time slot availability
* Structured role-based operations
* Secure payment verification workflow

It is designed for event organizers, staff, and customers to efficiently manage high-demand Ramadan buffet sessions.

---

## 🎯 Problem Statement

Traditional booking systems for Ramadan buffets often suffer from:

* Overbooking due to lack of real-time tracking
* Manual payment verification errors
* Poor coordination between staff roles

This system solves those issues through automation and centralized management.

---

## ✨ Key Features

### 🧾 Booking System

* Real-time slot availability tracking
* Dynamic pricing for promotional periods
* Automated booking reference generation

### 👥 Role-Based Access Control

* Admin
* Banquet Staff
* Entry Duty
* Finance Team
* Sales Team

Each role has specific permissions and dashboards.

### 💳 Payment Management

* Upload payment proof
* Admin verification workflow
* Status tracking (Pending / Approved / Rejected)

### 🎟️ Check-in System

* Ticket validation
* Reprint functionality
* Entry monitoring

### 📊 Admin Dashboard

* Revenue tracking
* Booking analytics
* System configuration panel

---

## 🛠️ Technology Stack

* Backend: PHP 8.2+
* Frontend: HTML5, CSS3, JavaScript
* Database: MySQL / MariaDB
* Authentication: Session-based RBAC

---

## 🧱 System Architecture

```
User (Customer)
      ↓
Frontend (HTML/CSS/JS)
      ↓
Backend (PHP)
      ↓
Database (MySQL)
      ↓
Admin & Staff Dashboard
```

---

## 📂 Project Structure

```
admin/        → Admin and staff dashboards
public/       → User-facing pages
config/       → Database configuration
assets/       → CSS, JS, images
uploads/      → Payment proofs
backups/      → System backups
database.sql  → Database schema
```

---

## ⚙️ Installation Guide

### 1. Clone Repository

```bash
git clone https://github.com/AfiqAiman1/Ramadan-Booking-and-Management-System.git
cd Ramadan-Booking-and-Management-System
```

### 2. Setup Database

* Create a database in phpMyAdmin
* Import `database.sql`

### 3. Configure Connection

Edit:

```
config/config.php
```

Update:

* DB name
* Username
* Password

### 4. Run Project

* Place project in `htdocs` (XAMPP) or server directory
* Start Apache & MySQL
* Open:

```
http://localhost/Ramadan-Booking-and-Management-System/public
```

---

## 🧪 Testing

* Booking flow testing
* Payment verification workflow
* Role-based login validation

---

## 🚧 Future Improvements

* QR code check-in system
* Email notification integration
* Payment gateway integration (FPX / Stripe)

---

## 👨‍💻 Author

Afiq Aiman
