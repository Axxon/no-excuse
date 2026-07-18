<x-mail::message>
@if ($messageLocale === 'en')
# A demo slot is available

A temporary no-excuse sandbox has become available. Visit the demo and start it while capacity remains.

<x-mail::button :url="config('app.url')">Open no-excuse</x-mail::button>

You received this single email because you joined the demo waitlist. Your address will not be used for marketing.
@else
# Une place est disponible

Une sandbox temporaire no-excuse vient de se libérer. Ouvrez la démo et lancez-la tant qu’une place reste disponible.

<x-mail::button :url="config('app.url')">Ouvrir no-excuse</x-mail::button>

Vous recevez cet unique e-mail parce que vous avez rejoint la liste d’attente de la démo. Votre adresse ne sera pas utilisée à des fins commerciales.
@endif
</x-mail::message>
