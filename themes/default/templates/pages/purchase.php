<?php
/**
 * The purchase page of a tier sold by manual payment (the local provider).
 *
 * @author Callistus Nwachukwu
 * @var string   $unavailable  Why the tier cannot be bought; empty when it can.
 * @var string   $app_name     The app the license is for.
 * @var string   $tier_name    The tier.
 * @var string   $term         The license term, e.g. "1 year".
 * @var int      $max_sites    Sites per license; -1 for unlimited.
 * @var string[] $features     Features of the tier.
 * @var string   $price        The price buyers pay.
 * @var string   $instructions How to pay (plain text).
 * @var string   $action       URL the form posts to.
 * @var string   $buyer_name   The signed-in buyer's name; empty for a guest.
 * @var string   $buyer_email  The signed-in buyer's email; empty for a guest.
 * @var string   $accept       Receipt MIME types for the file input.
 * @var string   $types_label  Receipt types, as people read them.
 * @var int      $max_mb       Largest receipt, in megabytes.
 * @var int      $max_bytes    Largest receipt, in bytes.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

if ( '' !== $unavailable ) {
	$this->partial(
		'status',
		[
			'tone'    => 'neutral',
			'diagram' => 'missing',
			'heading' => smliser__( 'Not available' ),
			'message' => $unavailable,
			'actions' => [
				[ 'label' => smliser__( 'Browse the repository' ), 'url' => $this->urls()->repository_url()->url(), 'accent' => true ],
				[ 'label' => smliser__( 'Return to home' ), 'url' => $this->url( '/' )->url() ],
			],
		]
	);
	return;
}

$guest = '' === $buyer_email;
?>
<div class="panel panel-wide">
	<h1 class="panel-heading"><?php smliser_esc_html_e( 'Buy' ); ?> <?php echo escHtml( $app_name ); ?></h1>
	<p class="panel-lead"><?php smliser_esc_html_e( 'Pay as shown below, then send us your receipt. Your license is issued once the payment is confirmed.' ); ?></p>

	<dl class="purchase-summary">
		<dt><?php smliser_esc_html_e( 'Option' ); ?></dt>
		<dd><?php echo escHtml( $tier_name ); ?></dd>
		<dt><?php smliser_esc_html_e( 'Price' ); ?></dt>
		<dd><?php echo escHtml( $price ); ?></dd>
		<dt><?php smliser_esc_html_e( 'License term' ); ?></dt>
		<dd><?php echo escHtml( $term ); ?></dd>
		<dt><?php smliser_esc_html_e( 'Sites' ); ?></dt>
		<dd><?php echo escHtml( -1 === $max_sites ? 'Unlimited' : (string) $max_sites ); ?></dd>
		<?php if ( [] !== $features ) : ?>
			<dt><?php smliser_esc_html_e( 'Includes' ); ?></dt>
			<dd><?php echo escHtml( implode( ', ', $features ) ); ?></dd>
		<?php endif; ?>
	</dl>

	<h2 class="field-label"><?php smliser_esc_html_e( 'How to pay' ); ?></h2>
	<div class="payment-instructions"><?php echo escHtml( $instructions ); ?></div>

	<form method="post" action="<?php echo escUrl( $action ); ?>" enctype="multipart/form-data" data-smliser-form>
		<div class="notice" data-smliser-form-message hidden></div>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Name on the license' ); ?></span>
			<input type="text" name="buyer_name" autocomplete="name" value="<?php echo escAttr( $buyer_name ); ?>" required>
		</label>
		<?php if ( $guest ) : ?>
			<label class="field">
				<span class="field-label"><?php smliser_esc_html_e( 'Email' ); ?></span>
				<input type="email" name="buyer_email" autocomplete="email" required>
				<span class="field-hint"><?php smliser_esc_html_e( 'The license is sent here.' ); ?></span>
			</label>
		<?php else : ?>
			<p class="field-hint"><?php /* translators: %s: buyer's email address. */ printf( smliser_esc_html__( 'The license is sent to %s and added to your account.' ), escHtml( $buyer_email ) ); ?></p>
		<?php endif; ?>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Payment reference (optional)' ); ?></span>
			<input type="text" name="payment_reference" maxlength="255">
			<span class="field-hint"><?php smliser_esc_html_e( 'E.g. the transfer ID or the name the payment was made in.' ); ?></span>
		</label>
		<div class="field">
			<span class="field-label" id="receipt-label"><?php smliser_esc_html_e( 'Payment receipt' ); ?></span>
			<input type="file" name="receipt" accept="<?php echo escAttr( $accept ); ?>" required aria-labelledby="receipt-label"
				data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_bytes ); ?>" data-label="<?php smliser_esc_attr_e( 'Drop your receipt here or click to choose' ); ?>">
		</div>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Note (optional)' ); ?></span>
			<textarea name="note" rows="3"></textarea>
		</label>
		<?php if ( $guest ) : ?>
			<label class="check">
				<input type="checkbox" name="create_account" value="1">
				<span><?php smliser_esc_html_e( 'Create an account for me, so I can find this license later.' ); ?></span>
			</label>
		<?php endif; ?>
		<button type="submit" class="btn btn-accent btn-block"><?php smliser_esc_html_e( 'Send for review' ); ?></button>
	</form>
</div>