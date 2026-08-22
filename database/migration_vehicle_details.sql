-- DVLA details retained with each booking.
-- The application also adds these columns lazily for existing installations.
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS vehicle_year SMALLINT UNSIGNED NULL AFTER vehicle_reg,
  ADD COLUMN IF NOT EXISTS vehicle_colour VARCHAR(40) NOT NULL DEFAULT '' AFTER vehicle_year,
  ADD COLUMN IF NOT EXISTS vehicle_fuel VARCHAR(40) NOT NULL DEFAULT '' AFTER vehicle_colour,
  ADD COLUMN IF NOT EXISTS vehicle_mot VARCHAR(40) NOT NULL DEFAULT '' AFTER vehicle_fuel;
