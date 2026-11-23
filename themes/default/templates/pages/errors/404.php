<?php
/**
 * The 404 page: no route matches the path.
 *
 * @author Callistus Nwachukwu
 * @var string $path The requested path.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'status',
	[
		'diagram' => 'missing',
		'heading' => smliser__( 'Not Found' ),
		'message' => smliser__( 'The requested resource could not be found.' ),
		'actions' => [
			[ 'label' => smliser__( 'Go back' ), 'action' => 'back' ],
			[ 'label' => smliser__( 'Home' ), 'url' => $this->url( '/' )->url() ],
		],
		'detail'  => sprintf( smliser__( 'HTTP 404 · %s' ), $path ),
	]
);
