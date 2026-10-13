# =============================================================================
# Agenia — protections IIS (équivalent du .htaccess Apache)
# Usage, en console administrateur : renseigner $site, puis coller les lignes
# (ou exécuter ce fichier si la stratégie d'exécution l'autorise).
# Les règles déjà présentes provoquent une erreur « doublon » sans conséquence.
# =============================================================================
$site = 'NOM_DU_SITE_IIS'
$ac   = "$env:windir\system32\inetsrv\appcmd.exe"

# Dossiers lus uniquement par PHP : jamais servis par le web.
foreach ($s in 'business','config','data','logs','lib','react','docs','deploy') {
  & $ac set config "$site" -section:system.webServer/security/requestFiltering /+"hiddenSegments.[segment='$s']"
}

# Fichiers de configuration .NET.
foreach ($s in 'appsettings.json','appsettings.Production.json','appsettings.Development.json') {
  & $ac set config "$site" -section:system.webServer/security/requestFiltering /+"hiddenSegments.[segment='$s']"
}

# Binaires et symboles .NET.
foreach ($e in '.dll','.pdb') {
  & $ac set config "$site" -section:system.webServer/security/requestFiltering /+"fileExtensions.[fileExtension='$e',allowed='false']"
}

# Contrôle : chaque adresse sensible doit répondre 404.
$hote = 'agent.datacorners.com' ; $port = 8081
foreach ($p in '/config/settings.json','/data/','/logs/','/lib/debug.php','/react/package.json','/business/business-api/appsettings.json','/login.php') {
  $c = curl.exe -s -o NUL -w "%{http_code}" -H "Host: $hote" ("http://127.0.0.1:$port" + $p) ; "$c  $p"
}
