(function ($) {
    'use strict';

    $(function () {
        var $form = $('#ele2gb-batch-convert-form');

        if (!$form.length) {
            return;
        }

        var $selectAll = $form.find('.ele2gb-select-all');

        $selectAll.on('change', function () {
            var isChecked = $(this).is(':checked');
            $form.find('tbody .check-column input[type="checkbox"]').prop('checked', isChecked);
        });

        $form.on('submit', function () {
            var $checked = $form.find('tbody .check-column input[type="checkbox"]:checked');

            if ($checked.length === 0) {
                if (window.ele2gbBatchWizard && window.ele2gbBatchWizard.noSelection) {
                    window.alert(window.ele2gbBatchWizard.noSelection);
                }
                return false;
            }

            return true;
        });
    });
})(jQuery);