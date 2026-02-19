<div class="<?= $outerClass ?? 'flex-grow relative flex items-center' ?>" <?= !empty($unique) ? 'x-data="{unique: true, uniqueValue: \''.($value ?? '').'\'}"' : ''; ?> >
    <input type="<?= $type ?? 'text'; ?>" <?= $attributes; ?> 
        class="<?= $class; ?> <?= $groupClass; ?> w-full min-w-0 py-2  placeholder:text-gray-500 dark:placeholder:text-gray-400  sm:text-sm sm:leading-5 <?= $type != 'text' ? 'input-icon' : ''; ?>
        <?= !empty($readonly) ? 'border-dashed text-gray-600 dark:text-gray-400 cursor-not-allowed focus:ring-0 focus:border-gray-400 dark:focus:border-gray-600 dark:bg-gray-800 dark:border-gray-700' : 'text-gray-900 dark:text-gray-100 dark:bg-gray-800 dark:border-gray-700 focus:ring-1 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400'; ?>"
        />

    <?php if ($type == 'url') { ?>
        <span class="pointer-events-none absolute top-0.5 right-2">
        <?= icon('basic', 'link', 'size-8 mt-px p-1.5 rounded bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'); ?>
        </span>
    <?php } ?>

    <?php if ($type == 'email') { ?>
        <span class="pointer-events-none absolute top-0.5 right-2">
        <?= icon('basic', 'email', 'size-8 mt-px p-1.5 rounded bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'); ?>
        </span>
    <?php } ?>

    <?php if (!empty($autocompleteList)) { ?>
        <datalist id="<?= $id; ?>DataList">
            <?php foreach ($autocompleteList as $listItem) { ?>
                <option value="<?= $listItem; ?>"></option>
            <?php } ?>
        </datalist>
    <?php } ?>

    <?php if (!empty($unique)) { ?>
        <span x-cloak x-show="uniqueValue.length > 0" id="<?= $id; ?>-unique" class="inline-msg">
            <span x-show="unique" class="text-green-600 dark:text-green-400"><?= $unique['alertSuccess']; ?></span>
            <span x-show="!unique" class="text-red-700 dark:text-red-400"><?= $unique['alertFailure']; ?></span>
        </span>
    <?php } ?>
</div><?php
?>
