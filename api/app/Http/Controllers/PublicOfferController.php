<?php

namespace App\Http\Controllers;

/**
 * Garde défensive : no-excuse ne publie jamais ses offres.
 *
 * @deprecated Le parcours public a été remplacé par l'API d'ingestion privée.
 */
class PublicOfferController extends Controller
{
    public function __invoke(): never
    {
        abort(404);
    }
}
