(function () {
    document.addEventListener('DOMContentLoaded', function() {

        // If an element with id "status" is found, do the version check
        // Supposed to only have one "#status" in the page, so this is only run once.
        const statusElement = document.querySelector("#status");
        if (statusElement) {
            const edgeIndicator = document.querySelector('#cuttingEdgeCode');
            const edgeHiddenInput = document.querySelector("input[name=cuttingEdgeCodeHidden]");

            // environment check
            var gibboninstallerError = false;
            if (typeof gibboninstaller === 'undefined') {
                console.error('Unable to find gibboninstaller in global variables');
                gibboninstallerError = true;
            } else if (typeof gibboninstaller.version === 'undefined') {
                console.error('No gibbon version is specified in the environment');
                gibboninstallerError = true;
            } else if (typeof gibboninstaller.msg === 'undefined') {
                console.error('Translation function gibboninstaller.msg() does not exits.');
                gibboninstallerError = true;
            }
            if (gibboninstallerError) {
                statusElement.setAttribute("class", "error");
                statusElement.innerHTML = "Cutting Edge Code check: Unexpected javascript error.";
                return;
            }

            // cutting edge code check
            fetch("https://gibbonedu.org/services/version/devCheck.php?version=" + gibboninstaller.version + "&callback=fnsuccesscallback", {
                method: "GET",
                mode: "cors"
            })
            .then(response => response.text())
            .then(text => {
                // Parse JSONP response by extracting JSON from callback
                const jsonMatch = text.match(/fnsuccesscallback\((.*)\)/);
                if (jsonMatch && jsonMatch[1]) {
                    return JSON.parse(jsonMatch[1]);
                }
                throw new Error('Invalid JSONP response');
            })
            .then(data => {
                statusElement.setAttribute("class", "success");
                if (data['status'] === 'false') {
                    statusElement.innerHTML = gibboninstaller.msg('__edge_code_check_success__');
                } else {
                    statusElement.innerHTML = gibboninstaller.msg('__edge_code_check_success__');
                    edgeIndicator.value = 'Yes';
                    edgeHiddenInput.value = 'Y';
                }
            })
            .catch(error => {
                statusElement.setAttribute("class", "error");
                statusElement.innerHTML = gibboninstaller.msg('__edge_code_check_failed__');
                console.error('Version check error:', error);
            });
        }
    });
})();
