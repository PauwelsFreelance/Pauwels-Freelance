<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

try {
    $pdo = db();

    $tierRows = $pdo->query(
        'SELECT * FROM configurator_tiers WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();

    $featuresByTier = [];
    foreach ($pdo->query('SELECT * FROM configurator_tier_features ORDER BY sort_order ASC') as $f) {
        $featuresByTier[$f['tier_id']][] = $f['feature_text'];
    }

    $presetsByTier = [];
    foreach ($pdo->query('SELECT tier_id, addon_id FROM configurator_presets') as $p) {
        $presetsByTier[$p['tier_id']][] = (int)$p['addon_id'];
    }

    $addonRows = $pdo->query(
        'SELECT a.*, c.title AS category_title, c.sort_order AS category_sort
         FROM configurator_addons a
         JOIN configurator_addon_categories c ON c.id = a.category_id
         ORDER BY c.sort_order ASC, a.sort_order ASC'
    )->fetchAll();

    $addonIdToKey = [];
    $categories = [];
    foreach ($addonRows as $a) {
        $addonIdToKey[$a['id']] = $a['addon_key'];
        if (!isset($categories[$a['category_id']])) {
            $categories[$a['category_id']] = [
                'title' => $a['category_title'],
                'items' => [],
            ];
        }
        $categories[$a['category_id']]['items'][] = [
            'k'     => $a['addon_key'],
            'label' => $a['label'],
        ];
    }

    $tiers = array_map(function ($t) use ($featuresByTier, $presetsByTier, $addonIdToKey) {
        $presetKeys = array_map(
            fn($id) => $addonIdToKey[$id] ?? null,
            $presetsByTier[$t['id']] ?? []
        );
        return [
            'key'          => $t['tier_key'],
            'tag'          => $t['tag'],
            'name'         => $t['name'],
            'fullName'     => $t['full_name'],
            'durationText' => $t['duration_text'],
            'features'     => $featuresByTier[$t['id']] ?? [],
            'presets'      => array_values(array_filter($presetKeys)),
        ];
    }, $tierRows);

    echo json_encode([
        'tiers'      => $tiers,
        'categories' => array_values($categories),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/configurator.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load configurator right now.']);
}
