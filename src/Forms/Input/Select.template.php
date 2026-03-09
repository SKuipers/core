<select <?= $attributes; ?> 
    class="<?= $class; ?> <?= $outerClass ?> select" >

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
