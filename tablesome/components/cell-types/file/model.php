<?php

namespace Tablesome\Components\CellTypes\File;

if (!class_exists('\Tablesome\Components\CellTypes\File\Model')) {
    class Model
    {
        public function __construct()
        {

        }

        public function get_media_data($data)
        {
            // error_log('get_media_data() data : ' . print_r($data, true));
            $attachment_id = $data["value"];
            $post = get_post($attachment_id);

            // error_log('get_media_data() attachment_id : ' . $attachment_id);
            // error_log('get_media_data() post : ' . print_r($post, true));
            // error_log('get_media_data() data : ' . print_r($data, true));
            // $post_mime_type = get_post_mime_type($attachment_id);

            if (!isset($post->post_mime_type) && empty($post->post_mime_type)) {
                return $attachment_id;
            }

            $post_mime_type = $post->post_mime_type;
            $media_type = explode('/', $post_mime_type)[0]; // video|image
            // $meta_data = wp_get_attachment_metadata($attachment_id);
            // error_log('meta_data : ' . print_r($meta_data, true));

            // error_log('get_media_data() $media_type : ' . $media_type);

            if ($media_type == 'image') {
                $file_url = wp_get_attachment_image_url($attachment_id, "full");
            } else {
                $file_url = wp_get_attachment_url($attachment_id);
            }

            $link = isset($data["link"]) && !empty($data["link"]) ? $data["link"] : $file_url;

            // error_log('get_media_data() file_url : ' . $file_url);
            // error_log('get_media_data() $$link  : ' . $link);

            $data = [
                'attachment' => [
                    "url" => $file_url,
                ],
                'type' => 'file',
                'url' => $file_url,
                'link' => $link,
                'name' => $post->post_name,
                'mime_type' => $post_mime_type,
            ];

            return $data;
        }

    } // END CLASS
}
