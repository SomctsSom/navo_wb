<?php
declare(strict_types=1);

require_method('GET');

$pdo = db();
$stmt = $pdo->query(
    "SELECT company_name, tagline, logo_path
     FROM contact_infos
     WHERE deleted_at IS NULL AND is_active = 1
     ORDER BY sort_order ASC, id ASC
     LIMIT 1"
);
$row = $stmt ? ($stmt->fetch() ?: null) : null;

$companyName = trim((string) ($row['company_name'] ?? ''));
$tagline = trim((string) ($row['tagline'] ?? ''));
$logoUrl = null;
if ($row && !empty($row['logo_path'])) {
    $logoUrl = secure_public_media_url((string) $row['logo_path']);
}

json_ok([
    'company_name' => $companyName !== '' ? $companyName : null,
    'tagline' => $tagline !== '' ? $tagline : null,
    'logo_url' => $logoUrl,
]);
