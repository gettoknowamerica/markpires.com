<?php
/**
 * Mark Pires Music EPK
 * Upload to: /public_html/mark-pires-music.php
 *
 * Add your real photos to:
 * /assets/epk/mark-live-1.jpg
 * /assets/epk/mark-live-2.jpg
 * /assets/epk/mark-live-3.jpg
 *
 * Add MP3s to:
 * /assets/epk/song-01.mp3 ... song-10.mp3
 *
 * Add optional videos:
 * /assets/epk/epk-highlight.mp4
 * /assets/epk/beatseat-demo.mp4
 */
$EPK_PRIVATE = true; // set false when ready to make public
$key = $_GET['key'] ?? '';
$accessKey = 'timetomakethedonuts';
if($EPK_PRIVATE && $key !== $accessKey){
  http_response_code(404);
  echo "Page not found.";
  exit;
}

$songs = [
  ['title'=>'Original Song 1', 'file'=>'/assets/epk/song-01.mp3'],
  ['title'=>'Original Song 2', 'file'=>'/assets/epk/song-02.mp3'],
  ['title'=>'Original Song 3', 'file'=>'/assets/epk/song-03.mp3'],
  ['title'=>'Original Song 4', 'file'=>'/assets/epk/song-04.mp3'],
  ['title'=>'Original Song 5', 'file'=>'/assets/epk/song-05.mp3'],
  ['title'=>'Original Song 6', 'file'=>'/assets/epk/song-06.mp3'],
  ['title'=>'Original Song 7', 'file'=>'/assets/epk/song-07.mp3'],
  ['title'=>'Original Song 8', 'file'=>'/assets/epk/song-08.mp3'],
  ['title'=>'Original Song 9', 'file'=>'/assets/epk/song-09.mp3'],
  ['title'=>'Original Song 10', 'file'=>'/assets/epk/song-10.mp3'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mark Pires Music EPK | Live Songwriting Concert Series + BeatSeat</title>
<meta name="description" content="Mark Pires music EPK: live songwriting concert series, BeatSeat inventor, former MTV artist, Song of the Year finalist, 209 original songs.">
<style>
:root{--gold:#d4af37;--bg:#05070d;--panel:#0b1220;--ink:#f8fafc;--muted:#94a3b8;--line:rgba(255,255,255,.14)}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 10% 0%,rgba(212,175,55,.22),transparent 28%),linear-gradient(180deg,#05070d,#0f172a 70%,#05070d);color:var(--ink);font-family:Inter,Arial,sans-serif}
a{color:inherit}.wrap{width:min(1180px,92vw);margin:auto}.hero{min-height:86vh;display:grid;grid-template-columns:1.05fr .95fr;gap:34px;align-items:center;padding:54px 0}
.kicker{color:var(--gold);font-weight:1000;letter-spacing:.14em;text-transform:uppercase;font-size:13px}.hero h1{font-size:clamp(42px,7vw,82px);line-height:.94;margin:12px 0}.hero p{color:#cbd5e1;font-size:20px;line-height:1.55}
.ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border-radius:999px;padding:13px 18px;font-weight:1000;border:1px solid var(--line);background:#111827}.btn.gold{background:linear-gradient(135deg,#f5d48b,#d4af37);color:#111827;border:0}.btn:hover{transform:translateY(-1px)}
.photo{border-radius:30px;overflow:hidden;border:1px solid rgba(212,175,55,.35);background:#111827;box-shadow:0 40px 100px rgba(0,0,0,.45)}.photo img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}.caption{padding:14px;color:#cbd5e1;border-top:1px solid var(--line)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:8px 0 40px}.stat{background:rgba(11,18,32,.86);border:1px solid var(--line);border-radius:20px;padding:18px;text-align:center}.stat b{display:block;color:var(--gold);font-size:28px}.stat span{color:var(--muted);font-weight:800;font-size:12px;text-transform:uppercase}
section{padding:42px 0}.sectionHead{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:18px}.sectionHead h2{font-size:34px;margin:0}.sectionHead p{color:var(--muted);max-width:620px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{background:rgba(11,18,32,.88);border:1px solid var(--line);border-radius:24px;padding:18px}.card h3{margin:0 0 8px;color:#f5d48b}.card p{color:#cbd5e1;line-height:1.55}.video{background:#000;border:1px solid rgba(212,175,55,.35);border-radius:24px;overflow:hidden}.video video{width:100%;display:block;aspect-ratio:16/9;background:#000}
.audioList{display:grid;gap:12px}.track{background:rgba(11,18,32,.88);border:1px solid var(--line);border-radius:18px;padding:14px;display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:center}.track b{color:#f5d48b}.track audio{width:100%}
.gallery{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.gallery img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:18px;border:1px solid var(--line);background:#111827}
.quote{font-size:28px;line-height:1.25;color:#fff;border-left:5px solid var(--gold);padding-left:18px}.booking{background:linear-gradient(135deg,rgba(212,175,55,.18),rgba(14,165,233,.1));border:1px solid rgba(212,175,55,.35);border-radius:28px;padding:26px}.footer{padding:36px 0;color:var(--muted);border-top:1px solid var(--line);margin-top:30px}
@media(max-width:900px){.hero{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}.gallery{grid-template-columns:repeat(2,1fr)}.track{grid-template-columns:1fr}.sectionHead{display:block}}
</style>
</head>
<body>
<main class="wrap">
<section class="hero">
  <div>
    <div class="kicker">Electronic Press Kit</div>
    <h1>Mark Pires<br>Live Songwriting Concert Series</h1>
    <p>Inventor and patent holder of The BeatSeat. Former MTV artist. Song of the Year finalist. Creator of 209 original songs and one of the longest daily live creation shows, running from 12/31/2018 to 6/9/2025.</p>
    <p><strong>Booking concept:</strong> a one-person live songwriting, rhythm, storytelling and audience-engagement show built around The BeatSeat — a unique instrument and performance experience.</p>
    <div class="ctas">
      <a class="btn gold" href="mailto:mark@markpires.com?subject=Booking%20Mark%20Pires%20Music">Book Mark</a>
      <a class="btn" href="tel:2032472655">Call/Text 203-247-2655</a>
      <a class="btn" href="#music">Hear Originals</a>
    </div>
  </div>
  <div class="photo">
    <img src="/assets/epk/mark-live-1.jpg" alt="Mark Pires performing live" onerror="this.src='/logo.png'">
    <div class="caption">Live songwriting, BeatSeat performance, original music, and audience connection.</div>
  </div>
</section>

<div class="stats">
  <div class="stat"><b>209</b><span>Original Songs</span></div>
  <div class="stat"><b>MTV</b><span>Former Artist</span></div>
  <div class="stat"><b>Finalist</b><span>Song of the Year</span></div>
  <div class="stat"><b>BeatSeat</b><span>Inventor / Patent Holder</span></div>
</div>

<section>
  <div class="sectionHead"><h2>Performance Offer</h2><p>A premium live show for venues, wineries, private events, arts centers, listening rooms, festivals, corporate events, and community series.</p></div>
  <div class="grid">
    <div class="card"><h3>Live Songwriting Concert</h3><p>Original songs, stories, audience engagement, and spontaneous creative moments built for rooms that want something memorable.</p></div>
    <div class="card"><h3>The BeatSeat Experience</h3><p>Mark performs with the instrument he invented — a unique rhythm platform for solo artists that turns a one-person show into a fuller live performance.</p></div>
    <div class="card"><h3>Speaking + Music Hybrid</h3><p>An inspiring creative-performance format around invention, consistency, creativity, entrepreneurship, and the discipline of showing up every day.</p></div>
  </div>
</section>

<section>
  <div class="sectionHead"><h2>Featured Video</h2><p>Replace this file with a 60–120 second EPK montage or BeatSeat demo.</p></div>
  <div class="video"><video controls poster="/assets/epk/mark-live-2.jpg"><source src="/assets/epk/epk-highlight.mp4" type="video/mp4"></video></div>
</section>

<section id="music">
  <div class="sectionHead"><h2>Original Music Sampler</h2><p>10-song montage/sample section. Replace titles/files with your strongest originals.</p></div>
  <div class="audioList">
    <?php foreach($songs as $i=>$song): ?>
      <div class="track">
        <b><?=($i+1)?>. <?=htmlspecialchars($song['title'])?></b>
        <audio controls preload="none"><source src="<?=htmlspecialchars($song['file'])?>" type="audio/mpeg"></audio>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section>
  <div class="sectionHead"><h2>Photo Gallery</h2><p>Drop 10 photos into /assets/epk/ and update these filenames when ready.</p></div>
  <div class="gallery">
    <?php for($i=1;$i<=10;$i++): ?>
      <img src="/assets/epk/mark-live-<?=$i?>.jpg" alt="Mark Pires live photo <?=$i?>" onerror="this.style.display='none'">
    <?php endfor; ?>
  </div>
</section>

<section>
  <div class="booking">
    <div class="kicker">Booking Notes</div>
    <h2>Ideal For</h2>
    <p>Listening rooms, wineries, breweries, arts centers, summer concert series, private events, corporate inspiration events, songwriter nights, podcasts, radio, and creative entrepreneurship programming.</p>
    <div class="ctas">
      <a class="btn gold" href="mailto:mark@markpires.com?subject=Booking%20Inquiry%20for%20Mark%20Pires">Request Booking</a>
      <a class="btn" href="mailto:mark@markpires.com?subject=EPK%20Follow-Up">Email Mark</a>
    </div>
  </div>
</section>

<section>
  <p class="quote">“A one-person show with the energy, rhythm and storytelling of a full creative life.”</p>
</section>

<footer class="footer">
  <strong>Mark Pires</strong> · mark@markpires.com · 203-247-2655 · Fairfield County, Connecticut
</footer>
</main>
</body>
</html>