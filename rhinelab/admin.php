<?php
session_start();
require_once __DIR__ . '/data.php';

// Cek apakah user sudah login dan adalah admin
if (!is_admin()) {
    header('Location: login.php');
    exit;
}

$doctors = load_data('doctors');
$schedules = load_data('schedules');
$patients = load_data('patients');
$siteContents = load_site_contents();
$doctorHolidays = load_doctor_holidays();
$message = '';
$messageType = 'success';

// Selected date for viewing schedules (from date picker)
$selectedDate = isset($_GET['selected_date']) ? sanitize($_GET['selected_date']) : date('Y-m-d');

// Schedules for the selected date
$todaySchedules = array_values(array_filter($schedules, function ($schedule) use ($selectedDate) {
    return $schedule['date'] === $selectedDate;
}));
usort($todaySchedules, function ($a, $b) {
    return strcmp($a['time'], $b['time']);
});
$bookings = get_schedule_bookings($schedules, $patients);
$doctorHolidaysForDate = get_doctor_holidays_for_date($selectedDate);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_site_content') {
        $fields = [
            'site_title',
            'hero_eyebrow',
            'hero_title',
            'hero_text',
            'hero_cta_text',
            'footer_text'
        ];
        foreach ($fields as $field) {
            $value = trim($_POST[$field] ?? '');
            upsert_site_content($field, $value);
        }
        $siteContents = load_site_contents();
        $message = 'Konten halaman berhasil disimpan.';
        $messageType = 'success';
    }
    if ($action === 'delete_doctor_holiday') {
        $holidayDoctorId = intval($_POST['holiday_doctor_id'] ?? 0);
        $holidayDate = sanitize($_POST['holiday_date'] ?? '');

        if ($holidayDoctorId > 0 && $holidayDate !== '') {
            delete_doctor_holiday($holidayDoctorId, $holidayDate);
            $message = 'Keterangan libur dokter berhasil dihapus.';
            $messageType = 'success';
        } else {
            $message = 'Data libur tidak valid.';
            $messageType = 'error';
        }
    }
    if ($action === 'update_doctor') {
        $doctorId = intval($_POST['doctor_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $specialty = sanitize($_POST['specialty'] ?? 'Umum');
        $phone = sanitize($_POST['phone'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $education = trim($_POST['education'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        
        if ($doctorId <= 0 || $name === '' || $phone === '') {
            $message = 'Data dokter tidak lengkap.';
            $messageType = 'error';
        } else {
            update_doctor($doctorId, $name, $specialty, $phone, $description, $education, $symptoms);
            $message = 'Profil dokter berhasil diperbarui.';
            $messageType = 'success';
        }
    }
    if ($action === 'add_doctor') {
        $name = sanitize($_POST['name'] ?? '');
        $specialty = sanitize($_POST['specialty'] ?? 'Umum');
        $phone = sanitize($_POST['phone'] ?? '');
        if ($name === '' || $phone === '') {
            $message = 'Nama dokter dan nomor telepon wajib diisi.';
            $messageType = 'error';
        } else {
            insert_doctor($name, $specialty, $phone);
            $message = 'Dokter berhasil ditambahkan.';
        }
    }
    if ($action === 'add_schedule') {
        $doctorId = sanitize($_POST['doctor_id'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        $time = sanitize($_POST['time'] ?? '');
        $slots = max(1, intval($_POST['slots'] ?? 1));
        if ($doctorId === '' || $date === '' || $time === '') {
            $message = 'Semua data jadwal dokter wajib diisi.';
            $messageType = 'error';
        } else {
            insert_schedule((int)$doctorId, $date, $time, $slots);
            $message = 'Jadwal dokter berhasil ditambahkan.';
        }
    }
    $doctors = load_data('doctors');
    $schedules = load_data('schedules');
    $patients = load_data('patients');
    $siteContents = load_site_contents();
    $doctorHolidays = load_doctor_holidays();
}

$bookings = get_schedule_bookings($schedules, $patients);

function get_site_content_value($contents, $key, $default = '') {
    return isset($contents[$key]) ? $contents[$key] : $default;
}

function format_date_indonesia($date) {
    return date('d F Y', strtotime($date));
}

function get_doctor_name($doctors, $id) {
    $doctor = find_by_id($doctors, $id);
    return $doctor ? $doctor['name'] : '-';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Klinik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/style.css" />
</head>
<body>
<div class="container py-5">
    <header class="header-bar d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <p class="eyebrow text-primary mb-2">Admin Klinik</p>
            <h1 class="h3 mb-0">Dashboard Pengaturan</h1>
        </div>
        <nav class="nav gap-2">
            <a class="nav-link px-3 py-2 rounded-3" href="index.php">Beranda</a>
            <a class="nav-link px-3 py-2 rounded-3" href="doctors.php">Profil Dokter</a>
            <a class="nav-link px-3 py-2 rounded-3 active" aria-current="page" href="admin.php">Admin</a>
            <a class="nav-link px-3 py-2 rounded-3" href="rekam_medis.php">Rekam Medis</a>
            <a class="nav-link px-3 py-2 rounded-3 text-danger fw-bold" href="logout.php">Logout</a>
        </nav>
    </header>

    <?php if ($message): ?>
        <?php flash_message($message, $messageType); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Lihat Jadwal berdasarkan Tanggal</h2>
            <input type="text" id="scheduleCalendar" class="form-control" placeholder="Pilih tanggal untuk melihat jadwal..." readonly />
        </div>
    </div>

        <section id="adminJadwal" class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Jadwal Dokter pada <?php echo format_date_indonesia($selectedDate); ?></h2>
                <?php if (!empty($doctorHolidaysForDate)): ?>
                    <div class="alert alert-warning">
                        <strong>Catatan libur hari ini:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($doctorHolidaysForDate as $holiday): ?>
                                <li><?php echo htmlspecialchars(get_doctor_name($doctors, $holiday['doctor_id'])); ?> - <?php echo htmlspecialchars($holiday['note']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($todaySchedules)): ?>
                    <p class="muted">Belum ada jadwal dokter untuk tanggal ini.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Dokter</th>
                                    <th>Spesialisasi</th>
                                    <th>Jam Praktik</th>
                                    <th>Menerima pasien</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todaySchedules as $schedule): ?>
                                    <?php
                                    $booked = $bookings[$schedule['id']] ?? 0;
                                    $holiday = $doctorHolidaysForDate[$schedule['doctor_id']] ?? null;
                                    $holidayNote = $holiday ? $holiday['note'] : '';
                                    $isFull = $booked >= $schedule['slots'];
                                    $isBlocked = $holiday !== null;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(get_doctor_name($doctors, $schedule['doctor_id'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['specialty'] ?? get_doctor_specialty($doctors, $schedule['doctor_id'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($schedule['time'], 0, 5)); ?></td>
                                        <td>
                                            <?php if ($isBlocked): ?>
                                                <span class="status-full">Libur</span> - <?php echo htmlspecialchars($holidayNote !== '' ? $holidayNote : 'Dokter sedang libur'); ?>
                                            <?php else: ?>
                                                <?php echo $isFull ? '<span class="status-full">Penuh</span>' : '<span class="status-free">Tersedia</span>'; ?> (<?php echo $booked; ?>/<?php echo $schedule['slots']; ?>)
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-4">Edit Konten Halaman</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_site_content" />
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Judul Halaman (Title)</label>
                        <input class="form-control" type="text" name="site_title" value="<?php echo htmlspecialchars(get_site_content_value($siteContents, 'site_title', 'Sistem Klinik Sederhana')); ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Header Atas (Eyebrow)</label>
                        <input class="form-control" type="text" name="hero_eyebrow" value="<?php echo htmlspecialchars(get_site_content_value($siteContents, 'hero_eyebrow', 'Selamat Datang di Klinik Sehat')); ?>" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teks Tombol Lihat Jadwal</label>
                        <input class="form-control" type="text" name="hero_cta_text" value="<?php echo htmlspecialchars(get_site_content_value($siteContents, 'hero_cta_text', 'Lihat Jadwal')); ?>" required />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Judul Hero</label>
                        <input class="form-control" type="text" name="hero_title" value="<?php echo htmlspecialchars(get_site_content_value($siteContents, 'hero_title', 'Reservasi Janji Temu Dokter dengan Mudah')); ?>" required />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Deskripsi Hero</label>
                        <textarea class="form-control" name="hero_text" rows="4" required><?php echo htmlspecialchars(get_site_content_value($siteContents, 'hero_text', 'Lihat jadwal dokter untuk hari ini, cek ketersediaan slot, dan langsung kirim permintaan reservasi ke admin lewat WhatsApp.')); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Footer</label>
                        <textarea class="form-control" name="footer_text" rows="3"><?php echo htmlspecialchars(get_site_content_value($siteContents, 'footer_text', 'Untuk reservasi, klik tombol WhatsApp dan kirim pesan ke admin klinik.')); ?></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Simpan Konten</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-4">Pengaturan Admin</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_admin_settings" />
                <div class="mb-3">
                    <label class="form-label">Nomor WhatsApp Admin</label>
                    <input class="form-control" type="text" name="admin_phone" value="<?php echo htmlspecialchars(get_admin_setting('admin_phone', ADMIN_PHONE)); ?>" placeholder="6281234567890" required />
                    <small class="text-muted">Format: 62 diikuti nomor tanpa 0 di depan</small>
                </div>
                <button class="btn btn-warning" type="submit">Simpan Pengaturan</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-4">Keterangan Libur Dokter</h2>
            <form method="post" class="mb-4">
                <input type="hidden" name="action" value="save_doctor_holiday" />
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Dokter</label>
                        <select class="form-select" name="holiday_doctor_id" required>
                            <option value="">Pilih dokter</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo (int)$doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Libur</label>
                        <input class="form-control" type="date" name="holiday_date" required />
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Keterangan</label>
                        <input class="form-control" type="text" name="holiday_note" placeholder="Contoh: Cuti, seminar, dinas luar" />
                    </div>
                    <div class="col-12">
                        <button class="btn btn-danger" type="submit">Simpan Libur</button>
                    </div>
                </div>
            </form>

            <?php if (empty($doctorHolidays)): ?>
                <p class="muted mb-0">Belum ada keterangan libur dokter.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Dokter</th>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctorHolidays as $holiday): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(get_doctor_name($doctors, $holiday['doctor_id'])); ?></td>
                                    <td><?php echo date('d F Y', strtotime($holiday['holiday_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($holiday['note']); ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="delete_doctor_holiday" />
                                            <input type="hidden" name="holiday_doctor_id" value="<?php echo (int)$holiday['doctor_id']; ?>" />
                                            <input type="hidden" name="holiday_date" value="<?php echo htmlspecialchars($holiday['holiday_date']); ?>" />
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-4">Edit Profil Dokter</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_doctor" />
                <div class="mb-3">
                    <label class="form-label">Pilih Dokter</label>
                    <select class="form-select" id="doctorSelect" name="doctor_id" required onchange="loadDoctorData(this.value)">
                        <option value="">Pilih dokter untuk diedit</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo (int)$doctor['id']; ?>" data-name="<?php echo htmlspecialchars($doctor['name'], ENT_QUOTES, 'UTF-8'); ?>" data-specialty="<?php echo htmlspecialchars($doctor['specialty'], ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars($doctor['phone'], ENT_QUOTES, 'UTF-8'); ?>" data-description="<?php echo htmlspecialchars($doctor['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-education="<?php echo htmlspecialchars($doctor['education'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-symptoms="<?php echo htmlspecialchars($doctor['symptoms'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3" id="doctorFormFields" style="display: none;">
                    <div class="col-md-6">
                        <label class="form-label">Nama Dokter</label>
                        <input class="form-control" type="text" name="name" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Spesialisasi</label>
                        <input class="form-control" type="text" name="specialty" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nomor Telepon</label>
                        <input class="form-control" type="text" name="phone" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Deskripsi Singkat</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Contoh: Dokter berpengalaman 10 tahun..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Riwayat Pendidikan</label>
                        <textarea class="form-control" name="education" rows="3" placeholder="Pisahkan setiap pendidikan dengan semicolon (;)&#10;Contoh: S1 Kedokteran Universitas X; Spesialis Umum RS Y"></textarea>
                        <small class="text-muted">Gunakan semicolon (;) untuk memisahkan setiap pendidikan</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Gejala yang Dapat Ditangani</label>
                        <textarea class="form-control" name="symptoms" rows="3" placeholder="Pisahkan setiap gejala dengan koma (,)&#10;Contoh: Demam, Batuk, Diare, Sakit Kepala"></textarea>
                        <small class="text-muted">Gunakan koma (,) untuk memisahkan setiap gejala</small>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success" type="submit">Simpan Profil Dokter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <div class=\"col\">
            <div class=\"card shadow-sm\">
                <div class=\"card-body\">
                    <h2 class=\"h5 mb-4\">Tambah Dokter</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_doctor" />
                        <div class="mb-3">
                            <label class="form-label">Nama Dokter</label>
                            <input class="form-control" type="text" name="name" placeholder="Contoh: Dr. Anita" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spesialisasi</label>
                            <input class="form-control" type="text" name="specialty" placeholder="Contoh: Umum" value="Umum" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input class="form-control" type="text" name="phone" placeholder="081234567890" required />
                        </div>
                        <button class="btn btn-primary" type="submit">Simpan Dokter</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-4">Tambah Jadwal Dokter</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_schedule" />
                        <div class="mb-3">
                            <label class="form-label">Dokter</label>
                            <select class="form-select" name="doctor_id" required>
                                <option value="">Pilih dokter</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo (int)$doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input class="form-control" type="date" name="date" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jam</label>
                            <input class="form-control" type="time" name="time" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah Slot</label>
                            <input class="form-control" type="number" name="slots" value="4" min="1" required />
                        </div>
                        <button class="btn btn-secondary" type="submit">Simpan Jadwal</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Data Klinik</h2>
        <div class="grid-columns">
            <div class="info-card">
                <p>Dokter</p>
                <strong><?php echo count($doctors); ?></strong>
            </div>
            <div class="info-card">
                <p>Jadwal</p>
                <strong><?php echo count($schedules); ?></strong>
            </div>
            <div class="info-card">
                <p>Calon Pasien</p>
                <strong><?php echo count($patients); ?></strong>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Daftar Dokter</h2>
        <?php if (empty($doctors)): ?>
            <p class="muted">Belum ada dokter tersimpan.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Nama</th><th>Spesialisasi</th><th>Telepon</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                            <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                            <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" id="daftarJadwal">
        <h2>Daftar Jadwal</h2>
        <p class="muted mb-3">Jadwal yang sudah melewati tanggalnya akan terhapus otomatis.</p>
        <?php if (empty($schedules)): ?>
            <p class="muted">Belum ada jadwal dokter.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Dokter</th><th>Tanggal</th><th>Jam</th><th>Slot</th><th>Terisi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(get_doctor_name($doctors, $schedule['doctor_id'])); ?></td>
                            <td><?php echo format_date_indonesia($schedule['date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($schedule['time'], 0, 5)); ?></td>
                            <td><?php echo (int)$schedule['slots']; ?></td>
                            <td><?php echo (int)($bookings[$schedule['id']] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer>
        <p>Gunakan halaman ini untuk mengelola dokter, jadwal, dan reservasi calon pasien.</p>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const schedules = <?php echo json_encode($schedules); ?>;
    const today = new Date();
    const scheduledDates = schedules.map(s => s.date);
    
    flatpickr('#scheduleCalendar', {
        mode: 'single',
        defaultDate: <?php echo json_encode($selectedDate); ?>,
        onChange: function(selectedDates) {
            if (selectedDates.length > 0) {
                const formattedDate = selectedDates[0].toISOString().split('T')[0];
                // Navigate to the page and include the daftarJadwal anchor so the page scrolls after load
                window.location.href = 'admin.php?selected_date=' + formattedDate + '#daftarJadwal';
            }
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            const dateStr = dayElem.dateObj.toISOString().split('T')[0];
            const scheduledDates = <?php echo json_encode(array_values(array_unique(array_column($schedules, 'date')))); ?>;
            if (scheduledDates.includes(dateStr)) {
                dayElem.classList.add('has-schedule');
            }
        }
    });

    // Load doctor data for editing
    function loadDoctorData(doctorId) {
        if (doctorId === '') {
            document.getElementById('doctorFormFields').style.display = 'none';
            return;
        }
        
        const select = document.getElementById('doctorSelect');
        const option = select.options[select.selectedIndex];
        
        document.querySelector('[name="name"]').value = option.dataset.name;
        document.querySelector('[name="specialty"]').value = option.dataset.specialty;
        document.querySelector('[name="phone"]').value = option.dataset.phone;
        document.querySelector('[name="description"]').value = option.dataset.description;
        document.querySelector('[name="education"]').value = option.dataset.education;
        document.querySelector('[name="symptoms"]').value = option.dataset.symptoms;
        
        document.getElementById('doctorFormFields').style.display = 'grid';
    }

    // If the page was opened with a hash (e.g. #daftarJadwal), scroll to that section after load
    document.addEventListener('DOMContentLoaded', function() {
        var hash = window.location.hash || '';
        if (hash.length > 1) {
            var id = hash.substring(1);
            var el = document.getElementById(id);
            if (el) {
                setTimeout(function() { el.scrollIntoView({behavior: 'smooth', block: 'start'}); }, 150);
            }
        }
    });
</script>
</body>
</html>
