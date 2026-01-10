# BUD Portal / Supply Stock Management

A comprehensive Inventory, Chain of Custody, and Scheduling system designed for Chill Division.

## Overview

The **BUD Portal** (Business Utility Dashboard) is a web-based application for managing:
- **Inventory Control**: Track stock levels, suppliers, and product bundles.
- **Chain of Custody (COC)**: Log and track controlled substances and other items sent to clients.
- **Scheduling**: Manage recurring tasks and cleaning schedules.
- **Timesheets**: Staff clock-in/clock-out and hours reporting.

## Architecture

*   **Backend**: PHP
*   **Database**: SQLite (`/data/bud.db`)
*   **Frontend**: HTML5, CSS (Dark/Light mode), JavaScript
*   **Deployment**: Home Assistant Addon or Standalone Docker container.

## Database Structure

The system uses a single SQLite database file. Schema migration is handled **automatically** by `config.php` on application startup.

### Core Tables

-   `stock_items`: Main inventory table.
    -   `is_controlled` (Boolean): Flags items requiring Chain of Custody logic.
-   `product_bundles`: Definitions for bundles (e.g., "Retail Pack").
-   `bundle_items`: Links stock items to bundles with quantities.
-   `chain_of_custody`: Records of shipments and deliveries.
-   `suppliers`: Supplier details.
-   `audit_log`: immutable history of all stock changes (Add, Subtract, COC).

### Modules

-   `staff`: Staff members for timesheets.
-   `timesheet`: Shift records.
-   `schedule` & `schedule_history`: Task management.

## Features & Usage

### 1. Stock Management (`stock.php`)
-   **View Stock**: Filterable table of all inventory.
-   **Quick Actions**:
    -   **+ Add**: Add stock with notes.
    -   **- Remove**: Deduct stock (Disabled for Controlled Substances).
    -   **📜 History**: View detailed audit trail for an item.
    -   **Edit**: Update item details.
-   **Bundles**: Manage product kits via "📦 Manage Bundles".

### 2. Chain of Custody (`custody.php`)
-   **Create Record**: Select Client and Items to ship.
-   **Validation**:
    -   Item selection is **filtered** to show only **Controlled Substances** and **Bundles** to prevent errors.
-   **Bundle Support**: Selecting a Bundle automatically calculates and deducts the correct quantity of individual components from stock.
-   **Signature**: Digital signature capture for verification.
-   **PDF Generation**: Generates official COC documents.

### 3. Scheduling (`scheduling.php`)
-   **Tasks**: View Upcoming and Due tasks.
-   **History**: Track completion logs.

## Deployment / Installation

### Home Assistant Addon
1.  Install the **Chill Division Addons** repository.
2.  Install **BUD Portal**.
3.  Start the addon.
4.  The database `bud.db` will be persisted in the `/data` directory.

### Configuration
Environmental variables (cleanly handled by `config.php`):
-   `APP_NAME`: Application Title (default "BUD Portal")
-   `Timezone`: Set to `Pacific/Auckland`.

## Development

### Auto-Migration
Database changes are applied automatically.
Check `config.php` -> `migrate_db()` function.
To add new tables, update the schema array in `config.php`.

### Versioning
-   See `CHANGELOG.md` for release history.
