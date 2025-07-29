<?php

if (!function_exists('csf_validate_number_of_records_per_page')) {

    function csf_validate_number_of_records_per_page($value)
    {
        if ($value > 200) {
            return esc_html__('Value should be 200 or below', 'tablesome');
        } else if ($value < 1) {
            return esc_html__('Value should be 1 at least', 'tablesome');
        }
    }
}
