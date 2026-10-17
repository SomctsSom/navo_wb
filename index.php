<?php
declare(strict_types=1);

/**
 * Fallback when rewrite rules are disabled: send visitors to the public site.
 */
$target = '/pw/public/';
$query = $_SERVER['QUERY_STRING'] ?? '';
if ($query !== '') {
    $target .= '?' . $query;
}
header('Location: ' . $target, true, 302);
exit;
