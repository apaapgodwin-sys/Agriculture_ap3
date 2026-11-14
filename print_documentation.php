<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgriTrack — Project Documentation | CTU AP3 Midterm</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Document Styles for Long Bond Paper (8.5in x 13in / Folio) */
        @page {
            size: 8.5in 13in;
            margin: 0.8in;
        }

        body {
            font-family: "Georgia", "Times New Roman", serif;
            color: #1a1a1a;
            line-height: 1.6;
            background-color: #f1f5f9;
        }

        .paper-sheet {
            background: #ffffff;
            width: 8.5in;
            min-height: 13in;
            margin: 20px auto;
            padding: 0.8in;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f2316;
            font-weight: 700;
        }

        .doc-section-title {
            border-bottom: 2px solid #15803d;
            padding-bottom: 6px;
            margin-top: 2rem;
            margin-bottom: 1.2rem;
            color: #15803d;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cover-page {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 11in;
            text-align: center;
            border: 4px double #15803d;
            padding: 3rem 2rem;
        }

        .table-doc {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.88rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .table-doc th, .table-doc td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
        }

        .table-doc th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 600;
        }

        .code-block {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            font-family: "Courier New", Courier, monospace;
            font-size: 0.82rem;
            line-height: 1.45;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        .badge-doc {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-pass { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        .floating-action-bar {
            position: fixed;
            top: 15px;
            right: 20px;
            z-index: 9999;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .screenshot-frame {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 1rem;
            background-color: #f8fafc;
            margin-bottom: 1.5rem;
        }

        .screenshot-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 600;
            color: #0f2316;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }

        .page-break {
            page-break-before: always;
            break-before: page;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .paper-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .floating-action-bar {
                display: none !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Floating Action Bar (hidden when printing) -->
<div class="floating-action-bar no-print">
    <button onclick="window.print()" class="btn btn-success btn-sm px-3 fw-bold shadow-sm">
        <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF (Long Bond Paper)
    </button>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Return to System
    </a>
</div>

<div class="paper-sheet">

    <!-- ================================================================== -->
    <!-- COVER PAGE                                                         -->
    <!-- ================================================================== -->
    <div class="cover-page">
        <div style="font-size: 1.15rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #166534;">
            Cebu Technological University
        </div>
        <div style="font-size: 0.95rem; font-weight: 600; color: #475569; margin-top: 4px;">
            College of Computer, Information and Communications Technology
        </div>
        <div style="font-size: 0.85rem; color: #64748b;">
            Cebu City Campus · Main Academic Center
        </div>

        <div style="margin: 4.5rem 0 3.5rem;">
            <div style="font-size: 2.3rem; font-weight: 800; color: #0f2316; line-height: 1.2;">
                AgriTrack
            </div>
            <div style="font-size: 1.35rem; font-weight: 600; color: #15803d; margin-top: 8px;">
                Agriculture Information System
            </div>
            <div style="font-size: 0.95rem; color: #64748b; margin-top: 10px; max-width: 480px; margin-left: auto; margin-right: auto;">
                A Normalized 3NF Relational Database Management System with Supabase PostgreSQL, Offline Resilience & Field Operations
            </div>
        </div>

        <div style="border-top: 1px solid #15803d; border-bottom: 1px solid #15803d; padding: 1.2rem 2.5rem; margin: 2rem 0; width: 100%; max-width: 520px;">
            <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;">Course Requirement</div>
            <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-top: 4px;">AP3 — Midterm Project Documentation</div>
        </div>

        <div style="margin-top: 3.5rem; font-size: 0.92rem; text-align: center;">
            <p class="mb-1"><strong>Submitted By:</strong></p>
            <p class="mb-3" style="font-size: 1.05rem; font-weight: 700; color: #0f172a;">STUDENT NAME / PROJECT TEAM</p>
            
            <p class="mb-1"><strong>Section & Folder Color:</strong></p>
            <div class="d-inline-flex gap-3 justify-content-center mb-4">
                <span style="border: 2px solid #2563eb; color: #1d4ed8; padding: 4px 14px; border-radius: 6px; font-weight: 700;">
                    [ ] II-A (Blue Folder)
                </span>
                <span style="border: 2px solid #dc2626; color: #b91c1c; padding: 4px 14px; border-radius: 6px; font-weight: 700;">
                    [ ] II-1 (Red Folder)
                </span>
            </div>

            <p class="mb-1"><strong>Instructor:</strong></p>
            <p class="mb-3" style="font-weight: 600;">Course Instructor / Evaluator</p>

            <p class="text-muted small mt-4">November 2026 · Academic Year 2026–2027</p>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- 1. PROJECT TITLE, DESCRIPTION & OBJECTIVES                         -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">1. Project Title, Description & Objectives</h2>
    
    <h5>1.1 Project Title</h5>
    <p><strong>AgriTrack — Agriculture Information System</strong></p>

    <h5>1.2 Project Description</h5>
    <p>
        The <strong>Agriculture Information System (AgriTrack)</strong> is an enterprise-grade, web-based database management software designed to centralize, standardize, and monitor agricultural landholdings, farmer registries, crop cultivation cycles, and harvest transactions across the province of Cebu. 
    </p>
    <p>
        The system implements rigorous relational database normalization (progressing from Unnormalized Form up to Third Normal Form), strictly enforces relational referential integrity, and incorporates secure Role-Based Access Control (RBAC) separating System Administrators from Agricultural Field Staff. Data storage is hosted on <strong>Supabase PostgreSQL</strong> with real-time REST API integration and a zero-downtime offline local fallback engine.
    </p>

    <h5>1.3 Project Objectives</h5>
    <ol>
        <li><strong>Relational Database Normalization:</strong> Architect a database normalized from UNF to 3NF, eliminating repeating groups, partial functional dependencies, and transitive functional dependencies.</li>
        <li><strong>Controlled Reference Lookup Values:</strong> Implement 3NF lookup tables for controlled dropdown lists (Soil Types, Irrigation Types, Crop Categories, Recommended Seasons, Planting Statuses, Quality Grades, and Harvest Remarks) ensuring numeric foreign keys are stored while descriptive labels are presented in the user interface.</li>
        <li><strong>Cloud Database Hosting (Supabase PostgreSQL):</strong> Host the relational PostgreSQL database in the cloud on Supabase, reading and writing live data via authenticated PostgREST endpoints and Row Level Security (RLS) policies.</li>
        <li><strong>Comprehensive CRUD Operations:</strong> Provide full Create, Read, Update, and Delete capabilities across Farmers, Farms, Crops, Planting Records, and Harvest Yields.</li>
        <li><strong>Executive Visualization & Analytics:</strong> Offer an interactive executive dashboard displaying aggregated agricultural metrics (total farmers, landholdings, active planting cycles, total harvested yield, and gross valuation in Philippine Pesos) coupled with Chart.js visualizations.</li>
        <li><strong>Operational Reporting & Audit Logging:</strong> Generate multi-criteria agricultural reports with date filtering, print stylesheets, RFC-4180 CSV export, and an automated immutable audit log tracking what was deleted, who logged in, what was created, and exact timestamps.</li>
        <li><strong>Field Assistance & User App:</strong> Provide a mobile-responsive User App featuring a Crop Guide, real-time Yield & Gross Profit Calculator, active planting countdown tracker, and agro-climatic advisories.</li>
    </ol>

    <!-- ================================================================== -->
    <!-- 2. FUNCTIONAL REQUIREMENTS                                         -->
    <!-- ================================================================== -->
    <h2 class="doc-section-title">2. Functional Requirements</h2>
    
    <h5>2.1 User Authentication & Role-Based Access Control (RBAC)</h5>
    <ul>
        <li><strong>Sign-In Authentication:</strong> Secure authentication verifying username/email and BCRYPT password hash. Passwords are never stored in plain text.</li>
        <li><strong>Administrator Role:</strong> Complete permissions across all tables, permanent deletion authorization, system user management (`users.php`), and audit log purge capabilities.</li>
        <li><strong>Staff Role (Field Officer):</strong> Authorized to create, view, and update farmers, farms, plantings, harvests, and field tools; permanent deletion is restricted.</li>
        <li><strong>CSRF Token Guard:</strong> All POST transactions require cryptographically validated session tokens.</li>
    </ul>

    <h5>2.2 Core Database Modules (CRUD)</h5>
    <ul>
        <li><strong>Farmers Registry:</strong> Personal information management (`first_name`, `last_name`, `contact_number`, `address`, `date_registered`), dynamic search, 10-record pagination, and profile inspection modal.</li>
        <li><strong>Farm Land Parcels:</strong> Management of agricultural parcels (`farm_name`, `location`, `area_hectares`) with 3NF foreign keys linking owner farmer, soil type, and irrigation infrastructure. Features soft-delete trash bin and restore.</li>
        <li><strong>Crops Master Catalog:</strong> Comprehensive catalogue of crops (`crop_name`, `variety`, `growing_period_days`) linked to 3NF categories and recommended seasons.</li>
        <li><strong>Planting Cycles:</strong> Cultivation cycle logs with intelligent auto-calculation: selecting a crop automatically sets `expected_harvest_date = planting_date + growing_period_days`.</li>
        <li><strong>Harvest Records:</strong> Yield harvest transactions (`harvest_date`, `quantity_kg`, `selling_price_per_kg`, `quality_grade_id`, `remark_id`). Dynamically calculates valuation (`quantity_kg * selling_price_per_kg`) and automatically updates planting status to "Harvested".</li>
    </ul>

    <h5>2.3 Reporting & Financial Analytics</h5>
    <ul>
        <li><strong>Harvest & Valuation Report:</strong> Yield tonnage and gross project revenue with date range filtering.</li>
        <li><strong>Farmers & Landholdings Directory:</strong> Farm ownership listings by municipality.</li>
        <li><strong>Crops per Farm Report:</strong> Distribution of crop varieties across registered land parcels.</li>
        <li><strong>Planting Records by Status:</strong> Operational status audit (Growing, Harvested, Planned).</li>
        <li><strong>Export to CSV & Print:</strong> RFC-4180 compliant export and printer-friendly stylesheets.</li>
    </ul>

    <h5>2.4 Field User App & Audit Trail</h5>
    <ul>
        <li><strong>Field User App (`app.php`):</strong> Crop Guide, interactive Yield & Profit Calculator, active planting countdowns, and agro-climatic advisories.</li>
        <li><strong>Activity & Audit Logs (`logs.php`):</strong> Tracks all creates, updates, deletions, sign-ins, IP addresses, and timestamps.</li>
    </ul>

    <!-- ================================================================== -->
    <!-- 3. ENTITY-RELATIONSHIP DIAGRAM (ERD)                               -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">3. Entity-Relationship Diagram (ERD)</h2>
    <p>
        The normalized database consists of 5 core entity tables, 1 system user table, 1 immutable activity audit log table, and 7 reference lookup tables ensuring complete Third Normal Form (3NF) compliance.
    </p>

    <div class="code-block mb-3">
+-------------------------+            +----------------------------+
|         FARMER          | 1        M |            FARM            |
|-------------------------|<-----------|----------------------------|
| farmer_id [PK]          |            | farm_id [PK]               |
| first_name              |            | farmer_id [FK]             |
| last_name               |            | farm_name                  |
| contact_number          |            | location                   |
| address                 |            | area_hectares              |
| date_registered         |            | soil_type_id [FK]          |
+-------------------------+            | irrigation_type_id [FK]    |
                                       | deleted_at (Soft Delete)   |
                                       +----------------------------+
                                                     | 1
                                                     |
                                                     | M
+-------------------------+ 1        M +----------------------------+
|          CROP           |<-----------|          PLANTING          |
|-------------------------|            |----------------------------|
| crop_id [PK]            |            | planting_id [PK]           |
| crop_name               |            | farm_id [FK]               |
| variety                 |            | crop_id [FK]               |
| category_id [FK]        |            | planting_date              |
| growing_period_days     |            | area_planted               |
| season_id [FK]          |            | seed_quantity              |
+-------------------------+            | expected_harvest_date      |
                                       | status_id [FK]             |
                                       +----------------------------+
                                                     | 1
                                                     |
                                                     | M
                                       +----------------------------+
                                       |          HARVEST           |
                                       |----------------------------|
                                       | harvest_id [PK]            |
                                       | planting_id [FK]           |
                                       | harvest_date               |
                                       | quantity_kg                |
                                       | selling_price_per_kg       |
                                       | quality_grade_id [FK]      |
                                       | remark_id [FK]             |
                                       | remarks_note               |
                                       +----------------------------+
    </div>

    <!-- ================================================================== -->
    <!-- 4. DATABASE NORMALIZATION (UNF TO 3NF)                             -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">4. Database Normalization (UNF through 3NF)</h2>

    <h5>4.1 Unnormalized Form (UNF)</h5>
    <p>A flat, unnormalized spreadsheet containing repeating groups, multivalued fields, and composite attributes:</p>
    <div class="code-block mb-3">
UNF_AGRICULTURE_LOG (
    Farmer_Name, Contact_Details, Address, Registration_Date,
    [ Farm_Name, Farm_Location, Farm_Size, Soil, Irrigation,
      [ Crop_Name, Crop_Variety, Category, Growth_Days, Season,
        Planting_Date, Area_Planted, Seed_Used, Expected_Date, Planting_Status,
        [ Harvest_Date, Yield_Kg, Price_Per_Kg, Total_Revenue, Grade, Remarks ]
      ]
    ]
)
    </div>
    <p><strong>Identified UNF Anomalies:</strong></p>
    <ul>
        <li><strong>Repeating Groups:</strong> Multiple farms, plantings, and harvest records exist per farmer.</li>
        <li><strong>Non-Atomic Values:</strong> `Farmer_Name` concatenates first name and last name.</li>
        <li><strong>Update & Deletion Anomalies:</strong> Deleting a harvest record could erase the farmer or crop specification.</li>
    </ul>

    <h5>4.2 First Normal Form (1NF)</h5>
    <p><strong>Transformation Steps:</strong> Decompose non-atomic attributes (`first_name`, `last_name`), eliminate repeating groups by creating discrete transaction rows, and define a unique primary key.</p>
    <div class="code-block mb-3">
1NF_FLAT_RECORD (
    farmer_id [PK], first_name, last_name, contact_number, address, date_registered,
    farm_id [PK], farm_name, location, area_hectares, soil_type, irrigation_type,
    crop_id [PK], crop_name, variety, category, growing_period_days, season,
    planting_id [PK], planting_date, area_planted, seed_quantity, expected_harvest_date, status,
    harvest_id [PK], harvest_date, quantity_kg, selling_price_per_kg, quality_grade, remarks
)
    </div>
    <p><strong>Identified 1NF Anomaly:</strong> Partial functional dependencies exist where non-key attributes depend on only a subset of the composite primary key.</p>

    <h5>4.3 Second Normal Form (2NF)</h5>
    <p><strong>Transformation Steps:</strong> Eliminate all partial dependencies by separating functional entities so that every non-key attribute is fully functionally dependent on the entity primary key.</p>
    <ul>
        <li><strong>FARMER</strong> (`farmer_id` [PK], `first_name`, `last_name`, `contact_number`, `address`, `date_registered`)</li>
        <li><strong>FARM</strong> (`farm_id` [PK], `farmer_id` [FK], `farm_name`, `location`, `area_hectares`, `soil_type`, `irrigation_type`)</li>
        <li><strong>CROP</strong> (`crop_id` [PK], `crop_name`, `variety`, `category`, `growing_period_days`, `season`)</li>
        <li><strong>PLANTING</strong> (`planting_id` [PK], `farm_id` [FK], `crop_id` [FK], `planting_date`, `area_planted`, `seed_quantity`, `expected_harvest_date`, `status`)</li>
        <li><strong>HARVEST</strong> (`harvest_id` [PK], `planting_id` [FK], `harvest_date`, `quantity_kg`, `selling_price_per_kg`, `quality_grade`, `remarks`)</li>
    </ul>
    <p><strong>Identified 2NF Anomaly:</strong> Transitive functional dependencies exist where non-key attributes depend on other non-key attributes (e.g. text classifications for soil types, crop categories, and harvest remarks cause string redundancy).</p>

    <h5>4.4 Third Normal Form (3NF)</h5>
    <p><strong>Transformation Steps:</strong> Eliminate all transitive dependencies ($X \rightarrow Y$ and $Y \rightarrow Z$). Extract all controlled categorical lists into independent lookup tables connected by numeric foreign keys.</p>
    
    <table class="table-doc">
        <thead>
            <tr>
                <th>3NF Lookup Table</th>
                <th>Primary Key</th>
                <th>Descriptive Attribute</th>
                <th>Sample Controlled Domain</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>crop_categories</code></td>
                <td><code>category_id</code></td>
                <td><code>category_name</code></td>
                <td>Cereal, Vegetable, Fruit, Legume, Root Crop</td>
            </tr>
            <tr>
                <td><code>recommended_seasons</code></td>
                <td><code>season_id</code></td>
                <td><code>season_name</code></td>
                <td>Wet Season, Dry Season, Year Round</td>
            </tr>
            <tr>
                <td><code>soil_types</code></td>
                <td><code>soil_type_id</code></td>
                <td><code>soil_type_name</code></td>
                <td>Loam, Clay Loam, Sandy Loam, Clay</td>
            </tr>
            <tr>
                <td><code>irrigation_types</code></td>
                <td><code>irrigation_type_id</code></td>
                <td><code>irrigation_type_name</code></td>
                <td>Rainfed, Irrigated, Drip Irrigation</td>
            </tr>
            <tr>
                <td><code>planting_statuses</code></td>
                <td><code>status_id</code></td>
                <td><code>status_name</code></td>
                <td>Growing, Harvested, Cancelled, Planned</td>
            </tr>
            <tr>
                <td><code>harvest_quality_grades</code></td>
                <td><code>quality_grade_id</code></td>
                <td><code>grade_name</code></td>
                <td>Grade A, Grade B, Grade C</td>
            </tr>
            <tr>
                <td><code>harvest_remarks</code></td>
                <td><code>remark_id</code></td>
                <td><code>remark_description</code></td>
                <td>1: Good Harvest, 2: Acceptable Quality, 3: Low Yield, 4: Poor Quality, 5: Damaged Harvest</td>
            </tr>
        </tbody>
    </table>

    <!-- ================================================================== -->
    <!-- 5. DATA DICTIONARY                                                 -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">5. Comprehensive Data Dictionary</h2>

    <h5>Table: <code>farmer</code> (Registered Farmers)</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Constraints</th><th>Description</th></tr>
        </thead>
        <tbody>
            <tr><td><code>farmer_id</code></td><td>INTEGER</td><td>NO</td><td>PK</td><td>AUTO / IDENTITY</td><td>Unique Farmer ID</td></tr>
            <tr><td><code>first_name</code></td><td>VARCHAR(50)</td><td>NO</td><td>—</td><td>Length &gt; 0</td><td>Given name</td></tr>
            <tr><td><code>last_name</code></td><td>VARCHAR(50)</td><td>NO</td><td>—</td><td>Length &gt; 0</td><td>Family name</td></tr>
            <tr><td><code>contact_number</code></td><td>VARCHAR(20)</td><td>YES</td><td>—</td><td>Valid phone</td><td>Mobile number</td></tr>
            <tr><td><code>address</code></td><td>VARCHAR(150)</td><td>NO</td><td>—</td><td>Length &gt; 0</td><td>Barangay / City</td></tr>
            <tr><td><code>date_registered</code></td><td>DATE</td><td>NO</td><td>—</td><td>CURRENT_DATE</td><td>Registration date</td></tr>
        </tbody>
    </table>

    <h5>Table: <code>farm</code> (Land Parcels)</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Constraints</th><th>Description</th></tr>
        </thead>
        <tbody>
            <tr><td><code>farm_id</code></td><td>INTEGER</td><td>NO</td><td>PK</td><td>AUTO / IDENTITY</td><td>Unique Farm ID</td></tr>
            <tr><td><code>farmer_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Owner Farmer ID</td></tr>
            <tr><td><code>farm_name</code></td><td>VARCHAR(100)</td><td>NO</td><td>—</td><td>Length &gt; 0</td><td>Name of parcel</td></tr>
            <tr><td><code>location</code></td><td>VARCHAR(150)</td><td>NO</td><td>—</td><td>Length &gt; 0</td><td>Municipality</td></tr>
            <tr><td><code>area_hectares</code></td><td>NUMERIC(8,2)</td><td>NO</td><td>—</td><td>&gt; 0.00</td><td>Land area in ha</td></tr>
            <tr><td><code>soil_type_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Soil Lookup FK</td></tr>
            <tr><td><code>irrigation_type_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Irrigation FK</td></tr>
            <tr><td><code>deleted_at</code></td><td>TIMESTAMPTZ</td><td>YES</td><td>—</td><td>Default NULL</td><td>Soft delete flag</td></tr>
        </tbody>
    </table>

    <h5>Table: <code>crop</code> (Master Crop Catalogue)</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Constraints</th><th>Description</th></tr>
        </thead>
        <tbody>
            <tr><td><code>crop_id</code></td><td>INTEGER</td><td>NO</td><td>PK</td><td>AUTO / IDENTITY</td><td>Crop ID</td></tr>
            <tr><td><code>crop_name</code></td><td>VARCHAR(80)</td><td>NO</td><td>—</td><td>Length &gt; 0</td><td>Botanical name</td></tr>
            <tr><td><code>variety</code></td><td>VARCHAR(80)</td><td>YES</td><td>—</td><td>UNIQUE(name,var)</td><td>Cultivar variety</td></tr>
            <tr><td><code>category_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Category Lookup FK</td></tr>
            <tr><td><code>growing_period_days</code></td><td>INTEGER</td><td>NO</td><td>—</td><td>&gt; 0</td><td>Days to maturity</td></tr>
            <tr><td><code>season_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Season Lookup FK</td></tr>
        </tbody>
    </table>

    <h5>Table: <code>planting</code> (Cultivation Cycles)</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Constraints</th><th>Description</th></tr>
        </thead>
        <tbody>
            <tr><td><code>planting_id</code></td><td>INTEGER</td><td>NO</td><td>PK</td><td>AUTO / IDENTITY</td><td>Planting Cycle ID</td></tr>
            <tr><td><code>farm_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Target Farm FK</td></tr>
            <tr><td><code>crop_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Target Crop FK</td></tr>
            <tr><td><code>planting_date</code></td><td>DATE</td><td>NO</td><td>—</td><td>Valid calendar date</td><td>Date sown</td></tr>
            <tr><td><code>area_planted</code></td><td>NUMERIC(8,2)</td><td>NO</td><td>—</td><td>&gt; 0.00</td><td>Planted area (ha)</td></tr>
            <tr><td><code>seed_quantity</code></td><td>NUMERIC(8,2)</td><td>YES</td><td>—</td><td>&gt;= 0.00</td><td>Seed kg used</td></tr>
            <tr><td><code>expected_harvest_date</code></td><td>DATE</td><td>YES</td><td>—</td><td>&gt;= planting_date</td><td>Computed harvest</td></tr>
            <tr><td><code>status_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Status Lookup FK</td></tr>
        </tbody>
    </table>

    <h5>Table: <code>harvest</code> (Yield Transactions)</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Constraints</th><th>Description</th></tr>
        </thead>
        <tbody>
            <tr><td><code>harvest_id</code></td><td>INTEGER</td><td>NO</td><td>PK</td><td>AUTO / IDENTITY</td><td>Harvest ID</td></tr>
            <tr><td><code>planting_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Planting Cycle FK</td></tr>
            <tr><td><code>harvest_date</code></td><td>DATE</td><td>NO</td><td>—</td><td>Valid calendar date</td><td>Date harvested</td></tr>
            <tr><td><code>quantity_kg</code></td><td>NUMERIC(10,2)</td><td>NO</td><td>—</td><td>&gt; 0.00</td><td>Harvest yield (kg)</td></tr>
            <tr><td><code>selling_price_per_kg</code></td><td>NUMERIC(10,2)</td><td>NO</td><td>—</td><td>&gt;= 0.00</td><td>Market price/kg</td></tr>
            <tr><td><code>quality_grade_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>Quality Grade FK</td></tr>
            <tr><td><code>remark_id</code></td><td>INTEGER</td><td>NO</td><td>FK</td><td>RESTRICT</td><td>3NF Remark FK</td></tr>
            <tr><td><code>remarks_note</code></td><td>VARCHAR(200)</td><td>YES</td><td>—</td><td>Optional string</td><td>Inspector notes</td></tr>
        </tbody>
    </table>

    <h5>Table: <code>activity_logs</code> (Audit Trail)</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Constraints</th><th>Description</th></tr>
        </thead>
        <tbody>
            <tr><td><code>log_id</code></td><td>INTEGER</td><td>NO</td><td>PK</td><td>AUTO / IDENTITY</td><td>Unique Log ID</td></tr>
            <tr><td><code>user_id</code></td><td>INTEGER</td><td>YES</td><td>FK</td><td>SET NULL</td><td>User ID responsible</td></tr>
            <tr><td><code>username</code></td><td>VARCHAR(50)</td><td>NO</td><td>—</td><td>Non-empty string</td><td>Username handle</td></tr>
            <tr><td><code>action</code></td><td>VARCHAR(30)</td><td>NO</td><td>—</td><td>CREATE/UPDATE/DELETE/LOGIN</td><td>Action code</td></tr>
            <tr><td><code>module</code></td><td>VARCHAR(30)</td><td>NO</td><td>—</td><td>AUTH/FARMER/FARM/etc.</td><td>Module identifier</td></tr>
            <tr><td><code>record_id</code></td><td>INTEGER</td><td>YES</td><td>—</td><td>Positive integer</td><td>Affected primary key</td></tr>
            <tr><td><code>description</code></td><td>TEXT</td><td>NO</td><td>—</td><td>Detailed audit text</td><td>Event description</td></tr>
            <tr><td><code>ip_address</code></td><td>VARCHAR(45)</td><td>YES</td><td>—</td><td>Client IP</td><td>Remote IP address</td></tr>
            <tr><td><code>created_at</code></td><td>TIMESTAMPTZ</td><td>NO</td><td>—</td><td>CURRENT_TIMESTAMP</td><td>Exact timestamp</td></tr>
        </tbody>
    </table>

    <!-- ================================================================== -->
    <!-- 6. TABLE RELATIONSHIPS & CONSTRAINTS                               -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">6. Table Relationships, Constraints & Lookup Data</h2>

    <h5>6.1 Referential Integrity Constraints</h5>
    <ul>
        <li><code>farm.farmer_id -> farmer.farmer_id (ON DELETE RESTRICT)</code>: Prevents deleting a farmer who owns active farm parcels.</li>
        <li><code>planting.farm_id -> farm.farm_id (ON DELETE RESTRICT)</code>: Prevents deleting or purging a farm that has active cultivation cycles.</li>
        <li><code>planting.crop_id -> crop.crop_id (ON DELETE RESTRICT)</code>: Prevents deleting a crop variety currently planted in active parcels.</li>
        <li><code>harvest.planting_id -> planting.planting_id (ON DELETE RESTRICT)</code>: Prevents deleting a planting cycle that has recorded harvest yields.</li>
    </ul>

    <h5>6.2 Pre-Seeded 3NF Master & Sample Data (Exact Document Match)</h5>
    <ul>
        <li><strong>10 Master Crops:</strong> Rice (115d), Corn (100d), Tomato (90d), Eggplant (100d), Banana (300d), Mango (150d), Peanut (110d), Cassava (240d), Sweet Potato (120d), Watermelon (85d).</li>
        <li><strong>15 Farmers:</strong> Juan Dela Cruz, Maria Santos, Pedro Reyes, Ana Garcia, Jose Mendoza, Ramon Flores, Elena Cruz, Antonio Lim, Rosa Fernandez, Mario Villanueva, Teresa Navarro, Carlos Ramos, Liza Torres, Roberto Castillo, Susan Aquino.</li>
        <li><strong>15 Farms:</strong> Dela Cruz Farm, Santos Rice Farm, Reyes Agricultural Farm, Garcia Vegetable Farm, Mendoza Farm, Flores Corn Farm, Cruz Family Farm, Lim Agricultural Farm, Fernandez Farm, Villanueva Green Farm, Navarro Rice Field, Ramos Farm, Torres Vegetable Farm, Castillo Agricultural Farm, Aquino Farm.</li>
        <li><strong>28 Planting Cycles & 20 Harvest Transactions:</strong> Spanning active and completed harvest operations.</li>
    </ul>

    <!-- ================================================================== -->
    <!-- 7. USER GUIDE & OPERATING INSTRUCTIONS                             -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">7. User Guide & Basic Operating Instructions</h2>

    <h5>7.1 System Access & Credentials</h5>
    <table class="table-doc">
        <thead>
            <tr><th>Role</th><th>Username</th><th>Password</th><th>Privileges</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Administrator</strong></td>
                <td><code>admin</code></td>
                <td><code>admin123</code></td>
                <td>Full CRUD, user management, audit purge, database sync.</td>
            </tr>
            <tr>
                <td><strong>Field Staff</strong></td>
                <td><code>staff</code></td>
                <td><code>staff123</code></td>
                <td>Create and update operations, view reports, field app logger.</td>
            </tr>
        </tbody>
    </table>

    <h5>7.2 Standard Operating Procedures</h5>
    <ol>
        <li><strong>Accessing the System:</strong> Navigate to the web application URL (`http://localhost/agriculture_ap3/` locally or `https://agriculture-ap3.onrender.com` on Render). Sign in using administrator or field staff credentials.</li>
        <li><strong>Registering Farmers & Farms:</strong> Navigate to <em>Farmers</em> to register new agricultural landholders. Then navigate to <em>Farms</em> to add acreage, location, and assign 3NF soil and irrigation classifications.</li>
        <li><strong>Logging Planting Cycles:</strong> Navigate to <em>Planting Records</em> &gt; Click <em>Log Planting Cycle</em>. Select the target farm and crop variety. The expected harvest date is dynamically auto-calculated based on the crop's growth period.</li>
        <li><strong>Recording Harvest Yields:</strong> In <em>Planting Records</em> or <em>Harvest Records</em>, click the harvest action. Enter harvested weight in kg and market selling price per kg. Select the Quality Grade and mandatory 3NF controlled harvest remark. Saving the record automatically transitions the planting status to "Harvested".</li>
        <li><strong>Using the Field User App (`app.php`):</strong> Field officers can access the Crop Guide, use the interactive Yield & Gross Profit Calculator to estimate revenue, monitor maturity countdowns, and execute quick field logs.</li>
        <li><strong>Reviewing Audit Logs (`logs.php`):</strong> Administrators can inspect the complete audit trail, filter by action type or user, and export the logs to CSV.</li>
    </ol>

    <!-- ================================================================== -->
    <!-- 8. SCREENSHOTS & EVIDENCE OF MAJOR MODULES                         -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">8. Evidence of Major System Modules</h2>

    <div class="screenshot-frame">
        <div class="screenshot-header">
            <span>Module 1: Authentication & Demo Quick Sign-in (`login.php`)</span>
            <span class="badge bg-success">Operational</span>
        </div>
        <p class="small text-muted mb-2">
            Secure authentication portal featuring BCRYPT verification, CTU institutional branding, session CSRF protection, and 1-click demo account fills for Administrator (`admin / admin123`) and Field Staff (`staff / staff123`).
        </p>
        <div class="code-block">
[ UI Interface: Sign In Card | Dark Agricultural Green Gradient (#0d3b1e -> #164e29) ]
- Input Fields: Username / Email Address, Password (encrypted)
- Interactive Action: Demo Admin Fill | Demo Staff Fill
- Security: One-way Cryptographic BCRYPT Hash Verification
        </div>
    </div>

    <div class="screenshot-frame">
        <div class="screenshot-header">
            <span>Module 2: Executive Analytics Dashboard (`dashboard.php`)</span>
            <span class="badge bg-success">Operational</span>
        </div>
        <p class="small text-muted mb-2">
            Central operational hub displaying real-time aggregated metrics (7 summary cards), interactive Chart.js visualizations (Plantings Donut Chart, Crop Yields Bar Chart), Recent Transactions table, and Live Activity Audit Trail.
        </p>
        <div class="code-block">
[ Summary Metrics: 15 Farmers | 15 Farms | 10 Crops | 28 Plantings | 20 Harvests | 41,200 kg Yield | ₱1,328,625 Valuation ]
[ Charts: Status Donut Chart (Growing, Harvested, Planned) | Bar Chart (Top Yields by Crop) ]
[ Audit Trail: Live activity feed showing recent operational events with time-ago badges ]
        </div>
    </div>

    <div class="screenshot-frame">
        <div class="screenshot-header">
            <span>Module 3: Farmers Registry & Modal Profile (`farmers.php`)</span>
            <span class="badge bg-success">Operational</span>
        </div>
        <p class="small text-muted mb-2">
            Full CRUD registry of Cebu farmers with real-time multi-field search, pagination, detailed profile inspection modal displaying owned land parcels, and referential deletion protection.
        </p>
        <div class="code-block">
[ Data Table: #1 Juan Dela Cruz | Cebu City | Contact: 09171234567 | Registered: Oct 26, 2026 ]
[ Actions: View Landholdings Profile Modal | Edit Farmer Details | Protected Delete (RBAC Admin) ]
        </div>
    </div>

    <div class="screenshot-frame">
        <div class="screenshot-header">
            <span>Module 4: Farm Land Parcels & Trash Recovery (`farms.php`)</span>
            <span class="badge bg-success">Operational</span>
        </div>
        <p class="small text-muted mb-2">
            Land parcel management categorized by 3NF soil and irrigation classifications. Features two dedicated tabs: Active Farms and Recently Deleted bin (soft-delete with 1-click restore or permanent purge).
        </p>
        <div class="code-block">
[ Tab 1: Active Farms (Search by name/owner, filter by Soil Type and Irrigation Type) ]
[ Tab 2: Recently Deleted Bin (Displays deleted_at timestamp, Restore button, Permanent Purge) ]
        </div>
    </div>

    <div class="screenshot-frame">
        <div class="screenshot-header">
            <span>Module 5: Field User App & Yield Calculator (`app.php`)</span>
            <span class="badge bg-success">Operational</span>
        </div>
        <p class="small text-muted mb-2">
            Mobile-optimized PWA application for field officers. Features Crop Catalog & Seasonal Guide, interactive Yield & Profit Calculator, active planting countdown badges, and agro-climatic advisories.
        </p>
        <div class="code-block">
[ Calculator: Select Crop (e.g. Rice) + Area (2.5 ha) + Price (₱25/kg) => Computes 10,500 kg & ₱262,500 Gross ]
[ Tracker: Real-time countdown badges ("🌾 Harvest in 14 days", "⚡ Ready for Harvest Today!") ]
        </div>
    </div>

    <div class="screenshot-frame">
        <div class="screenshot-header">
            <span>Module 6: System Activity & Audit Logs (`logs.php`)</span>
            <span class="badge bg-success">Operational</span>
        </div>
        <p class="small text-muted mb-2">
            Immutable audit logging recording what was deleted, who logged in, what was created or updated, exact timestamps, IP addresses, and RFC-4180 CSV export.
        </p>
        <div class="code-block">
[ Audit Table: Event ID | Timestamp | Action Badge | Module | User Avatar & Handle | Description | IP ]
[ Actions: Multi-criteria filter by Action & Module | Date Range Filter | One-Click Export to CSV ]
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- 9. TEST CASES AND RESULTS                                          -->
    <!-- ================================================================== -->
    <div class="page-break"></div>
    <h2 class="doc-section-title">9. Test Cases and Verification Results</h2>

    <table class="table-doc">
        <thead>
            <tr>
                <th style="width: 65px;">Test ID</th>
                <th style="width: 85px;">Module</th>
                <th>Feature Tested</th>
                <th>Input / Action</th>
                <th>Expected Outcome</th>
                <th style="width: 75px;">Result</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>TC-01</strong></td>
                <td>Auth</td>
                <td>Admin Sign-in</td>
                <td>User: <code>admin</code>, Pass: <code>admin123</code></td>
                <td>Grants access, redirects to dashboard with Admin badge</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-02</strong></td>
                <td>Auth</td>
                <td>Staff Sign-in</td>
                <td>User: <code>staff</code>, Pass: <code>staff123</code></td>
                <td>Grants access, redirects to dashboard with Staff badge</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-03</strong></td>
                <td>Auth</td>
                <td>Invalid Password</td>
                <td>User: <code>admin</code>, Pass: <code>wrongpass</code></td>
                <td>Access denied, displays error alert, logs failed attempt</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-04</strong></td>
                <td>Auth</td>
                <td>RBAC Delete Guard</td>
                <td>Staff attempts to delete Farmer record</td>
                <td>Action blocked with message: "Only Admins can delete"</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-05</strong></td>
                <td>Farmers</td>
                <td>Create Farmer</td>
                <td>Name: "Carlos Ramos", Phone: "09281234567"</td>
                <td>Farmer record created and listed in data table</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-06</strong></td>
                <td>Farmers</td>
                <td>Referential Guard</td>
                <td>Delete Farmer #1 owning active Farm #1</td>
                <td>Deletion blocked preserving relational integrity</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-07</strong></td>
                <td>Farms</td>
                <td>Soft Delete & Trash</td>
                <td>Click Trash on Farm #3 ("Reyes Farm")</td>
                <td>Moves to Recently Deleted tab with timestamp recorded</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-08</strong></td>
                <td>Farms</td>
                <td>Restore Farm</td>
                <td>Click Restore on soft-deleted farm</td>
                <td>Restores farm to Active Farms tab; clears deleted_at</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-09</strong></td>
                <td>Crops</td>
                <td>Unique Constraint</td>
                <td>Insert duplicate `(Rice, NSIC Rc 222)`</td>
                <td>Blocked by database unique composite constraint</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-10</strong></td>
                <td>Plantings</td>
                <td>Auto Harvest Date</td>
                <td>Crop: "Rice" (115d) + Date: "2026-01-10"</td>
                <td>Auto-calculates expected harvest date to `2026-05-05`</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-11</strong></td>
                <td>Plantings</td>
                <td>Date Validation</td>
                <td>Harvest Date set prior to Planting Date</td>
                <td>Form rejected with logical date constraint error</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-12</strong></td>
                <td>Harvests</td>
                <td>Valuation Math</td>
                <td>Qty = 900.00 kg, Price = ₱22.00/kg</td>
                <td>Client dynamically computes Total Value: ₱19,800.00</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-13</strong></td>
                <td>Harvests</td>
                <td>3NF Controlled Remark</td>
                <td>Select "Good Harvest" in dropdown</td>
                <td>UI displays "Good Harvest", DB stores numeric FK `1`</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-14</strong></td>
                <td>Harvests</td>
                <td>Auto Status Transition</td>
                <td>Record harvest for "Growing" planting</td>
                <td>Planting status automatically updated to "Harvested"</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-15</strong></td>
                <td>Reports</td>
                <td>CSV Export</td>
                <td>Click "Export to CSV" button</td>
                <td>Downloads valid RFC-4180 compliant CSV spreadsheet</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
            <tr>
                <td><strong>TC-16</strong></td>
                <td>Audit</td>
                <td>Activity Tracking</td>
                <td>Perform create, update, or delete</td>
                <td>Event logged in `activity_logs` with user and timestamp</td>
                <td><span class="badge-doc badge-pass">PASS</span></td>
            </tr>
        </tbody>
    </table>

    <div class="mt-4 pt-3 border-top text-center text-muted small">
        AgriTrack Documentation · Cebu Technological University · AP3 Midterm Project Submission · Page Printed Automatically
    </div>

</div>

</body>
</html>
