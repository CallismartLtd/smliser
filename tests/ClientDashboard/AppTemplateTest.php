<?php
/**
 * The client dashboard's app page offers only what the user's role allows.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\ClientDashboard;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\HostedApps\OwnerAppScope;

final class AppTemplateTest extends TestCase {

	private const TEMPLATE = __DIR__ . '/../../themes/default/templates/pages/client/app.php';

	/**
	 * Render the app page with these actions allowed.
	 *
	 * @param list<string> $allowed
	 * @param string       $status
	 */
	private function render( array $allowed, string $status = 'active' ) : string {
		$can = array_fill_keys( array_keys( OwnerAppScope::ACTIONS ), false );

		foreach ( $allowed as $action ) {
			$can[ $action ] = true;
		}

		$vars = [
			'app'       => [
				'id' => 3, 'type' => 'wpplugin', 'type_label' => 'WordPress Plugin', 'name' => 'Demo <b>', 'slug' => 'demo',
				'version' => '1.0.0', 'status' => $status, 'status_label' => ucfirst( $status ), 'created' => 'today',
				'updated' => 'today', 'public_url' => 'https://example.test/repo/demo',
				'download_url' => $can['download'] ? 'https://example.test/download/app/wpplugin/3' : '',
			],
			'info'      => [ [ 'label' => 'License', 'value' => 'GPL', 'copy' => false ] ],
			'fields'    => [ [ 'name' => 'app_name', 'label' => 'Name', 'value' => 'Demo', 'type' => 'text', 'required' => true, 'hint' => '' ] ],
			'manifest'  => '{"name":"</textarea><script>"}',
			'can'       => $can,
			'statuses'  => [ 'active' => 'Active', 'inactive' => 'Inactive' ],
			'assets'    => [ [ 'type' => 'icon', 'label' => 'Icons', 'hint' => '', 'items' => [ [ 'name' => 'icon-128x128.png', 'url' => 'https://example.test/i.png' ] ] ] ],
			'artifacts' => [
				[ 'filename' => 'demo.zip', 'main' => true, 'size' => '1 KB', 'mime_type' => 'application/zip', 'updated' => 'today', 'download_url' => $can['download'] ? 'https://example.test/main' : '' ],
				[ 'filename' => 'demo.zip.sha256', 'main' => false, 'size' => '64 B', 'mime_type' => 'text/plain', 'updated' => 'today', 'download_url' => $can['download'] ? 'https://example.test/sha' : '' ],
			],
			'stats'     => $can['view_stats'] ? [ [ 'icon' => 'ti ti-download', 'label' => 'Total Downloads', 'value' => '7', 'color' => '#000' ] ] : [],
			'max_size'  => 1024,
			'list_url'  => 'https://example.test/apps',
			'actions'   => [
				'save' => 'S', 'status' => 'ST', 'delete' => 'D', 'asset_upload' => 'AU', 'asset_delete' => 'AD',
				'artifact_upload' => 'ARU', 'artifact_update' => 'ARE', 'artifact_delete' => 'ARD',
			],
		];

		return ( static function () use ( $vars ) : string {
			extract( $vars );
			ob_start();
			require self::TEMPLATE;

			return (string) ob_get_clean();
		} )();
	}

	public function test_a_read_only_role_gets_no_forms() : void {
		$html = $this->render( [ 'view' ] );

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'Download package', $html );
		$this->assertStringNotContainsString( 'Total Downloads', $html );
	}

	public function test_artifact_forms_follow_their_actions() : void {
		$html = $this->render( [ 'view', 'upload_artifacts' ] );

		$this->assertStringContainsString( 'action="ARU"', $html );
		$this->assertStringNotContainsString( 'action="ARE"', $html );
		$this->assertStringNotContainsString( 'action="ARD"', $html );

		$html = $this->render( [ 'view', 'edit_artifacts', 'delete_artifacts' ] );

		$this->assertStringContainsString( 'action="ARE"', $html );
		$this->assertStringContainsString( 'data-method="PUT"', $html );
		// The main package is never renamed, replaced or deleted as an artifact.
		$this->assertSame( 1, substr_count( $html, 'action="ARD"' ) );
		$this->assertStringNotContainsString( 'value="demo.zip"', $html );
	}

	public function test_trash_needs_the_trash_action() : void {
		$this->assertStringNotContainsString( 'value="trash"', $this->render( [ 'view', 'change_status' ] ) );
		$this->assertStringContainsString( 'value="trash"', $this->render( [ 'view', 'change_status', 'trash' ] ) );
	}

	public function test_a_suspended_app_offers_no_status_change() : void {
		$html = $this->render( [ 'view', 'change_status', 'trash', 'delete' ], 'suspended' );

		$this->assertStringNotContainsString( 'action="ST"', $html );
		$this->assertStringNotContainsString( 'action="D"', $html );
	}

	public function test_downloads_and_stats_follow_their_actions() : void {
		$html = $this->render( [ 'view', 'download', 'view_stats' ] );

		$this->assertStringContainsString( 'https://example.test/download/app/wpplugin/3', $html );
		$this->assertStringContainsString( 'Total Downloads', $html );
	}

	public function test_output_is_escaped() : void {
		$html = $this->render( [ 'view', 'update' ] );

		$this->assertStringNotContainsString( 'Demo <b>', $html );
		$this->assertStringNotContainsString( '</textarea><script>', $html );
		$this->assertStringContainsString( 'data-smliser-json-editor', $html );
	}
}
