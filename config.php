<?php
// Einfaches "Secret" fürs Tresenboard
define('STAFF_KEY', '12345678'); // ändere das!
date_default_timezone_set('Europe/Paris');

// Pfade
define('DATA_DIR', __DIR__ . '/data');
define('CSV_TABLES', DATA_DIR . '/tables.csv');
define('CSV_MENU', DATA_DIR . '/menu.csv');
define('CSV_ORDERS', DATA_DIR . '/orders.csv');
define('CSV_ORDER_ITEMS', DATA_DIR . '/order_items.csv');
