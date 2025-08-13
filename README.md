# Teklif Yönetim Sistemi

## Overview
**Teklif Yönetim Sistemi** ("Proposal Management System") is a PHP-based web application for generating and managing price quotations for *Giyotin* and *Sürme* window/door systems. It replaces the page-by-page Excel process with a centralized platform that streamlines offer preparation and customer interactions.

## Motivation
The project was initiated to eliminate the inefficient manual Excel workflow. By delivering a sustainable, maintainable and efficient digital solution, the system improves accuracy, consistency and traceability across the entire quotation lifecycle.

## Key Features
- **Role-Based Access Control (RBAC):** Separate **Admin** and **User** roles.
  - **Admin:** View, edit and add cost data, access reports and perform detailed analyses.
  - **User:** Create customers and send quotations.
- **Customer Interaction:** Customers receive approval links, submit negotiations and confirm orders through a web form.
- **Product Coverage:** Supports quotation management for both *Giyotin* and *Sürme* systems.

## Installation Instructions
1. Clone the repository:
   ```bash
   git clone https://github.com/hakanicellioglu/teklifPro.git
   cd teklifPro
   ```
2. Install PHP dependencies via Composer:
   ```bash
   composer install
   ```
3. Copy the example environment file and adjust configuration:
   ```bash
   cp .env.example .env
   ```
4. Configure database credentials in `.env` and generate an application key:
   ```bash
   php artisan key:generate
   ```
5. Run migrations and seeders (if available):
   ```bash
   php artisan migrate --seed
   ```
6. Start the development server:
   ```bash
   php artisan serve
   ```

## Technologies Used
- **PHP**
- **MySQL**
- **Bootstrap**
- Optional: **Composer**, **Laravel**, **JavaScript**

## Folder Structure (Example)
```
teklifPro/
├── app/              # Core application logic
├── bootstrap/
├── config/           # Configuration files
├── database/         # Migrations and seeders
├── public/           # Entry point (index.php) and public assets
├── resources/        # Views, language files, etc.
├── routes/           # Web and API route definitions
├── tests/            # Automated tests
└── vendor/           # Composer dependencies
```

## Usage
1. Log in using your credentials.
2. Create or select a customer.
3. Generate a quotation for either *Giyotin* or *Sürme* products.
4. Send the proposal link to the customer.
5. Monitor approvals, negotiations and confirmations in real time.

*(Replace placeholders with actual screenshots when available.)*

## Roadmap
- WhatsApp integration for real-time order status updates.
- Enhanced customer interaction and engagement features.

## License
This project is licensed under the [Apache License 2.0](LICENSE).

## Contact Information
For inquiries or support, please contact:

**Project Maintainer**  
Hakan Berke İçellioğlu 
[hakanicellioglu@gmail.com](mailto:hakanicellioglu@gmail.com)
## Apache Configuration
To enable the custom error pages and routing, ensure the Apache vhost allows `.htaccess` overrides:

```
<Directory "/path/to/htdocs">
    AllowOverride All
</Directory>
```

After updating the configuration, restart Apache for the changes to take effect.

