<?php
/**
 * JSON viewer using json-formatter-js library.
 * Renders any <div class="json-view" data-json="..."> as interactive collapsible JSON tree.
 *
 * Attributes:
 *   data-json        (required) JSON string
 *   data-depth       (optional) Levels to expand, default 1. Use "Infinity" for all.
 *   data-theme       (optional) "dark" or empty (light). Default: light.
 *   data-expand      (optional) If "true", card is fully expanded (no max-height).
 *   data-no-toolbar  (optional) If present, no toolbar is rendered for this view.
 */
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/json-formatter-js@2.5.23/dist/json-formatter.css">
<script src="https://cdn.jsdelivr.net/npm/json-formatter-js@2.5.23/dist/json-formatter.umd.js"></script>
<style>
.json-view { font-size: 0.85rem; padding: 0.75rem; border-radius: 0.25rem; overflow: auto; }
.json-view:not(.json-view-dark) { background: #f8f9fa; }
.json-view.json-view-dark { background: #282c34; }
.json-view.json-view-dark .json-formatter-row .json-formatter-key { color: #abb2bf !important; }
.json-view.json-view-dark .json-formatter-row .json-formatter-string { color: #98c379 !important; }
.json-view.json-view-dark .json-formatter-row .json-formatter-number { color: #d19a66 !important; }
.json-view.json-view-dark .json-formatter-row .json-formatter-boolean { color: #d19a66 !important; }
.json-view.json-view-dark .json-formatter-row .json-formatter-null { color: #5c6370 !important; font-style: italic; }
.json-view.json-view-dark .json-formatter-row .json-formatter-undefined { color: #5c6370 !important; }
.json-view.json-view-dark .json-formatter-row a { color: #61afef !important; }
.json-view.json-view-dark .json-formatter-row .json-formatter-toggler { color: #61afef !important; }
.json-view-toolbar { display: flex; gap: 0.25rem; padding: 0.25rem 0; }
.json-view-toolbar .btn { font-size: 0.75rem; padding: 0.15rem 0.45rem; line-height: 1.3; }
.json-view-toolbar .jvt-copied { color: #198754; }
</style>
<script>
(function() {
    var formatterOpts = {
        hoverPreviewEnabled: true,
        hoverPreviewArrayCount: 5,
        hoverPreviewFieldCount: 5,
        animateOpen: false,
        animateClose: false
    };

    /**
     * Re-render the json-formatter inside a container at a given depth.
     */
    function renderAtDepth(container, depth) {
        var data = container._jsonData;
        if (data === undefined) return;
        var theme = container.getAttribute('data-theme');
        var opts = Object.assign({}, formatterOpts, { theme: theme === 'dark' ? 'dark' : '' });
        var formatter = new JSONFormatter(data, depth, opts);
        container.innerHTML = '';
        container.appendChild(formatter.render());
    }

    /**
     * Build a small toolbar for a json-view container.
     */
    function buildToolbar(container) {
        if (container.hasAttribute('data-no-toolbar')) return;
        var bar = document.createElement('div');
        bar.className = 'json-view-toolbar';

        // Copy button
        var btnCopy = document.createElement('button');
        btnCopy.type = 'button';
        btnCopy.className = 'btn btn-outline-secondary btn-sm';
        btnCopy.innerHTML = '&#128203; Copy';
        btnCopy.title = 'Copy JSON to clipboard';
        btnCopy.addEventListener('click', function() {
            var raw = container.getAttribute('data-json') || '';
            try { raw = JSON.stringify(JSON.parse(raw), null, 2); } catch(e) {}
            navigator.clipboard.writeText(raw).then(function() {
                btnCopy.innerHTML = '<span class="jvt-copied">&#10003; Copied</span>';
                setTimeout(function() { btnCopy.innerHTML = '&#128203; Copy'; }, 1500);
            });
        });
        bar.appendChild(btnCopy);

        // Expand All button
        var btnExpand = document.createElement('button');
        btnExpand.type = 'button';
        btnExpand.className = 'btn btn-outline-secondary btn-sm';
        btnExpand.innerHTML = '&#9660; Expand All';
        btnExpand.title = 'Expand all nodes';
        btnExpand.addEventListener('click', function() {
            renderAtDepth(container, Infinity);
        });
        bar.appendChild(btnExpand);

        // Collapse All button
        var btnCollapse = document.createElement('button');
        btnCollapse.type = 'button';
        btnCollapse.className = 'btn btn-outline-secondary btn-sm';
        btnCollapse.innerHTML = '&#9654; Collapse All';
        btnCollapse.title = 'Collapse all nodes';
        btnCollapse.addEventListener('click', function() {
            renderAtDepth(container, 1);
        });
        bar.appendChild(btnCollapse);

        container.parentNode.insertBefore(bar, container);
    }

    function initJsonView(container) {
        var jsonStr = container.getAttribute('data-json');
        if (!jsonStr) return;
        try {
            var data = JSON.parse(jsonStr);
        } catch (e) {
            container.innerHTML = '<pre style="margin:0;white-space:pre-wrap;color:inherit">' + jsonStr.replace(/</g, '&lt;') + '</pre>';
            return;
        }
        // Store parsed data for re-rendering by toolbar buttons
        container._jsonData = data;
        var depthAttr = container.getAttribute('data-depth');
        var depth = depthAttr === 'Infinity' ? Infinity : parseInt(depthAttr || '1', 10);
        var theme = container.getAttribute('data-theme');
        if (theme === 'dark') {
            container.classList.add('json-view-dark');
        }
        renderAtDepth(container, depth);
        buildToolbar(container);
    }
    function run() {
        document.querySelectorAll('.json-view[data-json]').forEach(initJsonView);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    // Expose for dynamic use
    window.initJsonView = initJsonView;
})();
</script>
