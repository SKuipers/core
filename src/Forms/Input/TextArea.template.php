<div class="relative <?= $outerClass ?? 'flex-grow'; ?>">
<textarea <?= $attributes; ?> 
    class="<?= $class; ?> w-full rounded-md border dark:border-gray-700 py-2 font-sans placeholder:text-gray-500 dark:placeholder:text-gray-400 sm:text-sm sm:leading-5
    <?= !empty($readonly) ? 'border-dashed text-gray-600 dark:text-gray-400 cursor-not-allowed :ring-0 focus:border-gray-400 dark:focus:border-gray-600 dark:bg-gray-800' : 'text-gray-900 dark:text-gray-100 dark:bg-gray-800 focus:ring-1 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400'; ?>"><?= $text; ?></textarea>

<?php if (!empty($autosize)) { ?>
    <script type="text/javascript">autosize($("#<?= $id; ?>"));</script>
<?php } ?>

</div>
