// =============================================================================
// i18n.js — Internationalisation front-end FR/EN
// Basculement instantané sans rechargement
// =============================================================================
'use strict';

var i18nLang = 'fr';

var i18n = {
  fr: {
    // ── Input ─────────────────────────────────────────────────────────────────
    placeholder:       'Tapez votre message...',
    send:              'Envoyer',
    stop:              'Arrêter',

    // ── Mic ───────────────────────────────────────────────────────────────────
    micTitle:          'Microphone',
    micDenied:         '🎤 Accès au microphone refusé — autorisez-le dans les réglages navigateur.',
    micExpired:        '🔒 Session expirée — Reconnectez-vous pour utiliser le microphone.',
    micUnavailable:    '🎤 Service de transcription inaccessible.',

    // ── Upload ────────────────────────────────────────────────────────────────
    uploadTitle:       'Joindre un fichier',
    uploadTooBig:      'Fichier trop volumineux (max {0} Mo)',
    uploadBadType:     'Type non supporté — image, PDF, TXT, DOCX uniquement',
    uploadDisabled:    'Upload désactivé',
    uploadDocxFail:    'Librairie DOCX non disponible',
    uploadPdfFail:     'Librairie PDF non disponible',
    uploadRemove:      'Retirer',

    // ── Vision ────────────────────────────────────────────────────────────────
    camFlip:           'Basculer caméra',
    camStop:           'ARRÊTER',
    camFront:          'Caméra frontale',
    camBack:           'Caméra arrière',

    // ── TTS ───────────────────────────────────────────────────────────────────
    ttsPlay:           'LIRE',
    ttsPause:          'Pause',
    ttsResume:         'Reprendre',
    ttsCopy:           'COPIER',
    ttsCopied:         'COPIÉ',

    // ── Admin ─────────────────────────────────────────────────────────────────
    adminTitle:        'ADMINISTRATION',
    settingsTitle:     'RÉGLAGES',
    naturalConv:       'Conversation naturelle',
    naturalConvWarn:   'Latence possible selon le modèle',
    instruct:          'Instruct (ton expressif)',
    replay:            'Relecture audio',
    testTts:           'TEST TTS',

    // ── User menu ─────────────────────────────────────────────────────────────
    myAccount:         'Mon compte',
    privacy:           'Confidentialité',
    logout:            'Déconnexion',

    // ── Errors ────────────────────────────────────────────────────────────────
    error:             'Erreur',

    // ── Consent ───────────────────────────────────────────────────────────────
    consentAccept:     'TOUT ACCEPTER',
    consentPartial:    'PARTIEL',
    consentRefuse:     'REFUSER',

    // ── Status ────────────────────────────────────────────────────────────────
    connecting:        'Connexion...',
    ready:             'Prêt',
    thinking:          'Réflexion...',

    // ── Guest / Trial ─────────────────────────────────────────────────────────
    trialBtn:          '⚡ Essai libre — 20 min',
    trialSub:          'Sans inscription · Accès complet · 10 messages',
    guestSession:      'Session',
    guestMessages:     'Messages',
    guestSignup:       '→ Créer un compte',
    sessionExpired:    'Session expirée',
    sessionExpiredMsg: 'Votre session de démonstration de 20 minutes est terminée. Créez un compte pour continuer.',
    sessionExpiredBack:'→ Retour à l\'accueil',

    // ── Modules panel ─────────────────────────────────────────────────────────
    modulesBtn:        '⊞ MODULES',
    modulesTooltip:    'Gérer les modules',
    modulesTitle:      'MODULES',
    modulesAdminNote:  'Modifications en temps réel · BDD',
    planGuest:         'Accès libre',
    planFree:          'Compte gratuit',
    planPro:           'Pro',
    planEnterprise:    'Entreprise',
    moduleGuestMax:    'Max {0} utilisations',

    // ── Logout / Pro ──────────────────────────────────────────────────────────
    logoutBtn:         '⏻',
    logoutTooltip:     'Déconnexion',
    proBtn:            '⭐ PRO',
    proBtnTooltip:     'Passer au plan Pro — 15€/mois',
    proMonthly:        'Mensuel — 15€/mois',
    proYearly:         'Annuel — 150€/an (-15%)',

    // ── Providers ─────────────────────────────────────────────────────────────
    providerLlm:       'Provider LLM',
    providerTts:       'Voix',
    providerStt:       'Transcription',
    apiKey:            'Clé API',

    // ── Super Admin — Navigation ───────────────────────────────────────────────
    saNavInterface:    'Interface',
    saDashboard:       'Dashboard',
    saClients:         'Clients',
    saUpgrades:        'Upgrades',
    saBilling:         'Facturation',
    saReport:          'Rapport',
    saEndpoints:       'Endpoints',
    saCapacites:       'Capacités Agent',
    saGroupes:         'Groupes Admin',

    // ── Super Admin — Dashboard ───────────────────────────────────────────────
    saApiDown:         'API inaccessible',
    saQuickStats:      'Statistiques rapides',
    saActive:          'CLIENTS ACTIFS',
    saPending:         'EN ATTENTE',
    saRefresh:         'Actualiser',
    saLoad:            'Charger',
    saLoading:         'Chargement…',
    saClickLoad:       'Cliquer sur Charger…',
    saLastEntries:     'Dernières entrées',

    // ── Super Admin — Clients ─────────────────────────────────────────────────
    saSearch:          'Recherche',
    saEmailOrId:       'Email ou ID',
    saNotFound:        'Introuvable',
    saUpdatePlan:      'Modifier le plan',
    saNewPlan:         'Nouveau plan',
    saApply:           'Appliquer',
    saPlanUpdated:     'Plan mis à jour',
    saPlan:            'Plan',
    saUserIdRequired:  'User ID requis',

    // ── Super Admin — White Label ─────────────────────────────────────────────
    saEnableWL:        'Activer white label',
    saAdminNote:       'Note admin',
    saEnable:          'Activer',
    saDisable:         'Désactiver',
    saEnabled:         'activé',
    saDisabled:        'désactivé',

    // ── Super Admin — Upgrades ────────────────────────────────────────────────
    saRequestedPlan:   'Plan demandé',
    saNoPending:       'Aucune demande en attente',
    saUpgradeValidated:'Upgrade validé',

    // ── Super Admin — Billing ─────────────────────────────────────────────────
    saTokenBalance:    'Solde tokens',
    saCheck:           'Vérifier',
    saUsed:            'Utilisés',
    saLimit:           'Limite',
    saCredits:         'Crédits',

    // ── Super Admin — Rapport ─────────────────────────────────────────────────
    saGenerate:        'Générer',
    saPeriod:          'Période (YYYY-MM)',
    saPeriodRequired:  'Période requise',

    // ── Super Admin — Capacités ───────────────────────────────────────────────
    saAccessByRole:    'Accès par rôle',
    saCapacitesNote:   'Cochez les rôles autorisés. La hiérarchie s\'applique automatiquement.',
    saSave:            'Enregistrer',
    saCapacity:        'Capacité',
    saActive:          'Actif',
    saNoCapacites:     'Aucune capacité en base.',
    saCapacitesSaved:  'Capacités enregistrées',
    saPartialError:    'Erreurs partielles',

    // ── Super Admin — Groupes ─────────────────────────────────────────────────
    saAssignMember:    'Associer un membre',
    saAdminEmail:      'Email admin',
    saMemberEmail:     'Email membre (pro)',
    saAdd:             'Ajouter',
    saRemove:          'Retirer',
    saMembersByAdmin:  'Membres par admin',
    saFilterByAdmin:   'Filtrer par admin',
    saEnterAdminEmail: 'Saisissez un email admin.',
    saMemberAdded:     'Membre ajouté',
    saMemberRemoved:   'Membre retiré',
    saEmailsRequired:  'Emails requis.',
    saNoMembers:       'Aucun membre.',
    saMember:          'Membre',
    saSince:           'Depuis',
  },

  en: {
    // ── Input ─────────────────────────────────────────────────────────────────
    placeholder:       'Type your message...',
    send:              'Send',
    stop:              'Stop',

    // ── Mic ───────────────────────────────────────────────────────────────────
    micTitle:          'Microphone',
    micDenied:         '🎤 Microphone access denied — enable it in browser settings.',
    micExpired:        '🔒 Session expired — Please reconnect to use the microphone.',
    micUnavailable:    '🎤 Transcription service unavailable.',

    // ── Upload ────────────────────────────────────────────────────────────────
    uploadTitle:       'Attach a file',
    uploadTooBig:      'File too large (max {0} MB)',
    uploadBadType:     'Unsupported type — image, PDF, TXT, DOCX only',
    uploadDisabled:    'Upload disabled',
    uploadDocxFail:    'DOCX library not available',
    uploadPdfFail:     'PDF library not available',
    uploadRemove:      'Remove',

    // ── Vision ────────────────────────────────────────────────────────────────
    camFlip:           'Switch camera',
    camStop:           'STOP',
    camFront:          'Front camera',
    camBack:           'Back camera',

    // ── TTS ───────────────────────────────────────────────────────────────────
    ttsPlay:           'PLAY',
    ttsPause:          'Pause',
    ttsResume:         'Resume',
    ttsCopy:           'COPY',
    ttsCopied:         'COPIED',

    // ── Admin ─────────────────────────────────────────────────────────────────
    adminTitle:        'ADMINISTRATION',
    settingsTitle:     'SETTINGS',
    naturalConv:       'Natural conversation',
    naturalConvWarn:   'Latency may occur depending on model',
    instruct:          'Instruct (expressive tone)',
    replay:            'Audio replay',
    testTts:           'TEST TTS',

    // ── User menu ─────────────────────────────────────────────────────────────
    myAccount:         'My account',
    privacy:           'Privacy',
    logout:            'Log out',

    // ── Errors ────────────────────────────────────────────────────────────────
    error:             'Error',

    // ── Consent ───────────────────────────────────────────────────────────────
    consentAccept:     'ACCEPT ALL',
    consentPartial:    'PARTIAL',
    consentRefuse:     'DECLINE',

    // ── Status ────────────────────────────────────────────────────────────────
    connecting:        'Connecting...',
    ready:             'Ready',
    thinking:          'Thinking...',

    // ── Guest / Trial ─────────────────────────────────────────────────────────
    trialBtn:          '⚡ Free trial — 20 min',
    trialSub:          'No sign-up · Full access · 10 messages',
    guestSession:      'Session',
    guestMessages:     'Messages',
    guestSignup:       '→ Sign up',
    sessionExpired:    'Session expired',
    sessionExpiredMsg: 'Your 20-minute demo session has ended. Create an account to continue.',
    sessionExpiredBack:'→ Back to login',

    // ── Modules panel ─────────────────────────────────────────────────────────
    modulesBtn:        '⊞ MODULES',
    modulesTooltip:    'Manage modules',
    modulesTitle:      'MODULES',
    modulesAdminNote:  'Real-time changes · DB',
    planGuest:         'Free access',
    planFree:          'Free account',
    planPro:           'Pro',
    planEnterprise:    'Enterprise',
    moduleGuestMax:    'Max {0} uses',

    // ── Logout / Pro ──────────────────────────────────────────────────────────
    logoutBtn:         '⏻',
    logoutTooltip:     'Sign out',
    proBtn:            '⭐ PRO',
    proBtnTooltip:     'Upgrade to Pro — €15/month',
    proMonthly:        'Monthly — €15/month',
    proYearly:         'Yearly — €150/year (-15%)',

    // ── Providers ─────────────────────────────────────────────────────────────
    providerLlm:       'LLM Provider',
    providerTts:       'Voice',
    providerStt:       'Transcription',
    apiKey:            'API Key',

    // ── Super Admin — Navigation ───────────────────────────────────────────────
    saNavInterface:    'Interface',
    saDashboard:       'Dashboard',
    saClients:         'Clients',
    saUpgrades:        'Upgrades',
    saBilling:         'Billing',
    saReport:          'Report',
    saEndpoints:       'Endpoints',
    saCapacites:       'Agent Capacities',
    saGroupes:         'Admin Groups',

    // ── Super Admin — Dashboard ───────────────────────────────────────────────
    saApiDown:         'API unavailable',
    saQuickStats:      'Quick stats',
    saActive:          'ACTIVE CLIENTS',
    saPending:         'PENDING',
    saRefresh:         'Refresh',
    saLoad:            'Load',
    saLoading:         'Loading…',
    saClickLoad:       'Click Load…',
    saLastEntries:     'Latest entries',

    // ── Super Admin — Clients ─────────────────────────────────────────────────
    saSearch:          'Search',
    saEmailOrId:       'Email or ID',
    saNotFound:        'Not found',
    saUpdatePlan:      'Update plan',
    saNewPlan:         'New plan',
    saApply:           'Apply',
    saPlanUpdated:     'Plan updated',
    saPlan:            'Plan',
    saUserIdRequired:  'User ID required',

    // ── Super Admin — White Label ─────────────────────────────────────────────
    saEnableWL:        'Enable white label',
    saAdminNote:       'Admin note',
    saEnable:          'Enable',
    saDisable:         'Disable',
    saEnabled:         'enabled',
    saDisabled:        'disabled',

    // ── Super Admin — Upgrades ────────────────────────────────────────────────
    saRequestedPlan:   'Requested plan',
    saNoPending:       'No pending requests',
    saUpgradeValidated:'Upgrade validated',

    // ── Super Admin — Billing ─────────────────────────────────────────────────
    saTokenBalance:    'Token balance',
    saCheck:           'Check',
    saUsed:            'Used',
    saLimit:           'Limit',
    saCredits:         'Credits',

    // ── Super Admin — Rapport ─────────────────────────────────────────────────
    saGenerate:        'Generate',
    saPeriod:          'Period (YYYY-MM)',
    saPeriodRequired:  'Period required',

    // ── Super Admin — Capacités ───────────────────────────────────────────────
    saAccessByRole:    'Access by role',
    saCapacitesNote:   'Check the allowed roles. Hierarchy applies automatically.',
    saSave:            'Save',
    saCapacity:        'Capacity',
    saActive:          'Active',
    saNoCapacites:     'No capacities in database.',
    saCapacitesSaved:  'Capacities saved',
    saPartialError:    'Partial errors',

    // ── Super Admin — Groupes ─────────────────────────────────────────────────
    saAssignMember:    'Assign a member',
    saAdminEmail:      'Admin email',
    saMemberEmail:     'Member email (pro)',
    saAdd:             'Add',
    saRemove:          'Remove',
    saMembersByAdmin:  'Members by admin',
    saFilterByAdmin:   'Filter by admin',
    saEnterAdminEmail: 'Enter an admin email.',
    saMemberAdded:     'Member added',
    saMemberRemoved:   'Member removed',
    saEmailsRequired:  'Emails required.',
    saNoMembers:       'No members.',
    saMember:          'Member',
    saSince:           'Since',
  }
};

// =============================================================================
// FONCTIONS PUBLIQUES
// =============================================================================

/** Obtenir un texte traduit avec interpolation {0}, {1}… */
function t(key) {
  var text = (i18n[i18nLang] && i18n[i18nLang][key])
          || (i18n['fr']     && i18n['fr'][key])
          || key;
  for (var i = 1; i < arguments.length; i++) {
    text = text.replace('{' + (i - 1) + '}', arguments[i]);
  }
  return text;
}

/** Alias pour superadmin.js (même signature que t()) */
window.i18nGet = t;

/** Basculer la langue et mettre à jour tous les [data-i18n] */
function setLang(lang) {
  if (!i18n[lang]) { return; }
  i18nLang = lang;
  localStorage.setItem('vox-lang', lang);
  i18nApply();
  var btn = typeof g === 'function' ? g('lang-btn') : document.getElementById('lang-btn');
  if (btn) { btn.textContent = lang.toUpperCase(); }
}

/** Appliquer les traductions sur tous les [data-i18n] du DOM */
function i18nApply() {
  var els = document.querySelectorAll('[data-i18n]');
  for (var i = 0; i < els.length; i++) {
    var key  = els[i].getAttribute('data-i18n');
    var attr = els[i].getAttribute('data-i18n-attr');
    if (attr) {
      els[i].setAttribute(attr, t(key));
    } else {
      els[i].textContent = t(key);
    }
  }
}

/** Initialiser depuis localStorage ou config serveur */
function initI18n() {
  var saved = localStorage.getItem('vox-lang');
  if (saved && i18n[saved]) {
    i18nLang = saved;
  } else if (typeof voxLang !== 'undefined' && i18n[voxLang]) {
    i18nLang = voxLang;
  }
  setLang(i18nLang);
}