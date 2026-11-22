<?php
/**
 * The signed-out page.
 *
 * @author Callistus Nwachukwu
 * @var bool   $ended      Whether a session was ended by this request.
 * @var string $name       Who signed out; empty when $ended is false.
 * @var bool   $everywhere Arrived from "sign out everywhere".
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

[ $tone, $diagram, $heading, $message ] = match ( true ) {
	$ended      => [ 'success', 'session-closed', sprintf( smliser__( 'Good bye, %s' ), $name ), smliser__( 'Your session has been closed. You will need to sign in again to continue.' ) ],
	$everywhere => [ 'success', 'session-closed', smliser__( 'Signed out everywhere' ), smliser__( 'Every session of your account has been closed, on every device.' ) ],
	default     => [ 'neutral', 'no-session', smliser__( 'Already signed out' ), smliser__( 'There was no active session on this device.' ) ],
};

$this->partial(
	'status',
	[
		'tone'    => $tone,
		'diagram' => $diagram,
		'heading' => $heading,
		'message' => $message,
		'actions' => [
			[ 'label' => smliser__( 'Sign in' ), 'url' => $this->urls()->login_url()->url(), 'accent' => true ],
			[ 'label' => smliser__( 'Return to home' ), 'url' => $this->url( '/' )->url() ],
		],
	]
);
