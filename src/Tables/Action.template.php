<?php
    if ($action == 'add' || $action == 'addMultiple' || $action == 'accept' || $action == 'approve') {
        $hoverClass = 'hover:text-green-500 hover:border-green-500 dark:hover:text-green-400 dark:hover:border-green-400';
    } elseif ($action == 'delete' || $action == 'reject' || $action == 'decline' || $action == 'cancel') {
        $hoverClass = 'hover:text-red-700 hover:border-red-700 dark:hover:text-red-400 dark:hover:border-red-400';
    } else {
        $hoverClass = 'hover:text-blue-500 hover:border-blue-500 dark:hover:text-blue-400 dark:hover:border-blue-400';
    }

    switch ($type) {
        case 'interface':
            $displayClass = 'border-0 px-2 py-2';
            $svgClass = 'size-4 '.($displayLabel ? 'lg:-ml-0.5 lg:mr-1.5 ' : '').($iconClass ?? '');
            break;
        default:
            $displayClass = 'px-3 py-2 bg-white dark:bg-gray-800 shadow-sm border dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700';
            $svgClass = 'size-6 sm:size-5 '.($displayLabel ? 'lg:-ml-0.5 lg:mr-1.5 ' : '').($iconClass ?? '');
    }
?>

<a <?= $attributes; ?> <?= !$modal ? '@click="modalOpen = false"' : '' ?> title="<?= !$displayLabel ? $label : ''; ?>"
    class="<?= $class; ?> inline-flex items-center align-middle rounded-md text-sm sm:leading-5 font-semibold <?= $displayClass; ?> <?= $hoverClass; ?> <?= $displayLabel ? 'text-gray-600 dark:text-gray-400 lg:text-gray-500 dark:lg:text-gray-400' : 'text-gray-600 dark:text-gray-400'; ?>">

    <?= icon($iconLibrary ?? 'solid', $icon ?? $action, $svgClass) ?>
    
    <?php if ($displayLabel) { ?>
    <span class="hidden lg:block text-gray-800 dark:text-gray-200 whitespace-nowrap">
        <?= $label; ?>
    </span>
    <?php } ?>
</a>
