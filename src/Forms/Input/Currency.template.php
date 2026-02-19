<div class="flex-grow relative flex" >
    
    <input type="text" <?= $attributes; ?>  x-init=""
        class="<?= $class; ?> <?= $groupClass; ?> w-full min-w-0 pl-6 pr-12 font-sans placeholder:text-gray-500 dark:placeholder:text-gray-400 sm:text-sm sm:leading-5 
        <?= !empty($readonly) ? 'border-dashed text-gray-600 dark:text-gray-400 cursor-not-allowed focus:ring-0 focus:border-gray-400 dark:focus:border-gray-600 dark:bg-gray-800 dark:border-gray-700' : 'text-gray-900 dark:text-gray-100 dark:bg-gray-800 dark:border-gray-700 focus:ring-1 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400'; ?>
        "/>

    <span class="pointer-events-none absolute top-2 left-2  ml-0.5 font-sans text-base font-normal text-gray-500 dark:text-gray-400">
        <?= $currencySymbol; ?>
    </span>

    <span class="pointer-events-none absolute top-2 right-2 mt-px mr-1 font-sans text-sm font-normal text-gray-500 dark:text-gray-400">
        <?= $currencyName; ?>
    </span>

</div>
