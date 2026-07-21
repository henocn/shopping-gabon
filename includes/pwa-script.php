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


        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            
            // Si on est déjà en standalone, on ignore
            if (isStandalone) return;

            deferredPrompt = e;


            if (installText) {
                installText.innerHTML = '<strong>Installer l\'application</strong><span>G&eacute;rez vos commandes plus rapidement</span>';
            }
            installBtn.textContent = 'Installer';
            installBtn.onclick = function() {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        hideBanner();
                        // Recharger pour activer le mode standalone
                        window.location.reload();
                    }
                    deferredPrompt = null;
                });
            };
            showBanner();
        });

        dismissBtn.addEventListener('click', function() {
            hideBanner();
            deferredPrompt = null;
        });

        window.addEventListener('appinstalled', function() {
            hideBanner();
            deferredPrompt = null;
            // Recharger pour appliquer le mode standalone
            window.location.reload();
        });

    })();
</script>
