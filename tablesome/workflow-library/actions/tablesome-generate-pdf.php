<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;
use Tablesome\Includes\Modules\Workflow\Traits\Placeholder;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly
if ( !class_exists( '\\Tablesome\\Workflow_Library\\Actions\\Tablesome_Generate_Pdf' ) ) {
    class Tablesome_Generate_Pdf extends Action {
        use Placeholder;
        public $fields = [];

        public $action_meta = [];

        public $placeholders = [];

        public $wp_media_file_handler;

        public $tmp_direrctory_name = 'tablesome-tmp';

        public function __construct() {
            $this->wp_media_file_handler = new \Tablesome\Includes\Modules\WP_Media_File_Handler();
        }

        public function get_config() {
            return array(
                'id'          => 18,
                'name'        => 'create_pdf',
                'label'       => __( 'Create PDF', 'tablesome' ),
                'integration' => 'tablesome',
                'is_premium'  => true,
            );
        }

        public function do_action( $trigger_class, $trigger_instance ) {
            global $workflow_data;
            $_trigger_instance_id = $trigger_instance['_trigger_instance_id'];
            // Get the current trigger data with previous action outputs
            $current_trigger_outputs = ( isset( $workflow_data[$_trigger_instance_id] ) ? $workflow_data[$_trigger_instance_id] : [] );
            $this->placeholders = $this->getPlaceholdersFromKeyValues( $current_trigger_outputs );
            // error_log('generate_pdf do_action() $this->placeholders : ' . print_r($this->placeholders, true));
            $trigger_source_data = $trigger_class->trigger_source_data['data'];
            // error_log('generate_pdf generate_pdf() $trigger_source_data : ' . print_r($trigger_source_data, true));
            // error_log('generate_pdf generate_pdf() $trigger_instance : ' . print_r($trigger_instance, true));
            $this->action_meta = ( isset( $trigger_instance['action_meta'] ) ? $trigger_instance['action_meta'] : [] );
            $this->fields = ( isset( $this->action_meta['pdf_fields'] ) ? $this->action_meta['pdf_fields'] : [] );
            $fields_to_use = $this->get_fields_to_use( $this->fields, $trigger_source_data );
        }

        public function add_information_to_workflow_data( $file_info ) {
            global $tablesome_workflow_data;
            $data = array_merge( $this->get_config(), [
                "attachment_url" => $file_info['attachment_url'],
                'file_name'      => $file_info['file_name'],
            ] );
            array_push( $tablesome_workflow_data, $data );
        }

        public function get_fields_to_use( $fields, $source_data ) {
            // error_log('get_fields_to_use() $fields: ' . print_r($fields, true));
            // error_log('get_fields_to_use() $source_data : ' . print_r($source_data, true));
            $fields_to_use = [];
            // Build signature-aware placeholders that convert signature URLs to img tags
            $signature_placeholders = $this->get_signature_placeholders( $source_data );
            foreach ( $fields as $key => $field ) {
                $field_name = $key;
                $fields_to_use[$field_name] = [];
                // Apply regular placeholders first
                $content = $this->applyPlaceholders( $this->placeholders, ( isset( $field['content'] ) ? $field['content'] : '' ) );
                // Then apply signature placeholders (converts URLs to img tags)
                $content = $this->applyPlaceholders( $signature_placeholders, $content );
                $fields_to_use[$field_name]['value'] = $content;
                $fields_to_use[$field_name]['label'] = $field_name;
            }
            return $fields_to_use;
        }

        /**
         * Get signature placeholders that convert URLs to img tags for PDF
         * 
         * @param array $source_data The trigger source data containing field info
         * @return array Placeholders that convert signature URLs to img tags
         */
        protected function get_signature_placeholders( $source_data ) {
            $placeholders = [];
            if ( empty( $source_data ) ) {
                return $placeholders;
            }
            foreach ( $source_data as $field_name => $field_data ) {
                // Check if this is a signature field
                $is_signature = isset( $field_data['is_signature'] ) && $field_data['is_signature'] === true;
                $is_signature_type = isset( $field_data['type'] ) && in_array( strtolower( $field_data['type'] ), [
                    'signature',
                    'e_signature',
                    'esignature',
                    'pafe_signature'
                ] );
                if ( $is_signature || $is_signature_type ) {
                    $value = ( isset( $field_data['value'] ) ? $field_data['value'] : '' );
                    $file_url = ( isset( $field_data['file_url'] ) ? $field_data['file_url'] : $value );
                    if ( !empty( $file_url ) ) {
                        // Create the placeholder name
                        $placeholder_name = $this->getPlaceholderName( $field_name );
                        // Width-only constraint preserves aspect ratio
                        // 300px at 96 DPI = ~79mm, reasonable for signature on A4
                        // Get width from action meta or use default
                        $signature_width = ( isset( $this->action_meta['signature_width'] ) ? absint( $this->action_meta['signature_width'] ) : 300 );
                        // Apply filter for developer customization
                        $signature_width = apply_filters( 'tablesome_pdf_signature_width', $signature_width );
                        // Adding <br> tags to ensure it appears on its own line, left-aligned
                        // datasig attribute marks this as a signature so the PDF renderer
                        // uses this explicit width instead of the global image width setting.
                        $img_tag = sprintf( '<br><img src="%s" width="%d" datasig="1"><br>', esc_url( $file_url ), $signature_width );
                        // Store the replacement - the placeholder with the URL will be replaced with img tag
                        $placeholders[$file_url] = $img_tag;
                    }
                }
            }
            return $placeholders;
        }

        /**
         * Strip width/height attributes from non-signature <img> tags.
         * Signature images (marked with datasig attribute) keep their
         * explicit width so the PDF renderer respects signature_width.
         */
        protected function strip_image_dimensions( $html ) {
            return preg_replace_callback( '/<img\\b([^>]*)>/i', function ( $m ) {
                $attrs = $m[1];
                // Keep dimensions on signature images
                if ( stripos( $attrs, 'datasig' ) !== false ) {
                    return $m[0];
                }
                $attrs = preg_replace( '/\\s*width=["\'][^"\']*["\']/i', '', $attrs );
                $attrs = preg_replace( '/\\s*height=["\'][^"\']*["\']/i', '', $attrs );
                return '<img' . $attrs . '>';
            }, $html );
        }

        public function create_pdf( $fields ) {
            require_once __DIR__ . '/tablesome-pdf-html.php';
            $pdf = new Tablesome_PDF_HTML(
                'P',
                'mm',
                'A4',
                $fields
            );
            // Set configurable image width (default 500px)
            $image_width = ( isset( $this->action_meta['image_width'] ) ? absint( $this->action_meta['image_width'] ) : 300 );
            $image_width = apply_filters( 'tablesome_pdf_image_width', $image_width );
            $pdf->setImageWidth( $image_width );
            $pdf->setUploadDir( wp_upload_dir() );
            $pdf->AddPage();
            $title = $fields['title']['value'];
            $pdf->SetFont( 'Arial', 'B', 24 );
            $pdf->Cell(
                0,
                20,
                $title,
                "B",
                2
            );
            /* Body */
            $body = $fields['body']['value'];
            $pdf->Ln( 10 );
            $pdf->SetFont( 'Arial', '', 10 );
            // Fix space issue
            $body = " " . $body;
            // Strip width/height from non-signature images so the PDF renderer
            // uses the configured image_width setting instead of WordPress's
            // thumbnail dimensions (e.g. width="150").
            $body = $this->strip_image_dimensions( $body );
            $pdf->WriteHTML( $body );
            /* Footer */
            // $this->footer($pdf);
            $file_info = $this->save_file( $pdf );
            return $file_info;
        }

        public function save_file( $pdf ) {
            $this->wp_media_file_handler->include_core_files();
            $upload_dir = wp_upload_dir();
            $file_name = $this->get_file_name();
            $base_path = $upload_dir['basedir'] . '/' . $this->tmp_direrctory_name . '/';
            $file_path = $base_path . $file_name;
            $this->wp_media_file_handler->maybe_create_dir( $base_path );
            $pdf->Output( 'F', $file_path );
            $pdf->freeMemory();
            $url = $upload_dir['baseurl'] . '/' . $this->tmp_direrctory_name . '/' . $file_name;
            // Upload file to media library
            $attachment_id = $this->wp_media_file_handler->upload_file_from_url( $url, [
                'can_delete_temp_file_after_download' => true,
                'file_path'                           => $file_path,
            ] );
            $attachment_url = ( !empty( $attachment_id ) ? wp_get_attachment_url( $attachment_id ) : '' );
            return [
                'attachment_url' => $attachment_url,
                'file_name'      => $file_name,
            ];
        }

        private function get_file_name() {
            $file_name = 'tablesome_pdf_' . time() . '.pdf';
            return $file_name;
        }

        public function create_pdf_old( $fields ) {
            // error_log('create_pdf create_pdf() $fields : ' . print_r($fields, true));
            // $source_data = $this->get_row_values($event_params);
            // $fields = $this->fields;
            require_once TABLESOME_PATH . 'includes/lib/fpdf/pdf-html.php';
            $pdf = new \PDF_HTML();
            $pdf->AddPage();
            $pdf->SetFont( 'Arial', 'B', 24 );
            // $pdf->Cell(40, 10, 'Hello World!', 'B', 1);
            $pdf->Cell(
                0,
                20,
                'Title',
                "B",
                2
            );
            // $pdf->Cell(0, 10, '', 'B');
            // Line break
            $pdf->Ln( 10 );
            $pdf->SetFont( 'Arial', '', 10 );
            $ii = 0;
            // error_log('fields : ' . print_r($fields, true));
            foreach ( $fields as $key => $field ) {
                $value = ( isset( $field['value'] ) ? $field['value'] : '' );
                $label = ( isset( $field['label'] ) ? $field['label'] : '' );
                // error_log('label : ' . $label . ' value : ' . $value);
                if ( !is_string( $value ) && !is_numeric( $value ) || is_array( $value ) ) {
                    continue;
                }
                $content = $label . ' : ' . $value;
                // $pdf->Cell(20, 10, $key . ' : ' . $value);
                $pdf->Cell(
                    0,
                    10,
                    $content,
                    0,
                    1
                );
                $pdf->WriteHTML( $content );
                // $pdf->Cell(0, 10, 'Printing line number ' . $ii, 0, 1);
                $ii++;
            }
            // $pdf->Output();
            $pdf_output = $pdf->Output( 'S' );
            // error_log('$pdf_output: ' . $pdf_output);
            $pdf->Output( 'F', TABLESOME_PATH . '/report.pdf' );
            //  $upload = wp_upload_bits('a.pdf', null, file_get_contents($pdf_output));
            // echo $upload['file'], $upload['url'], $upload['error'];
            // error_log('$upload : ' . print_r($upload, true));
        }

        private function get_row_values( $event_params ) {
            // error_log('get_row_values');
            $source_data = $event_params['source_data'];
            $fields_map = $event_params['fields_map'];
            // error_log('source_data : ' . print_r($source_data, true));
            return $source_data;
        }

    }

}