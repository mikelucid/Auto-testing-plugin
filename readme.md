# Laravel Auto Tester Plugin

Automatically generates and runs **Unit**, **Feature**, and **Browser (Dusk)** tests every time you change or add a PHP file in `app/`.

No more writing test stubs manually. Just code, and the plugin does the rest.

---

## Features

- 🔍 **Watches** `app/` for file changes/additions  
- ✍️ **Generates** 3 test types automatically:  
  - Unit tests (checks each method exists)  
  - Feature tests (tests conventional HTTP routes)  
  - Browser tests (Laravel Dusk)  
- ⚡ **Runs** all test suites immediately after each change  
- 📦 **Zero config** – drop it in and run  

---

## Requirements

- Laravel 8, 9, 10, or 11  
- PHP 7.4+  
- Composer  

---

## Installation

### 1. Add the command

Copy `AutoTesterCommand.php` into `app/Console/Commands/`.

### 2. Install Dusk (for browser tests)

```bash
composer require laravel/dusk --dev
php artisan dusk:install
