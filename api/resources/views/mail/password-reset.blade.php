<x-mail::message>
# Réinitialiser votre accès

Une réinitialisation du mot de passe no-excuse a été demandée pour **{{ $member->email }}**.

<x-mail::button :url="$resetUrl()">Choisir un nouveau mot de passe</x-mail::button>

Ce lien expire dans 60 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.

no-excuse
</x-mail::message>
