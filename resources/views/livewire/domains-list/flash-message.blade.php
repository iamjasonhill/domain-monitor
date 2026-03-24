<!-- Flash Message -->
<div x-data="{ showFlash: false, flashMessage: '', flashType: '' }" @flash-message.window="
        showFlash = true;
        flashMessage = $event.detail.message || 'Operation completed';
        flashType = $event.detail.type || 'success';
        setTimeout(() => showFlash = false, 5000);
     " x-show="showFlash" x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform translate-y-full"
    x-transition:enter-end="opacity-100 transform translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform translate-y-0"
    x-transition:leave-end="opacity-0 transform translate-y-full"
    class="fixed bottom-4 right-4 z-50 w-full max-w-sm" style="display: none;">
    <div :class="{
        'bg-green-500': flashType === 'success',
        'bg-yellow-500': flashType === 'warning',
        'bg-red-500': flashType === 'error'
    }" class="rounded-lg shadow-lg p-4 text-white flex items-center justify-between">
        <span x-text="flashMessage"></span>
        <button @click="showFlash = false" class="ml-4 text-white hover:text-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                </path>
            </svg>
        </button>
    </div>
</div>
