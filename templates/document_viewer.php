<?php
/**
 * Shared in-app document viewer. Any element can open it with:
 *
 *   onclick="attachmentViewerOpen(this)"
 *   data-url="…" data-mime="image/jpeg|application/pdf|…" data-name="…"
 *
 * which attachmentViewButton() emits, but a page may also wire the attributes
 * by hand (e.g. an avatar image). Images and PDFs render inline; other types
 * fall back to an open-in-new-tab prompt because the browser cannot display
 * them. Requires helpers/attachment_storage.php.
 */
?>

<div id="attachment-viewer" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <h3 id="attachment-viewer-title" class="text-sm font-medium text-gray-700 truncate"></h3>
            <div class="flex items-center gap-4 shrink-0">
                <a id="attachment-viewer-open" href="#" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-900 text-sm">
                    <i class="fas fa-external-link-alt mr-1"></i>Open
                </a>
                <button type="button" onclick="attachmentViewerClose()" class="text-gray-500 hover:text-gray-800" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div id="attachment-viewer-body" class="flex-1 overflow-auto bg-gray-100 p-4 flex items-center justify-center"></div>
    </div>
</div>

<script>
    function attachmentViewerOpen(trigger) {
        var mime = trigger.getAttribute('data-mime') || '';
        var fileUrl = trigger.getAttribute('data-url');
        var body = document.getElementById('attachment-viewer-body');

        body.innerHTML = '';
        if (mime.indexOf('image/') === 0) {
            var image = document.createElement('img');
            image.src = fileUrl;
            image.alt = trigger.getAttribute('data-name') || '';
            image.className = 'max-h-[78vh] max-w-full rounded shadow bg-white';
            body.appendChild(image);
        } else if (mime === 'application/pdf') {
            var frame = document.createElement('iframe');
            frame.src = fileUrl;
            frame.className = 'w-full h-[78vh] rounded border bg-white';
            body.appendChild(frame);
        } else {
            var notice = document.createElement('div');
            notice.className = 'text-center text-gray-600';
            notice.innerHTML = 'This file type cannot be previewed here.'
                + '<br><a href="' + fileUrl + '" class="text-blue-600 hover:text-blue-900 underline">Open it in a new tab</a>';
            body.appendChild(notice);
        }

        document.getElementById('attachment-viewer-title').textContent = trigger.getAttribute('data-name') || 'Document';
        document.getElementById('attachment-viewer-open').href = fileUrl;
        document.getElementById('attachment-viewer').classList.remove('hidden');
    }

    function attachmentViewerClose() {
        document.getElementById('attachment-viewer').classList.add('hidden');
        // Unloading the body stops a PDF staying open behind the dialog.
        document.getElementById('attachment-viewer-body').innerHTML = '';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            attachmentViewerClose();
        }
    });
</script>
