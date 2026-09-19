~setup {
    // State management
    $this->status = 'idle'; // idle, uploading, processing, success, error
    $this->progress = 0;
    $this->errorMessage = "";
    $this->selectedFile = null;
    $this->selectedFont = "merriweather";
    $this->result = null;
    $this->fontSearch = "";

    // Font data
    $this->fonts = [
        ['id' => 'merriweather', 'name' => 'Merriweather', 'description' => 'Elegant serif font for screens.'],
        ['id' => 'opendyslexic', 'name' => 'OpenDyslexic', 'description' => 'Designed to increase readability for dyslexia.'],
        ['id' => 'fira-sans', 'name' => 'Fira Sans', 'description' => 'Modern sans-serif with excellent legibility.'],
        ['id' => 'roboto', 'name' => 'Roboto', 'description' => 'Google\'s signature Material Design font.'],
        ['id' => 'lato', 'name' => 'Lato', 'description' => 'A friendly and balanced sans-serif typeface.'],
        ['id' => 'montserrat', 'name' => 'Montserrat', 'description' => 'Geometric sans-serif with wide utility.'],
        ['id' => 'playfair', 'name' => 'Playfair Display', 'description' => 'High-contrast serif, great for headings.'],
    ];

    // Computed fonts based on search
    $this->getFilteredFonts = function() {
        if (empty($this->fontSearch)) {
            return $this->fonts;
        }
        $search = strtolower($this->fontSearch);
        return array_filter($this->fonts, function($font) use ($search) {
            return str_contains(strtolower($font['name']), $search) ||
                   str_contains(strtolower($font['description']), $search);
        });
    };

    // Actions
    $this->onFileChange = function($event) {
        $files = $event['target']['files'] ?? [];
        if (count($files) > 0) {
            $this->selectedFile = [
                'name' => $files[0]['name'],
                'size' => $files[0]['size']
            ];
            $this->status = 'idle';
        }
    };

    $this->removeFile = function() {
        $this->selectedFile = null;
        $this->status = 'idle';
    };

    $this->handleSubmit = function() {
        if (!$this->selectedFile) return;

        $this->status = 'processing';
        $this->progress = 0;

        try {
            // Simulate progress for demonstration
            $this->progress = 30;
            $this->progress = 70;

            $this->result = [
                'download_url' => '#',
                'filename' => $this->selectedFile['name'] . '-modified.epub'
            ];

            $this->progress = 100;
            $this->status = 'success';

            global_state('system.toast', [
                'message' => 'EPUB font changed successfully!',
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            $this->status = 'error';
            $this->errorMessage = $e->getMessage();

            global_state('system.toast', [
                'message' => 'Failed to process EPUB.',
                'type' => 'error'
            ]);
        }
    };
} ~

<section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap -mx-4">
            <!-- Main Content -->
            <div class="w-full lg:w-2/3 px-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="p-8">
                        <div class="flex items-center space-x-4 mb-8">
                            <div class="p-3 bg-blue-100 text-blue-600 rounded-lg">
                                <i class="bi bi-type text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">EPUB Font Changer</h2>
                                <p class="text-gray-500">Personalize your reading experience by changing fonts.</p>
                            </div>
                        </div>

                        <!-- Form Area -->
                        <div class="space-y-6">
                            <!-- File Upload Area -->
                            <div class="relative">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">EPUB File</label>

                                <div @if(!$selectedFile)
                                     class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 transition-colors cursor-pointer bg-gray-50"
                                     @click="document.getElementById('file-input').click()">
                                    <i class="bi bi-cloud-arrow-up text-4xl text-gray-400 mb-4"></i>
                                    <p class="text-gray-600">Click to upload or drag and drop</p>
                                    <p class="text-xs text-gray-400 mt-2">Maximum file size: 100MB (EPUB only)</p>
                                    <input type="file" id="file-input" class="hidden" accept=".epub" @change="onFileChange">
                                </div>

                                <div @if($selectedFile) class="bg-blue-50 border border-blue-100 rounded-xl p-6 flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="p-2 bg-blue-500 text-white rounded">
                                            <i class="bi bi-file-earmark-richtext"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900">{{ $selectedFile['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ round($selectedFile['size'] / 1024, 2) }} KB</div>
                                        </div>
                                    </div>
                                    <button class="text-red-500 hover:text-red-600 p-2" @click="removeFile">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Font Selection with Reactive Search -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-semibold text-gray-700">Select Font</label>
                                    <div class="relative w-48">
                                        <input type="text"
                                               class="w-full pl-8 pr-3 py-1 text-xs border border-gray-200 rounded-full focus:outline-none focus:border-blue-400"
                                               placeholder="Search fonts..."
                                               @input="$fontSearch = $event.target.value">
                                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-64 overflow-y-auto p-1">
                                    <div @foreach="($this->getFilteredFonts)() as $font"
                                         class="p-4 rounded-xl border-2 transition-all cursor-pointer {{ $selectedFont === $font['id'] ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200 bg-white' }}"
                                         @click="$selectedFont = '{{ $font['id'] }}'">
                                        <div class="font-bold {{ $selectedFont === $font['id'] ? 'text-blue-700' : 'text-gray-900' }}">
                                            {{ $font['name'] }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">{{ $font['description'] }}</div>
                                    </div>

                                    <div @if(empty(($this->getFilteredFonts)())) class="col-span-2 py-8 text-center text-gray-400">
                                        <i class="bi bi-search mb-2 text-2xl block"></i>
                                        <p class="text-sm">No fonts found matching "{{ $fontSearch }}"</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                                        @click="handleSubmit"
                                        {{ !$selectedFile || $status === 'processing' ? 'disabled' : '' }}>
                                    <i @if($status === 'processing') class="bi bi-arrow-repeat animate-spin"></i>
                                    <i @if($status !== 'processing') class="bi bi-magic"></i>
                                    <span>{{ $status === 'processing' ? 'Processing...' : 'Change Font' }}</span>
                                </button>
                            </div>

                            <!-- Progress Indicator -->
                            <div @if($status === 'processing') class="mt-8 space-y-2">
                                <div class="flex justify-between text-sm font-medium">
                                    <span class="text-gray-700">Processing...</span>
                                    <span class="text-blue-600">{{ $progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>

                            <!-- Success Result -->
                            <div @if($status === 'success') class="mt-8 p-6 bg-green-50 border border-green-100 rounded-xl text-center">
                                <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-check-lg text-3xl"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Success!</h3>
                                <p class="text-gray-600 mb-6">Your EPUB has been processed and is ready for download.</p>
                                <a href="{{ $result['download_url'] }}" download="{{ $result['filename'] }}" class="inline-flex items-center space-x-2 px-8 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors">
                                    <i class="bi bi-download"></i>
                                    <span>Download EPUB</span>
                                </a>
                            </div>

                            <!-- Error Alert -->
                            <div @if($status === 'error') class="mt-8 p-4 bg-red-50 border border-red-100 rounded-xl flex items-start space-x-3 text-red-700">
                                <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
                                <div>
                                    <div class="font-bold">Error</div>
                                    <div class="text-sm">{{ $errorMessage }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-1/3 px-4">
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                            <i class="bi bi-info-circle mr-2 text-blue-500"></i>
                            About This Service
                        </h4>
                        <p class="text-gray-600 text-sm leading-relaxed mb-4">
                            This tool allows you to change the fonts in your EPUB e-books. Perfect for improving readability or personalizing your reading experience.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="bi bi-check2 text-green-500 mr-2"></i>
                                Supports EPUB 2 and 3
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="bi bi-check2 text-green-500 mr-2"></i>
                                Chunked upload support
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="bi bi-check2 text-green-500 mr-2"></i>
                                Open source fonts only
                            </li>
                        </ul>
                    </div>

                    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl shadow-sm p-6 text-white">
                        <h4 class="text-lg font-bold mb-4">Need Help?</h4>
                        <p class="text-blue-100 text-sm mb-6">
                            Check out our documentation for more information on how to use our tools.
                        </p>
                        <a href="/docs" class="block w-full py-3 bg-white text-blue-600 text-center font-bold rounded-lg hover:bg-blue-50 transition-colors">
                            Read Docs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Include the toast for feedback -->
<s:ui:toast />
