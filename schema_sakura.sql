-- さくらレンタルサーバー用
-- phpMyAdmin で対象DBを選択してからこのSQLを実行してください

CREATE TABLE IF NOT EXISTS applicants (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nickname     VARCHAR(50)     NOT NULL,
    email        VARCHAR(255)    NOT NULL DEFAULT '',
    submitted_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responses (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    applicant_id INT UNSIGNED    NOT NULL,
    question_id  INT             NOT NULL,
    answer       TEXT            NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_responses_applicant
        FOREIGN KEY (applicant_id) REFERENCES applicants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS results (
    applicant_id  INT UNSIGNED                             NOT NULL,
    status        ENUM('pending','approved','rejected')    NOT NULL DEFAULT 'pending',
    reject_reason VARCHAR(100)                             NULL,
    created_at    TIMESTAMP                                NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (applicant_id),
    CONSTRAINT fk_results_applicant
        FOREIGN KEY (applicant_id) REFERENCES applicants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
