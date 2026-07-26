<?php
/**
 * LUXEMARKET - Initialisation des notifications push
 * À inclure dans le <head> ou avant </body> des pages admin
 */
?>

<!-- Banner pour activer les notifications push -->
<div id="push-notif-banner" class="push-notif-banner d-none align-items-center gap-2 py-2 px-3 rounded bg-light border">
    <div class="flex-grow-1">
        <strong class="d-block">Notifications de commandes</strong>
        <span data-push-message class="small text-muted">Recevez une alerte lors d'une nouvelle commande, même application fermée.</span>
    </div>
    <button type="button" id="push-enable-btn" class="btn btn-primary btn-sm text-nowrap">Activer les notifications</button>
    <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('push-notif-banner').classList.add('d-none')"></button>
</div>

<!-- Initialisation de l'ID utilisateur pour les notifications -->
<script>
    window.LUXEMARKET_USER_ID = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
    window.LUXEMARKET_CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;
</script>

<!-- Chargement du script de notifications push -->
<script src="/assets/js/push-notifications.js"></script>
