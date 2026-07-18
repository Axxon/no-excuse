<?php

namespace App\Http\Controllers;

/**
 * Garde défensive : le suivi des candidatures reste dans le catalogue RH privé.
 *
 * @deprecated Aucun endpoint candidat n'est exposé.
 */
class CandidateStatusController extends Controller
{
    public function __invoke(): never
    {
        abort(404);
    }
}
