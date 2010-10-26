<?php
/**
 * Sites List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 */
class WP_Sites_Table extends WP_List_Table {

	function WP_Sites_Table() {
		parent::WP_List_Table( array(
			'screen' => 'sites-network',
			'plural' => 'sites',
		) );
	}

	function check_permissions() {
		if ( ! current_user_can( 'manage_sites' ) )
			wp_die( __( 'You do not have permission to access this page.' ) );
	}

	function prepare_items() {
		global $s, $mode, $wpdb;

		$mode = ( empty( $_REQUEST['mode'] ) ) ? 'list' : $_REQUEST['mode'];

		$per_page = $this->get_items_per_page( 'sites_network_per_page' );

		$pagenum = $this->get_pagenum();

		$s = isset( $_REQUEST['s'] ) ? stripslashes( trim( $_REQUEST[ 's' ] ) ) : '';
		$like_s = esc_sql( like_escape( $s ) );

		$query = "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";

		if ( isset( $_REQUEST['searchaction'] ) ) {
			if ( 'name' == $_REQUEST['searchaction'] ) {
				$query .= " AND ( {$wpdb->blogs}.domain LIKE '%{$like_s}%' OR {$wpdb->blogs}.path LIKE '%{$like_s}%' ) ";
			} elseif ( 'id' == $_REQUEST['searchaction'] ) {
				$query .= " AND {$wpdb->blogs}.blog_id = '{$like_s}' ";
			} elseif ( 'ip' == $_REQUEST['searchaction'] ) {
				$query = "SELECT *
					FROM {$wpdb->blogs}, {$wpdb->registration_log}
					WHERE site_id = '{$wpdb->siteid}'
					AND {$wpdb->blogs}.blog_id = {$wpdb->registration_log}.blog_id
					AND {$wpdb->registration_log}.IP LIKE ( '%{$like_s}%' )";
			}
		}

		$order_by = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'id';
		if ( $order_by == 'registered' ) {
			$query .= ' ORDER BY registered ';
		} elseif ( $order_by == 'lastupdated' ) {
			$query .= ' ORDER BY last_updated ';
		} elseif ( $order_by == 'blogname' ) {
			$query .= ' ORDER BY domain ';
		} else {
			$order_by = 'id';
			$query .= " ORDER BY {$wpdb->blogs}.blog_id ";
		}

		$order = ( isset( $_REQUEST['order'] ) && 'DESC' == strtoupper( $_REQUEST['order'] ) ) ? "DESC" : "ASC";
		$query .= $order;

		$total = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT( blog_id )', $query ) );

		$query .= " LIMIT " . intval( ( $pagenum - 1 ) * $per_page ) . ", " . intval( $per_page );
		$this->items = $wpdb->get_results( $query, ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page' => $per_page,
		) );
	}

	function no_items() {
		_e( 'No sites found.' );
	}

	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete' );
		$actions['spam'] = _x( 'Mark as Spam', 'site' );
		$actions['notspam'] = _x( 'Not Spam', 'site' );

		return $actions;
	}

	function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' == $which )
			$this->view_switcher( $mode );
	}

	function get_columns() {
		$blogname_columns = ( is_subdomain_install() ) ? __( 'Domain' ) : __( 'Path' );
		$sites_columns = array(
			'cb'          => '<input type="checkbox" />',
			'blogname'    => $blogname_columns,
			'lastupdated' => __( 'Last Updated' ),
			'registered'  => _x( 'Registered', 'site' ),
			'users'       => __( 'Users' )
		);

		if ( has_filter( 'wpmublogsaction' ) )
			$sites_columns['plugins'] = __( 'Actions' );

		$sites_columns = apply_filters( 'wpmu_blogs_columns', $sites_columns );

		return $sites_columns;
	}

	function get_sortable_columns() {
		return array(
			'id'          => 'id',
			'blogname'    => 'blogname',
			'lastupdated' => 'lastupdated',
			'registered'  => 'registered',
		);
	}

	function display_rows() {
		global $current_site, $mode;

		$status_list = array(
			'archived' => array( 'site-archived', __( 'Archived' ) ),
			'spam'     => array( 'site-spammed', _x( 'Spam', 'site' ) ),
			'deleted'  => array( 'site-deleted', __( 'Deleted' ) ),
			'mature'   => array( 'site-mature', __( 'Mature' ) )
		);

		$class = '';
		foreach ( $this->items as $blog ) {
			$class = ( 'alternate' == $class ) ? '' : 'alternate';
			reset( $status_list );

			$blog_states = array();
			foreach ( $status_list as $status => $col ) {
				if ( get_blog_status( $blog['blog_id'], $status ) == 1 ) {
					$class = $col[0];
					$blog_states[] = $col[1];
				}
			}
			$blog_state = '';
			if ( ! empty( $blog_states ) ) {
				$state_count = count( $blog_states );
				$i = 0;
				$blog_state .= ' - ';
				foreach ( $blog_states as $state ) {
					++$i;
					( $i == $state_count ) ? $sep = '' : $sep = ', ';
					$blog_state .= "<span class='post-state'>$state$sep</span>";
				}
			}
			echo "<tr class='$class'>";

			$blogname = ( is_subdomain_install() ) ? str_replace( '.'.$current_site->domain, '', $blog['domain'] ) : $blog['path'];

			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $columns as $column_name => $column_display_name ) {
				switch ( $column_name ) {
					case 'cb': ?>
						<th scope="row" class="check-column">
							<input type="checkbox" id="blog_<?php echo $blog['blog_id'] ?>" name="allblogs[]" value="<?php echo esc_attr( $blog['blog_id'] ) ?>" />
						</th>
					<?php
					break;

					case 'id': ?>
						<th valign="top" scope="row">
							<?php echo $blog['blog_id'] ?>
						</th>
					<?php
					break;

					case 'blogname': ?>
						<td class="column-title">
							<a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ); ?>" class="edit"><?php echo $blogname . $blog_state; ?></a>
							<?php
							if ( 'list' != $mode )
								echo '<p>' . sprintf( _x( '%1$s &#8211; <em>%2$s</em>', '%1$s: site name. %2$s: site tagline.' ), get_blog_option( $blog['blog_id'], 'blogname' ), get_blog_option( $blog['blog_id'], 'blogdescription ' ) ) . '</p>';

							// Preordered.
							$actions = array(
								'edit' => '', 'backend' => '',
								'activate' => '', 'deactivate' => '',
								'archive' => '', 'unarchive' => '',
								'spam' => '', 'unspam' => '',
								'delete' => '',
								'visit' => '',
							);

							$actions['edit']	= '<span class="edit"><a href="' . esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ) . '">' . __( 'Edit' ) . '</a></span>';
							$actions['backend']	= "<span class='backend'><a href='" . esc_url( get_admin_url( $blog['blog_id'] ) ) . "' class='edit'>" . __( 'Dashboard' ) . '</a></span>';
							if ( $current_site->blog_id != $blog['blog_id'] ) {
								if ( get_blog_status( $blog['blog_id'], 'deleted' ) == '1' )
									$actions['activate']	= '<span class="activate"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=activateblog&amp;id=' . $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to activate the site %s' ), $blogname ) ) ) ) . '">' . __( 'Activate' ) . '</a></span>';
								else
									$actions['deactivate']	= '<span class="activate"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=deactivateblog&amp;id=' . $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to deactivate the site %s' ), $blogname ) ) ) ) . '">' . __( 'Deactivate' ) . '</a></span>';

								if ( get_blog_status( $blog['blog_id'], 'archived' ) == '1' )
									$actions['unarchive']	= '<span class="archive"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=unarchiveblog&amp;id=' .  $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to unarchive the site %s.' ), $blogname ) ) ) ) . '">' . __( 'Unarchive' ) . '</a></span>';
								else
									$actions['archive']	= '<span class="archive"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=archiveblog&amp;id=' . $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to archive the site %s.' ), $blogname ) ) ) ) . '">' . _x( 'Archive', 'verb; site' ) . '</a></span>';

								if ( get_blog_status( $blog['blog_id'], 'spam' ) == '1' )
									$actions['unspam']	= '<span class="spam"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=unspamblog&amp;id=' . $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to unspam the site %s.' ), $blogname ) ) ) ) . '">' . _x( 'Not Spam', 'site' ) . '</a></span>';
								else
									$actions['spam']	= '<span class="spam"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=spamblog&amp;id=' . $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to mark the site %s as spam.' ), $blogname ) ) ) ) . '">' . _x( 'Spam', 'site' ) . '</a></span>';

								$actions['delete']	= '<span class="delete"><a href="' . esc_url( network_admin_url( 'edit.php?action=confirm&amp;action2=deleteblog&amp;id=' . $blog['blog_id'] . '&amp;msg=' . urlencode( sprintf( __( 'You are about to delete the site %s.' ), $blogname ) ) ) ) . '">' . __( 'Delete' ) . '</a></span>';
							}

							$actions['visit']	= "<span class='view'><a href='" . esc_url( get_home_url( $blog['blog_id'] ) ) . "' rel='permalink'>" . __( 'Visit' ) . '</a></span>';
							$actions = array_filter( $actions );
							echo $this->row_actions( $actions );
					?>
						</td>
					<?php
					break;

					case 'lastupdated': ?>
						<td valign="top">
							<?php
							if ( 'list' == $mode )
								$date = 'Y/m/d';
							else
								$date = 'Y/m/d \<\b\r \/\> g:i:s a';
							echo ( $blog['last_updated'] == '0000-00-00 00:00:00' ) ? __( 'Never' ) : mysql2date( $date, $blog['last_updated'] ); ?>
						</td>
					<?php
					break;
				case 'registered': ?>
						<td valign="top">
						<?php
						if ( $blog['registered'] == '0000-00-00 00:00:00' )
							echo '&#x2014;';
						else
							echo mysql2date( $date, $blog['registered'] );
						?>
						</td>
				<?php
				break;
					case 'users': ?>
						<td valign="top">
							<?php
							$blogusers = get_users( array( 'blog_id' => $blog['blog_id'], 'number' => 6) );
							if ( is_array( $blogusers ) ) {
								$blogusers_warning = '';
								if ( count( $blogusers ) > 5 ) {
									$blogusers = array_slice( $blogusers, 0, 5 );
									$blogusers_warning = __( 'Only showing first 5 users.' ) . ' <a href="' . esc_url( get_admin_url( $blog['blog_id'], 'users.php' ) ) . '">' . __( 'More' ) . '</a>';
								}
								foreach ( $blogusers as $user_object ) {
									echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $user_object->ID ) ) . '">' . esc_html( $user_object->user_login ) . '</a> ';
									if ( 'list' != $mode )
										echo '( ' . $user_object->user_email . ' )';
									echo '<br />';
								}
								if ( $blogusers_warning != '' )
									echo '<strong>' . $blogusers_warning . '</strong><br />';
							}
							?>
						</td>
					<?php
					break;

					case 'plugins': ?>
						<?php if ( has_filter( 'wpmublogsaction' ) ) { ?>
						<td valign="top">
							<?php do_action( 'wpmublogsaction', $blog['blog_id'] ); ?>
						</td>
						<?php } ?>
					<?php break;

					default: ?>
						<?php if ( has_filter( 'manage_blogs_custom_column' ) ) { ?>
						<td valign="top">
							<?php do_action( 'manage_blogs_custom_column', $column_name, $blog['blog_id'] ); ?>
						</td>
						<?php } ?>
					<?php break;
				}
			}
			?>
			</tr>
			<?php
		}
	}
}

?>