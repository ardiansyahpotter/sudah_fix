<?php
require_once __DIR__ . '/data.php';

$doctors = load_data('doctors');
$admin_phone = get_admin_setting('admin_phone', ADMIN_PHONE);

$selectedDoctorId = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : null;
$selectedDoctor = $selectedDoctorId ? get_doctor_by_id($selectedDoctorId) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profil Dokter - Klinik Sederhana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/style.css" />
</head>
<body>
<div class="container py-5">
    <header class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-0">Profil Dokter</h1>
            <p class="text-muted mb-0">Pilih dokter untuk melihat informasi lengkap dan rekomendasi</p>
        </div>
        <nav class="nav gap-2">
            <a class="nav-link px-3 py-2 rounded-3" href="index.php">Beranda</a>
            <a class="nav-link px-3 py-2 rounded-3 active" aria-current="page" href="doctors.php">Dokter</a>
        </nav>
    </header>

    <?php if (empty($doctors)): ?>
        <div class="alert alert-info">Belum ada dokter tersedia.</div>
    <?php else: ?>
        <div class="row g-4">
            <!-- List Dokter -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Daftar Dokter</h2>
                        <div class="list-group">
                            <?php foreach ($doctors as $doctor): ?>
                                <a href="doctors.php?doctor_id=<?php echo $doctor['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $selectedDoctorId === $doctor['id'] ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($doctor['name']); ?></h6>
                                        <small><?php echo htmlspecialchars($doctor['specialty']); ?></small>
                                    </div>
                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars($doctor['phone']); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Dokter -->
            <div class="col-lg-8">
                <?php if ($selectedDoctor): ?>
                    <!-- Profil Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h2 class="h4 mb-1"><?php echo htmlspecialchars($selectedDoctor['name']); ?></h2>
                                    <p class="text-primary fw-bold mb-2"><?php echo htmlspecialchars($selectedDoctor['specialty']); ?></p>
                                    <p class="text-muted mb-0">📞 <?php echo htmlspecialchars($selectedDoctor['phone']); ?></p>
                                </div>
                                <a class="btn btn-primary" href="https://wa.me/<?php echo $admin_phone; ?>" target="_blank">
                                    Hubungi via WhatsApp
                                </a>
                            </div>

                            <?php if (!empty($selectedDoctor['description'])): ?>
                                <div class="mb-4">
                                    <h5 class="mb-2">Tentang Dokter</h5>
                                    <p class="text-muted"><?php echo htmlspecialchars($selectedDoctor['description']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Gejala & Rekomendasi -->
                    <?php if (!empty($selectedDoctor['symptoms'])): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Dapat Menangani Gejala</h5>
                                <div class="row">
                                    <?php 
                                        $symptoms = array_filter(array_map('trim', explode(',', $selectedDoctor['symptoms'])));
                                        foreach ($symptoms as $symptom): 
                                    ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="p-3 bg-light rounded-3 border-start border-success border-5">
                                                <p class="mb-0">✓ <?php echo htmlspecialchars($symptom); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Pendidikan -->
                    <?php if (!empty($selectedDoctor['education'])): ?>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Riwayat Pendidikan</h5>
                                <div class="timeline">
                                    <?php 
                                        $educations = array_filter(array_map('trim', explode(';', $selectedDoctor['education'])));
                                        foreach ($educations as $idx => $edu): 
                                    ?>
                                        <div class="d-flex gap-3 <?php echo $idx < count($educations) - 1 ? 'mb-3' : ''; ?>">
                                            <div class="text-success">
                                                <div class="bg-success rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                    <span class="text-white fw-bold">✓</span>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($edu); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-secondary text-center py-5">
                        <p class="mb-0">Pilih dokter dari daftar untuk melihat informasi lengkapnya</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <footer class="text-center py-4 mt-5 text-muted border-top">
        <p class="mb-0">Hubungi admin untuk mendaftar atau membuat janji temu dengan dokter pilihan Anda.</p>
    </footer>
</div>
</body>
</html>
