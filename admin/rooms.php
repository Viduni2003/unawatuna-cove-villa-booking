<?php
/**
 * admin/rooms.php - lists every room (active and inactive) with booking counts.
 * Rooms that have booking history cannot be deleted, only deactivated.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$rooms = $pdo->query(
    "SELECT r.*,
            (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.room_id) AS booking_count,
            (SELECT COUNT(*) FROM bookings b
              WHERE b.room_id = r.room_id
                AND b.booking_status IN ('Pending','Confirmed')
                AND b.check_out >= CURDATE()) AS upcoming_count
     FROM rooms r
     ORDER BY r.room_status ASC, r.room_id ASC"
)->fetchAll();

$page_title = 'Manage Rooms';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="fw-bold mb-1">Manage Rooms</h1>
            <p class="mb-0">Add rooms, update details and prices, or take rooms out of service.</p>
        </div>
        <a href="<?= e(url('admin/room-form.php')) ?>" class="btn btn-warning fw-semibold">
            <i class="bi bi-plus-circle me-1"></i>Add new room
        </a>
    </div>
</div>

<div class="container py-4">
    <?php if ($rooms): ?>
        <div class="card shadow-sm border-0">
            <div class="table-responsive-stack">
                <table class="table align-middle mb-0 responsive-stack">
                    <thead class="table-light">
                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th class="text-end">Price / night</th>
                            <th>Max guests</th>
                            <th>Status</th>
                            <th>Bookings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <?php $is_active = $room['room_status'] === 'active'; ?>
                            <tr class="<?= $is_active ? '' : 'table-secondary' ?>">
                                <td data-label="Room">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= e(room_image_url((string) $room['image'])) ?>" alt=""
                                             class="admin-thumb d-none d-md-block">
                                        <div>
                                            <div class="fw-semibold"><?= e($room['room_name']) ?></div>
                                            <div class="small text-muted">ID <?= (int) $room['room_id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Type"><?= e($room['room_type']) ?></td>
                                <td data-label="Price / night" class="text-end"><?= e(format_lkr($room['price_per_night'])) ?></td>
                                <td data-label="Max guests"><?= (int) $room['max_guests'] ?></td>
                                <td data-label="Status"><?= status_badge((string) $room['room_status']) ?></td>
                                <td data-label="Bookings">
                                    <?= (int) $room['booking_count'] ?> total
                                    <?php if ((int) $room['upcoming_count'] > 0): ?>
                                        <div class="small text-muted"><?= (int) $room['upcoming_count'] ?> upcoming / active</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="<?= e(url('admin/room-form.php') . '?id=' . (int) $room['room_id']) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </a>

                                        <form method="post" action="<?= e(url('admin/room-delete.php')) ?>"
                                              data-confirm="<?= $is_active
                                                  ? 'Deactivate ' . e($room['room_name']) . '? It will be hidden from guests. Existing bookings are kept.'
                                                  : 'Re-activate ' . e($room['room_name']) . ' so guests can book it again?' ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="room_id" value="<?= (int) $room['room_id'] ?>">
                                            <input type="hidden" name="action" value="<?= $is_active ? 'deactivate' : 'activate' ?>">
                                            <button type="submit" class="btn btn-sm <?= $is_active ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                                <i class="bi <?= $is_active ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i><?= $is_active ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <?php if ((int) $room['booking_count'] === 0): ?>
                                            <form method="post" action="<?= e(url('admin/room-delete.php')) ?>"
                                                  data-confirm="Permanently delete <?= e($room['room_name']) ?>? This cannot be undone.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="room_id" value="<?= (int) $room['room_id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                    title="This room has booking history, so it can only be deactivated.">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-3">
            Rooms with booking history are protected by a database foreign key: deactivate them instead of deleting.
            Deactivated rooms disappear from the public pages but their past bookings stay in the records.
        </p>
    <?php else: ?>
        <div class="alert alert-info">No rooms yet. <a href="<?= e(url('admin/room-form.php')) ?>" class="alert-link">Add the first room</a>.</div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
