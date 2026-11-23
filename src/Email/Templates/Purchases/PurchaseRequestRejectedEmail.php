<?php
/**
 * Purchase request rejected email template.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Purchases;

/**
 * Sent to a buyer when their purchase request is rejected, with the reason.
 *
 * Tokens, besides PurchaseRequestEmail's:
 *   {{reason}} Why the request was rejected.
 */
class PurchaseRequestRejectedEmail extends PurchaseRequestEmail {

	public static function template_key() : string {
		return 'purchase_request_rejected';
	}

	protected function subject() : string {
		return sprintf( smliser__( 'Your purchase request #%d was not approved' ), $this->request->get_id() );
	}

	protected function preheader() : string {
		return smliser__( 'We could not confirm the payment for your purchase request.' );
	}

	protected function request_variables() : array {
		return [ '{{reason}}' => $this->request->get_review_note() ];
	}

	public function label() : string {
		return smliser__( 'Purchase Request Rejected' );
	}

	public function description() : string {
		return smliser__( 'Sent to a buyer when their purchase request is rejected, with the reviewer\'s reason.' );
	}

	public static function preview() : static {
		return new static(
			static::preview_request( [ 'status' => 'rejected', 'review_note' => smliser__( 'We did not receive a payment matching this receipt.' ) ] ),
			'jane@example.com',
			smliser__( 'Demo Plugin — Yearly' )
		);
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Hi {{buyer_name}},' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => smliser__( 'We reviewed your purchase request #{{request_id}} for {{product}} and could not approve it. The reason:' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'reason',
				'type'      => 'banner',
				'tone'      => 'warning',
				'content'   => '{{reason}}',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you believe this is a mistake, contact us at {{support_email}} and mention request #{{request_id}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
