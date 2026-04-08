# Global Settings (Admin Configuration)

This project uses a single-row table named `global_settings` to store global configuration for the event, ticket pricing, manual payment display, booking rules, and check-in configuration.

## Database

### Table: `global_settings`

Created/ensured by `ensure_global_settings_schema()` in `config.php`.

This table is designed to have **one row only** (the code always loads the first row).

Key columns:

- `event_name`, `event_venue`, `event_year`
- `event_start_date`, `event_end_date`
- `price_dewasa`, `price_kanak`, `price_warga`
- `payment_method_name`, `payment_bank_name`, `payment_account_holder`, `payment_qr_path`, `payment_instructions`
- `max_tickets_per_booking`, `booking_status`, `allow_same_day_booking`
- `checkin_start_time`, `allow_ticket_reprint`, `ticket_reference_prefix`
- `admin_name`, `admin_email`

## Admin UI

### `settings.php`

The Settings page is organized into tabs:

- **Event / Buffet Information**
  - Updates: `event_name`, `event_venue`, `event_year`, `event_start_date`, `event_end_date`

- **Ticket Pricing Configuration**
  - Updates: `price_dewasa`, `price_kanak`, `price_warga`

- **Payment Configuration (Manual Payment)**
  - Updates: `payment_method_name`, `payment_bank_name`, `payment_account_holder`, `payment_instructions`
  - Uploads QR image and stores the relative file path in `payment_qr_path` (saved under `uploads/payment_qr/`).

- **Booking Rules**
  - Updates: `max_tickets_per_booking`, `booking_status` (OPEN/CLOSED), `allow_same_day_booking`

- **Check-in Configuration**
  - Updates: `checkin_start_time`, `allow_ticket_reprint`, `ticket_reference_prefix`

- **Admin Profile**
  - Display-only fields (no editing): `admin_name`, `admin_email`

## Runtime Usage (Where settings are applied)

### `index.php`

- Reads `global_settings` via `load_global_settings()`.
- Uses:
  - `event_start_date` / `event_end_date` to set the booking date range and slot query range.
  - `booking_status` to disable booking when CLOSED.
  - `event_name` for the landing page hero title.
- Uses `load_event_settings_prices()` for ticket prices (this prefers `global_settings` pricing).

### `save_booking.php`

- Recalculates totals server-side using `load_event_settings_prices()` (prefers `global_settings` prices).
- Saves uploaded payment proof file under `uploads/payment_proof/`.

### `check_in.php`

- Reads `checkin_start_time` and disables check-in actions until the configured time.
- Uses `event_name` for the sidebar subtitle.

## How to update settings later

- Go to **Admin -> Settings** (`settings.php`).
- Save changes per tab.
- The changes take effect immediately on the booking flow.
