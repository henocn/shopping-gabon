<?php
/**
 * LUXEMARKET - Initialisation des notifications push
 * À inclure dans le <head> ou avant </body> des pages admin
 */
?>

<!-- Banner pour activer les notifications push -->
<div id="push-notif-banner" class="d-none align-items-center gap-2 py-2 px-3 rounded bg-light border position-fixed top-0 start-50 translate-middle" style="z-index: 9998; width: 90%; max-width: 600px;">
    <span class="small text-muted flex-grow-1">Recevoir les notifications push pour les nouvelles commandes</span>
    <button type="button" id="push-enable-btn" class="btn btn-primary btn-sm">Activer</button>
    <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('push-notif-banner').classList.add('d-none')"></button>
</div>

<!-- Initialisation de l'ID utilisateur pour les notifications -->
<script>
    window.LUXEMARKET_USER_ID = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
</script>

<!-- Chargement du script de notifications push -->
<script src="/assets/js/push-notifications.js"></script>
