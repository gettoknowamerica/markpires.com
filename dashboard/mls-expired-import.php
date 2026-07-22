<?php
session_start(); require_once __DIR__.'/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>MLS Chunk Import</title><style>
body{margin:0;background:#f5f3ef;font-family:Arial;color:#111}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e}.wrap{max-width:1200px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;padding:20px;box-shadow:0 4px 18px #0001}.btn{background:#c8a96e;border:0;border-radius:10px;padding:12px 16px;font-weight:900;margin:4px;cursor:pointer}input,select{width:100%;box-sizing:border-box;padding:10px;margin:6px 0 14px;border:1px solid #ddd;border-radius:8px}.prog{height:28px;background:#ddd;border-radius:99px;overflow:hidden}.bar{height:100%;background:#c8a96e;width:0%}pre{white-space:pre-wrap;background:#111827;color:white;padding:14px;border-radius:12px;max-height:420px;overflow:auto}.warn{background:#fff7ed;border-left:5px solid #c8a96e;padding:12px;border-radius:12px}</style></head><body>
<section class="hero"><h1>MLS Rich Chunk Import</h1><p>Separate delete/manager page + 200-row upload chunks.</p></section>
<main class="wrap"><section class="panel">
<div class="warn">Do not upload directly to Supabase if you want Jessica scoring. Use this importer so fields are mapped and opportunity_score is created.</div>
<p><a class="btn" href="/dashboard/mls-expired-manager.php">Open MLS Expired Manager / Delete Tools</a></p>
<form id="f"><label>MLS Export File</label><input type="file" id="file" accept=".csv,.txt,.xlsx,.XLSX" required><label>Import Batch Name</label><input id="batch" value="expired_<?=date('Ymd_His')?>"><label>Rows Per Chunk</label><input id="chunkSize" value="200"><button class="btn">Preview + Start Import</button></form>
<p id="status">Waiting.</p><div class="prog"><div class="bar" id="bar"></div></div><pre id="out"></pre>
</section></main>
<script>
const KEY=<?=json_encode($key)?>;
function parseLine(line, delim){const out=[];let cur='',q=false;for(let i=0;i<line.length;i++){const c=line[i],n=line[i+1];if(c==='"'&&q&&n==='"'){cur+='"';i++;continue;}if(c==='"'){q=!q;continue;}if(c===delim&&!q){out.push(cur);cur='';continue;}cur+=c;}out.push(cur);return out;}
function detectDelim(first){return (first.split('\t').length>=first.split(',').length)?'\t':',';}
document.getElementById('f').onsubmit=async e=>{
 e.preventDefault();
 const file=document.getElementById('file').files[0]; if(!file)return;
 const text=await file.text();
 const lines=text.split(/\r?\n/).filter(x=>x.trim().length);
 if(lines.length<2){document.getElementById('out').textContent='No rows found. If this is a true binary Excel file, export from MLS as CSV/tab-delimited first.';return;}
 const delim=detectDelim(lines[0]);
 const headers=parseLine(lines[0],delim).map(h=>h.trim());
 const rows=[];
 for(let i=1;i<lines.length;i++){const vals=parseLine(lines[i],delim);if(vals.length<2)continue;const obj={};headers.forEach((h,idx)=>obj[h]=vals[idx]??'');rows.push(obj);}
 document.getElementById('out').textContent=`Parsed ${rows.length} rows.\nDelimiter: ${delim==='\\t'?'TAB':'COMMA'}\nHeaders found: ${headers.slice(0,40).join(', ')}\n\nStarting upload...\n`;
 const chunkSize=parseInt(document.getElementById('chunkSize').value||'200',10);
 const batch=document.getElementById('batch').value;
 let inserted=0,totalChunks=Math.ceil(rows.length/chunkSize);
 for(let start=0,idx=0;start<rows.length;start+=chunkSize,idx++){
   const chunk=rows.slice(start,start+chunkSize);
   document.getElementById('status').textContent=`Uploading chunk ${idx+1} of ${totalChunks}...`;
   const res=await fetch('/lead-engine/import-mls-expired-chunk.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,batch,replace:'no',chunk_index:idx,rows:chunk})});
   const txt=await res.text(); let data; try{data=JSON.parse(txt)}catch(e){data={success:false,error:txt}}
   document.getElementById('out').textContent += JSON.stringify(data,null,2)+"\n";
   if(!data.success){document.getElementById('status').textContent='Stopped on error.';return;}
   inserted += data.inserted||0; document.getElementById('bar').style.width=Math.round(((start+chunk.length)/rows.length)*100)+'%';
 }
 document.getElementById('status').textContent=`Done. Inserted ${inserted} rows. Now open manager and Build Opportunity Engine.`;
};
</script></body></html>