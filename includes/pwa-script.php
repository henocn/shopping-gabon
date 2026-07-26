<script>
    (function() {
        // 1. Détection du mode d'affichage
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                          window.navigator.standalone ||
                          document.referrer.includes('android-app://');

        // 2. Enregistrement du Service Worker avec le bon scope
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/management/' }).catch(function() {});
        }

        // 3. Gestion de l'installation PWA
        var deferredPrompt = null;
        var banner = document.getElementById('pwa-install-banner');
        var installBtn = document.getElementById('pwa-install-btn');
        var dismissBtn = document.getElementById('pwa-install-dismiss');
        var installText = banner ? banner.querySelector('.pwa-install-text') : null;
        var promptShown = false;

        if (!banner || !installBtn || !dismissBtn) return;

        function showBanner() {
            if (promptShown) return;
            banner.classList.remove('d-none');
            document.body.classList.add('pwa-banner-shown');
            promptShown = true;
        }

        function hideBanner() {
            banner.classList.add('d-none');
            document.body.classList.remove('pwa-banner-shown');
            promptShown = false;
        }

        function setInstallMessage(description) {
            if (!installText) return;
            var title = document.createElement('strong');
            title.textContent = "Installer l'application";
            var text = document.createElement('span');
            text.textContent = description;
            installText.replaceChildren(title, text);
        }

        function showNativePrompt(e) {
            e.preventDefault();
            if (isStandalone) return;

            deferredPrompt = e;
            setInstallMessage('Gérez vos commandes plus rapidement.');
            installBtn.textContent = 'Installer';
            installBtn.onclick = function() {
                if (!deferredPrompt) {
                    hideBanner();
                    return;
                }

                var promptEvent = deferredPrompt;
                deferredPrompt = null;
                promptEvent.prompt();
                promptEvent.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        hideBanner();
                    }
                }).catch(function() {
                    hideBanner();
                });
            };
            showBanner();
        }

        window.addEventListener('beforeinstallprompt', showNativePrompt);

        dismissBtn.addEventListener('click', function() {
            hideBanner();
            deferredPrompt = null;
        });

        window.addEventListener('appinstalled', function() {
            hideBanner();
            deferredPrompt = null;
        });

    })();
</script>
