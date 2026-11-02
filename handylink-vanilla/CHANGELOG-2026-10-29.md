# HandyLink changes — 29 October 2026

## Implemented in the PHP application

- `post.php` continues to save `schedule_type`, `scheduled_date`, and `preferred_time`; Today uses today's Harare date and requires a time, This week requires a date within the next seven calendar days, and Flexible stores the schedule type with no forced date or time.
- Job posting now stores the original budget minimum and maximum in addition to the existing label and estimated-price compatibility value. The `$50+` range has no maximum.
- The dashboard's example job cards were replaced with the six newest open, public job posts from MySQL. The query does not retrieve private addresses, and cards link to the correct job ID.
- Job detail now checks the logged-in account's active provider/both role. Existing provider-capable accounts missing a helper profile get one when expressing interest, so the action no longer depends on prior profile setup. Customer-only accounts get a link to change their role in Profile.
- New signups and profile role changes create the helper profile before creating a wallet. Dashboard and wallet balance lookups now join the wallet through `provider_profiles`, matching the existing `wallets.provider_id` relationship.
- Job detail no longer selects the exact address for ordinary viewers. The server fetches it only for the customer, or for the assigned helper after job confirmation. The customer can view their own address before confirmation.

## Database migration

`migrations/2026-10-29-job-agreement-workflow.sql` adds original budget bounds, provisional selection/expiration fields, immutable proposal versions, version-specific confirmations, offer-scoped messages, credit-transaction idempotency keys, and a one-profile-per-user constraint. It preserves the existing `job_applications` table as the private offer thread.

The migration was applied to the local `handymen's` database on 29 October 2026 after verifying the live table definitions. It backfilled budget bounds for the two existing jobs and created the missing helper profile and wallet for the existing `both` account that had none. It is a one-time migration and must not be run twice.

The offer/proposal/confirmation endpoints and UI still need to be implemented; the migration creates their storage foundation but does not pretend that negotiation or mutual confirmation is already functional. The credit cost is intentionally not set here because the handoff says its formula is undecided.

## Negotiation and customer deadline work — 1 November 2026

- Added `job-negotiation.php` for helper offers, customer offer comparison, private offer conversations, proposal revisions, provisional selection, ready-for-confirmation, and mutual confirmation. All prices are checked against the original job budget. Credits remain untouched per the user's instruction.
- Added responsive styling and a countdown display. Server-side expiry checks reopen paused offers; the browser timer is display-only.
- Added a required customer-selected confirmation window when posting a job: 1, 3, 6, 12, 24, 48 hours, or no deadline. The timer starts when a selected proposal is marked ready, and a new material proposal version gets a new timer from the saved job choice. Ordinary messages do not change it.
- Added in-app notifications for new offers, messages, proposals, selection, confirmation readiness, confirmations, and expiry.
- Added migration `migrations/2026-11-01-customer-confirmation-window.sql` for the saved deadline choice and per-proposal start/deadline timestamps. It was applied to the local `handymen's` database. Existing jobs were left with `NULL` deadlines (No deadline).
- Credit charging is not implemented in this phase. The existing idempotency field remains available for a later approved billing rule.
