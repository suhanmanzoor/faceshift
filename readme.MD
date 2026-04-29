🧠 FaceShift — AI-Powered Attendance System

> **Hackathon Project | Theme: AI in Attendance**  
> Smart face recognition + real-time GPS geofencing for office attendance management.

> admin id: amdin
> admin password: admin123
---

## 📌 Project Name

**FaceShift**

> *"Clock in with your face. Stay tracked through your shift."*

The name combines **Face** (biometric recognition) + **Shift** (shift-based work management) — two core pillars of the system.

---
admin id: admin
password: admin123

## 🎯 Problem Statement

Traditional office attendance systems are:
- Prone to **buddy punching** (marking a friend's attendance)
- Unable to verify **physical presence** during working hours
- Fully **manual**, requiring HR effort daily
- Lacking **real-time awareness** of who is actually in the office

---

## 💡 Solution

**FaceShift** solves all of this with a two-layer verification system:

| Layer | Technology | What it does |
|---|---|---|
| **Layer 1** | Face Recognition (face-api.js) | Verifies identity at check-in |
| **Layer 2** | GPS Geofencing | Continuously tracks if employee is on premises |

Once an employee checks in via face scan, their phone reports GPS coordinates throughout the shift. If they leave the office boundary, it\'s logged with a timestamp. When they return, that too is recorded.

---

## ✨ Key Features

- 🤖 **AI Face Recognition** — Uses `face-api.js` (SSD MobileNet + 128D face descriptors) for real-time identification directly in the browser
- 📸 **3-Angle Registration** — Captures left, front, and right face angles for high-accuracy matching
- 📍 **Real-Time GPS Tracking** — Haversine formula geofencing with configurable radius (default 150m)
- 📡 **Dedicated GPS Tracker Page** — `tracker.php` runs on the employee\'s phone as a lightweight background-friendly GPS beacon
- 🔒 **Shift Lock** — Attendance kiosk is automatically locked outside shift hours
- 📊 **Admin Dashboard** — Live KPIs, attendance table, activity log, all auto-refreshing every 30s
- 👤 **Employee Management** — Register, view, and deactivate employees with face descriptors stored as JSON
- 🚗 **Leave Event Logging** — Every office departure and return is timestamped and tracked
- ✉️ **Automated Daily Email** — Each employee receives a personalized attendance report at day-end via PHPMailer
- 🌙 **Dark UI** — Clean, professional dark dashboard built with Bootstrap 5

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| **Frontend** | HTML5, CSS3, Bootstrap 5, JavaScript (ES6+) |
| **AI / ML** | [face-api.js](https://github.com/justadudewhohacks/face-api.js) (SSD MobileNet V1, Face Landmark 68, Face Recognition Net) |
| **Backend** | PHP 8+ |
| **Storage** | JSON flat files (no database required) |
| **Email** | PHPMailer (SMTP / Gmail App Password) |
| **Geolocation** | Browser Geolocation API + Haversine formula |
| **Dev Server** | Laragon (recommended) |

> ✅ Zero database setup required — everything is stored in JSON files.

---

## 📁 Project Structure

```
faceshift/
├── config.php            # All settings: office coords, shift times, SMTP, paths
├── index.php             # Admin login page
├── attendance.php        # Employee kiosk — face scan + GPS tracking
├── tracker.php           # 📡 Dedicated GPS tracker for employee phones
├── register.php          # Register employees (3-angle face capture)
├── admin.php             # Admin dashboard (live logs, KPIs, table)
├── api.php               # All AJAX endpoints (mark, GPS update, data fetch)
├── send_report.php       # Trigger end-of-day emails to all employees
├── logout.php            # Session destroy
│
├── data/                 # Auto-created — do NOT delete
│   ├── employees.json        # Employee profiles + face descriptors
│   ├── attendance.json       # Daily attendance records (by date > emp_id)
│   ├── activity_log.json     # All events: check-ins, leave events, registrations
│   ├── report_sent_log.json  # Log of sent daily reports
│   └── settings.json         # Dynamic settings storage
│
├── uploads/
│   └── faces/
│       └── EMP0001/      # 3 face angle photos per employee
│           ├── angle_0.jpg
│           ├── angle_1.jpg
│           └── angle_2.jpg
│
└── vendor/               # PHPMailer (via Composer)
    └── autoload.php
```

---

## 📡 tracker.php — Dedicated GPS Tracker

**Live URL:** [https://superdevs.co.in/tracker.php](https://superdevs.co.in/tracker.php)

After a face scan check-in, employees open `tracker.php` on their **personal phone**. This page acts as a lightweight, battery-friendly GPS beacon that:

- Runs in the browser — no app install required
- Continuously watches the device\'s GPS position via `watchPosition()`
- Sends coordinates to `api.php?action=update_location` on every significant movement
- Shows the employee their live distance from the office and whether they are inside or outside the geofence
- Displays real-time status: **🏢 In Office** or **⚠️ Outside Premises**
- Keeps running in the background as long as the browser tab is open

> **How employees use it:**
> 1. Check in at the kiosk via face scan (`attendance.php`)
> 2. Open `tracker.php` on their phone — [https://superdevs.co.in/tracker.php](https://superdevs.co.in/tracker.php)
> 3. Enter their Employee ID when prompted
> 4. Keep the tab open throughout the shift — that\'s it ✅

---

## ⚙️ Configuration some in confif file and some are being set using settings.php

```php
define(\'OFFICE_NAME\',     \'TechCorp HQ\');
define(\'OFFICE_LAT\',      28.6139);     // ← Your office latitude
define(\'OFFICE_LNG\',      77.2090);     // ← Your office longitude
define(\'OFFICE_RADIUS_M\', 150);         // Geofence radius in metres
define(\'SHIFT_START\',     \'09:00\');
define(\'SHIFT_END\',       \'18:00\');

define(\'SMTP_HOST\', \'smtp.gmail.com\');
define(\'SMTP_PORT\', 587);
define(\'SMTP_USER\', \'your@gmail.com\');
define(\'SMTP_PASS\', \'your-app-password\');  // Gmail App Password
```

---

## 🚀 Setup Guide

### 1. Clone / Download

```bash
git clone https://github.com/suhanmanzoor/faceshift.git
cd faceshift
```

### 2. Install PHPMailer

```bash
composer require phpmailer/phpmailer
```

### 3. Set Admin Password

```bash
php -r "echo password_hash(\'your_password_here\', PASSWORD_DEFAULT);"
```

Paste the output hash into `config.php` → `ADMIN_PASS_HASH`.

### 4. Create Required Folders

```bash
mkdir -p data uploads/faces
chmod 775 data uploads
```

### 5. Configure Settings

Edit `config.php` — set your office GPS coordinates, shift times, and SMTP credentials.

### 6. Serve with Laragon (Recommended)

- Place folder in `C:/laragon/www/faceshift/`
- Start Laragon → visit `http://faceshift.test/`

Or with PHP built-in server:

```bash
php -S localhost:8000
```

### 7. First Steps

1. Visit `https://superdevs.co.in/faceshift/` → login as admin
2. Go to **Employees** → click **Add Employee**
3. Register employees with 3-angle face capture
4. Open `attendance.php` on a kiosk device in the office
5. Employees open `tracker.php` — [https://superdevs.co.in/tracker.php](https://superdevs.co.in/tracker.php) — on their phones
6. Monitor everything live from `admin.php`

---

## 🔄 How It Works — Flow

```
Employee arrives at office
        │
        ▼
┌─────────────────────┐
│  attendance.php     │  ← Open on kiosk device
│  Face Scan          │
│  (face-api.js)      │
└────────┬────────────┘
         │ Face matched (>50% confidence)
         ▼
┌─────────────────────┐
│  api.php            │
│  mark_attendance    │  ← Check-in recorded with time
└────────┬────────────┘
         │
         ▼
┌──────────────────────────┐
│  tracker.php             │  ← Employee opens on their phone
│  superdevs.co.in/tracker │  ← Lightweight GPS beacon page
└────────┬─────────────────┘
         │ On movement + every 30s
         ▼
┌─────────────────────┐
│  api.php            │
│  update_location    │  ← Haversine distance to office checked
└────────┬────────────┘
         │
    ┌────┴────┐
    │         │
  In Range  Out of Range
    │         │
    │         ▼
    │   Leave event logged
    │   (left_at timestamp)
    │         │
    │   Employee returns
    │         │
    │   returned_at logged
    │
    ▼
End of Day → Admin triggers send_report.php
Each employee gets personalized email summary
```

---

## 📊 Data Structure

### `employees.json`

```json
{
  "EMP0001": {
    "id": "EMP0001",
    "name": "Suhan Manzoor",
    "email": "suhan@company.com",
    "department": "Engineering",
    "registered_at": "2026-04-29 09:00:00",
    "descriptors": [[0.12, -0.34, "..."], ["..."], ["..."]],
    "active": true
  }
}
```

### `attendance.json`

```json
{
  "2026-04-29": {
    "EMP0001": {
      "emp_id": "EMP0001",
      "name": "Suhan Manzoor",
      "check_in": "09:07:22",
      "status": "present",
      "confidence": 87.4,
      "in_office": true,
      "last_seen": "2026-04-29 11:45:00",
      "leave_events": [
        {
          "left_at": "10:15:00",
          "returned_at": "10:42:00"
        }
      ],
      "location_logs": [
        {
          "time": "2026-04-29 09:07:22",
          "lat": 28.6141,
          "lng": 77.2092,
          "dist_m": 28,
          "in_office": true
        }
      ]
    }
  }
}
```

---

## 📧 Email Report (Sample)

```
Subject: [TechCorp HQ] Your Attendance Summary — 29 Apr 2026

Hello Suhan Manzoor,

✅ Status: Present
⏰ Check-in: 09:07:22
🕐 Approx. Work Time: 513 minutes
🤖 Face Recognition Confidence: 87.4%

Leave Events:
  • Left at 10:15:00 → Returned at 10:42:00

Shift Hours: 09:00 – 18:00

— TechCorp HQ HR Team
(This is an automated message)
```

---

## 🔐 Security Considerations

| Feature | Implementation |
|---|---|
| Admin auth | PHP sessions + `password_hash` / `password_verify` |
| Face data | Stored as float arrays (128D descriptors), not raw photos |
| Shift lock | Server-side time check + frontend UI lock |
| Employee deactivation | Soft delete (`active: false`) — data preserved |
| No exposed paths | All data in `/data/` folder (add `.htaccess` to deny direct access) |

**Recommended `.htaccess` for `/data/` folder:**

```apache
Order deny,allow
Deny from all
```

---

## 🧪 Face Recognition Details

| Parameter | Value |
|---|---|
| Model | SSD MobileNet V1 |
| Descriptor | 128-dimensional face embedding |
| Match threshold | 0.5 (Euclidean distance) |
| Min confidence | 50% detection confidence |
| Angles captured | 3 (left, front, right) |
| Recognition loop | Every 600ms |

**Why 3 angles?** A single frontal descriptor may fail under different lighting or head tilt. Capturing left + front + right creates a `LabeledFaceDescriptors` set — face-api.js finds the best match across all three, dramatically improving accuracy.

---

## 🗺 GPS Geofencing Details

The Haversine formula calculates great-circle distance between two GPS coordinates:

```
a = sin²(Δlat/2) + cos(lat1) × cos(lat2) × sin²(Δlng/2)
d = 2R × atan2(√a, √(1−a))
```

- **Radius:** 150 metres (configurable in `config.php`)
- **Poll rate:** On movement + every 30s via `tracker.php` (`watchPosition`)
- **Accuracy:** `enableHighAccuracy: true` for GPS-level precision
- **Tracker URL:** [https://superdevs.co.in/tracker.php](https://superdevs.co.in/tracker.php)

---

## 🏆 Hackathon Highlights

| Judging Criteria | FaceShift\'s Answer |
|---|---|
| **AI Integration** | Real-time face recognition via neural net (face-api.js) directly in browser |
| **Innovation** | Dual-layer verification: biometric check-in + continuous GPS presence |
| **Completeness** | Full system: registration → attendance → monitoring → email reports |
| **Practicality** | Works today, no hardware required beyond a webcam-enabled device |
| **Tech Stack** | Pure PHP + JSON — zero DB setup, deployable anywhere |
| **UX** | Kiosk mode, dedicated tracker page, admin dashboard, auto email — fully automated |

---

## 🔭 Future Enhancements

- [ ] MySQL/MariaDB migration for large teams
- [ ] Mobile app (PWA) with background GPS service replacing `tracker.php`
- [ ] Monthly attendance reports & analytics charts
- [ ] Liveness detection (anti-spoofing — detect if photo is shown to camera)
- [ ] Slack/WhatsApp notification integration
- [ ] Multi-office / multi-branch support
- [ ] Overtime & late-arrival detection

---

## 👨‍💻 Built With

- [face-api.js](https://github.com/justadudewhohacks/face-api.js) — In-browser face recognition
- [Bootstrap 5](https://getbootstrap.com/) — UI framework
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) — Email delivery
- [PHP 8+](https://php.net) — Backend logic

---

## 🔗 Live Links

| Page | URL |
|---|---|
| **Main App** | [https://superdevs.co.in/faceshift/](https://superdevs.co.in/faceshift/) |
| **GPS Tracker** | [https://superdevs.co.in/tracker.php](https://superdevs.co.in/tracker.php) |

---

## 📄 License

MIT License — free to use, modify, and build upon.

---

<p align="center">Built for the <strong>AI in Attendance</strong> Hackathon 🏆</p>
'''


