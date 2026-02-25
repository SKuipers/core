<div class="flex-grow relative inline-flex">
    <input type="text" <?= $attributes; ?> class="w-full min-w-0 py-2 rounded-l-md placeholder:text-gray-500  sm:text-sm sm:leading-5">

    <button type="button" class="-ml-px px-4 bg-gray-100 inline-flex items-center border border-gray-400 rounded-r-md text-base text-gray-600" onclick="scanner(this)">
        <?= icon('solid', 'qr-code', 'pointer-events-none size-5 text-gray-700 fill-current'); ?>
    </button>
</div>

<script type="text/javascript">
function scanner(self) {
    const preview = document.getElementById("preview");
    const cameraButton = document.getElementById("cameraButton");
    
    if (preview) {
        preview.remove();
        if (cameraButton) cameraButton.remove();
    } else {
        const video = document.createElement('video');
        video.id = 'preview';
        video.className = 'w-64';
        self.parentElement.parentElement.appendChild(video);
    }
    
    let scanner = new Instascan.Scanner({ video: document.getElementById("preview") });
    scanner.addListener("scan", function (content) {
        scanner.stop();
        const input = self.parentElement.querySelector("input");
        if (input) input.value = content;
        
        const previewEl = document.getElementById("preview");
        const cameraButtonEl = document.getElementById("cameraButton");
        if (previewEl) previewEl.remove();
        if (cameraButtonEl) cameraButtonEl.remove();
    });
    
    Instascan.Camera.getCameras().then(function (cameras) {
        let count = 0;
        if (cameras.length > 0) {
            scanner.start(cameras[count]);
            if (cameras.length > 1) {
                const existingButton = document.getElementById("cameraButton");
                const previewExists = document.getElementById("preview");
                
                if (!existingButton && previewExists) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'button border rounded-r-md text-sm text-gray-600';
                    button.id = 'cameraButton';
                    button.style.height = '36px';
                    button.textContent = 'Change Camera';
                    self.parentElement.parentElement.appendChild(button);
                }
                
                const cameraBtn = document.getElementById("cameraButton");
                if (cameraBtn) {
                    cameraBtn.addEventListener("click", function() {
                        count++;
                        if (count >= cameras.length) {
                            count = 0;
                        }
                        scanner.start(cameras[count]);
                    });
                }
            }
        } else {
            scanner.stop();
            const input = self.parentElement.querySelector("input");
            if (input) input.value = "No camera available";
        }
    }).catch(function (e) {
        const input = self.parentElement.querySelector("input");
        if (input) input.value = "Camera Error";
    });   
}
</script>


<?php if (!empty($autocomplete)) { ?>
    <script type="text/javascript">
    const autocompleteEl = document.getElementById("<?= $id; ?>");
    if (autocompleteEl && typeof autocompleteEl.autocomplete === 'function') {
        autocompleteEl.autocomplete({source: [<?= $autocomplete; ?>]});
    }
    </script>
<?php } ?>
