@echo off
setlocal

:: =============================================================================
:: icacls-setup.bat — Permissions NTFS VoxMind / IIS
:: Lancer en tant qu'Administrateur
:: =============================================================================

set "ROOT=%~dp0"
set "APPPOOL=IIS AppPool\agent.datacorners.com"

echo.
echo ============================================================
echo   VoxMind - Configuration permissions NTFS
echo ============================================================
echo.
echo Repertoire : %ROOT%
echo AppPool    : %APPPOOL%
echo.

:: =============================================================================
:: 1. DESACTIVER L'HERITAGE SUR LE PROJET
:: =============================================================================

echo [1/8] Configuration de l'heritage...

icacls "%ROOT%" /inheritance:d /T /C

:: =============================================================================
:: 2. ADMINISTRATEURS + SYSTEM
:: =============================================================================

echo [2/8] SYSTEM + Administrateurs...

icacls "%ROOT%" /grant "SYSTEM:(OI)(CI)F" /T /C
icacls "%ROOT%" /grant "Administrateurs:(OI)(CI)F" /T /C

:: =============================================================================
:: 3. RETIRER LES ANCIENS DENY IIS_IUSRS
::
:: Important :
:: On supprime les anciens DENY avant de remettre les droits.
:: =============================================================================

echo [3/8] Nettoyage des anciens DENY IIS_IUSRS...

icacls "%ROOT%" /remove:d "IIS_IUSRS" /T /C

:: =============================================================================
:: 4. IIS_IUSRS : LECTURE / EXECUTION GENERALE
::
:: Pas de DENY.
:: Le pool applicatif recevra ensuite les droits precis necessaires.
:: =============================================================================

echo [4/8] IIS_IUSRS - lecture/execution...

icacls "%ROOT%" /grant "IIS_IUSRS:(OI)(CI)RX" /T /C

:: =============================================================================
:: 5. GIT : IIS N'A PAS BESOIN D'Y ACCEDER
::
:: On ne met PAS de DENY.
:: On retire simplement les droits IIS_IUSRS sur .git.
:: =============================================================================

echo [5/8] Protection .git...

if exist "%ROOT%\.git" (
    icacls "%ROOT%\.git" /remove "IIS_IUSRS" /T /C
)

:: =============================================================================
:: 6. DOSSIERS EN LECTURE SEULE
::
:: PHP/IIS peut lire les fichiers mais ne peut pas les modifier.
:: =============================================================================

echo [6/8] Configuration lecture seule...

if exist "%ROOT%\config" (
    icacls "%ROOT%\config" /grant "%APPPOOL%:(OI)(CI)RX" /T /C
)

if exist "%ROOT%\lib" (
    icacls "%ROOT%\lib" /grant "%APPPOOL%:(OI)(CI)RX" /T /C
)

if exist "%ROOT%\js" (
    icacls "%ROOT%\js" /grant "%APPPOOL%:(OI)(CI)RX" /T /C
)

:: =============================================================================
:: 7. DOSSIERS NECESSITANT L'ECRITURE PHP
::
:: M = Modify
:: Cela permet notamment :
:: - lire
:: - creer
:: - modifier
:: - supprimer
:: =============================================================================

echo [7/8] Dossiers PHP en ecriture...

if exist "%ROOT%\logs" (
    icacls "%ROOT%\logs" /grant "%APPPOOL%:(OI)(CI)M" /T /C
)

if exist "%ROOT%\config\rate-limits" (
    icacls "%ROOT%\config\rate-limits" /grant "%APPPOOL%:(OI)(CI)M" /T /C
)

:: =============================================================================
:: 8. FICHIERS DE CONFIGURATION SENSIBLES
::
:: Pas de DENY.
:: Le droit RX donne deja lecture/execution sans ecriture.
:: =============================================================================

echo [8/8] Fichiers sensibles...

if exist "%ROOT%\config\secrets.php" (
    icacls "%ROOT%\config\secrets.php" /grant "%APPPOOL%:R" /C
)

if exist "%ROOT%\config\users.php" (
    icacls "%ROOT%\config\users.php" /grant "%APPPOOL%:R" /C
)

if exist "%ROOT%\config\partners.php" (
    icacls "%ROOT%\config\partners.php" /grant "%APPPOOL%:R" /C
)

if exist "%ROOT%\config\rgpd.php" (
    icacls "%ROOT%\config\rgpd.php" /grant "%APPPOOL%:R" /C
)

:: =============================================================================
:: VERIFICATION
:: =============================================================================

echo.
echo ============================================================
echo   VERIFICATION
echo ============================================================
echo.

echo --- RACINE ---
icacls "%ROOT%"

echo.
echo --- CONFIG ---
if exist "%ROOT%\config" icacls "%ROOT%\config"

echo.
echo --- LIB ---
if exist "%ROOT%\lib" icacls "%ROOT%\lib"

echo.
echo --- LOGS ---
if exist "%ROOT%\logs" icacls "%ROOT%\logs"

echo.
echo --- RATE LIMITS ---
if exist "%ROOT%\config\rate-limits" (
    icacls "%ROOT%\config\rate-limits"
)

echo.
echo --- SECRETS ---
if exist "%ROOT%\config\secrets.php" (
    icacls "%ROOT%\config\secrets.php"
)

echo.
echo ============================================================
echo   Termine
echo ============================================================
echo.

pause
endlocal
