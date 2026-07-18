<x-mail::message>
# Bonjour {{ $application->candidate_name }},

@if ($application->status === 'rejected_out_of_scope')
{{ $application->offer->rejection_message }}
@elseif ($application->status === 'selected')
Votre candidature pour **{{ $application->offer->title }}** a été sélectionnée. L'équipe de {{ $application->offer->company }} va vous contacter.
@else
{{ $application->offer->final_rejection_message }}

Votre score d'adéquation final est de **{{ number_format($application->final_score ?? 0, 1, ',', ' ') }}/100**.

@if ($application->candidate_feedback)
**Retour du recruteur :** {{ $application->candidate_feedback }}
@endif
@endif

Merci pour le temps consacré à votre candidature.

{{ $application->offer->organization?->notification_sender_name ?? "L'équipe recrutement" }}
</x-mail::message>
