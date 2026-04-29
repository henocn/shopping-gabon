/**
 * Affiche le toast de message (succès/erreur) au chargement de la page boutique.
 */
function initOrderToast() {
  const toastEl = document.getElementById("liveToast");
  const toastBody = document.getElementById("toastMessage");

  if (!toastEl || !toastBody) return;

  const message = (toastBody.textContent || "").trim();
  if (!message) return;

  // Réinitialiser proprement les classes de base du toast
  toastEl.className = "toast align-items-center text-white border-0";
  toastEl.classList.add(/succ[eè]s|success/i.test(message) ? "bg-success" : "bg-danger");

  // Si Bootstrap JS est chargé, utiliser l'API officielle
  if (window.bootstrap && typeof bootstrap.Toast === "function") {
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
  } else {
    // Fallback sans Bootstrap : simplement montrer/cacher via les classes
    toastEl.classList.add("show");
    setTimeout(function () {
      toastEl.classList.remove("show");
    }, 5000);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initOrderToast);
} else {
  // DOM déjà prêt (script chargé en bas de page) : exécuter immédiatement
  initOrderToast();
}
