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
            foreach ( $fields as $key => $field ) {
                $field_name = $key;
                $fields_to_use[$field_name] = [];
                // $fields_to_use[$field_name]['value'] = $field['content'];
                $fields_to_use[$field_name]['value'] = $this->applyPlaceholders( $this->placeholders, $field['content'] );
                $fields_to_use[$field_name]['label'] = $field_name;
            }
            return $fields_to_use;
        }

        public function create_pdf( $fields ) {
            require_once TABLESOME_PATH . 'includes/lib/fpdf/pdf-html.php';
            $pdf = new \PDF_HTML(
                'P',
                'mm',
                'A4',
                $fields
            );
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
            $pdf->WriteHTML( $body );
            /* Footer */
            // $this->footer($pdf);
            /* Output */
            $pdf_output = $pdf->Output( 'S' );
            // error_log('$pdf_output: ' . $pdf_output);
            // $pdf->Output('F', TABLESOME_PATH . '/report.pdf');
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