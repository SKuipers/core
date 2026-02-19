<?php 
if ($disabled) {
    $bgClass = ' bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
} else if ($color == 'gray') {
    $bgClass = ' bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200';
} else if ($color == 'red') {
    $bgClass = ' border-red-700 dark:border-red-600 bg-red-700 dark:bg-red-600 hover:bg-red-900 dark:hover:bg-red-800 hover:border-red-900 dark:hover:border-red-800 text-white';
} else if ($color == 'purple') {
    $bgClass = ' border-purple-600 dark:border-purple-500 bg-purple-600 dark:bg-purple-500 hover:bg-purple-800 dark:hover:bg-purple-700 hover:border-purple-800 dark:hover:border-purple-700 text-white';
} else if ($type == 'submit' || $color == 'submit') {
    $bgClass = ' border-gray-800 dark:border-gray-700 bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 text-white';
} else {
    $bgClass = ' bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200';
}

if ($size == 'sm') {
    $sizeClass = ($value ? 'px-3' : 'px-2').' py-2 text-xs sm:leading-4 ';
    $sizeClassIcon = ($value ? ' lg:-ml-0.5 lg:mr-1 ' : '') . ' size-4 ';
} else {
    $sizeClass = ($value ? 'px-4' : 'px-3').' py-2 text-sm sm:leading-5 ';
    $sizeClassIcon = ($value ? ' lg:-ml-0.5 lg:mr-1.5 ' : '') . ' size-5 ';
}
?>
<?php if ($type == 'blank') { ?>
    <button type="button" <?= $attributes; ?> class="<?= $class; ?> <?= $groupClass; ?> <?= $sizeClass; ?> inline-block align-middle">
    
        <?php $svgClass = 'text-gray-600 dark:text-gray-400 block m-0.5 size-5 '.($iconClass ?? ''); ?>

        <?= !empty($icon) ? icon($iconLibrary ?? 'solid', $icon, $svgClass ) : ''; ?>
        
        <?= $value; ?>

    </button>
<?php } elseif ($type == 'submit') { ?>
    <button type="submit" <?= $attributes; ?> x-data="{ submitDisabled: false }" x-bind:disabled="submitDisabled" x-on:submit="submitDisabled = true" @click="submitting = true" :class="{'submitted bg-gray-100 dark:bg-gray-700': submitting, '<?= $bgClass; ?>' : !submitting}" class="<?= $class; ?> <?= $groupClass; ?> <?= $sizeClass; ?> inline-block align-middle items-center px-8 font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:focus-visible:outline-blue-400 border <?= $bgClass; ?>" />
        <span :class="{'opacity-0': submitting}">
            <?= $value; ?>
        </span>
    </button>
<?php } elseif ($type == 'quickSubmit') { ?>
    <button type="submit" <?= $attributes; ?>  @click="submitting = true" :class="{'submitted': submitting}" class="<?= $class; ?> <?= $groupClass; ?> <?= $sizeClass; ?> <?= $bgClass; ?> inline-flex align-middle items-center font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:focus-visible:outline-blue-400 border border-gray-400 dark:border-gray-700" >
        <?php $svgClass = 'inline text-gray-600 dark:text-gray-400 size-5 '.(!empty($value) ? 'lg:-ml-0.5 lg:mr-1.5 ' : '').($iconClass ?? ''); ?>
        <?= !empty($icon) ? icon($iconLibrary ?? 'solid', $icon, $svgClass ) : ''; ?>

        <span :class="{'opacity-0': submitting}"><?= $value; ?></span>
    </button>
<?php } elseif ($type == 'input') { ?>
    <input type="button" <?= $attributes; ?> class="<?= $class; ?> <?= $groupClass; ?> <?= $sizeClass; ?> <?= $bgClass; ?> inline-block align-middle items-center border border-gray-400 dark:border-gray-700 px-8 font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:focus-visible:outline-blue-400"/>
<?php } elseif ($type == 'button') { ?>
<button type="button" <?= $attributes; ?> class="<?= $class; ?> <?= $groupClass; ?> <?= $sizeClass; ?> <?= $bgClass; ?> inline-flex align-middle items-center border border-gray-400 dark:border-gray-700 gap-2 font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:focus-visible:outline-blue-400 ">

    <?php $svgClass = 'text-gray-600 dark:text-gray-400 block '.$sizeClassIcon.($iconClass ?? ''); ?>
    <?= !empty($icon) ? icon($iconLibrary ?? 'solid', $icon, $svgClass, $iconOptions ) : ''; ?>

    <?php if ($value) { ?><span aria-label="<?= $value; ?>"><?= $value; ?></span><?php } ?>

    <?= !empty($tag) ? "<span class='inline-flex items-center justify-center <?= $sizeClassIcon; ?> rounded-full ml-2 text-xxs bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-300 font-normal'>{$tag}</span>" : ''; ?>

    </button><?php 
} ?>
