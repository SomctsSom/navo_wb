<?php
declare(strict_types=1);

require_method('POST');

$pdo = db();
$input = array_merge(request_input(), $_POST);
$settings = get_contact_form_settings($pdo);
$validated = contact_inquiry_validate($input, $settings, $pdo);
if (!$validated['ok']) {
    json_error((string) $validated['error'], 422);
}

$inserted = contact_inquiry_insert($pdo, $validated['data']);
if (!$inserted['ok']) {
    json_error((string) $inserted['error'], 500);
}

$inquiryId = (int) ($inserted['id'] ?? 0);
$notifyData = $validated['data'];
$notifyData['id'] = $inquiryId;
try {
    contact_inquiry_notify_email($notifyData);
} catch (Throwable $e) {
    error_log('contact_inquiry_notify_email exception: ' . $e->getMessage());
}

json_ok(['id' => $inquiryId], 'Thank you. Your message has been sent.', 201);
