import type { Locale } from './config';

// Libellés de la coque applicative (barre d'environnement + chat).
// Séparés des messages de la page publique ; mêmes langues.

export interface EnvStrings {
  roleLabel: string;
  roleNames: Record<string, string>;
  roleTip: Record<string, string>;
  dataKey: string;
  local: string;
  cloud: string;
  dataTipLocal: string;
  dataTipCloud: string;
  leftKey: string;
  leftTip: string;
  msgShort: string;
  agentKey: string;
  agentOn: string;
  agentOff: string;
  agentTipOn: string;
  agentTipOff: string;
  logout: string;
  login: string;
  needLogin: string;
  envError: string;
  retry: string;
  empty: string;
  placeholder: string;
  send: string;
  loading: string;
  thinking: string;
  auditToggle: string;
  auditModel: string;
  auditDuration: string;
  auditLogged: string;
  auditLocalYes: string;
  auditLocalNo: string;
  auditFirstWord: string;
  auditVolume: string;
  auditTokens: string;
  auditNotLogged: string;
  auditTraceTip: string;
  agentTitle: string;
  agentWants: string;
  agentAllow: string;
  agentDeny: string;
  agentRunning: string;
  agentDone: string;
  agentRefused: string;
  errNetwork: string;
  errServer: string;
  copyMsg: string;
  copied: string;
  reread: string;
  stopRead: string;
  codeCopy: string;
  codeCopied: string;
  attachAdd: string;
  camTake: string;
  camBack: string;
  camFront: string;
  webcam: { title: string; shoot: string; switchCam: string; cancel: string; denied: string; notFound: string; failed: string };
  attachRemove: string;
  attachAlt: string;
  attachType: string;
  attachSize: string; // {mb} remplacé par la limite de settings.json
  attachPdf: string;
  attachEmpty: string;
  attachReadErr: string;
  attachTruncated: string;
  voiceAutoOn: string;
  voiceAutoOff: string;
  voiceUnavailable: string;
  upgradePro: string;
  upgradeProTip: string;
  createAccount: string;
  createAccountTip: string;
  superAdmin: string;
  auditJournal: string;
  demoBadge: string;
  demoBadgeTip: string;
  proTag: string;
  proLockGuest: string;
  proLockFree: string;
  langSwitch: string;
  themeToLight: string;
  themeToDark: string;
  micStart: string;
  micStop: string;
  micListening: string;
  micRecording: string;
  micTranscribing: string;
  micDenied: string;
  micUnsupported: string;
  micExpired: string;
  micUnavailable: string;
  micNetwork: string;
  pauseRead: string;
  resumeRead: string;
  stopAll: string;
  settings: {
    title: string;
    open: string;
    close: string;
    ia: string;
    tts: string;
    stt: string;
    provider: string;
    model: string;
    url: string;
    key: string;
    keyKept: string;
    voice: string;
    format: string;
    save: string;
    saving: string;
    saved: string;
    saveError: string;
    test: string;
    testing: string;
    searchModel: string;
    searching: string;
    noModels: string;
    iaTab: string;
    proIaTitle: string;
    proIaGuest: string;
    proIaFree: string;
    proProviders: string;
    proCtaRegister: string;
    proCtaPro: string;
    ttsTab: string;
    sttTab: string;
    testTab: string;
    visionModel: string;
    systemPrompt: string;
    modelDefault: string;
    visionDefault: string;
    loadErr: string;
    readingOptions: string;
    optAutoVoice: string;
    optStreamVoice: string;
    voiceUi: string;
    voiceNames: Record<'fr' | 'en', string>;
    voiceSamples: Record<'fr' | 'en', string>;
    voiceTech: string;
    voiceTest: string;
    voiceTesting: string;
    voiceStop: string;
    voiceFail: string;
    voiceErrEmpty: string;
    voiceErrTimeout: string; // {s} = délai de settings.json
    voiceErrNetwork: string;
  };
}

const fr: EnvStrings = {
  roleLabel: 'Vous êtes',
  roleNames: { guest: 'Invité', demo: 'Démo', user: 'Utilisateur', admin: 'Administrateur', superadmin: 'Super-admin', partner: 'Partenaire' },
  roleTip: {
    guest: "Accès d'essai. Vous pouvez discuter et analyser des documents ; les actions qui modifient le système sont réservées aux comptes autorisés.",
    admin: "Compte administrateur. Vous pilotez les modules et pouvez autoriser les actions de l'agent.",
    _default: "Votre compte et ce qu'il vous permet de faire dans Agenia.",
  },
  dataKey: 'Vos données',
  local: 'Local',
  cloud: 'Cloud',
  dataTipLocal: 'Modèle hébergé sur vos serveurs. Vos messages ne sortent pas de votre infrastructure.',
  dataTipCloud: "Modèle hébergé chez un fournisseur externe. Vos messages lui sont transmis pour obtenir la réponse.",
  leftKey: 'Il vous reste',
  leftTip: "Temps et messages restants pour votre session d'essai. Créez un compte pour un accès complet.",
  msgShort: 'msg',
  agentKey: "L'agent peut",
  agentOn: 'Agir sur accord',
  agentOff: 'Conversation seule',
  agentTipOn: "L'agent peut lire et analyser librement, et proposer des actions qu'une personne doit autoriser.",
  agentTipOff: "Mode conversation. L'agent répond mais n'exécute aucune action sur le système.",
  logout: 'Déconnexion',
  login: 'Se connecter',
  needLogin: 'Connectez-vous pour ouvrir votre assistant.',
  envError: "Impossible de charger votre environnement pour l'instant.",
  retry: 'Réessayer',
  empty: 'Posez votre première question.',
  placeholder: 'Écrivez votre message…',
  send: 'Envoyer',
  loading: 'Chargement…',
  thinking: 'Je réfléchis…',
  auditToggle: 'Comment j\'ai obtenu cette réponse',
  auditModel: 'Modèle',
  auditDuration: 'Durée',
  auditLogged: 'Tracé',
  auditLocalYes: 'rien n\'est sorti de vos serveurs',
  auditLocalNo: 'via un fournisseur externe',
  auditFirstWord: 'premier mot à',
  auditVolume: 'Volume',
  auditTokens: 'tokens',
  auditNotLogged: 'non tracé',
  auditTraceTip: "Numéro de trace : retrouvez cet échange dans le journal d'audit",
  agentTitle: 'L\'agent demande votre accord',
  agentWants: 'Il veut effectuer cette action :',
  agentAllow: 'Autoriser',
  agentDeny: 'Refuser',
  agentRunning: 'Action en cours…',
  agentDone: 'Action effectuée.',
  agentRefused: 'Action refusée.',
  errNetwork: 'Impossible de joindre le serveur.',
  errServer: 'Le service ne répond pas correctement.',
  copyMsg: 'Copier',
  copied: 'Copié',
  reread: 'Écouter',
  stopRead: 'Arrêter',
  codeCopy: 'Copier le code',
  codeCopied: 'Copié',
  attachAdd: 'Joindre un fichier (image, Word, texte)',
  camTake: 'Prendre une photo',
  camBack: 'Caméra arrière',
  camFront: 'Caméra avant',
  webcam: {
    title: 'Prendre une photo',
    shoot: 'Prendre la photo',
    switchCam: 'Changer de caméra',
    cancel: 'Annuler',
    denied: "Accès à la caméra refusé. Autorisez-la dans la barre d'adresse du navigateur, puis réessayez.",
    notFound: 'Aucune caméra détectée sur cet appareil.',
    failed: "Impossible d'utiliser la caméra sur ce navigateur.",
  },
  attachRemove: 'Retirer',
  attachAlt: 'Image jointe',
  attachType: "Ce type de fichier n'est pas accepté.",
  attachSize: 'Fichier trop lourd ({mb} Mo maximum).',
  attachPdf: "La lecture des PDF n'est pas encore disponible ici.",
  attachEmpty: 'Ce document ne contient pas de texte lisible.',
  attachReadErr: 'Lecture du fichier impossible.',
  attachTruncated: 'tronqué',
  voiceAutoOn: 'Lecture vocale des réponses : activée',
  voiceAutoOff: 'Lecture vocale des réponses : désactivée',
  voiceUnavailable: "Pas de son : le service de voix n'a pas répondu.",
  upgradePro: 'Passer PRO',
  upgradeProTip: "Passer à l'offre Pro",
  createAccount: 'Créer un compte',
  createAccountTip: "Créez votre compte pour passer à l'offre Pro",
  superAdmin: 'Super admin',
  auditJournal: "Journal d'audit",
  proTag: 'PRO',
  proLockGuest: "Fonction PRO : le journal d'audit trace chaque réponse de l'IA et chaque action de l'agent. Créez un compte pour y accéder.",
  proLockFree: "Fonction PRO : le journal d'audit trace chaque réponse de l'IA et chaque action de l'agent. Passez à PRO pour y accéder.",
  demoBadge: 'Démonstration',
  demoBadgeTip: "Mode vitrine : toutes les fonctionnalités sont visibles. Les actions de l'agent sont montrées puis simulées — rien n'est exécuté sur un vrai système.",
  langSwitch: 'Switch to English',
  themeToLight: 'Passer en thème clair',
  themeToDark: 'Passer en thème sombre',
  micStart: 'Parler',
  micStop: 'Arrêter le micro',
  micListening: 'Je vous écoute…',
  micRecording: 'Enregistrement…',
  micTranscribing: 'Transcription…',
  micDenied: "Accès au micro refusé : autorisez-le dans votre navigateur.",
  micUnsupported: "Le micro n'est pas disponible sur ce navigateur.",
  micExpired: 'Session expirée : reconnectez-vous pour utiliser le micro.',
  micUnavailable: 'La transcription est indisponible pour le moment.',
  micNetwork: 'Impossible de joindre le service de transcription.',
  pauseRead: 'Pause',
  resumeRead: 'Reprendre',
  stopAll: 'Arrêter',
  settings: {
    title: 'Réglages',
    open: 'Ouvrir les réglages',
    close: 'Replier',
    ia: 'Intelligence artificielle',
    tts: 'Voix (synthèse)',
    stt: 'Transcription',
    provider: 'Fournisseur',
    model: 'Modèle',
    url: 'URL',
    key: "Clé d'accès",
    keyKept: 'Laisser vide pour conserver la clé actuelle',
    voice: 'Voix',
    format: 'Format',
    save: 'Enregistrer',
    saving: 'Enregistrement…',
    saved: 'Enregistré.',
    saveError: "Échec de l'enregistrement.",
    test: 'Tester les connexions',
    testing: 'Test en cours…',
    searchModel: 'Rechercher un modèle…',
    searching: 'Recherche des modèles…',
    noModels: 'Aucun modèle trouvé.',
    iaTab: 'IA',
    proIaTitle: "Choix de l'IA — offre PRO",
    proIaGuest: "Avec un compte, choisissez le moteur d'IA qui répond à votre équipe : modèles cloud du marché ou IA locale, clés et modèles réglables.",
    proIaFree: "Avec l'offre PRO, choisissez le moteur d'IA qui répond à votre équipe : modèles cloud du marché ou IA locale, clés et modèles réglables.",
    proProviders: 'Fournisseurs disponibles',
    proCtaRegister: 'Créer un compte',
    proCtaPro: 'Passer à PRO',
    ttsTab: 'Voix',
    sttTab: 'Dictée',
    testTab: 'Test',
    visionModel: 'Modèle vision',
    systemPrompt: 'Prompt système',
    modelDefault: 'Par défaut : modèle de settings.json',
    visionDefault: 'Par défaut : le modèle ci-dessus',
    loadErr: 'Impossible de charger les réglages :',
    readingOptions: 'Lecture',
    optAutoVoice: 'Lire les réponses à voix haute',
    optStreamVoice: "Commencer la lecture pendant l'écriture",
    voiceUi: "Voix de l'assistant",
    voiceNames: { fr: 'Célia — Français', en: 'Alex — Anglais' },
    voiceSamples: { fr: 'Bonjour, ceci est un test de la voix.', en: 'Hello, this is a voice test.' },
    voiceTech: 'Réglages techniques',
    voiceTest: 'Tester la voix',
    voiceTesting: 'Lecture…',
    voiceStop: 'Arrêter',
    voiceFail: 'Pas de son',
    voiceErrEmpty: 'le serveur a renvoyé une réponse vide',
    voiceErrTimeout: 'pas de réponse en {s} s (ttsTimeoutMs)',
    voiceErrNetwork: 'serveur de voix injoignable',
  },
};

const en: EnvStrings = {
  roleLabel: 'You are',
  roleNames: { guest: 'Guest', demo: 'Demo', user: 'User', admin: 'Administrator', superadmin: 'Super-admin', partner: 'Partner' },
  roleTip: {
    guest: 'Trial access. You can chat and analyse documents; actions that change the system are reserved for authorised accounts.',
    admin: "Administrator account. You manage modules and can approve the agent's actions.",
    _default: 'Your account and what it lets you do in Agenia.',
  },
  dataKey: 'Your data',
  local: 'Local',
  cloud: 'Cloud',
  dataTipLocal: 'Model hosted on your servers. Your messages never leave your infrastructure.',
  dataTipCloud: 'Model hosted by an outside provider. Your messages are sent to it to get the answer.',
  leftKey: 'You have left',
  leftTip: 'Time and messages left in your trial session. Create an account for full access.',
  msgShort: 'msg',
  agentKey: 'The agent can',
  agentOn: 'Act on approval',
  agentOff: 'Chat only',
  agentTipOn: 'The agent can read and analyse freely, and propose actions a person must approve.',
  agentTipOff: 'Chat mode. The agent answers but runs no action on the system.',
  logout: 'Sign out',
  login: 'Sign in',
  needLogin: 'Sign in to open your assistant.',
  envError: "Can't load your environment right now.",
  retry: 'Try again',
  empty: 'Ask your first question.',
  placeholder: 'Type your message…',
  send: 'Send',
  loading: 'Loading…',
  thinking: 'Thinking…',
  auditToggle: 'How I got this answer',
  auditModel: 'Model',
  auditDuration: 'Time',
  auditLogged: 'Logged',
  auditLocalYes: 'nothing left your servers',
  auditLocalNo: 'via an outside provider',
  auditFirstWord: 'first word at',
  auditVolume: 'Volume',
  auditTokens: 'tokens',
  auditNotLogged: 'not logged',
  auditTraceTip: 'Trace number: find this exchange in the audit log',
  agentTitle: 'The agent asks for your approval',
  agentWants: 'It wants to perform this action:',
  agentAllow: 'Allow',
  agentDeny: 'Deny',
  agentRunning: 'Running…',
  agentDone: 'Action completed.',
  agentRefused: 'Action declined.',
  errNetwork: 'Could not reach the server.',
  errServer: 'The service is not responding correctly.',
  copyMsg: 'Copy',
  copied: 'Copied',
  reread: 'Listen',
  stopRead: 'Stop',
  codeCopy: 'Copy code',
  codeCopied: 'Copied',
  attachAdd: 'Attach a file (image, Word, text)',
  camTake: 'Take a photo',
  camBack: 'Back camera',
  camFront: 'Front camera',
  webcam: {
    title: 'Take a photo',
    shoot: 'Take photo',
    switchCam: 'Switch camera',
    cancel: 'Cancel',
    denied: "Camera access denied. Allow it from the browser's address bar, then try again.",
    notFound: 'No camera detected on this device.',
    failed: 'The camera cannot be used in this browser.',
  },
  attachRemove: 'Remove',
  attachAlt: 'Attached image',
  attachType: 'This file type is not accepted.',
  attachSize: 'File too large ({mb} MB maximum).',
  attachPdf: 'PDF reading is not available here yet.',
  attachEmpty: 'This document has no readable text.',
  attachReadErr: 'Could not read the file.',
  attachTruncated: 'truncated',
  voiceAutoOn: 'Spoken replies: on',
  voiceAutoOff: 'Spoken replies: off',
  voiceUnavailable: 'No sound: the voice service did not respond.',
  upgradePro: 'Go PRO',
  upgradeProTip: 'Upgrade to the Pro plan',
  createAccount: 'Create account',
  createAccountTip: 'Create your account to upgrade to the Pro plan',
  superAdmin: 'Super admin',
  auditJournal: 'Audit log',
  proTag: 'PRO',
  proLockGuest: 'PRO feature: the audit log records every AI answer and every agent action. Create an account to access it.',
  proLockFree: 'PRO feature: the audit log records every AI answer and every agent action. Upgrade to PRO to access it.',
  demoBadge: 'Demo',
  demoBadgeTip: 'Showcase mode: every feature is visible. Agent actions are shown then simulated — nothing is run on a real system.',
  langSwitch: 'Passer en français',
  themeToLight: 'Switch to light theme',
  themeToDark: 'Switch to dark theme',
  micStart: 'Speak',
  micStop: 'Stop the microphone',
  micListening: 'Listening…',
  micRecording: 'Recording…',
  micTranscribing: 'Transcribing…',
  micDenied: 'Microphone access denied: allow it in your browser.',
  micUnsupported: 'The microphone is not available in this browser.',
  micExpired: 'Session expired: sign in again to use the microphone.',
  micUnavailable: 'Transcription is unavailable right now.',
  micNetwork: 'Could not reach the transcription service.',
  pauseRead: 'Pause',
  resumeRead: 'Resume',
  stopAll: 'Stop',
  settings: {
    title: 'Settings',
    open: 'Open settings',
    close: 'Collapse',
    ia: 'AI model',
    tts: 'Voice (speech)',
    stt: 'Transcription',
    provider: 'Provider',
    model: 'Model',
    url: 'URL',
    key: 'Access key',
    keyKept: 'Leave empty to keep the current key',
    voice: 'Voice',
    format: 'Format',
    save: 'Save',
    saving: 'Saving…',
    saved: 'Saved.',
    saveError: 'Could not save.',
    test: 'Test connections',
    testing: 'Testing…',
    searchModel: 'Search a model…',
    searching: 'Loading models…',
    noModels: 'No model found.',
    iaTab: 'AI',
    proIaTitle: 'AI choice — PRO plan',
    proIaGuest: 'With an account, choose the AI engine that answers your team: leading cloud models or local AI, with adjustable keys and models.',
    proIaFree: 'With the PRO plan, choose the AI engine that answers your team: leading cloud models or local AI, with adjustable keys and models.',
    proProviders: 'Available providers',
    proCtaRegister: 'Create an account',
    proCtaPro: 'Upgrade to PRO',
    ttsTab: 'Voice',
    sttTab: 'Speech',
    testTab: 'Test',
    visionModel: 'Vision model',
    systemPrompt: 'System prompt',
    modelDefault: 'Default: model from settings.json',
    visionDefault: 'Default: the model above',
    loadErr: 'Could not load the settings:',
    readingOptions: 'Reading',
    optAutoVoice: 'Read replies aloud',
    optStreamVoice: 'Start reading while the reply is being written',
    voiceUi: 'Assistant voice',
    voiceNames: { fr: 'Célia — French', en: 'Alex — English' },
    voiceSamples: { fr: 'Bonjour, ceci est un test de la voix.', en: 'Hello, this is a voice test.' },
    voiceTech: 'Technical settings',
    voiceTest: 'Test the voice',
    voiceTesting: 'Playing…',
    voiceStop: 'Stop',
    voiceFail: 'No sound',
    voiceErrEmpty: 'the server returned an empty response',
    voiceErrTimeout: 'no answer within {s} s (ttsTimeoutMs)',
    voiceErrNetwork: 'voice server unreachable',
  },
};

export const envStrings: Record<Locale, EnvStrings> = { fr, en };
