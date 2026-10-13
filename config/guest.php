<?php
// =============================================================================
// config/guests.php — Configuration par défaut des sessions guest
// Surchargée par agenia_prospect_configs en BDD pour les prospects invités
// =============================================================================
return [

  // ── Prompt système par défaut ──────────────────────────────────────────────
  // Sera remplacé par le prompt personnalisé du prospect si disponible en BDD
  'system_prompt' => 'Tu es Agenia, un assistant IA démonstration. '
    . 'Tu aides l\'utilisateur à découvrir les capacités de la plateforme. '
    . 'Sois concis, professionnel et bienveillant. '
    . 'Cette session de démonstration dure 20 minutes.',

  // ── Surcharge config LLM pour les guests ──────────────────────────────────
  // null = utiliser la valeur de settings.json
  'llm_config' => [
    'model'       => null,   // null = modèle par défaut de settings.json
    'memoire'     => 5000,   // contexte réduit pour les guests
    'noThink'     => false,
  ],

  // ── Modules actifs pour les guests ────────────────────────────────────────
  // Surcharge possible par prospect via agenia_prospect_configs.modules
  'modules' => null,  // null = lire depuis agenia_modules en BDD

  // ── Message de bienvenue affiché dans le chat ──────────────────────────────
  'welcome_fr' => '👋 Bienvenue ! Vous disposez de **20 minutes** et **10 messages** pour explorer Agenia. '
    . 'Posez vos questions librement.',
  'welcome_en' => '👋 Welcome! You have **20 minutes** and **10 messages** to explore Agenia. '
    . 'Feel free to ask anything.',
];