<div class="flex-grow relative flex" x-data="{selectOpen: false, selectedItem: ''}"
    @keydown.escape="if(selectOpen){ selectOpen=false; }"
    >
    
    <input type="text" <?= $attributes; ?> maxlength="5" @click="selectOpen"
        class="<?= $class; ?> <?= $groupClass; ?> w-full min-w-0 py-2 font-sans placeholder:text-gray-500 dark:placeholder:text-gray-400 sm:text-sm sm:leading-5 <?= $type != 'text' ? 'input-icon' : ''; ?>
        <?= !empty($readonly) ? 'border-dashed text-gray-600 dark:text-gray-400 cursor-not-allowed focus:ring-0 focus:border-gray-400 dark:focus:border-gray-600 dark:bg-gray-800 dark:border-gray-700' : 'text-gray-900 dark:text-gray-100 dark:bg-gray-800 dark:border-gray-600 focus:ring-1 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400'; ?>
        "/>

    <span class="pointer-events-none absolute top-0.5 right-2">
        <?= icon('outline', 'clock', 'pointer-events-none size-8 mt-px p-1.5 rounded text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300'); ?>
    </span>

</div>
