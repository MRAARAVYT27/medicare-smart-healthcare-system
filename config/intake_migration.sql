-- ============================================================
-- Pre-Visit Intake Questionnaire — Migration
-- Run this in phpMyAdmin or MySQL CLI AFTER schema.sql
-- ============================================================

USE healthcare_db;

CREATE TABLE IF NOT EXISTS appointment_intake (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    patient_id INT NOT NULL,

    -- Q1: Primary reason for visit
    q1 TINYINT(1) COMMENT '1=New symptom, 2=Follow-up, 3=Medication review, 4=Routine',
    -- Q2: Duration of main concern
    q2 TINYINT(1) COMMENT '1=<24h, 2=1-7 days, 3=1-4 weeks, 4=>1 month',
    -- Q3: Severity of main concern
    q3 TINYINT(1) COMMENT '1=Mild, 2=Moderate, 3=Severe, 4=Very severe',
    -- Q4: Symptom trend
    q4 TINYINT(1) COMMENT '1=Getting worse, 2=Same, 3=Improving, 4=N/A',
    -- Q5: Impact on daily life
    q5 TINYINT(1) COMMENT '1=Not at all, 2=Slightly, 3=Moderately, 4=Severely',
    -- Q6: Number of current medications
    q6 TINYINT(1) COMMENT '1=None, 2=1-2, 3=3-5, 4=>5',
    -- Q7: Chronic conditions
    q7 TINYINT(1) COMMENT '1=None, 2=One, 3=Two-three, 4=>Three',
    -- Q8: Hospitalisation/surgery past year
    q8 TINYINT(1) COMMENT '1=No, 2=Hospitalisation only, 3=Surgery only, 4=Both',
    -- Q9: Urgent symptoms present
    q9 TINYINT(1) COMMENT '1=Chest pain, 2=Shortness of breath, 3=Severe pain, 4=None',
    -- Q10: Mental health (PHQ-2 proxy)
    q10 TINYINT(1) COMMENT '1=Never, 2=Several days, 3=>Half the days, 4=Nearly every day',

    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);
