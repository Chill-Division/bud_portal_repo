# Changelog

## [0.10.1] - 2026-01-03
### Fixed
- **Auto-Migration System**: Database schema now updates automatically on any page load. No manual migration required when upgrading to v0.10.
- Removed manual migration script in favor of automatic migration in `config.php`



## [0.10] - 2026-01-03
### Added
- **Bundle Management System**: New "Bundles" page allows creating product bundles (e.g., "Finished Box" containing multiple stock items). When bundles are shipped via Chain of Custody, all component items are automatically deducted from stock.
- **COC Stock Deduction**: Chain of Custody submissions now automatically reduce stock quantities for shipped items, with full audit trail logging.
- **Scheduling - Upcoming Tasks**: New section displays tasks that will be due within 24 hours.
- **Scheduling - Edit Capability**: Edit button for each schedule with modal form to modify name, frequency, and description.
- **Scheduling - Completion History**: History button shows last 7 completions for each schedule with staff names, dates, and notes.
- **Timesheet - 7-Day Reporting**: New comprehensive weekly summary with:
  - Daily breakdown showing total hours and staff count per day
  - Staff breakdown showing total hours per person with daily details
- **Navigation**: Added "Bundles" link to main navigation.

### Technical
- Added `product_bundles` and `bundle_items` database tables
- New files: `bundles.php`, `get_bundle_items.php`, `get_schedule_history.php`
- Enhanced `custody.php` with automatic stock deduction logic
- Enhanced `scheduling.php` with upcoming tasks classification and edit/history modals
- Enhanced `timesheet.php` with 7-day historical analysis and aggregation

### Notes
- Bundle system supports controlled substance tracking - controlled items in bundles are properly logged for regulatory compliance
- Stock deductions are audited in `audit_log` for full traceability
- Scheduling upcoming threshold is configurable (currently 24 hours before due date)



## [0.9.10] - 2024-05-21
### Added
- **Global Timezone**: Enforced 'Pacific/Auckland' timezone across the application to ensure correct timestamps in logs and reports.
- **Scheduling**: Renamed "Cleaning" module to "Scheduling" to better reflect its versatility for general recurring tasks.
- **Documentation**: Completely rewrote README for better user clarity.

### Fixed
- **Responsive Tables**: Added horizontal scrolling to data tables (`.table-responsive`) to prevent layout breakage on mobile devices.
- **Timesheet**: Now explicitly records local time for Sign In/Out actions.

## [0.9.9] - 2024-05-21
### Fixed
- Fixed signature canvas alignment issue where the drawn line was offset from the cursor/finger. It now correctly calculates coordinates based on the canvas display size vs internal resolution.

## 0.9.8
- Bug Fix: Fixed Regression causing unbroken Navigation and Theme Toggle