<?php
/**
 * Purchase request received email template.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Purchases;

/**
 * Sent to a buyer when their purchase request and receipt are received.
 */
class PurchaseRequestReceivedEmail extends PurchaseRequestEmail {

	public static function template_key() : string {
		return 'purchase_request_received';
	}

	protected function subject() : string {
		return sprintf( smliser__( 'We received your purchase request #%d' ), $this->request->get_id() );
	}

	protected function preheader() : string {
		return smliser__( 'Your payment receipt is being reviewed. Your license follows once the payment is confirmed.' );
	}

	public function label() : string {
		return smliser__( 'Purchase Request Received' );
	}

	public function description() : string {
		return smliser__( 'Sent to a buyer when their purchase request and payment receipt are received.' );
	}

	public static function preview() : static {
		return new static( static::preview_request(), 'jane@example.com', smliser__( 'Demo Plugin — Yearly' ) );
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
				'content'   => smliser__( 'Thank you. We received your purchase request and your payment receipt. We will check the payment and email your license as soon as it is confirmed.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'details',
				'type'      => 'detail_card',
				'rows'      => [
					[ 'label' => smliser__( 'Request' ), 'value' => '#{{request_id}}' ],
					[ 'label' => smliser__( 'Product' ), 'value' => '{{product}}' ],
					[ 'label' => smliser__( 'Price' ), 'value' => '{{price}}' ],
				],
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you have a question about your request, contact us at {{support_email}} and mention request #{{request_id}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
