<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #183128; font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; line-height: 1.5; }
        .notice { padding: 7px 34px; color: #466057; background: #eef6e7; text-align: center; font-size: 8.5px; letter-spacing: .8px; text-transform: uppercase; }
        header { padding: 30px 38px 25px; color: white; background: #143d2e; }
        h1 { margin: 0 0 4px; font-size: 27px; font-weight: 700; }
        .role { margin: 0 0 16px; color: #d9eeaa; font-size: 14px; }
        .contact { color: #e3eee9; font-size: 9.5px; }
        main { padding: 26px 38px 20px; }
        .column-left { display: inline-block; width: 66%; padding-right: 24px; vertical-align: top; }
        .column-right { display: inline-block; width: 33%; padding-left: 21px; border-left: 1px solid #dce7e1; vertical-align: top; }
        h2 { margin: 0 0 10px; color: #23704e; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        section { margin-bottom: 22px; }
        .profile { margin: 0; font-size: 11px; }
        .experience { margin-bottom: 16px; }
        .experience strong { display: block; font-size: 11.5px; }
        .experience .meta { color: #587066; font-size: 9px; }
        .experience p { margin: 5px 0 0; }
        .skill { display: inline-block; margin: 0 4px 5px 0; padding: 4px 7px; color: #1b513b; background: #edf5e7; border-radius: 3px; font-size: 8.5px; }
        .side-item { margin-bottom: 13px; }
        .side-item strong { display: block; }
        footer { position: fixed; right: 38px; bottom: 18px; left: 38px; color: #73867f; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
<div class="notice">CV fictif · démonstration no-excuse · aucune personne réelle</div>
<header>
    <h1>{{ $candidate['name'] }}</h1>
    <p class="role">{{ $candidate['role'] }}</p>
    <div class="contact">{{ $location }} · {{ $phone }} · {{ $email }}</div>
</header>
<main>
    <div class="column-left">
        <section>
            <h2>Profil</h2>
            <p class="profile">{{ $candidate['summary'] }} Je recherche un environnement où la qualité du produit, la collaboration et la progression collective occupent une place centrale.</p>
        </section>
        <section>
            <h2>Expériences professionnelles</h2>
            @foreach ($experiences as $experience)
                <div class="experience">
                    <strong>{{ $experience['role'] }}</strong>
                    <div class="meta">{{ $experience['company'] }} · {{ $experience['period'] }}</div>
                    <p>{{ $experience['details'] }}</p>
                </div>
            @endforeach
        </section>
        <section>
            <h2>Projet marquant</h2>
            <p>Conception et livraison d’un outil métier de bout en bout : cadrage, développement, tests, documentation, suivi des retours utilisateurs et amélioration des performances.</p>
        </section>
    </div>
    <div class="column-right">
        <section>
            <h2>Compétences</h2>
            @foreach ($skills as $skill)<span class="skill">{{ $skill }}</span>@endforeach
        </section>
        <section>
            <h2>Formation</h2>
            <div class="side-item"><strong>Master / diplôme spécialisé</strong>{{ $school }} · {{ $graduationYear }}</div>
        </section>
        <section>
            <h2>Langues</h2>
            <div class="side-item"><strong>Français</strong>Courant</div>
            <div class="side-item"><strong>Anglais</strong>Professionnel</div>
        </section>
        <section>
            <h2>Centres d’intérêt</h2>
            <p>Veille professionnelle, transmission, projets associatifs et randonnée.</p>
        </section>
    </div>
</main>
<footer>Document synthétique généré uniquement pour tester no-excuse. Coordonnées et parcours entièrement fictifs.</footer>
</body>
</html>
