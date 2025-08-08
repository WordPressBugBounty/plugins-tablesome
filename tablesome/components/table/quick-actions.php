<?php

namespace Tablesome\Components\Table;

if ( !class_exists( '\\Tablesome\\Components\\Table\\Quick_Actions' ) ) {
    class Quick_Actions {
        public function modify_table_row_actions( $actions, $post ) {
            // global $tablesome_tables_count_collection;
            $can_user_edit = current_user_can( 'edit_posts' );
            $is_tablesome_cpt = isset( $post ) && $post->post_type == TABLESOME_CPT;
            if ( !$can_user_edit || !$is_tablesome_cpt ) {
                return $actions;
            }
            $actions['export'] = '<a href="' . admin_url( 'admin.php?page=tablesome-export&action=export&table_id=' . $post->ID ) . '">' . __( 'Export', 'tablesome' ) . '</a>';
            $actions['duplicate'] = $this->get_duplicate_table_action_url( $post );
            $actions['empty'] = $this->get_empty_table_action_url( $post );
            return $actions;
        }

        private function get_duplicate_table_action_url( $table ) {
            /** duplicate action url */
            $url = wp_nonce_url( add_query_arg( array(
                'action'   => 'duplicate_the_tablesome_table',
                'table_id' => $table->ID,
            ), 'admin.php' ), TABLESOME_PLUGIN_BASE, 'tablesome_duplicate_nonce' );
            $title = __( 'Duplicate the table', 'tablesome' );
            $link_text = __( 'Duplicate', 'tablesome' );
            $classes = 'tablesome__table-action--duplicate';
            if ( !can_use_tablesome_premium() ) {
                $link_text .= '<span class="tablesome__premiumText">PRO</span>';
                $classes .= ' free';
                $url = tablesome_fs()->get_trial_url();
            }
            return '<a class="' . $classes . '" href="javascript:void(0);" data-url="' . $url . '" title="' . $title . '" rel="permalink">' . $link_text . '</a>';
        }

        private function get_empty_table_action_url( $table ) {
            /** empty table action url */
            $url = wp_nonce_url( add_query_arg( array(
                'action'   => 'empty_the_tablesome_table',
                'table_id' => $table->ID,
            ), 'admin.php' ), TABLESOME_PLUGIN_BASE, 'tablesome_empty_table_nonce' );
            $title = __( 'Empty the table (remove all rows)', 'tablesome' );
            $link_text = __( 'Empty Table', 'tablesome' );
            $classes = 'tablesome__table-action--emptyTable';
            if ( !can_use_tablesome_premium() ) {
                $link_text .= '<span class="tablesome__premiumText">PRO</span>';
                $classes .= ' free';
                $url = tablesome_fs()->get_trial_url();
            }
            return '<a class="' . $classes . '" href="javascript:void(0);" data-url="' . $url . '" title="' . $title . '" rel="permalink">' . $link_text . '</a>';
        }

        public function show_notices() {
            $post_type = ( isset( $_GET['post_type'] ) ? tablesome_wp_kses( $_GET['post_type'] ) : '' );
            $action = ( isset( $_GET['link_action'] ) ? tablesome_wp_kses( $_GET['link_action'] ) : '' );
            $status = ( isset( $_GET['status'] ) ? tablesome_wp_kses( $_GET['status'] ) : '' );
            if ( $post_type != TABLESOME_CPT || !in_array( $action, ['DUPLICATE', 'EMPTY_TABLE', 'EMAIL_LOGS'] ) ) {
                return;
            }
            $status_content = array(
                'MISSING_POST_ID'                           => array(
                    'class'   => 'notice-warning',
                    'message' => __( 'Missing Tablesome table ID ', 'tablesome' ),
                ),
                'SESSION_EXPIRED'                           => array(
                    'class'   => 'notice-warning',
                    'message' => __( 'Session Expired, Please try again.', 'tablesome' ),
                ),
                'INVALID_POST_ID'                           => array(
                    'class'   => 'notice-warning',
                    'message' => __( 'Invalid Table ID', 'tablesome' ),
                ),
                'TABLE_NOT_DUPLICATE'                       => array(
                    'class'   => 'notice-warning',
                    'message' => __( 'Table Can\'t duplicated, Please try again', 'tablesome' ),
                ),
                'TABLE_DUPLICATED'                          => array(
                    'class'   => 'notice-success',
                    'message' => __( 'Table duplicated successfully.', 'tablesome' ),
                ),
                'TABLE_EMPTIED'                             => array(
                    'class'   => 'notice-success',
                    'message' => __( 'Table emptied successfully.', 'tablesome' ),
                ),
                'TABLE_NOT_EMPTIED'                         => array(
                    'class'   => 'notice-warning',
                    'message' => __( "Table Can't emptied, Please try again", "tablesome" ),
                ),
                'CREATED_EMAIL_LOGS_TRIGGER_TABLE'          => array(
                    'class'   => 'notice-success',
                    'message' => __( 'Email logs trigger table created successfully.', 'tablesome' ),
                ),
                'PUBLISH_EMAIL_LOGS_TABLE'                  => array(
                    'class'   => 'notice-warning',
                    'message' => __( 'Please publish the table to start storing the email logs.', 'tablesome' ) . '<a href="javascript:void(0);" style="margin-left: 3px;" class="tablesome_publish_table_action">Publish Table</a>',
                ),
                'PUBLISHED_TABLE'                           => array(
                    'class'   => 'notice-success',
                    'message' => __( 'Table published successfully.', 'tablesome' ),
                ),
                'FAILED_TO_CREATE_EMAIL_LOGS_TRIGGER_TABLE' => array(
                    'class'   => 'notice-warning',
                    'message' => __( 'Failed to create email logs trigger table, Please try again.', 'tablesome' ),
                ),
            );
            $notice_class = ( isset( $status_content[$status]['class'] ) ? $status_content[$status]['class'] : 'notice-warning' );
            $desc = ( isset( $status_content[$status] ) ? $status_content[$status]['message'] : 'Something went wrong to duplicating the table, try again later' );
            $html = '<div class="helpie-notice notice ' . $notice_class . ' is-dismissible" >';
            $html .= '<p>' . tablesome_wp_kses( $desc ) . '</p>';
            $html .= '</div>';
            add_action( 'admin_notices', function () use($html) {
                //  echo tablesome_wp_kses($html);
                $allowed_html = tablesome_allowed_html();
                echo wp_kses( $html, $allowed_html );
            } );
        }

    }

}