<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ProposalController extends Controller
{
    public function index()
    {
        return view('proposal.form');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'judul' => 'required',
            'citation_style' => 'required'
        ]);

        $judul = $request->judul;

        // Cari jurnal (15 → jelasin 10)
        $references = $this->searchJournal($judul, 15);

        // Ambil abstrak jurnal (minimal 10)
        $abstracts = $this->fetchAbstracts($references, 10);

        if (count($abstracts) < 5) {
            return back()->with('error', 'Abstrak jurnal tidak cukup.');
        }

        // AI generate isi proposal
        $content = $this->generateWithAI(
            $judul,
            $abstracts,
            $references
        );

        // Generate Word
        $risPath = $this->generateRIS($references, $judul);

        return $this->generateWord(
            $judul,
            $content,
            $references,
            $request->citation_style
        );
    }

 
    // JURNAL

    private function searchJournal($title, $limit = 15)
    {
        $currentYear = date('Y');
        $minYear = $currentYear - 5; // 5 tahun kebelakang

        $response = Http::get('https://api.crossref.org/works', [
            'query.title' => $title,
            'filter' => "from-pub-date:{$minYear}-01-01,until-pub-date:{$currentYear}-12-31",
            'rows' => $limit
        ]);

        return collect($response['message']['items'] ?? [])
            ->filter(function ($item) use ($minYear) {
                $year = $item['issued']['date-parts'][0][0] ?? 0;
                return $year >= $minYear;
            })
            ->map(function ($item) {
                return [
                    'title' => $item['title'][0] ?? '-',
                    'author' => $item['author'][0]['family'] ?? 'Anonim',
                    'year' => $item['issued']['date-parts'][0][0] ?? '-',
                    'url' => $item['URL'] ?? '-',
                    'doi' => $item['DOI'] ?? null,
                ];
            })->toArray();
    }


    private function fetchAbstracts(array $refs, int $limit)
    {
        $abstracts = [];

        foreach ($refs as $ref) {
            if (!$ref['doi']) continue;

            $res = Http::get(
                "https://api.semanticscholar.org/graph/v1/paper/DOI:{$ref['doi']}",
                ['fields' => 'abstract']
            );

            if ($res->ok() && $res['abstract']) {
                $abstracts[] = $res['abstract'];
            }

            if (count($abstracts) >= $limit) break;
        }

        return $abstracts;
    }

    
    // OPENAI

    private function generateWithAI(string $judul, array $abstracts, array $refs): array
    {
        $abstractList = '';
        foreach ($abstracts as $i => $abs) {
            $author = $refs[$i]['author'] ?? 'Anonim';
            $year   = $refs[$i]['year'] ?? 'n.d';

            $abstractList .= ($i+1) . ". ({$author}, {$year}) {$abs}\n";
        }

    $prompt = <<<PROMPT
    Anda adalah penulis akademik profesional.

    TUGAS UTAMA:
    - Setiap SATU abstrak menjadi SATU paragraf hasil PARAFRASE
    - DILARANG menyalin kalimat asli abstrak
    - Gunakan parafrase semantik (struktur kalimat berbeda)
    - Setiap paragraf WAJIB menyebutkan sumbernya

    Judul Proposal:
    {$judul}

    ABSTRAK JURNAL:
    {$abstractList}

    STRUKTUR WAJIB:
    1. Latar Belakang → 3 paragraf (3 abstrak berbeda)
    2. Tujuan Penelitian → 1 paragraf
    3. Ruang Lingkup → 1 paragraf
    4. State of the Art → 2 paragraf (bandingkan penelitian)

    ATURAN:
    - Total minimal 500 kata
    - Gunakan bahasa Indonesia akademik
    - Gunakan kutipan naratif:
    "Menurut Author (Tahun)..."
    "Penelitian oleh Author et al. (Tahun)..."

    PROMPT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.15, // rendah = aman akademik
        ]);

        return $this->parseAI(
            $response['choices'][0]['message']['content']
        );
    }



    private function parseAI(string $text): array
    {
        preg_match('/Latar Belakang(.*?)(Tujuan Penelitian|$)/s', $text, $lb);
        preg_match('/Tujuan Penelitian(.*?)(Ruang Lingkup|$)/s', $text, $tj);
        preg_match('/Ruang Lingkup(.*?)(State of the Art|$)/s', $text, $rl);
        preg_match('/State of the Art(.*)/s', $text, $sota);

        return [
            'latar_belakang' => trim($lb[1] ?? ''),
            'tujuan' => trim($tj[1] ?? ''),
            'ruang_lingkup' => trim($rl[1] ?? ''),
            'state_of_the_art' => trim($sota[1] ?? ''),
        ];
    }

    
    # WORD

    private function generateWord($judul, $content, $refs, $style)
    {
        $phpWord = new PhpWord();

        
        // DEFAULT FONT

        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

       
        // STYLES

        // Heading 1 (Judul Utama)
        $phpWord->addTitleStyle(1, [
            'name' => 'Times New Roman',
            'size' => 14,
            'bold' => true,
        ], [
            'alignment' => 'center',
            'spaceAfter' => 300
        ]);

        // Heading 2 (Sub Judul)
        $phpWord->addTitleStyle(2, [
            'name' => 'Times New Roman',
            'size' => 12,
            'bold' => true,
        ], [
            'spaceBefore' => 200,
            'spaceAfter' => 120
        ]);

        // Paragraf isi
        $textStyle = [
            'name' => 'Times New Roman',
            'size' => 12
        ];

        $paragraphStyle = [
            'alignment' => 'both', // justify
            'spaceAfter' => 200,
            'spacing' => 120
        ];

        $section = $phpWord->addSection();

       
        // CONTENT

        // Judul Proposal
        $section->addTitle("PROPOSAL\n{$judul}", 1);

        // Isi Proposal
        foreach ($content as $title => $text) {
            $section->addTitle(
                strtoupper(str_replace('_', ' ', $title)),
                2
            );

            $cleanedText = $this->cleanText($text);

            foreach (explode('.', $cleanedText) as $sentence) {
                if (trim($sentence) !== '') {
                    $section->addText(
                        trim($sentence) . '.',
                        $textStyle,
                        $paragraphStyle
                    );
                }
            }

        }

        
        // DAFTAR PUSTAKA

        $section->addTitle('DAFTAR PUSTAKA', 2);

        foreach ($refs as $i => $ref) {
            $citation = $style === 'apa'
                ? "{$ref['author']} ({$ref['year']}). {$ref['title']}. {$ref['url']}"
                : "[" . ($i + 1) . "] {$ref['author']}, \"{$ref['title']}\", {$ref['year']}. {$ref['url']}";

            $section->addText(
                $citation,
                $textStyle,
                ['spaceAfter' => 120]
            );
        }

        
        // SAVE FILE

        $filename = 'Proposal-' . Str::slug($judul) . '.docx';
        $path = storage_path("app/{$filename}");

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return response()->download($path)->deleteFileAfterSend();
    }

    private function cleanText(string $text): string
    {
        // Hapus bullet & markdown
        $text = preg_replace('/^[\*\-\•]+\s*/m', '', $text);

        // Hapus numbering aneh (1., 2), dll
        $text = preg_replace('/^\d+[\.\)]\s*/m', '', $text);

        // Hapus multiple spasi
        $text = preg_replace('/\s+/', ' ', $text);

        // Rapikan paragraf
        $text = str_replace([' .', ' ,'], ['.', ','], $text);

        return trim($text);
    }

    private function generateRIS(array $refs, string $judul): string
    {
        $ris = '';

        foreach ($refs as $ref) {
            $ris .= "TY  - JOUR\n";
            $ris .= "TI  - {$ref['title']}\n";
            $ris .= "AU  - {$ref['author']}\n";
            $ris .= "PY  - {$ref['year']}\n";

            if (!empty($ref['doi'])) {
                $ris .= "DO  - {$ref['doi']}\n";
            }

            if (!empty($ref['url'])) {
                $ris .= "UR  - {$ref['url']}\n";
            }

            $ris .= "ER  - \n\n";
        }

        $filename = 'References-' . Str::slug($judul) . '.ris';
        $path = storage_path("app/{$filename}");

        file_put_contents($path, $ris);

        return $path;
    }



}
