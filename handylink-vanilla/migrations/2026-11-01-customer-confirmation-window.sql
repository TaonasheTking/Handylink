-- Customer-selected confirmation windows for HandyLink.
-- Existing jobs are assigned NULL (No deadline); no current selection is expired.
ALTER TABLE jobs
  ADD COLUMN confirmation_window_hours SMALLINT NULL AFTER budget_max,
  ADD CONSTRAINT chk_jobs_confirmation_window
    CHECK (confirmation_window_hours IS NULL OR confirmation_window_hours IN (1,3,6,12,24,48));

ALTER TABLE job_proposals
  MODIFY COLUMN status ENUM('proposed','ready','superseded','confirmed') NOT NULL DEFAULT 'proposed',
  ADD COLUMN confirmation_started_at DATETIME NULL AFTER status,
  ADD COLUMN confirmation_deadline_at DATETIME NULL AFTER confirmation_started_at;
