<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

$rss_url = 'https://diarpus.kukarkab.go.id/feed/';
$ctx = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'KukarQuiz/1.0'
    ]
]);

$xml = @file_get_contents($rss_url, false, $ctx);

if (!$xml) {
    http_response_code(502);
    echo json_encode(['error' => 'Gagal mengambil feed', 'items' => []]);
    exit;
}

$xml = simplexml_load_string($xml);
if (!$xml || !isset($xml->channel->item)) {
    http_response_code(502);
    echo json_encode(['error' => 'Format feed tidak valid', 'items' => []]);
    exit;
}

$items = [];
$count = 0;
foreach ($xml->channel->item as $item) {
    if ($count >= 3) break;

    $title = (string)($item->title ?? '');
    $link  = (string)($item->link ?? '');
    $dateStr = (string)($item->pubDate ?? '');
    $desc = strip_tags((string)($item->description ?? ''));

    if (!$title || !$link) continue;

    $dateObj = $dateStr ? new DateTime($dateStr) : new DateTime();
    $items[] = [
        'title' => $title,
        'link'  => $link,
        'date'  => $dateObj->format('d M Y'),
        'date_iso' => $dateObj->format('Y-m-d'),
        'desc'  => mb_substr($desc, 0, 160)
    ];
    $count++;
}

echo json_encode(['items' => $items]);