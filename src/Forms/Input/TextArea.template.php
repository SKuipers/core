<div class="relative <?= $outerClass ?? 'flex-grow'; ?>">
<textarea <?= $attributes; ?> 
    class="<?= $class; ?> textarea"><?= $text; ?></textarea>

<?php if (!empty($autosize)) { ?>
    <script type="text/javascript">autosize($("#<?= $id; ?>"));</script>
<?php } ?>

</div>
