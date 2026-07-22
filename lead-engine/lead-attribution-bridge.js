/**
 * MarkPires Lead Attribution Bridge V1
 * Upload to: /public_html/lead-engine/lead-attribution-bridge.js
 *
 * Add before </body> after conversion-tracker.js:
 * <script src="/lead-engine/lead-attribution-bridge.js" defer></script>
 */
(function(){
  function getLS(k){ try{return localStorage.getItem(k)||'';}catch(e){return '';} }
  function getSS(k){ try{return sessionStorage.getItem(k)||'';}catch(e){return '';} }

  function ensureHidden(form, name, value){
    if(!value) return;
    var input = form.querySelector('[name="'+name+'"]');
    if(!input){
      input = document.createElement('input');
      input.type='hidden';
      input.name=name;
      form.appendChild(input);
    }
    input.value=value;
  }

  function applyAttribution(form){
    ensureHidden(form,'utm_source', getLS('mp_utm_source'));
    ensureHidden(form,'utm_medium', getLS('mp_utm_medium'));
    ensureHidden(form,'utm_campaign', getLS('mp_utm_campaign'));
    ensureHidden(form,'utm_content', getLS('mp_utm_content'));
    ensureHidden(form,'utm_term', getLS('mp_utm_term'));
    ensureHidden(form,'visitor_id', getLS('mp_visitor_id'));
    ensureHidden(form,'session_id', getSS('mp_session_id'));
    ensureHidden(form,'referrer', document.referrer || '');
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form').forEach(applyAttribution);
  });

  document.addEventListener('submit', function(e){
    applyAttribution(e.target);
  }, true);
})();
