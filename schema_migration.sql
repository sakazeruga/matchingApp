-- applicants に email を追加
ALTER TABLE applicants
    ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER nickname;

-- results.status に approved を追加
ALTER TABLE results
    MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending';
