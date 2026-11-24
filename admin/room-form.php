<?php
/**
 * admin/room-form.php - one form for adding (no id) and editing (?id=) a room.
 * Includes server-side validation and safe image upload (MIME type check,
 * size limit, random file name). Requires the PHP "fileinfo" extension
 * (enabled by default in XAMPP and WAMP).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

const ROOM_IMAGE_MAX_BYTES = 2097152;   // 2 MB

/**
 * Validates and stores an uploaded room image.
 * Returns the new file name, or null when nothing was uploaded / on error ($error is set).
 */
function save_room_image(array $file, ?string &$error): ?string
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The image is too large. Maximum size is 2 MB.';
        return null;
    }
    if ($code !== UPLOAD_ERR_OK) {
        $error = 'The image could not be uploaded (error code ' . (int) $code . ').';
        return null;
    }
    if ((int) $file['size'] > ROOM_IMAGE_MAX_BYTES) {
        $error = 'The image is too large. Maximum size is 2 MB.';
        return null;
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        $error = 'Invalid upload.';
        return null;
    }

    // Check the real content type, not the file name or the browser's claim
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowed[$mime]) || @getimagesize((string) $file['tmp_name']) === false) {
        $error = 'Only JPG, PNG or WEBP images are allowed.';
        return null;
    }

    if (!is_dir(ROOM_IMAGE_DIR) && !mkdir(ROOM_IMAGE_DIR, 0755, true) && !is_dir(ROOM_IMAGE_DIR)) {
        $error = 'The image folder could not be created.';
        return null;
    }

    $name = 'room_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], ROOM_IMAGE_DIR . '/' . $name)) {
        $error = 'The image could not be saved. Check that assets/images/rooms is writable.';
        return null;
    }
    return $name;
}

/** "Wi-Fi, Pool,  wi-fi" becomes "Wi-Fi,Pool" (trimmed, de-duplicated, comma separated). */
function normalise_facilities(string $input): string
{
    $clean = [];
    foreach (preg_split('/[,\r\n]+/', $input) ?: [] as $part) {
        $part = trim((string) preg_replace('/\s+/', ' ', $part));
        $key  = mb_strtolower($part);
        if ($part !== '' && !isset($clean[$key])) {
            $clean[$key] = $part;
        }
    }
    return implode(',', $clean);
}

/* ---- Add or edit? ---- */
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = $room_id !== false && $room_id !== null;
$room    = null;

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE room_id = ? LIMIT 1');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    if (!$room) {
        flash_set('danger', 'That room could not be found.');
        redirect(url('admin/rooms.php'));
    }
}

/* ---- Form values: from the database (edit), defaults (add) or the failed POST ---- */
$v = [
    'room_name'       => $room['room_name'] ?? '',
    'room_type'       => $room['room_type'] ?? '',
    'description'     => $room['description'] ?? '',
    'facilities'      => isset($room['facilities']) ? str_replace(',', ', ', $room['facilities']) : '',
    'price_per_night' => $room['price_per_night'] ?? '',
    'max_guests'      => $room['max_guests'] ?? '2',
    'room_status'     => $room['room_status'] ?? 'active',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    foreach (['room_name', 'room_type', 'description', 'facilities', 'price_per_night', 'max_guests', 'room_status'] as $field) {
        $v[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    // ---- Server-side validation ----
    if (mb_strlen($v['room_name']) < 2 || mb_strlen($v['room_name']) > 100) {
        $errors['room_name'] = 'Room name must be between 2 and 100 characters.';
    }
    if (mb_strlen($v['room_type']) < 2 || mb_strlen($v['room_type']) > 50) {
        $errors['room_type'] = 'Room type must be between 2 and 50 characters.';
    }
    if (mb_strlen($v['description']) < 10 || mb_strlen($v['description']) > 2000) {
        $errors['description'] = 'Description must be between 10 and 2000 characters.';
    }

    $facilities = normalise_facilities($v['facilities']);
    if ($facilities === '') {
        $errors['facilities'] = 'Enter at least one facility (separate several with commas).';
    } elseif (mb_strlen($facilities) > 255) {
        $errors['facilities'] = 'The facilities list is too long (255 characters maximum).';
    }

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v['price_per_night']) || (float) $v['price_per_night'] <= 0) {
        $errors['price_per_night'] = 'Enter a price greater than 0 using digits only, e.g. 18500 or 18500.50.';
    }

    $max_guests = filter_var($v['max_guests'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($max_guests === false) {
        $errors['max_guests'] = 'Maximum guests must be a whole number between 1 and 20.';
    }

    if (!in_array($v['room_status'], ['active', 'inactive'], true)) {
        $errors['room_status'] = 'Please choose a valid status.';
    }

    // ---- Image upload (only when the rest of the form is valid) ----
    $new_image = null;
    if (!$errors) {
        $upload_error = null;
        $new_image    = save_room_image($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $upload_error);
        if ($upload_error !== null) {
            $errors['image'] = $upload_error;
        }
    }

    // ---- Save ----
    if (!$errors) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare(
                    'UPDATE rooms
                     SET room_name = ?, room_type = ?, description = ?, facilities = ?,
                         price_per_night = ?, max_guests = ?, image = ?, room_status = ?
                     WHERE room_id = ?'
                );
                $stmt->execute([
                    $v['room_name'], $v['room_type'], $v['description'], $facilities,
                    $v['price_per_night'], $max_guests, $new_image ?? $room['image'], $v['room_status'],
                    $room_id,
                ]);

                if ($new_image !== null) {
                    delete_uploaded_room_image((string) $room['image']);   // remove the replaced upload
                }
                flash_set('success', 'Room "' . $v['room_name'] . '" was updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO rooms (room_name, room_type, description, facilities, price_per_night, max_guests, image, room_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $v['room_name'], $v['room_type'], $v['description'], $facilities,
                    $v['price_per_night'], $max_guests, $new_image ?? '', $v['room_status'],
                ]);
                flash_set('success', 'Room "' . $v['room_name'] . '" was added.');
            }
            redirect(url('admin/rooms.php'));
        } catch (PDOException $ex) {
            if ($new_image !== null) {
                delete_uploaded_room_image($new_image);   // do not leave an orphan file behind
            }
            error_log('Room save failed: ' . $ex->getMessage());
            $errors['general'] = 'The room could not be saved because of a system error. Please try again.';
        }
    }
}

$types = $pdo->query('SELECT DISTINCT room_type FROM rooms ORDER BY room_type')->fetchAll(PDO::FETCH_COLUMN);

$page_title = $is_edit ? 'Edit Room' : 'Add Room';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(url('admin/index.php')) ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('admin/rooms.php')) ?>">Rooms</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $is_edit ? 'Edit' : 'Add' ?></li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h3 fw-bold mb-4"><?= $is_edit ? 'Edit room: ' . e($room['room_name']) : 'Add a new room' ?></h1>

                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="" enctype="multipart/form-data" id="roomForm" data-validate="room" novalidate>
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="room_name" class="form-label fw-semibold">Room name</label>
                                <input type="text" class="form-control <?= isset($errors['room_name']) ? 'is-invalid' : '' ?>"
                                       id="room_name" name="room_name" maxlength="100" value="<?= e($v['room_name']) ?>" required>
                                <div class="invalid-feedback"><?= e($errors['room_name'] ?? '') ?></div>
                            </div>

                            <div class="col-md-4">
                                <label for="room_type" class="form-label fw-semibold">Room type</label>
                                <input type="text" class="form-control <?= isset($errors['room_type']) ? 'is-invalid' : '' ?>"
                                       id="room_type" name="room_type" maxlength="50" list="typeList"
                                       value="<?= e($v['room_type']) ?>" placeholder="e.g. Deluxe" required>
                                <datalist id="typeList">
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= e($t) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="invalid-feedback"><?= e($errors['room_type'] ?? '') ?></div>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label fw-semibold">Description</label>
                                <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                                          id="description" name="description" rows="4" maxlength="2000" required><?= e($v['description']) ?></textarea>
                                <div class="invalid-feedback"><?= e($errors['description'] ?? '') ?></div>
                            </div>

                            <div class="col-12">
                                <label for="facilities" class="form-label fw-semibold">Facilities</label>
                                <textarea class="form-control <?= isset($errors['facilities']) ? 'is-invalid' : '' ?>"
                                          id="facilities" name="facilities" rows="2" maxlength="400"
                                          placeholder="Air Conditioning, Free Wi-Fi, Sea View Balcony" required><?= e($v['facilities']) ?></textarea>
                                <div class="form-text">Separate facilities with commas.</div>
                                <div class="invalid-feedback"><?= e($errors['facilities'] ?? '') ?></div>
                            </div>

                            <div class="col-md-4">
                                <label for="price_per_night" class="form-label fw-semibold">Price per night (LKR)</label>
                                <input type="text" inputmode="decimal"
                                       class="form-control <?= isset($errors['price_per_night']) ? 'is-invalid' : '' ?>"
                                       id="price_per_night" name="price_per_night" value="<?= e($v['price_per_night']) ?>" required>
                                <div class="invalid-feedback"><?= e($errors['price_per_night'] ?? '') ?></div>
                            </div>

                            <div class="col-md-4">
                                <label for="max_guests" class="form-label fw-semibold">Maximum guests</label>
                                <input type="number" min="1" max="20"
                                       class="form-control <?= isset($errors['max_guests']) ? 'is-invalid' : '' ?>"
                                       id="max_guests" name="max_guests" value="<?= e($v['max_guests']) ?>" required>
                                <div class="invalid-feedback"><?= e($errors['max_guests'] ?? '') ?></div>
                            </div>

                            <div class="col-md-4">
                                <label for="room_status" class="form-label fw-semibold">Status</label>
                                <select class="form-select <?= isset($errors['room_status']) ? 'is-invalid' : '' ?>"
                                        id="room_status" name="room_status" required>
                                    <option value="active"<?= $v['room_status'] === 'active' ? ' selected' : '' ?>>Active (bookable)</option>
                                    <option value="inactive"<?= $v['room_status'] === 'inactive' ? ' selected' : '' ?>>Inactive (hidden)</option>
                                </select>
                                <div class="invalid-feedback"><?= e($errors['room_status'] ?? '') ?></div>
                            </div>

                            <div class="col-12">
                                <label for="image" class="form-label fw-semibold">
                                    Room image <?= $is_edit ? '<span class="text-muted fw-normal">(leave empty to keep the current image)</span>' : '<span class="text-muted fw-normal">(optional)</span>' ?>
                                </label>
                                <div class="row g-3 align-items-center">
                                    <div class="col-sm-4">
                                        <img id="imagePreview" class="img-fluid rounded border"
                                             src="<?= e(room_image_url((string) ($room['image'] ?? ''))) ?>" alt="Room image preview">
                                    </div>
                                    <div class="col-sm-8">
                                        <input type="file" class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>"
                                               id="image" name="image" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text">JPG, PNG or WEBP, maximum 2 MB.</div>
                                        <div class="invalid-feedback"><?= e($errors['image'] ?? '') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-ocean btn-lg">
                                <i class="bi bi-save me-1"></i><?= $is_edit ? 'Save changes' : 'Add room' ?>
                            </button>
                            <a href="<?= e(url('admin/rooms.php')) ?>" class="btn btn-outline-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
