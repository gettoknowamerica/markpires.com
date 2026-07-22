Goliath Omni OS v57.1 — Columbo YouTube Vault + Indexer

Install:
1) Upload public_html/ over your existing public_html.
2) Run sql/goliath-v57-1-columbo-youtube.sql in Supabase.
3) Add the config snippet from docs/config-snippet-google-youtube.php.txt into public_html/lead-engine/config.php.
4) Fill in YOUTUBE_API_KEY and both channel constants.
5) Test:
   https://markpires.com/lead-engine/columbo-youtube-check.php?key=timetomakethedonuts
6) Index first batch:
   https://markpires.com/lead-engine/columbo-youtube-index.php?key=timetomakethedonuts&limit=25

Notes:
- Best channel values are actual channel IDs beginning with UC...
- Handles like @MarkInspiresTheWorld can work if YouTube resolves them.
- This release indexes videos. The next release turns indexed videos into Columbo archive assets and repurpose recommendations.
