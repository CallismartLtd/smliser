<?php
/**
 * New purchase request (admin) email template.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Purchases;

use SmartLicenseServer\Monetization\PurchaseRequest;

/**
 * Sent to the admin email address when a buyer submits a purchase request.
 *
 * Tokens, besides PurchaseRequestEmail's:
 *   {{buyer_email}}       The buyer's email address.
 *   {{payment_reference}} The buyer's payment reference.
 *   {{review_url}}        The request's review page.
 */
class AdminPurchaseRequestEmail extends PurchaseRequestEmail {

	/**
	 * @param PurchaseRequest $request    The purchase request.
	 * @param string          $to         Recipient email address.
	 * @param string          $product    What was bought.
	 * @param string          $review_url The request's review page.
	 */
	public function __construct(
		PurchaseRequest $request,
		string $to,
		string $product,
		private readonly string $review_url
	) {
		parent::__construct( $request, $to, $product );
	}

	public static function template_key() : string {
		return 'admin_purchase_request';
	}

	protected function subject() : string {
		return sprintf( smliser__( 'New purchase request #%d to review' ), $this->request->get_id() );
	}

	protected function preheader() : string {
		return smliser__( 'A buyer sent a payment receipt. Review it to issue the license.' );
	}

	protected function request_variables() : array {
		return [
			'{{buyer_email}}'       => $this->request->get_buyer_email(),
			'{{payment_reference}}' => $this->request->get_payment_reference() ?: 'None given',
			'{{review_url}}'        => $this->review_url,
		];
	}

	public function label() : string {
		return smliser__( 'New Purchase Request (Admin)' );
	}

	public function description() : string {
		return smliser__( 'Sent to the admin email address when a buyer submits a purchase request with a payment receipt.' );
	}

	public static function preview() : static {
		return new static( static::preview_request(), 'admin@example.com', smliser__( 'Demo Plugin — Yearly' ), 'https://example.com/admin/licenses/purchase-request?id=1024' );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => smliser__( 'A buyer sent a payment receipt. Check the payment, then approve the request to issue the license, or reject it.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'details',
				'type'      => 'detail_card',
				'rows'      => [
					[ 'label' => smliser__( 'Request' ), 'value' => '#{{request_id}}' ],
					[ 'label' => smliser__( 'Buyer' ), 'value' => '{{buyer_name}} ({{buyer_email}})' ],
					[ 'label' => smliser__( 'Product' ), 'value' => '{{product}}' ],
					[ 'label' => smliser__( 'Price' ), 'value' => '{{price}}' ],
					[ 'label' => smliser__( 'Payment reference' ), 'value' => '{{payment_reference}}' ],
				],
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'button',
				'type'      => 'button',
				'label'     => smliser__( 'Review the request' ),
				'url'       => '{{review_url}}',
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
