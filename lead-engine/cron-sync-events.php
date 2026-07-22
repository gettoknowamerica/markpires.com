<?php
/**
 * EVENTS CALENDAR SYNC — cron-sync-events.php
 * Runs daily via cron job to pull Fairfield County events
 * from multiple public sources into a JSON cache file.
 *
 * SETUP on Hostinger:
 *   Cron: 0 4 * * * php /home/u123456789/domains/discoverct.net/public_html/lead-engine/cron-sync-events.php
 *   (runs at 4am daily — change u123456789 to your Hostinger username)
 *
 * SOURCES this hits:
 *   - Eventbrite (CT / Fairfield County public API)
 *   - Patch.com RSS feeds per town
 *   - CTvisit.com events RSS
 *   - Westport, Norwalk, Stamford official city event RSS
 *   - Google Events schema scrape (gentle)
 *
 * OUTPUT: /lead-engine/cache/events.json
 */

set_time_limit(120);
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_FILE', CACHE_DIR . '/events.json');
define('LOG_FILE',   CACHE_DIR . '/sync-log.txt');
define('EVENTBRITE_TOKEN', 'YOUR_EVENTBRITE_TOKEN'); // free at eventbrite.com/platform

if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

$all_events = [];
$errors     = [];
$log        = [];

// ─── UTILITY: HTTP GET ───────────────────────────────────────────────────
function http_get(string $url, array $headers = [], int $timeout = 15): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'DiscoverCT Events Bot/1.0 (+https://discoverct.net)',
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false, // for shared hosting SSL quirks
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) ? $body : null;
}

// ─── UTILITY: Parse RSS Feed ─────────────────────────────────────────────
function parse_rss(string $xml_str, string $source, string $default_category = 'local'): array {
    $events = [];
    try {
        $xml = @simplexml_load_string($xml_str);
        if (!$xml) return $events;
        foreach ($xml->channel->item as $item) {
            $date_str = (string)($item->pubDate ?? '');
            $ts       = $date_str ? strtotime($date_str) : time();
            if ($ts < strtotime('-1 day')) continue; // skip past events
            $events[] = [
                'id'       => md5((string)$item->link),
                'title'    => html_entity_decode(strip_tags((string)$item->title), ENT_QUOTES),
                'date'     => date('D, M j', $ts),
                'date_ts'  => $ts,
                'location' => '',
                'url'      => (string)$item->link,
                'category' => $default_category,
                'source'   => $source,
            ];
        }
    } catch (Exception $e) {}
    return $events;
}

// ─── SOURCE 1: Eventbrite API ─────────────────────────────────────────────
$log[] = "Fetching Eventbrite...";
try {
    // Fairfield County bounding box (rough): lat 41.0-41.25, lon -73.7 to -73.1
    $eb_url = 'https://www.eventbriteapi.com/v3/events/search/'
            . '?location.latitude=41.15&location.longitude=-73.35&location.within=40mi'
            . '&start_date.range_start=' . date('Y-m-d') . 'T00:00:00'
            . '&start_date.range_end=' . date('Y-m-d', strtotime('+30 days')) . 'T23:59:59'
            . '&status=live&expand=venue,category&page_size=50';
    $eb_res = http_get($eb_url, ["Authorization: Bearer " . EVENTBRITE_TOKEN]);
    if ($eb_res) {
        $eb     = json_decode($eb_res, true);
        $eb_cat_map = [
            '103' => 'music', '110' => 'food', '105' => 'arts',
            '108' => 'sports', '115' => 'family', '199' => 'local'
        ];
        foreach (($eb['events'] ?? []) as $ev) {
            $cat_id  = $ev['category_id'] ?? '199';
            $ts      = strtotime($ev['start']['local']);
            $all_events[] = [
                'id'       => 'eb_' . $ev['id'],
                'title'    => html_entity_decode($ev['name']['text'] ?? '', ENT_QUOTES),
                'date'     => date('D, M j', $ts),
                'date_ts'  => $ts,
                'location' => $ev['venue']['address']['localized_address_display'] ?? 'Fairfield County, CT',
                'url'      => $ev['url'],
                'category' => $eb_cat_map[$cat_id] ?? 'local',
                'source'   => 'Eventbrite',
            ];
        }
        $log[] = "Eventbrite: " . count($all_events) . " events";
    }
} catch (Exception $e) { $errors[] = "Eventbrite: " . $e->getMessage(); }

// ─── SOURCE 2: Patch.com RSS per town ────────────────────────────────────
$log[] = "Fetching Patch RSS feeds...";
$patch_feeds = [
    'westport-ct'  => 'https://patch.com/connecticut/westport/calendar/rss',
    'greenwich-ct' => 'https://patch.com/connecticut/greenwich/calendar/rss',
    'darien-ct'    => 'https://patch.com/connecticut/darien/calendar/rss',
    'norwalk-ct'   => 'https://patch.com/connecticut/norwalk/calendar/rss',
    'stamford-ct'  => 'https://patch.com/connecticut/stamford/calendar/rss',
    'ridgefield-ct'=> 'https://patch.com/connecticut/ridgefield/calendar/rss',
    'fairfield-ct' => 'https://patch.com/connecticut/fairfield/calendar/rss',
    'new-canaan-ct'=> 'https://patch.com/connecticut/newcanaan/calendar/rss',
];
foreach ($patch_feeds as $town => $url) {
    $rss = http_get($url);
    if ($rss) {
        $parsed = parse_rss($rss, "Patch — $town", 'local');
        // Add location from town slug
        foreach ($parsed as &$p) {
            $p['location'] = ucwords(str_replace('-ct','',str_replace('-',' ',$town))) . ', CT';
        }
        $all_events = array_merge($all_events, $parsed);
        $log[] = "Patch $town: " . count($parsed) . " items";
    }
    usleep(500000); // 0.5s between requests, be polite
}

// ─── SOURCE 3: CTvisit.com events RSS ─────────────────────────────────────
$log[] = "Fetching CTvisit.com...";
$ctvr = http_get('https://www.ctvisit.com/articles/rss/events');
if ($ctvr) {
    $parsed = parse_rss($ctvr, 'CTvisit.com', 'local');
    // Filter for Fairfield County only
    $fc_keywords = ['fairfield','westport','greenwich','darien','norwalk','stamford','ridgefield','bridgeport','shelton','trumbull'];
    foreach ($parsed as $p) {
        foreach ($fc_keywords as $kw) {
            if (stripos($p['title'].$p['location'], $kw) !== false) {
                $all_events[] = $p; break;
            }
        }
    }
}

// ─── SOURCE 4: Town official feeds ───────────────────────────────────────
$official_feeds = [
    ['url'=>'https://www.westportct.gov/government/news___notices/calendar_of_events','town'=>'Westport, CT','cat'=>'local'],
    ['url'=>'https://www.norwalkct.gov/calendar.aspx?CID=13','town'=>'Norwalk, CT','cat'=>'local'],
    ['url'=>'https://www.stamfordct.gov/government/departments/mayor_s_office/news___events','town'=>'Stamford, CT','cat'=>'local'],
];
foreach ($official_feeds as $feed) {
    $html = http_get($feed['url']);
    if ($html) {
        // Simple heuristic: look for schema.org Event microdata
        preg_match_all('/"name"\s*:\s*"([^"]{10,100})"[^}]*"startDate"\s*:\s*"([^"]+)"/s', $html, $m, PREG_SET_ORDER);
        foreach ($m as $match) {
            $ts = strtotime($match[2]);
            if ($ts < time()) continue;
            $all_events[] = [
                'id'       => md5($match[1].$feed['town']),
                'title'    => html_entity_decode($match[1], ENT_QUOTES),
                'date'     => date('D, M j', $ts),
                'date_ts'  => $ts,
                'location' => $feed['town'],
                'url'      => $feed['url'],
                'category' => $feed['cat'],
                'source'   => $feed['town'] . ' Official',
            ];
        }
        $log[] = "Official feed " . $feed['town'] . ": scraped";
    }
    usleep(300000);
}

// ─── DEDUPLICATE & SORT ───────────────────────────────────────────────────
$seen = [];
$unique = [];
foreach ($all_events as $ev) {
    if (!isset($seen[$ev['id']])) {
        $seen[$ev['id']] = true;
        $unique[] = $ev;
    }
}
usort($unique, fn($a,$b) => $a['date_ts'] <=> $b['date_ts']);

// Keep only future events, max 200
$future = array_filter($unique, fn($e) => $e['date_ts'] >= strtotime('-1 day'));
$future = array_slice(array_values($future), 0, 200);

// ─── WRITE CACHE ──────────────────────────────────────────────────────────
$cache = [
    'updated'     => date('Y-m-d H:i:s'),
    'count'       => count($future),
    'events'      => $future,
];
file_put_contents(CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(LOG_FILE, implode("\n", array_merge($log, $errors, ["Done: " . count($future) . " events cached at " . date('Y-m-d H:i:s')])) . "\n", FILE_APPEND);

echo "Done. " . count($future) . " events cached.\n";
