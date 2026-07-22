<?php
require_once __DIR__.'/social-core.php';
function gds_platform_publish($item){
  return gds_draft_publish($item);
}
