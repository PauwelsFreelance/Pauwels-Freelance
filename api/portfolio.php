<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

try {
    $rows = db()->query(
        'SELECT title, description, tags, image_filename, cta_text, contact_type
         FROM portfolio_projects WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();

    $projects = array_map(function ($r) {
        return [
            'title'       => $r['title'],
            'description' => $r['description'],
            'tags'        => array_values(array_filter(array_map('trim', explode(',', $r['tags'])))),
            'image'       => 'assets/' . $r['image_filename'],
            'ctaText'     => $r['cta_text'],
            'contactType' => $r['contact_type'],
        ];
    }, $rows);

    echo json_encode(['projects' => $projects], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/portfolio.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load portfolio right now.']);
}
