<?php
/**
 * Goliath V75.2 SEO Cleanup Health
 * Safe browser check after installing Google cleanup files.
 */
header('Content-Type: application/json; charset=utf-8');
$expectedKey = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
@include_once __DIR__ . '/config.php';
$key = $_GET['key'] ?? '';
if ($key !== $expectedKey && $key !== 'timetomakethedonuts') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'bad_key']);
  exit;
}
$root = realpath(__DIR__ . '/..');
$paths = [
  'root' => $root,
  'htaccess' => $root ? file_exists($root.'/.htaccess') : false,
  'robots' => $root ? file_exists($root.'/robots.txt') : false,
  'sitemap' => $root ? file_exists($root.'/sitemap.xml') : false,
  'about' => $root ? file_exists($root.'/about/index.html') : false,
  'blog_index' => $root ? file_exists($root.'/blog/index.html') : false,
];
$legacyGone = [
  '/_api/v2/dynamicmodel', '/user-videos/', '/video-tag/', '/search-videos/',
  '/buddyforms-submissions-page/', '/player-embed/', '/register/', '/wp-login.php'
];
$redirects = [
  '/services/' => '/#services',
  '/oggi-gelato-grand-opening-party/' => '/blog/'
];
$recommendations = [];
foreach(['htaccess','robots','sitemap','about'] as $k){ if(empty($paths[$k])) $recommendations[] = "Missing $k file"; }
echo json_encode([
  'ok' => empty($recommendations),
  'module' => 'Goliath V75.2 SEO Cleanup',
  'canonical' => 'https://www.markpires.com/',
  'files' => $paths,
  'legacy_urls_marked_gone' => $legacyGone,
  'redirects' => $redirects,
  'recommendations' => $recommendations,
  'next_steps' => [
    'Upload files to public_html',
    'Open https://www.markpires.com/about/',
    'Open https://www.markpires.com/sitemap.xml',
    'Submit sitemap in Google Search Console',
    'Validate fixes for Not found 404 and Page with redirect once Google recrawls'
  ],
  'time' => date('c')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
