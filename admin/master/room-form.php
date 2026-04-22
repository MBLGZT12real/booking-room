<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('rooms.manage');

$db = db();
$id = intVal(qParam('id'));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Ruangan' : 'Tambah Ruangan';

if ($isEdit) {
    $room = $db->fetch("SELECT * FROM rooms WHERE id = ?", [$id]);
    if (!$room) { flash('error', 'Ruangan tidak ditemukan.'); redirect(BASE_URL . '/admin/master/rooms.php'); }
    $room['facilities_arr'] = jsonDecode($room['facilities'] ?? '[]');
} else {
    $room = [
        'name' => '', 'code' => '', 'location' => '', 'floor' => '',
        'capacity' => 10, 'description' => '', 'facilities' => '',
        'requires_approval' => 0, 'is_active' => 1, 'image' => null,
        'facilities_arr' => [],
    ];
}

$errors = [];

if (isPost() && verifyCsrf()) {
    $facilities = array_filter(array_map('trim', explode("\n", pParam('facilities_text'))));
    $data = [
        'name'             => trim(pParam('name')),
        'code'             => strtoupper(trim(pParam('code'))) ?: null,
        'location'         => trim(pParam('location')) ?: null,
        'floor'            => trim(pParam('floor')) ?: null,
        'capacity'         => intVal(pParam('capacity')),
        'description'      => trim(pParam('description')) ?: null,
        'facilities'       => !empty($facilities) ? json_encode(array_values($facilities)) : null,
        'requires_approval'=> (int) (pParam('requires_approval') === '1'),
        'is_active'        => (int) (pParam('is_active') === '1'),
    ];

    if (empty($data['name'])) $errors[] = 'Nama ruangan harus diisi.';
    if ($data['capacity'] < 1) $errors[] = 'Kapasitas minimal 1 orang.';

    // Check unique code
    if ($data['code']) {
        $existCode = $db->fetch("SELECT id FROM rooms WHERE code = ? AND id != ?", [$data['code'], $id ?: 0]);
        if ($existCode) $errors[] = 'Kode ruangan sudah digunakan.';
    }

    if (!$errors) {
        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'rooms');
            if (!$upload['success']) {
                $errors[] = $upload['message'];
            } else {
                // Delete old image
                if ($isEdit && $room['image']) deleteFile('rooms/' . $room['image']);
                $data['image'] = $upload['filename'];
            }
        } elseif ($isEdit) {
            $data['image'] = $room['image']; // Keep existing
        }
    }

    if (!$errors) {
        if ($isEdit) {
            $db->update('rooms', $data, ['id' => $id]);
            auditLog('update', 'rooms', 'Edit ruangan: ' . $data['name']);
            flash('success', 'Ruangan berhasil diperbarui.');
        } else {
            $db->insert('rooms', $data);
            auditLog('create', 'rooms', 'Tambah ruangan: ' . $data['name']);
            flash('success', 'Ruangan berhasil ditambahkan.');
        }
        redirect(BASE_URL . '/admin/master/rooms.php');
    }
    $room = array_merge($room, $data);
    $room['facilities_arr'] = $facilities;
}
?>
<?php include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="admin-wrapper">
<?php include dirname(__DIR__) . '/layout/sidebar.php'; ?>
<div class="main-content">
<?php include dirname(__DIR__) . '/layout/navbar.php'; ?>
<div class="page-content">

    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h4><i class="bi bi-door-open me-2 text-primary"></i><?= e($pageTitle) ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/master/rooms.php">Ruangan</a></li>
                    <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/master/rooms.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="row g-3">
            <!-- Left Column -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">Informasi Ruangan</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nama Ruangan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                       value="<?= e($room['name']) ?>" required
                                       placeholder="Contoh: Ruang Rapat A">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kode Ruangan</label>
                                <input type="text" class="form-control text-uppercase" name="code"
                                       value="<?= e($room['code'] ?? '') ?>"
                                       placeholder="RR-A" maxlength="20">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Lokasi / Gedung</label>
                                <input type="text" class="form-control" name="location"
                                       value="<?= e($room['location'] ?? '') ?>"
                                       placeholder="Contoh: Gedung Utama">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lantai</label>
                                <input type="text" class="form-control" name="floor"
                                       value="<?= e($room['floor'] ?? '') ?>"
                                       placeholder="Contoh: 2">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Deskripsi Ruangan</label>
                                <textarea class="form-control" name="description" rows="3"
                                          placeholder="Deskripsi singkat tentang ruangan..."><?= e($room['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facilities -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">Fasilitas Ruangan</h6>
                    </div>
                    <div class="card-body">
                        <label class="form-label">Daftar Fasilitas</label>
                        <textarea class="form-control" name="facilities_text" rows="6"
                                  placeholder="Tulis satu fasilitas per baris, contoh:&#10;Proyektor&#10;Whiteboard&#10;AC&#10;TV Monitor"><?= e(implode("\n", $room['facilities_arr'] ?? [])) ?></textarea>
                        <div class="form-text">Tulis satu fasilitas per baris.</div>

                        <!-- Common facilities quick-add -->
                        <div class="mt-2">
                            <small class="text-muted me-2">Cepat tambah:</small>
                            <?php foreach (['Proyektor', 'Whiteboard', 'AC', 'TV Monitor', 'Sound System', 'Microphone', 'Video Conference', 'Laser Pointer', 'Komputer'] as $fac): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1 fac-quick"
                                    data-fac="<?= e($fac) ?>"><?= e($fac) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-12 col-lg-4">
                <!-- Image Upload -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">Foto Ruangan</h6>
                    </div>
                    <div class="card-body">
                        <div id="imagePreview" class="mb-3 text-center">
                            <img src="<?= roomImageUrl($room['image'] ?? null) ?>"
                                 class="img-fluid rounded" style="max-height:200px;object-fit:cover;"
                                 id="previewImg" alt="Preview">
                        </div>
                        <input type="file" class="form-control form-control-sm" name="image"
                               id="imageInput" accept="image/*">
                        <div class="form-text">Format: JPG, PNG, WebP. Maks 2MB.</div>
                    </div>
                </div>

                <!-- Settings -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">Pengaturan Booking</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Kapasitas Peserta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="capacity"
                                       value="<?= (int) $room['capacity'] ?>" min="1" max="9999" required>
                                <span class="input-group-text">orang</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Perlu Persetujuan</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="requires_approval"
                                           id="approvalNo" value="0"
                                           <?= !$room['requires_approval'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="approvalNo">
                                        <span class="text-success fw-semibold">Otomatis Dikonfirmasi</span>
                                        <div class="text-muted small">Booking langsung confirmed tanpa approval</div>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="requires_approval"
                                           id="approvalYes" value="1"
                                           <?= $room['requires_approval'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="approvalYes">
                                        <span class="text-warning fw-semibold">Perlu Approval Admin</span>
                                        <div class="text-muted small">Booking masuk sebagai pending dulu</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Status Ruangan</label>
                            <select class="form-select" name="is_active">
                                <option value="1" <?= $room['is_active'] ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= !$room['is_active'] ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>
                        <?= $isEdit ? 'Perbarui Ruangan' : 'Simpan Ruangan' ?>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/master/rooms.php" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>
    </form>

</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
<script>
// Image preview
document.getElementById('imageInput')?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewImg').src = e.target.result;
        reader.readAsDataURL(this.files[0]);
    }
});

// Quick-add facilities
document.querySelectorAll('.fac-quick').forEach(btn => {
    btn.addEventListener('click', function() {
        const textarea = document.querySelector('[name="facilities_text"]');
        const fac = this.dataset.fac;
        const lines = textarea.value.split('\n').map(l => l.trim()).filter(Boolean);
        if (!lines.includes(fac)) {
            textarea.value = [...lines, fac].join('\n');
        }
    });
});

// Auto uppercase code
document.querySelector('[name="code"]')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
