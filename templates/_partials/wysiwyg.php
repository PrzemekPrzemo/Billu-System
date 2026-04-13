<?php
/**
 * Simple WYSIWYG editor — zero CDN, zero dependencies.
 * Replaces TinyMCE which failed due to no-api-key CDN.
 *
 * Usage: include this file at the bottom of any template that uses
 * <textarea class="wysiwyg-editor">. The script auto-initializes all matching textareas.
 */
?>
<style>
.wysiwyg-wrap { border:1px solid var(--gray-300); border-radius:8px; overflow:hidden; background:#fff; }
.wysiwyg-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:6px 8px; background:var(--gray-50); border-bottom:1px solid var(--gray-200); }
.wysiwyg-toolbar button { background:none; border:1px solid transparent; border-radius:4px; padding:4px 8px; cursor:pointer; font-size:13px; color:var(--gray-700); line-height:1; }
.wysiwyg-toolbar button:hover { background:var(--gray-100); border-color:var(--gray-300); }
.wysiwyg-toolbar button.active { background:var(--gray-200); border-color:var(--gray-400); }
.wysiwyg-toolbar .sep { width:1px; background:var(--gray-300); margin:0 4px; align-self:stretch; }
.wysiwyg-body { min-height:200px; padding:12px 14px; font-family:Arial,sans-serif; font-size:14px; line-height:1.6; outline:none; color:#333; }
.wysiwyg-body:empty::before { content:attr(data-placeholder); color:var(--gray-400); pointer-events:none; }
.wysiwyg-body p { margin:0 0 8px; }
.wysiwyg-body ul, .wysiwyg-body ol { margin:0 0 8px 20px; }
</style>
<script>
(function() {
    document.querySelectorAll('textarea.wysiwyg-editor').forEach(function(textarea) {
        // Create wrapper
        var wrap = document.createElement('div');
        wrap.className = 'wysiwyg-wrap';

        // Toolbar
        var toolbar = document.createElement('div');
        toolbar.className = 'wysiwyg-toolbar';
        toolbar.innerHTML =
            '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>' +
            '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>' +
            '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>' +
            '<span class="sep"></span>' +
            '<button type="button" data-cmd="insertUnorderedList" title="Lista">&#8226; Lista</button>' +
            '<button type="button" data-cmd="insertOrderedList" title="Lista numerowana">1. Lista</button>' +
            '<span class="sep"></span>' +
            '<button type="button" data-cmd="createLink" title="Link">&#128279; Link</button>' +
            '<button type="button" data-cmd="removeFormat" title="Usuń formatowanie">&#10005;</button>' +
            '<span class="sep"></span>' +
            '<button type="button" data-cmd="_code" title="Kod HTML">&lt;/&gt;</button>';

        // Content editable area
        var body = document.createElement('div');
        body.className = 'wysiwyg-body';
        body.contentEditable = 'true';
        body.dataset.placeholder = 'Wpisz treść...';
        body.innerHTML = textarea.value;

        // Set min-height from textarea rows
        var rows = parseInt(textarea.getAttribute('rows')) || 10;
        body.style.minHeight = Math.max(rows * 22, 120) + 'px';

        wrap.appendChild(toolbar);
        wrap.appendChild(body);

        // Replace textarea visually (keep hidden for form submit)
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrap, textarea);

        // Sync content → textarea on input
        body.addEventListener('input', function() {
            textarea.value = body.innerHTML;
        });

        // Also sync on form submit
        var form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                textarea.value = body.innerHTML;
            });
        }

        // Toolbar actions
        var codeMode = false;
        toolbar.addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();
            var cmd = btn.dataset.cmd;

            if (cmd === '_code') {
                // Toggle HTML source view
                codeMode = !codeMode;
                btn.classList.toggle('active', codeMode);
                if (codeMode) {
                    body.innerText = body.innerHTML;
                    body.style.fontFamily = 'monospace';
                    body.style.fontSize = '12px';
                    body.style.whiteSpace = 'pre-wrap';
                } else {
                    body.innerHTML = body.innerText;
                    body.style.fontFamily = 'Arial, sans-serif';
                    body.style.fontSize = '14px';
                    body.style.whiteSpace = 'normal';
                    textarea.value = body.innerHTML;
                }
                return;
            }

            if (codeMode) return; // Disable formatting in code mode

            if (cmd === 'createLink') {
                var url = prompt('URL:');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
            } else {
                document.execCommand(cmd, false, null);
            }
            textarea.value = body.innerHTML;
            body.focus();
        });
    });
})();
</script>
