<?php
/** Load WordPress Bootstrap */
require_once($_SERVER['DOCUMENT_ROOT'].'/wp-admin/admin.php');

$parent_file = 'edit-comments.php';
$submenu_file = 'edit-comments.php';

wp_reset_vars( array('action') );
/**
 * Display error message at bottom of comments.
 *
 * @param string $msg Error Message. Assumed to contain HTML and be sanitized.
 */
function comment_footer_die( $msg ) {  //
	echo "<div class='wrap'><p>$msg</p></div>";
	include('admin-footer.php');
	die;
}
switch ( $action ) {
	case 'reportcomment' :
		$comment_id = absint( $_REQUEST['c'] );
		$post_id = absint( $_REQUEST['p'] );
		check_admin_referer( 'report-comment_' . $comment_id );

		if ( ! $comment = get_comment( $comment_id ) ) {
			comment_footer_die( __( 'Oops, no comment with this ID.' ) . sprintf( ' <a href="%s">' . __( 'Go back' ) . '</a>!', 'edit-comments.php' ) );
		}
		if ( ! current_user_can( 'edit_post', $comment->comment_post_ID ) ) {
			comment_footer_die( __( 'You are not allowed to edit comments on this post.' ) );
		}
		$report_url = clean_url( wp_nonce_url( "comment.php?action=deletecomment&p=$comment->comment_post_ID&c=$comment->comment_ID", "delete-comment_$comment->comment_ID" ) );

		if ( '' != wp_get_referer() && false == $noredir && false === strpos( wp_get_referer(), 'comment.php' ) ) {
			wp_redirect( wp_get_referer() );
		} else {
			if ( '' != wp_get_original_referer() && false == $noredir ) {
				wp_redirect( wp_get_original_referer() );
			} else {
				wp_redirect( admin_url( 'edit-comments.php' ) );
			}
		}
		;
		die();
		break;

	default :
		wp_die( __( 'Unknown action.' ) );
		break;
}
?>