<div class="space-y-3">
    <input
        type="text"
        value="{{ $url }}"
        readonly
        class="w-full rounded-lg border-gray-300 text-sm"
        onclick="this.select(); navigator.clipboard?.writeText(this.value);"
    >

    <p class="text-sm text-gray-500">
        Select or click the field to copy the public media URL.
    </p>
</div>
