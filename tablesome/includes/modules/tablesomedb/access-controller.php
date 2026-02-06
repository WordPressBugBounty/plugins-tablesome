<?php

namespace Tablesome\Components\TablesomeDB;

if (!class_exists('\Tablesome\Components\TablesomeDB\Access_Controller')) {
    class Access_Controller
    {
        public function __construct()
        {
        }

        public function can_update_table($args)
        {
            // default
            $user_can_update = false;

            $table_meta_data = isset($args['meta_data']) ? $args['meta_data'] : [];
            $permissions = $this->get_permissions($table_meta_data);

            $mode = isset($args['mode']) ? $args['mode'] : '';
            $is_admin_area = ($mode == 'editor');
            $user_can_edit = isset($permissions['can_edit']) ? $permissions['can_edit'] : false;

            if ($is_admin_area || $user_can_edit) {
                $user_can_update = true;
                return $user_can_update;
            }

            return $user_can_update;
        }

        public function get_permissions($table_meta, $current_user = null)
        {
            // $dummy_data = $this->get_dummy_data();
            $access_control = isset($table_meta['options']['access_control']) ? $table_meta['options']['access_control'] : [];
            $enable_frontend_editing = isset($access_control['enable_frontend_editing']) ? $access_control['enable_frontend_editing'] : false;

            $can_edit = $this->does_user_have_general_frontend_edit_access($access_control, $current_user);

            $editable_column_ids = [];
            $can_edit_columns = false;
            $can_delete_own_records = false;
            $can_add_records = false;
            $record_edit_access = "";

            if ($can_edit) {
                $editable_column_ids = isset($access_control['editable_columns']) ? $access_control['editable_columns'] : $editable_column_ids;
                $can_edit_columns = count($editable_column_ids) > 0 ? true : false;
                $can_delete_own_records = isset($access_control['can_delete_own_records']) ? $access_control['can_delete_own_records'] : false;
                $record_edit_access = isset($access_control['record_edit_access']) ? $access_control['record_edit_access'] : '';
                $can_add_records = isset($access_control['can_add_records']) ? $access_control['can_add_records'] : false;
            }

            return [
                'enable_frontend_editing' => $enable_frontend_editing,
                // 'user_can_allow_to_modify' => $user_can_allow_to_modify,
                'can_edit' => $can_edit,
                'can_edit_columns' => $can_edit_columns,
                'editable_columns' => $editable_column_ids,
                'record_edit_access' => $record_edit_access,
                'can_delete_own_records' => $can_delete_own_records,
                'can_add_records' => $can_add_records,
            ];
        }

        public function does_user_have_general_frontend_edit_access($access_control, $current_user = null)
        {
            $enable_frontend_editing = isset($access_control['enable_frontend_editing']) ? $access_control['enable_frontend_editing'] : false;

            // FALSE - ESCAPE CONDITIONS
            if (!$enable_frontend_editing) {
                return false;
            }

            if (!can_use_tablesome_premium()) {
                return false;
            }

            // TRUE - CONDITIONS
            if ($this->is_site_admin_with_user($current_user)) {
                return true;
            }

            if ($this->is_user_role_allowed_in_settings($access_control, $current_user)) {
                return true;
            }

            // Does not match any of the above conditions
            return false;
            // $can_edit = $enable_frontend_editing && $user_can_allow_to_modify && tablesome_fs()->can_use_premium_code__premium_only() ? true : false;
        }

        public function is_user_role_allowed_in_settings($access_control, $current_user = null)
        {
            $allowed_roles = isset($access_control['allowed_roles']) ? $access_control['allowed_roles'] : [];
            $allowed_roles[] = "administrator";
            if ($current_user === null) {
                $current_user = wp_get_current_user();
            }
            $user_role = isset($current_user->roles[0]) ? $current_user->roles[0] : '';
            $user_can_allow_to_modify = in_array($user_role, $allowed_roles) ? true : false;
            return $user_can_allow_to_modify;
        }

        public function is_site_admin()
        {
            $is_administrator = in_array('administrator', wp_get_current_user()->roles);
            $is_super_admin = is_super_admin();

            return $is_administrator || $is_super_admin;
        }

        public function is_site_admin_with_user($current_user = null)
        {
            if ($current_user === null) {
                $current_user = wp_get_current_user();
            }
            $is_administrator = in_array('administrator', $current_user->roles);
            $is_super_admin = is_super_admin();

            return $is_administrator || $is_super_admin;
        }

        public function can_edit_record($record, $table_meta, $record_edit_access, $current_user = null, $current_user_id = null)
        {
            // Allow all records
            if ($record_edit_access == 'all_records') {
                return true;
            }
            
            // Check email-based access
            if ($record_edit_access == 'email_column_match') {
                return $this->can_edit_record_by_email($record, $table_meta, $current_user);
            }
            
            // Check author-based access (own records)
            $created_by = isset($record->author_id) ? $record->author_id : 0;
            if ($current_user_id === null) {
                $current_user_id = get_current_user_id();
            }
            if ($record_edit_access == 'own_records' && $created_by == $current_user_id) {
                return true;
            }
            
            return false;
        }
        
        public function can_edit_record_by_email($record, $table_meta, $current_user = null)
        {
            $access_control = isset($table_meta['options']['access_control']) ? $table_meta['options']['access_control'] : [];
            $email_column_id = isset($access_control['email_column_for_row_access']) ? $access_control['email_column_for_row_access'] : null;
            
            // If no email column configured, deny access
            if ($email_column_id === null || $email_column_id === '') {
                error_log('Tablesome Security: Email column match enabled but no email column configured');
                return false;
            }
            
            // Get current user's email (use cached user if provided)
            if ($current_user === null) {
                $current_user = wp_get_current_user();
            }
            if (!$current_user || !$current_user->user_email) {
                error_log('Tablesome Security: No current user or email found');
                return false;
            }
            $user_email = strtolower(trim($current_user->user_email));
            
            // Get email value from record
            $column_key = 'column_' . $email_column_id;
            $record_email = isset($record->$column_key) ? $record->$column_key : '';
            $record_email = strtolower(trim($record_email));
            
            // Check if emails match
            if ($user_email === $record_email) {
                return true;
            }
            
            // Log failed access attempt
            error_log('Tablesome Security: Email mismatch - User: ' . $user_email . ' vs Record: ' . $record_email);
            return false;
        }

        public function can_delete_record($record, $table_meta, $permissions, $current_user = null, $current_user_id = null)
        {
            $can_delete_own_records = isset($permissions['can_delete_own_records']) ? $permissions['can_delete_own_records'] : false;
            if (!$can_delete_own_records) {
                return false;
            }
            
            // Get record edit access mode
            $access_control = isset($table_meta['options']['access_control']) ? $table_meta['options']['access_control'] : [];
            $record_edit_access = isset($access_control['record_edit_access']) ? $access_control['record_edit_access'] : '';
            
            // Allow deletion of all records
            if ($record_edit_access === 'all_records') {
                return true;
            }
            
            // Check email-based access
            if ($record_edit_access === 'email_column_match') {
                return $this->can_edit_record_by_email($record, $table_meta, $current_user);
            }
            
            // Check author-based access (own records)
            $created_by = isset($record->author_id) ? $record->author_id : 0;
            if ($current_user_id === null) {
                $current_user_id = get_current_user_id();
            }
            if ($record_edit_access === 'own_records' && $created_by == $current_user_id) {
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
    } // end class
}
