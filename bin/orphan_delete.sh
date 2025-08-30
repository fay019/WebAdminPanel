#!/usr/bin/env bash
set -euo pipefail

# Usage: orphan_delete.sh <chemin_du_dossier>

dir="$1"

if [[ -z "$dir" ]]; then
  echo "[ERREUR] Aucun dossier fourni." >&2
  exit 1
fi

# Vérification que le dossier existe
if [[ ! -d "$dir" ]]; then
  echo "[ERREUR] Le dossier n’existe pas : $dir" >&2
  exit 1
fi

# Vérification de sécurité : doit être sous /var/www
if [[ "$dir" != /var/www/* ]]; then
  echo "[ERREUR] Suppression refusée : chemin en dehors de /var/www" >&2
  exit 1
fi

# Suppression
echo "[INFO] Suppression du dossier : $dir"
rm -rf -- "$dir"

if [[ -d "$dir" ]]; then
  echo "[ERREUR] La suppression a échoué : $dir" >&2
  exit 1
fi

echo "[OK] Dossier supprimé avec succès : $dir"