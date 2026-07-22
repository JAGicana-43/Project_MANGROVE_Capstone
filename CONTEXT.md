# Project MANGROVE — Developer Context File
> Use this file as context when prompting AI assistants (Codex, Claude, etc.)
> Always include relevant sections when generating code for this project.

---

## 1. Project Overview

**Project MANGROVE** is a multi-platform mangrove monitoring and rehabilitation system built for DENR (Department of Environment and Natural Resources) field operations in the Philippines.

**Three-component architecture:**
- **Web System** — Laravel 11 + PostgreSQL + Blade (researcher/admin interface)
- **Mobile App** — Flutter (field technician interface)
- **Hardware** — ESP32 microcontroller with soil sensors (pH, salinity, moisture, NPK)

**AI Pipeline** — Python (Flask + PyTorch ResNet18) for orthomosaic image classification, runs as a separate internal service called by Laravel via HTTP.

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 (PHP 8.2+) |
| Database | PostgreSQL |
| Frontend | Blade templating + vanilla JS |
| Maps | Leaflet.js + georaster |
| AI Service | Python Flask + PyTorch ResNet18 |
| Mobile | Flutter (Dart) |
| Hardware | ESP32 + soil sensors |
| Local Dev | XAMPP (PHP) + PostgreSQL (manual install) |
| Hosting | DigitalOcean VPS |
| Tunneling | Ngrok (for mobile-to-local dev connection) |

---

## 3. Database Name

```
projectmangrove_db
```

---

## 4. Database Tables & Columns

> All primary keys are UUID. All foreign keys are UUID. Laravel models use `$incrementing = false` and `$keyType = 'string'`.

---

### 4.1 roles
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | gen_random_uuid() |
| name | string | admin, researcher, field_technician |
| permissions | json | array of permission strings |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.2 users
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| role_id | uuid FK | → roles.id |
| name | string | |
| email | string unique | |
| password | string | bcrypt hashed |
| is_active | boolean | default true |
| last_login_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.3 login_attempts
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| user_id | uuid FK nullable | → users.id |
| email | string | tracked even if user doesn't exist |
| ip_address | string(45) nullable | |
| success | boolean | |
| attempted_at | timestamp | |

---

### 4.4 sessions
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| user_id | uuid FK | → users.id |
| token_hash | string | hashed token |
| ip_address | string(45) nullable | |
| user_agent | string nullable | |
| expires_at | timestamp | |
| created_at | timestamp | |

---

### 4.5 projects
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| created_by | uuid FK | → users.id |
| name | string | |
| description | text nullable | |
| status | string | active, completed, archived |
| started_at | timestamp nullable | |
| ended_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.6 project_members
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| project_id | uuid FK | → projects.id |
| user_id | uuid FK | → users.id |
| role | string | lead, member, observer |
| assigned_at | timestamp | |

Unique constraint: (project_id, user_id)

---

### 4.7 areas_of_interest
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| project_id | uuid FK | → projects.id |
| created_by | uuid FK | → users.id |
| name | string | |
| boundary_coordinates | json | array of [lat, lng] pairs |
| center_latitude | float nullable | |
| center_longitude | float nullable | |
| area_sqm | float nullable | computed area in sq meters |
| status | string | active, archived |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.8 drone_missions
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| aoi_id | uuid FK | → areas_of_interest.id |
| created_by | uuid FK | → users.id |
| altitude_m | float nullable | flight altitude in meters |
| overlap_pct | float nullable | image overlap percentage |
| speed_mps | float nullable | drone speed m/s |
| kmz_file_path | string nullable | path to generated KMZ file |
| status | string | planned, completed, cancelled |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.9 orthomosaics
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| aoi_id | uuid FK | → areas_of_interest.id |
| mission_id | uuid FK nullable | → drone_missions.id |
| uploaded_by | uuid FK | → users.id |
| file_path | string | path to orthomosaic file |
| file_format | string | tif, png, jpg |
| resolution_cm | float nullable | ground resolution cm/pixel |
| bounds_coordinates | json nullable | [min_lat, min_lng, max_lat, max_lng] |
| coordinate_system | string | default WGS84 |
| status | string | pending, processing, ready, failed |
| captured_at | timestamp nullable | date of drone capture |
| uploaded_at | timestamp | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.10 classification_runs
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| orthomosaic_id | uuid FK | → orthomosaics.id |
| model_version | string | e.g. resnet18 |
| confidence_threshold | float | default 0.7 |
| total_tiles | int | |
| processed_tiles | int | |
| status | string | queued, running, completed, failed |
| error_message | text nullable | |
| started_at | timestamp nullable | |
| completed_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.11 classified_tiles
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| run_id | uuid FK | → classification_runs.id |
| tile_x | int | column index |
| tile_y | int | row index |
| land_cover_type | string | mangrove, water, bare_soil, built_up |
| confidence | float | 0.0 - 1.0 |
| center_latitude | float nullable | |
| center_longitude | float nullable | |
| tile_bounds | json nullable | [min_lat, min_lng, max_lat, max_lng] |
| created_at | timestamp | |

---

### 4.12 sampling_grids
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| run_id | uuid FK | → classification_runs.id |
| grid_x | int | column index |
| grid_y | int | row index |
| dominant_cover | string nullable | most common land cover in cell |
| suitability_score | float nullable | 0.0 - 1.0 |
| cell_bounds | json nullable | [min_lat, min_lng, max_lat, max_lng] |
| center_latitude | float nullable | |
| center_longitude | float nullable | |
| computed_at | timestamp | |

---

### 4.13 sampling_points
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| grid_id | uuid FK | → sampling_grids.id |
| assigned_to | uuid FK nullable | → users.id (field technician) |
| latitude | float | |
| longitude | float | |
| priority_rank | int nullable | 1 = highest priority |
| status | string | pending, transmitted, collected, skipped |
| transmitted_at | timestamp nullable | when sent to mobile app |
| collected_at | timestamp nullable | when field data was collected |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.14 soil_readings
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| point_id | uuid FK | → sampling_points.id |
| collected_by | uuid FK | → users.id |
| ph_avg | float nullable | averaged from 20 ESP32 readings |
| salinity_avg | float nullable | in mS/cm |
| moisture_avg | float nullable | in percentage |
| nitrogen_avg | float nullable | in ppm |
| phosphorus_avg | float nullable | in ppm |
| potassium_avg | float nullable | in ppm |
| reading_count | int | default 20 |
| suitability_rating | string nullable | suitable, marginal, unsuitable |
| remarks | text nullable | |
| is_synced | boolean | default false |
| synced_at | timestamp nullable | |
| collected_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.15 suitability_surfaces
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| aoi_id | uuid FK | → areas_of_interest.id |
| run_id | uuid FK | → classification_runs.id |
| interpolation_method | string | idw, kriging |
| output_file_path | string | path to generated file |
| file_format | string | geojson, tif |
| legend | json nullable | color scale metadata |
| generated_at | timestamp | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.16 survey_comparisons
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| aoi_id | uuid FK | → areas_of_interest.id |
| orthomosaic_a_id | uuid FK | → orthomosaics.id (earlier survey) |
| orthomosaic_b_id | uuid FK | → orthomosaics.id (later survey) |
| generated_by | uuid FK | → users.id |
| change_summary | json nullable | land cover change breakdown |
| report_file_path | string nullable | |
| notes | text nullable | |
| compared_at | timestamp | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.17 reports
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| project_id | uuid FK | → projects.id |
| generated_by | uuid FK | → users.id |
| title | string | |
| report_type | string | classification, suitability, comparison, soil, full_survey |
| file_path | string | |
| file_format | string | pdf, csv, xlsx |
| download_count | int | default 0 |
| generated_at | timestamp | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.18 audit_logs
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| user_id | uuid FK nullable | → users.id |
| action | string | created, updated, deleted, login, logout, failed_login, exported |
| table_name | string nullable | which table was affected |
| record_id | uuid nullable | which record was affected |
| payload | json nullable | before/after values |
| ip_address | string(45) nullable | |
| user_agent | string nullable | |
| logged_at | timestamp | |

---

## 5. Laravel Models

| Model | Table | Key Relationships |
|---|---|---|
| Role | roles | hasMany Users |
| User | users | belongsTo Role, hasMany Projects, hasMany ProjectMembers, hasMany SoilReadings, hasMany AuditLogs |
| LoginAttempt | login_attempts | belongsTo User |
| UserSession | sessions | belongsTo User |
| Project | projects | belongsTo User (creator), hasMany ProjectMembers, hasMany AreasOfInterest, hasMany Reports |
| ProjectMember | project_members | belongsTo Project, belongsTo User |
| AreaOfInterest | areas_of_interest | belongsTo Project, hasMany DroneMissions, hasMany Orthomosaics, hasMany SuitabilitySurfaces |
| DroneMission | drone_missions | belongsTo AreaOfInterest, hasMany Orthomosaics |
| Orthomosaic | orthomosaics | belongsTo AreaOfInterest, belongsTo DroneMission, hasMany ClassificationRuns |
| ClassificationRun | classification_runs | belongsTo Orthomosaic, hasMany ClassifiedTiles, hasMany SamplingGrids, hasMany SuitabilitySurfaces |
| ClassifiedTile | classified_tiles | belongsTo ClassificationRun |
| SamplingGrid | sampling_grids | belongsTo ClassificationRun, hasMany SamplingPoints |
| SamplingPoint | sampling_points | belongsTo SamplingGrid, belongsTo User (assigned_to), hasOne SoilReading |
| SoilReading | soil_readings | belongsTo SamplingPoint, belongsTo User (collected_by) |
| SuitabilitySurface | suitability_surfaces | belongsTo AreaOfInterest, belongsTo ClassificationRun |
| SurveyComparison | survey_comparisons | belongsTo AreaOfInterest, belongsTo Orthomosaic (A and B) |
| Report | reports | belongsTo Project, belongsTo User (generated_by) |
| AuditLog | audit_logs | belongsTo User |

---

## 6. Roles & Permissions

### admin
Full access to everything:
- manage_users, manage_roles, manage_projects, manage_aoi
- upload_orthomosaic, run_classification, view_classification
- manage_sampling, view_soil_readings, generate_reports
- view_reports, view_audit_logs, manage_drone_missions, compare_surveys

### researcher
- manage_projects, manage_aoi, upload_orthomosaic
- run_classification, view_classification, manage_sampling
- view_soil_readings, generate_reports, view_reports
- manage_drone_missions, compare_surveys
- create_field_technician_accounts (within their projects)

### field_technician
- view_sampling (only their assigned points)
- collect_soil_readings
- view_reports (limited)

---

## 7. Business Rules

1. **Account creation is web-only.** No account registration on the mobile app.
2. **Admin** can create all account types.
3. **Researcher** can only create field_technician accounts.
4. **Field technician** cannot create any accounts.
5. **Field tech must be assigned to a project** before they can see any data on mobile.
6. **Field tech must be assigned specific sampling points** before they can collect data.
7. **Soil readings are averaged** from 20 ESP32 sensor readings per sampling point.
8. **Login lockout** after 3 failed attempts — locked for 15 minutes.
9. **Every significant action** is recorded in audit_logs.
10. **Soil readings sync** from mobile to Laravel API — stored with is_synced = false until confirmed received.
11. **Researcher reviews synced soil data** — can verify or reject each reading.
12. **Data flows through projects**, not person-to-person. Field tech data belongs to the project, not the researcher directly.
13. **Each user has one role** — roles do not stack.

---

## 8. Key Workflows

### Workflow 1 — Full Survey Flow
```
Researcher creates Project
→ Adds AOI (draws boundary on Leaflet map)
→ Creates Drone Mission (sets altitude, overlap, speed)
→ Uploads Orthomosaic (after drone flight)
→ Triggers AI Classification (Flask processes the image)
→ System generates Sampling Grids + Sampling Points
→ Researcher assigns Sampling Points to Field Technicians
→ Researcher transmits points to mobile
→ Field Tech collects soil readings (ESP32 via mobile)
→ Mobile syncs data to Laravel API
→ Researcher verifies soil readings
→ System generates Suitability Surface (IDW interpolation)
→ Researcher generates Report
```

### Workflow 2 — Account Creation Flow
```
Admin/Researcher logs into web
→ Creates field technician account (email + password)
→ Assigns field tech to a project
→ Field tech logs into mobile app with their credentials
→ Sees only their assigned sampling points
```

### Workflow 3 — Survey Comparison Flow
```
Researcher selects two Orthomosaics from same AOI (different dates)
→ System compares classification results
→ Generates change summary (land cover change per class)
→ Displays before/after map overlay
→ Researcher generates comparison report
```

---

## 9. Auth & Routing

- **Login page:** `/login`
- **Admin dashboard:** `/admin/dashboard`
- **Researcher dashboard:** `/researcher/dashboard`
- **Field tech dashboard:** `/fieldtech/dashboard`
- **Middleware:** `auth` (requires login) + `role:admin/researcher/field_technician`
- **After login:** redirected by role automatically
- **Logout:** POST to `/logout`

---

## 10. File Storage

All uploaded files (orthomosaics, KMZ files, reports, suitability surfaces) are stored in Laravel's `storage/app/public/` directory and served via symlink.

Key storage paths:
- Orthomosaics: `storage/app/public/orthomosaics/`
- KMZ files: `storage/app/public/missions/`
- Reports: `storage/app/public/reports/`
- Suitability surfaces: `storage/app/public/surfaces/`

---

## 11. API Endpoints (for Flutter mobile app)

All mobile API routes are prefixed with `/api/` and require Bearer token authentication.

| Method | Endpoint | Description |
|---|---|---|
| POST | /api/auth/login | Field tech login |
| POST | /api/auth/logout | Field tech logout |
| GET | /api/sampling-points | Get assigned points for logged-in field tech |
| POST | /api/soil-readings | Submit soil reading from ESP32 |
| GET | /api/projects | Get projects assigned to field tech |

---

## 12. Standard Prompt Template for Codex

When prompting Codex for any feature, use this format:

```
I am building Project MANGROVE — a Laravel 11 + PostgreSQL + Blade web system.
Refer to CONTEXT.md for full database structure, models, roles, and business rules.

Key rules:
- All primary keys are UUID
- Use Auth::user() to get the logged-in user
- Always check role before allowing actions
- Log important actions using AuditLog::record()
- Use $table->uuid references in queries

Task: [describe what you want to generate]
```
