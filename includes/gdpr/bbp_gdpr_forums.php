<?php
/**
 * Created by PhpStorm.
 * User: kaklo
 * Date: 16/01/18
 * Time: 2:15 PM
 */

namespace Boss\bbPress\GDPR;


if ( ! class_exists( '\Boss\bbPress\GDPR\BBP_GDPR_Forum' ) ) {

	class BBP_GDPR_Forums {

		public $post_type = 'forum';

		/**
		 * BBP_GDPR_Forum constructor.
		 */
		public function __construct() {

			add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ), 10 );
			add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erase_exporter' ), 10 );
		}

		function register_exporter( $exporters ) {
			$exporters['bbp-forum'] = array(
				'exporter_friendly_name' => __( 'Forums' ),
				'callback' => array( $this, 'forums_exporter' ),
			);
			return $exporters;
		}

		function erase_exporter( $erasers ) {
			$erasers['bbp-forum'] = array(
				'eraser_friendly_name' => __( 'bbPress Forums' ),
				'callback'             => array( $this, 'forums_eraser' ),
			);
			return $erasers;
		}

		function forums_exporter( $email_address, $page = 1 ) {
			$per_page = 500; // Limit us to avoid timing out
			$page = (int) $page;

			$export_items = array();

			$user = get_user_by( 'email' , $email_address );
			if ( false === $user ) {
				return array(
					'data' => $export_items,
					'done' => true,
				);
			}

			$forums_details = $this->get_forums( $user, $page, $per_page );
			$total = isset( $forums_details['total'] ) ? $forums_details['total'] : 0;
			$forums = isset( $forums_details['forums'] ) ? $forums_details['forums'] : array();

			if ( $total > 0 ) {
				foreach( $forums as $forum ) {
					$item_id = "bbp-forum-{$forum->ID}";

					$group_id = 'bbp-forums';

					$group_label = __( 'Forums' );

					$permalink = get_permalink( $forum->ID );

					// Plugins can add as many items in the item data array as they want
					$data = array(
						array(
							'name'  => __( 'Forum Author' ),
							'value' => $user->display_name
						),
						array(
							'name'  => __( 'Forum Author Email' ),
							'value' => $user->user_email
						),
						array(
							'name'  => __( 'Forum Title' ),
							'value' => $forum->post_title
						),
						array(
							'name'  => __( 'Forum Content' ),
							'value' => $forum->post_content
						),
						array(
							'name'  => __( 'Forum Date' ),
							'value' => $forum->post_date
						),
						array(
							'name'  => __( 'Forum URL' ),
							'value' => $permalink
						),
					);

					$export_items[] = array(
						'group_id'    => $group_id,
						'group_label' => $group_label,
						'item_id'     => $item_id,
						'data'        => $data,
					);
				}
			}

			$offset = ( $page - 1 ) * $per_page;

			// Tell core if we have more comments to work on still
			$done = $total < $offset;
			return array(
				'data' => $export_items,
				'done' => $done,
			);
		}

		function get_forums( $user, $page, $per_page ) {
			$pp_args   = array(
				'post_type'      => $this->post_type,
				'author'         => $user->ID,
				'posts_per_page' => $per_page,
				'paged'          => $page
			);

			$the_query = new \WP_Query( $pp_args );

			if ( $the_query->have_posts() ) {
				return array( 'forums' => $the_query->posts, 'total' => $the_query->post_count );
			}
			return false;
		}


		function forums_eraser( $email_address, $page = 1 ) {
			$per_page = 500; // Limit us to avoid timing out
			$page = (int) $page;

			$user = get_user_by( 'email' , $email_address );
			if ( false === $user ) {
				return array(
					'items_removed'  => false,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			}

			$items_removed  = false;
			$items_retained = false;
			$messages    = array();

			$items = $this->get_forums( $user, 1, $per_page );

			if ( ! $items ) {
				return array(
					'items_removed'  => false,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			}

			$total	 = isset( $items['total'] ) ? $items['total'] : 0;
			$forums	 = ! empty( $items['forums'] ) ? $items['forums'] : array();

			if ( $total ) {
				foreach ( (array) $forums as $forum ) {
					$attachments = get_posts( array(
						'post_type' => 'attachment',
						'posts_per_page' => -1,
						'post_parent' => $forum->ID,
					) );

					if ( $attachments ) {
						foreach ( $attachments as $attachment ) {
							wp_delete_post( $attachment->ID, true );
						}
					}
					wp_delete_post( $forum->ID, true );
					$items_removed = true;
				}
			}

			$offset = ( $page - 1 ) * $per_page;

			// Tell core if we have more comments to work on still
			$done = $total < $offset;

			return array(
				'items_removed'  => $items_removed,
				'items_retained' => $items_retained,
				'messages'       => $messages,
				'done'           => $done,
			);
		}
	}
}