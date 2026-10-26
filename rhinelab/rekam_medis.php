<?php
session_start();
require_once __DIR__ . '/data.php';

if (!isset($_SESSION['user_id']) || (!is_admin() && !is_doctor())) {
    header('Location: login.php');
    exit;
}

$isAdmin = is_admin();
$doctorId = $isAdmin ? 0 : (int)($_SESSION['doctor_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$message = '';
$messageType = 'success';
$cuppingTypes = ['Bekam kering', 'Bekam basah', 'Sinanti'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $nik = preg_replace('/\D+/', '', $_POST['nik'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $cuppingType = trim($_POST['cupping_type'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');

    if ($action === 'add_visit') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $schedule = $scheduleId > 0 ? get_schedule_by_id($scheduleId) : null;
        if ($name === '' || $nik === '' || $address === '' || $phone === '' || !in_array($cuppingType, $cuppingTypes, true) || !$schedule) {
            $message = 'Lengkapi identitas pasien, jenis bekam, dan jadwal yang valid.';
            $messageType = 'error';
        } elseif (!$isAdmin && (int)$schedule['doctor_id'] !== $doctorId) {
            $message = 'Anda hanya dapat menambahkan pasien pada jadwal sendiri.';
            $messageType = 'error';
        } elseif (count_bookings(load_data('patients'), $scheduleId) >= (int)$schedule['slots']) {
            $message = 'Jadwal sudah penuh dan tidak bisa ditambahkan pasien.';
            $messageType = 'error';
        } else {
            try {
                insert_patient_visit($name, $nik, $address, $phone, (int)$schedule['doctor_id'], $scheduleId, $cuppingType, $treatment);
                header('Location: rekam_medis.php?search=' . urlencode($nik) . '&saved=1');
                exit;
            } catch (Throwable $e) {
                $message = 'Data gagal disimpan. Periksa kembali nomor NIK pasien.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_record') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $record = $recordId > 0 ? get_medical_record_by_id($recordId) : null;
        if (!$record || (!$isAdmin && (int)$record['doctor_id'] !== $doctorId)) {
            $message = 'Rekam medis tidak ditemukan atau Anda tidak memiliki akses.';
            $messageType = 'error';
        } elseif ($name === '' || $nik === '' || $address === '' || $phone === '' || !in_array($cuppingType, $cuppingTypes, true) || $scheduleId < 1) {
            $message = 'Lengkapi identitas pasien, jenis bekam, dan jadwal pemeriksaan.';
            $messageType = 'error';
        } else {
            try {
                update_medical_record($recordId, $name, $nik, $address, $phone, $cuppingType, $treatment, $scheduleId, $isAdmin ? null : $doctorId);
                header('Location: rekam_medis.php?search=' . urlencode($nik) . '&saved=1');
                exit;
            } catch (Throwable $e) {
                $message = in_array($e->getMessage(), [
                    'Jadwal tujuan sudah penuh.',
                    'Jadwal tidak ditemukan atau Anda tidak memiliki akses.',
                    'Data jadwal pasien tidak ditemukan.'
                ], true) ? $e->getMessage() : 'Data gagal diperbarui. Pastikan NIK tersebut belum digunakan pasien lain.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'assign_queue') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $queueNumber = (int)($_POST['queue_number'] ?? 0);
        $record = $recordId > 0 ? get_medical_record_by_id($recordId) : null;
        if (!$record || (!$isAdmin && (int)$record['doctor_id'] !== $doctorId)) {
            $message = 'Rekam medis tidak ditemukan atau Anda tidak memiliki akses.';
            $messageType = 'error';
        } elseif (empty($record['patient_booking_id']) || $queueNumber < 1) {
            $message = empty($record['patient_booking_id'])
                ? 'Data booking pasien tidak ditemukan untuk kunjungan ini.'
                : 'Nomor antrean harus lebih besar dari nol.';
            $messageType = 'error';
        } else {
            try {
                assign_patient_queue((int)$record['patient_booking_id'], $queueNumber, $isAdmin ? null : $doctorId);
                header('Location: rekam_medis.php?search=' . urlencode($record['nik']) . '&saved=queue');
                exit;
            } catch (Throwable $e) {
                $message = in_array($e->getMessage(), [
                    'Nomor antrean sudah digunakan pada jadwal ini.',
                    'Nomor antrean harus lebih besar dari nol.'
                ], true) ? $e->getMessage() : 'Nomor antrean gagal ditetapkan.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $message = $_GET['saved'] === 'queue' ? 'Nomor antrean berhasil ditetapkan.' : 'Rekam medis berhasil disimpan.';
}

$schedules = load_data('schedules');
$patients = load_data('patients');
if (!$isAdmin) {
    $schedules = array_values(array_filter($schedules, function ($schedule) use ($doctorId) {
        return (int)$schedule['doctor_id'] === $doctorId;
    }));
}
$scheduleBookings = get_schedule_bookings($schedules, $patients);
$records = load_medical_records($search);
$doctors = load_data('doctors');
$doctorNames = [];
foreach ($doctors as $doctor) {
    $doctorNames[(int)$doctor['id']] = $doctor['name'];
}
if (!$isAdmin) {
    $records = array_values(array_filter($records, function ($record) use ($doctorId) {
        return (int)$record['doctor_id'] === $doctorId;
    }));
}

$editRecord = null;
if (isset($_GET['edit_id'])) {
    $editRecord = get_medical_record_by_id((int)$_GET['edit_id']);
    if ($editRecord && !$isAdmin && (int)$editRecord['doctor_id'] !== $doctorId) {
        $editRecord = null;
    }
}
$printRecord = null;
if (isset($_GET['print_id'])) {
    $printRecord = get_medical_record_by_id((int)$_GET['print_id']);
    if ($printRecord && !$isAdmin && (int)$printRecord['doctor_id'] !== $doctorId) {
        $printRecord = null;
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function visit_status($count) {
    return (int)$count > 1 ? 'Pasien lama' : 'Pasien baru';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rekam Medis Pasien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/style.css" />
    <style>
        .print-sheet { display: none; }
        @media print {
            body { background: #fff !important; }
            body * { visibility: hidden !important; }
            .print-sheet, .print-sheet * { visibility: visible !important; }
            .print-sheet { display: block !important; position: absolute; inset: 0; width: 100%; padding: 12mm; color: #111; }
            .print-sheet h1 { font-size: 22pt; }
            .print-sheet h2 { font-size: 14pt; margin-top: 18pt; }
            .print-sheet table { width: 100%; border-collapse: collapse; }
            .print-sheet th, .print-sheet td { padding: 8px; border: 1px solid #777; text-align: left; vertical-align: top; }
        }
    </style>
</head>
<body>
<div class="container py-5 screen-content">
    <header class="header-bar d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <p class="eyebrow text-primary mb-2">Data Klinik</p>
            <h1 class="h3 mb-2">Rekam Medis Pasien</h1>
            <p class="hero-text text-muted mb-0">Riwayat kunjungan, penanganan, dan jadwal pemeriksaan pasien.</p>
        </div>
        <nav class="nav gap-2">
            <a class="nav-link px-3 py-2 rounded-3" href="<?php echo $isAdmin ? 'admin.php' : 'dokter.php'; ?>">Kembali</a>
            <a class="nav-link px-3 py-2 rounded-3 text-danger fw-bold" href="logout.php">Logout</a>
        </nav>
    </header>

    <?php if ($message): ?>
        <?php flash_message($message, $messageType); ?>
    <?php endif; ?>

    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Tambah Pasien / Kunjungan</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_visit" />
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nomor KTP / NIK</label>
                        <input class="form-control" name="nik" inputmode="numeric" maxlength="30" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Pasien</label>
                        <input class="form-control" name="name" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alamat</label>
                        <input class="form-control" name="address" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nomor Telepon</label>
                        <input class="form-control" name="phone" type="tel" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Bekam</label>
                        <select class="form-select" name="cupping_type" required>
                            <option value="">Pilih jenis bekam</option>
                            <?php foreach ($cuppingTypes as $type): ?>
                                <option value="<?php echo h($type); ?>"><?php echo h($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jadwal Pemeriksaan</label>
                        <select class="form-select" name="schedule_id" required>
                            <option value="">Pilih jadwal</option>
                            <?php foreach ($schedules as $schedule): ?>
                                <option value="<?php echo (int)$schedule['id']; ?>">
                                    <?php echo h(date('d-m-Y', strtotime($schedule['date'])) . ' ' . substr($schedule['time'], 0, 5)); ?>
                                    <?php if ($isAdmin): ?> - <?php echo h($doctorNames[(int)$schedule['doctor_id']] ?? 'Dokter'); ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Penanganan / Catatan Pemeriksaan</label>
                        <textarea class="form-control" name="treatment" rows="3" placeholder="Catatan penanganan dapat dilengkapi saat pemeriksaan"></textarea>
                    </div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Simpan Kunjungan</button></div>
                </div>
            </form>
        </div>
    </section>

    <?php if ($editRecord): ?>
        <section class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Edit Pasien dan Jadwal Kunjungan</h2>
                <form method="post">
                    <input type="hidden" name="action" value="update_record" />
                    <input type="hidden" name="record_id" value="<?php echo (int)$editRecord['id']; ?>" />
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nomor KTP / NIK</label><input class="form-control" name="nik" value="<?php echo h($editRecord['nik']); ?>" required /></div>
                        <div class="col-md-6"><label class="form-label">Nama Pasien</label><input class="form-control" name="name" value="<?php echo h($editRecord['patient_name']); ?>" required /></div>
                        <div class="col-md-6"><label class="form-label">Alamat</label><input class="form-control" name="address" value="<?php echo h($editRecord['address']); ?>" required /></div>
                        <div class="col-md-6"><label class="form-label">Nomor Telepon</label><input class="form-control" name="phone" type="tel" value="<?php echo h($editRecord['phone']); ?>" required /></div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Bekam</label>
                            <select class="form-select" name="cupping_type" required>
                                <?php foreach ($cuppingTypes as $type): ?>
                                    <option value="<?php echo h($type); ?>" <?php echo $editRecord['cupping_type'] === $type ? 'selected' : ''; ?>><?php echo h($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jadwal Pasien</label>
                            <select class="form-select" name="schedule_id" required>
                                <option value="">Pilih jadwal pemeriksaan</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <?php $scheduleBooked = $scheduleBookings[(int)$schedule['id']] ?? 0; ?>
                                    <option value="<?php echo (int)$schedule['id']; ?>" <?php echo (int)($editRecord['schedule_id'] ?? 0) === (int)$schedule['id'] ? 'selected' : ''; ?>>
                                        <?php echo h(date('d-m-Y', strtotime($schedule['date'])) . ' ' . substr($schedule['time'], 0, 5)); ?>
                                        <?php if ($isAdmin): ?> - <?php echo h($doctorNames[(int)$schedule['doctor_id']] ?? 'Dokter'); ?><?php endif; ?>
                                        (<?php echo (int)$scheduleBooked; ?>/<?php echo (int)$schedule['slots']; ?> pasien)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Penanganan</label><textarea class="form-control" name="treatment" rows="4" required><?php echo h($editRecord['treatment']); ?></textarea></div>
                        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan Perubahan</button><a class="btn btn-outline-secondary" href="rekam_medis.php?search=<?php echo urlencode($search); ?>">Batal</a></div>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Daftar Kunjungan</h2>
            <form method="get" class="row g-2 mb-3">
                <div class="col"><input class="form-control" name="search" value="<?php echo h($search); ?>" placeholder="Cari nomor KTP / NIK atau nama pasien" /></div>
                <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Cari</button></div>
                <?php if ($search !== ''): ?><div class="col-auto"><a class="btn btn-outline-secondary" href="rekam_medis.php">Hapus pencarian</a></div><?php endif; ?>
            </form>
            <?php if (empty($records)): ?>
                <p class="text-muted mb-0">Belum ada rekam medis yang cocok dengan pencarian.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Nomor antrean</th><th>Tanggal / Jadwal</th><th>NIK</th><th>Pasien</th><th>Alamat</th><th>Jenis Bekam</th><th>Dokter</th><th>Status</th><th>Pengaturan</th></tr></thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($record['queue_number'])): ?>
                                            <strong class="queue-number">#<?php echo (int)$record['queue_number']; ?></strong>
                                        <?php else: ?>
                                            <span class="muted">Belum ditetapkan</span>
                                        <?php endif; ?>
                                        <?php if (!empty($record['patient_booking_id'])): ?>
                                            <form method="post" class="d-flex gap-2 mt-2">
                                                <input type="hidden" name="action" value="assign_queue" />
                                                <input type="hidden" name="record_id" value="<?php echo (int)$record['id']; ?>" />
                                                <input class="form-control form-control-sm" type="number" name="queue_number" min="1" value="<?php echo !empty($record['queue_number']) ? (int)$record['queue_number'] : ''; ?>" placeholder="Nomor" aria-label="Nomor antrean pasien <?php echo h($record['patient_name']); ?>" required />
                                                <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo !empty($record['queue_number']) ? 'Ubah' : 'Tetapkan'; ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h(date('d-m-Y', strtotime($record['visit_date'])) . ' ' . substr($record['visit_time'], 0, 5)); ?></td>
                                    <td><?php echo h($record['nik']); ?></td>
                                    <td><?php echo h($record['patient_name']); ?><br><small class="text-muted"><?php echo h($record['phone']); ?></small></td>
                                    <td><?php echo h($record['address']); ?></td>
                                    <td><?php echo h($record['cupping_type']); ?></td>
                                    <td><?php echo h($record['doctor_name'] ?: '-'); ?></td>
                                    <td><?php echo h(visit_status($record['visit_count'])); ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="rekam_medis.php?search=<?php echo urlencode($search); ?>&edit_id=<?php echo (int)$record['id']; ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="rekam_medis.php?print_id=<?php echo (int)$record['id']; ?>" target="_blank">Cetak</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0">Jumlah kunjungan ditentukan dari seluruh rekam medis dengan NIK yang sama.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($printRecord): ?>
    <article class="print-sheet">
        <h1>Hasil Pemeriksaan</h1>
        <p>Dicetak pada <?php echo h(date('d-m-Y H:i')); ?></p>
        <h2>Identitas Pasien</h2>
        <table>
            <tr><th>Nomor KTP / NIK</th><td><?php echo h($printRecord['nik']); ?></td></tr>
            <tr><th>Nama</th><td><?php echo h($printRecord['patient_name']); ?></td></tr>
            <tr><th>Alamat</th><td><?php echo nl2br(h($printRecord['address'])); ?></td></tr>
            <tr><th>Nomor Telepon</th><td><?php echo h($printRecord['phone']); ?></td></tr>
            <tr><th>Status Pasien</th><td><?php echo h(visit_status($printRecord['visit_count'])); ?> (kunjungan ke-<?php echo (int)$printRecord['visit_count']; ?>)</td></tr>
        </table>
        <h2>Informasi Pemeriksaan</h2>
        <table>
            <tr><th>Tanggal</th><td><?php echo h(date('d-m-Y', strtotime($printRecord['visit_date']))); ?></td></tr>
            <tr><th>Jadwal</th><td><?php echo h(substr($printRecord['visit_time'], 0, 5)); ?></td></tr>
            <tr><th>Nomor antrean</th><td><?php echo h($printRecord['queue_number'] ?: '-'); ?></td></tr>
            <tr><th>Dokter yang menangani</th><td><?php echo h($printRecord['doctor_name'] ?: '-'); ?></td></tr>
            <tr><th>Jenis bekam</th><td><?php echo h($printRecord['cupping_type']); ?></td></tr>
            <tr><th>Penanganan</th><td><?php echo nl2br(h($printRecord['treatment'] ?: '-')); ?></td></tr>
        </table>
        <p style="margin-top: 50mm; text-align: right">Dokter yang menangani,<br><br><br><?php echo h($printRecord['doctor_name'] ?: '____________________'); ?></p>
    </article>
    <script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
