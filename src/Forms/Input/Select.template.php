<select <?= $attributes; ?> 
    class="<?= $class; ?> <?= $groupClass; ?> <?= $outerClass ?? 'w-full' ?> min-w-16 border dark:border-gray-700 py-2 text-gray-900 dark:text-gray-100 dark:bg-gray-800 placeholder:text-gray-500 dark:placeholder:text-gray-400
    focus:ring-1 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400 sm:text-sm sm:leading-5" >

    <?php if (isset($placeholder) && empty($multiple)) { ?>
        <option value=""><?= __($placeholder); ?></option>
    <?php } ?>

    <?php foreach ($options as $optLabel => $optGroup) { ?>

        <?php if (!empty($optLabel)) { ?>
           <optgroup label="— <?= $optLabel; ?> —">
        <?php } ?>

        <?php foreach ($optGroup as $value => $option) { ?>
            <option value="<?= $value; ?>" class="<?= $option['class']; ?>" <?= $option['selected']; ?>><?= $option['label']; ?></option>
        <?php } ?>

        <?php if (!empty($optLabel)) { ?>
           </optgroup>
        <?php } ?>

    <?php } ?>

</select><?php 

if (!empty($chainedToID)) { ?>
    <script type="text/javascript">
        $(function() {$("#<?= $id; ?>").chainedTo("#<?= $chainedToID; ?>");});
    </script>
<?php } ?>
