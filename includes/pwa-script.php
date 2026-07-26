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
        var fallbackTimer = null;

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

        function showManualGuide() {
            if (deferredPrompt || isStandalone || promptShown) return;

            var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
            setInstallMessage(isIos
                ? 'Touchez Partager puis Ajouter à l’écran d’accueil.'
                : 'Ouvrez le menu ⋮ puis Ajouter à l’écran d’accueil.');
            installBtn.textContent = 'Comment faire ?';
            installBtn.onclick = function() {
                setInstallMessage(isIos
                    ? 'Dans Safari : Partager → Ajouter à l’écran d’accueil.'
                    : 'Dans Chrome : menu ⋮ → Installer l’application ou Ajouter à l’écran d’accueil.');
                installBtn.textContent = 'J’ai compris';
                installBtn.onclick = hideBanner;
            };
            showBanner();
        }

        function showNativePrompt(e) {
            e.preventDefault();
            if (isStandalone) return;

            deferredPrompt = e;
            if (fallbackTimer) window.clearTimeout(fallbackTimer);
            setInstallMessage('Gérez vos commandes plus rapidement.');
            installBtn.textContent = 'Installer';
            installBtn.onclick = function() {
                if (!deferredPrompt) {
                    showManualGuide();
                    return;
                }

                var promptEvent = deferredPrompt;
                deferredPrompt = null;
                promptEvent.prompt();
                promptEvent.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        hideBanner();
                    } else {
                        showManualGuide();
                    }
                }).catch(function() {
                    showManualGuide();
                });
            };
            showBanner();
        }

        window.addEventListener('beforeinstallprompt', showNativePrompt);

        dismissBtn.addEventListener('click', function() {
            hideBanner();
            deferredPrompt = null;
            if (fallbackTimer) window.clearTimeout(fallbackTimer);
        });

        window.addEventListener('appinstalled', function() {
            hideBanner();
            deferredPrompt = null;
            if (fallbackTimer) window.clearTimeout(fallbackTimer);
        });

        if (!isStandalone) {
            fallbackTimer = window.setTimeout(showManualGuide, 2500);
        }

    })();
</script>
