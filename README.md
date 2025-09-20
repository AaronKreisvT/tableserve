# TableServe

**TableServe** is a lightweight web application for table-specific orders in restaurants and bars.  
Each table has its own QR code that points directly to the ordering page.  
Orders are stored in CSV files – no database is required.

---

## ⚡ Tech Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 8 (Apache + PHP-FPM)
- **Data storage:** CSV files (`/data`) instead of SQL databases
- **Authentication:** Session-based (staff/admin keys defined in `config.php`)
- **Export:** jsPDF for generating daily PDF reports
- **Printing:** Browser-based thermal receipt printing (80 mm width)
- **Deployment:** Works on any Linux/Windows server with PHP-FPM + SSL

---

## 📂 Directory Structure

tableserve/
├── api/ # PHP API endpoints (orders, status, menu, tables, reports)
├── assets/
│ ├── css/ # Stylesheets
│ ├── js/ # Frontend logic (order/staff/admin)
│ └── icons/ # UI icons
├── data/ # CSV files (menu.csv, tables.csv, orders.csv, order_items.csv)
├── partials/ # Header & footer templates
├── config.php # Keys, paths, constants
├── staff.html # Staff board (orders, receipts, reports)
├── admin.html # Admin panel (tables & menu management)
├── order.html # Guest ordering page (via QR code with ?code=XXXX)
├── index.html # Landing page for manual table code entry
└── ...


---

## 🔑 Security Considerations
- `/data` is protected against direct HTTP access using `.htaccess`.
- All user inputs (quantity, notes) are sanitized on the server side:
  - Notes are limited in length and stripped of unwanted characters.
  - Protection against CSV injection and XSS.
- No SQL layer → no SQL injection possible.

---

## 📦 Features
- QR-code-based ordering (each table has a unique code).
- Session-authenticated staff and admin areas.
- CSV-based persistence:  
  - `menu.csv` – menu items (name, price, category, active)  
  - `tables.csv` – tables (code, name)  
  - `orders.csv` – orders (id, table_code, status, created_at)  
  - `order_items.csv` – order positions (order_id, item_id, qty, notes)  
- Order IDs prefixed with table code to avoid collisions.
- Thermal receipt printing via browser (`window.print()` + CSS for 80 mm).
- Daily PDF reports via jsPDF (served-only items).
- No external dependencies except jsPDF and the browser environment.

---

## 🚀 Deployment
1. Clone repository into your webroot:
   ```bash
   git clone https://github.com/YOUR-ACCOUNT/tableserve.git /var/www/tableserve
2. Adjust config.php:
Define STAFF_KEY and ADMIN_KEY

Set paths if needed
3. Ensure /data is writable by the web server.
4. Configure Apache VirtualHost with PHP-FPM and SSl enabled.
5. Access:
order.html?code=XXXX for guests
staff.html for staff board
admin.html for admin management

## 📖 Documentation

The detailed user guide and setup instructions are published on my homepage:
👉 https://www.kreisaaron.de
