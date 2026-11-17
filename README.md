# Mora Mori — online store

PHP 8 + MySQL/MariaDB, no Composer or build step. Runs on XAMPP or any PHP host.

## Setup
1. Copy the folder into `htdocs` (it is already at `C:\xampp\htdocs\moramori`).
2. Check the DB login and `BASE_URL` in `config.php`.
3. Open `/moramori/install.php`, enter your admin name, email and password.
   (Delete `install.lock` to run it again; existing data is kept.)

## Admin (`/moramori/admin/`)
- **Orders**: see the payment screenshot, verify or reject the payment, change the status
  (Confirmed → Being prepared → Shipped → Out for delivery → Delivered / Cancelled), add tracking notes.
  Each change can email the customer. Cancelling puts the stock back.
- **Delivery locations**: add locations with a fee and delivery time, and switch delivery on/off for each one.
  Choose whether unavailable locations are shown greyed out at checkout or hidden.
- **Products / Categories**: images, sizes, stock, sale price, featured.
- **Settings → Payment**: upload your static QR code (eSewa / Khalti / Fonepay / bank).
- **Settings → Email**: SMTP for real email (Gmail: smtp.gmail.com, 587, tls, App Password).
  Until SMTP is on, emails are only recorded in **Email log**.
- **Customers / Login history**: every sign-in attempt with time, device and IP.

## Customer side
Guest or account checkout → choose delivery location → scan QR → upload screenshot.
Accounts get order history, order tracking, login history and password change/reset.
Guests track orders with order number + email (`/track.php`).

## Going live
- Set `APP_DEBUG` to `false` in `config.php`, use HTTPS, set a DB password.
- `uploads/payments` is private (blocked by `.htaccess`, shown only to admins via `admin/file.php`).
- Delete `admin-credentials.txt` once you have your own admin account.
