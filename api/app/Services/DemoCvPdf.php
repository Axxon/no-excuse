<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class DemoCvPdf
{
    /** @param array{name: string, role: string, years: string, skills: string, summary: string} $candidate */
    public function render(array $candidate, int $index): string
    {
        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $years = preg_split('/\s+/', $candidate['years']) ?: [];
        $skills = preg_split('/\s+/', $candidate['skills']) ?: [];
        $locations = ['Paris', 'Lyon', 'Bordeaux', 'Nantes', 'Lille', 'Toulouse', 'Rennes', 'Montpellier'];
        $schools = ['Université Paris Cité', 'Université de Lille', 'INSA Lyon', 'Université de Bordeaux', 'IUT de Nantes'];
        $companies = ['Nova Studio', 'HexaCloud', 'Kanso Digital', 'Octave Solutions', 'Miroir Labs', 'Alto Services'];
        $firstYear = (int) ($years[0] ?? 2018);
        $middleYear = (int) ($years[1] ?? ($firstYear + 3));
        $lastYear = (int) ($years[2] ?? 2026);

        $html = view('demo.cv', [
            'candidate' => $candidate,
            'skills' => $skills,
            'location' => $locations[$index % count($locations)],
            'email' => 'candidat-'.($index + 1).'@example.test',
            'phone' => sprintf('06 00 %02d %02d %02d', $index + 10, $index + 20, $index + 30),
            'school' => $schools[$index % count($schools)],
            'experiences' => [
                ['period' => $middleYear.' — '.$lastYear, 'role' => $candidate['role'], 'company' => $companies[$index % count($companies)], 'details' => $candidate['summary'].' Travail en équipe, documentation et amélioration continue des livraisons.'],
                ['period' => $firstYear.' — '.$middleYear, 'role' => 'Consultant·e / développeur·se', 'company' => $companies[($index + 2) % count($companies)], 'details' => 'Participation à des projets clients, analyse des besoins, réalisation et maintenance. Pratique régulière de '.implode(', ', array_slice($skills, 0, 4)).'.'],
            ],
            'graduationYear' => max(2012, $firstYear - 1),
        ])->render();

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
