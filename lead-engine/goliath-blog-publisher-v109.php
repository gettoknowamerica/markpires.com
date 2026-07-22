<?php
/**
 * GOLIATH V109.0 BLOG PUBLISHER
 * Publishes only fully approved/enhanced Shakespeare packages.
 * Uses /public_html/blog/blog-template.html and updates /public_html/blog/index.html.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';

function bp_key(): string {
    if (defined('AFTER_HOURS_CRON_KEY')) return (string) AFTER_HOURS_CRON_KEY;
    if (defined('RETELL_WEBHOOK_KEY')) return (string) RETELL_WEBHOOK_KEY;
    return 'timetomakethedonuts';
}

function bp_one(string $sql, array $params = []): ?array {
    try { return gdb_one($sql, $params) ?: null; }
    catch (Throwable $e) { return null; }
}

function bp_all(string $sql, array $params = []): array {
    try { return gdb_all($sql, $params) ?: []; }
    catch (Throwable $e) { return []; }
}

function bp_table(string $table): bool {
    $row = bp_one(
        "SELECT COUNT(*) c FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name=?",
        [$table]
    );
    return (int)($row['c'] ?? 0) > 0;
}

function bp_col(string $table, string $column): bool {
    $row = bp_one(
        "SELECT COUNT(*) c FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",
        [$table, $column]
    );
    return (int)($row['c'] ?? 0) > 0;
}

function bp_update(string $table, int $id, array $row): void {
    $safe = [];
    foreach ($row as $key => $value) {
        if (bp_col($table, $key)) $safe[$key] = $value;
    }
    if ($safe) gdb_update($table, $safe, 'id=:id', ['id' => $id]);
}

function bp_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string)$text, '-');
}

function bp_replace(string $template, array $values): string {
    foreach ($values as $key => $value) {
        $tokens = [
            '{{' . $key . '}}',
            '{{ ' . $key . ' }}',
            '[[' . $key . ']]',
            '%' . strtoupper($key) . '%',
        ];
        $template = str_replace($tokens, (string)$value, $template);
    }
    return $template;
}

$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if (!hash_equals(bp_key(), $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'bad_key'], JSON_PRETTY_PRINT);
    exit;
}

$limit = max(1, min(3, (int)($_GET['limit'] ?? 1)));
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/\\');
$blogDir = $docRoot . '/blog';
$templatePath = $blogDir . '/blog-template.html';
$indexPath = $blogDir . '/index.html';

if (!is_file($templatePath)) {
    echo json_encode([
        'ok' => false,
        'version' => 'V109.0 Blog Publisher',
        'error' => 'missing_blog_template',
        'expected_path' => $templatePath,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_file($indexPath)) {
    echo json_encode([
        'ok' => false,
        'version' => 'V109.0 Blog Publisher',
        'error' => 'missing_blog_index',
        'expected_path' => $indexPath,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$table = bp_table('shakespeare_content_packages')
    ? 'shakespeare_content_packages'
    : (bp_table('shakespeare_campaign_packages') ? 'shakespeare_campaign_packages' : '');

if ($table === '') {
    echo json_encode([
        'ok' => false,
        'version' => 'V109.0 Blog Publisher',
        'error' => 'no_shakespeare_package_table',
    ], JSON_PRETTY_PRINT);
    exit;
}

/*
 * Publish only packages that have passed approval and are not already published.
 * Column checks keep this compatible with both package-table versions.
 */
$where = ["1=1"];
if (bp_col($table, 'status')) {
    $where[] = "status IN ('approved','ready_to_publish','publication_ready')";
}
if (bp_col($table, 'published_path')) {
    $where[] = "(published_path IS NULL OR published_path='')";
}
if (bp_col($table, 'approval_status')) {
    $where[] = "approval_status='approved'";
}
if (bp_col($table, 'verification_score')) {
    $where[] = "COALESCE(verification_score,0) >= 70";
}
if (bp_col($table, 'seo_score')) {
    $where[] = "COALESCE(seo_score,0) >= 70";
}

$order = bp_col($table, 'authority_score')
    ? 'authority_score DESC, id ASC'
    : 'id ASC';

$packages = bp_all(
    "SELECT * FROM `$table`
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $order
     LIMIT $limit"
);

$template = file_get_contents($templatePath);
$index = file_get_contents($indexPath);
$published = [];

foreach ($packages as $package) {
    $id = (int)$package['id'];
    $title = trim((string)($package['title'] ?? 'Untitled Article'));
    $slug = trim((string)($package['slug'] ?? '')) ?: bp_slug($title);
    $html = (string)($package['html_content'] ?? $package['content_html'] ?? $package['content'] ?? '');
    $metaTitle = trim((string)($package['meta_title'] ?? '')) ?: $title;
    $metaDescription = trim((string)($package['meta_description'] ?? ''));
    if ($metaDescription === '') {
        $metaDescription = mb_substr(trim(strip_tags($html)), 0, 155);
    }

    if ($html === '') continue;

    $publishedPath = '/blog/' . $slug . '.html';
    $absolutePath = $blogDir . '/' . $slug . '.html';

    $page = bp_replace($template, [
        'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
        'meta_title' => htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'),
        'meta_description' => htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'),
        'content' => $html,
        'article_content' => $html,
        'slug' => $slug,
        'published_date' => date('F j, Y'),
        'canonical_url' => 'https://www.markpires.com' . $publishedPath,
    ]);

    /*
     * If the template has no known content token, inject before </main> or </body>.
     */
    if ($page === $template) {
        $insertion = "\n<article class=\"goliath-blog-article\">\n<h1>"
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . "</h1>\n" . $html . "\n</article>\n";
        if (stripos($page, '</main>') !== false) {
            $page = str_ireplace('</main>', $insertion . '</main>', $page);
        } else {
            $page = str_ireplace('</body>', $insertion . '</body>', $page);
        }
    }

    file_put_contents($absolutePath, $page, LOCK_EX);

    $card = "\n<!-- GOLIATH_BLOG:$slug -->\n"
        . '<article class="blog-card" data-goliath-blog="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">'
        . '<h2><a href="' . htmlspecialchars($publishedPath, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</a></h2>'
        . '<p>' . htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<a class="read-more" href="' . htmlspecialchars($publishedPath, ENT_QUOTES, 'UTF-8') . '">Read Article</a>'
        . '</article>'
        . "\n<!-- /GOLIATH_BLOG:$slug -->\n";

    if (strpos($index, 'GOLIATH_BLOG:' . $slug) === false) {
        if (strpos($index, '<!-- GOLIATH_BLOG_LIST -->') !== false) {
            $index = str_replace('<!-- GOLIATH_BLOG_LIST -->', '<!-- GOLIATH_BLOG_LIST -->' . $card, $index);
        } elseif (stripos($index, '</main>') !== false) {
            $index = str_ireplace('</main>', $card . '</main>', $index);
        } else {
            $index = str_ireplace('</body>', $card . '</body>', $index);
        }
    }

    bp_update($table, $id, [
        'published_path' => $publishedPath,
        'status' => 'published',
        'approval_status' => 'approved',
        'published_at' => gdb_now(),
        'updated_at' => gdb_now(),
    ]);

    $published[] = [
        'id' => $id,
        'title' => $title,
        'path' => $publishedPath,
    ];
}

if ($published) {
    file_put_contents($indexPath, $index, LOCK_EX);
}

echo json_encode([
    'ok' => true,
    'version' => 'V109.0 Blog Publisher',
    'table' => $table,
    'published_count' => count($published),
    'published' => $published,
    'template' => $templatePath,
    'index' => $indexPath,
    'time' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>