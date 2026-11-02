-- Apply once to the existing HandyLink database before posting jobs.
ALTER TABLE jobs
  ADD COLUMN private_address VARCHAR(255) NULL AFTER location_address,
  ADD COLUMN budget_label VARCHAR(25) NULL AFTER estimated_price,
  ADD COLUMN schedule_type ENUM('flexible', 'today', 'this_week') NOT NULL DEFAULT 'flexible' AFTER budget_label,
  ADD COLUMN scheduled_date DATE NULL AFTER schedule_type,
  ADD COLUMN preferred_time TIME NULL AFTER scheduled_date;