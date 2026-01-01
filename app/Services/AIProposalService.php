<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIProposalService
{
    public static function generate(string $judul, array $abstracts): array
    {
        $prompt = "
Anda adalah penulis akademik.
Gunakan abstrak jurnal berikut untuk menyusun proposal ilmiah.

Judul Proposal:
{$judul}

Abstrak Jurnal:
" . implode("\n\n", $abstracts) . "

Buatkan:
1. Latar Belakang (akademik, formal)
2. Tujuan Penelitian
3. Ruang Lingkup
4. State of the Art (ringkas)

Gunakan bahasa Indonesia baku.
Jangan mengarang data di luar abstrak.
";

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2
            ]);

        return self::parse($response['choices'][0]['message']['content']);
    }

    private static function parse(string $text): array
    {
        preg_match('/Latar Belakang:(.*?)Tujuan Penelitian:/s', $text, $lb);
        preg_match('/Tujuan Penelitian:(.*?)Ruang Lingkup:/s', $text, $tj);
        preg_match('/Ruang Lingkup:(.*?)State of the Art:/s', $text, $rl);
        preg_match('/State of the Art:(.*)/s', $text, $sota);

        return [
            'latar_belakang' => trim($lb[1] ?? ''),
            'tujuan' => trim($tj[1] ?? ''),
            'ruang_lingkup' => trim($rl[1] ?? ''),
            'state_of_the_art' => trim($sota[1] ?? ''),
        ];
    }
}
