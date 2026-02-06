<?php

namespace Tablesome\Includes\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tablesome Shortcode Block
 *
 * Registers a native WordPress Gutenberg block that integrates with
 * the CSF shortcode builder modal for selecting and configuring tables.
 *
 * @since 1.1.36
 */
class Tablesome_Shortcode_Block
{
    /**
     * Initialize the block registration.
     */
    public function init()
    {
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        // Unregister CSF's block after it registers, so only our native block appears
        add_action('enqueue_block_editor_assets', array($this, 'unregister_csf_block'), 20);
    }

    /**
     * Register the block type using block.json metadata.
     */
    public function register_block()
    {
        // Only register if block functions exist (WP 5.8+)
        if (!function_exists('register_block_type')) {
            return;
        }

        // Register the script handle that block.json references
        // This must happen before register_block_type() validates it
        $asset_file = TABLESOME_PATH . 'assets/bundles/tablesome-block.bundle.js';
        if (file_exists($asset_file)) {
            wp_register_script(
                'tablesome-block',
                TABLESOME_URL . 'assets/bundles/tablesome-block.bundle.js',
                array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
                TABLESOME_VERSION,
                true
            );
        }

        register_block_type(
            TABLESOME_PATH . 'assets/blocks/tablesome-shortcode/block.json'
        );
    }

    /**
     * Enqueue editor-specific assets for the block.
     */
    public function enqueue_editor_assets()
    {
        // Enqueue editor styles
        // Depends on 'csf' styles because the block uses CSF's shortcode builder modal.
        // CSF is bundled with Tablesome and always loaded in admin, so this dependency is safe.
        $style_file = TABLESOME_PATH . 'assets/blocks/tablesome-shortcode/editor.css';
        if (file_exists($style_file)) {
            wp_enqueue_style(
                'tablesome-block-editor',
                TABLESOME_URL . 'assets/blocks/tablesome-shortcode/editor.css',
                array('csf'),
                TABLESOME_VERSION
            );
        }
    }

    /**
     * Unregister CSF's block so only our native block appears in the inserter.
     *
     * CSF registers a block named 'csf/tablesome-shortcode' when show_in_editor is true.
     * We need show_in_editor=true for the modal to work, but we want our native
     * 'tablesome/shortcode' block to be the only one visible.
     */
    public function unregister_csf_block()
    {
        if (function_exists('unregister_block_type')) {
            $registry = \WP_Block_Type_Registry::get_instance();
            if ($registry->is_registered('csf/tablesome-shortcode')) {
                unregister_block_type('csf/tablesome-shortcode');
            }
        }
    }
}
