<?php

namespace Tablesome\Components\TablesomeDB;

if ( !class_exists( '\\Tablesome\\Components\\TablesomeDB\\Access_Controller' ) ) {
    class Access_Controller {
        public function __construct() {
        }

        public function can_update_table( $args ) {
            // default
            $user_can_update = false;
            $table_meta_data = ( isset( $args['meta_data'] ) ? $args['meta_data'] : [] );
            $permissions = $this->get_permissions( $table_meta_data );
            $mode = ( isset( $args['mode'] ) ? $args['mode'] : '' );
            $is_admin_area = $mode == 'editor';
            $user_can_edit = ( isset( $permissions['can_edit'] ) ? $permissions['can_edit'] : false );
            if ( $is_admin_area || $user_can_edit ) {
                $user_can_update = true;
                return $user_can_update;
            }
            return $user_can_update;
        }

        public function get_permissions( $table_meta ) {
            // $dummy_data = $this->get_dummy_data();
            $access_control = ( isset( $table_meta['options']['access_control'] ) ? $table_meta['options']['access_control'] : [] );
            $enable_frontend_editing = ( isset( $access_control['enable_frontend_editing'] ) ? $access_control['enable_frontend_editing'] : false );
            $can_edit = $this->does_user_have_general_frontend_edit_access( $access_control );
            $editable_column_ids = [];
            $can_edit_columns = false;
            $can_delete_own_records = false;
            $can_add_records = false;
            $record_edit_access = "";
            if ( $can_edit ) {
                $editable_column_ids = ( isset( $access_control['editable_columns'] ) ? $access_control['editable_columns'] : $editable_column_ids );
                $can_edit_columns = ( count( $editable_column_ids ) > 0 ? true : false );
                $can_delete_own_records = ( isset( $access_control['can_delete_own_records'] ) ? $access_control['can_delete_own_records'] : false );
                $record_edit_access = ( isset( $access_control['record_edit_access'] ) ? $access_control['record_edit_access'] : '' );
                $can_add_records = ( isset( $access_control['can_add_records'] ) ? $access_control['can_add_records'] : false );
            }
            return [
                'enable_frontend_editing' => $enable_frontend_editing,
                'can_edit'                => $can_edit,
                'can_edit_columns'        => $can_edit_columns,
                'editable_columns'        => $editable_column_ids,
                'record_edit_access'      => $record_edit_access,
                'can_delete_own_records'  => $can_delete_own_records,
                'can_add_records'         => $can_add_records,
            ];
        }

        public function does_user_have_general_frontend_edit_access( $access_control ) {
            $enable_frontend_editing = ( isset( $access_control['enable_frontend_editing'] ) ? $access_control['enable_frontend_editing'] : false );
            // FALSE - ESCAPE CONDITIONS
            if ( !$enable_frontend_editing ) {
                return false;
            }
            return false;
            // TRUE - CONDITIONS
            if ( $this->is_site_admin() ) {
                return true;
            }
            if ( $this->is_user_role_allowed_in_settings( $access_control ) ) {
                return true;
            }
            // Does not match any of the above conditions
            return false;
            // $can_edit = $enable_frontend_editing && $user_can_allow_to_modify && tablesome_fs()->can_use_premium_code__premium_only() ? true : false;
        }

        public function is_user_role_allowed_in_settings( $access_control ) {
            $allowed_roles = ( isset( $access_control['allowed_roles'] ) ? $access_control['allowed_roles'] : [] );
            $allowed_roles[] = "administrator";
            $user = wp_get_current_user();
            $user_role = ( isset( $user->roles[0] ) ? $user->roles[0] : '' );
            $user_can_allow_to_modify = ( in_array( $user_role, $allowed_roles ) ? true : false );
            return $user_can_allow_to_modify;
        }

        public function is_site_admin() {
            $is_administrator = in_array( 'administrator', wp_get_current_user()->roles );
            $is_super_admin = is_super_admin();
            return $is_administrator || $is_super_admin;
        }

        public function can_edit_record( $record, $table_meta, $record_edit_access ) {
            if ( $record_edit_access == 'all_records' ) {
                return true;
            }
            $created_by = ( isset( $record->author_id ) ? $record->author_id : 0 );
            $current_user_id = get_current_user_id();
            if ( $record_edit_access == 'own_records' && $created_by == $current_user_id ) {
                return true;
            }
            return false;
        }

        public function can_delete_record( $record, $table_meta, $permissions ) {
            $can_delete_own_records = ( isset( $permissions['can_delete_own_records'] ) ? $permissions['can_delete_own_records'] : false );
            if ( !$can_delete_own_records ) {
                return false;
            }
            $created_by = ( isset( $record->author_id ) ? $record->author_id : 0 );
            $current_user_id = get_current_user_id();
            if ( $created_by == $current_user_id ) {
                return true;
            }
            return false;
        }

        // public function get_dummy_data()
        // {
        //     $file_path = TABLESOME_PATH . "includes/data/dummy/frontend-editing-dummy.json";
        //     $dummydata = get_data_from_json_file('', $file_path);
        //     // error_log('$dummydata : ' . print_r($dummydata, true));
        //     return $dummydata;
        // }
    }

    // end class
}