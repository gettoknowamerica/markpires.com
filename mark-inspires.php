<?php
/**
 * Mark insPires Speaker
 * Upload to: /public_html/mark-inspires.php
 *
 * Add photos/shorts to:
 * /assets/inspires/
 *
 * Suggested:
 * mark-speaking-1.jpg ... mark-speaking-10.jpg
 * mark-inspires-short-1.mp4 ... mark-inspires-short-5.mp4
 */
$PRIVATE = true;
$key = $_GET['key'] ?? '';
$accessKey = 'timetomakethedonuts';
if($PRIVATE && $key !== $accessKey){
  http_response_code(404);
  echo "Page not found.";
  exit;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mark insPires | Motivational Speaker EPK</title>
<meta name="description" content="Mark insPires speaker EPK: Affecting a Positive Change One Person At A Time. Motivational speaker, inventor, entrepreneur, creator, Realtor influencer, BeatSeat inventor, Discover CT creator.">
<style>
:root{--gold:#d4af37;--blue:#38bdf8;--bg:#05070d;--panel:#0b1220;--ink:#f8fafc;--muted:#94a3b8;--line:rgba(255,255,255,.14)}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 12% 0%,rgba(56,189,248,.22),transparent 28%),radial-gradient(circle at 80% 5%,rgba(212,175,55,.22),transparent 32%),linear-gradient(180deg,#05070d,#0f172a 70%,#05070d);color:var(--ink);font-family:Inter,Arial,sans-serif}
a{color:inherit}.wrap{width:min(1180px,92vw);margin:auto}.hero{min-height:88vh;display:grid;grid-template-columns:1.08fr .92fr;gap:34px;align-items:center;padding:56px 0}
.kicker{color:var(--gold);font-weight:1000;letter-spacing:.14em;text-transform:uppercase;font-size:13px}.hero h1{font-size:clamp(42px,7vw,84px);line-height:.93;margin:12px 0}.hero p{color:#cbd5e1;font-size:20px;line-height:1.55}.highlight{color:#f5d48b;font-weight:900}
.ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border-radius:999px;padding:13px 18px;font-weight:1000;border:1px solid var(--line);background:#111827}.btn.gold{background:linear-gradient(135deg,#f5d48b,#d4af37);color:#111827;border:0}.btn.blue{background:linear-gradient(135deg,#67e8f9,#38bdf8);color:#082f49;border:0}.btn:hover{transform:translateY(-1px)}
.photo{border-radius:30px;overflow:hidden;border:1px solid rgba(212,175,55,.35);background:#111827;box-shadow:0 40px 100px rgba(0,0,0,.45)}.photo img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}.caption{padding:14px;color:#cbd5e1;border-top:1px solid var(--line)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:8px 0 40px}.stat{background:rgba(11,18,32,.86);border:1px solid var(--line);border-radius:20px;padding:18px;text-align:center}.stat b{display:block;color:var(--gold);font-size:26px}.stat span{color:var(--muted);font-weight:800;font-size:12px;text-transform:uppercase}
section{padding:42px 0}.sectionHead{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:18px}.sectionHead h2{font-size:34px;margin:0}.sectionHead p{color:var(--muted);max-width:650px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{background:rgba(11,18,32,.88);border:1px solid var(--line);border-radius:24px;padding:18px}.card h3{margin:0 0 8px;color:#f5d48b}.card p,.card li{color:#cbd5e1;line-height:1.55}.card ul{padding-left:20px}
.video{background:#000;border:1px solid rgba(212,175,55,.35);border-radius:24px;overflow:hidden}.video iframe{width:100%;aspect-ratio:16/9;border:0;display:block}
.gallery{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.gallery img,.gallery video{width:100%;aspect-ratio:1;object-fit:cover;border-radius:18px;border:1px solid var(--line);background:#111827}
.quote{font-size:30px;line-height:1.25;color:#fff;border-left:5px solid var(--gold);padding-left:18px}.booking{background:linear-gradient(135deg,rgba(212,175,55,.18),rgba(14,165,233,.13));border:1px solid rgba(212,175,55,.35);border-radius:28px;padding:26px}.footer{padding:36px 0;color:var(--muted);border-top:1px solid var(--line);margin-top:30px}
.timeline{display:grid;gap:12px}.milestone{display:grid;grid-template-columns:110px 1fr;gap:14px;background:rgba(11,18,32,.88);border:1px solid var(--line);border-radius:18px;padding:16px}.year{color:var(--gold);font-weight:1000}
@media(max-width:900px){.hero{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}.gallery{grid-template-columns:repeat(2,1fr)}.sectionHead{display:block}.milestone{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="wrap">
<section class="hero">
  <div>
    <div class="kicker">Motivational Speaker</div>
    <h1>Mark insPires</h1>
    <p><span class="highlight">Affecting a Positive Change One Person At A Time.</span></p>
    <p>Mark Pires is a social media Realtor influencer, inventor, creator, musician, entrepreneur, and motivational speaker who entertains and inspires more than 100,000 followers across his platforms.</p>
    <p>When you need someone to captivate a room with a passionate message of never giving up, authentic reinvention, innovation, and the power of a smile — Mark brings a story people remember.</p>
    <div class="ctas">
      <a class="btn gold" href="mailto:mark@markpires.com?subject=Booking%20Mark%20insPires%20Speaking">Book Mark insPires</a>
      <a class="btn blue" href="#speech">Watch Full Speech</a>
      <a class="btn" href="tel:2032472655">Call/Text 203-247-2655</a>
    </div>
  </div>
  <div class="photo">
    <img src="/assets/inspires/mark-speaking-1.jpg" alt="Mark Pires speaking" onerror="this.src='/logo.png'">
    <div class="caption">Motivational speaking, entrepreneurship, creativity, real estate innovation, and live inspiration.</div>
  </div>
</section>

<div class="stats">
  <div class="stat"><b>100K+</b><span>Social Audience</span></div>
  <div class="stat"><b>209</b><span>Original Songs</span></div>
  <div class="stat"><b>BeatSeat</b><span>Inventor / Patent Holder</span></div>
  <div class="stat"><b>2013</b><span>CT Drone Marketing Pioneer</span></div>
</div>

<section>
  <div class="sectionHead"><h2>Signature Message</h2><p>High-energy, real-life inspiration for entrepreneurs, creators, sales teams, students, real estate professionals, community groups, and corporate events.</p></div>
  <div class="grid">
    <div class="card"><h3>Affecting Positive Change</h3><p>A passionate reminder that one person can change the energy of a room, a business, a family, a client experience, and a community.</p></div>
    <div class="card"><h3>Never Give Up</h3><p>Mark’s story connects invention, music, content creation, real estate, rejection, persistence, reinvention, and the decision to keep showing up.</p></div>
    <div class="card"><h3>Finding Your Authentic Self</h3><p>A creative, funny, emotional talk about building the courage to be seen, to create, to serve, and to lead without pretending to be someone else.</p></div>
  </div>
</section>

<section>
  <div class="sectionHead"><h2>Potential Speech Topics</h2><p>Each topic can be delivered as keynote, breakout, workshop, school/community talk, corporate inspiration session, or music/speaking hybrid.</p></div>
  <div class="grid">
    <div class="card"><h3>Innovation & Invention</h3><ul><li>Inventing and patenting a new product</li><li>The BeatSeat story</li><li>Turning an impossible idea into a tangible product</li></ul></div>
    <div class="card"><h3>Entrepreneurship</h3><ul><li>Creating opportunities before anyone gives permission</li><li>Building multiple brands from scratch</li><li>Using creativity as a business advantage</li></ul></div>
    <div class="card"><h3>Human Connection</h3><ul><li>The power of a smile</li><li>Affecting a positive change one person at a time</li><li>Real estate, relationships, and trust</li></ul></div>
  </div>
</section>

<section>
  <div class="sectionHead"><h2>Innovation Timeline</h2><p>Mark’s career is built around being early, taking risks, and creating what did not exist yet.</p></div>
  <div class="timeline">
    <div class="milestone"><div class="year">2013</div><div>The first Realtor in Connecticut to offer drone aerial photography as a listing marketing advantage.</div></div>
    <div class="milestone"><div class="year">2019</div><div>First known podcast studio built by a real estate firm in Connecticut, creating a platform for local storytelling and community content.</div></div>
    <div class="milestone"><div class="year">Creator</div><div>Creator of Discover CT, The House Detective, American Renovation, and Get To Know America across YouTube and social platforms.</div></div>
    <div class="milestone"><div class="year">Music</div><div>Former MTV artist, Song of the Year finalist, one-man-band performer, and creator of 209 original songs.</div></div>
    <div class="milestone"><div class="year">BeatSeat</div><div>Inventor and patent holder of The BeatSeat — the first full body percussion drum for solo artists.</div></div>
  </div>
</section>

<section>
  <div class="sectionHead"><h2>Short Clips / Promo Gallery</h2><p>Upload vertical shorts and speaking photos into /assets/inspires/ when ready.</p></div>
  <div class="gallery">
    <?php for($i=1;$i<=5;$i++): ?>
      <video controls muted preload="metadata"><source src="/assets/inspires/mark-inspires-short-<?=$i?>.mp4" type="video/mp4"></video>
    <?php endfor; ?>
    <?php for($i=1;$i<=5;$i++): ?>
      <img src="/assets/inspires/mark-speaking-<?=$i?>.jpg" alt="Mark insPires photo <?=$i?>" onerror="this.style.display='none'">
    <?php endfor; ?>
  </div>
</section>

<section id="speech">
  <div class="sectionHead"><h2>Full Mark insPires Episode</h2><p>Featured 16:9 keynote/speech episode for booking agents, events, venues, and corporate organizers.</p></div>
  <div class="video">
    <iframe src="https://youtu.be/8jC_7noH01k?si=yF2GiYwb8O6cOmNK" title="Mark insPires Upward Bound" allowfullscreen></iframe>
  </div>
</section>

<section>
  <div class="booking">
    <div class="kicker">Booking Fit</div>
    <h2>Ideal For</h2>
    <p>Corporate events, sales teams, real estate conferences, entrepreneurship groups, schools, community organizations, chambers of commerce, creative conferences, innovation events, podcasts, radio, and live music/speaking hybrid events.</p>
    <div class="ctas">
      <a class="btn gold" href="mailto:mark@markpires.com?subject=Mark%20insPires%20Booking%20Inquiry">Request Booking</a>
      <a class="btn" href="mailto:mark@markpires.com?subject=Mark%20insPires%20EPK%20Follow-Up">Email Mark</a>
    </div>
  </div>
</section>

<section><p class="quote">“When someone says "You Can't Do Something" Smile and thank them for the fuel” - Mark Pires </p></section>

<footer class="footer">
  <strong>Mark Pires</strong> · mark@markpires.com · 203-247-2655 · Fairfield County, Connecticut
</footer>
</main>
</body>
</html>