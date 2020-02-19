<?php
/**
 * Created by PhpStorm.
 * User: kaklo
 * Date: 16/01/18
 * Time: 2:15 PM
 */

namespace Boss\bbPress\GDPR;


if ( ! class_exists( '\Boss\bbPress\GDPR\BBP_GDPR_Topics' ) ) {

	class BBP_GDPR_Topics {

		public $post_type = 'topic';

		/**
		 * BBP_GDPR_Forum constructor.
		 */
		public function __construct() {

			add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ), 10 );
			add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erase_exporter' ), 10 );
		}

		function register_exporter( $exporters ) {
			$exporters['bbp-topic'] = array(
				'exporter_friendly_name' => __( 'Forum Topics' ),
				'callback' => array( $this, 'topics_exporter' ),
			);
			return $exporters;
		}

		function erase_exporter( $erasers ) {
			$erasers['bbp-topic'] = array(
				'eraser_friendly_name' => __( 'bbPress Topics' ),
				'callback'             => array( $this, 'topics_eraser' ),
			);
			return $erasers;
		}

		function topics_exporter( $email_address, $page = 1 ) {
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

			$topics_details = $this->get_topics( $user, $page, $per_page );
			$total = isset( $topics_details['total'] ) ? $topics_details['total'] : 0;
			$topics = isset( $topics_details['topics'] ) ? $topics_details['topics'] : array();

			if ( $total > 0 ) {
				foreach( $topics as $topic ) {
					$item_id = "bbp-topic-{$topic->ID}";

					$group_id = 'bbp-topics';

					$group_label = __( 'Forum Topics' );

					$permalink = get_permalink( $topic->ID );

					// Plugins can add as many items in the item data array as they want
					$data = array(
						array(
							'name'  => __( 'Topic Author' ),
							'value' => $user->display_name
						),
						array(
							'name'  => __( 'Topic Author Email' ),
							'value' => $user->user_email
						),
						array(
							'name'  => __( 'Topic Title' ),
							'value' => $topic->post_title
						),
						array(
							'name'  => __( 'Topic Content' ),
							'value' => $topic->post_content
						),
						array(
							'name'  => __( 'Topic Date' ),
							'value' => $topic->post_date
						),
						array(
							'name'  => __( 'Topic URL' ),
							'value' => $permalink
						),
						array(
							'name'  => __( 'Forum Name' ),
							'value' => get_the_title( $topic->post_parent )
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

		function get_topics( $user, $page, $per_page ) {
			$pp_args   = array(
				'post_type'      => $this->post_type,
				'author'         => $user->ID,
				'posts_per_page' => $per_page,
				'paged'          => $page
			);

			$the_query = new \WP_Query( $pp_args );

			if ( $the_query->have_posts() ) {
				return array( 'topics' => $the_query->posts, 'total' => $the_query->post_count );
			}
			return false;
		}


		function topics_eraser( $email_address, $page = 1 ) {
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

			$items = $this->get_topics( $user, 1, $per_page );

			if ( ! $items ) {
				return array(
					'items_removed'  => false,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			}

			$total	 = isset( $items['total'] ) ? $items['total'] : 0;
			$topics	 = ! empty( $items['topics'] ) ? $items['topics'] : array();

			if ( $total ) {
				foreach ( (array) $topics as $topic ) {
					$attachments = get_posts( array(
						'post_type' => 'attachment',
						'posts_per_page' => -1,
						'post_parent' => $topic->ID,
					) );

					if ( $attachments ) {
						foreach ( $attachments as $attachment ) {
							wp_delete_post( $attachment->ID, true );
						}
					}
					wp_delete_post( $topic->ID, true );
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