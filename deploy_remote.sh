#!/bin/bash
#=====================================================
# BiLLU v4.0 - Pobierz z GitHub i zaktualizuj
#
# Użycie na serwerze:
#   1. Wgraj ten plik na serwer (SCP/FTP)
#   2. chmod +x deploy_remote.sh
#   3. sudo ./deploy_remote.sh
#
# Lub jednolinijkowo (wklej na serwer):
#   sudo bash -c 'BRANCH="claude/invoice-verification-system-E8wzz"; T=/tmp/fp_$$; git clone --depth 1 -b "$BRANCH" https://github.com/PrzemekPrzemo/Faktury.git "$T" && bash "$T/deploy.sh" "$@" && rm -rf "$T"' _ "$@"
#
#=====================================================

set -e

BRANCH="claude/invoice-verification-system-E8wzz"
REPO="https://github.com/PrzemekPrzemo/Faktury.git"
TEMP="/tmp/faktury_deploy_$$"

echo "Pobieranie BiLLU v4.0..."

# Klonuj repo (z retry)
RETRIES=0
while [[ $RETRIES -lt 4 ]]; do
    if git clone --depth 1 -b "$BRANCH" "$REPO" "$TEMP" 2>&1; then
        break
    fi
    RETRIES=$((RETRIES + 1))
    if [[ $RETRIES -lt 4 ]]; then
        echo "Retry ${RETRIES}/3..."
        sleep $((2 ** RETRIES))
    else
        echo "BŁĄD: Nie udało się pobrać repozytorium!"
        echo ""
        echo "Jeśli repo jest prywatne, najpierw skonfiguruj dostęp:"
        echo "  git config --global credential.helper store"
        echo "  git clone $REPO /tmp/test  (podaj login/token)"
        echo "  rm -rf /tmp/test"
        echo "  # Teraz uruchom ponownie deploy_remote.sh"
        exit 1
    fi
done

# Uruchom główny deploy.sh
echo ""
bash "$TEMP/deploy.sh" "$@"

# Sprzątanie
rm -rf "$TEMP"
