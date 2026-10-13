#!/bin/bash
# =============================================================================
# check_files.sh — Vérifie que chaque fichier est au bon endroit
# Principe : le nom du fichier doit apparaître dans son propre contenu
# (commentaire d'en-tête, require, include, class, etc.)
# Usage : bash check_files.sh [racine_projet]
# =============================================================================

ROOT="${1:-.}"
PASS=0
FAIL=0
WARN=0

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
DIM='\033[2m'
NC='\033[0m'

echo ""
echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  FILE PLACEMENT CHECK — $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${CYAN}  Racine : ${ROOT}${NC}"
echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
echo ""

# Fichiers à ignorer (binaires, locks, logs, etc.)
IGNORE_PATTERN='\.(jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot|pdf|zip|gz|log|lock|map)$'

# Extensions à analyser
INCLUDE_PATTERN='\.(php|js|css|sh|json|md|html)$'

check_file() {
    local filepath="$1"
    local filename
    filename=$(basename "$filepath")
    local filename_noext="${filename%.*}"

    # Ignorer les fichiers non texte
    if echo "$filename" | grep -qiE "$IGNORE_PATTERN"; then
        return
    fi

    # Ignorer si pas dans les extensions cibles
    if ! echo "$filename" | grep -qiE "$INCLUDE_PATTERN"; then
        return
    fi

    # Ignorer les fichiers vides
    if [ ! -s "$filepath" ]; then
        echo -e "  ${YELLOW}⚠ VIDE${NC}     $filepath"
        ((WARN++))
        return
    fi

    # Lire les 30 premières lignes (en-têtes de fichier)
    local header
    header=$(head -30 "$filepath" 2>/dev/null)

    # Recherche du nom de fichier (avec ou sans extension) dans le contenu
    # Insensible à la casse, cherche aussi le chemin relatif partiel
    local rel_path="${filepath#$ROOT/}"

    local found=0

    # Test 1 : nom complet (ex: superadmin-back.php)
    if echo "$header" | grep -qi "$filename"; then
        found=1
    fi

    # Test 2 : nom sans extension (ex: superadmin-back)
    if [ $found -eq 0 ] && echo "$header" | grep -qi "$filename_noext"; then
        found=1
    fi

    # Test 3 : chemin relatif partiel (ex: api/superadmin/router)
    if [ $found -eq 0 ] && echo "$header" | grep -qi "$rel_path"; then
        found=1
    fi

    # Test 4 : chemin relatif sans extension
    local rel_noext="${rel_path%.*}"
    if [ $found -eq 0 ] && echo "$header" | grep -qi "$rel_noext"; then
        found=1
    fi

    if [ $found -eq 1 ]; then
        echo -e "  ${GREEN}✓ OK${NC}       ${DIM}$rel_path${NC}"
        ((PASS++))
    else
        echo -e "  ${RED}✗ SUSPECT${NC}  $rel_path"
        echo -e "             ${DIM}→ \"$filename\" absent des 30 premières lignes${NC}"
        ((FAIL++))
    fi
}

# Parcourir récursivement depuis la racine
# Exclure node_modules, .git, vendor
while IFS= read -r -d '' file; do
    check_file "$file"
done < <(find "$ROOT" \
    -not \( -path "*/node_modules/*" -o -path "*/.git/*" -o -path "*/vendor/*" -o -path "*/.idea/*" \) \
    -type f \
    -print0 | sort -z)

# ── Résumé ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
echo -e "  ${GREEN}✓ OK      : $PASS${NC}"
echo -e "  ${RED}✗ SUSPECT : $FAIL${NC}"
echo -e "  ${YELLOW}⚠ VIDES   : $WARN${NC}"
echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
echo ""

if [ $FAIL -gt 0 ]; then
    echo -e "${RED}  ⚠ $FAIL fichier(s) potentiellement mal placés.${NC}"
    echo -e "${DIM}  Vérifiez manuellement les lignes marquées SUSPECT.${NC}"
    echo ""
    exit 1
else
    echo -e "${GREEN}  Tous les fichiers semblent correctement placés.${NC}"
    echo ""
    exit 0
fi
