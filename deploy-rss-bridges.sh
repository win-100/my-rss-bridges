#!/usr/bin/env bash
set -euo pipefail

# Dossiers (modifiables via variables d'env si besoin)
REPO_DIR="${REPO_DIR:-$HOME/my-rss-bridges}"
TARGET_DIR="${TARGET_DIR:-/var/www/rss-bridge/bridges}"

# Vérifs minimales
[[ -d "$REPO_DIR" ]]   || { echo "Dépôt introuvable: $REPO_DIR" >&2; exit 1; }
[[ -d "$TARGET_DIR" ]] || { echo "Dossier cible introuvable: $TARGET_DIR" >&2; exit 1; }
[[ -w "$TARGET_DIR" ]] || { echo "Pas d'écriture sur: $TARGET_DIR (utilisateur: $(id -un))" >&2; exit 1; }

echo "Déploiement des bridges de $REPO_DIR vers $TARGET_DIR ..."
count=0

shopt -s nullglob
for src in "$REPO_DIR"/*Bridge.php; do
  base="$(basename "$src")"
  ln -sfn "$src" "$TARGET_DIR/$base"
  echo "  ✓ $base"
  count=$((count + 1))
done
shopt -u nullglob

[[ $count -gt 0 ]] || echo "Aucun fichier *Bridge.php trouvé dans $REPO_DIR"
echo "Déploiement terminé ✅"

exit 0
