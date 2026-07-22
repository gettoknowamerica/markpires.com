<?php
/**
 * V18.1 Goliath OS Command Center
 * Upload: /public_html/commandcenter.php
 */
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath OS Command Center</title><style>
body{margin:0;background:radial-gradient(circle at top left,#fff 0,#f5f3ef 42%,#e9e1d3 100%);color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:linear-gradient(135deg,#111827,#0b1020);color:white;padding:42px 24px}.heroIn{max-width:1800px;margin:auto}h1{font-family:Georgia,serif;color:#c8a96e;font-size:54px;line-height:.95;margin:0 0 10px}.sub{font-size:18px;color:#d8d8d8;max-width:900px}.wrap{max-width:1800px;margin:auto;padding:24px}.quick{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.card,.section{background:white;border-radius:18px;box-shadow:0 4px 20px #0001;border:1px solid #fff}.card{padding:18px;text-decoration:none;color:#111}.card h3{font-family:Georgia,serif;margin:0 0 8px;font-size:21px}.card p{margin:0;color:#666;font-size:14px;line-height:1.45}.section{margin-top:22px;overflow:hidden}.section h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid #eee;font-size:26px}.appGrid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:18px}.app{display:block;text-decoration:none;color:#111;border:1px solid #eee;border-radius:14px;padding:14px;background:#fffdf8;min-height:104px;transition:.15s}.app:hover{transform:translateY(-2px);box-shadow:0 8px 22px #0002;border-color:#c8a96e}.app b{display:block;font-size:15px;margin-bottom:7px}.app span{font-size:12px;color:#666;line-height:1.35}.badge{display:inline-block;background:#111827;color:#fff;border-radius:999px;padding:4px 8px;font-size:10px;margin-top:10px}@media(max-width:1100px){.quick,.appGrid{grid-template-columns:1fr 1fr}h1{font-size:40px}}@media(max-width:700px){.quick,.appGrid{grid-template-columns:1fr}.hero{padding:28px 16px}.wrap{padding:14px}}
</style><link rel="stylesheet" href="/dashboard/assets/goliath-stealth.css?v=302"><link rel="icon" href="/dashboard/assets/goliath-ai-logo.svg"></head><body>
<?php require_once __DIR__ . '/dashboard/includes/goliath-nav.php'; ?>
<section class="hero"><div class="heroIn"><h1>Goliath OS Command Center</h1><div class="sub">One Mac-style operating system for Jessica: leads, voice, content, video, audio, ads, distribution, executive intelligence, and automation.</div></div></section>
<main class="wrap">
<div class="quick">
<a class="card" href="/dashboard/jessica-creative-command-center.php"><h3>Creator Center</h3><p>Human editor mode for video, captions, lower thirds, logos, title cards, audio, and render notes.</p></a>
<a class="card" href="/dashboard/jessica-audio-command-center.php"><h3>Audio Studio</h3><p>Voice cleanup, noise reduction, loudness, EQ, compression, and music ducking.</p></a>
<a class="card" href="/dashboard/seller-acquisition-director.php"><h3>Hot Leads</h3><p>Seller opportunities, valuation requests, and high-priority lead intelligence.</p></a>
<a class="card" href="/dashboard/campaign-command-center.php"><h3>Advertising</h3><p>Campaign plans, creative review, scaling decisions, ROI, and ad launch control.</p></a>
</div>
<?php foreach($GOLIATH_SECTIONS as $name=>$links): ?>
<section class="section"><h2><?=goliath_h($name)?></h2><div class="appGrid">
<?php foreach($links as $l): ?><a class="app" href="<?=goliath_h($l[1])?>"><b><?=goliath_h($l[0])?></b><span><?=goliath_h($l[2])?></span><em class="badge">Open</em></a><?php endforeach; ?>
</div></section>
<?php endforeach; ?>
</main></body></html>