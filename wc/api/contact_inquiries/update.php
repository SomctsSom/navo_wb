<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('contact_inquiries.edit');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$id = (int) ($input['id'] ?? 0);
$status = trim((string) ($input['status'] ?? ''));
$replyMessage = trim((string) ($input['reply_message'] ?? ''));
if ($id < 1) {
    json_error('Invalid id', 422);
}
if (!in_array($status, contact_inquiry_statuses(), true)) {
    json_error('Invalid status', 422);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT id, status, reply_message, replied_at FROM contact_inquiries WHERE id = :id AND deleted_at IS NULL'
);
$stmt->execute([':id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Not found', 404);
}

$currentStatus = (string) ($existing['status'] ?? 'new');
$allowedNext = contact_inquiry_next_status($currentStatus);
if ($allowedNext === null) {
    json_error('This inquiry is already closed.', 422);
}
if ($status !== $allowedNext) {
    json_error(
        'Next status must be ' . contact_inquiry_status_label($allowedNext) . '.',
        422
    );
}
if ($status === 'replied' && $replyMessage === '') {
    json_error('Please enter the reply message.', 422);
}

$now = now_utc();
$params = [
    ':status' => $status,
    ':u' => $now,
    ':by' => $actor['id'],
    ':id' => $id,
];

if ($status === 'replied') {
    $sql = 'UPDATE contact_inquiries
            SET status = :status,
                reply_message = :reply,
                replied_at = COALESCE(replied_at, :replied_at),
                updated_at = :u,
                updated_by = :by
            WHERE id = :id';
    $params[':reply'] = $replyMessage;
    $params[':replied_at'] = $now;
} else {
    $sql = 'UPDATE contact_inquiries
            SET status = :status,
                updated_at = :u,
                updated_by = :by
            WHERE id = :id';
}

$pdo->prepare($sql)->execute($params);
audit_log((int) $actor['id'], 'update', 'contact_inquiries', $id, [
    'status' => $status,
    'from' => $currentStatus,
    'reply_saved' => $status === 'replied',
]);
json_ok([
    'id' => $id,
    'status' => $status,
    'reply_message' => $status === 'replied' ? $replyMessage : ($existing['reply_message'] ?? null),
], 'Status updated');
