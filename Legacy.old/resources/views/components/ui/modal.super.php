~setup {
    $this->show = $show ?? false;
    $this->title = $title ?? "Modal Title";
    $this->size = $size ?? "md"; // sm, md, lg, xl

    $this->close = function() {
        $this->show = false;
    };
} ~

<div @if("$show")
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true"
     @transition="fade">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full {{ $size === 'sm' ? 'sm:max-w-sm' : ($size === 'lg' ? 'sm:max-w-lg' : ($size === 'xl' ? 'sm:max-w-xl' : 'sm:max-w-md')) }}">

            <!-- Header -->
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b">
                <div class="sm:flex sm:items-start justify-between">
                    <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">
                        {{ $title }}
                    </h3>
                    <button type="button"
                            class="text-gray-400 hover:text-gray-500 transition-colors"
                            @click="close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <div @if(isset($footer)) class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t">
                {{ $footer }}
            </div>
        </div>
    </div>
</div>
