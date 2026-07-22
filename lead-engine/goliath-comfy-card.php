<?php
require_once __DIR__.'/goliath-comfy-v75-bridge.php';
$id=(int)($_GET['job_id']??($_GET['id']??0));
$job=null;
if(gdb_enabled() && gc55_table('goliath_comfy_jobs') && $id){ $job=gdb_one('SELECT * FROM goliath_comfy_jobs WHERE id=? LIMIT 1',[$id]); }
$title=$job['title']??'Scorsese Production Asset';
$prompt=$job['prompt']??'Goliath Omni media asset';
function sx($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
header('Content-Type: image/svg+xml; charset=utf-8');
$short=mb_substr(trim(preg_replace('/\s+/',' ',$prompt)),0,260);
echo '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#020617"/><stop offset=".55" stop-color="#581c87"/><stop offset="1" stop-color="#f5c85d"/></linearGradient><filter id="shadow"><feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#000" flood-opacity=".55"/></filter></defs>
<rect width="1280" height="720" fill="url(#g)"/>
<circle cx="1040" cy="110" r="260" fill="#ffffff" opacity=".06"/>
<rect x="70" y="62" width="1140" height="596" rx="38" fill="#020617" opacity=".76" stroke="#f5c85d" stroke-width="3"/>
<text x="100" y="132" fill="#f5c85d" font-family="Arial Black,Arial" font-size="28" letter-spacing="5">SCORSESE • GOLIATH OMNI</text>
<text x="100" y="230" fill="#ffffff" font-family="Georgia,serif" font-size="54" font-weight="800" filter="url(#shadow)">'.sx($title).'</text>
<foreignObject x="100" y="285" width="1030" height="230"><div xmlns="http://www.w3.org/1999/xhtml" style="font:700 31px/1.35 Arial;color:#dbeafe;">'.sx($short).'</div></foreignObject>
<text x="100" y="610" fill="#f5c85d" font-family="Arial Black,Arial" font-size="34">READY FOR MARK PIRES REVIEW</text>
</svg>';
?>