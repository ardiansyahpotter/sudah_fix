<?php
require_once __DIR__ . '/config.php';

function db_connect() {
    static $pdo;
    if ($pdo) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]
        );

        initialize_database($pdo);
        $pdo->exec('USE `' . DB_NAME . '`');
        return $pdo;
    } catch (PDOException $e) {
        die('Database connection gagal: ' . $e->getMessage());
    }
}

function initialize_database($pdo) {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    ensure_table($pdo, 'doctors', 'CREATE TABLE IF NOT EXISTS doctors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            specialty VARCHAR(100) NOT NULL,
            phone VARCHAR(25) NOT NULL,
            description TEXT,
            education TEXT,
            symptoms TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    ensure_table($pdo, 'schedules', 'CREATE TABLE IF NOT EXISTS schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doctor_id INT NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            slots INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    ensure_table($pdo, 'patients', 'CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(25) NOT NULL,
            doctor_id INT NOT NULL,
            schedule_id INT NOT NULL,
                patient_profile_id INT NULL,
                queue_number INT NULL,
            status VARCHAR(50) NOT NULL DEFAULT "Reservasi",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_patients_schedule_queue (schedule_id, queue_number),
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            ensure_column($pdo, 'patients', 'patient_profile_id', 'INT NULL AFTER schedule_id');
            ensure_column($pdo, 'patients', 'queue_number', 'INT NULL AFTER patient_profile_id');

    ensure_table($pdo, 'patient_profiles', 'CREATE TABLE IF NOT EXISTS patient_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(30) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(25) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    ensure_table($pdo, 'medical_records', 'CREATE TABLE IF NOT EXISTS medical_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            doctor_id INT NULL,
            visit_date DATE NOT NULL,
            visit_time TIME NOT NULL,
            queue_number INT NULL,
            cupping_type VARCHAR(30) NOT NULL,
            treatment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_medical_records_patient_date (patient_id, visit_date),
            FOREIGN KEY (patient_id) REFERENCES patient_profiles(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    ensure_column($pdo, 'medical_records', 'queue_number', 'INT NULL AFTER visit_time');
    $pdo->exec('UPDATE medical_records mr
                INNER JOIN patients p ON p.patient_profile_id = mr.patient_id AND p.doctor_id = mr.doctor_id
                INNER JOIN schedules s ON s.id = p.schedule_id AND s.date = mr.visit_date AND s.time = mr.visit_time
                SET mr.queue_number = p.queue_number
                WHERE mr.queue_number IS NULL AND p.queue_number IS NOT NULL');

    ensure_table($pdo, 'doctor_holidays', 'CREATE TABLE IF NOT EXISTS doctor_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doctor_id INT NOT NULL,
            holiday_date DATE NOT NULL,
            note VARCHAR(255) NOT NULL DEFAULT "Libur",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_doctor_date (doctor_id, holiday_date),
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    ensure_table($pdo, 'site_content', 'CREATE TABLE IF NOT EXISTS site_content (
            content_key VARCHAR(100) PRIMARY KEY,
            content_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec("UPDATE site_content SET content_value = 'Lihat slot yang tersedia dan estimasi antrean. Nomor antrean resmi ditentukan oleh dokter atau admin.' WHERE content_key = 'hero_text' AND content_value = 'Lihat jadwal dokter untuk hari ini, cek ketersediaan slot, dan langsung kirim permintaan reservasi ke admin lewat WhatsApp.'");

    ensure_table($pdo, 'admin_settings', 'CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    ensure_table($pdo, 'users', 'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            doctor_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $defaultDoctors = $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    if ($defaultDoctors == 0) {
        $insertDoctor = $pdo->prepare('INSERT INTO doctors (name, specialty, phone) VALUES (?, ?, ?)');
        $insertDoctor->execute(['Dr. Siti Aminah', 'Umum', '081234567890']);
        $insertDoctor->execute(['Dr. Budi Santoso', 'Anak', '081298765432']);
    }

    $defaultSchedules = $pdo->query('SELECT COUNT(*) FROM schedules')->fetchColumn();
    if ($defaultSchedules == 0) {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $insertSchedule = $pdo->prepare('INSERT INTO schedules (doctor_id, date, time, slots) VALUES (?, ?, ?, ?)');
        $insertSchedule->execute([1, $today, '09:00:00', 4]);
        $insertSchedule->execute([1, $today, '14:00:00', 4]);
        $insertSchedule->execute([2, $today, '10:00:00', 3]);
        $insertSchedule->execute([2, $tomorrow, '11:00:00', 3]);
    }

    $defaultContent = $pdo->query('SELECT COUNT(*) FROM site_content')->fetchColumn();
    if ($defaultContent == 0) {
        $insertContent = $pdo->prepare('INSERT INTO site_content (content_key, content_value) VALUES (?, ?)');
        $insertContent->execute(['site_title', 'Sistem Klinik Sederhana']);
        $insertContent->execute(['hero_eyebrow', 'Selamat Datang di Klinik Sehat']);
        $insertContent->execute(['hero_title', 'Reservasi Janji Temu Dokter dengan Mudah']);
        $insertContent->execute(['hero_text', 'Lihat slot yang tersedia dan estimasi antrean. Nomor antrean resmi ditentukan oleh dokter atau admin.']);
        $insertContent->execute(['hero_cta_text', 'Lihat Jadwal']);
        $insertContent->execute(['footer_text', 'Untuk reservasi, klik tombol WhatsApp dan kirim pesan ke admin klinik.']);
    }

    $defaultSettings = $pdo->query('SELECT COUNT(*) FROM admin_settings')->fetchColumn();
    if ($defaultSettings == 0) {
        $insertSettings = $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?)');
        $insertSettings->execute(['admin_phone', ADMIN_PHONE]);
    }

    $adminExists = $pdo->prepare('SELECT COUNT(*) AS count FROM users WHERE username = ? LIMIT 1');
    $adminExists->execute(['admin']);
    $adminExistsRow = $adminExists->fetch(PDO::FETCH_ASSOC);
    if (!$adminExistsRow || $adminExistsRow['count'] == 0) {
        $insertUser = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
        $insertUser->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    }

    $doctorRows = $pdo->query('SELECT id FROM doctors')->fetchAll(PDO::FETCH_COLUMN);
    $checkDoctorUser = $pdo->prepare('SELECT COUNT(*) AS count FROM users WHERE doctor_id = ? LIMIT 1');
    $createDoctorUser = $pdo->prepare('INSERT INTO users (username, password, role, doctor_id) VALUES (?, ?, ?, ?)');
    foreach ($doctorRows as $doctorId) {
        $checkDoctorUser->execute([$doctorId]);
        $doctorExistsRow = $checkDoctorUser->fetch(PDO::FETCH_ASSOC);
        if (!$doctorExistsRow || $doctorExistsRow['count'] == 0) {
            $createDoctorUser->execute([(string)$doctorId, password_hash('doctor123', PASSWORD_DEFAULT), 'doctor', (int)$doctorId]);
        }
    }
}

function ensure_table($pdo, $tableName, $createSql) {
    $pdo->exec($createSql);

    try {
        $pdo->query('SELECT 1 FROM `' . $tableName . '` LIMIT 1');
    } catch (PDOException $e) {
        $message = $e->getMessage();
        $errorInfo = $e->errorInfo ?? [];
        $isBrokenTable = strpos($message, 'doesn\'t exist in engine') !== false
            || strpos($message, 'Base table or view not found') !== false
            || (isset($errorInfo[1]) && in_array((int)$errorInfo[1], [1146, 1932], true));

        if (!$isBrokenTable) {
            throw $e;
        }

        $pdo->exec('DROP TABLE IF EXISTS `' . $tableName . '`');
        $pdo->exec($createSql);
    }
}

function ensure_column($pdo, $tableName, $columnName, $definition) {
    $check = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $check->execute([DB_NAME, $tableName, $columnName]);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `' . $tableName . '` ADD COLUMN `' . $columnName . '` ' . $definition);
    }
}

function db_query($sql, $params = [], $one = false) {
    $stmt = db_connect()->prepare($sql);
    $stmt->execute($params);
    return $one ? $stmt->fetch() : $stmt->fetchAll();
}

function cleanup_expired_schedules() {
    db_query('DELETE FROM schedules WHERE date < CURDATE()');
}

function load_data($name) {
    switch ($name) {
        case 'doctors':
            return db_query('SELECT * FROM doctors ORDER BY name');
        case 'schedules':
            cleanup_expired_schedules();
            return db_query('SELECT * FROM schedules ORDER BY date, time');
        case 'patients':
            return db_query('SELECT * FROM patients ORDER BY created_at DESC');
    }
    return [];
}

function load_doctor_schedules($doctorId) {
    cleanup_expired_schedules();
    return db_query('SELECT * FROM schedules WHERE doctor_id = ? ORDER BY date, time', [(int)$doctorId]);
}

function load_site_contents() {
    $rows = db_query('SELECT content_key, content_value FROM site_content');
    $contents = [];
    foreach ($rows as $row) {
        $contents[$row['content_key']] = $row['content_value'];
    }
    return $contents;
}

function load_doctor_holidays($doctorId = null, $date = null) {
    $sql = 'SELECT * FROM doctor_holidays';
    $params = [];
    $conditions = [];

    if ($doctorId !== null) {
        $conditions[] = 'doctor_id = ?';
        $params[] = (int)$doctorId;
    }

    if ($date !== null) {
        $conditions[] = 'holiday_date = ?';
        $params[] = $date;
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY holiday_date DESC, created_at DESC';
    return db_query($sql, $params);
}

function get_doctor_holidays_for_date($date) {
    $rows = load_doctor_holidays(null, $date);
    $holidays = [];
    foreach ($rows as $row) {
        $holidays[(int)$row['doctor_id']] = $row;
    }
    return $holidays;
}

function get_doctor_holiday($doctorId, $date) {
    return db_query('SELECT * FROM doctor_holidays WHERE doctor_id = ? AND holiday_date = ? LIMIT 1', [(int)$doctorId, $date], true);
}

function upsert_doctor_holiday($doctorId, $date, $note = 'Libur') {
    $note = trim($note);
    if ($note === '') {
        $note = 'Libur';
    }

    db_query(
        'INSERT INTO doctor_holidays (doctor_id, holiday_date, note) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note)',
        [(int)$doctorId, $date, $note]
    );
}

function delete_doctor_holiday($doctorId, $date) {
    db_query('DELETE FROM doctor_holidays WHERE doctor_id = ? AND holiday_date = ?', [(int)$doctorId, $date]);
}

function upsert_site_content($key, $value) {
    db_query('INSERT INTO site_content (content_key, content_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)', [$key, $value]);
}

function get_site_content($key, $default = '') {
    $row = db_query('SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1', [$key], true);
    return $row ? $row['content_value'] : $default;
}

function find_by_id($items, $id, $key = 'id') {
    foreach ($items as $item) {
        if ((string)$item[$key] === (string)$id) {
            return $item;
        }
    }
    return null;
}

function get_schedule_by_id($scheduleId) {
    cleanup_expired_schedules();
    return db_query('SELECT * FROM schedules WHERE id = ? LIMIT 1', [$scheduleId], true);
}

function get_doctor_by_id($doctorId) {
    return db_query('SELECT * FROM doctors WHERE id = ? LIMIT 1', [$doctorId], true);
}

function get_doctor_specialty($doctors, $id) {
    // If $doctors is an array of doctor rows, try to find the matching one
    if (is_array($doctors)) {
        foreach ($doctors as $d) {
            if ((string)$d['id'] === (string)$id) {
                return $d['specialty'] ?? 'Umum';
            }
        }
    }
    // Fallback: query the DB directly
    $row = get_doctor_by_id($id);
    return $row ? ($row['specialty'] ?? 'Umum') : 'Umum';
}

function insert_doctor($name, $specialty, $phone, $description = '', $education = '', $symptoms = '') {
    $stmt = db_connect()->prepare('INSERT INTO doctors (name, specialty, phone, description, education, symptoms) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $specialty, $phone, $description, $education, $symptoms]);
    $doctorId = db_connect()->lastInsertId();
    $username = (string)$doctorId;
    $passwordHash = password_hash('doctor123', PASSWORD_DEFAULT);
    $createUser = db_connect()->prepare('INSERT INTO users (username, password, role, doctor_id) VALUES (?, ?, ?, ?)');
    $createUser->execute([$username, $passwordHash, 'doctor', (int)$doctorId]);
}

function update_doctor($doctorId, $name, $specialty, $phone, $description = '', $education = '', $symptoms = '') {
    db_query('UPDATE doctors SET name = ?, specialty = ?, phone = ?, description = ?, education = ?, symptoms = ? WHERE id = ?', 
        [$name, $specialty, $phone, $description, $education, $symptoms, $doctorId]);
}

function get_admin_setting($key, $default = '') {
    $row = db_query('SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1', [$key], true);
    return $row ? $row['setting_value'] : $default;
}

function upsert_admin_setting($key, $value) {
    db_query('INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}

function insert_schedule($doctorId, $date, $time, $slots) {
    db_query('INSERT INTO schedules (doctor_id, date, time, slots) VALUES (?, ?, ?, ?)', [$doctorId, $date, $time, $slots]);
}

function delete_schedule_by_id($scheduleId) {
    db_query('DELETE FROM schedules WHERE id = ?', [$scheduleId]);
}

/**
 * Deduplicate schedules so that for each doctor and date only the latest
 * created schedule is returned.
 */
function dedupe_latest_schedules($schedules) {
    $map = [];
    foreach ($schedules as $s) {
        $key = $s['doctor_id'] . '|' . $s['date'];
        if (!isset($map[$key])) {
            $map[$key] = $s;
            continue;
        }
        // Prefer the most recently created schedule (created_at) or higher id
        $existing = $map[$key];
        $existingTime = isset($existing['created_at']) ? strtotime($existing['created_at']) : 0;
        $currentTime = isset($s['created_at']) ? strtotime($s['created_at']) : 0;
        if ($currentTime > $existingTime) {
            $map[$key] = $s;
        } elseif ($currentTime == $existingTime && isset($s['id']) && isset($existing['id']) && $s['id'] > $existing['id']) {
            $map[$key] = $s;
        }
    }
    return array_values($map);
}

function insert_patient($name, $phone, $doctorId, $scheduleId) {
    $pdo = db_connect();
    $pdo->beginTransaction();
    try {
        $booking = create_patient_booking_record($name, $phone, $doctorId, $scheduleId);
        $pdo->commit();
        return $booking['queue_number'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function insert_patient_visit($name, $nik, $address, $phone, $doctorId, $scheduleId, $cuppingType, $treatment = '') {
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $profile = db_query('SELECT id FROM patient_profiles WHERE nik = ? LIMIT 1', [$nik], true);
        if ($profile) {
            $patientId = (int)$profile['id'];
            db_query('UPDATE patient_profiles SET name = ?, address = ?, phone = ? WHERE id = ?', [$name, $address, $phone, $patientId]);
        } else {
            db_query('INSERT INTO patient_profiles (nik, name, address, phone) VALUES (?, ?, ?, ?)', [$nik, $name, $address, $phone]);
            $patientId = (int)$pdo->lastInsertId();
        }

        $booking = create_patient_booking_record($name, $phone, $doctorId, $scheduleId, $patientId);
        $schedule = $booking['schedule'];
        db_query(
            'INSERT INTO medical_records (patient_id, doctor_id, visit_date, visit_time, queue_number, cupping_type, treatment) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$patientId, $doctorId, $schedule['date'], $schedule['time'], null, $cuppingType, $treatment]
        );
        $recordId = (int)$pdo->lastInsertId();
        $pdo->commit();
        return ['queue_number' => null, 'medical_record_id' => $recordId, 'patient_id' => $patientId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function create_patient_booking_record($name, $phone, $doctorId, $scheduleId, $patientProfileId = null) {
    $pdo = db_connect();
    $scheduleQuery = $pdo->prepare('SELECT * FROM schedules WHERE id = ? FOR UPDATE');
    $scheduleQuery->execute([(int)$scheduleId]);
    $schedule = $scheduleQuery->fetch(PDO::FETCH_ASSOC);
    if (!$schedule) {
        throw new RuntimeException('Jadwal tidak ditemukan.');
    }
    if ((int)$schedule['doctor_id'] !== (int)$doctorId) {
        throw new RuntimeException('Dokter tidak sesuai dengan jadwal yang dipilih.');
    }

    $bookingCount = db_query('SELECT COUNT(*) AS total FROM patients WHERE schedule_id = ?', [(int)$scheduleId], true);
    if ((int)$bookingCount['total'] >= (int)$schedule['slots']) {
        throw new RuntimeException('Jadwal sudah penuh.');
    }

    db_query(
        'INSERT INTO patients (name, phone, doctor_id, schedule_id, patient_profile_id, queue_number) VALUES (?, ?, ?, ?, ?, ?)',
        [$name, $phone, (int)$schedule['doctor_id'], (int)$scheduleId, $patientProfileId, null]
    );

    return ['queue_number' => null, 'schedule' => $schedule];
}

function assign_patient_queue($patientId, $queueNumber, $doctorId = null) {
    if ((int)$queueNumber < 1) {
        throw new RuntimeException('Nomor antrean harus lebih besar dari nol.');
    }

    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $scheduleLookup = db_query('SELECT schedule_id FROM patients WHERE id = ? LIMIT 1', [(int)$patientId], true);
        if (!$scheduleLookup) {
            throw new RuntimeException('Pasien tidak ditemukan.');
        }
        $scheduleLock = $pdo->prepare('SELECT id FROM schedules WHERE id = ? FOR UPDATE');
        $scheduleLock->execute([(int)$scheduleLookup['schedule_id']]);

        $patientQuery = $pdo->prepare('SELECT p.*, s.date, s.time FROM patients p INNER JOIN schedules s ON s.id = p.schedule_id WHERE p.id = ? FOR UPDATE');
        $patientQuery->execute([(int)$patientId]);
        $patient = $patientQuery->fetch(PDO::FETCH_ASSOC);
        if (!$patient || ($doctorId !== null && (int)$patient['doctor_id'] !== (int)$doctorId)) {
            throw new RuntimeException('Pasien tidak ditemukan atau tidak memiliki akses.');
        }

        $duplicate = db_query(
            'SELECT id FROM patients WHERE schedule_id = ? AND queue_number = ? AND id <> ? LIMIT 1',
            [(int)$patient['schedule_id'], (int)$queueNumber, (int)$patientId],
            true
        );
        if ($duplicate) {
            throw new RuntimeException('Nomor antrean sudah digunakan pada jadwal ini.');
        }

        db_query('UPDATE patients SET queue_number = ? WHERE id = ?', [(int)$queueNumber, (int)$patientId]);
        if (!empty($patient['patient_profile_id'])) {
            db_query(
                'UPDATE medical_records SET queue_number = ? WHERE patient_id = ? AND doctor_id = ? AND visit_date = ? AND visit_time = ?',
                [(int)$queueNumber, (int)$patient['patient_profile_id'], (int)$patient['doctor_id'], $patient['date'], $patient['time']]
            );
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function find_upcoming_patient_queues($nik, $phone) {
    return db_query(
        'SELECT p.id, p.queue_number, p.name, p.phone, s.date, s.time, d.name AS doctor_name
         FROM patients p
         INNER JOIN patient_profiles pp ON pp.id = p.patient_profile_id
         INNER JOIN schedules s ON s.id = p.schedule_id
         INNER JOIN doctors d ON d.id = p.doctor_id
         WHERE pp.nik = ? AND p.phone = ? AND s.date >= CURDATE()
         ORDER BY s.date, s.time, p.queue_number',
        [$nik, $phone]
    );
}

function load_medical_records($search = '') {
        $sql = 'SELECT mr.*, pp.nik, pp.name AS patient_name, pp.address, pp.phone,
                                     (SELECT p.id
                                        FROM patients p
                                        INNER JOIN schedules booking_schedule ON booking_schedule.id = p.schedule_id
                                        WHERE p.patient_profile_id = mr.patient_id AND p.doctor_id = mr.doctor_id
                                            AND booking_schedule.date = mr.visit_date AND booking_schedule.time = mr.visit_time
                                        ORDER BY p.id DESC LIMIT 1) AS patient_booking_id,
                   d.name AS doctor_name,
                                     (SELECT COUNT(*) FROM medical_records history
                                        WHERE history.patient_id = mr.patient_id
                                            AND (history.visit_date < mr.visit_date OR (history.visit_date = mr.visit_date AND history.id <= mr.id))) AS visit_count
            FROM medical_records mr
            INNER JOIN patient_profiles pp ON pp.id = mr.patient_id
            LEFT JOIN doctors d ON d.id = mr.doctor_id';
    $params = [];
    if ($search !== '') {
        $sql .= ' WHERE pp.nik LIKE ? OR pp.name LIKE ?';
        $params = ['%' . $search . '%', '%' . $search . '%'];
    }
    $sql .= ' ORDER BY mr.visit_date DESC, mr.visit_time DESC, mr.id DESC';
    return db_query($sql, $params);
}

function get_medical_record_by_id($recordId) {
    return db_query(
    'SELECT mr.*, pp.nik, pp.name AS patient_name, pp.address, pp.phone,
                                (SELECT p.id
                                 FROM patients p
                                 INNER JOIN schedules booking_schedule ON booking_schedule.id = p.schedule_id
                                 WHERE p.patient_profile_id = mr.patient_id AND p.doctor_id = mr.doctor_id
                                     AND booking_schedule.date = mr.visit_date AND booking_schedule.time = mr.visit_time
                                 ORDER BY p.id DESC LIMIT 1) AS patient_booking_id,
        (SELECT p.schedule_id
         FROM patients p
         INNER JOIN schedules s ON s.id = p.schedule_id
         WHERE p.patient_profile_id = mr.patient_id AND p.doctor_id = mr.doctor_id
           AND s.date = mr.visit_date AND s.time = mr.visit_time
         ORDER BY p.id DESC LIMIT 1) AS schedule_id,
                d.name AS doctor_name,
                                (SELECT COUNT(*) FROM medical_records history
                                 WHERE history.patient_id = mr.patient_id
                                     AND (history.visit_date < mr.visit_date OR (history.visit_date = mr.visit_date AND history.id <= mr.id))) AS visit_count
         FROM medical_records mr
         INNER JOIN patient_profiles pp ON pp.id = mr.patient_id
         LEFT JOIN doctors d ON d.id = mr.doctor_id
         WHERE mr.id = ? LIMIT 1',
        [(int)$recordId],
        true
    );
}

function update_medical_record($recordId, $name, $nik, $address, $phone, $cuppingType, $treatment, $scheduleId, $doctorId = null) {
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $recordQuery = $pdo->prepare('SELECT * FROM medical_records WHERE id = ? FOR UPDATE');
        $recordQuery->execute([(int)$recordId]);
        $record = $recordQuery->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            throw new RuntimeException('Rekam medis tidak ditemukan.');
        }

        $scheduleQuery = $pdo->prepare('SELECT * FROM schedules WHERE id = ? FOR UPDATE');
        $scheduleQuery->execute([(int)$scheduleId]);
        $schedule = $scheduleQuery->fetch(PDO::FETCH_ASSOC);
        if (!$schedule || ($doctorId !== null && (int)$schedule['doctor_id'] !== (int)$doctorId)) {
            throw new RuntimeException('Jadwal tidak ditemukan atau Anda tidak memiliki akses.');
        }

        $bookingQuery = $pdo->prepare(
            'SELECT p.id, p.schedule_id, p.queue_number
             FROM patients p
             INNER JOIN schedules s ON s.id = p.schedule_id
             WHERE p.patient_profile_id = ? AND p.doctor_id = ? AND s.date = ? AND s.time = ?
             ORDER BY p.id DESC LIMIT 1 FOR UPDATE'
        );
        $bookingQuery->execute([(int)$record['patient_id'], (int)$record['doctor_id'], $record['visit_date'], $record['visit_time']]);
        $booking = $bookingQuery->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            throw new RuntimeException('Data jadwal pasien tidak ditemukan.');
        }

        $scheduleChanged = (int)$booking['schedule_id'] !== (int)$schedule['id'];
        $capacityQuery = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE schedule_id = ? AND id <> ?');
        $capacityQuery->execute([(int)$schedule['id'], (int)$booking['id']]);
        if ((int)$capacityQuery->fetchColumn() >= (int)$schedule['slots']) {
            throw new RuntimeException('Jadwal tujuan sudah penuh.');
        }

        $queueNumber = $scheduleChanged ? null : $booking['queue_number'];
        $pdo->prepare('UPDATE patient_profiles SET name = ?, nik = ?, address = ?, phone = ? WHERE id = ?')
            ->execute([$name, $nik, $address, $phone, (int)$record['patient_id']]);
        $pdo->prepare('UPDATE patients SET name = ?, phone = ?, doctor_id = ?, schedule_id = ?, queue_number = ? WHERE id = ?')
            ->execute([$name, $phone, (int)$schedule['doctor_id'], (int)$schedule['id'], $queueNumber, (int)$booking['id']]);
        $pdo->prepare('UPDATE medical_records SET doctor_id = ?, visit_date = ?, visit_time = ?, queue_number = ?, cupping_type = ?, treatment = ? WHERE id = ?')
            ->execute([(int)$schedule['doctor_id'], $schedule['date'], $schedule['time'], $queueNumber, $cuppingType, $treatment, (int)$recordId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function count_bookings(array $patients, int $scheduleId): int {
    $count = 0;
    foreach ($patients as $patient) {
        if ((int)$patient['schedule_id'] === (int)$scheduleId) {
            $count++;
        }
    }
    return $count;
}

function get_schedule_bookings($schedules, $patients) {
    $bookings = [];
    foreach ($schedules as $schedule) {
        $bookings[$schedule['id']] = count_bookings($patients, $schedule['id']);
    }
    return $bookings;
}

function flash_message($text, $type = 'success') {
    echo '<div class="flash flash-' . htmlspecialchars($type) . '">' . htmlspecialchars($text) . '</div>';
}

function sanitize($value) {
    return is_scalar($value) ? trim((string)$value) : '';
}

function login_user(string $username, string $password): ?array {
    $row = db_query('SELECT id, username, password, role, doctor_id FROM users WHERE username = ? LIMIT 1', [$username], true);
    if (!$row && ctype_digit($username)) {
        $row = db_query('SELECT id, username, password, role, doctor_id FROM users WHERE doctor_id = ? LIMIT 1', [$username], true);
    }

    if ($row) {
        if (password_verify($password, $row['password'])) {
            return $row;
        }
        return null;
    }

    if (ctype_digit($username)) {
        $doctor = get_doctor_by_id((int)$username);
        if ($doctor && $password === 'doctor123') {
            $passwordHash = password_hash('doctor123', PASSWORD_DEFAULT);
            $createUser = db_connect()->prepare('INSERT INTO users (username, password, role, doctor_id) VALUES (?, ?, ?, ?)');
            $createUser->execute([$username, $passwordHash, 'doctor', (int)$username]);
            return db_query('SELECT id, username, password, role, doctor_id FROM users WHERE username = ? LIMIT 1', [$username], true);
        }
    }

    return null;
}

function check_session() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $user = db_query('SELECT id, username, role, doctor_id FROM users WHERE id = ? LIMIT 1', [$_SESSION['user_id']], true);
    return $user ? $user : false;
}

function is_admin() {
    $user = check_session();
    return $user && $user['role'] === 'admin';
}

function is_doctor() {
    $user = check_session();
    return $user && $user['role'] === 'doctor';
}
