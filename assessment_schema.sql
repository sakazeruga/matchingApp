-- ────────────────────────────────────────────────────────
-- アセスメント DB スキーマ
-- ────────────────────────────────────────────────────────

-- 設問マスタ
CREATE TABLE IF NOT EXISTS assessment_questions (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    assessment_type ENUM('love_score','cute_score') NOT NULL,
    category_code   VARCHAR(4)      NOT NULL COMMENT 'A/B/C/D/E/K/L/M/N or F/G/H/I/J/PQ/OR',
    sub_item_code   VARCHAR(8)      NOT NULL COMMENT 'A-1, K-3 等',
    question_text   TEXT            NOT NULL,
    question_type   ENUM('likert','reverse_likert','scenario') NOT NULL DEFAULT 'likert',
    options         JSON            NULL     COMMENT 'scenario型の場合: [{"label":"..","score":N}]',
    is_flag_trigger TINYINT(1)      NOT NULL DEFAULT 0,
    flag_threshold  TINYINT         NULL     COMMENT 'この値以下でフラグ発動（1-5スケール）',
    display_order   SMALLINT        NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_type_category (assessment_type, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- アセスメントセッション
CREATE TABLE IF NOT EXISTS assessment_sessions (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    applicant_id    INT UNSIGNED    NOT NULL,
    assessment_type ENUM('love_score','cute_score') NOT NULL,
    status          ENUM('in_progress','completed','flagged') NOT NULL DEFAULT 'in_progress',
    started_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP       NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_asess_applicant
        FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    INDEX idx_applicant_type (applicant_id, assessment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 回答
CREATE TABLE IF NOT EXISTS assessment_responses (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    session_id  INT UNSIGNED    NOT NULL,
    question_id INT UNSIGNED    NOT NULL,
    score       TINYINT         NOT NULL COMMENT '1-5',
    answered_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_session_question (session_id, question_id),
    CONSTRAINT fk_aresp_session
        FOREIGN KEY (session_id) REFERENCES assessment_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_aresp_question
        FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- スコア（計算結果）
CREATE TABLE IF NOT EXISTS assessment_scores (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    session_id      INT UNSIGNED    NOT NULL,
    applicant_id    INT UNSIGNED    NOT NULL,
    assessment_type ENUM('love_score','cute_score') NOT NULL,
    category_code   VARCHAR(4)      NOT NULL,
    category_score  DECIMAL(5,2)    NOT NULL COMMENT '0-100',
    total_score     DECIMAL(5,2)    NOT NULL COMMENT '0-100',
    has_warning     TINYINT(1)      NOT NULL DEFAULT 0,
    warning_details JSON            NULL,
    calculated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ascore_session (session_id),
    INDEX idx_ascore_applicant (applicant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ピアレビュー（将来実装用）
CREATE TABLE IF NOT EXISTS peer_reviews (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    target_id       INT UNSIGNED    NOT NULL COMMENT '評価される applicant_id',
    reviewer_token  VARCHAR(64)     NOT NULL COMMENT 'URLトークン（匿名性保護）',
    category_code   VARCHAR(4)      NOT NULL,
    score           TINYINT         NOT NULL COMMENT '1-5',
    comment         TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pr_target (target_id),
    UNIQUE KEY uq_reviewer_category (reviewer_token, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
