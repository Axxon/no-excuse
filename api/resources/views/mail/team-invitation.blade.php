<x-mail::message>
# Bonjour {{ $member->name }},

Vous êtes invité·e à rejoindre l’espace recrutement de **{{ $member->organization?->name }}** sur no-excuse avec le rôle **{{ $member->role }}**.

<x-mail::button :url="$activationUrl()">Activer mon accès</x-mail::button>

Ce lien personnel expire dans 24 heures. Aucun mot de passe temporaire ne doit être échangé avec votre équipe.

L’équipe {{ $member->organization?->name }}
</x-mail::message>
