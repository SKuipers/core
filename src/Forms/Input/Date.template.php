<div class="<?= $outerClass ?? 'flex-grow relative flex items-center' ?>">
    <input type="date" <?= $attributes; ?> maxlength="10"
    class="<?= $groupClass; ?> w-full font-sans py-2 text-gray-900 dark:text-gray-100 dark:bg-gray-800 dark:border-gray-600 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-1 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400 sm:text-sm sm:leading-5"
    />
</div><?php
?>
