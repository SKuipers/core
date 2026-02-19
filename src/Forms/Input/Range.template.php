<div class="flex-grow relative inline-flex justify-between items-center" x-data="{'rangeValue': '<?= $value; ?>'}">
    <span class="flex-shrink text-left text-gray-600 dark:text-gray-400 font-normal mr-2"><?= $min; ?></span>
    <input type="range" class="flex-grow" <?= $attributes; ?> x-model="rangeValue">
    <span class="flex-shrink text-right text-gray-600 dark:text-gray-400 font-normal ml-2"><?= $max; ?></span>
    <output class="block w-12 p-1 ml-4 border dark:border-gray-700 rounded-md bg-gray-200 dark:bg-gray-700 dark:text-gray-100" x-html="rangeValue"><?= $value; ?></output>
</div>
