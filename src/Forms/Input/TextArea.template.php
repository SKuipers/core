<textarea <?= $attributes; ?> 
    class="<?= $class; ?> w-full rounded-md border py-2 text-gray-900 font-sans placeholder:text-gray-400 focus:ring-1 focus:ring-inset focus:ring-blue-500 sm:text-sm sm:leading-6" ><?= $text; ?></textarea>

<?php if (!empty($autosize)) { ?>
    <script type="text/javascript">autosize($("#<?= $id; ?>"));</script>
<?php } ?>
