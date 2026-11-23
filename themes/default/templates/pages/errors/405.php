<?php
/**
 * The 405 page: the path exists, but not for this method.
 *
 * @author Callistus Nwachukwu
 * @var string   $method  The request method.
 * @var string[] $allowed The methods the path accepts.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'status',
	[
		'diagram' => 'missing',
		'heading' => smliser__( 'Method Not Allowed' ),
		'message' => sprintf( smliser__( '%s is not supported here. This address accepts %s.' ), $method, implode( ', ', $allowed ) ),
		'actions' => [
			[ 'label' => smliser__( 'Go back' ), 'action' => 'back' ],
			[ 'label' => smliser__( 'Home' ), 'url' => $this->url( '/' )->url() ],
		],
		'detail'  => smliser__( 'HTTP 405' ),
	]
);
