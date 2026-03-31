<?php
/**
 * Settings page static asset delivery — CSS and JS strings returned for inline injection.
 *
 * Extracted from trait-settings-page.php to reduce file size.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.13.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Settings_Assets {

    /**
     * Returns the admin settings page CSS, delivered via wp_add_inline_style().
     *
     * @since 4.10.22
     * @return string Raw CSS string.
     */
    private function admin_page_css(): string {
        return <<<'CSS'
.ab-tabs { display:flex; flex-wrap:wrap; gap:6px; margin:20px 0 0; padding:0; border-bottom:3px solid #1d2327; }
.ab-tab { padding:8px 14px; cursor:pointer; border:none; border-radius:6px 6px 0 0; font-size:12px; font-weight:600; letter-spacing:0.01em; background:#e0e0e0; color:#50575e; transition:background 0.15s, color 0.15s; margin-bottom:0; position:relative; bottom:-1px; white-space:nowrap; }
@media (min-width:783px) { .ab-tab { padding:10px 22px; font-size:13px; } }
.ab-tab:hover:not(.active) { background:#c3c4c7; color:#1d2327; }
.ab-tab[data-tab="seo"].active     { background:#2271b1; color:#fff; }
.ab-tab[data-tab="aitools"].active  { background:#6366f1; color:#fff; }
.ab-tab[data-tab="sitemap"].active  { background:#1a7a34; color:#fff; }
.ab-tab[data-tab="batch"].active  { background:#e67e00; color:#fff; }
.ab-tab[data-tab="catfix"].active { background:#2d6a4f; color:#fff; }
.ab-tab[data-tab="perf"].active   { background:#d946a6; color:#fff; }
.ab-pane { display:none; padding-top:24px; padding-bottom:40px; padding-right:8px; }
.ab-pane.active { display:block; }
#ab-ai-writer { font-family: -apple-system, sans-serif; }
.ab-ai-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
#ab-log { background:#1a1a2e; color:#a8b4c8; font-family:'Courier New',monospace; font-size:12px; padding:14px; border-radius:6px; max-height:260px; overflow-y:auto; margin:16px 0; display:none; border:1px solid #2a2a4a; }
#ab-log.visible { display:block; }
#ab-alt-log { background:#1a1a2e; color:#a8b4c8; font-family:'Courier New',monospace; font-size:12px; padding:14px; border-radius:6px; max-height:260px; overflow-y:auto; margin:8px 0; border:1px solid #2a2a4a; display:none; }
.ab-log-line { color:#ffffff; margin-bottom:2px; }
.ab-log-ok   { color:#00d084; }
.ab-log-err  { color:#ff6b6b; }
.ab-log-skip { color:#f0c040; }
.ab-log-warn { color:#f0a040; }
.ab-log-info { color:#8080b0; }
.ab-progress { background:#f0f0f1; border-radius:4px; height:8px; margin:8px 0 4px; overflow:hidden; display:none; }
.ab-progress.visible { display:block; }
.ab-progress-fill { height:100%; background:#2271b1; border-radius:4px; transition:width 0.3s; width:0%; }
.ab-stats { font-size:12px; color:#50575e; margin-bottom:12px; }
.ab-stat-val { font-weight:600; color:#1d2327; }
table.ab-posts { width:100%; min-width:640px; border-collapse:collapse; margin-top:12px; }
table.ab-posts th { text-align:left; padding:8px 10px; border-bottom:2px solid #c3c4c7; font-size:12px; color:#50575e; font-weight:600; }
table.ab-posts td { padding:9px 10px; border-bottom:1px solid #f0f0f1; font-size:13px; vertical-align:top; }
table.ab-posts tr:hover td { background:#f6f7f7; }
.ab-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
.ab-score-badge { display:inline-block; padding:3px 9px; border-radius:12px; font-size:11px; font-weight:700; white-space:nowrap; cursor:pointer; transition:opacity 0.15s; }
.ab-score-badge:hover { opacity:0.8; }
.ab-score-none  { background:#f3f4f6; color:#6b7280; border:1px solid #d1d5db; font-weight:400; font-style:italic; }
.ab-score-poor  { background:#fde8e8; color:#9b1c1c; border:1px solid #fca5a5; }
.ab-score-fair  { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
.ab-score-good  { background:#d1fae5; color:#064e3b; border:1px solid #6ee7b7; }
.ab-score-great { background:#dcfce7; color:#14532d; border:1px solid #86efac; }
.ab-badge-none   { background:#f0e8fb; color:#4a1a7a; border:1px solid #c4b2e0; }
.ab-badge-ok     { background:#edfaef; color:#1a7a34; border:1px solid #b2dfc0; }
.ab-badge-short  { background:#fcf9e8; color:#7a5c00; border:1px solid #f0d676; }
.ab-badge-long   { background:#fcf0ef; color:#8a2424; border:1px solid #f5bcbb; }
.ab-badge-gen    { background:#e8f3fb; color:#1a4a7a; border:1px solid #b2cfe0; }
.ab-badge-gen-short { background:#f0e8fb; color:#4a1a7a; border:1px solid #c4b2e0; }
.ab-badge-gen-long  { background:#fcf0ef; color:#8a2424; border:1px solid #f5bcbb; }
.ab-desc-text { font-size:12px; color:#50575e; margin-top:3px; line-height:1.4; word-wrap:break-word; white-space:normal; }
.ab-desc-gen  { font-size:12px; color:#1a4a7a; margin-top:4px; background:#e8f3fb; border-left:3px solid #2271b1; padding:4px 8px; border-radius:0 3px 3px 0; }
.ab-row-btn { font-size:11px; padding:3px 8px; }
.ab-key-row { display:flex; gap:8px; align-items:center; }
.ab-key-status { font-size:12px; font-weight:600; }
.ab-key-ok  { color:#1a7a34; }
.ab-key-err { color:#8a2424; }
#ab-ai-gen-all { position:relative; }
.ab-spinner { display:inline-block; animation:ab-spin 0.8s linear infinite; margin-right:4px; }
@keyframes ab-spin { to { transform:rotate(360deg); } }
.ab-pager { display:flex; gap:8px; align-items:center; margin-top:12px; }
.ab-summary-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:12px 0; }
.ab-summary-card { background:#f6f7f7; border:1px solid #c3c4c7; border-radius:6px; padding:12px; text-align:center; }
.ab-summary-num  { font-size:24px; font-weight:700; color:#2271b1; line-height:1; }
.ab-summary-lbl  { font-size:11px; color:#50575e; margin-top:4px; }
.ab-zone-card { border-radius:8px; overflow:hidden; box-shadow:0 6px 28px rgba(30,100,200,0.55), 0 2px 8px rgba(30,100,200,0.35); margin:24px 0 0; }
.ab-zone-header { display:flex; align-items:center; gap:10px; padding:13px 20px; font-size:15px; font-weight:700; color:#fff; letter-spacing:0.01em; }
.ab-zone-header .ab-zone-icon { font-size:17px; }
.ab-zone-body { background:#f4f5f7; padding:4px 0 20px; }
.ab-zone-body .form-table th { padding-left:20px; }
.ab-zone-body .form-table td { padding-right:20px; }
@media (max-width:782px) {
    .ab-zone-body .form-table th { padding-left:16px; }
    .ab-zone-body .form-table td { padding-left:16px; padding-right:16px; }
    .ab-zone-body > *:not(.form-table):not(.ab-zone-body) { padding-left:16px; padding-right:16px; }
    .ab-alt-posts-wrap, #ab-alt-posts-wrap, #ab-alt-log, #ab-alt-prog-label, #ab-alt-toolbar, #ab-alt-summary, #ab-alt-load-cta { padding-left:16px; padding-right:16px; }
}
.ab-zone-card.ab-card-identity .ab-zone-header  { background:#2271b1; }
.ab-zone-card.ab-card-features .ab-zone-header  { background:#1a7a34; }
.ab-checkbox-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px 24px; padding:4px 0 8px; }
.ab-checkbox-grid label { display:flex; align-items:flex-start; gap:6px; font-size:13px; cursor:pointer; padding:6px 8px; border-radius:4px; }
.ab-checkbox-grid label.ab-rec { background:#edf7ed; border:1px solid #c3e6c3; }
.ab-checkbox-grid label:not(.ab-rec) { background:#f9f9f9; border:1px solid #e5e5e5; }
.ab-toggle-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f0f0f0; }
.ab-toggle-row:last-child { border-bottom:none; }
.ab-toggle-label { font-size:13px; font-weight:600; color:#1d2327; }
.ab-toggle-label span { display:block; font-weight:400; color:#666; font-size:12px; margin-top:2px; }
.ab-toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
.ab-toggle-switch input { opacity:0; width:0; height:0; }
.ab-toggle-slider { position:absolute; cursor:pointer; inset:0; background:#ccc; border-radius:24px; transition:background 0.2s; }
.ab-toggle-slider:before { content:''; position:absolute; width:18px; height:18px; border-radius:50%; left:3px; bottom:3px; background:#fff; transition:transform 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
.ab-toggle-switch input:checked + .ab-toggle-slider { background:#1a7a34; }
.ab-toggle-switch input:checked + .ab-toggle-slider:before { transform:translateX(20px); }
.ab-zone-card.ab-card-robots .ab-zone-header    { background:#b45309; }
.ab-physical-robots-warn { display:flex; gap:16px; align-items:flex-start; background:#fff8e1; border:2px solid #f0ad00; border-radius:6px; padding:16px 20px; margin:16px 20px 4px; font-size:13px; line-height:1.6; }
.ab-zone-card.ab-card-person .ab-zone-header    { background:#6b3fa0; }
.ab-zone-card.ab-card-ai .ab-zone-header        { background:#c3372b; }
.ab-zone-card.ab-card-ai .ab-zone-body          { background:#f5ecec; }
.ab-zone-card.ab-card-schedule .ab-zone-header  { background:#e67e00; }
.ab-zone-card.ab-card-lastrun  .ab-zone-header  { background:#1a4a7a; }
.ab-zone-card.ab-card-alt      .ab-zone-header  { background:#0e6b6b; }
.ab-zone-card.ab-card-summary  .ab-zone-header  { background:#6b3fa0; }
.ab-zone-card.ab-card-catfix   .ab-zone-header  { background:#2d6a4f; }
.ab-zone-card.ab-card-sitemap-settings .ab-zone-header { background:#1a7a34; }
.ab-zone-card.ab-card-sitemap-preview  .ab-zone-header { background:#0e5229; }
.ab-zone-card.ab-card-llms   .ab-zone-header    { background:#1a4a8a; }
.ab-zone-card.ab-card-https  .ab-zone-header    { background:#7a1a1a; }
.ab-sitemap-url { font-size:12px; color:#0a6be0; word-break:break-all; font-weight:500; }
.ab-sitemap-type { font-size:11px; font-weight:700; padding:2px 7px; border-radius:3px; white-space:nowrap; }
.ab-sitemap-type-home { background:#c8a8f0; color:#2d1060; }
.ab-sitemap-type-post { background:#a8cff0; color:#0a2a5a; }
.ab-sitemap-type-page { background:#a8f0b8; color:#0a4a1a; }
.ab-sitemap-type-tax  { background:#f0e0a8; color:#4a3000; }
.ab-sitemap-type-cpt  { background:#f0c0a8; color:#5a1a00; }
table.ab-sitemap-tbl { width:100%; border-collapse:collapse; font-size:13px; }
table.ab-sitemap-tbl th { text-align:left; padding:8px 12px; border-bottom:2px solid #8c8f94; background:#f0f0f1; font-size:12px; color:#1d2327; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
table.ab-sitemap-tbl td { padding:8px 12px; border-bottom:1px solid #dcdcde; vertical-align:middle; color:#1d2327; }
table.ab-sitemap-tbl tr:hover td { background:#e8f0fa; }
table.ab-sitemap-tbl tr:nth-child(even) td { background:#fafafa; }
table.ab-sitemap-tbl tr:nth-child(even):hover td { background:#e8f0fa; }
.ab-sitemap-count { font-size:13px; color:#1d2327; margin:0 0 12px; font-weight:500; }
.ab-sitemap-count strong { color:#1d2327; }
.cs-rc-link:hover { text-decoration:underline !important; }
.cs-hover-underline:hover { text-decoration:underline !important; }
.ab-zone-card.ab-card-update-posts .ab-zone-header { background:#1d2327; font-size:17px; padding:16px 22px; }
.ab-zone-card.ab-card-update-posts .ab-zone-header .ab-zone-icon { color:#f0c040; font-size:20px; }
.ab-load-cta { display:flex; align-items:center; gap:18px; background:linear-gradient(135deg, #1d2327 0%, #2c3338 100%); border-radius:6px; padding:20px 24px; margin-bottom:20px; border-left:5px solid #f0c040; }
.ab-load-cta-icon { font-size:32px; line-height:1; flex-shrink:0; }
.ab-load-cta-text { flex:1; }
.ab-load-cta-text strong { display:block; color:#fff; font-size:15px; margin-bottom:3px; }
.ab-load-cta-text span { color:#a7aaad; font-size:13px; }
.ab-load-btn { flex-shrink:0; background:#f0c040 !important; border-color:#d4a800 !important; color:#1d2327 !important; font-weight:700 !important; font-size:15px !important; padding:10px 28px !important; border-radius:4px; cursor:pointer; white-space:nowrap; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
.ab-load-btn:hover { background:#f5d060 !important; }
.ab-action-btn { font-size:13px !important; padding:6px 14px !important; height:auto !important; }
.ab-fix-btn    { background:#e67e00 !important; border-color:#c26900 !important; color:#fff !important; }
.ab-regen-btn  { background:#1a7a34 !important; border-color:#155f28 !important; color:#fff !important; }
.ab-static-btn { background:#c2185b !important; border-color:#ad1457 !important; color:#fff !important; }
.ab-zone-divider { border:none; border-top:2px solid #dcdcde; margin:32px 0 0; opacity:1; }
#cs-robots-txt, textarea[name="cs_seo_options[sitemap_exclude]"] { background:#1a1a2e !important; color:#e0e0f0 !important; font-family:'Courier New',monospace !important; font-size:12px !important; line-height:1.6 !important; border:1px solid #2a2a4a !important; border-radius:4px !important; }
textarea[name="cs_seo_options[home_desc]"], textarea[name="cs_seo_options[default_desc]"], textarea[name="cs_seo_options[sameas]"] { color:#1d2327 !important; }
.ab-zone-body p.description, .ab-zone-body .description { color:#2a7a3a !important; font-style:italic !important; font-size:12px !important; padding-left:8px !important; margin-top:4px !important; border-left:3px solid #b8dfc0 !important; }
.ab-zone-body .form-table th, .ab-zone-body .form-table th label { font-weight:700 !important; color:#1d2327 !important; }
.ab-api-key-warning { display:none; align-items:flex-start; gap:12px; background:#fff8e1; border:2px solid #f0ad00; border-radius:6px; padding:14px 18px; margin:0 0 16px; }
.ab-api-key-warning.visible { display:flex; }
.ab-api-key-warning .ab-warn-icon { font-size:22px; line-height:1; flex-shrink:0; }
.ab-api-key-warning .ab-warn-body { font-size:13px; color:#1d2327; }
.ab-api-key-warning .ab-warn-body strong { display:block; margin-bottom:4px; font-size:14px; }
.ab-api-key-warning .ab-warn-body a { color:#2271b1; font-weight:600; }
.ab-zone-card.ab-card-redirects .ab-zone-header { background:#0a7e8c; }
.ab-zone-body p.submit { padding-left:20px; margin-bottom:0; }
.ab-card-redirects input::placeholder { color:#bbb; opacity:1; }
CSS;
    }

    /**
     * Returns the llms.txt preview JS, delivered via wp_add_inline_script().
     * PHP values are passed via csSeoAdmin (wp_localize_script).
     *
     * @since 4.12.4
     * @return string JavaScript string.
     */
    private function llms_preview_js(): string {
        return <<<'JS'
(function() {
    function escHtml(s){var d=document.createElement('div');d.textContent=String(s);return d.innerHTML;}
    var _ajax  = csSeoAdmin.ajaxUrl;
    var _nonce = csSeoAdmin.nonce;
    document.addEventListener('DOMContentLoaded', function() {
        var btn  = document.getElementById('ab-llms-load');
        var wrap = document.getElementById('ab-llms-preview-wrap');
        if (!btn || !wrap) return;
        btn.addEventListener('click', function() {
            btn.disabled = true;
            btn.textContent = '⟳ Loading...';
            wrap.innerHTML = '<p style="color:#666;font-size:13px">Fetching llms.txt content\u2026</p>';
            fetch(_ajax, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=cs_seo_llms_preview&nonce=' + encodeURIComponent(_nonce)
            })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = '\u21bb Reload Preview';
                if (data.success && data.data.content) {
                    var lines = data.data.content.split('\n').length;
                    wrap.dataset.raw = data.data.content;
                    var highlighted = data.data.content
                        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                        .replace(/^(# .+)$/gm, '<span style="color:#e2c97e;font-size:14px;font-weight:700">$1</span>')
                        .replace(/^(## .+)$/gm, '<span style="color:#7eb8e2;font-weight:600">$1</span>')
                        .replace(/^(&gt; .+)$/gm, '<span style="color:#a8d8a8;font-style:italic">$1</span>')
                        .replace(/^(Author:.+)$/gm, '<span style="color:#c9a8e2">$1</span>')
                        .replace(/(\[([^\]]+)\]\([^)]+\))/g, function(m) {
                            return m.replace(/\[([^\]]+)\]/, '<span style="color:#7eb8e2">[$1]</span>');
                        });
                    wrap.innerHTML =
                        '<div style="margin-bottom:8px;font-size:12px;color:#50575e">' + lines + ' lines \u2014 ' + data.data.content.length + ' characters</div>' +
                        '<pre id="ab-llms-pre" style="background:#1a1a2e;color:#d0d8e8;font-family:Courier New,monospace;font-size:12px;line-height:1.7;padding:16px;border-radius:6px;max-height:400px;overflow:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #2a2a4a">' +
                        highlighted + '</pre>';
                } else {
                    wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Failed to load preview.</div>';
                }
            })
            .catch(function(e) {
                btn.disabled = false;
                btn.textContent = '\u21bb Reload Preview';
                wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Network error: ' + escHtml(e.message) + '</div>';
            });
        });
    });
})();
JS;
    }

    /**
     * Returns the sitemap preview JS, delivered via wp_add_inline_script().
     * PHP values are passed via csSeoAdmin (wp_localize_script).
     *
     * @since 4.12.4
     * @return string JavaScript string.
     */
    private function sitemap_preview_js(): string {
        return <<<'JS'
(function() {
    function escHtml(s){var d=document.createElement('div');d.textContent=String(s);return d.innerHTML;}
    function safeHref(url){var s=String(url).replace(/\s/g,'').toLowerCase();return(s.indexOf('javascript:')===0||s.indexOf('data:')===0||s.indexOf('vbscript:')===0)?'#':escHtml(url);}
    var _ajax  = csSeoAdmin.ajaxUrl;
    var _nonce = csSeoAdmin.nonce;
    function loadSitemapPreview(pg) {
        var wrap = document.getElementById('ab-sitemap-preview-wrap');
        var btn  = document.getElementById('ab-sitemap-load');
        if (!wrap || !btn) return;
        btn.disabled = true;
        btn.textContent = '\u27f3 Loading...';
        btn.style.background = '#c0882a';
        wrap.innerHTML = '<p style="color:#666;font-size:13px">Fetching sitemap entries\u2026</p>';
        if (!(pg > 1)) window._abSitemapUrls = [];
        var body = 'action=cs_seo_sitemap_preview&nonce='+encodeURIComponent(_nonce)+'&sitemap_pg='+(pg||1);
        fetch(_ajax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body})
            .then(function(r){ return r.text(); })
            .then(function(txt) {
                btn.disabled = false;
                btn.textContent = '\u21bb Reload';
                btn.style.background = '#f0b429';
                var data;
                try { data = JSON.parse(txt); } catch(e) {
                    wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Response was not JSON. Check for PHP errors.</div>';
                    return;
                }
                if (!data.success) {
                    wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Error: '+escHtml(data.data||'unknown')+'</div>';
                    return;
                }
                var d=data.data, entries=d.entries, total=d.total, page=d.page, pages=d.pages, per=d.per_page;
                window._abSitemapUrls = (window._abSitemapUrls || []).concat(entries.map(function(e){ return e.loc; }));
                var cols={home:'#6b3fa0',post:'#1a4a7a',page:'#1a7a34',tax:'#7a5c00',cpt:'#8a3a00'};
                var labels={home:'Home',post:'Post',page:'Page',tax:'Taxonomy',cpt:'CPT'};
                var rows=entries.map(function(e){
                    return '<tr style="border-bottom:1px solid #f0f0f0">'+
                        '<td style="padding:6px 8px"><a href="'+safeHref(e.loc)+'" target="_blank" style="font-size:12px;color:#2271b1">'+escHtml(e.loc)+'</a>'+(e.title?'<br><small style="color:#888;font-size:11px">'+escHtml(e.title)+'</small>':'')+'</td>'+
                        '<td style="padding:6px 8px"><span style="background:'+(cols[e.type]||'#444')+';color:#fff;border-radius:3px;padding:2px 8px;font-size:11px;white-space:nowrap">'+(labels[e.type]||escHtml(e.type))+'</span></td>'+
                        '<td style="padding:6px 8px;color:#888;font-size:12px;white-space:nowrap">'+(e.lastmod?escHtml(e.lastmod):'\u2014')+'</td></tr>';
                }).join('');
                var pager='';
                if(pages>1){
                    pager='<div style="display:flex;gap:10px;align-items:center;margin-top:14px;flex-wrap:wrap">'+
                        '<button class="button" '+(page<=1?'disabled':'onclick="window._abSitemapLoad('+(page-1)+')"')+'>\u2190 Prev</button>'+
                        '<span style="font-size:13px;color:#50575e">Page <strong>'+page+'</strong> of <strong>'+pages+'</strong></span>'+
                        '<button class="button button-primary" '+(page>=pages?'disabled':'onclick="window._abSitemapLoad('+(page+1)+')"')+'>Next \u2192</button>'+
                        '<span style="font-size:12px;margin-left:auto;color:#888">'+((page-1)*per+1)+'\u2013'+Math.min(page*per,total)+' of '+total+' URLs</span>'+
                        '</div>';
                }
                wrap.innerHTML=
                    '<p style="font-size:13px;margin:0 0 12px;color:#1d2327"><strong>'+total+'</strong> total URLs across <strong>'+pages+'</strong> sitemap file'+(pages>1?'s':'')+
                    ' &nbsp;\u00b7&nbsp; <a href="' + safeHref(csSeoAdmin.sitemapIndexUrl) + '" target="_blank" style="color:#2271b1">View live sitemap \u2197</a></p>'+
                    '<table style="width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #e0e0e0;border-radius:4px;overflow:hidden">'+
                    '<thead><tr style="background:#f6f7f7;border-bottom:2px solid #e0e0e0">'+
                    '<th style="text-align:left;padding:8px;font-size:12px;color:#50575e;font-weight:600">URL</th>'+
                    '<th style="text-align:left;padding:8px;font-size:12px;color:#50575e;font-weight:600">Type</th>'+
                    '<th style="text-align:left;padding:8px;font-size:12px;color:#50575e;font-weight:600">Last Modified</th></tr></thead>'+
                    '<tbody>'+rows+'</tbody></table>'+pager;
            })
            .catch(function(e){
                btn.disabled=false; btn.textContent='\u21bb Reload'; btn.style.background='#f0b429';
                wrap.innerHTML='<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Network error: '+escHtml(e.message)+'</div>';
            });
    }
    window.abLoadSitemap  = loadSitemapPreview;
    window._abSitemapLoad = loadSitemapPreview;
    document.addEventListener('DOMContentLoaded', function() {
        var b = document.getElementById('ab-sitemap-load');
        if (b) b.addEventListener('click', function(e){ e.preventDefault(); loadSitemapPreview(1); });
    });
})();
JS;
    }

}
