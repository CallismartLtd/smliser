<?php
/**
 * The email verification page (the link in the verification email).
 *
 * @author Callistus Nwachukwu
 * @var bool   $verified Whether the address is now verified.
 * @var string $email    The verified address; empty on failure.
 * @var int    $linked   Licenses linked to the account by this visit.
 * @var string $reason   Why the link failed; empty on success.
 * @var bool   $resend   Whether the visitor may ask for a new link.
 * @var string $action   URL of the resend form.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$message    = $reason;
if ( $verified ) {
	$message = sprintf( smliser__( '%s is verified.' ), $email );

	if ( $linked > 0 ) {
		$message .= sprintf( smliser__( ' %d %s issued to this address %s now in your account.' ), $linked, 1 === $linked ? 'license' : 'licenses', 1 === $linked ? 'is' : 'are' );
	}
}

$this->partial(
	'status',
	[
		'tone'    => $verified ? 'success' : 'failure',
		'diagram' => $verified ? 'connected' : 'missing',
		'heading' => $verified ? smliser__( 'Email verified' ) : smliser__( 'This link did not work' ),
		'message' => $message,
		'actions' => [
			[ 'label' => smliser__( 'Go to your dashboard' ), 'url' => $this->urls()->client_dashboard_url()->url(), 'accent' => true ],
			[ 'label' => smliser__( 'Return to home' ), 'url' => $this->url( '/' )->url() ],
		],
	]
);
?>
<?php if ( $resend ) : ?>
	<div class="panel">
		<form method="post" action="<?php echo escUrl( $action ); ?>" data-smliser-form>
			<div class="notice" data-smliser-form-message hidden></div>
			<button type="submit" class="btn btn-block"><?php smliser_esc_html_e( 'Send me a new link' ); ?></button>
		</form>
	</div>
<?php endif; ?>