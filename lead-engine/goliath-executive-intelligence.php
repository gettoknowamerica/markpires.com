<?php
/**
 * Goliath V75.7 — Executive Intelligence, Tool Capability Dictionary,
 * Anti-Fabrication Rules, and Deliverable Contract
 *
 * This file does not require Supabase or HubSpot. Hostinger MySQL remains the source of truth.
 */

function gei_tool_dictionary(){
  return [
    'browser_use'=>[
      'name'=>'Browser Use / OpenBrowser',
      'category'=>'research_web',
      'capability'=>'Operate a browser-like workflow for live research, page review, lead discovery, and evidence gathering.',
      'inputs'=>'search query, URL, target site, business name, town, lead criteria',
      'outputs'=>'verified URLs, notes, extracted page facts, screenshots or structured records when available',
      'best_for'=>'Scout, Einstein, Pandora, Prospector, Shakespeare, Columbo',
      'never'=>'Never invent data if browser/search access fails. Mark as NEEDS_WEB_RESEARCH.'
    ],
    'playwright'=>[
      'name'=>'Playwright',
      'category'=>'research_web',
      'capability'=>'Automate websites, test forms, inspect pages, validate funnels, and capture structured data.',
      'inputs'=>'URL, test steps, selectors, form data, expected outcome',
      'outputs'=>'pass/fail report, screenshots, broken links, captured data, form-test evidence',
      'best_for'=>'Einstein, Scout, Shakespeare, Jessica',
      'never'=>'Do not claim a form works unless it was actually tested.'
    ],
    'firecrawl'=>[
      'name'=>'Firecrawl',
      'category'=>'research_web',
      'capability'=>'Crawl websites and convert pages into clean structured content for research or lead extraction.',
      'inputs'=>'URL/domain/search target',
      'outputs'=>'markdown, extracted content, links, metadata, structured page summaries',
      'best_for'=>'Scout, Einstein, Pandora, Prospector, Shakespeare',
      'never'=>'Do not use output as verified contact info unless source URL is included.'
    ],
    'crawl4ai'=>[
      'name'=>'Crawl4AI',
      'category'=>'research_web',
      'capability'=>'AI-oriented website crawling, content extraction, and structured research from multi-page sites.',
      'inputs'=>'URL/domain, crawl scope, extraction goal',
      'outputs'=>'structured content, candidate URLs, summaries, lead/opportunity evidence',
      'best_for'=>'Scout, Einstein, Pandora, Prospector',
      'never'=>'Do not fabricate crawl results if no pages are reachable.'
    ],
    'beautifulsoup'=>[
      'name'=>'BeautifulSoup',
      'category'=>'research_web',
      'capability'=>'Parse HTML and extract names, links, phone numbers, addresses, tables, and page metadata.',
      'inputs'=>'HTML file/page source',
      'outputs'=>'parsed records, links, contact candidates, cleaned fields',
      'best_for'=>'Scout, Einstein, Columbo',
      'never'=>'Never assume extracted text is current without page URL/date.'
    ],
    'trafilatura'=>[
      'name'=>'Trafilatura',
      'category'=>'research_web',
      'capability'=>'Extract clean article/page text from messy web pages.',
      'inputs'=>'URL or HTML',
      'outputs'=>'clean article text, title, author/date when available',
      'best_for'=>'Einstein, Shakespeare, Pandora, Columbo',
      'never'=>'Do not turn extracted text into claimed facts without citing the source URL.'
    ],
    'newspaper4k'=>[
      'name'=>'Newspaper4k',
      'category'=>'research_web',
      'capability'=>'Extract and analyze news/article pages including title, text, authors, and dates.',
      'inputs'=>'news/article URL',
      'outputs'=>'clean article, metadata, summary candidates',
      'best_for'=>'Pandora, Prospector, Einstein, Shakespeare',
      'never'=>'Never invent reporters or media contacts.'
    ],
    'qdrant'=>[
      'name'=>'Qdrant',
      'category'=>'ai_infrastructure',
      'capability'=>'Vector database for semantic search across Mark’s content, leads, transcripts, music, and research.',
      'inputs'=>'embedded documents/records, search query',
      'outputs'=>'semantically relevant records, source IDs, similarity matches',
      'best_for'=>'Columbo, Einstein, Scout, Mozart',
      'never'=>'Do not use as proof of external facts unless records contain source evidence.'
    ],
    'meilisearch'=>[
      'name'=>'Meilisearch',
      'category'=>'ai_infrastructure',
      'capability'=>'Fast keyword search across internal records, CRM, content, transcripts, and deliverables.',
      'inputs'=>'indexed records, keyword query',
      'outputs'=>'matching records, IDs, snippets',
      'best_for'=>'Scout, Columbo, Jessica, Einstein',
      'never'=>'Do not claim something exists unless the record ID/link is included.'
    ],
    'autogen'=>[
      'name'=>'AutoGen',
      'category'=>'ai_infrastructure',
      'capability'=>'Multi-agent collaboration framework for planning, review, and role-based task execution.',
      'inputs'=>'goal, agents, tool capabilities, constraints',
      'outputs'=>'multi-agent plan, critiques, handoffs',
      'best_for'=>'Goliath, Einstein, Rockefeller',
      'never'=>'Do not let AutoGen agents invent facts without evidence.'
    ],
    'ollama'=>[
      'name'=>'Ollama',
      'category'=>'ai_infrastructure',
      'capability'=>'Local LLM reasoning, drafting, summarizing, rewriting, and task planning.',
      'inputs'=>'prompt/context',
      'outputs'=>'text plan, draft, structured report, JSON-like summaries',
      'best_for'=>'All executives',
      'never'=>'Ollama alone is not evidence. Use it for reasoning, not fabricated research.'
    ],
    'openwebui'=>[
      'name'=>'OpenWebUI',
      'category'=>'ai_infrastructure',
      'capability'=>'Local model interface for testing prompts, models, and long-running local AI sessions.',
      'inputs'=>'prompt/model/session',
      'outputs'=>'AI responses, prompt experiments',
      'best_for'=>'Goliath, Einstein, Shakespeare',
      'never'=>'Do not treat OpenWebUI output as verified research.'
    ],
    'claude_code'=>[
      'name'=>'Claude Code',
      'category'=>'development',
      'capability'=>'Code editing, project inspection, refactoring, and implementation assistance.',
      'inputs'=>'repository/files/task',
      'outputs'=>'code patches, diffs, implementation notes',
      'best_for'=>'Einstein, Goliath, Shakespeare',
      'never'=>'Do not deploy code without review/testing.'
    ],
    'comfyui'=>[
      'name'=>'ComfyUI',
      'category'=>'media_video',
      'capability'=>'Node-based AI image/video generation and workflow execution.',
      'inputs'=>'workflow JSON, prompt, models, images/video inputs',
      'outputs'=>'images, videos, intermediate assets',
      'best_for'=>'Scorsese',
      'never'=>'Do not queue renders if required models/workflows are missing.'
    ],
    'wan'=>[
      'name'=>'WAN Text-to-Video',
      'category'=>'media_video',
      'capability'=>'Generate AI video clips from cinematic text prompts.',
      'inputs'=>'positive prompt, negative prompt, WAN model, text encoder, VAE',
      'outputs'=>'short AI video clips',
      'best_for'=>'Scorsese',
      'never'=>'Avoid text-heavy scenes, exact human likeness, or claims of final production quality without review.'
    ],
    'sam2'=>[
      'name'=>'SAM2',
      'category'=>'media_video',
      'capability'=>'Segment and track objects/people across images or video.',
      'inputs'=>'image/video, target object or mask',
      'outputs'=>'masks, segmented assets, tracked objects',
      'best_for'=>'Scorsese, Columbo',
      'never'=>'Do not use for identity claims.'
    ],
    'controlnet'=>[
      'name'=>'ControlNet',
      'category'=>'media_image',
      'capability'=>'Guide AI image/video generation using pose, depth, edge, or structural controls.',
      'inputs'=>'control image/map, prompt, model',
      'outputs'=>'composition-controlled image/video outputs',
      'best_for'=>'Scorsese',
      'never'=>'Do not use when precise composition is not needed.'
    ],
    'ip_adapter'=>[
      'name'=>'IP Adapter',
      'category'=>'media_image',
      'capability'=>'Use reference images to guide style/identity/composition in image generation.',
      'inputs'=>'reference image, prompt, model',
      'outputs'=>'reference-influenced images',
      'best_for'=>'Scorsese',
      'never'=>'Do not claim exact likeness unless approved reference workflow is used.'
    ],
    'pulid'=>[
      'name'=>'PuLID',
      'category'=>'media_image',
      'capability'=>'Identity-preserving face/reference guidance for generated portraits/characters.',
      'inputs'=>'reference face/image, model, prompt',
      'outputs'=>'identity-consistent generated images',
      'best_for'=>'Scorsese',
      'never'=>'Use carefully; do not generate real-person likeness without approved source and review.'
    ],
    'florence2'=>[
      'name'=>'Florence 2 Vision Language Model',
      'category'=>'vision',
      'capability'=>'Image understanding, captioning, object detection, OCR-like visual analysis.',
      'inputs'=>'image/screenshot/frame',
      'outputs'=>'captions, object descriptions, visual tags',
      'best_for'=>'Scorsese, Columbo, Einstein',
      'never'=>'Do not use as definitive identity recognition.'
    ],
    'joycaption'=>[
      'name'=>'JoyCaption',
      'category'=>'vision',
      'capability'=>'Generate rich image captions/tags useful for prompts, SEO, thumbnails, and media organization.',
      'inputs'=>'image/frame',
      'outputs'=>'caption, descriptive tags',
      'best_for'=>'Scorsese, Columbo, Shakespeare',
      'never'=>'Do not treat captions as external facts.'
    ],
    'flux'=>[
      'name'=>'Flux',
      'category'=>'media_image',
      'capability'=>'High-quality image generation for thumbnails, concepts, ads, and creative visuals.',
      'inputs'=>'prompt, style, model settings',
      'outputs'=>'images',
      'best_for'=>'Scorsese, Shakespeare, Pandora',
      'never'=>'Do not use for final ad without brand review.'
    ],
    'video_helper_suite'=>[
      'name'=>'ComfyUI Video Helper Suite',
      'category'=>'media_video',
      'capability'=>'Load, process, combine, and export video frames in ComfyUI workflows.',
      'inputs'=>'video/frames/workflow',
      'outputs'=>'video/frame sequences',
      'best_for'=>'Scorsese',
      'never'=>'Do not assume it renders alone; it supports workflows.'
    ],
    'impact_pack'=>[
      'name'=>'ComfyUI Impact Pack',
      'category'=>'media_image',
      'capability'=>'Advanced detectors, segmentation helpers, and workflow utilities for ComfyUI.',
      'inputs'=>'images/workflow nodes',
      'outputs'=>'enhanced masks, detections, workflow utilities',
      'best_for'=>'Scorsese',
      'never'=>'Do not use without verifying nodes are installed.'
    ],
    'model_manager'=>[
      'name'=>'ComfyUI Model Manager',
      'category'=>'media_system',
      'capability'=>'Track/install/verify ComfyUI models and dependencies.',
      'inputs'=>'model requirement',
      'outputs'=>'model availability status',
      'best_for'=>'Scorsese, Goliath',
      'never'=>'Do not render if manager says a required model is missing.'
    ],
    'remotion'=>[
      'name'=>'Remotion',
      'category'=>'media_video',
      'capability'=>'Programmatic video assembly with captions, lower thirds, logo overlays, templates, exports.',
      'inputs'=>'clips, images, copy, template',
      'outputs'=>'finished videos in multiple formats',
      'best_for'=>'Scorsese, Columbo, Shakespeare',
      'never'=>'Do not use when a simple raw render is enough.'
    ],
    'ffmpeg'=>[
      'name'=>'FFmpeg / AIFFmpeg',
      'category'=>'media_video',
      'capability'=>'Cut, crop, transcode, compress, extract frames/audio, create social formats.',
      'inputs'=>'media file and command/settings',
      'outputs'=>'converted/cropped/trimmed media',
      'best_for'=>'Scorsese, Mozart, Columbo',
      'never'=>'Do not overwrite originals.'
    ],
    'rife'=>[
      'name'=>'ECCV2022-RIFE / Frame Interpolation',
      'category'=>'media_video',
      'capability'=>'Smooth motion and convert low-FPS video to higher frame rates.',
      'inputs'=>'video file',
      'outputs'=>'higher-FPS video',
      'best_for'=>'Scorsese',
      'never'=>'Avoid if it creates artifacts around faces/hands.'
    ],
    '4k_upscaler'=>[
      'name'=>'4K Video Upscaler Colab',
      'category'=>'media_video',
      'capability'=>'Upscale videos for sharper final outputs.',
      'inputs'=>'video file',
      'outputs'=>'upscaled video',
      'best_for'=>'Scorsese',
      'never'=>'Do not upscale bad content before creative review.'
    ],
    'arcads'=>[
      'name'=>'Arcads',
      'category'=>'media_video',
      'capability'=>'AI avatar/spokesperson style video generation for ads and presentations.',
      'inputs'=>'script, avatar/style, brand direction',
      'outputs'=>'spokesperson/ad video',
      'best_for'=>'Scorsese, Pandora, Jessica',
      'never'=>'Do not impersonate Mark without approved clone/voice/likeness rules.'
    ],
    'whisperx'=>[
      'name'=>'WhisperX',
      'category'=>'audio_transcription',
      'capability'=>'Fast transcription with word-level timestamps and alignment.',
      'inputs'=>'audio/video file',
      'outputs'=>'transcript, timestamps, captions',
      'best_for'=>'Columbo, Mozart, Scorsese, Shakespeare',
      'never'=>'Review important quotes before publishing.'
    ],
    'pyannote'=>[
      'name'=>'Pyannote',
      'category'=>'audio_transcription',
      'capability'=>'Speaker diarization: identify who spoke when.',
      'inputs'=>'audio/video',
      'outputs'=>'speaker-labeled transcript segments',
      'best_for'=>'Columbo, Mozart, Scorsese',
      'never'=>'Do not identify speakers by name unless already known.'
    ],
    'silero_vad'=>[
      'name'=>'Silero VAD',
      'category'=>'audio_processing',
      'capability'=>'Voice activity detection: find speech vs silence.',
      'inputs'=>'audio file',
      'outputs'=>'speech segments, silence cuts',
      'best_for'=>'Mozart, Scorsese, Columbo',
      'never'=>'Do not remove emotional pauses without review.'
    ],
    'piper'=>[
      'name'=>'Piper',
      'category'=>'audio_voice',
      'capability'=>'Local text-to-speech voice generation.',
      'inputs'=>'script/text, voice model',
      'outputs'=>'spoken audio',
      'best_for'=>'Jessica, Scorsese, Shakespeare',
      'never'=>'Do not present TTS as Mark unless approved.'
    ],
    'demucs'=>[
      'name'=>'Demucs / Audio Separator',
      'category'=>'audio_processing',
      'capability'=>'Separate vocals, drums, bass, and other instruments into stems.',
      'inputs'=>'song/performance audio',
      'outputs'=>'audio stems',
      'best_for'=>'Mozart, Scorsese, Columbo',
      'never'=>'Do not publish stems without rights/approval.'
    ],
    'humanizer'=>[
      'name'=>'Humanizer',
      'category'=>'writing',
      'capability'=>'Make AI-written copy sound natural, emotionally intelligent, and human.',
      'inputs'=>'draft copy',
      'outputs'=>'polished copy',
      'best_for'=>'Jessica, Shakespeare, Pandora, Einstein',
      'never'=>'Do not humanize fabricated facts.'
    ],
    'markdown'=>[
      'name'=>'MarkItDown / Markdown',
      'category'=>'documents',
      'capability'=>'Convert documents/pages into clean Markdown for indexing, review, and summarization.',
      'inputs'=>'PDF/doc/web content',
      'outputs'=>'Markdown text',
      'best_for'=>'Einstein, Shakespeare, Columbo',
      'never'=>'Check tables and figures separately.'
    ]
  ];
}

function gei_agent_tools($exec){
  $exec = strtolower((string)$exec);
  $map = [
    'scout'=>['browser_use','playwright','firecrawl','crawl4ai','beautifulsoup','trafilatura','newspaper4k','qdrant','meilisearch','markdown','ollama'],
    'prospector'=>['browser_use','playwright','firecrawl','crawl4ai','trafilatura','newspaper4k','humanizer','ollama'],
    'pandora'=>['browser_use','playwright','firecrawl','crawl4ai','newspaper4k','humanizer','arcads','remotion','ollama'],
    'einstein'=>['browser_use','playwright','firecrawl','crawl4ai','trafilatura','newspaper4k','qdrant','meilisearch','markdown','ollama','openwebui'],
    'shakespeare'=>['humanizer','browser_use','firecrawl','trafilatura','newspaper4k','markdown','joycaption','remotion','ollama'],
    'scorsese'=>['comfyui','wan','sam2','controlnet','ip_adapter','pulid','florence2','joycaption','flux','video_helper_suite','impact_pack','model_manager','remotion','ffmpeg','rife','4k_upscaler','arcads','whisperx','silero_vad','ollama'],
    'mozart'=>['demucs','whisperx','pyannote','silero_vad','ffmpeg','piper','qdrant','meilisearch','ollama'],
    'columbo'=>['browser_use','youtube_api','whisperx','pyannote','qdrant','meilisearch','florence2','joycaption','ffmpeg','ollama'],
    'jessica'=>['humanizer','piper','browser_use','meilisearch','ollama'],
    'rockefeller'=>['browser_use','firecrawl','qdrant','meilisearch','humanizer','ollama'],
    'goliath'=>array_keys(gei_tool_dictionary())
  ];
  return $map[$exec] ?? ['ollama','browser_use','humanizer'];
}

function gei_rotating_assignment($exec, $title=''){
  $exec = strtolower((string)$exec);
  $sets = [
    'scout'=>[
      'Import or enrich real CRM/lead records. Output must include record IDs, source links, and next action.',
      'Find verified homeowner, seller, expired, FSBO, or absentee-owner opportunities. No invented names.',
      'Audit current lead records for missing phone/email/address fields and produce a clickable cleanup list.',
      'Score the top leads already in Hostinger CRM and explain why each is actionable.'
    ],
    'prospector'=>[
      'Find real speaking, press, sponsor, venue, partnership, or revenue opportunities with source URLs.',
      'Create a verified outreach shortlist with organization, contact page URL, reason, and next step.',
      'Research local venues/media/partnerships for Mark Pires, BeatSeat, Discover CT, LegacySaved, and speaking.',
      'Prepare a handoff to Jessica only when a real source link or contact page exists.'
    ],
    'pandora'=>[
      'Open new revenue doors using verified public information and cite the source link for every opportunity.',
      'Find partnership angles for Discover CT, BeatSeat, LegacySaved, real estate, music, or speaking.',
      'Create opportunity briefs that include proof, why now, pitch angle, and who should follow up.'
    ],
    'einstein'=>[
      'Audit MarkPires.com for AEO/SEO improvement opportunities and output fixes with page URLs.',
      'Find how to make Mark the first-choice answer for Fairfield County real estate searches.',
      'Review indexed content strategy and produce schema/blog/video recommendations with evidence.',
      'Analyze bottlenecks from real system data and create a prioritized fix list.'
    ],
    'shakespeare'=>[
      'Create a publish-ready blog, email, landing page, or video description using verified facts only.',
      'Rewrite existing copy into high-converting, human, local, SEO/AEO-rich content.',
      'Build content that makes Mark the obvious local authority, with title, slug, meta, and CTA.'
    ],
    'scorsese'=>[
      'Create a structured render spec: workflow, models, prompt, plugins, exports, thumbnail, and review link.',
      'Convert an idea or completed content into a real video production package, not just a concept.',
      'Review available media and produce one usable asset, render request, thumbnail, or edit plan.'
    ],
    'mozart'=>[
      'Analyze music/audio assets and produce stems, highlights, EPK clips, or contest submission candidates.',
      'Find the strongest hook/moment in a performance and create a concrete audio/video handoff.',
      'Produce a music leverage plan with files or source references, not invented claims.'
    ],
    'columbo'=>[
      'Mine Mark’s archives for real clips, titles, transcripts, thumbnail opportunities, and YouTube improvements.',
      'Find viral or motivational moments and hand them to Scorsese/Pandora with source links.',
      'Create YouTube metadata improvements with episode/source references.'
    ],
    'jessica'=>[
      'Review real leads/opportunities needing follow-up and create email/SMS/call drafts tied to records.',
      'Prepare human follow-up sequences for verified CRM records only.',
      'Create outreach drafts for opportunities handed off by Scout, Prospector, or Pandora.'
    ],
    'rockefeller'=>[
      'Rank current projects by potential ROI using real opportunities, current assets, and implementation effort.',
      'Identify the fastest path to revenue today with concrete next actions.',
      'Prioritize what Mark should do next across real estate, music, BeatSeat, LegacySaved, speaking, and Discover CT.'
    ],
    'goliath'=>[
      'Run the executive council: review completed work, identify missing deliverables, assign the next real task.',
      'Stop repeated generic missions and convert them into evidence-backed, clickable deliverables.',
      'Find bottlenecks across workers, ComfyUI, CRM, website, prompts, and review queue.'
    ]
  ];
  $list = $sets[$exec] ?? ['Create one concrete, useful deliverable with evidence and a clickable output.'];
  $idx = (int)(date('G') + (crc32($exec.$title) % count($list))) % count($list);
  return $list[$idx];
}

function gei_capabilities_text($exec){
  $dict = gei_tool_dictionary();
  $keys = gei_agent_tools($exec);
  $lines = [];
  foreach($keys as $k){
    if(!isset($dict[$k])) continue;
    $t = $dict[$k];
    $lines[] = "- {$t['name']}: {$t['capability']} Inputs: {$t['inputs']} Outputs: {$t['outputs']} Use for: {$t['best_for']}. Never: {$t['never']}";
  }
  return implode("\n", $lines);
}

function gei_enhance_prompt($exec, $title, $prompt, $metadata=[]){
  $execClean = ucfirst(strtolower((string)$exec ?: 'goliath'));
  $assignment = gei_rotating_assignment($execClean, $title);
  $tools = gei_capabilities_text($execClean);
  $original = trim((string)$prompt);
  $title = trim((string)$title ?: 'Goliath executive task');

  return <<<PROMPT
YOU ARE {$execClean}, A GOLIATH OMNI EXECUTIVE.

CURRENT DYNAMIC ASSIGNMENT:
{$assignment}

MISSION TITLE:
{$title}

ORIGINAL TASK CONTEXT:
{$original}

AVAILABLE TOOL CAPABILITIES YOU MAY REQUEST OR USE:
{$tools}

NON-NEGOTIABLE ANTI-FABRICATION RULES:
1. Never invent names, companies, contacts, phone numbers, emails, URLs, values, quotes, or prior press.
2. If live web research or a tool is required but unavailable, write: NEEDS_VERIFIED_RESEARCH and explain exactly which tool should run.
3. If you mention a person, company, lead, media outlet, venue, property, or opportunity, include the evidence/source URL or internal record ID.
4. If you cannot verify it, do not present it as fact.
5. Do not claim you created leads, emails, videos, files, or CRM updates unless you include a link, file path, table/record ID, or review item.

REQUIRED DELIVERABLE CONTRACT:
Your answer must begin with this exact structure:

DELIVERABLE_TYPE:
(one of: lead_list, crm_update, outreach_draft, seo_audit, blog_article, media_render_spec, video_asset, audio_asset, opportunity_list, executive_plan, system_fix)

EXECUTIVE:
{$execClean}

ACTIONABLE_OUTPUT:
A short description of the actual output created.

EVIDENCE:
- Source URL or internal record ID for every factual claim.
- If none exists, write NEEDS_VERIFIED_RESEARCH.

CLICKABLE_OUTPUTS:
- Include file paths, URLs, database record IDs, review links, or exact next action locations.
- If no clickable output exists, write NO_CLICKABLE_OUTPUT_CREATED and explain why.

HANDOFFS:
- Which executive should receive this next, and why.

NEXT_ACTION:
- One concrete next step Mark can take immediately.

Then provide the work product.

QUALITY BAR:
Mark should be able to click or act immediately. Do not write generic executive summaries. Produce usable work.
PROMPT;
}
?>