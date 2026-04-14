# 🌙 Ramadan Buffet Booking and Management System

A comprehensive Ramadan Iftar buffet booking management system with multi-role administration, payment verification, and real-time capacity management.

## ✨ Features

### 🎯 Core Functionality
- **Online Booking System** with real-time slot availability
- **Multi-Role User Management** (Admin, Banquet, Entry Duty, Staff, Finance)
- **Payment Verification** with proof upload and admin approval workflow
- **Dynamic Pricing** with special promotional rates
- **Check-in Management** with ticket validation and reprint capabilities

### 🎨 User Experience
- **Ramadan-Themed UI** with green/gold color palette and Islamic motifs
- **Responsive Design** for desktop and mobile devices
- **Real-time Updates** with live booking statistics
- **Auto-save Functionality** to prevent data loss

### 📊 Administrative Features
- **Dashboard Analytics** with booking trends and revenue tracking
- **Global Settings Management** for event configuration
- **Backup System** for payment proofs and data
- **Notification System** for payment alerts and system events

## 🛠️ Technology Stack

- **Backend**: PHP 8.2+ with MySQLi
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Database**: MySQL/MariaDB
- **Authentication**: Session-based with role-based access control
- **File Handling**: Secure upload system for payment proofs

## 📁 Project Structure
├── admin/
│ ├── admin_dashboard.php
│ ├── check_in.php
│ ├── settings.php
│ ├── reports.php
│ ├── all_bookings.php
│ ├── finance_confirm.php
│ └── booking_slots.php
│
├── public/
│ ├── home.php
│ ├── index.php
│ ├── upload_proof.php
│ ├── booking_reference.php
│ └── login.php
│
├── config/
│ └── config.php
│
├── assets/
│ ├── css/
│ │ └── main.css
│ ├── js/
│ │ └── main.js
│ └── img/
│
├── uploads/
├── backups/
└── database.sql


---

## 🚀 Quick Start

### 📌 Prerequisites
- PHP 8.2 or higher  
- MySQL / MariaDB  
- Apache / Nginx  
- Required PHP extensions:
  - mysqli  
  - gd  
  - session  
  - json  
  - mbstring  

---

### ⚙️ Installation

1. **Clone the repository**
```bash
git clone https://github.com/AfiqAiman1/Ramadan-Booking-and-Management-System.git
cd Ramadan-Booking-and-Management-System
