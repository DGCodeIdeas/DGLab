<~setup
    // MangaScript Workspace Reactive State
    $title = $props['title'] ?? 'New Manga Project';
    $sourceMaterial = $props['source'] ?? '';
    $category = $props['category'] ?? 'A'; // Enterprise Cloud
    $tier = $props['tier'] ?? 'medium'; // Chapters
    $status = 'idle'; // idle, processing, completed, failed
    $progress = 0;
    $jobId = null;

    $onGenerate = function() use (&$status, &$sourceMaterial, &$category, &$tier, &$jobId) {
        $status = 'processing';
        $jobId = mangascript()->processAsync([
            'title' => $this->title,
            'source' => $sourceMaterial,
            'category' => $category,
            'tier' => $tier
        ]);
    };

    $onStatusUpdate = function($payload) use (&$status, &$progress) {
        if ($payload['status'] === 'completed') {
            $status = 'completed';
            $progress = 100;
        } elseif ($payload['status'] === 'failed') {
            $status = 'failed';
        } else {
            $progress = $payload['progress'] ?? $progress;
        }
    };
~>

<div class="mangascript-workspace bg-gray-900 text-white p-6 rounded-lg shadow-xl border border-blue-500/30">
    <header class="flex justify-between items-center mb-6 border-b border-gray-800 pb-4">
        <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400">
            MangaScript Studio
        </h1>
        <div class="flex space-x-2">
            <span class="px-3 py-1 bg-blue-500/10 text-blue-400 text-xs rounded-full border border-blue-400/20">
                Phase 1: Core
            </span>
            <span class="px-3 py-1 bg-purple-500/10 text-purple-400 text-xs rounded-full border border-purple-400/20">
                SuperPHP Powered
            </span>
        </div>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Input Section -->
        <section class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Project Title</label>
                <input type="text" @bind="$title" class="w-full bg-gray-800 border border-gray-700 rounded p-2 focus:border-blue-500 transition-colors" placeholder="Enter title...">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Source Material (Novel/Text)</label>
                <textarea @bind="$sourceMaterial" rows="12" class="w-full bg-gray-800 border border-gray-700 rounded p-2 focus:border-blue-500 transition-colors resize-none" placeholder="Paste your novel content here..."></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">AI Provider Category</label>
                    <select @bind="$category" class="w-full bg-gray-800 border border-gray-700 rounded p-2 focus:border-blue-500">
                        <option value="A">Enterprise Cloud (Tier 1)</option>
                        <option value="B">Open Model Hosting (Tier 2)</option>
                        <option value="E">Local/Self-Hosted (Tier 3)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Context Tier</label>
                    <select @bind="$tier" class="w-full bg-gray-800 border border-gray-700 rounded p-2 focus:border-blue-500">
                        <option value="short">Short (Scenes/Paragraphs)</option>
                        <option value="medium">Medium (Chapters)</option>
                        <option value="long">Long (Short Stories)</option>
                        <option value="massive">Massive (Full Novels)</option>
                    </select>
                </div>
            </div>

            <button @click="$onGenerate" class="w-full py-3 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 text-white font-bold rounded-lg shadow-lg shadow-blue-500/20 transition-all transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed" :disabled="$status === 'processing'">
                <span s-if="$status !== 'processing'">Generate Manga Script</span>
                <span s-else class="flex items-center justify-center">
                    <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                </span>
            </button>
        </section>

        <!-- Preview Section -->
        <section class="bg-gray-800/50 rounded-lg p-6 border border-gray-700 relative overflow-hidden">
            <div s-if="$status === 'idle'" class="h-full flex flex-col items-center justify-center text-gray-500 text-center space-y-4">
                <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p>Configure your novel and click generate to begin the transformation.</p>
            </div>

            <div s-if="$status === 'processing'" class="h-full flex flex-col items-center justify-center space-y-6">
                <div class="w-full bg-gray-700 rounded-full h-4 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-blue-500 to-cyan-400 h-full transition-all duration-500" :style="'width: ' + $progress + '%'"></div>
                </div>
                <div class="text-center">
                    <p class="text-blue-400 font-medium mb-1">Generation in Progress...</p>
                    <p class="text-xs text-gray-500">ID: {{ $jobId }}</p>
                </div>
            </div>

            <div s-if="$status === 'completed'" class="h-full">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-green-400 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Generation Complete
                    </h3>
                    <button class="text-xs bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded transition-colors">Download PDF</button>
                </div>
                <div class="bg-gray-900 rounded p-4 text-sm font-mono text-gray-300 h-96 overflow-y-auto custom-scrollbar border border-gray-800">
                    <p class="mb-4 text-blue-400"># TITLE: {{ $title }}</p>
                    <p class="mb-4">[PAGE 1]</p>
                    <p class="mb-2">PANEL 1: Wide shot of the city at dusk. The sky is a gradient of violet and amber.</p>
                    <p class="mb-4">DIALOGUE (Protagonist): "The silence here is louder than the storms back home."</p>
                    <p class="mb-2">PANEL 2: Close up on a single window lighting up in a distant skyscraper.</p>
                </div>
            </div>

            <div s-if="$status === 'failed'" class="h-full flex flex-col items-center justify-center text-red-400 space-y-4">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <p class="font-bold">Generation Failed</p>
                <p class="text-sm text-gray-500">The AI provider returned an error. Please try again.</p>
                <button @click="$status = 'idle'" class="mt-4 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded text-xs transition-colors">Reset</button>
            </div>
        </section>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #4B5563; }
</style>
