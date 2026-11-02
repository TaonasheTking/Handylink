-- HandyLink job budget, offer, proposal-version, and confirmation workflow.
-- Run after 2026-10-25-job-posting-fields.sql. Back up the database first.

-- Original customer budget stays separate from negotiated proposal prices.
ALTER TABLE jobs
  ADD COLUMN budget_min DECIMAL(10,2) NULL AFTER estimated_price,
  ADD COLUMN budget_max DECIMAL(10,2) NULL AFTER budget_min,
  ADD COLUMN selected_application_id INT NULL AFTER assigned_provider_id,
  ADD COLUMN selected_at DATETIME NULL AFTER selected_application_id,
  ADD COLUMN selection_expires_at DATETIME NULL AFTER selected_at,
  ADD COLUMN confirmed_proposal_id INT NULL AFTER selection_expires_at,
  ADD COLUMN confirmed_at DATETIME NULL AFTER confirmed_proposal_id,
  ADD KEY idx_jobs_selected_application (selected_application_id),
  ADD KEY idx_jobs_confirmed_proposal (confirmed_proposal_id);

UPDATE jobs
SET budget_min = COALESCE(budget_min, estimated_price),
    budget_max = CASE
      WHEN budget_max IS NOT NULL THEN budget_max
      WHEN estimated_price = 5.00 THEN 10.00
      WHEN estimated_price = 10.00 THEN 25.00
      WHEN estimated_price = 25.00 THEN 50.00
      WHEN estimated_price = 50.00 THEN NULL
      ELSE estimated_price
    END;

-- A helper gets one private offer thread per job. Keep legacy status values
-- while allowing the offer lifecycle described in the project handoff.
ALTER TABLE job_applications
  MODIFY COLUMN status ENUM(
    'pending','accepted','rejected','submitted','negotiating','selected',
    'paused','declined','withdrawn','expired','closed'
  ) NOT NULL DEFAULT 'submitted',
  ADD COLUMN selected_at DATETIME NULL,
  ADD COLUMN selection_expires_at DATETIME NULL,
  ADD KEY idx_job_applications_status (status);

-- Proposal rows are immutable snapshots: every term change creates a new version.
CREATE TABLE job_proposals (
  id INT NOT NULL AUTO_INCREMENT,
  job_id INT NOT NULL,
  application_id INT NOT NULL,
  version_number INT NOT NULL,
  proposed_by INT NOT NULL,
  proposed_price DECIMAL(10,2) NOT NULL,
  scope_description TEXT NOT NULL,
  scheduled_date DATE NULL,
  start_time TIME NULL,
  estimated_duration_minutes INT NULL,
  materials_arrangement TEXT NULL,
  additional_terms TEXT NULL,
  status ENUM('proposed','superseded','confirmed') NOT NULL DEFAULT 'proposed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_proposal_version (application_id, version_number),
  KEY idx_job_proposals_job_application (job_id, application_id),
  CONSTRAINT fk_job_proposals_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE,
  CONSTRAINT fk_job_proposals_application FOREIGN KEY (application_id) REFERENCES job_applications (id) ON DELETE CASCADE,
  CONSTRAINT fk_job_proposals_user FOREIGN KEY (proposed_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each party's confirmation points to one exact proposal version.
CREATE TABLE proposal_confirmations (
  id INT NOT NULL AUTO_INCREMENT,
  proposal_id INT NOT NULL,
  user_id INT NOT NULL,
  confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proposal_confirmation_user (proposal_id, user_id),
  CONSTRAINT fk_proposal_confirmations_proposal FOREIGN KEY (proposal_id) REFERENCES job_proposals (id) ON DELETE CASCADE,
  CONSTRAINT fk_proposal_confirmations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE job_applications
  ADD COLUMN current_proposal_id INT NULL,
  ADD KEY idx_job_applications_current_proposal (current_proposal_id),
  ADD CONSTRAINT fk_job_applications_current_proposal FOREIGN KEY (current_proposal_id) REFERENCES job_proposals (id) ON DELETE SET NULL;

-- Messages can be scoped to a private helper offer thread.
ALTER TABLE messages
  ADD COLUMN application_id INT NULL AFTER job_id,
  ADD KEY idx_messages_application (application_id),
  ADD CONSTRAINT fk_messages_application FOREIGN KEY (application_id) REFERENCES job_applications (id) ON DELETE CASCADE;

-- Idempotency key lets the eventual confirmation transaction safely retry a
-- credit deduction without charging twice. The exact credit cost remains configurable.
ALTER TABLE credit_transactions
  ADD COLUMN idempotency_key VARCHAR(100) NULL,
  ADD UNIQUE KEY uq_credit_transactions_idempotency (idempotency_key);

-- Backfill helper profiles omitted by older signup flows, then give every
-- helper profile a wallet. Wallet.provider_id references provider_profiles.id.
INSERT INTO provider_profiles (user_id, bio, business_name, is_business)
SELECT u.id, NULL, NULL, 0
FROM users u
LEFT JOIN provider_profiles pp ON pp.user_id = u.id
WHERE u.user_type IN ('provider','both') AND pp.id IS NULL;

INSERT INTO wallets (provider_id, credit_balance)
SELECT pp.id, 0
FROM provider_profiles pp
LEFT JOIN wallets w ON w.provider_id = pp.id
WHERE w.id IS NULL;

-- One helper profile per account makes profile lookup and offer ownership reliable.
ALTER TABLE provider_profiles
  ADD UNIQUE KEY uq_provider_profiles_user (user_id);

-- Add the selected/confirmed references after their target tables exist.
ALTER TABLE jobs
  ADD CONSTRAINT fk_jobs_selected_application FOREIGN KEY (selected_application_id) REFERENCES job_applications (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_jobs_confirmed_proposal FOREIGN KEY (confirmed_proposal_id) REFERENCES job_proposals (id) ON DELETE SET NULL;
