<?php 
if ($type == 'submit' || $color == 'submit' || $color == 'primary') {
    $buttonClass = $size == 'lg' ? 'button-lg-primary' : ($size == 'sm' ? 'button-sm-primary' : 'button-primary');
} elseif ($type == 'blank' || $type == 'ghost') {
    $buttonClass = $size == 'lg' ? 'button-lg-ghost' : ($size == 'sm' ? 'button-sm-ghost' : 'button-ghost');
} elseif ($type == 'outline' || $type == 'quickSubmit') {
    $buttonClass = $size == 'lg' ? 'button-lg-outline' : ($size == 'sm' ? 'button-sm-outline' : 'button-outline');
} elseif ($type == 'danger' || $color == 'red') {
    $buttonClass = $size == 'lg' ? 'button-lg-danger' : ($size == 'sm' ? 'button-sm-danger' : 'button-danger');
} elseif ($type == 'highlight' || $color == 'purple') {
    $buttonClass = $size == 'lg' ? 'button-lg-secondary' : ($size == 'sm' ? 'button-sm-secondary' : 'button-secondary');
} else {
    $buttonClass = $size == 'lg' ? 'button-lg' : ($size == 'sm' ? 'button-sm' : 'button');
}
?>

<?php if ($type == 'submit' || $type == 'quickSubmit') { ?>
    <button type="submit" <?= $attributes; ?> x-data="{ submitDisabled: false }" x-bind:disabled="submitDisabled" x-on:submit="submitDisabled = true" @click="submitting = true" :class="{'submitted bg-gray-100 dark:bg-gray-700': submitting}" class="<?= $class; ?> <?= $buttonClass; ?>" >
        
        <span :class="{'opacity-0': submitting}">
            <?= $value; ?>
        </span>
    
    </button>
<?php } elseif ($type == 'input') { ?>
    <input type="button" <?= $attributes; ?> class="<?= $class; ?> <?= $buttonClass; ?>"/>
    
<?php } else { ?>
    <button type="button" <?= $attributes; ?> class="<?= $class; ?> <?= $buttonClass; ?>">

        <?= !empty($icon) ? icon($iconLibrary ?? 'solid', $icon, $iconClass, $iconOptions) : ''; ?>

        <?php if ($value) { ?><span aria-label="<?= $value; ?>"><?= $value; ?></span><?php } ?>

        <?= !empty($tag) ? "<span class='badge-sm badge-primary'>{$tag}</span>" : ''; ?>

    </button><?php 
} ?>
