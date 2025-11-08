<?php
// Modern admin dashboard (promotions list)
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

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
        <div>
          <h1 class="admin-title">Gestion des promotions</h1>
          <p class="admin-sub">Ajoutez, mettez à jour ou supprimez les offres mises en avant sur la page d'accueil.</p>
        </div>
        <div class="admin-user">
          <div class="connected-as text-sm">Connecté en tant que <strong class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></strong></div>
          <a href="logout.php" class="logout-pill">Se déconnecter</a>
        </div>
      </header>

      <?php if ($flash): ?>
        <div class="mb-6 rounded-md bg-green-50 border border-green-100 p-4 text-green-700"><?= htmlspecialchars($flash) ?></div>
      <?php endif; ?>

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
                <input name="discount" required placeholder="Ex : -20%" class="input" />
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
                    <form method="post" action="delete_promotion.php" style="display:inline">
                      <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                      <button type="submit" class="btn-danger" onclick="return confirm('Supprimer ?')">Supprimer</button>
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
                      <form method="post" action="activate_user.php" style="display:inline">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                        <button type="submit" class="btn btn-success">Valider</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="delete_user.php" style="display:inline;margin-left:8px">
                      <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                      <button type="submit" class="btn btn-outline" onclick="return confirm('Supprimer le compte ?')">Supprimer</button>
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
</body>
</html>
 
