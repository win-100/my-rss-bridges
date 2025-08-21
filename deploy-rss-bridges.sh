#!/usr/bin/env bash
set -euo pipefail

# Répertoire du dépôt perso (dans ~ de l'utilisateur appelant sudo)
REPO_DIR="$HOME/my-rss-bridges"
# Répertoire cible de RSS-Bridge
TARGET_DIR="/var/www/rss-bridge/bridges"
# Utilisateur/groupe cible
OWNER="rss-bridge:www-data"

# Vérifs
[[ -d "$REPO_DIR" ]]   || { echo "Dépôt introuvable: $REPO_DIR" >&2; exit 1; }
[[ -d "$TARGET_DIR" ]] || { echo "Dossier cible introuvable: $TARGET_DIR" >&2; exit 1; }

echo "Déploiement des bridges de $REPO_DIR vers $TARGET_DIR ..."

# Boucle sur tous les fichiers *Bridge.php du repo
for src in "$REPO_DIR"/*Bridge.php; do
    [[ -e "$src" ]] || continue
    base="$(basename "$src")"
    dst="$TARGET_DIR/$base"

    ln -sfn "$src" "$dst"
    chown -h "$OWNER" "$dst"
    echo "  ✓ $base"
done

echo "Déploiement terminé ✅"

exit 0