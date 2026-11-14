# AgriTrack — Agriculture Information System
### Cebu Technological University (CTU) — AP3 Midterm Project

A full-stack, normalized (3NF) web-based Agriculture Information System built with **PHP 8**, **Supabase PostgreSQL**, **Bootstrap 5**, and **Chart.js**.

---

## 🚀 Key Features

- **Relational 3NF Database Normalization:**
  - Core entity tables: `farmer`, `farm`, `crop`, `planting`, `harvest`.
  - 3NF Controlled reference lookup tables: `soil_types`, `irrigation_types`, `crop_categories`, `recommended_seasons`, `planting_statuses`, `harvest_quality_grades`, and `harvest_remarks`.
  - Strict adherence to the assignment's controlled dropdown requirement: e.g., Harvest Remarks displays descriptive text (`Good Harvest`, `Acceptable Quality`, `Low Yield`, `Poor Quality`, `Damaged Harvest`) while the database strictly stores the numeric ID (`1` to `5`).
- **Complete Sample Data (Exact Document Match):**
  - **10 Crops Master:** Rice, Corn, Tomato, Eggplant, Banana, Mango, Peanut, Cassava, Sweet Potato, Watermelon.
  - **15 Farmers:** Juan Dela Cruz, Maria Santos, Pedro Reyes, Ana Garcia, Jose Mendoza, Ramon Flores, Elena Cruz, Antonio Lim, Rosa Fernandez, Mario Villanueva, Teresa Navarro, Carlos Ramos, Liza Torres, Roberto Castillo, Susan Aquino.
  - **15 Farms:** Dela Cruz Farm, Santos Rice Farm, Reyes Agricultural Farm, Garcia Vegetable Farm, Mendoza Farm, Flores Corn Farm, Cruz Family Farm, Lim Agricultural Farm, Fernandez Farm, Villanueva Green Farm, Navarro Rice Field, Ramos Farm, Torres Vegetable Farm, Castillo Agricultural Farm, Aquino Farm.
  - **28 Plantings & 20 Harvests:** Fully linked with real agricultural cycles, yield quantities, and price calculations.
- **Role-Based Access Control (RBAC):**
  - **Administrator:** Full CRUD privileges across all tables, permanent deletion authorization, and User Management (`users.php`).
  - **Staff (Field Officer):** Authorized to create, view, and update records; deletion is restricted.
  - Secure **BCRYPT** password hashing (no plain-text passwords).
- **Executive Dashboard:**
  - Real-time summary cards (Total Farmers, Farms, Crops, Plantings, Harvests, Harvested kg, Total ₱ Valuation).
  - Interactive **Chart.js** visualizations:
    - Donut chart: Planting Operations by Status.
    - Bar chart: Harvest Yield Production by Crop.
  - Recent Plantings and Recent Harvests tables with badges.
- **Full CRUD Modules with Search, Filter & Pagination:**
  - Search by multiple fields, sort by columns, and navigate 10-record paginated tables.
  - Add/Edit modals with input constraint checking.
  - Referential integrity guards: prevents deleting parents with active children (e.g. farmers with farms, or crops with plantings).
  - Intelligent auto-calculation: expected harvest dates are auto-computed from crop growing periods.
- **Reporting & Financial Valuation:**
  - Multi-tab report suite: Harvest & Financial Valuation, Farmers & Farms Directory, Crops per Farm, and Plantings by Status.
  - Date range filtering.
  - One-click **Export to CSV**.
  - One-click **Print-Friendly Report** stylesheet.
- **Dual Cloud & Local Resilience:**
  - Direct connection to **Supabase PostgreSQL** via REST API and Row Level Security (RLS).
  - Automatic embedded SQLite engine ensures zero-downtime demonstration even offline!

---

## 🛠️ Quick Start Instructions

### 1. Run in XAMPP
1. Place this project folder in `c:\xampp\htdocs\agriculture_ap3`.
2. Start the **Apache** server from the XAMPP Control Panel.
3. Open your browser to:
   ```
   http://localhost/agriculture_ap3/
   ```

### 2. Default Login Credentials
- **Administrator:**
  - Username: `admin` (or `admin@agritrack.com`)
  - Password: `admin123`
- **Field Staff:**
  - Username: `staff` (or `staff@agritrack.com`)
  - Password: `staff123`

*(You can also click the quick **Demo Account Fill** buttons on the login page!)*

### 3. Deploying to Supabase (All-in-One Query)
1. Open your [Supabase Dashboard](https://supabase.com/dashboard).
2. In the left navigation, click **SQL Editor** > Click **+ New Query**.
3. Open [`supabase/schema_3nf.sql`](supabase/schema_3nf.sql), select all (`Ctrl+A`), copy, paste it into the SQL Editor, and click **Run**.
   *(This all-in-one master script safely cleans up old conflicting tables, sets up the 3NF schema, and automatically seeds all 10 crops, 15 farmers, 15 farms, 28 plantings, and 20 harvests in one single run!)*
4. Ensure your `.env` contains your project URL and publishable/anon key:
   ```env
   SUPABASE_URL=https://<your-project-id>.supabase.co
   SUPABASE_ANON_KEY=<your-anon-or-publishable-key>
   ```
5. Refresh the AgriTrack app in your browser: the top status pill will turn **● Supabase Connected**!

---

## 📂 Project Architecture

```
agriculture_ap3/
├── .env                         # Secure environment configuration
├── .env.example                 # Environment template for git
├── .gitignore                   # Excludes .env, local database, logs
├── index.php                    # System entry point / router
├── login.php                    # Secure authentication with demo account fills
├── logout.php                   # Session termination
├── dashboard.php                # Summary cards, Chart.js graphs, quick operations
├── app.php                      # Field User App (Crop Guide, Yield Calculator, Maturity Tracker)
├── profile.php                  # User Account & Profile Settings Interface
├── farmers.php                  # Farmer registry CRUD, profile view modal
├── farms.php                    # Farm parcels CRUD, soft-delete & trash restore
├── crops.php                    # Master crops catalog CRUD, growth cycles
├── plantings.php                # Planting cycles CRUD, auto-calculated expected dates
├── harvests.php                 # Harvest transactions, market pricing, 3NF controlled remarks
├── reports.php                  # Multi-tab reporting, date filters, CSV export, print
├── logs.php                     # Activity & Audit Logs (Deletions, Creations, Sign-ins, Timestamps)
├── users.php                    # Admin User Management & RBAC roles
├── setup.php                    # Supabase health diagnostic & sync tool
├── manifest.json                # PWA Web App manifest for installable mobile/desktop app
├── config/
│   ├── config.php               # Environment loader, CSRF, flash messages, formatters
│   └── database.php             # Unified Supabase REST API & Local SQLite client
├── includes/
│   ├── auth.php                 # Authentication helpers & RBAC permission checks
│   ├── header.php               # Responsive sidebar, navigation, status pill
│   └── footer.php               # System footer, Bootstrap Bundle, custom JS
├── assets/
│   ├── css/style.css            # Agricultural color theme, responsive cards, print CSS
│   └── js/app.js                # Auto-calculation scripts, modals, confirmation
├── supabase/
│   ├── schema_3nf.sql           # Master 3NF PostgreSQL DDL, constraints, RLS policies
│   └── seed_demo_data.sql       # Exact document seed rows (10 crops, 15 farmers, etc.)
├── DOCUMENTATION.md             # Complete AP3 midterm documentation (UNF-3NF, ERD, Data Dictionary)
└── README.md                    # Setup and operating instructions
```

---

## 🐙 Push to GitHub

To submit your code to your GitHub repository:

```bash
git init
git add .
git commit -m "Complete Agriculture Information System (AgriTrack) for AP3 Midterm"
git branch -M main
git remote add origin https://github.com/apaapgodwin-sys/Agriculture_ap3.git
git push -u origin main
```

*(Note: `.gitignore` protects your `.env` from being pushed to public repositories).*
