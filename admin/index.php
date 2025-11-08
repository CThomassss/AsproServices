<?php
// Modern admin dashboard (promotions list)
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Enforce server-side inactivity timeout (redirects to login.php on timeout)
enforceSessionTimeout();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$promotions = getPromotions();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Find exported CSS
$cssFile = null;
if (!empty(CSS_FILE) && strpos(CSS_FILE, '/_next/static/css/') !== false) {
    $cssFile = basename(CSS_FILE);
}
if (!$cssFile) {
    foreach (glob(__DIR__ . '/../frontend/_next/static/css/*.css') as $f) {
        $cssFile = basename($f);
        break;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Gestion des promotions</title>
  <?php if ($cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/frontend/_next/static/css/' . $cssFile) ?>" crossorigin="" />
    <link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/admin/static/tailwind-like.css') ?>" />
  <?php else: ?>
    <style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:24px}</style>
    <link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/admin/static/tailwind-like.css') ?>" />
  <?php endif; ?>
</head>
<body>
  <main class="min-h-screen">
    <div class="admin-wrapper">
      <header class="admin-header">
  <h1 class="admin-title" style="margin-bottom:0;">Gestion des promotions</h1>

        <!-- Second row: subtitle on the left, user info on the right (aligned on the same line) -->
  <div class="admin-header-row" style="display:flex;align-items:center;gap:32px;margin-top:6px;width:100%;">
          <!-- subtitle should be allowed to shrink/grow while user block stays pinned right -->
          <p class="admin-sub" style="margin:0;flex:1;min-width:0;">Ajoutez, mettez à jour ou supprimez les offres mises en avant sur la page d'accueil.</p>

          <div class="admin-user" style="white-space:nowrap;margin-left:auto;display:flex;align-items:center;gap:12px;">
            <div class="connected-as text-sm" style="margin:0;">Connecté en tant que <strong class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></strong></div>
            <a href="logout.php" class="logout-pill">Se déconnecter</a>
          </div>
        </div>
      </header>

      <!-- flash message moved into the Add Promotion form beneath the PDF controls -->

      <!-- Vertical layout: add form, then promotions list, then profiles -->
      <div class="space-y-4">
        <!-- Add promotion card -->
        <section class="card form-card">
          <div class="card-header">
            <div>
              <h2 class="section-title">Ajouter une promotion</h2>
              <p class="section-subtitle mt-2">Ajoutez, mettez à jour ou supprimez les offres mises en avant sur la page d'accueil.</p>
            </div>
            <div class="form-actions">
              <!-- empty: actions could go here later (preview/save draft) -->
            </div>
          </div>
          <form action="add_promotion.php" method="post" enctype="multipart/form-data" class="form-grid mt-4">
            <div class="form-row">
              <label class="text-sm">Titre de l'offre
                <input name="title" required placeholder="Ex : Pack outillage électricien" class="input" />
              </label>
            </div>
            <div class="form-row">
              <label class="text-sm">Avantage / remise
                <!-- Use a numeric input to prevent arbitrary text. We'll format to percent on submit -->
                <input id="discount-input" name="discount_raw" type="number" inputmode="numeric" min="0" max="100" step="1" required placeholder="Ex : 20" class="input" aria-describedby="discount-help" />
                <!-- Hidden field that will contain the normalized value sent to the server (e.g. "-20%") -->
                <input type="hidden" name="discount" id="discount-hidden" />
                <div id="discount-help" class="text-xs text-secondary mt-1">Entrez un nombre entre 0 et 100. Le symbole % et le signe <code>-</code> seront ajoutés automatiquement.</div>
                <div id="discount-error" class="text-xs text-red-600 mt-1" style="display:none;">Veuillez saisir un nombre entier entre 0 et 100.</div>
              </label>
            </div>
            <div class="form-row" style="grid-column:1 / -1">
              <label class="text-sm">Description
                <textarea name="description" required placeholder="Détails de l'offre, contenu du pack..." class="textarea"></textarea>
              </label>
            </div>
            <div class="form-row">
              <label class="text-sm">Valable jusqu'au
                <input name="validUntil" type="date" required class="input" />
              </label>
            </div>
            <div class="form-row">
              <label class="text-sm">Image de l'offre (téléversement)
                <input type="file" name="image" />
              </label>
              <p class="text-xs text-secondary mt-2">Formats JPG/PNG/WebP. 2 Mo max. Optionnel si vous fournissez un lien.</p>
            </div>
            <div class="form-row pdf-row" style="grid-column:1 / -1">
              <input type="checkbox" id="use_urlpdf" name="use_urlpdf" />
              <label for="use_urlpdf" class="text-sm" style="margin-left:8px">Ajouter un fichier téléchargeable (PDF)</label>
              <div class="pdf-controls">
                <input type="file" name="pdf" class="pdf-upload" />
                <input type="text" name="urlpdf" placeholder="URL publique du PDF (ex: https://...)" class="input mt-2" />
                <p class="text-xs text-secondary mt-2">Cochez la case pour afficher ces options. Si vous saisissez une URL, elle sera enregistrée dans la base (pdfUrl).</p>
              </div>
            </div>
              <div id="pdf-selected-name" class="text-sm text-slate-600 mt-2" aria-live="polite"></div>
              <?php if (!empty($flash)): ?>
                <div id="form-flash" class="mt-3 rounded-md bg-green-50 border border-green-100 p-3 text-green-700"><?= htmlspecialchars($flash) ?></div>
              <?php endif; ?>
            <div style="grid-column:1 / -1" class="form-actions">
              <button type="submit" class="cta-button">Ajouter la promotion</button>
            </div>
          </form>
        </section>

        <!-- Promotions list below the form -->
        <section>
          <h2 class="section-heading mb-3">Promotions en cours</h2>
          <?php if (empty($promotions)): ?>
            <div class="card">Aucune promotion.</div>
          <?php else: ?>
            <div class="promotions-list">
              <?php foreach ($promotions as $p): ?>
                <article class="promo-card">
                  <div class="promo-left">
                    <div class="promo-code text-xs"><?= htmlspecialchars(substr($p['title'],0,6)) ?></div>
                    <div class="promo-title"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="promo-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="promo-valid">Valable jusqu'au <?= htmlspecialchars($p['validUntil'] ?? '') ?></div>
                  </div>
                  <div class="promo-right">
                    <div class="promo-actions">
                      <?php if (!empty($p['imageUrl'])): ?><a href="<?= htmlspecialchars(SITE_URL . '/' . ltrim($p['imageUrl'],'/')) ?>" class="promo-link">Ouvrir l'image</a><?php endif; ?>
                      <?php if (!empty($p['pdfUrl'])): ?><a href="<?= htmlspecialchars(SITE_URL . '/' . ltrim($p['pdfUrl'],'/')) ?>" class="promo-link">Télécharger le PDF</a><?php endif; ?>
                    </div>
                    <form method="post" action="delete_promotion.php" style="display:inline" class="delete-form">
                      <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                      <button type="button" class="btn-danger btn-delete" data-title="<?= htmlspecialchars($p['title']) ?>">Supprimer</button>
                    </form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <!-- Profiles section placeholder (can reuse functions.php to list users) -->
      <section class="mt-10">
        <h2 class="section-heading mb-3">Gestion des profils</h2>
        <p class="text-sm text-slate-600 mb-4">Liste des comptes enregistrés. Validez les comptes en attente.</p>
        <!-- Simple list using users table if available -->
        <div class="space-y-3">
          <?php
            // try to list users if DB present
            try {
              $pdo = getPDO();
              if ($pdo) {
                $stmt = $pdo->query('SELECT id, username, role, isActive, createdAt FROM users ORDER BY id DESC LIMIT 20');
                $users = $stmt->fetchAll();
              } else {
                $users = [];
              }
            } catch (Exception $e) { $users = []; }
          ?>
          <?php if (empty($users)): ?>
            <div class="card bg-white rounded-2xl p-4 text-slate-600">Aucun profil listé.</div>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <article class="profile-card">
                <div class="profile-left">
                  <div class="profile-name"><?= htmlspecialchars($u['username']) ?></div>
                  <div class="profile-meta">Créé le <?= htmlspecialchars($u['createdAt'] ?? '') ?></div>
                </div>
                <div class="profile-right">
                  <div class="profile-actions">
                    <?php if (!empty($u['isActive'])): ?>
                      <span class="status-badge status-active">Activé</span>
                    <?php else: ?>
                      <span class="status-badge status-pending">En attente</span>
                    <?php endif; ?>
                    <span class="role-badge"><?= htmlspecialchars($u['role'] ?? '') ?></span>
                  </div>
                  <div>
                    <?php if (empty($u['isActive'])): ?>
                      <?php if (!empty($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'superadmin'): ?>
                        <form method="post" action="activate_user.php" style="display:inline" class="activate-user-form" data-id="<?= htmlspecialchars($u['id']) ?>">
                          <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                          <button type="button" class="btn btn-success btn-activate-user" data-id="<?= htmlspecialchars($u['id']) ?>" data-username="<?= htmlspecialchars($u['username']) ?>">Valider</button>
                        </form>
                      <?php else: ?>
                        <!-- only superadmin can validate: no action (status shown above) -->
                      <?php endif; ?>
                    <?php endif; ?>
                    <form method="post" action="delete_user.php" style="display:inline;margin-left:8px" class="delete-user-form" data-id="<?= htmlspecialchars($u['id']) ?>">
                      <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                      <button type="button" class="btn btn-outline btn-delete-user" data-id="<?= htmlspecialchars($u['id']) ?>" data-username="<?= htmlspecialchars($u['username']) ?>">Supprimer</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

    </div>
  </main>
  <!-- Confirmation modal -->
  <div id="confirm-modal" role="dialog" aria-modal="true" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;">
    <div id="confirm-backdrop" style="position:absolute;inset:0;background:rgba(2,6,23,0.6);"></div>
    <div style="position:relative;max-width:480px;width:90%;background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(2,6,23,0.4);padding:20px;z-index:2;">
      <h3 id="confirm-title" style="margin:0 0 8px;font-size:18px;color:#0b1724">Confirmer la suppression</h3>
      <p id="confirm-message" style="margin:0 0 18px;color:#374151">Voulez-vous vraiment supprimer cette promotion ? Cette action est irréversible.</p>
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <button id="confirm-cancel" class="btn" style="background:#fff;border:1px solid rgba(11,23,36,0.06)">Annuler</button>
        <button id="confirm-reject" class="btn" style="background:#ef4444;border:none;color:#fff;display:none">Refuser</button>
        <button id="confirm-ok" class="btn-danger" style="background:#ef4444;border:none;color:#fff">Supprimer</button>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var modal = document.getElementById('confirm-modal');
      var titleEl = document.getElementById('confirm-title');
      var msgEl = document.getElementById('confirm-message');
      var btnOk = document.getElementById('confirm-ok');
      var btnCancel = document.getElementById('confirm-cancel');
      var btnReject = document.getElementById('confirm-reject');
      var activeForm = null;
      var activeAction = null; // 'delete-promo' | 'delete-user' | 'activate-user'

      // Discount input: client-side validation and normalization
      var promoForm = document.querySelector('form[action="add_promotion.php"]');
      var discountInput = document.getElementById('discount-input');
      var discountHidden = document.getElementById('discount-hidden');
      var discountError = document.getElementById('discount-error');
      if (discountInput) {
        // simple live validation UI
        discountInput.addEventListener('input', function(){
          if (!discountError) return;
          var v = this.value;
          if (v === '') { discountError.style.display = 'none'; return; }
          var n = Number(v);
          if (!Number.isFinite(n) || n < 0 || n > 100) {
            discountError.textContent = 'Veuillez saisir un nombre entre 0 et 100.';
            discountError.style.display = 'block';
          } else {
            discountError.style.display = 'none';
          }
        });
      }
      if (promoForm) {
        promoForm.addEventListener('submit', function(e){
          if (!discountInput || !discountHidden) return; // no-op
          var raw = discountInput.value;
          if (raw === '') {
            discountError.textContent = 'La remise est requise.';
            discountError.style.display = 'block';
            e.preventDefault();
            return;
          }
          var n = Math.round(Number(raw));
          if (!Number.isFinite(n) || n < 0 || n > 100) {
            discountError.textContent = 'La remise doit être un nombre entre 0 et 100.';
            discountError.style.display = 'block';
            e.preventDefault();
            return;
          }
          // normalized value: '0%' or '-NN%'
          var normalized = (n === 0) ? '0%' : ('-' + n + '%');
          discountHidden.value = normalized;
          // allow submit; visible number input remains untouched
        });
      }

      document.addEventListener('click', function(e){
        // promotion delete buttons (existing)
        var promoBtn = e.target.closest && e.target.closest('.btn-delete');
        if (promoBtn) {
          e.preventDefault();
          activeForm = promoBtn.closest('form');
          activeAction = 'delete-promo';
          var title = promoBtn.getAttribute('data-title') || 'cette promotion';
          titleEl.textContent = 'Supprimer « ' + title + ' » ?';
          msgEl.textContent = 'Cette action supprimera définitivement la promotion. Confirmez pour continuer.';
          btnReject.style.display = 'none';
          btnOk.textContent = 'Supprimer';
          modal.style.display = 'flex';
          modal.setAttribute('aria-hidden','false');
          btnOk.focus();
          return;
        }

        // user delete button
        var delUserBtn = e.target.closest && e.target.closest('.btn-delete-user');
        if (delUserBtn) {
          e.preventDefault();
          activeForm = delUserBtn.closest('form');
          activeAction = 'delete-user';
          var uname = delUserBtn.getAttribute('data-username') || 'cet utilisateur';
          titleEl.textContent = 'Supprimer le compte de « ' + uname + ' » ?';
          msgEl.textContent = 'Voulez-vous vraiment supprimer ce compte ? Cette action est irréversible.';
          btnReject.style.display = 'none';
          btnOk.textContent = 'Supprimer';
          modal.style.display = 'flex';
          modal.setAttribute('aria-hidden','false');
          btnOk.focus();
          return;
        }

        // user activate button (superadmin only)
        var actBtn = e.target.closest && e.target.closest('.btn-activate-user');
        if (actBtn) {
          e.preventDefault();
          activeForm = actBtn.closest('form');
          activeAction = 'activate-user';
          var uname = actBtn.getAttribute('data-username') || 'cet utilisateur';
          titleEl.textContent = 'Valider l\u2019utilisateur « ' + uname + ' » ?';
          msgEl.textContent = 'Confirmez si vous souhaitez activer ce compte. Sinon vous pouvez le refuser (supprimer).';
          btnReject.style.display = 'inline-block';
          btnReject.textContent = 'Refuser';
          btnOk.textContent = 'Valider';
          modal.style.display = 'flex';
          modal.setAttribute('aria-hidden','false');
          btnOk.focus();
          return;
        }
      });

      btnCancel.addEventListener('click', function(){
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden','true');
        activeForm = null;
        activeAction = null;
      });

      btnOk.addEventListener('click', function(){
        if (!activeForm) { modal.style.display='none'; activeAction=null; return; }
        // submit the form related to the action
        if (activeAction === 'delete-promo' || activeAction === 'delete-user' || activeAction === 'activate-user') {
          activeForm.submit();
        }
      });

      btnReject.addEventListener('click', function(){
        // when rejecting an activation, submit a delete user form (POST to delete_user.php)
        if (!activeForm) { modal.style.display='none'; return; }
        if (activeAction === 'activate-user') {
          // extract user id from the activate form or data attribute
          var id = activeForm.querySelector('input[name="id"]') ? activeForm.querySelector('input[name="id"]').value : activeForm.getAttribute('data-id');
          // create and submit a small form to delete_user.php
          var f = document.createElement('form');
          f.method = 'post';
          f.action = 'delete_user.php';
          var i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value = id; f.appendChild(i);
          document.body.appendChild(f);
          f.submit();
        }
      });

      // Close when clicking backdrop
      document.getElementById('confirm-backdrop').addEventListener('click', function(){
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden','true');
        activeForm = null;
        activeAction = null;
      });

      // keyboard: ESC to cancel
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display === 'flex') { btnCancel.click(); } });
    })();
  </script>
  <!-- Attempt to log out the user when they close or navigate away from the admin page -->
  <script>
    (function(){
      function sendLogout() {
        // use navigator.sendBeacon if available (safe on unload)
        try {
          if (navigator.sendBeacon) {
            navigator.sendBeacon('logout.php');
          } else {
            // fallback: synchronous XHR (may be blocked in modern browsers)
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'logout.php', false);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('via=unload');
          }
        } catch (e) {
          // ignore errors — best-effort
        }
      }
      window.addEventListener('unload', sendLogout, {passive:true});
    })();
  </script>
</body>
</html>
 
