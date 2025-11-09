# Déployer AS PRO SERVICES sur Hostinger (Business)

Ce guide décrit pas à pas comment exporter votre site local (XAMPP) et le publier sur un hébergement Hostinger Business. Il couvre : préparation locale, export de fichiers, export/import de la base de données, mise à jour des configurations, tests et points de dépannage.

Remarques / hypothèses
- Vous avez le projet dans `c:\xampp\htdocs\AsproServices` (site fonctionnel localement).
- Vous avez un compte Hostinger Business et accès au panneau (hPanel), FTP/SFTP, phpMyAdmin, et au gestionnaire de fichiers.
- Ce guide se concentre sur le déploiement PHP + MySQL (pas de Node/Next). Si vous avez des builds Frontend, voyez la section "Assets/Static".

Table des matières
- Checklist rapide
- 1) Préparer le site localement
- 2) Exporter la base de données
- 3) Préparer les fichiers à transférer
- 4) Créer la base MySQL sur Hostinger
- 5) Importer la base sur Hostinger
- 6) Transférer les fichiers (FTP/SFTP ou File Manager)
- 7) Configuration côté serveur (`admin/config.php`, `.htaccess`)
- 8) Permissions & uploads
- 9) Tester et valider
- 10) Sécurisation et optimisation (SSL, PHP settings)
- Dépannage courant
- Checklist finale

Checklist rapide
- [ ] Avoir accès SSH / FTP / hPanel
- [ ] Dump SQL exporté
- [ ] Archive des fichiers du site prête à l'envoi
- [ ] Backups locaux ou point de restauration

---

## 1) Préparer le site localement
1. Vérifiez que le site fonctionne localement : ouvrez `http://localhost/AsproServices/` et testez les pages (admin/login.php, ajout/suppression promos, page publique).
2. Notez les valeurs dans `admin/config.php` : `SITE_URL`, `MYSQL_*`, `UPLOADS_DIR`, `PROMOTIONS_JSON`, `SESSION_TIMEOUT`.
3. Si vous avez des fichiers uploadés (images, PDF) dans `public/promotions`, assurez-vous qu'ils sont présents et intacts.

Conseil : mettez à jour `SESSION_TIMEOUT` dans `admin/config.php` si vous voulez un délai plus long côté production.

## 2) Exporter la base de données
Si vous utilisez MySQL local (XAMPP) :

- Ouvrez PowerShell et exécutez :

```powershell
# depuis Windows (XAMPP) : dump de la base
cd C:\xampp\mysql\bin
./mysqldump.exe -u root -p asproservice > C:\Temp\asproservice_dump.sql
```

- Entrez le mot de passe MySQL si demandé (par défaut XAMPP `root` n'a pas de mot de passe).

Alternativement utilisez phpMyAdmin (localhost/phpmyadmin) → sélectionnez `asproservice` → Export → SQL (rapide) → Go.

Compression (optionnel) :
```powershell
Compress-Archive -Path C:\Temp\asproservice_dump.sql -DestinationPath C:\Temp\asproservice_dump.zip
```

## 3) Préparer les fichiers à transférer
1. Nettoyez le dossier : retirez fichiers de dev inutiles (.git, node_modules, etc.).
2. Créez une archive ZIP de tout le site (ou juste les fichiers nécessaires). Exemple PowerShell :

```powershell
cd C:\xampp\htdocs
Compress-Archive -Path AsproServices\* -DestinationPath C:\Temp\AsproServices_files.zip
```

3. Vérifiez que `admin/config.php` contient `SITE_URL` correct pour le site final (nous mettrons à jour après l'import si nécessaire).

Important : Ne commitez pas de mots de passe publics. Gardez une copie locale avant modification.

## 4) Créer la base MySQL sur Hostinger
1. Connectez-vous à hPanel → Bases de données → MySQL Databases.
2. Créez une nouvelle base, un utilisateur et attribuez-lui tous les privilèges. Notez : host (souvent `localhost`), nomBDD, utilisateur, motdepasse.
3. Notez les informations (vous en aurez besoin pour `admin/config.php`).

## 5) Importer la base sur Hostinger
Options : phpMyAdmin (plus simple) ou SSH + mysql CLI.

Avec phpMyAdmin (hPanel) :
1. Ouvrez phpMyAdmin pour la base créée.
2. Import → Choisir fichier `asproservice_dump.sql` (ou zip si autorisé) → Go.

Si le fichier SQL est trop gros (> upload limit), vous pouvez :
- Diviser le fichier SQL (tools) ; ou
- Importer via SSH (mysql CLI) si Hostinger le permet ; ou
- Contacter le support Hostinger pour augmenter la limite.

## 6) Transférer les fichiers
Méthodes :
- SFTP (recommandé) avec FileZilla/WinSCP
- Gestionnaire de fichiers hPanel (upload ZIP puis extraction)

SFTP (FileZilla)
1. HPanel → Compte FTP → créez un compte FTP SFTP (ou utilisez SFTP/SSH credentials si fournis).
2. Dans FileZilla, hôte = ftp.yourdomain.com ou l'adresse fournie, user/pass. Port SFTP = 22.
3. Déposez les fichiers dans le dossier `public_html` ou le dossier assigné à votre domaine.

Gestionnaire de fichiers hPanel (upload ZIP)
1. Upload du ZIP dans `public_html` via File Manager.
2. Extraire l'archive là où doit vivre le site.

Structure attendue sur le serveur :
```
/public_html/
  index.php
  index.html
  admin/
  public/
  includes/
  css/
  backend/
  data/
```

## 7) Configuration côté serveur
1. Ouvrez `admin/config.php` sur le serveur (via File Manager ou SFTP) et mettez à jour :
   - `MYSQL_HOST` (souvent `localhost` chez Hostinger), `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_PORT` (3306)
   - `SITE_URL` → `https://votredomaine.tld` (important pour la génération de URLs)
   - `UPLOADS_DIR` → `__DIR__ . '/../public/promotions'` (la même logique marche)
   - `CSS_FILE` si nécessaire
   - `SESSION_TIMEOUT` (ex: 1800 = 30 minutes)

Exemple de modifications :
```php
// admin/config.php
define('MYSQL_HOST', 'localhost');
define('MYSQL_USER', 'host_user');
define('MYSQL_PASSWORD', 'secure_password');
define('MYSQL_DATABASE', 'host_db_name');
define('SITE_URL', 'https://www.votredomaine.tld');
```

2. Vérifiez que `PROMOTIONS_JSON` pointe vers un chemin accessible et que `public/promotions` existe et est lisible.

3. `.htaccess` : si vous avez des règles locales (par ex. redirection d'`index.html` vers `/`), assurez-vous que `mod_rewrite` est activé. Hostinger active généralement `mod_rewrite`.

Contenu conseillé (existant dans votre projet) :
```
DirectoryIndex index.php index.html
# autres règles de rewrite si besoin
```

## 8) Permissions & uploads
- Assurez-vous que le dossier `public/promotions` est inscriptible par PHP (permission 755 généralement, 775 si besoin, évitez 777).
- Si vous utilisez `PROMOTIONS_JSON` (stockage de fallback), assurez-vous que `data/` est inscriptible pour le webserver.

## 9) Tester et valider
1. Ouvrez `https://votredomaine.tld/` — vérifiez que la section promotions apparaît.
   - Si elle n'apparaît pas sur `index.html` ouvert directement, utilisez `https://votredomaine.tld/` (index.php injecte la section).
2. Connectez-vous à `https://votredomaine.tld/admin/login.php` et testez : ajout/suppression de promotions, suppression d'utilisateurs.
3. Vérifiez les uploads (image/pdf) et liens `public/promotions/...`.

## 10) Sécurisation & optimisation
- SSL : dans hPanel activez "Let's Encrypt SSL" puis forcer HTTPS.
- PHP version : choisissez une version compatible (PHP 8.0+ recommandé).
- Extensions : activez `pdo_mysql`, `mysqli`, `gd`/`imagick` si votre app les utilise.
- Désactivez `display_errors` en production ; loggez dans `error_log`.
- Sauvegardes : activez backups automatiques sur Hostinger si disponible.

## Dépannage courant
- Promotions absentes sur `index.html` : ouvrez la racine (`/`) ou appliquez la redirection. (Voir `index.php` qui injecte la section via `includes/promotions.php`.)
- Erreur de connexion DB : vérifiez `admin/config.php` credentials + `MYSQL_HOST`.
- Fichiers manquants : vérifiez que tous les fichiers du dossier `public/` ont été transférés.
- Erreur 500 : consultez les logs (hPanel → Logs) et vérifiez `display_errors` désactivé.
- Import SQL échoue (taille) : utilisez l'outil d'Hostinger ou import via SSH (si autorisé) ou demandez au support.

## Commandes utiles (PowerShell)
```powershell
# 1) Dump SQL local (XAMPP)
C:\xampp\mysql\bin\mysqldump.exe -u root -p asproservice > C:\Temp\asproservice_dump.sql

# 2) Compresser dossier
Compress-Archive -Path C:\xampp\htdocs\AsproServices\* -DestinationPath C:\Temp\AsproServices_files.zip

# 3) scp (si vous avez SSH access) - exemple
scp C:\Temp\AsproServices_files.zip user@remote_host:/home/user/

# 4) Décompresser côté serveur (SSH)
unzip AsproServices_files.zip -d public_html/
```

## Suggestions post-déploiement
- Mettre `SESSION_TIMEOUT` à une valeur raisonnable (ex: 1800s).
- Sauvegarder régulièrement `PROMOTIONS_JSON` et la base.
- Envisager une protection HTTP Basic sur `admin/` si vous voulez une couche supplémentaire.

## Récapitulatif rapide (checklist final)
- [ ] SQL importé ✅
- [ ] Fichiers transférés ✅
- [ ] `admin/config.php` mis à jour ✅
- [ ] `public/promotions` permissions OK ✅
- [ ] SSL activé ✅
- [ ] Tests fonctionnels réalisés (login, add promo, delete user) ✅

---

Si vous voulez, je peux :
- Ajouter le fichier `DEPLOY_HOSTINGER.md` au dépôt (je viens de le créer).
- Générer un `.htaccess` optimisé pour Hostinger (réécrit propres, cache, sécurité).
- Préparer une checklist imprimable ou un script automatisé (rsync / scp) pour déployer plus rapidement.

Dites-moi quelle option vous préférez et j'applique la suite.
