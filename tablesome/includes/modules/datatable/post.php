<?php

namespace Tablesome\Includes\Modules\Datatable;

if (!class_exists('\Tablesome\Includes\Modules\Datatable\Post')) {
    class Post
    {
        public function save($post_id, $post_data)
        {
            $post_data['post_content'] = "Tablesome Table";

            if ($post_id != 0) {
                $post_data['ID'] = $post_id;
                return wp_update_post($post_data);
            }

            return wp_insert_post($post_data);
        }

    } // END CLASS
} //
