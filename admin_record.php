<?php
require_once __DIR__ . '/config.php';
requireRole('admin');

$records = [
    'user' => ['User', 'manage_users_admin.php', 'users r', [
        'full_name' => ['Full name', 'text', 100], 'email' => ['Email address', 'email', 100],
        'phone' => ['Phone', 'tel', 20], 'address' => ['Address', 'text', 255],
    ]],
    'health_worker' => ['Health worker', 'health_workers_admin.php', 'health_workers r JOIN users u ON u.id = r.user_id', [
        'full_name' => ['Full name', 'text', 100, 'users'], 'email' => ['Email address', 'email', 100, 'users'],
        'phone' => ['Phone', 'tel', 20, 'users'], 'specialization' => ['Specialization', 'text', 100],
        'facility_name' => ['Facility', 'text', 150], 'license_number' => ['Licence number', 'text', 50],
        'years_of_experience' => ['Years of experience', 'number', 100],
    ]],
    'mother' => ['Mother record', 'mothers_admin.php', 'mothers r JOIN users u ON u.id = r.user_id', [
        'full_name' => ['Full name', 'text', 100, 'users'], 'email' => ['Email address', 'email', 100, 'users'],
        'phone' => ['Phone', 'tel', 20, 'users'], 'national_id' => ['National ID', 'text', 50],
        'occupation' => ['Occupation', 'text', 100], 'next_of_kin' => ['Next of kin', 'text', 100],
        'next_of_kin_phone' => ['Next of kin phone', 'tel', 20], 'emergency_contact' => ['Emergency contact', 'tel', 20],
    ]],
    'child' => ['Child record', 'children_admin.php', 'children r', [
        'full_name' => ['Full name', 'text', 100], 'date_of_birth' => ['Date of birth', 'date', 10],
        'gender' => ['Gender', ['male', 'female'], 6], 'birth_weight' => ['Birth weight (kg)', 'decimal', 999.99],
        'birth_length' => ['Birth length (cm)', 'decimal', 999.99], 'place_of_birth' => ['Place of birth', 'text', 150],
        'delivery_type' => ['Delivery type', 'text', 50],
    ]],
    'appointment' => ['Appointment', 'appointments_admin.php', 'appointments r', [
        'appointment_date' => ['Date', 'date', 10], 'appointment_time' => ['Time', 'time', 8],
        'reason' => ['Reason', 'text', 255], 'facility' => ['Facility', 'text', 150],
        'status' => ['Status', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'], 15],
        'notes' => ['Notes', 'textarea', 4000],
    ]],
];

$recordType = $recordType ?? '';
$readOnly = $readOnly ?? false;
if (!isset($records[$recordType])) {
    http_response_code(404);
    exit('Record type not found.');
}
[$label, $back, $source, $fields] = $records[$recordType];
$table = explode(' ', $source, 2)[0];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$db = getDB();
$columns = ['r.id'];
if (in_array($recordType, ['health_worker', 'mother'], true)) {
    $columns[] = 'r.user_id';
}
foreach ($fields as $name => $field) {
    $columns[] = (isset($field[3]) ? 'u.' : 'r.') . $name;
}
$stmt = $db->prepare('SELECT ' . implode(', ', $columns) . " FROM $source WHERE r.id = ?");
$stmt->execute([$id ?: 0]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
$error = '';
if (!$record) {
    http_response_code(404);
} elseif (!$readOnly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['admin_record_csrf'] ?? '', $token) || $token === '') {
        http_response_code(403);
        $error = 'This form expired. Reload the page and try again.';
    } else {
        $updates = [];
        foreach ($fields as $name => [$caption, $type, $limit]) {
            $value = $_POST[$name] ?? null;
            $value = is_string($value) ? trim($value) : null;
            $required = in_array($name, ['full_name', 'email', 'date_of_birth', 'gender', 'appointment_date', 'appointment_time', 'status'], true);
            $valid = $value !== null && ($value !== '' || !$required);
            if ($valid && $value !== '') {
                if (is_array($type)) $valid = in_array($value, $type, true);
                elseif ($type === 'number' || $type === 'decimal') {
                    $valid = preg_match($type === 'number' ? '/^\d+$/' : '/^\d+(?:\.\d{1,2})?$/', $value)
                        && (float) $value <= $limit;
                } else {
                    $valid = mb_strlen($value) <= $limit;
                    if ($type === 'email') $valid = $valid && filter_var($value, FILTER_VALIDATE_EMAIL);
                    if ($type === 'date') {
                        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                        $valid = $date && $date->format('Y-m-d') === $value;
                    }
                    if ($type === 'time') $valid = (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value);
                }
            }
            if (!$valid) {
                $error = 'Check the value for ' . $caption . '.';
                break;
            }
            $record[$name] = $value;
            $updates[$fields[$name][3] ?? $table][$name] = $value !== '' ? $value : null;
        }
        if ($error === '') {
            try {
                $db->beginTransaction();
                foreach ($updates as $target => $values) {
                    $assignments = array_map(static fn($name) => "$name = ?", array_keys($values));
                    $targetId = $target === $table ? $id : $record['user_id'];
                    $stmt = $db->prepare("UPDATE $target SET " . implode(', ', $assignments) . ' WHERE id = ?');
                    $stmt->execute([...array_values($values), $targetId]);
                }
                $db->commit();
                header('Location: ' . $back, true, 303);
                exit;
            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('MA-LIFE record update failed: ' . $e->getMessage());
                $error = $e->getCode() === '23000' ? 'A record already uses one of these values.' : 'The record could not be saved.';
            }
        }
    }
}
$_SESSION['admin_record_csrf'] ??= bin2hex(random_bytes(32));
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$title = $record ? ($readOnly ? $label . ' details' : 'Edit ' . strtolower($label)) : 'Record not found';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title) ?> | MA-LIFE</title>
    <link rel="stylesheet" href="interface.css">
</head>
<body class="ml-auth">
<main class="ml-auth-layout">
    <section class="ml-auth-form" style="width:min(640px,100%);background:#fff;border:1px solid var(--ml-border);border-radius:18px">
        <a href="<?= $escape($back) ?>">Back to list</a>
        <h1><?= $escape($title) ?></h1>
        <?php if (!$record): ?>
            <p>This record could not be found.</p>
        <?php elseif ($readOnly): ?>
            <dl>
                <?php foreach ($fields as $name => [$caption]): ?>
                    <dt><strong><?= $escape($caption) ?></strong></dt>
                    <dd><?= $escape($record[$name] ?? '—') ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php else: ?>
            <?php if ($error !== ''): ?><p class="ml-error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $escape($_SESSION['admin_record_csrf']) ?>">
                <?php foreach ($fields as $name => [$caption, $type, $limit]): ?>
                    <div>
                        <label for="<?= $name ?>"><?= $escape($caption) ?></label>
                        <?php if (is_array($type)): ?>
                            <select id="<?= $name ?>" name="<?= $name ?>">
                                <?php foreach ($type as $option): ?>
                                    <option value="<?= $escape($option) ?>" <?= $record[$name] === $option ? 'selected' : '' ?>><?= $escape(ucfirst(str_replace('_', ' ', $option))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'textarea'): ?>
                            <textarea id="<?= $name ?>" name="<?= $name ?>" maxlength="<?= $limit ?>" rows="4"><?= $escape($record[$name]) ?></textarea>
                        <?php else: ?>
                            <input id="<?= $name ?>" name="<?= $name ?>" type="<?= $type === 'decimal' ? 'number' : $type ?>" value="<?= $escape($record[$name]) ?>" <?= $type === 'decimal' ? 'min="0" step="0.01"' : '' ?> <?= $type === 'number' ? 'min="0" max="' . $limit . '"' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button class="ml-button ml-button-primary" type="submit">Save changes</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
