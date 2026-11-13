-- ========================================================================
-- CEBU TECHNOLOGICAL UNIVERSITY - AP3 MIDTERM PROJECT
-- Agriculture Information System (AgriTrack)
-- Seed Script: Populates Exact Document Demo Data (Crops, Farmers, Farms, Plantings, Harvests)
-- ========================================================================

begin;

-- ------------------------------------------------------------------------
-- 1. LOOKUP DATA
-- ------------------------------------------------------------------------
insert into public.crop_categories (category_id, category_name) values
    (1, 'Cereal'),
    (2, 'Vegetable'),
    (3, 'Fruit'),
    (4, 'Legume'),
    (5, 'Root Crop')
on conflict (category_id) do update set category_name = excluded.category_name;

insert into public.recommended_seasons (season_id, season_name) values
    (1, 'Wet Season'),
    (2, 'Dry Season'),
    (3, 'Year Round')
on conflict (season_id) do update set season_name = excluded.season_name;

insert into public.soil_types (soil_type_id, soil_type_name) values
    (1, 'Loam'),
    (2, 'Clay Loam'),
    (3, 'Sandy Loam'),
    (4, 'Clay')
on conflict (soil_type_id) do update set soil_type_name = excluded.soil_type_name;

insert into public.irrigation_types (irrigation_type_id, irrigation_type_name) values
    (1, 'Rainfed'),
    (2, 'Irrigated'),
    (3, 'Drip Irrigation')
on conflict (irrigation_type_id) do update set irrigation_type_name = excluded.irrigation_type_name;

insert into public.planting_statuses (status_id, status_name) values
    (1, 'Growing'),
    (2, 'Harvested'),
    (3, 'Cancelled'),
    (4, 'Planned')
on conflict (status_id) do update set status_name = excluded.status_name;

insert into public.harvest_quality_grades (quality_grade_id, grade_name) values
    (1, 'Grade A'),
    (2, 'Grade B'),
    (3, 'Grade C')
on conflict (quality_grade_id) do update set grade_name = excluded.grade_name;

insert into public.harvest_remarks (remark_id, remark_description) values
    (1, 'Good Harvest'),
    (2, 'Acceptable Quality'),
    (3, 'Low Yield'),
    (4, 'Poor Quality'),
    (5, 'Damaged Harvest')
on conflict (remark_id) do update set remark_description = excluded.remark_description;

-- ------------------------------------------------------------------------
-- 2. SYSTEM USERS (Admin and Staff with BCRYPT hashed passwords)
-- Admin: admin / admin123
-- Staff: staff / staff123
-- ------------------------------------------------------------------------
insert into public.users (user_id, username, email, password_hash, full_name, role, status) values
    (1, 'admin', 'admin@agritrack.com', '$2y$10$oBNwNiMOYG7ZuPNKH/0YsO8j3At3HBXYUQieUh2c6dtjUpuS4U4NC', 'System Administrator', 'admin', 'active'),
    (2, 'staff', 'staff@agritrack.com', '$2y$10$cpSvk9tv.WM9ZIvuKB/rTeoVT8bs7Bn0oXa946O2Wx7qLefqECfEq', 'Agricultural Field Officer', 'staff', 'active')
on conflict (user_id) do update set
    username = excluded.username,
    email = excluded.email,
    password_hash = excluded.password_hash,
    full_name = excluded.full_name,
    role = excluded.role,
    status = excluded.status;

-- ------------------------------------------------------------------------
-- 3. CROP DATA (Exact 10 Rows from Document)
-- ------------------------------------------------------------------------
insert into public.crop (crop_id, crop_name, variety, category_id, growing_period_days, season_id) values
    (1, 'Rice', 'NSIC Rc 222', 1, 115, 1),
    (2, 'Corn', 'Yellow Corn', 1, 100, 2),
    (3, 'Tomato', 'Diamante Max', 2, 90, 2),
    (4, 'Eggplant', 'Morena', 2, 100, 3),
    (5, 'Banana', 'Lakatan', 3, 300, 3),
    (6, 'Mango', 'Carabao', 3, 150, 2),
    (7, 'Peanut', 'BPI Pn 9', 4, 110, 2),
    (8, 'Cassava', 'Golden Yellow', 5, 240, 3),
    (9, 'Sweet Potato', 'NSIC Sp 30', 5, 120, 3),
    (10, 'Watermelon', 'Sweet 16', 3, 85, 2)
on conflict (crop_id) do update set
    crop_name = excluded.crop_name,
    variety = excluded.variety,
    category_id = excluded.category_id,
    growing_period_days = excluded.growing_period_days,
    season_id = excluded.season_id;

-- ------------------------------------------------------------------------
-- 4. FARMER DATA (Exact 15 Rows from Document)
-- ------------------------------------------------------------------------
insert into public.farmer (farmer_id, first_name, last_name, contact_number, address, date_registered) values
    (1, 'Juan', 'Dela Cruz', '09171234567', 'Cebu City', '2026-10-26'),
    (2, 'Maria', 'Santos', '09181234567', 'Carcar City, Cebu', '2026-10-26'),
    (3, 'Pedro', 'Reyes', '09191234567', 'Danao City, Cebu', '2026-10-26'),
    (4, 'Ana', 'Garcia', '09201234567', 'Argao, Cebu', '2026-10-26'),
    (5, 'Jose', 'Mendoza', '09211234567', 'Bogo City, Cebu', '2026-10-26'),
    (6, 'Ramon', 'Flores', '09221234567', 'Toledo City, Cebu', '2026-10-26'),
    (7, 'Elena', 'Cruz', '09231234567', 'Naga City, Cebu', '2026-10-26'),
    (8, 'Antonio', 'Lim', '09241234567', 'Minglanilla, Cebu', '2026-10-26'),
    (9, 'Rosa', 'Fernandez', '09251234567', 'Liloan, Cebu', '2026-10-26'),
    (10, 'Mario', 'Villanueva', '09261234567', 'Compostela, Cebu', '2026-10-26'),
    (11, 'Teresa', 'Navarro', '09271234567', 'Talisay City, Cebu', '2026-10-26'),
    (12, 'Carlos', 'Ramos', '09281234567', 'Consolacion, Cebu', '2026-10-26'),
    (13, 'Liza', 'Torres', '09291234567', 'Dalaguete, Cebu', '2026-10-26'),
    (14, 'Roberto', 'Castillo', '09301234567', 'Barili, Cebu', '2026-10-26'),
    (15, 'Susan', 'Aquino', '09311234567', 'Sibonga, Cebu', '2026-10-26')
on conflict (farmer_id) do update set
    first_name = excluded.first_name,
    last_name = excluded.last_name,
    contact_number = excluded.contact_number,
    address = excluded.address,
    date_registered = excluded.date_registered;

-- ------------------------------------------------------------------------
-- 5. FARM DATA (Exact 15 Rows from Document)
-- ------------------------------------------------------------------------
insert into public.farm (farm_id, farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id) values
    (1, 1, 'Dela Cruz Farm', 'Cebu City', 2.50, 1, 1),
    (2, 2, 'Santos Rice Farm', 'Carcar City, Cebu', 4.20, 2, 2),
    (3, 3, 'Reyes Agricultural Farm', 'Danao City, Cebu', 3.80, 3, 1),
    (4, 4, 'Garcia Vegetable Farm', 'Argao, Cebu', 1.75, 1, 3),
    (5, 5, 'Mendoza Farm', 'Bogo City, Cebu', 5.00, 4, 2),
    (6, 6, 'Flores Corn Farm', 'Toledo City, Cebu', 3.40, 3, 1),
    (7, 7, 'Cruz Family Farm', 'Naga City, Cebu', 2.80, 1, 2),
    (8, 8, 'Lim Agricultural Farm', 'Minglanilla, Cebu', 2.25, 2, 3),
    (9, 9, 'Fernandez Farm', 'Liloan, Cebu', 3.10, 3, 1),
    (10, 10, 'Villanueva Green Farm', 'Compostela, Cebu', 4.50, 1, 2),
    (11, 11, 'Navarro Rice Field', 'Talisay City, Cebu', 3.75, 4, 2),
    (12, 12, 'Ramos Farm', 'Consolacion, Cebu', 2.60, 1, 1),
    (13, 13, 'Torres Vegetable Farm', 'Dalaguete, Cebu', 1.80, 3, 3),
    (14, 14, 'Castillo Agricultural Farm', 'Barili, Cebu', 5.50, 2, 2),
    (15, 15, 'Aquino Farm', 'Sibonga, Cebu', 3.20, 1, 1)
on conflict (farm_id) do update set
    farmer_id = excluded.farmer_id,
    farm_name = excluded.farm_name,
    location = excluded.location,
    area_hectares = excluded.area_hectares,
    soil_type_id = excluded.soil_type_id,
    irrigation_type_id = excluded.irrigation_type_id;

-- ------------------------------------------------------------------------
-- 6. PLANTING DATA (Exact Rows 1 to 28 from Document)
-- ------------------------------------------------------------------------
insert into public.planting (planting_id, farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status_id) values
    (1, 1, 1, '2026-01-10', 0.50, 10.00, '2026-05-05', 2),
    (2, 2, 2, '2026-01-13', 0.60, 15.00, '2026-04-23', 2),
    (3, 3, 3, '2026-01-16', 0.70, 20.00, '2026-04-16', 2),
    (4, 4, 4, '2026-01-19', 0.80, 25.00, '2026-04-29', 2),
    (5, 5, 5, '2026-01-22', 0.90, 30.00, '2026-11-18', 2),
    (6, 6, 6, '2026-01-25', 1.00, 35.00, '2026-06-24', 2),
    (7, 7, 7, '2026-01-28', 1.10, 40.00, '2026-05-18', 2),
    (8, 8, 8, '2026-01-31', 1.20, 45.00, '2026-09-28', 2),
    (9, 9, 9, '2026-02-03', 1.30, 10.00, '2026-06-03', 2),
    (10, 10, 10, '2026-02-06', 1.40, 15.00, '2026-05-02', 2),
    (11, 11, 1, '2026-02-09', 0.50, 20.00, '2026-06-04', 2),
    (12, 12, 2, '2026-02-12', 0.60, 25.00, '2026-05-23', 2),
    (13, 13, 3, '2026-02-15', 0.70, 30.00, '2026-05-16', 2),
    (14, 14, 4, '2026-02-18', 0.80, 35.00, '2026-05-29', 2),
    (15, 15, 5, '2026-02-21', 0.90, 40.00, '2026-12-18', 2),
    (16, 1, 6, '2026-02-24', 1.00, 45.00, '2026-07-24', 2),
    (17, 2, 7, '2026-02-27', 1.10, 10.00, '2026-06-17', 2),
    (18, 3, 8, '2026-03-02', 1.20, 15.00, '2026-10-28', 2),
    (19, 4, 9, '2026-03-05', 1.30, 20.00, '2026-07-03', 2),
    (20, 5, 10, '2026-03-08', 1.40, 25.00, '2026-06-01', 2),
    (21, 6, 1, '2026-03-11', 0.50, 30.00, '2026-07-04', 1),
    (22, 7, 2, '2026-03-14', 0.60, 35.00, '2026-06-22', 1),
    (23, 8, 3, '2026-03-17', 0.70, 40.00, '2026-06-15', 1),
    (24, 9, 4, '2026-03-20', 0.80, 45.00, '2026-06-28', 1),
    (25, 10, 5, '2026-03-23', 0.90, 10.00, '2027-01-17', 1),
    (26, 11, 6, '2026-03-26', 1.00, 15.00, '2026-08-23', 1),
    (27, 12, 7, '2026-03-29', 1.10, 20.00, '2026-07-17', 1),
    (28, 13, 8, '2026-04-01', 1.20, 25.00, '2026-11-27', 1)
on conflict (planting_id) do update set
    farm_id = excluded.farm_id,
    crop_id = excluded.crop_id,
    planting_date = excluded.planting_date,
    area_planted = excluded.area_planted,
    seed_quantity = excluded.seed_quantity,
    expected_harvest_date = excluded.expected_harvest_date,
    status_id = excluded.status_id;

-- ------------------------------------------------------------------------
-- 7. HARVEST DATA (Exact Rows 1 to 20 from Document)
-- ------------------------------------------------------------------------
insert into public.harvest (harvest_id, planting_id, harvest_date, quantity_kg, selling_price_per_kg, quality_grade_id, remark_id, remarks_note) values
    (1, 1, '2026-05-07', 900.00, 22.00, 1, 1, 'High quality harvest'),
    (2, 2, '2026-04-25', 1025.00, 25.50, 2, 1, 'Good harvest'),
    (3, 3, '2026-04-18', 1150.00, 29.00, 3, 2, 'Acceptable quality'),
    (4, 4, '2026-05-01', 1275.00, 32.50, 1, 1, 'High quality harvest'),
    (5, 5, '2026-11-20', 1400.00, 36.00, 2, 1, 'Good harvest'),
    (6, 6, '2026-06-26', 1525.00, 39.50, 3, 2, 'Acceptable quality'),
    (7, 7, '2026-05-20', 1650.00, 43.00, 1, 1, 'High quality harvest'),
    (8, 8, '2026-09-30', 1775.00, 46.50, 2, 1, 'Good harvest'),
    (9, 9, '2026-06-05', 1900.00, 22.00, 3, 2, 'Acceptable quality'),
    (10, 10, '2026-05-04', 2025.00, 25.50, 1, 1, 'High quality harvest'),
    (11, 11, '2026-06-06', 2150.00, 29.00, 2, 1, 'Good harvest'),
    (12, 12, '2026-05-25', 2275.00, 32.50, 3, 2, 'Acceptable quality'),
    (13, 13, '2026-05-18', 2400.00, 36.00, 1, 1, 'High quality harvest'),
    (14, 14, '2026-05-31', 2525.00, 39.50, 2, 1, 'Good harvest'),
    (15, 15, '2026-12-20', 2650.00, 43.00, 3, 2, 'Acceptable quality'),
    (16, 16, '2026-07-26', 2775.00, 46.50, 1, 1, 'High quality harvest'),
    (17, 17, '2026-06-19', 2900.00, 22.00, 2, 1, 'Good harvest'),
    (18, 18, '2026-10-30', 3025.00, 25.50, 3, 2, 'Acceptable quality'),
    (19, 19, '2026-07-05', 3150.00, 29.00, 1, 1, 'High quality harvest'),
    (20, 20, '2026-06-03', 3275.00, 32.50, 2, 1, 'Good harvest')
on conflict (harvest_id) do update set
    planting_id = excluded.planting_id,
    harvest_date = excluded.harvest_date,
    quantity_kg = excluded.quantity_kg,
    selling_price_per_kg = excluded.selling_price_per_kg,
    quality_grade_id = excluded.quality_grade_id,
    remark_id = excluded.remark_id,
    remarks_note = excluded.remarks_note;

-- ------------------------------------------------------------------------
-- 8. RESET SEQUENCES FOR FUTURE AUTO-INCREMENTS
-- ------------------------------------------------------------------------
select setval(pg_get_serial_sequence('public.crop_categories', 'category_id'), coalesce((select max(category_id) from public.crop_categories), 1), true);
select setval(pg_get_serial_sequence('public.recommended_seasons', 'season_id'), coalesce((select max(season_id) from public.recommended_seasons), 1), true);
select setval(pg_get_serial_sequence('public.soil_types', 'soil_type_id'), coalesce((select max(soil_type_id) from public.soil_types), 1), true);
select setval(pg_get_serial_sequence('public.irrigation_types', 'irrigation_type_id'), coalesce((select max(irrigation_type_id) from public.irrigation_types), 1), true);
select setval(pg_get_serial_sequence('public.planting_statuses', 'status_id'), coalesce((select max(status_id) from public.planting_statuses), 1), true);
select setval(pg_get_serial_sequence('public.harvest_quality_grades', 'quality_grade_id'), coalesce((select max(quality_grade_id) from public.harvest_quality_grades), 1), true);
select setval(pg_get_serial_sequence('public.harvest_remarks', 'remark_id'), coalesce((select max(remark_id) from public.harvest_remarks), 1), true);
select setval(pg_get_serial_sequence('public.users', 'user_id'), coalesce((select max(user_id) from public.users), 1), true);
select setval(pg_get_serial_sequence('public.crop', 'crop_id'), coalesce((select max(crop_id) from public.crop), 1), true);
select setval(pg_get_serial_sequence('public.farmer', 'farmer_id'), coalesce((select max(farmer_id) from public.farmer), 1), true);
select setval(pg_get_serial_sequence('public.farm', 'farm_id'), coalesce((select max(farm_id) from public.farm), 1), true);
select setval(pg_get_serial_sequence('public.planting', 'planting_id'), coalesce((select max(planting_id) from public.planting), 1), true);
select setval(pg_get_serial_sequence('public.harvest', 'harvest_id'), coalesce((select max(harvest_id) from public.harvest), 1), true);

commit;
