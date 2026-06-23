-- 雨雲はれる 男性入会審査フォーム DB スキーマ
CREATE DATABASE IF NOT EXISTS amagumo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amagumo;

CREATE TABLE applicants (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nickname     VARCHAR(50)     NOT NULL,
    submitted_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE responses (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    applicant_id INT UNSIGNED    NOT NULL,
    question_id  INT             NOT NULL,
    answer       TEXT            NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_responses_applicant
        FOREIGN KEY (applicant_id) REFERENCES applicants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE results (
    applicant_id  INT UNSIGNED                    NOT NULL,
    status        ENUM('pending','rejected')      NOT NULL DEFAULT 'pending',
    reject_reason VARCHAR(100)                    NULL,
    created_at    TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (applicant_id),
    CONSTRAINT fk_results_applicant
        FOREIGN KEY (applicant_id) REFERENCES applicants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
