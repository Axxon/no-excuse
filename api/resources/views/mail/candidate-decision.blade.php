<x-mail::message>
# Bonjour {{ $application->candidate_name }},

@if ($application->status === 'rejected_out_of_scope')
{{ $application->offer->rejection_message }}

@if ($application->scope_reason)
**Motif expliqué par l’analyse :** {{ $application->scope_reason }}
@endif
@elseif ($application->status === 'selected')
Votre candidature pour **{{ $application->offer->title }}** a été sélectionnée. L'équipe de {{ $application->offer->company }} va vous contacter.
@else
{{ $application->offer->final_rejection_message }}

Votre score d'adéquation final est de **{{ number_format($application->final_score ?? 0, 1, ',', ' ') }}/100**.

@endif

@if ($application->status !== 'rejected_out_of_scope' && $application->candidate_feedback)
**Message personnalisé de l’équipe RH :** {{ $application->candidate_feedback }}
@endif

Merci pour le temps consacré à votre candidature.

{{ $application->offer->organization?->notification_sender_name ?? "L'équipe recrutement" }}
</x-mail::message>
