<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Proposal Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-xl">
    <h1 class="text-2xl font-bold mb-6">
        Proposal Generator
    </h1>

    @if(session('error'))
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('proposal.generate') }}" class="space-y-4">
        @csrf

        <input name="judul" required
            class="w-full border p-3 rounded-xl"
            placeholder="Judul Proposal">

        <select name="citation_style" class="w-full border p-3 rounded-xl">
            <option value="apa">APA</option>
            <option value="ieee">IEEE</option>
        </select>

        <button
            class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">
            Generate Otomatis
        </button>
    </form>
</div>

</body>
</html>
