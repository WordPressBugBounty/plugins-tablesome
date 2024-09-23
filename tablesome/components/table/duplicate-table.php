<?php

namespace Tablesome\Components\Table;

if (!class_exists('\Tablesome\Components\Table\Duplicate_Table')) {
    class Duplicate_Table
    {

        public $core_table;
        public $datatable;

        public function __construct()
        {
            $this->core_table = new \Tablesome\Includes\Core\Table();
            $this->datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();
        }

        public function duplicate_table($post)
        {
            /** Get current user ID */
            $new_table_id = $this->copy_table_post($post);

            if (empty($new_table_id)) {
                return false;
            }

            $post_meta_copied = $this->copy_table_meta_data($post->ID, $new_table_id);

            if (!$post_meta_copied) {
                return false;
            }

            /** Copy the records from source table and insert the records into duplicated table  */
            // $records_copied = $this->core_table->copy_table_records($post->ID, $new_table_id);
            // if (false == $records_copied) {
            //     return false;
            // }

            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $old_table_instance = $tablesome_db->create_table_instance($post->ID);
            $table_copied = $tablesome_db->duplicate_table($old_table_instance, $new_table_id);
            if (false == $table_copied) {
                return false;
            }
            return $new_table_id;
        }

        public function copy_table_post($post)
        {
            $current_user = wp_get_current_user();
            $author_id = $current_user->ID;

            $table_data = array(
                'post_author' => $author_id,
                'post_content' => $post->post_content,
                'post_name' => 'copy-of-' . $post->post_name,
                'post_status' => $post->post_status,
                'post_title' => 'Copy of ' . $post->post_title,
                'post_type' => TABLESOME_CPT,
            );

            $new_table_id = $this->datatable->post->save(0, $table_data);
            return $new_table_id;
        }

        public function copy_table_meta_data($source_table_id, $new_table_id)
        {
            /** Get the source table meta data */
            $source_table_post_meta = get_tablesome_data($source_table_id);
            /** Get the source table trigger data */
            $source_table_trigger_meta = get_tablesome_table_triggers($source_table_id);

            /** add those data to the copied table */
            update_post_meta($new_table_id, 'tablesome_data', $source_table_post_meta);
            update_post_meta($new_table_id, 'tablesome_table_triggers', $source_table_trigger_meta);

            $meta_data_copied = metadata_exists('post', $new_table_id, 'tablesome_data');
            $trigger_data_copied = metadata_exists('post', $new_table_id, 'tablesome_table_triggers');

            return ($meta_data_copied && $trigger_data_copied) ? true : false;
        }
    }
}
