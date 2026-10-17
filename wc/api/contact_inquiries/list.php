<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('contact_inquiries.view');

$input = request_input();
[$page, $perPage, $offset] = paginate_params($input);
$q = trim((string) ($input['q'] ?? ''));
$status = trim((string) ($input['status'] ?? ''));

$where = ['deleted_at IS NULL'];
$params = [];
if ($q !== '') {
    $where[] = '(full_name LIKE :q OR company_name LIKE :q OR email LIKE :q OR phone LIKE :q OR message LIKE :q OR preferred_service_title LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($status !== '' && in_array($status, contact_inquiry_statuses(), true)) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}
$sqlWhere = 'WHERE ' . implode(' AND ', $where);
$pdo = db();
$cnt = $pdo->prepare("SELECT COUNT(*) FROM contact_inquiries {$sqlWhere}");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT * FROM contact_inquiries {$sqlWhere}
     ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$items = $stmt->fetchAll() ?: [];
foreach ($items as &$row) {
    $row['id'] = (int) $row['id'];
    $row['preferred_service_id'] = $row['preferred_service_id'] !== null ? (int) $row['preferred_service_id'] : null;
    $row['page_id'] = $row['page_id'] !== null ? (int) $row['page_id'] : null;
    $row['status_label'] = contact_inquiry_status_label((string) $row['status']);
    $row['created_at_display'] = format_display_time($row['created_at'] ?? null);
}
unset($row);

$totalPages = max(1, (int) ceil($total / max(1, $perPage)));
json_ok([
    'items' => $items,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
    ],
    'statuses' => array_map(
        static fn (string $s): array => ['value' => $s, 'label' => contact_inquiry_status_label($s)],
        contact_inquiry_statuses()
    ),
]);
