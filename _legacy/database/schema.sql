-- ============================================================================
-- e-RESERVE — schema
-- SIGLA: A Student's Night 2026 · BatStateU TNEU ARASOF-Nasugbu
--
-- Entry model: a student registers with their GSuite account, uploads three
-- PDF documents (signed waiver, COR, school ID) and is issued a ticket number.
-- The QR code on that ticket is what gets scanned at the gate.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Accounts
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190)    NOT NULL,
    password_hash   VARCHAR(255)    NULL,
    role            ENUM('student','admin') NOT NULL DEFAULT 'student',
    name            VARCHAR(160)    NOT NULL,
    initials        VARCHAR(4)      NOT NULL DEFAULT '',
    student_no      VARCHAR(32)     NULL,
    college_code    VARCHAR(16)     NULL,
    program         VARCHAR(160)    NULL,
    year_level      VARCHAR(32)     NULL,
    mobile          VARCHAR(32)     NULL,
    status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    -- Email confirmation. NULL here means the address has not been proved to
    -- belong to the person who registered, and the account cannot upload.
    email_verified_at DATETIME      NULL,
    verify_token_hash CHAR(64)      NULL,
    verify_expires_at DATETIME      NULL,
    verify_sent_at    DATETIME      NULL,
    last_login_at   DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_student_no (student_no),
    KEY ix_users_role (role),
    KEY ix_users_verify (verify_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Enrolled students — the registrar's masterlist, A.Y. 2026-2027 1st semester
--
-- Sign-up checks the SR-code against this table, so only students actually
-- enrolled at ARASOF-Nasugbu can create an account. The data is loaded from
-- database/enrolled_students.sql. This table is NOT dropped on re-install:
-- the masterlist is reference data, not demo data.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrolled_students (
    sr_code         VARCHAR(10)     NOT NULL,
    full_name       VARCHAR(160)    NOT NULL,
    college         VARCHAR(16)     NOT NULL,
    program         VARCHAR(160)    NOT NULL,
    major           VARCHAR(120)    NULL,
    year_level      VARCHAR(16)     NOT NULL,
    PRIMARY KEY (sr_code),
    KEY ix_enrolled_college (college),
    KEY ix_enrolled_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Documents — the three PDFs every student must submit
--
-- UNIQUE (user_id, doc_type): a student holds exactly one file per requirement.
-- Replacing a file overwrites the row, so the reviewer only ever sees the
-- latest upload.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS documents;
CREATE TABLE documents (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    doc_type        ENUM('waiver','cor','id') NOT NULL,
    original_name   VARCHAR(190)    NOT NULL,
    stored_name     VARCHAR(128)    NOT NULL,
    size_bytes      INT UNSIGNED    NOT NULL,
    status          ENUM('submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
    reviewed_by     INT UNSIGNED    NULL,
    reviewed_at     DATETIME        NULL,
    review_note     VARCHAR(255)    NULL,
    uploaded_ip     VARCHAR(45)     NULL,
    uploaded_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_user_type (user_id, doc_type),
    UNIQUE KEY uq_documents_stored (stored_name),
    KEY ix_documents_status (status),
    CONSTRAINT fk_documents_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tickets — one per student, issued when all three documents are in
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS tickets;
CREATE TABLE tickets (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ticket_no       VARCHAR(32)   NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    -- Signed into the QR payload so a fabricated ticket number fails at the gate.
    verify_hash     CHAR(64)      NOT NULL,
    status          ENUM('valid','used','void') NOT NULL DEFAULT 'valid',
    issued_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    voided_at       DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_no (ticket_no),
    UNIQUE KEY uq_tickets_user (user_id),
    KEY ix_tickets_status (status),
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Gate check-ins — UNIQUE ticket_id makes a second scan impossible
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS checkins;
CREATE TABLE checkins (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ticket_id   INT UNSIGNED  NOT NULL,
    gate        VARCHAR(32)   NOT NULL,
    scanner     VARCHAR(64)   NOT NULL,
    scanned_by  INT UNSIGNED  NULL,
    scanned_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkins_ticket (ticket_id),
    CONSTRAINT fk_checkins_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_checkins_staff  FOREIGN KEY (scanned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Audit trail — append only
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS audit_log;
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id    INT UNSIGNED    NULL,
    actor_name  VARCHAR(160)    NOT NULL,
    actor_role  VARCHAR(32)     NOT NULL,
    action      VARCHAR(64)     NOT NULL,
    entity_type VARCHAR(32)     NOT NULL,
    entity_id   VARCHAR(64)     NULL,
    context     JSON            NULL,
    ip_address  VARCHAR(45)     NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_audit_entity (entity_type, entity_id),
    KEY ix_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Document sequences (ticket numbers)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS sequences;
CREATE TABLE sequences (
    seq_key       VARCHAR(32)  NOT NULL,
    current_value INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (seq_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tables from the earlier payment-based build. Dropped last so a reinstall over
-- an old database leaves nothing behind.
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS ticket_tiers;
DROP TABLE IF EXISTS ledger_entries;

SET FOREIGN_KEY_CHECKS = 1;
