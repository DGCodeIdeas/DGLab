-- ============================================================================
-- DGLab Migration Runner — Metadata Schema
-- Controlling design: Z.ai Focused Revision 5.3
-- Storage engine: InnoDB (REQUIRED — the gseq protocol, gate S/X, and crash
--   release semantics all depend on InnoDB row-level locking and transactions).
-- Character set: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. migration_gseq_counter — global monotonic event sequence
--    Rev 5 §3.2 / Rev 5.3 §2.2: single row, id=1. The UPDATE ... SET c =
--    LAST_INSERT_ID(c+1) acquires a row lock held through COMMIT, serializing
--    gseq allocation with commit order (= causal order).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_gseq_counter` (
    `id` INT NOT NULL,
    `c` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize the single row if it does not exist. Safe under concurrent
-- startup: INSERT IGNORE does not reset an existing sequence.
INSERT IGNORE INTO `migration_gseq_counter` (`id`, `c`) VALUES (1, 0);

-- ----------------------------------------------------------------------------
-- 2. migration_gate — InnoDB shared/exclusive gate (Rev 5.1 §2, Rev 5.3 §2)
--    One row per target schema. Per-migration ops hold FOR SHARE; global
--    reconciliation holds FOR UPDATE. The row is never UPDATEd or DELETEd
--    by the runner; it exists only as a lock target.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_gate` (
    `schema_hash` CHAR(8) NOT NULL,
    `schema_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`schema_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. migration — discovered migration definitions (Rev 4 §6, Rev 5 §6.1)
--    Records the linear chain: each migration declares exactly one predecessor
--    (or NULL for genesis). The runner validates at startup that the set forms
--    a single linear chain rooted at one genesis migration.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration` (
    `migration_id` VARCHAR(255) NOT NULL,
    `predecessor_migration_id` VARCHAR(255) NULL,
    `chain_position` INT NOT NULL,
    `description` TEXT NULL,
    `discovered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`migration_id`),
    INDEX `idx_migration_chain_position` (`chain_position`),
    INDEX `idx_migration_predecessor` (`predecessor_migration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. migration_attempt — attempt records (Rev 4 §5.1, Rev 5 §6 schema delta)
--    direction ∈ {upgrade, downgrade}. A rollback is a real executed attempt
--    against the migration's downgrade artifact.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_attempt` (
    `attempt_id` BIGINT NOT NULL AUTO_INCREMENT,
    `migration_id` VARCHAR(255) NOT NULL,
    `direction` ENUM('upgrade','downgrade') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attempt_id`),
    INDEX `idx_attempt_migration` (`migration_id`, `attempt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. migration_attempt_event — append-only event log (Rev 4 §3, Rev 5 §6)
--    gseq: global monotonic sequence (Rev 5 §3.2 atomic protocol).
--    INSERT-only: the runner account has no UPDATE/DELETE grant on this table
--    (Rev 3 §8.1). BEFORE UPDATE/DELETE triggers enforce this as defense-in-depth.
--    Event types (Rev 4 §3.1 + Rev 5 §3.1):
--      Establishing: execution_succeeded, execution_failed_dispatched,
--                    manual_completion, manual_cleanup, baseline_adopted, suspend
--      Audit-only:   auto_reconciled, auto_diverged, auto_quarantined_integrity,
--                    degraded_integrity_mode
--      Lifecycle:    prepared, dispatch_started
--      Intent:       statement_dispatched
--      Result:       statement_succeeded, statement_failed
--      Terminal:     execution_failed_pre_exec, attempt_superseded
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_attempt_event` (
    `event_id` BIGINT NOT NULL AUTO_INCREMENT,
    `gseq` BIGINT NOT NULL,
    `attempt_id` BIGINT NOT NULL,
    `event_type` VARCHAR(64) NOT NULL,
    `statement_index` INT NULL,
    `recorded_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `payload` JSON NULL,
    PRIMARY KEY (`event_id`),
    UNIQUE KEY `uq_event_gseq` (`gseq`),
    INDEX `idx_event_attempt` (`attempt_id`, `event_id`),
    INDEX `idx_event_type` (`event_type`),
    CONSTRAINT `fk_event_attempt` FOREIGN KEY (`attempt_id`)
        REFERENCES `migration_attempt` (`attempt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only enforcement trigger (defense-in-depth; Rev 3 §8.2)
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `tr_event_no_update`
BEFORE UPDATE ON `migration_attempt_event`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'migration_attempt_event is append-only';
END//

CREATE TRIGGER IF NOT EXISTS `tr_event_no_delete`
BEFORE DELETE ON `migration_attempt_event`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'migration_attempt_event is append-only';
END//
DELIMITER ;

-- ----------------------------------------------------------------------------
-- 6. operator_event — operator/recovery decisions (Rev 4 §3.1, Rev 5 §6)
--    gseq: global monotonic sequence (same counter as attempt events).
--    INSERT-only: same append-only discipline as attempt events.
--    The uniqueness index on (migration_id, decision_type, establishing_ref)
--    makes reconciliation-inserted audit events idempotent (Rev 4 §2.5).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `operator_event` (
    `operator_event_id` BIGINT NOT NULL AUTO_INCREMENT,
    `gseq` BIGINT NOT NULL,
    `migration_id` VARCHAR(255) NOT NULL,
    `decision_type` VARCHAR(64) NOT NULL,
    `establishing_ref` VARCHAR(255) NULL,
    `recorded_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `payload` JSON NULL,
    PRIMARY KEY (`operator_event_id`),
    UNIQUE KEY `uq_operator_gseq` (`gseq`),
    UNIQUE KEY `uq_operator_reconcile` (`migration_id`, `decision_type`, `establishing_ref`),
    INDEX `idx_operator_migration` (`migration_id`, `operator_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE TRIGGER IF NOT EXISTS `tr_operator_no_update`
BEFORE UPDATE ON `operator_event`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operator_event is append-only';
END//

CREATE TRIGGER IF NOT EXISTS `tr_operator_no_delete`
BEFORE DELETE ON `operator_event`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operator_event is append-only';
END//
DELIMITER ;

-- ----------------------------------------------------------------------------
-- 7. migration_state — per-migration current outcome (Rev 4 §4.1, Rev 5 §6)
--    established_by_type ∈ {attempt_event, operator_event, NULL}
--    established_by_id → PK of the establishing record in the named table
--    established_by_gseq → gseq of the establishing record (Rev 4 §8 delta)
--    last_attempt_id → nullable traceability (NOT the justifying record)
--    current_state ∈ {pending, applied, unapplied, diverged, quarantined}
--    This is the ONLY mutable runner table (INSERT + UPDATE; no DELETE).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_state` (
    `migration_id` VARCHAR(255) NOT NULL,
    `current_state` ENUM('pending','applied','unapplied','diverged','quarantined') NOT NULL DEFAULT 'pending',
    `established_by_type` ENUM('attempt_event','operator_event') NULL,
    `established_by_id` BIGINT NULL,
    `established_by_gseq` BIGINT NULL,
    `last_attempt_id` BIGINT NULL,
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`migration_id`),
    INDEX `idx_state_established` (`established_by_type`, `established_by_id`),
    INDEX `idx_state_current` (`current_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. migration_current_level — per-schema current migration level (Rev 4 §5.1)
--    The highest migration in chain order such that it and all its ancestors
--    are applied. Recomputed deterministically after every terminal transition
--    and after every reconciliation pass. Never set by hand.
--    Protected by the dedicated level lock (advisory: dglab.mig.level.<hash>)
--    during recomputation (Rev 5.3 §2.2).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_current_level` (
    `schema_hash` CHAR(8) NOT NULL,
    `current_level_migration_id` VARCHAR(255) NULL,
    `recomputed_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`schema_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Required privileges for the runner account (Rev 3 §8.1, Rev 5 §4):
--   migration_gseq_counter:      SELECT, UPDATE (counter increment)
--   migration_gate:              SELECT, INSERT (bootstrap row), UPDATE (FOR SHARE/FOR UPDATE)
--   migration:                   SELECT, INSERT, UPDATE (discovery records)
--   migration_attempt:           SELECT, INSERT
--   migration_attempt_event:     SELECT, INSERT only (no UPDATE/DELETE)
--   operator_event:              SELECT, INSERT only (no UPDATE/DELETE)
--   migration_state:             SELECT, INSERT, UPDATE (no DELETE)
--   migration_current_level:     SELECT, INSERT, UPDATE
-- At startup the runner executes SHOW GRANTS and refuses to start (or logs
-- degraded_integrity_mode) if privileges exceed the contract.
-- ============================================================================
