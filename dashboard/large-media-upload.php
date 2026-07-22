<?php
/**
 * V20.1.2 Large Media Upload
 * Upload: /public_html/dashboard/large-media-upload.php
 */
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';
$key = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Large Media Upload</title>
<style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.hero{background:linear-gradient(135deg,#111827,#0b1020);color:white;padding:30px}
.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:42px;margin:0 0 8px}
.wrap{max-width:1200px;margin:auto;padding:22px}
.panel{background:white;border-radius:18px;padding:20px;box-shadow:0 4px 18px #0001;margin:16px 0}
.btn{background:#c8a96e;border:0;border-radius:10px;padding:12px 16px;font-weight:900;cursor:pointer}
input{display:block;margin:12px 0;font-size:16px}
.progress{height:22px;background:#eee;border-radius:99px;overflow:hidden;margin:14px 0}
.bar{height:100%;width:0;background:#c8a96e;transition:.15s}
pre{background:#111827;color:white;padding:14px;border-radius:12px;white-space:pre-wrap}
</style>
</head>
<body>
<section class="hero"><h1>Large Media Upload</h1><div>Chunk upload large videos directly into /uploads/media/raw/ without hitting normal PHP upload-size limits.</div></section>
<main class="wrap">
<section class="panel">
<h2>Upload Large Video / Audio</h2>
<input type="file" id="file" accept="video/*,audio/*,.mp4,.mov,.m4v,.webm,.mkv,.mp3,.wav,.m4a">
<button class="btn" id="upload">Upload In Chunks</button>
<div class="progress"><div class="bar" id="bar"></div></div>
<div id="status">Choose a file.</div>
</section>
<section class="panel">
<h2>After Upload</h2>
<pre>1. Upload completes here.
2. Open Media Director.
3. Choose the uploaded file from Existing Raw File.
4. Save project.
5. Run Media Director → Shorts Factory → Content Intelligence.</pre>
<p><a href="/dashboard/jessica-media-director.php">Open Media Director</a></p>
</section>
</main>
<script>
const KEY = <?=json_encode($key)?>;
const CHUNK_SIZE = 20 * 1024 * 1024;
const bar = document.getElementById('bar');
const statusEl = document.getElementById('status');

document.getElementById('upload').onclick = async () => {
  const file = document.getElementById('file').files[0];
  if(!file){ statusEl.textContent='Choose a file first.'; return; }

  const uploadId = 'up_' + Date.now() + '_' + Math.random().toString(36).slice(2);
  const total = Math.ceil(file.size / CHUNK_SIZE);
  statusEl.textContent = `Uploading ${file.name} in ${total} chunks...`;

  for(let i=0;i<total;i++){
    const start = i * CHUNK_SIZE;
    const end = Math.min(file.size, start + CHUNK_SIZE);
    const blob = file.slice(start,end);
    const fd = new FormData();
    fd.append('key', KEY);
    fd.append('upload_id', uploadId);
    fd.append('filename', file.name);
    fd.append('chunk_index', i);
    fd.append('total_chunks', total);
    fd.append('chunk', blob, file.name + '.part' + i);

    const res = await fetch('/lead-engine/chunk-upload.php', {method:'POST', body:fd});
    const data = await res.json();
    if(!data.success){
      statusEl.textContent = 'Upload failed: ' + (data.error || 'Unknown error');
      return;
    }

    const pct = Math.round(((i+1)/total)*100);
    bar.style.width = pct + '%';
    statusEl.textContent = `Uploaded chunk ${i+1} of ${total} (${pct}%)`;

    if(data.complete){
      statusEl.innerHTML = `Complete: <a target="_blank" href="${data.url}">${data.filename}</a>`;
    }
  }
};
</script>
</body>
</html>