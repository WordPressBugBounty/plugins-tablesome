<?php 

if (!function_exists('pluginator_allowed_html_tags')) {
    function pluginator_allowed_html_tags()
    {
        global $allowedposttags;

        $allowed_form_attrs = array(
            'type' => true,
            'name' => true,
            'value' => true,
            'placeholder' => true,
            'id' => true,
            'class' => true,
            'required' => true,
            'size' => true,
            'action' => true,
            'method' => true,
            'novalidate' => true,
            'tabindex' => true,
            'for' => true,
            'width' => true,
            'height' => true,
            'title' => true,
            'cols' => true,
            'rows' => true,
            'disabled' => true,
            'readonly' => true,
            'style' => true,
            'role' => true,
            'data-*' => true,
            'aria-live' => true,
            'aria-describedby' => true,
            'aria-details' => true,
            'aria-label' => true,
            'aria-labelledby' => true,
            'aria-hidden' => true,
            'aria-required' => true,
            'aria-invalid' => true,
            'checked' => true,
        );

        $allowedposttags['form'] = $allowed_form_attrs;
        $allowedposttags['input'] = $allowed_form_attrs;
        $allowedposttags['select'] = $allowed_form_attrs;
        $allowedposttags['option'] = $allowed_form_attrs;
        $allowedposttags['textarea'] = $allowed_form_attrs;
        $allowedposttags['script'] = $allowed_form_attrs;
        // $allowedposttags['a'] = $allowed_form_attrs;
    }
}

if (!function_exists('pluginator_safe_kses')) {
    function pluginator_safe_kses($content)
    {
        pluginator_allowed_html_tags();
        $allowed_form_tags = wp_kses_allowed_html('post');
        return wp_kses($content, $allowed_form_tags);
    }
}
