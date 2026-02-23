<?php if (!empty($chainedToID)) { ?>
<select <?= $attributes; ?> 
    class="<?= $class; ?> <?= $groupClass; ?> <?= $outerClass ?? 'w-full' ?> min-w-16 border py-2 text-gray-900  placeholder:text-gray-500 
    focus:ring-1 focus:ring-inset focus:ring-blue-500 sm:text-sm sm:leading-5"
    x-data="{
        parentValue: '',
        backup: null,
        init() {
            const parent = document.querySelector('#<?= $chainedToID; ?>');
            if (parent) {
                // Clone the select to preserve all options
                this.backup = this.$el.cloneNode(true);
                
                // Set initial parent value
                this.parentValue = parent.value;
                
                // Listen for parent changes
                parent.addEventListener('change', () => {
                    this.parentValue = parent.value;
                    this.updateOptions();
                });
                
                // Initial update
                this.updateOptions();
            }
        },
        updateOptions() {
            const selectedChild = this.$el.value;
            
            // Restore all options from backup
            this.$el.innerHTML = this.backup.innerHTML;
            this.$el.value = '';
            
            // Remove options that don't match the selected parent
            const options = Array.from(this.$el.options);
            options.forEach(option => {
                const optionValue = option.value;
                const optionClass = option.className;
                const hasParentClass = optionClass && optionClass.split(' ').includes(this.parentValue);
                
                // Keep empty options, placeholder options, and options matching parent
                if (optionValue !== '' && 
                    optionValue !== 'Please select...' && 
                    !hasParentClass) {
                    option.remove();
                }
            });
            
            // Try to restore the previously selected child value
            const matchingOption = Array.from(this.$el.options).find(
                opt => opt.value === selectedChild
            );
            if (matchingOption) {
                this.$el.value = selectedChild;
            }
            
            // Count selectable options (excluding empty and placeholder)
            const selectableOptions = Array.from(this.$el.options).filter(option => {
                return option.value !== '' && option.value !== 'Please select...';
            });
            
            // Disable child select if no valid options available
            this.$el.disabled = selectableOptions.length === 0;
        }
    }">
<?php } else { ?>
<select <?= $attributes; ?> 
    class="<?= $class; ?> <?= $groupClass; ?> <?= $outerClass ?? 'w-full' ?> min-w-16 border py-2 text-gray-900  placeholder:text-gray-500 
    focus:ring-1 focus:ring-inset focus:ring-blue-500 sm:text-sm sm:leading-5" >
<?php } ?>

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

</select>
