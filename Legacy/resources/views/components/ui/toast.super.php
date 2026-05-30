~setup {
    @global('system.toast', 'toast_data')

    $this->show = isset($toast_data) && !empty($toast_data);
    $this->message = $toast_data['message'] ?? "";
    $this->type = $toast_data['type'] ?? "info"; // info, success, warning, error

    $this->close = function() {
        $this->show = false;
    };
} ~

<div @if("$show")
     class="toast-container position-fixed bottom-0 end-0 p-3"
     style="z-index: 1055;"
     @transition="fade">
    <div class="toast show align-items-center border-0 {{ $type === 'success' ? 'bg-success' : ($type === 'error' ? 'bg-danger' : ($type === 'warning' ? 'bg-warning' : 'bg-primary')) }} text-white shadow-lg rounded-lg px-4 py-2 flex items-center space-x-3"
         role="alert"
         aria-live="assertive"
         aria-atomic="true">
        <div class="flex-shrink-0">
            <i @if($type === 'success') class="bi bi-check-circle-fill text-xl"></i>
            <i @if($type === 'error') class="bi bi-exclamation-octagon-fill text-xl"></i>
            <i @if($type === 'warning') class="bi bi-exclamation-triangle-fill text-xl"></i>
            <i @if($type === 'info') class="bi bi-info-circle-fill text-xl"></i>
        </div>
        <div class="flex-grow">
            <div class="toast-body font-medium">
                {{ $message }}
            </div>
        </div>
        <button type="button"
                class="flex-shrink-0 ml-4 text-white hover:text-gray-200 focus:outline-none transition-colors"
                @click="close"
                aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>
