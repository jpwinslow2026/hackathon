CREATE DATABASE IF NOT EXISTS migration_scope
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE migration_scope;

CREATE TABLE assessments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(200) NOT NULL,
  opportunity_name VARCHAR(200) NULL,
  consultant_name VARCHAR(200) NULL,
  status ENUM('draft','review','complete') NOT NULL DEFAULT 'draft',
  selected_workloads JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE assessment_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assessment_id BIGINT UNSIGNED NOT NULL,
  question_id VARCHAR(150) NOT NULL,
  answer_text LONGTEXT NULL,
  source ENUM('customer','consultant','ai') NOT NULL DEFAULT 'consultant',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assessment_question (assessment_id, question_id),
  CONSTRAINT fk_answers_assessment FOREIGN KEY (assessment_id)
    REFERENCES assessments(id) ON DELETE CASCADE
);

CREATE TABLE assessment_findings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assessment_id BIGINT UNSIGNED NOT NULL,
  finding_type ENUM('fact','risk','assumption','dependency','exclusion','open_question') NOT NULL,
  finding_text TEXT NOT NULL,
  approved BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_findings_assessment FOREIGN KEY (assessment_id)
    REFERENCES assessments(id) ON DELETE CASCADE
);
