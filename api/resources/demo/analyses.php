<?php

$qualified = static fn (
    float $scopeScore,
    string $scopeReason,
    float $finalScore,
    float $experience,
    float $clarity,
    string $summary,
): array => [
    'in_scope' => true,
    'scope_score' => $scopeScore,
    'scope_reason' => $scopeReason,
    'final_score' => $finalScore,
    'score_breakdown' => ['adéquation' => $scopeScore, 'expérience' => $experience, 'clarté' => $clarity],
    'summary' => $summary,
];

$rejected = static fn (float $scopeScore, string $scopeReason): array => [
    'in_scope' => false,
    'scope_score' => $scopeScore,
    'scope_reason' => $scopeReason,
    'final_score' => null,
    'score_breakdown' => null,
    'summary' => null,
];

return [
    0 => $qualified(96, 'Le CV démontre Laravel, PHP, PostgreSQL, Redis, Docker et la pratique des traitements asynchrones.', 93.4, 91, 90, 'Très forte adéquation backend : expérience SaaS, queues, bases de données et qualité logicielle clairement démontrées.'),
    1 => $qualified(98, 'Le CV couvre Laravel, Vue, TypeScript, PostgreSQL, Redis et les tests attendus.', 95.1, 89, 92, 'Profil full-stack très complet, directement aligné avec la stack et les contraintes de mise en production.'),
    2 => $qualified(94, 'Le CV démontre PHP, Laravel, Vue, TypeScript, Docker, PostgreSQL et les tests automatisés.', 92.8, 94, 88, 'Solide expérience d’architecture et de qualité logicielle, avec une couverture équilibrée du backend et du frontend.'),
    3 => $qualified(72, 'Le profil est principalement frontend mais démontre Vue, TypeScript, accessibilité, tests et intégration avec des API Laravel.', 74.6, 72, 86, 'Bonne contribution possible sur le frontend ; la profondeur backend Laravel devra être vérifiée en entretien.'),
    4 => $qualified(97, 'Le CV démontre Laravel, PHP, PostgreSQL, Redis, Docker, CI/CD, PHPUnit et une expérience de fort volume.', 96.2, 98, 91, 'Profil senior particulièrement adapté aux enjeux de queues, performance, fiabilité et pilotage technique.'),
    5 => $qualified(82, 'Le CV démontre Laravel, Vue, TypeScript, PostgreSQL et les tests automatisés.', 82.7, 76, 84, 'Bonne adéquation produit et SaaS ; Redis et Docker sont moins explicitement documentés.'),
    6 => $qualified(89, 'Le CV démontre PHP, Laravel, Redis, PostgreSQL, Docker et la fiabilisation de workers asynchrones.', 89.8, 90, 85, 'Très bon profil backend pour les traitements distribués, l’observabilité et l’optimisation des données.'),
    7 => $qualified(88, 'Le CV démontre Vue, TypeScript, Laravel, PHP, PostgreSQL, Redis et des tests end-to-end.', 88.5, 84, 91, 'Profil full-stack cohérent, avec une attention utile aux tests d’interface et aux systèmes de composants.'),
    8 => $qualified(91, 'Le CV démontre Laravel, PHP, PostgreSQL, Redis, PHPUnit, Docker et une expérience Symfony transférable.', 90.6, 95, 87, 'Expérience backend confirmée et pertinente pour faire évoluer progressivement une application Laravel maintenable.'),
    9 => $qualified(93, 'Le CV démontre Laravel, Vue, TypeScript, PostgreSQL, Redis, Kubernetes et les tests automatisés.', 92.1, 88, 90, 'Très bonne maîtrise des produits multi-tenant et du déploiement, adaptée à un SaaS en croissance.'),
    10 => $qualified(68, 'Le CV démontre PHP, Laravel, Vue, TypeScript, PostgreSQL et une pratique régulière des tests.', 70.3, 55, 82, 'Profil junior prometteur et bien aligné techniquement, avec une expérience encore limitée à accompagner.'),
    11 => $qualified(86, 'Le CV démontre Laravel, PHP, API REST, PostgreSQL, Redis, sécurité et Docker.', 87.4, 92, 84, 'Profil backend expérimenté, particulièrement pertinent pour l’API d’ingestion, l’authentification et la performance.'),
    12 => $qualified(80, 'Le CV démontre Vue, TypeScript, Laravel, PostgreSQL, UX et les tests automatisés.', 81.9, 78, 90, 'Bon profil produit, capable de relier besoins métier, qualité d’interface et implémentation full-stack.'),
    13 => $qualified(63, 'Le CV démontre Docker, Redis, PostgreSQL, Laravel, CI/CD et le monitoring.', 73.2, 94, 72, 'Expertise opérationnelle utile pour industrialiser le produit ; la pratique du développement applicatif devra être confirmée.'),
    14 => $qualified(70, 'Le CV démontre Docker, Redis, PostgreSQL, Laravel, Linux et la supervision de files.', 75.8, 96, 70, 'Profil DevOps applicatif pertinent pour la production et les workers, avec une adéquation développement plus partielle.'),
    15 => $rejected(8, 'Le CV présente une expertise mobile Swift, Kotlin, iOS et Android, sans expérience démontrée sur Laravel, PHP, Vue ou PostgreSQL.'),
    16 => $rejected(6, 'Le CV présente une expertise data Python, SQL et BI, sans expérience démontrée en développement SaaS Laravel et Vue.'),
    17 => $rejected(5, 'Le CV présente une expertise de design produit, recherche utilisateur et prototypage, sans expérience de développement sur la stack annoncée.'),
    18 => $rejected(3, 'Le CV présente une expérience commerciale B2B, sans compétences techniques démontrées correspondant au poste de développement.'),
    19 => $rejected(4, 'Le CV présente une expérience en ressources humaines et management, sans expérience démontrée sur Laravel, PHP, Vue ou TypeScript.'),
];
