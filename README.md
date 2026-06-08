# Ivor Paine Memorial Hospital - Premium Healthcare Management System

## Overview

A premium, Awwwards-inspired hospital management dashboard built for the Ivor Paine Memorial Hospital. Transforms the original basic PHP system into a modern, data-rich command center with beautiful visuals, smooth interactions, Medster API medicine integration, advanced reporting, audit trails, and complete end-to-end features.

## System Requirements

- PHP 7.4+ with sqlsrv and curl extensions enabled
- Microsoft SQL Server (Express or higher)
- Existing database: `HospitalDatabse` with Phase 1-6 schema
- Web server: IIS, Apache, or built-in PHP server
- Internet access (for Medster API medicine search)

## File Structure

```
ivor-paine-hospital/
|── index.php                     # Dashboard Command Center
|── patients.php                  # Patient Directory with profile modals
|── doctors.php                   # Doctor Profiles with performance
|── nurses.php                    # Nursing Staff with shift management
|── appointments.php              # Advanced Appointment Scheduling
|── wards.php                     # Ward & Bed Management
|── complaints.php                # Complaints Management
|── treatments.php                # Treatments Management
|── medicines.php                 # Medster API Medicine Search (NEW)
|── prescriptions.php             # Prescription Workflow with Medicine Search (NEW)
|── reports.php                   # Advanced Reports & Analytics Dashboard (NEW)
|── audit_log.php                 # Complete Audit Trail System (NEW)
|── style.css                     # Complete Premium Design System
|── components/
|   |── header.php                # HTML head, theme init, toast container
|   |── sidebar.php               # Premium sidebar navigation
|   |── topbar.php                # Top bar with search, clock, theme toggle
|   |── footer.php                # Mobile nav, all shared JavaScript
|── includes/
|   |── db.php                    # Secure SQL Server connection
|   |── helpers.php               # Safe output, badges, pagination, etc.
|   |── medicine_api.php          # Medster API Service (NEW)
|── api/
|   |── search.php                # Global search endpoint
|   |── medicine_api_proxy.php    # Medicine API proxy (NEW)
|── sql/
|   |── optional_tables.sql       # ACTIVITY_LOG, audit tables
|   |── medicine_tables.sql       # Medicine, prescription, cache tables (NEW)
|── README.md                     # This file
```

## Installation

### 1. Backup Your Database
Always backup `HospitalDatabse` before making changes.

### 2. Deploy Files
Copy all files to your web server's document root. Ensure PHP has write permissions if needed.

### 3. Verify Database Connection
Open `includes/db.php` and verify the SQL Server connection settings:

```php
$serverName = "HP\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "HospitalDatabse",
    "Uid" => "",
    "PWD" => "",
    "TrustServerCertificate" => true
];
```

Update credentials if your SQL Server requires authentication.

### 4. Create New Tables
Run `sql/medicine_tables.sql` in SQL Server Management Studio to create all required tables:

- **PRESCRIPTION_ITEM** - Stores multiple medicines per prescription
- **MEDICINE_API_CACHE** - Caches medicine search results and details
- **API_LOG** - Tracks all external API calls
- **AUDIT_LOG** - Complete audit trail for system actions

This script is idempotent (safe to run multiple times) and includes optional triggers.

### 5. Run Optional Tables (if not already done)
Run `sql/optional_tables.sql` for the `ACTIVITY_LOG` table if you haven't already.

### 6. Test Access
Open `index.php` in your browser. You should see the Hospital Command Center dashboard.

## Medster API Integration

The system integrates with the Medster API (https://medster.vercel.app) for medicine search and details:

| Endpoint | Purpose |
|----------|---------|
| `GET /api/search?q={name}` | Search medicines by name |
| `GET /api/details?id={id}` | Get full medicine details |

### API Features
- **Automatic caching** - Results cached for 60 minutes to reduce API calls
- **Fallback to cache** - If API is down, cached results are served
- **Health monitoring** - API status tracked with response time logging
- **Error handling** - Graceful degradation when API is unavailable
- **Price parsing** - Extracts numeric values from price strings like "Rs. 36.0"

## New Features (Tasks 31-50)

### Task 31: Medster API Service (`includes/medicine_api.php`)
- Reusable PHP service for medicine search and details
- `searchMedicines($query)` - validates query, encodes URL, calls API, decodes JSON, returns results
- `getMedicineDetails($medicineId)` - fetches full pharmaceutical details
- `callMedsterAPI($url)` - cURL-based HTTP client with SSL verification options
- `logApiCall()` - logs all API calls to API_LOG table for monitoring

### Task 32: Medicine Details API
- `getMedicineDetails()` fetches full details: name, price, discount, uses, warnings, dosage, side effects
- Details displayed dynamically from any section returned by the API
- Missing fields handled gracefully without breaking the UI

### Task 33: Medicine Search Page (`medicines.php`)
- Premium pharmacy search interface with debounced search input
- Skeleton loading animation while searching
- Beautiful empty state with quick search suggestions
- Error state with retry button
- Modern medicine cards with hover effects
- Search results show: medicine name, price, medicine ID
- Quick actions: View Details and Prescribe buttons
- Cached results visually indicated

### Task 34: Medicine Details Modal
- Glassmorphism-style modal with medicine icon
- Price badge and discount badge display
- Medical information warning banner
- Accordion sections for Uses, Warnings, Dosage, Side Effects
- "Add to Prescription" button linking to prescription workflow
- Loading state while fetching details
- Mobile-friendly responsive design

### Task 35: Prescription with Medicine Search (`prescriptions.php`)
- Inline medicine search with debounced API calls
- Dropdown results from Medster API
- One-click medicine selection with auto-fill of name, ID, and price
- Manual dosage, frequency, duration, quantity, instructions fields
- Support for multiple medicines in one prescription
- Fallback text fields when no API medicines are selected
- Tabbed interface: All Prescriptions, Create New, View Details

### Task 36: Prescription Items Table
- Normalized PRESCRIPTION_ITEM table stores multiple medicines per prescription
- Fields: MedicineApiID, MedicineName, Price, Dosage, Frequency, Duration, Quantity, Instructions
- Backward compatible with single-medication PRESCRIPTION records
- `savePrescriptionItems()` and `getPrescriptionItems()` functions

### Task 37: Printable Prescription
- Professional A4 print layout with hospital header
- Patient and doctor details, appointment info
- Complete medicine table with dosage, frequency, quantity, instructions
- Estimated cost summary
- Signature line and doctor stamp area
- Print button that hides sidebar and navigation
- Hidden in normal view, only shows when printing

### Task 38: Medicine Price Awareness
- Live cost estimation as medicines are added
- Quantity-based subtotal calculation
- Total estimated prescription cost with formatted currency
- Note: "Estimated from external medicine API"
- Missing prices don't block prescription saving

### Task 39: Medicine API Cache
- MEDICINE_API_CACHE table for search results and details
- 60-minute cache duration with fallback on API failure
- Individual medicine caching for faster lookups
- Cache improves prescription lookup performance

### Task 40: API Health Status
- API status card on Reports dashboard
- Shows: online/offline status, average response time, searches today, details today
- Last successful/failed call tracking
- Dashboard doesn't fail if API is down
- Premium UI matching the design system

### Task 41: Medicine Reports (`reports.php`)
- Most prescribed medicines
- Medicines prescribed by doctor
- Medicines prescribed by patient
- Estimated medicine cost by patient
- API vs manually entered medicine usage
- Medicines with missing dosage or instructions
- All reports filterable by date range
- CSV export support

### Task 42: Report Builder Dashboard
- Interactive report category sidebar
- 8 categories: Executive, Medicines, Patients, Doctors, Wards, Appointments, Complaints, Finance
- Date range, doctor, patient, status filters
- Generate, Reset, Export CSV, Print actions
- Visual chart cards for applicable reports
- Executive-level portfolio-ready design

### Task 43: Executive Summary Reports
- 6 KPI cards with animated counters: Total Patients, Bed Occupancy, Appointments (30d), Avg Appts/Doctor, Treatment Cost, Top Medicine
- Real SQL data, not hardcoded
- Bed occupancy percentage with conditional color
- New patients this month indicator
- Most common complaint severity

### Task 44: Finance and Cost Reports
- Treatment cost by patient, doctor, ward
- Monthly treatment cost trend
- High-cost patients identification
- Currency formatted consistently
- Charts and tables with visual KPIs

### Task 45: Patient Full Medical Report
- Complete patient medical history combining multiple tables
- Profile, admission, bed/ward, appointments, complaints, treatments, prescriptions
- Total estimated cost calculation
- Chronological ordering where possible
- Printable report button

### Task 46: Doctor Workload Report
- Doctor name, specialty, position
- Total, completed, cancelled appointments
- Unique patients count
- Prescriptions issued, treatments assigned
- Average performance rating
- Filterable by date range and specialty
- CSV export

### Task 47: Ward Utilization Report
- Ward name, location, total beds
- Occupied, available, maintenance beds
- Occupancy percentage with visual progress bars
- Conditional coloring (>85% highlighted in warning)
- Current patients count
- Printable report

### Task 48: Appointment Performance Report
- Total, completed, cancelled, scheduled, no-show counts
- Completion rate percentage
- Appointments by doctor, patient, purpose, month
- Chart visualizations
- Filterable by doctor, patient, status, date

### Task 49: Complaint & Treatment Outcome Reports
- Complaints by severity with resolved/unresolved counts
- Treatments by status
- Complaints linked to treatments
- Average treatment duration
- Critical unresolved complaints highlighted
- Date filtering and CSV export

### Task 50: Audit Trail (`audit_log.php`)
- AUDIT_LOG table tracks all important actions
- Action types: create, update, delete, search, view
- Entity types: patient, prescription, medicine, appointment, treatment, complaint, bed
- Filterable by action type, entity type, date range, user, search
- Visual action icons with color-coded badges
- CSV export support
- Automatic triggers on PRESCRIPTION and PATIENT tables
- `logAudit()` function available for all pages

## Pages & Features

### Dashboard (index.php)
- 6 animated KPI stat cards
- Weekly appointment trend chart (Chart.js)
- Complaint severity distribution (doughnut chart)
- Bed availability by ward (stacked bar chart)
- Recent patients table
- Today's & upcoming appointments
- Ward occupancy progress bars
- Recent activity timeline
- System health check cards
- Live clock with greeting

### Patients (patients.php)
- Patient directory with search & sort
- Register patient modal with live age preview
- Edit patient modal with bed reassignment
- Discharge patient with confirmation dialog
- Patient profile detail modal with tabs
- CSV export

### Doctors (doctors.php)
- Profile cards with appointment count & rating
- Specialty & position filters
- Detail modal with experience, performance, team tabs

### Nurses (nurses.php)
- Nursing staff directory
- Ward & type filters
- Nurse profile modal with shift schedule

### Appointments (appointments.php)
- Table & calendar view toggle
- Status & doctor filters
- Conflict detection on booking
- Detail modal with medical action panels
- Print support

### Medicines (medicines.php) [NEW]
- Premium pharmacy search interface
- Debounced Medster API search
- Skeleton loading and empty states
- Modern medicine cards with hover effects
- Quick search suggestions (Panadol, Brufen, etc.)
- View details and prescribe buttons
- API health status indicator

### Prescriptions (prescriptions.php) [NEW]
- Complete prescription management
- Inline medicine search with API integration
- Multiple medicines per prescription
- Cost estimation with live calculation
- Professional printable prescription layout
- Tabbed interface: List, Create, View
- CSV export

### Reports (reports.php) [NEW]
- Executive KPI dashboard with animated counters
- 8 report categories with 30+ reports
- Interactive filter panel (date, doctor, patient, status)
- Visual charts for applicable reports
- CSV export and print support
- Medster API health status card

### Audit Log (audit_log.php) [NEW]
- Complete audit trail for all system actions
- Color-coded action type badges
- Filter by action type, entity type, date range
- Search across all fields
- CSV export support
- Automatic logging via database triggers

### Wards (wards.php)
- Ward cards with occupancy visualization
- Interactive bed map grid
- Bed assignment form

### Complaints (complaints.php)
- Severity badge display
- One-click resolve
- Add new complaint modal

### Treatments (treatments.php)
- Treatment cards with cost display
- Type & status filters
- Inline note adding

## Global Features

- **Dark Mode**: Toggle in topbar, persists via localStorage
- **Global Search**: Instant grouped results across all entities
- **Toast Notifications**: Success, error, warning, info
- **Confirmation Dialogs**: Safe destructive actions
- **Responsive Design**: Desktop sidebar + mobile bottom nav
- **Print Styles**: Clean print layouts for prescriptions and reports
- **CSV Export**: Available on patients, prescriptions, reports, and audit log
- **Animated Counters**: KPI numbers animate on scroll
- **Loading States**: Button spinner, skeleton screens
- **Tab System**: Used across detail modals and prescriptions
- **Live Clock**: Real-time date/time display
- **Medster API Integration**: Medicine search, details, caching, health monitoring
- **Audit Trail**: Complete action logging for accountability
- **Report Builder**: Interactive category-based report generation

## Design System

The `style.css` file provides:
- CSS custom properties (light/dark mode)
- 11 badge color variants
- 6 stat card accent colors
- Card, table, form, modal, tab, toast components
- Progress bars, star ratings, timeline, breadcrumbs
- Health cards, bed grid, medicine cards
- Medicine search and accordion styles
- Prescription item forms and cost summary
- KPI cards for executive summary
- Occupancy bars with conditional coloring
- Audit log entry styles
- Print styles for prescriptions and reports
- Responsive breakpoints (1200px, 1024px, 768px)
- Smooth animations & transitions
- Dark mode overrides for all new components

## Security Features

- Parameterized queries (`sqlsrv_query` with params)
- `htmlspecialchars` output escaping via `e()` function
- No raw SQL error exposure to users
- Transaction support for multi-step operations
- Input validation server-side
- API proxy prevents direct external API exposure
- Audit trail for accountability

## API Configuration

If you need to change the Medster API base URL, edit `includes/medicine_api.php`:

```php
const MEDSTER_BASE_URL = 'https://medster.vercel.app';
const API_TIMEOUT = 10; // seconds
const CACHE_DURATION_MINUTES = 60; // cache expiry
```

## Troubleshooting

**Database connection fails:**
- Verify SQL Server is running
- Check `TrustServerCertificate` is set to `true`
- Confirm `sqlsrv` PHP extension is enabled in `php.ini`

**Medicine search not working:**
- Verify internet access (Medster API requires external connectivity)
- Check that `curl` PHP extension is enabled
- Review `API_LOG` table for error details
- Check API health card on Reports page

**Charts not loading:**
- Ensure internet access (Chart.js loaded from CDN)
- Check browser console for JavaScript errors

**Dark mode not persisting:**
- Verify `localStorage` is not blocked
- Check for JavaScript console errors

**Audit log not populating:**
- Verify `AUDIT_LOG` table was created by running `sql/medicine_tables.sql`
- Check that database triggers are enabled
- Some actions require the page to call `logAudit()` explicitly

## Testing Checklist

- [ ] Search medicine by name (try "panadol", "brufen")
- [ ] View medicine details modal with accordion sections
- [ ] Add medicine to prescription from search
- [ ] Save prescription with multiple medicines
- [ ] Print prescription (opens in new tab with A4 layout)
- [ ] Verify cost estimation updates live
- [ ] Generate medicine reports with date filters
- [ ] Generate patient full medical report
- [ ] Generate doctor workload report
- [ ] Generate ward utilization report
- [ ] Export any report to CSV
- [ ] Test API failure state (disconnect internet)
- [ ] Test empty API results (search nonsense term)
- [ ] Test mobile responsiveness
- [ ] Test dark mode on new pages
- [ ] Verify audit trail captures prescription creation
- [ ] Check API health status card
- [ ] Test cache fallback when API is down

## License

Internal use for Ivor Paine Memorial Hospital project.
