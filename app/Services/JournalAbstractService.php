<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class JournalAbstractService
{
    public static function fetchAbstracts(array $references, int $limit = 10): array
    {
        $abstracts = [];

        foreach ($references as $ref) {
            if (!isset($ref['doi'])) continue;

            $response = Http::get(
                "https://api.semanticscholar.org/graph/v1/paper/DOI:{$ref['doi']}",
                ['fields' => 'title,abstract']
            );

            if ($response->ok() && $response['abstract']) {
                $abstracts[] = $response['abstract'];
            }

            if (count($abstracts) >= $limit) break;
        }

        return $abstracts;
    }
}
