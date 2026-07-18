<x-mail::message>
@if ($messageLocale === 'en')
# It is your turn 🎉

A temporary no-excuse sandbox has just become available. You can now explore the recruiter workspace with 20 realistic fictional applications.

<x-mail::panel>
The slot is not permanently reserved: start the demo while capacity is still available. Your sandbox will then remain active for up to four hours.
</x-mail::panel>

<x-mail::button :url="config('app.url')">Start my demo</x-mail::button>

You received this single email because you joined the demo waitlist. Your address will not be used for marketing.
@else
# C’est votre tour 🎉

Une sandbox temporaire no-excuse vient de se libérer. Vous pouvez maintenant découvrir l’espace RH avec 20 candidatures fictives réalistes.

<x-mail::panel>
La place n’est pas réservée indéfiniment : lancez la démo tant que la capacité est disponible. Votre sandbox restera ensuite accessible pendant quatre heures maximum.
</x-mail::panel>

<x-mail::button :url="config('app.url')">Lancer ma démo</x-mail::button>

Vous recevez cet unique e-mail parce que vous avez rejoint la liste d’attente de la démo. Votre adresse ne sera pas utilisée à des fins commerciales.
@endif
</x-mail::message>
