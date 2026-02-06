<?php

namespace Tablesome\Components\CellTypes;

if (!class_exists('\Tablesome\Components\CellTypes\Button')) {
    class Button
    {
        public function __construct()
        {
            add_filter("tablesome_get_cell_data", [$this, 'get_button_data']);
        }

        public function get_button_data($cell)
        {
            if ($cell['type'] != 'button' || empty(trim($cell['value']))) {
                return $cell;
            }

            // Check if URL has a scheme - if not, prepend // for protocol-relative URL
            // Simple string check is fast and works with IDN URLs, unusual schemes, etc.
            // esc_url() provides proper XSS protection for HTML output
            $has_scheme = (strpos($cell['value'], '://') !== false);
            // Don't prepend // for dangerous protocols (let esc_url handle them directly)
            $dangerous_protocol = preg_match('/^(javascript|data|vbscript):/i', $cell['value']);
            $raw_link = ($has_scheme || $dangerous_protocol) ? $cell['value'] : '//' . $cell['value'];
            $link = esc_url($raw_link);

            $link_text = isset($cell['linkText']) && !empty($cell['linkText']) ? $cell['linkText'] : $cell['value'];
            $link_text = url_shorten($link_text, $length = 40);
            $link_text = esc_html($link_text);

            $cell["html"] = '<a class="tablesome__button" href="' . $link . '" target="_blank">' . $this->get_button($link_text) . '</a>';

            return $cell;
        }

        public function get_button($link_text)
        {
            return '<button type="submit" class="button">' . $link_text . '</button>';
        }
    } // END CLASS
}
