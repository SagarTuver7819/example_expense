# Ocean Expense Manager

Web-based role-based expense management system for Ocean Infotech.

## Setup

1. Create/import database:
   - Open phpMyAdmin.
   - Import `database.sql`.
2. Keep project in `c:\xampp\htdocs\expense_management`.
3. Start Apache + MySQL in XAMPP.
4. Open:
   - `http://localhost/expense_management`

## Default Login

- Admin:
  - Email: `admin@ocean.com`
  - Password: `admin123`
- Employee:
  - Email: `john@ocean.com`
  - Password: `admin123`

## Implemented Modules

- Authentication and session handling
- Dynamic roles and permissions
- User management (admin)
- Expense submission with bill upload
- Strict backdate logic with approval flow
- Expense approval/rejection
- Backdate approval/rejection
- Notifications with unread badge
- Admin/manager and employee dashboards (with charts)
- Reports with filters and Excel/PDF export options

## Notes

- Bill uploads are stored in `uploads/bills/` and auto-created on first upload.
- Excel export is CSV (opens in Excel).
- PDF export uses browser print dialog from report view.

## If Default Login Fails

Run this SQL in phpMyAdmin to reset both default passwords to `admin123`:

```sql
UPDATE users
SET password = '$2y$10$cMjNBpb1JzW6I6dN5jTfVuLnwDmejpWKRBaFe.A7SJ0CR87YVor9S'
WHERE email IN ('admin@ocean.com', 'john@ocean.com');
```

## Daily Expense Reminder

The app auto-creates a reminder notification for employees after `18:00` (server time) if today's expense is missing (triggered when they use the app).

For a true end-of-day reminder even if nobody opens the app, schedule this script using Windows Task Scheduler:

`php C:\xampp\htdocs\expense_management\cron\daily_reminder.php`
