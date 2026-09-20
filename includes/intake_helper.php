<?php
/**
 * Ensures the appointment_intake table exists.
 *
 * The table is normally created by running config/intake_migration.sql
 * once in phpMyAdmin. If that migration was never run, $conn->prepare()
 * against this table silently returns false (mysqli default error mode),
 * which previously caused a fatal "call to bind_param() on bool" error —
 * shown to the patient as a raw server error when submitting the
 * questionnaire. Calling this at the top of any endpoint that touches
 * appointment_intake makes the feature self-healing regardless of
 * whether the manual migration step was completed.
 */
function ensureIntakeTable(mysqli $conn): void {
    static $checked = false;
    if ($checked) return; // only check once per request

    $conn->query("
        CREATE TABLE IF NOT EXISTS appointment_intake (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL UNIQUE,
            patient_id INT NOT NULL,
            q1 TINYINT(1) COMMENT '1=New symptom, 2=Follow-up, 3=Medication review, 4=Routine',
            q2 TINYINT(1) COMMENT '1=<24h, 2=1-7 days, 3=1-4 weeks, 4=>1 month',
            q3 TINYINT(1) COMMENT '1=Mild, 2=Moderate, 3=Severe, 4=Very severe',
            q4 TINYINT(1) COMMENT '1=Getting worse, 2=Same, 3=Improving, 4=N/A',
            q5 TINYINT(1) COMMENT '1=Not at all, 2=Slightly, 3=Moderately, 4=Severely',
            q6 TINYINT(1) COMMENT '1=None, 2=1-2, 3=3-5, 4=>5',
            q7 TINYINT(1) COMMENT '1=None, 2=One, 3=Two-three, 4=>Three',
            q8 TINYINT(1) COMMENT '1=No, 2=Hospitalisation only, 3=Surgery only, 4=Both',
            q9 TINYINT(1) COMMENT '1=Chest pain, 2=Shortness of breath, 3=Severe pain, 4=None',
            q10 TINYINT(1) COMMENT '1=Never, 2=Several days, 3=>Half the days, 4=Nearly every day',
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $checked = true;
}
?>
