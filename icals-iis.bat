@echo off
:: =============================================================================
:: VoxMind -- Configuration NTFS (icacls)
:: A executer en Administrateur
:: =============================================================================
setlocal EnableDelayedExpansion

set /p POOL=  Nom du pool IIS (ex: lepool) : 
set /p ROOT=  Chemin racine (ex: E:\projets\AgenIa\voxmind3) : 

if "!POOL!"=="" (echo   [ERREUR] Pool requis & exit /b 1)
if "%ROOT%"=="" (echo   [ERREUR] Chemin requis & exit /b 1)

set "IDENTITY=IIS AppPool\!POOL!"

echo.
echo   -- Droits NTFS pour !IDENTITY! --
echo.

:: Creation dossiers si manquants
if not exist "%ROOT%\logs" mkdir "%ROOT%\logs"
if not exist "%ROOT%\config\rate-limits" mkdir "%ROOT%\config\rate-limits"
if not exist "%ROOT%\modules" mkdir "%ROOT%\modules"

:: -- Racine -- lecture + execution PHP
icacls "%ROOT%" /grant "!IDENTITY!:(OI)(CI)RX" /T /Q
echo   [OK] Racine : RX

:: -- config/ -- lecture PHP, pas d'ecriture
icacls "%ROOT%\config" /grant "!IDENTITY!:(OI)(CI)R" /T /Q
icacls "%ROOT%\config" /deny  "!IDENTITY!:(OI)(CI)(W,D)" /Q
echo   [OK] config/ : R (deny W,D)

:: -- config/rate-limits/ -- lecture + ecriture (compteurs JSON)
icacls "%ROOT%\config\rate-limits" /grant "!IDENTITY!:(OI)(CI)RW" /T /Q
echo   [OK] config/rate-limits/ : RW

:: -- lib/ -- lecture seule
icacls "%ROOT%\lib" /grant "!IDENTITY!:(OI)(CI)R" /T /Q
icacls "%ROOT%\lib" /deny  "!IDENTITY!:(OI)(CI)(W,D)" /Q
echo   [OK] lib/ : R (deny W,D)

:: -- modules/ -- lecture seule
icacls "%ROOT%\modules" /grant "!IDENTITY!:(OI)(CI)R" /T /Q
icacls "%ROOT%\modules" /deny  "!IDENTITY!:(OI)(CI)(W,D)" /Q
echo   [OK] modules/ : R (deny W,D)

:: -- js/ -- lecture seule
icacls "%ROOT%\js" /grant "!IDENTITY!:(OI)(CI)R" /T /Q
icacls "%ROOT%\js" /deny  "!IDENTITY!:(OI)(CI)(W,D)" /Q
echo   [OK] js/ : R (deny W,D)

:: -- logs/ -- lecture + ecriture
icacls "%ROOT%\logs" /grant "!IDENTITY!:(OI)(CI)RW" /T /Q
echo   [OK] logs/ : RW

:: -- sql/ -- aucun acces IIS
icacls "%ROOT%\sql" /deny "!IDENTITY!:(OI)(CI)(R,W,X)" /Q
echo   [OK] sql/ : aucun acces

:: -- Fichiers sensibles -- lecture seule stricte
icacls "%ROOT%\config\secrets.php" /deny "!IDENTITY!:(W,D)" /Q
icacls "%ROOT%\config\users.php" /deny "!IDENTITY!:(W,D)" /Q
icacls "%ROOT%\config\partners.php" /deny "!IDENTITY!:(W,D)" /Q
icacls "%ROOT%\config\modules.php" /deny "!IDENTITY!:(W,D)" /Q
echo   [OK] Fichiers sensibles : deny W,D

echo.
echo   -- Verification --
echo.
icacls "%ROOT%\config\secrets.php" | findstr /i "!POOL!"
icacls "%ROOT%\logs" | findstr /i "!POOL!"
icacls "%ROOT%\config\rate-limits" | findstr /i "!POOL!"

echo.
echo   [OK] Configuration NTFS terminee
echo.

endlocal
pause